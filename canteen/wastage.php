<?php
// 1. Backend Logic-ah inga connect pandrom
require_once 'wastage_logic.php';

// 2. UI Headers
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
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Available Food Table -->
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
                            <td colspan="9" class="text-center text-muted py-4">
                                No food serving records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $sl = 1; ?>
                        <?php foreach ($servings as $row): ?>
                            <?php $availableWastage = max(0, (float)$row['available_wastage']); ?>
                            <tr>
                                <td><?= $sl++ ?></td>
                                <td><?= e($row['serving_date']) ?></td>
                                <td>
                                    <div class="food-cell">
                                        <span class="food-icon"><i class="fa-solid fa-utensils"></i></span>
                                        <div>
                                            <strong><?= e($row['food_name']) ?></strong><br>
                                            <small class="text-muted"><?= e($row['food_code']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= number_format((float)$row['received_qty'], 2) ?></td>
                                <td><?= number_format((float)$row['served_qty'], 2) ?></td>
                                <td><?= number_format((float)$row['remaining_qty'], 2) ?></td>
                                <td>
                                    <span class="badge badge-disabled">
                                        <?= number_format((float)$row['total_wastage'], 2) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($availableWastage > 0): ?>
                                        <span class="badge badge-sent"><?= number_format($availableWastage, 2) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-muted">0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($availableWastage > 0): ?>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                                data-bs-target="#wastageModal"
                                                data-serving-id="<?= $row['id'] ?>"
                                                data-food-name="<?= e($row['food_name']) ?>"
                                                data-available="<?= $availableWastage ?>">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-secondary" disabled>
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

    <!-- Wastage History Table -->
    <div class="content-card">
        <div class="content-card-header">
            <h5 class="mb-0">
                <i class="fa-solid fa-clock-rotate-left me-2"></i>
                Wastage History
            </h5>
        </div>
        <div class="content-card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Wastage Date</th>
                            <th>Food</th>
                            <th>Quantity</th>
                            <th>Reason</th>
                            <!-- PUDHU COLUMN FOR WET/DRY -->
                            <th>Category</th> 
                            <th>Remarks</th>
                            <th>Recorded By</th>
                            <th>Recorded At</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($wastageHistory)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No wastage records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $sl = 1; ?>
                        <?php foreach ($wastageHistory as $row): ?>
                            <tr>
                                <td><?= $sl++ ?></td>
                                <td><?= e($row['wastage_date']) ?></td>
                                <td>
                                    <strong><?= e($row['food_name']) ?></strong><br>
                                    <small class="text-muted"><?= e($row['food_code']) ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-disabled">
                                        <?= number_format((float)$row['wastage_qty'], 2) ?> <?= e($row['unit']) ?>
                                    </span>
                                </td>
                                <td><?= e($row['reason'] ?: '-') ?></td>
                                
                                <!-- PUDHU DATA FOR WET/DRY -->
                                <td>
                                    <?php if($row['waste_type'] === 'Wet Waste'): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-leaf me-1"></i> Wet Waste</span>
                                    <?php elseif($row['waste_type'] === 'Dry Waste'): ?>
                                        <span class="badge bg-info text-dark"><i class="fa-solid fa-box me-1"></i> Dry Waste</span>
                                    <?php elseif($row['waste_type'] === 'N/A'): ?>
                                        <span class="badge bg-secondary">N/A</span>
                                    <?php else: ?>
                                        <?= e($row['waste_type'] ?: '-') ?>
                                    <?php endif; ?>
                                </td>

                                <td><?= e($row['remarks'] ?: '-') ?></td>
                                <td><?= e($row['recorded_by_name'] ?: '-') ?></td>
                                <td><?= e($row['created_at']) ?></td>
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

<?php
// 3. Modal and JS File-ah inga connect pandrom
require_once 'wastage_modal.php';

require_once '../includes/footer.php';
?>