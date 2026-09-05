<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/store_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Stock Issue';

$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| ISSUE STOCK TO KITCHEN
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'stock_issue'
) {

    $materialId = (int)($_POST['material_id'] ?? 0);
    $quantity   = (float)($_POST['quantity'] ?? 0);
    $remarks    = trim($_POST['remarks'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($materialId <= 0) {

        $message = 'Please select a material.';
        $messageType = 'danger';

    } elseif ($quantity <= 0) {

        $message = 'Issue quantity must be greater than zero.';
        $messageType = 'danger';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | START TRANSACTION
            |--------------------------------------------------------------------------
            */

            $con->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | GET MATERIAL WITH ROW LOCK
            |--------------------------------------------------------------------------
            */

            $stmt = $con->prepare("
                SELECT
                    id,
                    material_code,
                    material_name,
                    unit,
                    current_stock,
                    minimum_stock,
                    status
                FROM materials
                WHERE id = :id
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                ':id' => $materialId
            ]);

            $material = $stmt->fetch();


            if (!$material) {

                throw new Exception('Material not found.');

            }


            /*
            |--------------------------------------------------------------------------
            | CHECK MATERIAL STATUS
            |--------------------------------------------------------------------------
            */

            if ($material['status'] !== 'Enable') {

                throw new Exception(
                    'This material is disabled and cannot be issued.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CHECK AVAILABLE STOCK
            |--------------------------------------------------------------------------
            */

            $currentStock = (float)$material['current_stock'];

            if ($quantity > $currentStock) {

                throw new Exception(
                    'Insufficient stock. Available stock is '
                    . number_format($currentStock, 2)
                    . ' '
                    . $material['unit']
                    . '.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | GET LOGGED-IN USER
            |--------------------------------------------------------------------------
            */

            $createdBy = null;

            if (
                isset($_SESSION['user_data']) &&
                is_array($_SESSION['user_data'])
            ) {

                if (isset($_SESSION['user_data']['id'])) {

                    $createdBy =
                        (int)$_SESSION['user_data']['id'];

                } elseif (isset($_SESSION['user_data']['user_id'])) {

                    $createdBy =
                        (int)$_SESSION['user_data']['user_id'];

                } elseif (isset($_SESSION['user_data']['userid'])) {

                    $createdBy =
                        (int)$_SESSION['user_data']['userid'];

                }

            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE STOCK
            |--------------------------------------------------------------------------
            */

            $stmt = $con->prepare("
                UPDATE materials
                SET current_stock = current_stock - :quantity
                WHERE id = :id
            ");

            $stmt->execute([
                ':quantity' => $quantity,
                ':id'       => $materialId
            ]);


            /*
            |--------------------------------------------------------------------------
            | RECORD STOCK TRANSACTION
            |--------------------------------------------------------------------------
            */

            $stmt = $con->prepare("
                INSERT INTO stock_transactions
                (
                    material_id,
                    transaction_type,
                    quantity,
                    reference_id,
                    remarks,
                    created_by
                )
                VALUES
                (
                    :material_id,
                    'ISSUE_KITCHEN',
                    :quantity,
                    NULL,
                    :remarks,
                    :created_by
                )
            ");

            $stmt->bindValue(
                ':material_id',
                $materialId,
                PDO::PARAM_INT
            );

            $stmt->bindValue(
                ':quantity',
                $quantity
            );

            $stmt->bindValue(
                ':remarks',
                $remarks
            );

            if ($createdBy !== null && $createdBy > 0) {

                $stmt->bindValue(
                    ':created_by',
                    $createdBy,
                    PDO::PARAM_INT
                );

            } else {

                $stmt->bindValue(
                    ':created_by',
                    null,
                    PDO::PARAM_NULL
                );

            }

            $stmt->execute();


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $con->commit();


            $newStock = $currentStock - $quantity;

            $message =
                'Stock issued successfully to Kitchen. '
                . $material['material_name']
                . ': '
                . number_format($quantity, 2)
                . ' '
                . $material['unit']
                . ' issued. Remaining stock: '
                . number_format($newStock, 2)
                . ' '
                . $material['unit']
                . '.';

            $messageType = 'success';


        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | ROLLBACK
            |--------------------------------------------------------------------------
            */

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
| FETCH ENABLED MATERIALS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT
        id,
        material_code,
        material_name,
        category,
        unit,
        current_stock,
        minimum_stock
    FROM materials
    WHERE status = 'Enable'
    ORDER BY material_name ASC
");

$materials = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| RECENT KITCHEN ISSUES
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT
        st.id,
        st.quantity,
        st.remarks,
        st.created_at,
        m.material_code,
        m.material_name,
        m.unit,
        u.employee_name
    FROM stock_transactions st

    INNER JOIN materials m
        ON m.id = st.material_id

    LEFT JOIN users u
        ON u.id = st.created_by

    WHERE st.transaction_type = 'ISSUE_KITCHEN'

    ORDER BY st.id DESC

    LIMIT 15
");

$recentIssues = $stmt->fetchAll();


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
                    Stock Issue to Kitchen
                </h4>

                <div class="text-muted">
                    Issue store materials to the kitchen.
                </div>

            </div>

        </div>


        <!-- MESSAGE -->
        <?php if ($message !== ''): ?>

            <div
                class="alert alert-<?= e($messageType) ?> alert-dismissible fade show"
                role="alert">

                <?= e($message) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>


        <!-- ISSUE FORM -->
        <div class="content-card mb-4">

            <div class="content-card-header">

                <strong>

                    <i class="fa-solid fa-arrow-right-from-bracket me-1"></i>

                    Issue Material

                </strong>

            </div>


            <div class="p-4">

                <form method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="stock_issue">


                    <div class="row g-3">

                        <!-- MATERIAL -->
                        <div class="col-md-6">

                            <label class="form-label">

                                Material

                                <span class="text-danger">*</span>

                            </label>

                            <select
                                name="material_id"
                                id="material_id"
                                class="form-select"
                                required>

                                <option value="">
                                    --- Select Material ---
                                </option>

                                <?php foreach ($materials as $material): ?>

                                    <option
                                        value="<?= (int)$material['id'] ?>">

                                        <?= e($material['material_code']) ?>
                                        -
                                        <?= e($material['material_name']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- AVAILABLE STOCK -->
                        <div class="col-md-3">

                            <label class="form-label">
                                Available Stock
                            </label>

                            <input
                                type="text"
                                id="current_stock"
                                class="form-control"
                                readonly
                                value="0">

                        </div>


                        <!-- UNIT -->
                        <div class="col-md-3">

                            <label class="form-label">
                                Unit
                            </label>

                            <input
                                type="text"
                                id="material_unit"
                                class="form-control"
                                readonly
                                value="-">

                        </div>


                        <!-- QUANTITY -->
                        <div class="col-md-6">

                            <label class="form-label">

                                Issue Quantity

                                <span class="text-danger">*</span>

                            </label>

                            <input
                                type="number"
                                name="quantity"
                                id="issue_quantity"
                                class="form-control"
                                min="0.01"
                                step="0.01"
                                required
                                placeholder="Enter quantity">

                            <small
                                id="stock_warning"
                                class="text-danger d-none">

                                Issue quantity cannot be greater than
                                available stock.

                            </small>

                        </div>


                        <!-- REMARKS -->
                        <div class="col-md-6">

                            <label class="form-label">
                                Remarks
                            </label>

                            <input
                                type="text"
                                name="remarks"
                                class="form-control"
                                maxlength="255"
                                placeholder="Example: Morning kitchen requirement">

                        </div>


                        <!-- SUBMIT -->
                        <div class="col-12">

                            <button
                                type="submit"
                                id="issueButton"
                                class="btn btn-warning">

                                <i class="fa-solid fa-arrow-right-from-bracket me-1"></i>

                                Issue to Kitchen

                            </button>

                            <button
                                type="reset"
                                class="btn btn-secondary"
                                onclick="resetMaterialInfo();">

                                <i class="fa-solid fa-rotate-left me-1"></i>

                                Reset

                            </button>

                        </div>

                    </div>

                </form>

            </div>

        </div>


        <!-- RECENT ISSUES -->
        <div class="content-card">

            <div class="content-card-header">

                <div class="d-flex justify-content-between align-items-center">

                    <strong>

                        <i class="fa-solid fa-clock-rotate-left me-1"></i>

                        Recent Kitchen Issues

                    </strong>

                    <span class="text-muted small">

                        Last 15 transactions

                    </span>

                </div>

            </div>


            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Material</th>

                            <th>Quantity</th>

                            <th>Remarks</th>

                            <th>Issued By</th>

                            <th>Date</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($recentIssues)): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="text-center text-muted py-5">

                                <i class="fa-solid fa-inbox fs-2 d-block mb-2"></i>

                                No kitchen issue transactions found.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($recentIssues as $index => $row): ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>


                                <td>

                                    <strong>
                                        <?= e($row['material_name']) ?>
                                    </strong>

                                    <br>

                                    <small class="text-muted">

                                        <?= e($row['material_code']) ?>

                                    </small>

                                </td>


                                <td>

                                    <span class="badge bg-warning text-dark">

                                        -
                                        <?= number_format(
                                            (float)$row['quantity'],
                                            2
                                        ) ?>

                                        <?= e($row['unit']) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= e(
                                        $row['remarks'] ?: '-'
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        $row['employee_name']
                                        ?: 'System'
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        $row['created_at']
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</main>


<script>

const materials = <?= json_encode(
    $materials,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_AMP |
    JSON_HEX_QUOT
) ?>;


/*
|--------------------------------------------------------------------------
| MATERIAL SELECTION
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', function () {

    const materialSelect =
        document.getElementById('material_id');

    const quantityInput =
        document.getElementById('issue_quantity');

    const issueButton =
        document.getElementById('issueButton');

    const warning =
        document.getElementById('stock_warning');


    materialSelect.addEventListener('change', function () {

        const selectedId =
            parseInt(this.value);

        const currentStock =
            document.getElementById('current_stock');

        const materialUnit =
            document.getElementById('material_unit');


        if (!selectedId) {

            currentStock.value = '0';
            materialUnit.value = '-';

            return;
        }


        const material =
            materials.find(function (item) {

                return parseInt(item.id) === selectedId;

            });


        if (material) {

            currentStock.value =
                parseFloat(
                    material.current_stock
                ).toFixed(2);

            materialUnit.value =
                material.unit;

            quantityInput.max =
                parseFloat(
                    material.current_stock
                );

            validateQuantity();

        }

    });


    /*
    |--------------------------------------------------------------------------
    | VALIDATE QUANTITY WHILE TYPING
    |--------------------------------------------------------------------------
    */

    quantityInput.addEventListener(
        'input',
        validateQuantity
    );


    function validateQuantity()
    {
        const selectedId =
            parseInt(materialSelect.value);

        const quantity =
            parseFloat(quantityInput.value) || 0;


        if (!selectedId) {

            warning.classList.add('d-none');
            issueButton.disabled = false;

            return;
        }


        const material =
            materials.find(function (item) {

                return parseInt(item.id) === selectedId;

            });


        if (!material) {

            return;
        }


        const availableStock =
            parseFloat(material.current_stock);


        if (quantity > availableStock) {

            warning.classList.remove('d-none');

            issueButton.disabled = true;

        } else {

            warning.classList.add('d-none');

            issueButton.disabled = false;

        }
    }

});


/*
|--------------------------------------------------------------------------
| RESET
|--------------------------------------------------------------------------
*/

function resetMaterialInfo()
{
    setTimeout(function () {

        document.getElementById(
            'current_stock'
        ).value = '0';

        document.getElementById(
            'material_unit'
        ).value = '-';

        document.getElementById(
            'issue_quantity'
        ).value = '';

        document.getElementById(
            'stock_warning'
        ).classList.add('d-none');

        document.getElementById(
            'issueButton'
        ).disabled = false;

    }, 50);
}

</script>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>