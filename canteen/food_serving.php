<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$role = $_SESSION['role_name'] ?? '';

if (!in_array($role, ['Canteen', 'Super Admin'], true)) {
    header('Location: ../index.php');
    exit;
}

$loginUserId = (int)($_SESSION['user_id'] ?? 0);

if ($loginUserId <= 0 && !empty($_SESSION['user_data']) && is_array($_SESSION['user_data'])) {
    foreach ($_SESSION['user_data'] as $key => $value) {
        if (in_array($key, ['id', 'user_id'], true)) {
            $loginUserId = (int)$value;
            break;
        }
    }
}

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| SAVE FOOD SERVING (ADVANCED FLOW)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_serving'])) {

    $servingId = (int)($_POST['serving_id'] ?? 0);
    // User ippo Remaining Qty thaan enter pannuvanga
    $remainingQty = (float)($_POST['remaining_qty'] ?? 0);
    $pax       = (int)($_POST['pax'] ?? 0);

    if ($servingId <= 0) {
        $error = 'Invalid serving record.';
    } elseif ($remainingQty < 0) {
        $error = 'Remaining quantity cannot be negative.';
    } elseif ($pax < 0) {
        $error = 'Pax cannot be negative.';
    } else {
        try {
            $con->beginTransaction();

            $stmt = $con->prepare("SELECT received_qty, status FROM canteen_food_serving WHERE id = ? FOR UPDATE");
            $stmt->execute([$servingId]);
            $serving = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$serving) {
                throw new Exception('Serving record was not found.');
            }
            if ($serving['status'] === 'Closed') {
                throw new Exception('This service is already closed and locked.');
            }

            $receivedQty = (float)$serving['received_qty'];

            if ($remainingQty > $receivedQty) {
                throw new Exception('Remaining quantity cannot be greater than received quantity.');
            }

            // Auto-calculate Served Qty
            $servedQty = $receivedQty - $remainingQty;

            $update = $con->prepare("
                UPDATE canteen_food_serving
                SET served_qty = :served_qty, remaining_qty = :remaining_qty, pax = :pax, recorded_by = :recorded_by
                WHERE id = :id
            ");

            $update->execute([
                ':served_qty'    => $servedQty,
                ':remaining_qty' => $remainingQty,
                ':pax'           => $pax,
                ':recorded_by'   => $loginUserId,
                ':id'            => $servingId
            ]);

            $con->commit();
            $success = 'Food serving details updated successfully.';

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
| CLOSE SERVICE (LOCK DATA)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_service'])) {
    $servingId = (int)($_POST['serving_id'] ?? 0);

    try {
        $update = $con->prepare("UPDATE canteen_food_serving SET status = 'Closed' WHERE id = ? AND status = 'Serving'");
        $update->execute([$servingId]);
        if ($update->rowCount() > 0) {
            $success = 'Service closed and locked successfully. Data sent to Wastage tracking.';
        } else {
            $error = 'Unable to close service or it is already closed.';
        }
    } catch (Throwable $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| FETCH RECEIVED FOOD (WITH STATUS)
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
        cfs.pax,
        cfs.status,

        ft.transfer_no,

        fi.food_code,
        fi.food_name,
        fi.unit

    FROM canteen_food_serving cfs

    INNER JOIN food_transfers ft
        ON ft.id = cfs.transfer_id

    INNER JOIN food_items fi
        ON fi.id = cfs.food_id

    ORDER BY
        cfs.serving_date DESC,
        cfs.id DESC
");

$servingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>

<div class="main-content">
<div class="page-body">

    <div class="page-header">
        <div>
            <h4 class="page-header-title"><i class="fa-solid fa-bowl-food me-2"></i>Food Serving</h4>
            <p class="page-header-subtitle">Record remaining food and close service end of the day.</p>
        </div>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fa-solid fa-circle-check me-2"></i><?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="content-card">
        <div class="content-card-header">
            <h5 class="mb-0"><i class="fa-solid fa-utensils me-2"></i>Received Food</h5>
            <span class="badge badge-count"><?= count($servingRows) ?> Records</span>
        </div>

        <div class="content-card-body p-0">
            <?php if (empty($servingRows)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-bowl-food fa-3x mb-3"></i>
                    <h5>No Food Received</h5>
                    <p class="mb-0">Food received from Kitchen will appear here.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-nowrap">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Transfer No</th>
                                <th>Food</th>
                                <th>Date</th>
                                <th>Received</th>
                                <th>Served</th>
                                <th>Remaining</th>
                                <th>Pax</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($servingRows as $index => $row): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><strong><?= e($row['transfer_no']) ?></strong></td>
                                    <td>
                                        <div class="food-cell">
                                            <span class="food-icon"><i class="fa-solid fa-utensils"></i></span>
                                            <div>
                                                <strong><?= e($row['food_name']) ?></strong><br>
                                                <small class="text-muted"><?= e($row['food_code']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= date('d-m-Y', strtotime($row['serving_date'])) ?></td>
                                    <td><strong><?= number_format((float)$row['received_qty'], 2) ?></strong> <?= e($row['unit']) ?></td>
                                    <td><?= number_format((float)$row['served_qty'], 2) ?> <?= e($row['unit']) ?></td>
                                    <td>
                                        <span class="badge remaining-pill<?= ((float)$row['remaining_qty']) <= 0 ? ' is-zero' : '' ?>">
                                            <?= number_format((float)$row['remaining_qty'], 2) ?> <?= e($row['unit']) ?>
                                        </span>
                                    </td>
                                    <td><strong><?= (int)$row['pax'] ?></strong></td>
                                    <td class="text-center">
                                        <?php if (($row['status'] ?? 'Serving') === 'Serving'): ?>
                                            <div class="d-flex justify-content-center gap-2">
                                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#servingModal"
                                                    data-id="<?= (int)$row['id'] ?>"
                                                    data-food="<?= e($row['food_name']) ?>"
                                                    data-unit="<?= e($row['unit']) ?>"
                                                    data-received="<?= (float)$row['received_qty'] ?>"
                                                    data-remaining="<?= (float)$row['remaining_qty'] ?>"
                                                    data-pax="<?= (int)$row['pax'] ?>">
                                                    <i class="fa-solid fa-pen me-1"></i> Update
                                                </button>
                                                <form method="POST" class="m-0" onsubmit="return confirm('Are you sure? Once closed, you cannot edit this and it will move to Wastage tracking.');">
                                                    <input type="hidden" name="serving_id" value="<?= (int)$row['id'] ?>">
                                                    <button type="submit" name="close_service" class="btn btn-danger btn-sm">
                                                        <i class="fa-solid fa-lock me-1"></i> Close Service
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="fa-solid fa-lock me-1"></i> Service Closed</span>
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
</div>

<!-- =========================================================
     SERVING MODAL (ADVANCED UI)
========================================================== -->
<div class="modal fade app-modal" id="servingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-bowl-food me-2"></i>Update Remaining Food</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="serving_id" id="serving_id">

                    <div class="mb-3">
                        <label class="form-label">Food</label>
                        <input type="text" class="form-control bg-light" id="food_name" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Received Quantity (From Kitchen)</label>
                        <div class="input-group">
                            <input type="text" class="form-control bg-light" id="received_qty" readonly>
                            <span class="input-group-text" id="unit"></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="remaining_qty" class="form-label text-primary fw-bold">
                            Remaining Quantity (At Canteen) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" class="form-control border-primary" name="remaining_qty" id="remaining_qty" required>
                            <span class="input-group-text border-primary" id="remaining_unit"></span>
                        </div>
                        <small class="text-muted">Enter how much food is left in the vessel.</small>
                    </div>

                    <div class="mb-3">
                        <label for="pax" class="form-label">Pax / People Served <span class="text-danger">*</span></label>
                        <input type="number" min="0" class="form-control" name="pax" id="pax" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-success">Served Quantity (Auto Calculated)</label>
                        <input type="text" class="form-control bg-light text-success fw-bold" id="served_qty" readonly>
                        <small class="text-success"><i class="fa-solid fa-calculator me-1"></i> (Received - Remaining = Served)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_serving" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('servingModal');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id       = button.getAttribute('data-id');
        const food     = button.getAttribute('data-food');
        const unit     = button.getAttribute('data-unit');
        const received = parseFloat(button.getAttribute('data-received')) || 0;
        const remaining = parseFloat(button.getAttribute('data-remaining')) || 0;
        const pax      = parseInt(button.getAttribute('data-pax')) || 0;

        document.getElementById('serving_id').value = id;
        document.getElementById('food_name').value = food;
        document.getElementById('unit').textContent = unit;
        document.getElementById('remaining_unit').textContent = unit;
        document.getElementById('received_qty').value = received.toFixed(2);
        
        // Ipo remaining dhaan editable
        document.getElementById('remaining_qty').value = remaining.toFixed(2);
        document.getElementById('pax').value = pax;

        calculateServed();
    });

    // Auto calculate formula: Received - Remaining = Served
    function calculateServed() {
        const received = parseFloat(document.getElementById('received_qty').value) || 0;
        let remaining = parseFloat(document.getElementById('remaining_qty').value) || 0;

        if (remaining > received) {
            remaining = received;
            document.getElementById('remaining_qty').value = received.toFixed(2);
        }

        let served = received - remaining;
        document.getElementById('served_qty').value = served.toFixed(2);
    }

    document.getElementById('remaining_qty').addEventListener('input', calculateServed);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>