<?php

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Role Check
|--------------------------------------------------------------------------
*/
$role = $_SESSION['role_name'] ?? '';

if (!in_array($role, ['Canteen', 'Super Admin'], true)) {
    header('Location: ../index.php');
    exit;
}

$loginUserId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;

if (!$loginUserId) {
    header('Location: ../index.php');
    exit;
}

$message = '';
$messageType = 'success';

/*
|--------------------------------------------------------------------------
| Record Wastage
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_wastage'])) {

    $servingId = (int)($_POST['serving_id'] ?? 0);
    $wastageQty = (float)($_POST['wastage_qty'] ?? 0);
    $wastageDate = trim($_POST['wastage_date'] ?? date('Y-m-d'));
    $reason = trim($_POST['reason'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if ($servingId <= 0) {

        $message = 'Invalid food serving selected.';
        $messageType = 'danger';

    } elseif ($wastageQty <= 0) {

        $message = 'Wastage quantity must be greater than zero.';
        $messageType = 'danger';

    } elseif (empty($wastageDate)) {

        $message = 'Please select wastage date.';
        $messageType = 'danger';

    } else {

        try {

            $con->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Lock Serving Record
            |--------------------------------------------------------------------------
            */
            $stmt = $con->prepare("
                SELECT
                    cfs.id,
                    cfs.transfer_id,
                    cfs.food_id,
                    cfs.serving_date,
                    cfs.received_qty,
                    cfs.served_qty,
                    cfs.remaining_qty,
                    fi.food_name
                FROM canteen_food_serving cfs
                INNER JOIN food_items fi
                    ON fi.id = cfs.food_id
                WHERE cfs.id = ?
                FOR UPDATE
            ");

            $stmt->execute([$servingId]);

            $serving = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$serving) {
                throw new Exception('Food serving record not found.');
            }

            /*
            |--------------------------------------------------------------------------
            | Calculate Already Recorded Wastage
            |--------------------------------------------------------------------------
            */
            $stmt = $con->prepare("
                SELECT COALESCE(SUM(wastage_qty), 0)
                FROM canteen_wastage
                WHERE serving_id = ?
            ");

            $stmt->execute([$servingId]);

            $alreadyWasted = (float)$stmt->fetchColumn();

            /*
            |--------------------------------------------------------------------------
            | Remaining Available Food
            |
            | remaining_qty = received_qty - served_qty
            |
            | Available for wastage =
            | remaining_qty - previous wastage
            |--------------------------------------------------------------------------
            */
            $remainingAfterServing = (float)$serving['remaining_qty'];

            $availableForWastage = $remainingAfterServing - $alreadyWasted;

            if ($availableForWastage < 0) {
                $availableForWastage = 0;
            }

            if ($wastageQty > $availableForWastage) {

                throw new Exception(
                    'Wastage quantity cannot be greater than the available remaining quantity. ' .
                    'Available: ' . number_format($availableForWastage, 2)
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Insert Wastage
            |--------------------------------------------------------------------------
            */
            $stmt = $con->prepare("
                INSERT INTO canteen_wastage
                (
                    food_id,
                    serving_id,
                    wastage_date,
                    wastage_qty,
                    reason,
                    remarks,
                    recorded_by
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?
                )
            ");

            $stmt->execute([
                $serving['food_id'],
                $servingId,
                $wastageDate,
                $wastageQty,
                $reason !== '' ? $reason : null,
                $remarks !== '' ? $remarks : null,
                $loginUserId
            ]);

            $con->commit();

            $message = 'Food wastage recorded successfully.';
            $messageType = 'success';

        } catch (Throwable $e) {

            if ($con->inTransaction()) {
                $con->rollBack();
            }

            $message = $e->getMessage();
            $messageType = 'danger';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Fetch Serving Records Available for Wastage
|--------------------------------------------------------------------------
|
| We calculate:
|
| total_wastage
| available_wastage
|
| dynamically because canteen_food_serving.remaining_qty
| represents quantity remaining after serving.
|--------------------------------------------------------------------------
*/
$stmt = $con->query("
    SELECT
        cfs.id,
        cfs.transfer_id,
        cfs.food_id,
        cfs.serving_date,
        cfs.received_qty,
        cfs.served_qty,
        cfs.remaining_qty,

        fi.food_code,
        fi.food_name,
        fi.unit,

        COALESCE(
            (
                SELECT SUM(cw.wastage_qty)
                FROM canteen_wastage cw
                WHERE cw.serving_id = cfs.id
            ),
            0
        ) AS total_wastage,

        (
            cfs.remaining_qty -
            COALESCE(
                (
                    SELECT SUM(cw2.wastage_qty)
                    FROM canteen_wastage cw2
                    WHERE cw2.serving_id = cfs.id
                ),
                0
            )
        ) AS available_wastage

    FROM canteen_food_serving cfs

    INNER JOIN food_items fi
        ON fi.id = cfs.food_id

    -- INDHA LINE THAAN PUDHUSA ADD PANNIRUKOM
    WHERE cfs.status = 'Closed'

    ORDER BY
        cfs.serving_date DESC,
        cfs.id DESC
");

$servings = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Wastage History
|--------------------------------------------------------------------------
*/
$stmt = $con->query("
    SELECT
        cw.id,
        cw.wastage_date,
        cw.wastage_qty,
        cw.reason,
        cw.remarks,
        cw.created_at,

        fi.food_code,
        fi.food_name,
        fi.unit,

        cfs.serving_date,

        u.employee_name AS recorded_by_name

    FROM canteen_wastage cw

    INNER JOIN food_items fi
        ON fi.id = cw.food_id

    LEFT JOIN canteen_food_serving cfs
        ON cfs.id = cw.serving_id

    LEFT JOIN users u
        ON u.id = cw.recorded_by

    ORDER BY
        cw.wastage_date DESC,
        cw.id DESC
");

$wastageHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = basename($_SERVER['PHP_SELF']);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/topbar.php';

?>

<div class="main-content">

<div class="page-body">

    <!-- Page Header -->
    <div class="page-header">

        <div>
            <h4 class="page-header-title">
                <i class="fa-solid fa-trash-can me-2"></i>
                Food Wastage
            </h4>

            <p class="page-header-subtitle">
                Record and monitor daily food wastage.
            </p>
        </div>

    </div>


    <!-- Message -->
    <?php if (!empty($message)): ?>

        <div class="alert alert-<?= e($messageType) ?> alert-dismissible fade show">

            <?= e($message) ?>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
            </button>

        </div>

    <?php endif; ?>


    <!-- Available Food -->
    <div class="content-card mb-4">

        <div class="content-card-header">

            <h5 class="mb-0">
                <i class="fa-solid fa-utensils me-2"></i>
                Food Available for Wastage
            </h5>

        </div>

        <div class="content-card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>Date</th>

                            <th>Food</th>

                            <th>Received</th>

                            <th>Served</th>

                            <th>Remaining</th>

                            <th>Already Wasted</th>

                            <th>Available for Wastage</th>

                            <th width="100">Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($servings)): ?>

                        <tr>

                            <td colspan="9"
                                class="text-center text-muted py-4">

                                No food serving records found.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php $sl = 1; ?>

                        <?php foreach ($servings as $row): ?>

                            <?php
                            $availableWastage = max(
                                0,
                                (float)$row['available_wastage']
                            );
                            ?>

                            <tr>

                                <td>
                                    <?= $sl++ ?>
                                </td>

                                <td>
                                    <?= e($row['serving_date']) ?>
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
                                    <?= number_format(
                                        (float)$row['received_qty'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (float)$row['served_qty'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (float)$row['remaining_qty'],
                                        2
                                    ) ?>
                                </td>

                                <td>

                                    <span class="badge badge-disabled">

                                        <?= number_format(
                                            (float)$row['total_wastage'],
                                            2
                                        ) ?>

                                    </span>

                                </td>

                                <td>

                                    <?php if ($availableWastage > 0): ?>

                                        <span class="badge badge-sent">

                                            <?= number_format(
                                                $availableWastage,
                                                2
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-muted">
                                            0.00
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if ($availableWastage > 0): ?>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#wastageModal"
                                            data-serving-id="<?= $row['id'] ?>"
                                            data-food-name="<?= e($row['food_name']) ?>"
                                            data-available="<?= $availableWastage ?>"
                                        >

                                            <i class="fa-solid fa-trash-can"></i>

                                        </button>

                                    <?php else: ?>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-secondary"
                                            disabled
                                        >

                                            <i class="fa-solid fa-check"></i>

                                        </button>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- Wastage History -->
    <div class="content-card">

        <div class="content-card-header">

            <h5 class="mb-0">

                <i class="fa-solid fa-clock-rotate-left me-2"></i>

                Wastage History

            </h5>

        </div>

        <div class="content-card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>Wastage Date</th>

                            <th>Food</th>

                            <th>Quantity</th>

                            <th>Reason</th>

                            <th>Remarks</th>

                            <th>Recorded By</th>

                            <th>Recorded At</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($wastageHistory)): ?>

                        <tr>

                            <td colspan="8"
                                class="text-center text-muted py-4">

                                No wastage records found.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php $sl = 1; ?>

                        <?php foreach ($wastageHistory as $row): ?>

                            <tr>

                                <td>
                                    <?= $sl++ ?>
                                </td>

                                <td>
                                    <?= e($row['wastage_date']) ?>
                                </td>

                                <td>

                                    <strong>
                                        <?= e($row['food_name']) ?>
                                    </strong>

                                    <br>

                                    <small class="text-muted">
                                        <?= e($row['food_code']) ?>
                                    </small>

                                </td>

                                <td>

                                    <span class="badge badge-disabled">

                                        <?= number_format(
                                            (float)$row['wastage_qty'],
                                            2
                                        ) ?>

                                        <?= e($row['unit']) ?>

                                    </span>

                                </td>

                                <td>
                                    <?= e($row['reason'] ?: '-') ?>
                                </td>

                                <td>
                                    <?= e($row['remarks'] ?: '-') ?>
                                </td>

                                <td>
                                    <?= e($row['recorded_by_name'] ?: '-') ?>
                                </td>

                                <td>
                                    <?= e($row['created_at']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</div>


<!-- ==========================================================
     WASTAGE MODAL
=========================================================== -->

<div class="modal fade app-modal"
     id="wastageModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog">

        <div class="modal-content">

            <form method="POST">

                <div class="modal-header">

                    <h5 class="modal-title">

                        <i class="fa-solid fa-trash-can me-2"></i>

                        Record Food Wastage

                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">

                    <input type="hidden"
                           name="serving_id"
                           id="wastage_serving_id">


                    <!-- Food -->
                    <div class="mb-3">

                        <label class="form-label">
                            Food
                        </label>

                        <input type="text"
                               class="form-control"
                               id="wastage_food_name"
                               readonly>

                    </div>


                    <!-- Available -->
                    <div class="mb-3">

                        <label class="form-label">
                            Available Quantity
                        </label>

                        <input type="text"
                               class="form-control"
                               id="wastage_available_display"
                               readonly>

                    </div>


                    <!-- Quantity -->
                    <div class="mb-3">

                        <label class="form-label">
                            Wastage Quantity
                            <span class="text-danger">*</span>
                        </label>

                        <input type="number"
                               name="wastage_qty"
                               id="wastage_qty"
                               class="form-control"
                               min="0.01"
                               step="0.01"
                               required>

                        <small class="text-muted">
                            Quantity must not exceed the available quantity.
                        </small>

                    </div>


                    <!-- Date -->
                    <div class="mb-3">

                        <label class="form-label">
                            Wastage Date
                            <span class="text-danger">*</span>
                        </label>

                        <input type="date"
                               name="wastage_date"
                               class="form-control"
                               value="<?= date('Y-m-d') ?>"
                               required>

                    </div>


                    <!-- Reason -->
                    <div class="mb-3">

                        <!-- Reason -->
                    <div class="mb-3">

                        <label class="form-label">
                            Reason
                        </label>

                        <select name="reason"
                                class="form-select">

                            <option value="">
                                -- Select Reason --
                            </option>

                            <option value="Staff Consumption">
                                Staff Consumption
                            </option>

                            <option value="Excess Food">
                                Excess Food
                            </option>

                            <option value="Spoiled Food">
                                Spoiled Food
                            </option>

                            <option value="Burnt Food">
                                Burnt Food
                            </option>

                            <option value="Damaged Food">
                                Damaged Food
                            </option>

                            <option value="Quality Issue">
                                Quality Issue
                            </option>

                            <option value="Other">
                                Other
                            </option>

                        </select>

                    </div>


                    <!-- Remarks -->
                    <div class="mb-3">

                        <label class="form-label">
                            Remarks
                        </label>

                        <textarea
                            name="remarks"
                            class="form-control"
                            rows="3"
                            placeholder="Enter remarks if required..."
                        ></textarea>

                    </div>

                </div>


                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <button type="submit"
                            name="record_wastage"
                            class="btn btn-danger">

                        <i class="fa-solid fa-save me-1"></i>

                        Record Wastage

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const wastageModal =
        document.getElementById('wastageModal');

    const servingId =
        document.getElementById('wastage_serving_id');

    const foodName =
        document.getElementById('wastage_food_name');

    const availableDisplay =
        document.getElementById('wastage_available_display');

    const wastageQty =
        document.getElementById('wastage_qty');


    wastageModal.addEventListener(
        'show.bs.modal',
        function (event) {

            const button =
                event.relatedTarget;

            const id =
                button.getAttribute(
                    'data-serving-id'
                );

            const food =
                button.getAttribute(
                    'data-food-name'
                );

            const available =
                parseFloat(
                    button.getAttribute(
                        'data-available'
                    )
                ) || 0;


            servingId.value = id;

            foodName.value = food;

            availableDisplay.value =
                available.toFixed(2);

            wastageQty.value = '';

            wastageQty.max =
                available;

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Client-side quantity validation
    |--------------------------------------------------------------------------
    */
    wastageQty.addEventListener(
        'input',
        function () {

            const max =
                parseFloat(
                    wastageQty.max
                ) || 0;

            const value =
                parseFloat(
                    wastageQty.value
                ) || 0;

            if (value > max) {

                wastageQty.value =
                    max.toFixed(2);

            }

            if (value < 0) {

                wastageQty.value = '';

            }

        }
    );

});

</script>


<?php

require_once '../includes/footer.php';

?>