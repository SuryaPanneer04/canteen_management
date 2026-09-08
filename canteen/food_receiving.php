<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$role = $_SESSION['role_name'] ?? '';

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

if (!in_array($role, ['Canteen', 'Super Admin'], true)) {
    header('Location: ../index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| LOGGED-IN USER
|--------------------------------------------------------------------------
*/

$loginUserId = (int)($_SESSION['user_id'] ?? 0);

if ($loginUserId <= 0) {

    /*
    | Some versions of the project store the logged-in user
    | information inside $_SESSION['user_data'].
    */

    if (!empty($_SESSION['user_data']) && is_array($_SESSION['user_data'])) {

        foreach ($_SESSION['user_data'] as $key => $value) {

            if (in_array($key, ['id', 'user_id'], true)) {
                $loginUserId = (int)$value;
                break;
            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| PAGE MESSAGE
|--------------------------------------------------------------------------
*/

$success = '';
$error   = '';


/*
|--------------------------------------------------------------------------
| RECEIVE FOOD
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['receive_food'])) {

    $transferId = (int)($_POST['transfer_id'] ?? 0);

    if ($transferId <= 0) {

        $error = 'Invalid food transfer selected.';

    } else {

        try {

            $con->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | GET TRANSFER
            |--------------------------------------------------------------------------
            */

            $stmt = $con->prepare("
                SELECT
                    ft.id,
                    ft.transfer_no,
                    ft.preparation_id,
                    ft.food_id,
                    ft.quantity,
                    ft.transfer_date,
                    ft.status,
                    ft.sent_by,

                    fi.food_code,
                    fi.food_name,
                    fi.unit

                FROM food_transfers ft

                INNER JOIN food_items fi
                    ON fi.id = ft.food_id

                WHERE ft.id = ?

                FOR UPDATE
            ");

            $stmt->execute([$transferId]);

            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$transfer) {

                throw new Exception('Food transfer was not found.');

            }


            /*
            |--------------------------------------------------------------------------
            | CHECK STATUS
            |--------------------------------------------------------------------------
            */

            if ($transfer['status'] !== 'Sent') {

                throw new Exception(
                    'This food transfer has already been received.'
                );

            }


            $quantity = (float)$transfer['quantity'];

            if ($quantity <= 0) {

                throw new Exception(
                    'The transferred food quantity is invalid.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CHECK DUPLICATE SERVING RECORD
            |--------------------------------------------------------------------------
            */

            $check = $con->prepare("
                SELECT id
                FROM canteen_food_serving
                WHERE transfer_id = ?
                LIMIT 1
            ");

            $check->execute([$transferId]);

            if ($check->fetch()) {

                throw new Exception(
                    'A receiving record already exists for this transfer.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CREATE CANTEEN SERVING RECORD
            |--------------------------------------------------------------------------
            |
            | The full transferred quantity is received.
            |
            | Initial values:
            |
            | received_qty = transferred quantity
            | served_qty   = 0
            | remaining_qty = transferred quantity
            | pax           = 0
            |
            */

            $insert = $con->prepare("
                INSERT INTO canteen_food_serving
                (
                    transfer_id,
                    food_id,
                    serving_date,
                    received_qty,
                    served_qty,
                    remaining_qty,
                    pax,
                    recorded_by
                )
                VALUES
                (
                    :transfer_id,
                    :food_id,
                    :serving_date,
                    :received_qty,
                    0,
                    :remaining_qty,
                    0,
                    :recorded_by
                )
            ");

            $insert->execute([
                ':transfer_id'   => $transferId,
                ':food_id'       => (int)$transfer['food_id'],
                ':serving_date'  => $transfer['transfer_date'],
                ':received_qty'  => $quantity,
                ':remaining_qty' => $quantity,
                ':recorded_by'   => $loginUserId
            ]);


            /*
            |--------------------------------------------------------------------------
            | UPDATE FOOD TRANSFER
            |--------------------------------------------------------------------------
            */

            $update = $con->prepare("
                UPDATE food_transfers

                SET
                    status = 'Received',
                    received_by = :received_by,
                    received_at = NOW()

                WHERE id = :id
                  AND status = 'Sent'
            ");

            $update->execute([
                ':received_by' => $loginUserId,
                ':id'          => $transferId
            ]);


            if ($update->rowCount() !== 1) {

                throw new Exception(
                    'Unable to update the food transfer.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $con->commit();

            $success = 'Food received successfully.';


        } catch (Throwable $e) {

            if ($con->inTransaction()) {
                $con->rollBack();
            }

            $error = $e->getMessage();

        }

    }

}


/*
|--------------------------------------------------------------------------
| FETCH FOOD SENT BY KITCHEN
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT
        ft.id,
        ft.transfer_no,
        ft.preparation_id,
        ft.food_id,
        ft.quantity,
        ft.transfer_date,
        ft.status,
        ft.sent_at,
        ft.received_at,

        fi.food_code,
        fi.food_name,
        fi.unit

    FROM food_transfers ft

    INNER JOIN food_items fi
        ON fi.id = ft.food_id

    WHERE ft.status = 'Sent'

    ORDER BY
        ft.transfer_date DESC,
        ft.id DESC
");

$pendingTransfers = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| RECEIVED HISTORY
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT
        ft.id,
        ft.transfer_no,
        ft.quantity,
        ft.transfer_date,
        ft.received_at,

        fi.food_code,
        fi.food_name,
        fi.unit,

        cfs.received_qty,
        cfs.served_qty,
        cfs.remaining_qty,
        cfs.pax

    FROM food_transfers ft

    INNER JOIN food_items fi
        ON fi.id = ft.food_id

    INNER JOIN canteen_food_serving cfs
        ON cfs.transfer_id = ft.id

    WHERE ft.status = 'Received'

    ORDER BY
        ft.received_at DESC,
        ft.id DESC

    LIMIT 100
");

$receivedTransfers = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| SUMMARY STATS
|--------------------------------------------------------------------------
*/

$pendingQtyTotal = 0.0;

foreach ($pendingTransfers as $row) {
    $pendingQtyTotal += (float)$row['quantity'];
}

$receivedQtyTotal = 0.0;
$remainingQtyTotal = 0.0;

foreach ($receivedTransfers as $row) {
    $receivedQtyTotal += (float)$row['received_qty'];
    $remainingQtyTotal += (float)$row['remaining_qty'];
}


/*
|--------------------------------------------------------------------------
| COMMON HEADER
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';

?>

<div class="main-content">

<div class="page-body">

    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->

    <div class="page-header">

        <div>

            <h4 class="page-header-title">
                <i class="fa-solid fa-hand-holding-heart me-2"></i>
                Food Receiving
            </h4>

            <p class="page-header-subtitle">
                Receive food sent from Kitchen.
            </p>

        </div>

    </div>


    <!-- =========================================================
         SUCCESS MESSAGE
    ========================================================== -->

    <?php if ($success !== ''): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <i class="fa-solid fa-circle-check me-2"></i>

            <?= e($success) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         ERROR MESSAGE
    ========================================================== -->

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <i class="fa-solid fa-circle-exclamation me-2"></i>

            <?= e($error) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         SUMMARY STATS
    ========================================================== -->

    <div class="row g-3 mb-4">

        <div class="col-md-4">

            <div class="stat-card d-flex align-items-center gap-3">

                <div class="stat-icon stat-icon-amber">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                </div>

                <div>
                    <div class="stat-label">Pending Receipt</div>
                    <div class="stat-value">
                        <?= count($pendingTransfers) ?>
                    </div>
                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="stat-card d-flex align-items-center gap-3">

                <div class="stat-icon stat-icon-green">
                    <i class="fa-solid fa-check"></i>
                </div>

                <div>
                    <div class="stat-label">Received Qty</div>
                    <div class="stat-value">
                        <?= number_format($receivedQtyTotal, 2) ?>
                    </div>
                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="stat-card d-flex align-items-center gap-3">

                <div class="stat-icon stat-icon-primary">
                    <i class="fa-solid fa-bowl-food"></i>
                </div>

                <div>
                    <div class="stat-label">Remaining to Serve</div>
                    <div class="stat-value">
                        <?= number_format($remainingQtyTotal, 2) ?>
                    </div>
                </div>

            </div>

        </div>

    </div>


    <!-- =========================================================
         FOOD WAITING FOR RECEIVING
    ========================================================== -->

    <div class="content-card mb-4">

        <div class="content-card-header">

            <h5 class="mb-0">

                <i class="fa-solid fa-truck-ramp-box me-2"></i>

                Food Sent by Kitchen

            </h5>

            <span class="badge badge-pending">

                <?= count($pendingTransfers) ?> Pending

            </span>

        </div>


        <div class="content-card-body p-0">

            <?php if (empty($pendingTransfers)): ?>

                <div class="text-center py-5 text-muted">

                    <i
                        class="fa-solid fa-box-open fa-3x mb-3"
                    ></i>

                    <h5>
                        No Food Pending
                    </h5>

                    <p class="mb-0">
                        There is currently no food waiting to be received.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th>#</th>

                                <th>
                                    Transfer No
                                </th>

                                <th>
                                    Food
                                </th>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Quantity
                                </th>

                                <th>
                                    Status
                                </th>

                                <th class="text-center">
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($pendingTransfers as $index => $row): ?>

                                <tr>

                                    <td>
                                        <?= $index + 1 ?>
                                    </td>


                                    <td>

                                        <strong>
                                            <?= e($row['transfer_no']) ?>
                                        </strong>

                                    </td>


                                    <td>

                                        <div class="food-cell">

                                            <span class="food-icon">
                                                <i class="fa-solid fa-utensils"></i>
                                            </span>

                                            <div>

                                                <strong>
                                                    <?= e($row['food_name']) ?>
                                                </strong>

                                                <br>

                                                <small class="text-muted">

                                                    <?= e($row['food_code']) ?>

                                                </small>

                                            </div>

                                        </div>

                                    </td>


                                    <td>

                                        <?= date(
                                            'd-m-Y',
                                            strtotime($row['transfer_date'])
                                        ) ?>

                                    </td>


                                    <td>

                                        <strong>
                                            <?= number_format(
                                                (float)$row['quantity'],
                                                2
                                            ) ?>
                                        </strong>

                                        <?= e($row['unit']) ?>

                                    </td>


                                    <td>

                                        <span class="badge badge-pending">

                                            <i class="fa-solid fa-clock me-1"></i>

                                            Sent

                                        </span>

                                    </td>


                                    <td class="text-center">

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to receive this food?');"
                                            class="d-inline"
                                        >

                                            <input
                                                type="hidden"
                                                name="transfer_id"
                                                value="<?= (int)$row['id'] ?>"
                                            >


                                            <button
                                                type="submit"
                                                name="receive_food"
                                                class="btn btn-primary btn-sm"
                                            >

                                                <i class="fa-solid fa-check me-1"></i>

                                                Receive

                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- =========================================================
         RECEIVED HISTORY
    ========================================================== -->

    <div class="content-card">

        <div class="content-card-header">

            <h5 class="mb-0">

                <i class="fa-solid fa-clock-rotate-left me-2"></i>

                Received Food History

            </h5>

        </div>


        <div class="content-card-body p-0">

            <?php if (empty($receivedTransfers)): ?>

                <div class="text-center py-5 text-muted">

                    <i
                        class="fa-solid fa-inbox fa-3x mb-3"
                    ></i>

                    <h5>
                        No Receiving History
                    </h5>

                    <p class="mb-0">
                        Received food will appear here.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th>#</th>

                                <th>
                                    Transfer No
                                </th>

                                <th>
                                    Food
                                </th>

                                <th>
                                    Received Date
                                </th>

                                <th>
                                    Received Qty
                                </th>

                                <th>
                                    Served Qty
                                </th>

                                <th>
                                    Remaining
                                </th>

                                <th>
                                    Pax
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($receivedTransfers as $index => $row): ?>

                                <tr>

                                    <td>
                                        <?= $index + 1 ?>
                                    </td>


                                    <td>

                                        <strong>
                                            <?= e($row['transfer_no']) ?>
                                        </strong>

                                    </td>


                                    <td>

                                        <div class="food-cell">

                                            <span class="food-icon">
                                                <i class="fa-solid fa-utensils"></i>
                                            </span>

                                            <div>

                                                <strong>
                                                    <?= e($row['food_name']) ?>
                                                </strong>

                                                <br>

                                                <small class="text-muted">

                                                    <?= e($row['food_code']) ?>

                                                </small>

                                            </div>

                                        </div>

                                    </td>


                                    <td>

                                        <?php if (!empty($row['received_at'])): ?>

                                            <?= date(
                                                'd-m-Y H:i',
                                                strtotime($row['received_at'])
                                            ) ?>

                                        <?php else: ?>

                                            -

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <strong>

                                            <?= number_format(
                                                (float)$row['received_qty'],
                                                2
                                            ) ?>

                                        </strong>

                                        <?= e($row['unit']) ?>

                                    </td>


                                    <td>

                                        <?= number_format(
                                            (float)$row['served_qty'],
                                            2
                                        ) ?>

                                        <?= e($row['unit']) ?>

                                    </td>


                                    <td>

                                        <?php

                                        $remainingQty =
                                            (float)$row['remaining_qty'];

                                        ?>

                                        <span
                                            class="badge remaining-pill<?= $remainingQty <= 0 ? ' is-zero' : '' ?>"
                                        >

                                            <?= number_format(
                                                $remainingQty,
                                                2
                                            ) ?>

                                            <?= e($row['unit']) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?= (int)$row['pax'] ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>