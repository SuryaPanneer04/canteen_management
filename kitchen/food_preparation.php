<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/store_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Food Preparation';

$error = null;
$success = flash('success');

$userId = (int)($_SESSION['user_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| ACCESS
|--------------------------------------------------------------------------
*/

$allowedRoles = ['Kitchen', 'Super Admin'];

if (!isset($_SESSION['userrole']) || !in_array($_SESSION['userrole'], $allowedRoles, true)) {
    header('Location: ../index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| GENERATE TRANSFER NUMBER
|--------------------------------------------------------------------------
*/

function generateTransferNo(PDO $con): string
{
    $prefix = 'FT-' . date('Ym') . '-';

    $stmt = $con->prepare("
        SELECT transfer_no
        FROM food_transfers
        WHERE transfer_no LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([$prefix . '%']);

    $last = $stmt->fetchColumn();

    if ($last) {
        $number = (int)substr((string)$last, strlen($prefix));
        $number++;
    } else {
        $number = 1;
    }

    return $prefix . str_pad((string)$number, 4, '0', STR_PAD_LEFT);
}

/*
|--------------------------------------------------------------------------
| GENERATE PREPARATION NUMBER
|--------------------------------------------------------------------------
*/

function generatePreparationNo(PDO $con): string
{
    $prefix = 'FP-' . date('Ym') . '-';

    $stmt = $con->prepare("
        SELECT preparation_no
        FROM food_preparations
        WHERE preparation_no LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([$prefix . '%']);

    $last = $stmt->fetchColumn();

    if ($last) {
        $number = (int)substr((string)$last, strlen($prefix));
        $number++;
    } else {
        $number = 1;
    }

    return $prefix . str_pad((string)$number, 4, '0', STR_PAD_LEFT);
}

/*
|--------------------------------------------------------------------------
| SEND PREPARED FOOD TO CANTEEN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'prepare_food') {

        $planItemId = (int)($_POST['plan_item_id'] ?? 0);
        $preparedQty = (float)($_POST['prepared_qty'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');

        if ($planItemId <= 0) {
            $error = 'Invalid cooking plan item.';
        } elseif ($preparedQty <= 0) {
            $error = 'Prepared quantity must be greater than zero.';
        } else {

            try {

                $con->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | GET PLAN ITEM
                |--------------------------------------------------------------------------
                */

                $stmt = $con->prepare("
                    SELECT
                        dpi.id AS plan_item_id,
                        dpi.required_plates,
                        dpi.food_id,

                        dcp.id AS plan_id,
                        dcp.cooking_date,
                        dcp.meal_type,
                        dcp.status AS plan_status,

                        fi.food_name,
                        fi.unit

                    FROM daily_cooking_plan_items dpi

                    INNER JOIN daily_cooking_plans dcp
                        ON dcp.id = dpi.cooking_plan_id

                    INNER JOIN food_items fi
                        ON fi.id = dpi.food_id

                    WHERE dpi.id = ?
                    LIMIT 1
                ");

                $stmt->execute([$planItemId]);

                $planItem = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$planItem) {
                    throw new RuntimeException('Cooking plan item not found.');
                }

                /*
                |--------------------------------------------------------------------------
                | PLAN MUST BE SENT TO STORE
                |--------------------------------------------------------------------------
                */

                if (!in_array(
                    $planItem['plan_status'],
                    ['Sent to Store', 'Completed'],
                    true
                )) {
                    throw new RuntimeException(
                        'This cooking plan is not ready for food preparation.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | CHECK MATERIAL REQUEST
                |--------------------------------------------------------------------------
                |
                | Chef approval creates the kitchen request.
                | Store must completely issue the materials before cooking.
                |
                */

                $stmt = $con->prepare("
                    SELECT
                        id,
                        request_no,
                        status

                    FROM kitchen_requests

                    WHERE plan_id = ?

                    AND status = 'Completed'

                    ORDER BY id DESC

                    LIMIT 1
                ");

                $stmt->execute([(int)$planItem['plan_id']]);

                $request = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$request) {
                    throw new RuntimeException(
                        'Materials have not been completely issued by Store yet.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | CHECK DUPLICATE PREPARATION
                |--------------------------------------------------------------------------
                */

                $stmt = $con->prepare("
                    SELECT id
                    FROM food_preparations
                    WHERE plan_item_id = ?
                    AND status IN ('Prepared', 'Sent to Canteen')
                    LIMIT 1
                ");

                $stmt->execute([$planItemId]);

                if ($stmt->fetch()) {
                    throw new RuntimeException(
                        'Food preparation has already been created for this item.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | PREPARATION NUMBER
                |--------------------------------------------------------------------------
                */

                $preparationNo = generatePreparationNo($con);

                /*
                |--------------------------------------------------------------------------
                | CREATE FOOD PREPARATION
                |--------------------------------------------------------------------------
                */

                $stmt = $con->prepare("
                    INSERT INTO food_preparations
                    (
                        preparation_no,
                        food_id,
                        preparation_date,
                        prepared_qty,
                        planned_qty,
                        plan_item_id,
                        request_id,
                        status,
                        prepared_by,
                        remarks
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'Prepared',
                        ?,
                        ?
                    )
                ");

                $stmt->execute([
                    $preparationNo,
                    (int)$planItem['food_id'],
                    date('Y-m-d'),
                    $preparedQty,
                    (float)$planItem['required_plates'],
                    $planItemId,
                    (int)$request['id'],
                    $userId > 0 ? $userId : null,
                    $remarks !== '' ? $remarks : null
                ]);

                $preparationId = (int)$con->lastInsertId();

                /*
                |--------------------------------------------------------------------------
                | TRANSFER NUMBER
                |--------------------------------------------------------------------------
                */

                $transferNo = generateTransferNo($con);

                /*
                |--------------------------------------------------------------------------
                | CREATE FOOD TRANSFER
                |--------------------------------------------------------------------------
                */

                $stmt = $con->prepare("
                    INSERT INTO food_transfers
                    (
                        transfer_no,
                        preparation_id,
                        food_id,
                        quantity,
                        transfer_date,
                        sent_by,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        NOW(),
                        ?,
                        'Sent'
                    )
                ");

                $stmt->execute([
                    $transferNo,
                    $preparationId,
                    (int)$planItem['food_id'],
                    $preparedQty,
                    $userId > 0 ? $userId : null
                ]);

                /*
                |--------------------------------------------------------------------------
                | UPDATE PREPARATION STATUS
                |--------------------------------------------------------------------------
                */

                $stmt = $con->prepare("
                    UPDATE food_preparations
                    SET status = 'Sent to Canteen'
                    WHERE id = ?
                ");

                $stmt->execute([$preparationId]);

                /*
                |--------------------------------------------------------------------------
                | UPDATE PLAN STATUS
                |--------------------------------------------------------------------------
                */

                $stmt = $con->prepare("
                    UPDATE daily_cooking_plans dcp

                    SET dcp.status = CASE
                        WHEN NOT EXISTS (
                            SELECT 1
                            FROM daily_cooking_plan_items dpi2
                            WHERE dpi2.cooking_plan_id = dcp.id
                            AND NOT EXISTS (
                                SELECT 1
                                FROM food_preparations fp2
                                WHERE fp2.plan_item_id = dpi2.id
                                AND fp2.status IN ('Prepared', 'Sent to Canteen', 'Completed')
                            )
                        )
                        THEN 'Completed'

                        ELSE 'Sent to Store'
                    END

                    WHERE dcp.id = ?
                ");

                $stmt->execute([
                    (int)$planItem['plan_id']
                ]);

                $con->commit();

                flash(
                    'success',
                    $planItem['food_name'] .
                    ' prepared and sent to Canteen successfully.'
                );

                header('Location: food_preparation.php');
                exit;

            } catch (Throwable $e) {

                if ($con->inTransaction()) {
                    $con->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| GET READY FOOD ITEMS
|--------------------------------------------------------------------------
|
| Only cooking plans whose Store request is COMPLETED are shown.
|
*/

$stmt = $con->query("
    SELECT
        dpi.id AS plan_item_id,
        dpi.required_plates,

        dcp.id AS plan_id,
        dcp.cooking_date,
        dcp.meal_type,
        dcp.status AS plan_status,

        fi.id AS food_id,
        fi.food_name,
        fi.unit,

        kr.request_no,
        kr.status AS request_status,

        fp.id AS preparation_id,
        fp.preparation_no,
        fp.prepared_qty,
        fp.status AS preparation_status

    FROM daily_cooking_plan_items dpi

    INNER JOIN daily_cooking_plans dcp
        ON dcp.id = dpi.cooking_plan_id

    INNER JOIN food_items fi
        ON fi.id = dpi.food_id

    INNER JOIN kitchen_requests kr
        ON kr.plan_id = dcp.id
        AND kr.status = 'Completed'

    LEFT JOIN food_preparations fp
        ON fp.plan_item_id = dpi.id

    WHERE dcp.status IN ('Sent to Store', 'Completed')

    ORDER BY
        dcp.cooking_date DESC,
        FIELD(
            dcp.meal_type,
            'Breakfast',
            'Lunch',
            'Snacks',
            'Dinner'
        ),
        dpi.id ASC
");

$foodItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| RECENT PREPARATION HISTORY
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT
        fp.id,
        fp.preparation_no,
        fp.preparation_date,
        fp.prepared_qty,
        fp.status,
        fp.remarks,

        fi.food_name,
        fi.unit,

        dcp.meal_type,
        dcp.cooking_date,

        ft.transfer_no,
        ft.quantity AS transferred_qty,
        ft.status AS transfer_status

    FROM food_preparations fp

    INNER JOIN food_items fi
        ON fi.id = fp.food_id

    LEFT JOIN daily_cooking_plan_items dpi
        ON dpi.id = fp.plan_item_id

    LEFT JOIN daily_cooking_plans dcp
        ON dcp.id = dpi.cooking_plan_id

    LEFT JOIN food_transfers ft
        ON ft.preparation_id = fp.id

    ORDER BY fp.id DESC

    LIMIT 50
");

$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid">


<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1">Food Preparation</h3>
        <p class="text-muted mb-0">
            Prepare approved food and send it to Canteen.
        </p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<!--
|--------------------------------------------------------------------------
| READY FOR PREPARATION
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm mb-4">

    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-utensils"></i>
            Ready for Preparation
        </h5>
    </div>

    <div class="card-body">

        <?php if (!$foodItems): ?>

            <div class="text-center py-5">

                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>

                <h5>No food pending for preparation</h5>

                <p class="text-muted mb-0">
                    Food will appear here after the Store completely issues
                    the required materials.
                </p>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-light">

                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Meal</th>
                            <th>Food</th>
                            <th>Planned Plates</th>
                            <th>Store Request</th>
                            <th>Preparation</th>
                            <th width="180">Action</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($foodItems as $index => $item): ?>

                        <tr>

                            <td>
                                <?= $index + 1 ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    date(
                                        'd-m-Y',
                                        strtotime($item['cooking_date'])
                                    )
                                ) ?>
                            </td>

                            <td>

                                <?php
                                $mealClass = match ($item['meal_type']) {
                                    'Breakfast' => 'badge bg-warning text-dark',
                                    'Lunch' => 'badge bg-primary',
                                    'Snacks' => 'badge bg-info text-dark',
                                    'Dinner' => 'badge bg-dark',
                                    default => 'badge bg-secondary'
                                };
                                ?>

                                <span class="<?= $mealClass ?>">
                                    <?= htmlspecialchars($item['meal_type']) ?>
                                </span>

                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($item['food_name']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= number_format(
                                    (float)$item['required_plates'],
                                    0
                                ) ?>
                                plates
                            </td>

                            <td>
                                <span class="badge bg-success">
                                    <?= htmlspecialchars($item['request_no']) ?>
                                </span>

                                <br>

                                <small class="text-success">
                                    Materials Issued
                                </small>
                            </td>

                            <td>

                                <?php if ($item['preparation_id']): ?>

                                    <?php if ($item['preparation_status'] === 'Sent to Canteen'): ?>

                                        <span class="badge bg-success">
                                            Sent to Canteen
                                        </span>

                                    <?php elseif ($item['preparation_status'] === 'Completed'): ?>

                                        <span class="badge bg-primary">
                                            Completed
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-warning text-dark">
                                            Prepared
                                        </span>

                                    <?php endif; ?>

                                <?php else: ?>

                                    <span class="badge bg-secondary">
                                        Not Prepared
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if (!$item['preparation_id']): ?>

                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#prepareModal<?= (int)$item['plan_item_id'] ?>"
                                    >
                                        <i class="fas fa-fire"></i>
                                        Prepare Food
                                    </button>

                                <?php else: ?>

                                    <span class="text-muted">
                                        Already processed
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| PREPARATION HISTORY
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm">

    <div class="card-header">

        <h5 class="mb-0">
            <i class="fas fa-history"></i>
            Preparation History
        </h5>

    </div>

    <div class="card-body">

        <?php if (!$history): ?>

            <div class="text-center text-muted py-4">
                No preparation history available.
            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-bordered table-hover">

                    <thead class="table-light">

                        <tr>
                            <th>Preparation No</th>
                            <th>Date</th>
                            <th>Meal</th>
                            <th>Food</th>
                            <th>Prepared Qty</th>
                            <th>Transfer No</th>
                            <th>Status</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($history as $row): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($row['preparation_no']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    date(
                                        'd-m-Y',
                                        strtotime($row['preparation_date'])
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['meal_type'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['food_name']) ?>
                            </td>

                            <td>
                                <?= number_format(
                                    (float)$row['prepared_qty'],
                                    2
                                ) ?>

                                <?= htmlspecialchars($row['unit']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['transfer_no'] ?? '-'
                                ) ?>
                            </td>

                            <td>

                                <?php if (
                                    ($row['transfer_status'] ?? '') === 'Received'
                                ): ?>

                                    <span class="badge bg-success">
                                        Received by Canteen
                                    </span>

                                <?php elseif (
                                    ($row['transfer_status'] ?? '') === 'Sent'
                                ): ?>

                                    <span class="badge bg-warning text-dark">
                                        Sent to Canteen
                                    </span>

                                <?php elseif (
                                    $row['status'] === 'Completed'
                                ): ?>

                                    <span class="badge bg-primary">
                                        Completed
                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-secondary">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>

                                <?php endif; ?>

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

<!--
|--------------------------------------------------------------------------
| PREPARE FOOD MODALS
|--------------------------------------------------------------------------
-->

<?php foreach ($foodItems as $item): ?>


<?php if ($item['preparation_id']) {
    continue;
} ?>

<div
    class="modal fade"
    id="prepareModal<?= (int)$item['plan_item_id'] ?>"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Prepare Food
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>

            <form method="POST">

                <div class="modal-body">

                    <input
                        type="hidden"
                        name="action"
                        value="prepare_food"
                    >

                    <input
                        type="hidden"
                        name="plan_item_id"
                        value="<?= (int)$item['plan_item_id'] ?>"
                    >

                    <div class="mb-3">

                        <label class="form-label">
                            Food
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="<?= htmlspecialchars($item['food_name']) ?>"
                            readonly
                        >

                    </div>

                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Meal
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                value="<?= htmlspecialchars($item['meal_type']) ?>"
                                readonly
                            >

                        </div>

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Planned Plates
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                value="<?= number_format(
                                    (float)$item['required_plates'],
                                    0
                                ) ?>"
                                readonly
                            >

                        </div>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Actual Prepared Quantity
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="number"
                            name="prepared_qty"
                            class="form-control"
                            min="0.01"
                            step="0.01"
                            value="<?= htmlspecialchars(
                                (string)$item['required_plates']
                            ) ?>"
                            required
                        >

                        <small class="text-muted">
                            Enter the actual quantity prepared by the Kitchen.
                        </small>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Remarks
                        </label>

                        <textarea
                            name="remarks"
                            class="form-control"
                            rows="3"
                            placeholder="Optional remarks"
                        ></textarea>

                    </div>

                    <div class="alert alert-info mb-0">

                        <i class="fas fa-info-circle"></i>

                        After saving, the prepared food will automatically
                        be marked as <strong>Sent to Canteen</strong>.

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="fas fa-paper-plane"></i>
                        Prepare & Send to Canteen
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

?>
