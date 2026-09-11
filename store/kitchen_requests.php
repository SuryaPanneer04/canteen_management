<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$allowedRoles = ['Store', 'Super Admin'];

if (!in_array($_SESSION['role_name'] ?? '', $allowedRoles, true)) {
    header('Location: ../index.php');
    exit;
}

$pageTitle = 'Kitchen Material Requests';

$success = '';
$error = '';

$loginUserId = (int)($_SESSION['user_id'] ?? 0);


/*
|--------------------------------------------------------------------------
| ISSUE MATERIAL TO KITCHEN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_selected_materials'])) {

    $requestId = (int)($_POST['request_id'] ?? 0);
    $selectedItems = $_POST['selected_items'] ?? [];
    $issueQtys = $_POST['issue_qty'] ?? [];

    if ($requestId <= 0 || !is_array($selectedItems) || !$selectedItems) {

        $error = 'Please select at least one material to issue.';

    } else {

        try {

            $con->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | REQUEST STATUS
            |--------------------------------------------------------------------------
            */

            $requestStmt = $con->prepare("
                SELECT id, request_no, status
                FROM kitchen_requests
                WHERE id = :request_id
                FOR UPDATE
            ");

            $requestStmt->execute([
                ':request_id' => $requestId
            ]);

            $requestHeader = $requestStmt->fetch(PDO::FETCH_ASSOC);

            if (!$requestHeader) {
                throw new RuntimeException('Kitchen request was not found.');
            }

            $allowedStatuses = [
                'Chef Approved',
                'Sent to Store',
                'Partially Issued'
            ];

            if (!in_array($requestHeader['status'], $allowedStatuses, true)) {
                throw new RuntimeException(
                    'This request cannot be issued in its current status.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | PREPARE STATEMENTS
            |--------------------------------------------------------------------------
            */

            $itemStmt = $con->prepare("
                SELECT
                    kri.id AS item_id,
                    kri.request_id,
                    kri.material_id,
                    kri.requested_qty,
                    kri.approved_qty,
                    kri.issued_qty,
                    m.material_code,
                    m.material_name,
                    m.unit,
                    m.current_stock
                FROM kitchen_request_items kri
                INNER JOIN materials m
                    ON m.id = kri.material_id
                WHERE kri.id = :item_id
                  AND kri.request_id = :request_id
                FOR UPDATE
            ");

            $updateStock = $con->prepare("
                UPDATE materials
                SET current_stock = current_stock - :quantity
                WHERE id = :material_id
            ");

            $updateItem = $con->prepare("
                UPDATE kitchen_request_items
                SET issued_qty = :issued_qty
                WHERE id = :item_id
            ");

            $transaction = $con->prepare("
                INSERT INTO stock_transactions
                (
                    material_id,
                    transaction_type,
                    quantity,
                    reference_no,
                    reference_id,
                    remarks,
                    created_by,
                    created_at
                )
                VALUES
                (
                    :material_id,
                    'ISSUE_KITCHEN',
                    :quantity,
                    :reference_no,
                    :reference_id,
                    :remarks,
                    :created_by,
                    NOW()
                )
            ");

            $issuedMaterials = [];

            /*
            |--------------------------------------------------------------------------
            | ISSUE ALL SELECTED MATERIALS
            |--------------------------------------------------------------------------
            */

            foreach ($selectedItems as $selectedItemId) {

                $itemId = (int)$selectedItemId;

                if ($itemId <= 0) {
                    continue;
                }

                $itemStmt->execute([
                    ':item_id'    => $itemId,
                    ':request_id' => $requestId
                ]);

                $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

                if (!$item) {
                    throw new RuntimeException(
                        'One of the selected materials was not found.'
                    );
                }

                $approvedQty  = (float)$item['approved_qty'];
                $issuedQty    = (float)$item['issued_qty'];
                $currentStock = (float)$item['current_stock'];

                $remainingQty = $approvedQty - $issuedQty;

                if ($remainingQty < 0) {
                    $remainingQty = 0;
                }

                if ($remainingQty <= 0) {
                    throw new RuntimeException(
                        'Material "' . $item['material_name'] .
                        '" has already been fully issued.'
                    );
                }

                /*
                | Use the quantity entered for this selected material.
                | The UI defaults it to the requested quantity where possible.
                */

                $issueQty = (float)($issueQtys[$itemId] ?? 0);

                if ($issueQty <= 0) {
                    throw new RuntimeException(
                        'Please enter a valid issue quantity for "' .
                        $item['material_name'] . '".'
                    );
                }

                if ($issueQty > $remainingQty) {
                    throw new RuntimeException(
                        'Issue quantity for "' . $item['material_name'] .
                        '" cannot be greater than the remaining approved quantity. ' .
                        'Remaining: ' . number_format($remainingQty, 2) .
                        ' ' . $item['unit']
                    );
                }

                if ($issueQty > $currentStock) {
                    throw new RuntimeException(
                        'Insufficient stock for "' . $item['material_name'] .
                        '". Available stock: ' .
                        number_format($currentStock, 2) .
                        ' ' . $item['unit']
                    );
                }

                $newIssuedQty = $issuedQty + $issueQty;

                $updateStock->execute([
                    ':quantity'    => $issueQty,
                    ':material_id' => $item['material_id']
                ]);

                $updateItem->execute([
                    ':issued_qty' => $newIssuedQty,
                    ':item_id'    => $itemId
                ]);

                $transaction->execute([
                    ':material_id'  => $item['material_id'],
                    ':quantity'     => $issueQty,
                    ':reference_no' => $requestHeader['request_no'],
                    ':reference_id' => $requestId,
                    ':remarks'      =>
                        'Material issued to Kitchen - ' .
                        $item['material_name'],
                    ':created_by'   => $loginUserId
                ]);

                $issuedMaterials[] =
                    $item['material_name'] . ' (' .
                    number_format($issueQty, 2) . ' ' .
                    $item['unit'] . ')';
            }

            if (!$issuedMaterials) {
                throw new RuntimeException(
                    'Please select at least one valid material to issue.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | CHECK ALL ITEMS
            |--------------------------------------------------------------------------
            */

            $checkItems = $con->prepare("
                SELECT
                    approved_qty,
                    issued_qty
                FROM kitchen_request_items
                WHERE request_id = :request_id
            ");

            $checkItems->execute([
                ':request_id' => $requestId
            ]);

            $requestItemsAfterIssue = $checkItems->fetchAll(PDO::FETCH_ASSOC);

            $allIssued = true;

            foreach ($requestItemsAfterIssue as $requestItem) {

                $approved = (float)$requestItem['approved_qty'];
                $issued   = (float)$requestItem['issued_qty'];

                if ($approved <= 0) {
                    continue;
                }

                if ($issued < $approved) {
                    $allIssued = false;
                    break;
                }
            }

            $newStatus = $allIssued
                ? 'Completed'
                : 'Partially Issued';

            $updateRequest = $con->prepare("
                UPDATE kitchen_requests
                SET status = :status
                WHERE id = :request_id
            ");

            $updateRequest->execute([
                ':status'     => $newStatus,
                ':request_id' => $requestId
            ]);

            $con->commit();

            $success =
                count($issuedMaterials) . ' material(s) issued successfully: ' .
                implode(', ', $issuedMaterials) . '.';

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
| REQUEST STATUS FILTER
|--------------------------------------------------------------------------
*/

$statusFilter = trim($_GET['status'] ?? '');


/*
|--------------------------------------------------------------------------
| FETCH KITCHEN REQUESTS
|--------------------------------------------------------------------------
*/

$where = [];

$params = [];


/*
|--------------------------------------------------------------------------
| ONLY KITCHEN REQUESTS
|--------------------------------------------------------------------------
*/

$where[] = "
    kr.status IN
    (
        'Chef Approved',
        'Sent to Store',
        'Partially Issued',
        'Completed'
    )
";


/*
|--------------------------------------------------------------------------
| OPTIONAL STATUS FILTER
|--------------------------------------------------------------------------
*/

if ($statusFilter !== '') {

    $where[] = "kr.status = :status";

    $params[':status'] = $statusFilter;
}


$whereSql = implode(' AND ', $where);


/*
|--------------------------------------------------------------------------
| REQUEST LIST
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        kr.id,
        kr.request_no,
        kr.request_date,
        kr.status,

        kr.cook_remarks,
        kr.chef_remarks,

        kr.created_at,
        kr.approved_at,
        kr.sent_to_store_at,

        u.employee_name,
        u.employee_code,

        COUNT(kri.id) AS item_count,

        COALESCE(
            SUM(kri.approved_qty),
            0
        ) AS total_approved_qty,

        COALESCE(
            SUM(kri.issued_qty),
            0
        ) AS total_issued_qty

    FROM kitchen_requests kr

    INNER JOIN users u
        ON u.id = kr.requested_by

    LEFT JOIN kitchen_request_items kri
        ON kri.request_id = kr.id

    WHERE {$whereSql}

    GROUP BY
        kr.id,
        kr.request_no,
        kr.request_date,
        kr.status,
        kr.cook_remarks,
        kr.chef_remarks,
        kr.created_at,
        kr.approved_at,
        kr.sent_to_store_at,
        u.employee_name,
        u.employee_code

    ORDER BY kr.id DESC
";


$stmt = $con->prepare($sql);
$stmt->execute($params);

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| REQUEST ITEMS
|--------------------------------------------------------------------------
*/

$requestItems = [];

foreach ($requests as $request) {

    $stmt = $con->prepare("
        SELECT
            kri.id AS item_id,
            kri.request_id,
            kri.material_id,

            kri.requested_qty,
            kri.approved_qty,
            kri.issued_qty,
            kri.remarks,

            m.material_code,
            m.material_name,
            m.unit,
            m.current_stock

        FROM kitchen_request_items kri

        INNER JOIN materials m
            ON m.id = kri.material_id

        WHERE kri.request_id = :request_id

        ORDER BY m.material_name ASC
    ");

    $stmt->execute([
        ':request_id' => $request['id']
    ]);

    $requestItems[$request['id']] =
        $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| SUMMARY COUNTS (for the stat cards)
|--------------------------------------------------------------------------
*/

$chefApprovedCount = 0;
$sentToStoreCount = 0;
$partiallyIssuedCount = 0;
$completedCount = 0;

foreach ($requests as $request) {

    switch ($request['status']) {

        case 'Chef Approved':
            $chefApprovedCount++;
            break;

        case 'Sent to Store':
            $sentToStoreCount++;
            break;

        case 'Partially Issued':
            $partiallyIssuedCount++;
            break;

        case 'Completed':
            $completedCount++;
            break;
    }

}


require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">

    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <div class="page-body">

        <!-- PAGE HEADER -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h4 class="mb-1">
                    Kitchen Material Requests
                </h4>

                <div class="text-muted">
                    Issue approved kitchen materials to Kitchen.
                </div>

            </div>

            <a href="dashboard.php"
               class="btn btn-outline-secondary">

                <i class="fa-solid fa-arrow-left me-1"></i>

                Dashboard

            </a>

        </div>


        <!-- SUCCESS -->

        <?php if ($success): ?>

            <div class="alert alert-success alert-dismissible fade show">

                <i class="fa-solid fa-circle-check me-2"></i>

                <?= e($success) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>


        <!-- ERROR -->

        <?php if ($error): ?>

            <div class="alert alert-danger alert-dismissible fade show">

                <i class="fa-solid fa-circle-exclamation me-2"></i>

                <?= e($error) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>


        <!-- STAT CARDS -->

        <div class="row g-3 mb-4">

            <div class="col-6 col-lg-3">

                <div class="stat-card d-flex align-items-center gap-3">

                    <div class="stat-icon stat-icon-primary">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>

                    <div>
                        <div class="stat-label">Chef Approved</div>
                        <div class="stat-value"><?= $chefApprovedCount ?></div>
                    </div>

                </div>

            </div>

            <div class="col-6 col-lg-3">

                <div class="stat-card d-flex align-items-center gap-3">

                    <div class="stat-icon stat-icon-amber">
                        <i class="fa-solid fa-paper-plane"></i>
                    </div>

                    <div>
                        <div class="stat-label">Sent to Store</div>
                        <div class="stat-value"><?= $sentToStoreCount ?></div>
                    </div>

                </div>

            </div>

            <div class="col-6 col-lg-3">

                <div class="stat-card d-flex align-items-center gap-3">

                    <div class="stat-icon stat-icon-purple">
                        <i class="fa-solid fa-truck-ramp-box"></i>
                    </div>

                    <div>
                        <div class="stat-label">Partially Issued</div>
                        <div class="stat-value"><?= $partiallyIssuedCount ?></div>
                    </div>

                </div>

            </div>

            <div class="col-6 col-lg-3">

                <div class="stat-card d-flex align-items-center gap-3">

                    <div class="stat-icon stat-icon-green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div>
                        <div class="stat-label">Completed</div>
                        <div class="stat-value"><?= $completedCount ?></div>
                    </div>

                </div>

            </div>

        </div>


        <!-- FILTER -->

        <div class="content-card mb-4">

            <div class="content-card-header">

                <strong>
                    <i class="fa-solid fa-filter me-1"></i>
                    Request Filter
                </strong>

            </div>

            <div class="content-card-body">

                <form method="GET">

                    <div class="row g-3 align-items-end">

                        <div class="col-md-4">

                            <label class="form-label">
                                Request Status
                            </label>

                            <select name="status"
                                    class="form-select">

                                <option value="">
                                    All Requests
                                </option>

                                <option value="Chef Approved"
                                    <?= $statusFilter === 'Chef Approved'
                                        ? 'selected'
                                        : '' ?>>
                                    Chef Approved
                                </option>

                                <option value="Sent to Store"
                                    <?= $statusFilter === 'Sent to Store'
                                        ? 'selected'
                                        : '' ?>>
                                    Sent to Store
                                </option>

                                <option value="Partially Issued"
                                    <?= $statusFilter === 'Partially Issued'
                                        ? 'selected'
                                        : '' ?>>
                                    Partially Issued
                                </option>

                                <option value="Completed"
                                    <?= $statusFilter === 'Completed'
                                        ? 'selected'
                                        : '' ?>>
                                    Completed
                                </option>

                            </select>

                        </div>


                        <div class="col-md-2">

                            <button
                                type="submit"
                                class="btn btn-primary w-100">

                                <i class="fa-solid fa-filter me-1"></i>

                                Filter

                            </button>

                        </div>


                        <div class="col-md-2">

                            <a
                                href="kitchen_requests.php"
                                class="btn btn-outline-secondary w-100">

                                Reset

                            </a>

                        </div>

                    </div>

                </form>

            </div>

        </div>


        <!-- REQUESTS -->

        <?php if (!$requests): ?>

            <div class="content-card">

                <div class="content-card-body text-center py-5">

                    <i class="fa-solid fa-inbox fs-1 text-muted mb-3"></i>

                    <h5>
                        No Kitchen Requests
                    </h5>

                    <p class="text-muted mb-0">
                        There are no kitchen material requests to display.
                    </p>

                </div>

            </div>

        <?php else: ?>


            <?php foreach ($requests as $request): ?>

                <?php

                $status = $request['status'];

                $badgeClass = match ($status) {

                    'Chef Approved' =>
                        'bg-primary',

                    'Sent to Store' =>
                        'bg-warning text-dark',

                    'Partially Issued' =>
                        'bg-info text-dark',

                    'Completed' =>
                        'badge-enable',

                    default =>
                        'bg-secondary'
                };

                ?>


                <div class="content-card mb-4">


                    <!-- REQUEST HEADER -->

                    <div class="content-card-header">

                        <div class="row align-items-center w-100">

                            <div class="col-md-3">

                                <strong>
                                    <?= e($request['request_no']) ?>
                                </strong>

                                <br>

                                <small class="text-muted">

                                    <?= e(
                                        date(
                                            'd-m-Y',
                                            strtotime(
                                                $request['request_date']
                                            )
                                        )
                                    ) ?>

                                </small>

                            </div>


                            <div class="col-md-4">

                                <small class="text-muted">
                                    Requested By
                                </small>

                                <br>

                                <strong>
                                    <?= e(
                                        $request['employee_name']
                                    ) ?>
                                </strong>

                                <small class="text-muted">

                                    (
                                    <?= e(
                                        $request['employee_code']
                                    ) ?>
                                    )

                                </small>

                            </div>


                            <div class="col-md-2">

                                <small class="text-muted">
                                    Items
                                </small>

                                <br>

                                <strong>
                                    <?= (int)$request['item_count'] ?>
                                </strong>

                            </div>


                            <div class="col-md-3 text-md-end">

                                <span class="badge <?= $badgeClass ?>">

                                    <?= e($status) ?>

                                </span>

                            </div>

                        </div>

                    </div>


                    <!-- REMARKS -->

                    <?php if (
                        !empty($request['cook_remarks']) ||
                        !empty($request['chef_remarks'])
                    ): ?>

                        <div class="content-card-body border-bottom py-3">

                            <?php if (!empty($request['cook_remarks'])): ?>

                                <div class="mb-2">

                                    <strong>
                                        Cook Remarks:
                                    </strong>

                                    <?= nl2br(
                                        e($request['cook_remarks'])
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <?php if (!empty($request['chef_remarks'])): ?>

                                <div>

                                    <strong>
                                        Chef Remarks:
                                    </strong>

                                    <?= nl2br(
                                        e($request['chef_remarks'])
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </div>

                    <?php endif; ?>


                    <!-- ITEMS -->

                    <form method="POST"
                          class="bulk-issue-form"
                          onsubmit="return confirmBulkIssue(this);">

                        <input type="hidden"
                               name="request_id"
                               value="<?= (int)$request['id'] ?>">

                        <div class="table-responsive">

                            <table class="table table-hover mb-0 align-middle">

                                <thead>

                                    <tr>

                                        <th style="width:45px;">
                                            <input
                                                type="checkbox"
                                                class="form-check-input select-all-materials"
                                                title="Select all available materials">
                                        </th>

                                        <th>
                                            Material
                                        </th>

                                        <th>
                                            Unit
                                        </th>

                                        <th>
                                            Requested
                                        </th>

                                        <th>
                                            Approved
                                        </th>

                                        <th>
                                            Issued
                                        </th>

                                        <th>
                                            Remaining
                                        </th>

                                        <th>
                                            Stock
                                        </th>

                                        <th style="width:240px;">
                                            Issue Material
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                <?php foreach (
                                    $requestItems[$request['id']] ?? []
                                    as $item
                                ): ?>

                                    <?php

                                    $requested =
                                        (float)$item['requested_qty'];

                                    $approved =
                                        (float)$item['approved_qty'];

                                    $issued =
                                        (float)$item['issued_qty'];

                                    $stock =
                                        (float)$item['current_stock'];

                                    $remaining =
                                        $approved - $issued;

                                    if ($remaining < 0) {
                                        $remaining = 0;
                                    }

                                    $maxIssue =
                                        min(
                                            $remaining,
                                            $stock
                                        );

                                    /*
                                    | Show the requested quantity in the
                                    | Issue Material quantity field.
                                    | If requested quantity is greater than
                                    | what can currently be issued, use the
                                    | maximum safe quantity instead.
                                    */

                                    $defaultIssueQty =
                                        $requested > 0
                                            ? min($requested, $maxIssue)
                                            : $maxIssue;

                                    $itemInitials = strtoupper(
                                        substr($item['material_name'], 0, 1)
                                    );

                                    $canIssue =
                                        $remaining > 0 &&
                                        $stock > 0 &&
                                        $status !== 'Completed';

                                    ?>

                                    <tr>

                                        <!-- SELECT -->

                                        <td>
                                            <?php if ($canIssue): ?>

                                                <input
                                                    type="checkbox"
                                                    class="form-check-input material-checkbox"
                                                    name="selected_items[]"
                                                    value="<?= (int)$item['item_id'] ?>">

                                            <?php endif; ?>
                                        </td>


                                        <!-- MATERIAL -->

                                        <td>

                                            <div class="d-flex align-items-center gap-2">

                                                <span class="row-avatar">
                                                    <?= e($itemInitials) ?>
                                                </span>

                                                <div>

                                                    <div class="fw-semibold">
                                                        <?= e($item['material_name']) ?>
                                                    </div>

                                                    <div class="text-muted small">
                                                        <?= e($item['material_code']) ?>
                                                    </div>

                                                </div>

                                            </div>

                                        </td>


                                        <!-- UNIT -->

                                        <td>
                                            <?= e($item['unit']) ?>
                                        </td>


                                        <!-- REQUESTED -->

                                        <td>

                                            <?= number_format(
                                                $requested,
                                                2
                                            ) ?>

                                        </td>


                                        <!-- APPROVED -->

                                        <td>

                                            <span class="badge bg-primary">

                                                <?= number_format(
                                                    $approved,
                                                    2
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- ISSUED -->

                                        <td>

                                            <?= number_format(
                                                $issued,
                                                2
                                            ) ?>

                                        </td>


                                        <!-- REMAINING -->

                                        <td>

                                            <?php if ($remaining > 0): ?>

                                                <span
                                                    class="badge bg-warning text-dark">

                                                    <?= number_format(
                                                        $remaining,
                                                        2
                                                    ) ?>

                                                </span>

                                            <?php else: ?>

                                                <span
                                                    class="badge badge-enable">

                                                    Completed

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- STOCK -->

                                        <td>

                                            <?php if ($stock >= $remaining && $remaining > 0): ?>

                                                <span
                                                    class="badge badge-enable">

                                                    <?= number_format(
                                                        $stock,
                                                        2
                                                    ) ?>

                                                </span>

                                            <?php elseif ($stock > 0): ?>

                                                <span
                                                    class="badge bg-warning text-dark">

                                                    <?= number_format(
                                                        $stock,
                                                        2
                                                    ) ?>

                                                </span>

                                            <?php else: ?>

                                                <span
                                                    class="badge badge-disabled">

                                                    0.00

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- ISSUE MATERIAL -->

                                        <td>

                                            <?php if ($canIssue): ?>

                                                <div class="d-flex align-items-center gap-2">

                                                    <div style="min-width:145px;">

                                                        <div class="input-group input-group-sm">

                                                            <input
                                                                type="number"
                                                                name="issue_qty[<?= (int)$item['item_id'] ?>]"
                                                                class="form-control issue-qty"
                                                                min="0.01"
                                                                max="<?= htmlspecialchars(
                                                                    (string)$maxIssue
                                                                ) ?>"
                                                                step="0.01"
                                                                value="<?= htmlspecialchars(
                                                                    (string)$defaultIssueQty
                                                                ) ?>"
                                                                data-requested="<?= htmlspecialchars(
                                                                    (string)$requested
                                                                ) ?>"
                                                                data-max="<?= htmlspecialchars(
                                                                    (string)$maxIssue
                                                                ) ?>"
                                                                aria-label="Issue quantity for <?= e($item['material_name']) ?>"
                                                                required>

                                                            <span class="input-group-text">
                                                                <?= e($item['unit']) ?>
                                                            </span>

                                                        </div>

                                                        <small class="text-muted">
                                                            Requested:
                                                            <?= number_format(
                                                                $requested,
                                                                2
                                                            ) ?>
                                                        </small>

                                                    </div>

                                                </div>

                                            <?php elseif ($remaining <= 0): ?>

                                                <span class="text-success">

                                                    <i
                                                        class="fa-solid fa-circle-check me-1">
                                                    </i>

                                                    Fully Issued

                                                </span>

                                            <?php elseif ($stock <= 0): ?>

                                                <span class="text-danger">

                                                    <i
                                                        class="fa-solid fa-triangle-exclamation me-1">
                                                    </i>

                                                    No Stock

                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>


                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>


                        <!-- BULK ISSUE FOOTER -->

                        <?php
                        $hasIssuableItems = false;

                        foreach ($requestItems[$request['id']] ?? [] as $checkItem) {
                            $checkRemaining =
                                max(
                                    0,
                                    (float)$checkItem['approved_qty'] -
                                    (float)$checkItem['issued_qty']
                                );

                            if (
                                $checkRemaining > 0 &&
                                (float)$checkItem['current_stock'] > 0 &&
                                $status !== 'Completed'
                            ) {
                                $hasIssuableItems = true;
                                break;
                            }
                        }
                        ?>

                        <?php if ($hasIssuableItems): ?>

                            <div class="content-card-body border-top py-3">

                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                                    <div class="text-muted small">
                                        <i class="fa-solid fa-circle-info me-1"></i>
                                        Select one or more materials, then click
                                        <strong>Issue Selected</strong>.
                                    </div>

                                    <button
                                        type="submit"
                                        name="issue_selected_materials"
                                        value="1"
                                        class="btn btn-primary">

                                        <i class="fa-solid fa-box-open me-1"></i>
                                        Issue Selected

                                    </button>

                                </div>

                            </div>

                        <?php endif; ?>

                    </form>


                    <!-- FOOTER -->

                    <div class="content-card-body border-top">

                        <div class="row">

                            <div class="col-md-4">

                                <small class="text-muted">
                                    Total Approved
                                </small>

                                <br>

                                <strong>

                                    <?= number_format(
                                        (float)$request['total_approved_qty'],
                                        2
                                    ) ?>

                                </strong>

                            </div>


                            <div class="col-md-4">

                                <small class="text-muted">
                                    Total Issued
                                </small>

                                <br>

                                <strong>

                                    <?= number_format(
                                        (float)$request['total_issued_qty'],
                                        2
                                    ) ?>

                                </strong>

                            </div>


                            <div class="col-md-4">

                                <small class="text-muted">
                                    Request Status
                                </small>

                                <br>

                                <span
                                    class="badge <?= $badgeClass ?>">

                                    <?= e($status) ?>

                                </span>

                            </div>

                        </div>


                        <?php if (!empty($request['chef_remarks'])): ?>

                            <div class="mt-3">

                                <strong>
                                    Chef Remarks:
                                </strong>

                                <?= e(
                                    $request['chef_remarks']
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>


        <?php endif; ?>

    </div>

</main>


<script>
document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.bulk-issue-form').forEach(function (form) {

        const selectAll = form.querySelector('.select-all-materials');
        const checkboxes = Array.from(
            form.querySelectorAll('.material-checkbox')
        );

        if (!selectAll) {
            return;
        }

        selectAll.addEventListener('change', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                selectAll.checked =
                    checkboxes.length > 0 &&
                    checkboxes.every(function (item) {
                        return item.checked;
                    });
            });
        });

        /*
        | When a material is selected, make sure its issue quantity
        | is valid and never exceeds the safe maximum.
        */

        form.addEventListener('change', function (event) {

            if (!event.target.classList.contains('issue-qty')) {
                return;
            }

            const input = event.target;
            const max = parseFloat(input.dataset.max || '0');
            const value = parseFloat(input.value || '0');

            if (max > 0 && value > max) {
                input.value = max;
            }

            if (value < 0) {
                input.value = '';
            }
        });
    });

});

function confirmBulkIssue(form) {

    const selected = form.querySelectorAll(
        '.material-checkbox:checked'
    );

    if (selected.length === 0) {
        alert('Please select at least one material to issue.');
        return false;
    }

    let invalid = false;

    selected.forEach(function (checkbox) {

        const itemId = checkbox.value;

        const qtyInput = form.querySelector(
            'input[name="issue_qty[' + itemId + ']"]'
        );

        if (!qtyInput) {
            invalid = true;
            return;
        }

        const quantity = parseFloat(qtyInput.value || '0');
        const max = parseFloat(qtyInput.dataset.max || '0');

        if (quantity <= 0 || quantity > max) {
            invalid = true;
        }
    });

    if (invalid) {
        alert(
            'Please enter a valid issue quantity for all selected materials.'
        );
        return false;
    }

    return confirm(
        'Issue the selected materials to Kitchen?'
    );
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>