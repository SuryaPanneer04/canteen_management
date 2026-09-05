<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/store_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Stock Inward';

$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| ADD STOCK / STOCK INWARD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'stock_inward'
) {

    $materialId = (int)($_POST['material_id'] ?? 0);
    $quantity = (float)($_POST['quantity'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($materialId <= 0) {

        $message = 'Please select a material.';
        $messageType = 'danger';

    } elseif ($quantity <= 0) {

        $message = 'Quantity must be greater than zero.';
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
            | GET MATERIAL
            |--------------------------------------------------------------------------
            |
            | FOR UPDATE prevents two users from modifying the same stock
            | at the same time.
            |
            */

            $stmt = $con->prepare("
                SELECT
                    id,
                    material_name,
                    unit,
                    current_stock,
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
                    'This material is disabled and cannot receive stock.'
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

                /*
                 * Different projects store the user ID under
                 * different keys. Try the common keys.
                 */

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
            | UPDATE CURRENT STOCK
            |--------------------------------------------------------------------------
            */

            $stmt = $con->prepare("
                UPDATE materials
                SET current_stock = current_stock + :quantity
                WHERE id = :id
            ");

            $stmt->execute([
                ':quantity' => $quantity,
                ':id' => $materialId
            ]);


            /*
            |--------------------------------------------------------------------------
            | INSERT STOCK TRANSACTION
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
                    'PURCHASE',
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


            $message =
                'Stock inward completed successfully for '
                . $material['material_name']
                . '. Added '
                . number_format($quantity, 2)
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

            $message =
                'Unable to add stock. '
                . $e->getMessage();

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
| RECENT INWARD TRANSACTIONS
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

    WHERE st.transaction_type = 'PURCHASE'

    ORDER BY st.id DESC

    LIMIT 15
");

$recentInward = $stmt->fetchAll();


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
                    Stock Inward
                </h4>

                <div class="text-muted">
                    Add purchased materials to the store stock.
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


        <!-- STOCK INWARD FORM -->
        <div class="content-card mb-4">

            <div class="content-card-header">

                <strong>

                    <i class="fa-solid fa-arrow-right-to-bracket me-1"></i>

                    Add Stock

                </strong>

            </div>


            <div class="p-4">

                <form method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="stock_inward">


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


                        <!-- CURRENT STOCK -->
                        <div class="col-md-3">

                            <label class="form-label">
                                Current Stock
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

                                Inward Quantity

                                <span class="text-danger">*</span>

                            </label>

                            <input
                                type="number"
                                name="quantity"
                                class="form-control"
                                min="0.01"
                                step="0.01"
                                required
                                placeholder="Enter quantity">

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
                                placeholder="Example: Purchase invoice / supplier details">

                        </div>


                        <!-- SUBMIT -->
                        <div class="col-12">

                            <button
                                type="submit"
                                class="btn btn-success">

                                <i class="fa-solid fa-plus me-1"></i>

                                Add Stock

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


        <!-- RECENT STOCK INWARD -->
        <div class="content-card">

            <div class="content-card-header">

                <div class="d-flex justify-content-between align-items-center">

                    <strong>

                        <i class="fa-solid fa-clock-rotate-left me-1"></i>

                        Recent Stock Inward

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

                            <th>Created By</th>

                            <th>Date</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($recentInward)): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="text-center text-muted py-5">

                                <i class="fa-solid fa-inbox fs-2 d-block mb-2"></i>

                                No stock inward transactions found.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($recentInward as $index => $row): ?>

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

                                    <span class="badge bg-success">

                                        +
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
| SHOW MATERIAL STOCK INFORMATION
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', function () {

    const materialSelect =
        document.getElementById('material_id');

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

        } else {

            currentStock.value = '0';
            materialUnit.value = '-';

        }

    });

});


/*
|--------------------------------------------------------------------------
| RESET MATERIAL INFORMATION
|--------------------------------------------------------------------------
*/

function resetMaterialInfo()
{
    setTimeout(function () {

        document.getElementById('current_stock').value = '0';

        document.getElementById('material_unit').value = '-';

    }, 50);
}

</script>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>