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

/*
|--------------------------------------------------------------------------
| ISSUE MATERIAL TO KITCHEN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_material'])) {

    $requestId = (int)($_POST['request_id'] ?? 0);
    $itemId    = (int)($_POST['item_id'] ?? 0);
    $issueQty  = (float)($_POST['issue_qty'] ?? 0);

    if ($requestId <= 0 || $itemId <= 0) {
        $error = 'Invalid request.';
    } elseif ($issueQty <= 0) {
        $error = 'Issue quantity must be greater than zero.';
    } else {

        try {

            $con->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | GET REQUEST ITEM
            |--------------------------------------------------------------------------
            */

            $stmt = $con->prepare("
                SELECT
                    pri.id AS item_id,
                    pri.request_id,
                    pri.material_id,
                    pri.requested_qty,

                    pr.status AS request_status,

                    m.material_code,
                    m.material_name,
                    m.unit,
                    m.current_stock

                FROM purchase_request_items pri

                INNER JOIN purchase_requests pr
                    ON pr.id = pri.request_id

                INNER JOIN materials m
                    ON m.id = pri.material_id

                WHERE pri.id = :item_id
                  AND pri.request_id = :request_id

                FOR UPDATE
            ");

            $stmt->execute([
                ':item_id' => $itemId,
                ':request_id' => $requestId
            ]);

            $item = $stmt->fetch();

            if (!$item) {
                throw new RuntimeException(
                    'Requested material was not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | REQUEST MUST BE APPROVED
            |--------------------------------------------------------------------------
            */

            if ($item['request_status'] !== 'Approved') {
                throw new RuntimeException(
                    'Only approved requests can be issued.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDATE QUANTITY
            |--------------------------------------------------------------------------
            */

            $requestedQty = (float)$item['requested_qty'];
            $currentStock = (float)$item['current_stock'];

            if ($issueQty > $requestedQty) {
                throw new RuntimeException(
                    'Issue quantity cannot be greater than requested quantity.'
                );
            }

            if ($issueQty > $currentStock) {
                throw new RuntimeException(
                    'Insufficient stock. Available stock: '
                    . number_format($currentStock, 2)
                    . ' '
                    . $item['unit']
                );
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE STOCK
            |--------------------------------------------------------------------------
            */

            $updateStock = $con->prepare("
                UPDATE materials
                SET current_stock = current_stock - :quantity
                WHERE id = :material_id
            ");

            $updateStock->execute([
                ':quantity' => $issueQty,
                ':material_id' => $item['material_id']
            ]);

            /*
            |--------------------------------------------------------------------------
            | STOCK TRANSACTION
            |--------------------------------------------------------------------------
            */

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

            $transaction->execute([
                ':material_id' => $item['material_id'],
                ':quantity' => $issueQty,
                ':reference_no' => 'REQ-' . $requestId,
                ':reference_id' => $requestId,
                ':remarks' =>
                    'Material issued to Kitchen - '
                    . $item['material_name'],
                ':created_by' => $_SESSION['user_id']
            ]);

            /*
            |--------------------------------------------------------------------------
            | CHECK WHETHER ALL ITEMS HAVE BEEN ISSUED
            |--------------------------------------------------------------------------
            |
            | For now, after issuing an item we mark the request as Completed
            | when all requested items have at least one issue transaction.
            |
            */

            $checkItems = $con->prepare("
                SELECT
                    pri.id,
                    pri.material_id,
                    pri.requested_qty,

                    COALESCE(
                        (
                            SELECT SUM(st.quantity)
                            FROM stock_transactions st
                            WHERE st.transaction_type = 'ISSUE_KITCHEN'
                              AND st.reference_id = pri.request_id
                              AND st.material_id = pri.material_id
                        ),
                        0
                    ) AS issued_qty

                FROM purchase_request_items pri

                WHERE pri.request_id = :request_id
            ");

            $checkItems->execute([
                ':request_id' => $requestId
            ]);

            $requestItems = $checkItems->fetchAll();

            $allIssued = true;

            foreach ($requestItems as $requestItem) {

                if (
                    (float)$requestItem['issued_qty']
                    <
                    (float)$requestItem['requested_qty']
                ) {
                    $allIssued = false;
                    break;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE REQUEST STATUS
            |--------------------------------------------------------------------------
            */

            if ($allIssued) {

                $updateRequest = $con->prepare("
                    UPDATE purchase_requests
                    SET status = 'Completed'
                    WHERE id = :request_id
                ");

                $updateRequest->execute([
                    ':request_id' => $requestId
                ]);

            }

            $con->commit();

            $success =
                'Material issued successfully. '
                . number_format($issueQty, 2)
                . ' '
                . $item['unit']
                . ' of '
                . $item['material_name']
                . ' issued to Kitchen.';

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
| Kitchen requests are stored in purchase_requests.
| We identify Kitchen requests using the requesting user's role.
*/

$where[] = "
    r.id IN (
        SELECT pr2.id
        FROM purchase_requests pr2
        INNER JOIN users u2
            ON u2.id = pr2.requested_by
        INNER JOIN roles r2
            ON r2.id = u2.role_id
        WHERE r2.role_name = 'Kitchen'
    )
";


if ($statusFilter !== '') {

    $where[] = "pr.status = :status";

    $params[':status'] = $statusFilter;
}

$whereSql = implode(' AND ', $where);


$sql = "
    SELECT
        pr.id,
        pr.status,
        pr.remarks,
        pr.created_at,

        u.employee_name,
        u.employee_code,

        COUNT(pri.id) AS item_count,

        COALESCE(
            SUM(pri.requested_qty),
            0
        ) AS total_qty

    FROM purchase_requests pr

    INNER JOIN users u
        ON u.id = pr.requested_by

    INNER JOIN roles r
        ON r.id = u.role_id

    LEFT JOIN purchase_request_items pri
        ON pri.request_id = pr.id

    WHERE {$whereSql}

    GROUP BY
        pr.id,
        pr.status,
        pr.remarks,
        pr.created_at,
        u.employee_name,
        u.employee_code

    ORDER BY pr.id DESC
";

$stmt = $con->prepare($sql);
$stmt->execute($params);

$requests = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| REQUEST ITEMS
|--------------------------------------------------------------------------
*/

$requestItems = [];

foreach ($requests as $request) {

    $stmt = $con->prepare("
        SELECT
            pri.id AS item_id,
            pri.request_id,
            pri.material_id,
            pri.requested_qty,

            m.material_code,
            m.material_name,
            m.unit,
            m.current_stock

        FROM purchase_request_items pri

        INNER JOIN materials m
            ON m.id = pri.material_id

        WHERE pri.request_id = :request_id

        ORDER BY m.material_name ASC
    ");

    $stmt->execute([
        ':request_id' => $request['id']
    ]);

    $requestItems[$request['id']] = $stmt->fetchAll();
}


require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>

<div class="main-content">

    <!-- PAGE HEADER -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h4 class="mb-1">
                Kitchen Material Requests
            </h4>

            <p class="text-muted mb-0">
                Review and issue materials requested by Kitchen.
            </p>

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

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"></button>

        </div>

    <?php endif; ?>


    <!-- ERROR -->

    <?php if ($error): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <i class="fa-solid fa-circle-exclamation me-2"></i>

            <?= e($error) ?>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"></button>

        </div>

    <?php endif; ?>


    <!-- FILTER -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

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

                            <option value="Pending"
                                <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>
                                Pending
                            </option>

                            <option value="Approved"
                                <?= $statusFilter === 'Approved' ? 'selected' : '' ?>>
                                Approved
                            </option>

                            <option value="Completed"
                                <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>
                                Completed
                            </option>

                            <option value="Rejected"
                                <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>>
                                Rejected
                            </option>

                        </select>

                    </div>


                    <div class="col-md-2">

                        <button type="submit"
                                class="btn btn-primary w-100">

                            <i class="fa-solid fa-filter me-1"></i>
                            Filter

                        </button>

                    </div>


                    <div class="col-md-2">

                        <a href="kitchen_requests.php"
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

        <div class="card border-0 shadow-sm">

            <div class="card-body text-center py-5">

                <i class="fa-solid fa-inbox fs-1 text-muted mb-3"></i>

                <h5>
                    No Kitchen Requests
                </h5>

                <p class="text-muted mb-0">
                    There are no material requests to display.
                </p>

            </div>

        </div>

    <?php else: ?>


        <?php foreach ($requests as $request): ?>

            <?php

            $status = $request['status'];

            $badgeClass = match ($status) {

                'Pending' =>
                    'bg-warning text-dark',

                'Approved' =>
                    'bg-success',

                'Completed' =>
                    'bg-primary',

                'Rejected' =>
                    'bg-danger',

                default =>
                    'bg-secondary'
            };

            ?>

            <div class="card border-0 shadow-sm mb-4">

                <!-- REQUEST HEADER -->

                <div class="card-header bg-white py-3">

                    <div class="row align-items-center">

                        <div class="col-md-4">

                            <strong>
                                Request #<?= (int)$request['id'] ?>
                            </strong>

                            <br>

                            <small class="text-muted">

                                <?= e(
                                    date(
                                        'd-m-Y H:i',
                                        strtotime($request['created_at'])
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
                                <?= e($request['employee_name']) ?>
                            </strong>

                            <small class="text-muted">
                                (<?= e($request['employee_code']) ?>)
                            </small>

                        </div>


                        <div class="col-md-4 text-md-end">

                            <span class="badge <?= $badgeClass ?>">
                                <?= e($status) ?>
                            </span>

                        </div>

                    </div>

                </div>


                <!-- ITEMS -->

                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-bordered mb-0 align-middle">

                            <thead class="table-light">

                                <tr>

                                    <th>Material</th>

                                    <th>Unit</th>

                                    <th>Requested</th>

                                    <th>Available Stock</th>

                                    <th style="width: 300px;">
                                        Issue Material
                                    </th>
                                </tr>

                            </thead>


                            <tbody>

                            <?php foreach (
                                $requestItems[$request['id']]
                                ?? []
                                as $item
                            ): ?>

                                <tr>

                                    <td>

                                        <strong>
                                            <?= e(
                                                $item['material_name']
                                            ) ?>
                                        </strong>

                                        <br>

                                        <small class="text-muted">
                                            <?= e(
                                                $item['material_code']
                                            ) ?>
                                        </small>

                                    </td>


                                    <td>
                                        <?= e($item['unit']) ?>
                                    </td>


                                    <td>

                                        <?= number_format(
                                            (float)$item['requested_qty'],
                                            2
                                        ) ?>

                                    </td>


                                    <td>

                                        <?php

                                        $stock =
                                            (float)$item['current_stock'];

                                        $requested =
                                            (float)$item['requested_qty'];

                                        ?>

                                        <?php if ($stock >= $requested): ?>

                                            <span class="badge bg-success">

                                                <?= number_format(
                                                    $stock,
                                                    2
                                                ) ?>

                                            </span>

                                        <?php elseif ($stock > 0): ?>

                                            <span class="badge bg-warning text-dark">

                                                <?= number_format(
                                                    $stock,
                                                    2
                                                ) ?>

                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-danger">

                                                0.00

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?php if ($status === 'Approved'): ?>

                                            <form method="POST" class="d-flex gap-2">

                                                <input
                                                    type="hidden"
                                                    name="request_id"
                                                    value="<?= (int)$request['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="item_id"
                                                    value="<?= (int)$item['item_id'] ?>"
                                                >

                                                <input
                                                    type="number"
                                                    name="issue_qty"
                                                    class="form-control"
                                                    min="0.01"
                                                    max="<?= min(
                                                        (float)$item['requested_qty'],
                                                        (float)$item['current_stock']
                                                    ) ?>"
                                                    step="0.01"
                                                    placeholder="Enter quantity"
                                                    required
                                                >

                                                <button
                                                    type="submit"
                                                    name="issue_material"
                                                    value="1"
                                                    class="btn btn-primary"
                                                >

                                                    <i class="fa-solid fa-box-open me-1"></i>
                                                    Issue

                                                </button>

                                            </form>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                -
                                            </span>

                                        <?php endif; ?>

                                    </td>
                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </div>


                <!-- REMARKS -->

                <?php if (!empty($request['remarks'])): ?>

                    <div class="card-footer bg-white">

                        <strong>
                            Remarks:
                        </strong>

                        <?= e($request['remarks']) ?>

                    </div>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>