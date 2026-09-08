<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/store_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Material Master';

$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| MATERIAL CATEGORIES
|--------------------------------------------------------------------------
| Categories are maintained in material_categories.php and loaded from the
| database. Only enabled categories are available for new/edit materials.
*/
$stmt = $con->query("
    SELECT id, category_name
    FROM material_categories
    WHERE status = 'Enable'
    ORDER BY category_name ASC
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| ADD MATERIAL
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'add_material') {

    $materialCode = trim($_POST['material_code'] ?? '');
    $materialName = trim($_POST['material_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $minimumStock = (float)($_POST['minimum_stock'] ?? 0);

    if ($materialCode === '') {

        $message = 'Material code is required.';
        $messageType = 'danger';

    } elseif ($materialName === '') {

        $message = 'Material name is required.';
        $messageType = 'danger';

    } elseif ($unit === '') {

        $message = 'Unit is required.';
        $messageType = 'danger';

    } elseif ($minimumStock < 0) {

        $message = 'Minimum stock cannot be negative.';
        $messageType = 'danger';

    } else {

        // Check duplicate material code
        $stmt = $con->prepare("
            SELECT COUNT(*)
            FROM materials
            WHERE material_code = :material_code
        ");

        $stmt->execute([
            ':material_code' => $materialCode
        ]);

        $exists = (int)$stmt->fetchColumn();

        if ($exists > 0) {

            $message = 'Material code already exists.';
            $messageType = 'danger';

        } else {

            $stmt = $con->prepare("
                INSERT INTO materials
                (
                    material_code,
                    material_name,
                    category,
                    unit,
                    minimum_stock,
                    current_stock,
                    status
                )
                VALUES
                (
                    :material_code,
                    :material_name,
                    :category,
                    :unit,
                    :minimum_stock,
                    0,
                    'Enable'
                )
            ");

            $stmt->execute([
                ':material_code' => $materialCode,
                ':material_name' => $materialName,
                ':category' => $category,
                ':unit' => $unit,
                ':minimum_stock' => $minimumStock
            ]);

            $message = 'Material added successfully.';
            $messageType = 'success';
        }
    }
}


/*
|--------------------------------------------------------------------------
| EDIT MATERIAL
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'edit_material') {

    $materialId = (int)($_POST['material_id'] ?? 0);

    $materialCode = trim($_POST['material_code'] ?? '');
    $materialName = trim($_POST['material_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $minimumStock = (float)($_POST['minimum_stock'] ?? 0);

    if ($materialId <= 0) {

        $message = 'Invalid material.';
        $messageType = 'danger';

    } elseif ($materialCode === '') {

        $message = 'Material code is required.';
        $messageType = 'danger';

    } elseif ($materialName === '') {

        $message = 'Material name is required.';
        $messageType = 'danger';

    } elseif ($unit === '') {

        $message = 'Unit is required.';
        $messageType = 'danger';

    } elseif ($minimumStock < 0) {

        $message = 'Minimum stock cannot be negative.';
        $messageType = 'danger';

    } else {

        // Check duplicate material code
        $stmt = $con->prepare("
            SELECT COUNT(*)
            FROM materials
            WHERE material_code = :material_code
              AND id != :id
        ");

        $stmt->execute([
            ':material_code' => $materialCode,
            ':id' => $materialId
        ]);

        $exists = (int)$stmt->fetchColumn();

        if ($exists > 0) {

            $message = 'Another material already uses this code.';
            $messageType = 'danger';

        } else {

            $stmt = $con->prepare("
                UPDATE materials
                SET
                    material_code = :material_code,
                    material_name = :material_name,
                    category = :category,
                    unit = :unit,
                    minimum_stock = :minimum_stock
                WHERE id = :id
            ");

            $stmt->execute([
                ':material_code' => $materialCode,
                ':material_name' => $materialName,
                ':category' => $category,
                ':unit' => $unit,
                ':minimum_stock' => $minimumStock,
                ':id' => $materialId
            ]);

            $message = 'Material updated successfully.';
            $messageType = 'success';
        }
    }
}


/*
|--------------------------------------------------------------------------
| DISABLE MATERIAL
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'disable_material') {

    $materialId = (int)($_POST['material_id'] ?? 0);

    if ($materialId > 0) {

        $stmt = $con->prepare("
            UPDATE materials
            SET status = 'Disabled'
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $materialId
        ]);

        $message = 'Material disabled successfully.';
        $messageType = 'success';
    }
}


/*
|--------------------------------------------------------------------------
| ENABLE MATERIAL
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'enable_material') {

    $materialId = (int)($_POST['material_id'] ?? 0);

    if ($materialId > 0) {

        $stmt = $con->prepare("
            UPDATE materials
            SET status = 'Enable'
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $materialId
        ]);

        $message = 'Material enabled successfully.';
        $messageType = 'success';
    }
}


/*
|--------------------------------------------------------------------------
| FETCH MATERIALS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT
        id,
        material_code,
        material_name,
        category,
        unit,
        minimum_stock,
        current_stock,
        status,
        created_at
    FROM materials
    ORDER BY id DESC
");

$materials = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| SUMMARY COUNTS (for the stat cards)
|--------------------------------------------------------------------------
*/

$totalMaterials = count($materials);
$enabledCount = 0;
$disabledCount = 0;
$lowStockCount = 0;

foreach ($materials as $m) {

    if ($m['status'] === 'Enable') {
        $enabledCount++;
    } else {
        $disabledCount++;
    }

    if ((float)$m['current_stock'] <= (float)$m['minimum_stock']) {
        $lowStockCount++;
    }
}


/*
|--------------------------------------------------------------------------
| EDIT MATERIAL DATA
|--------------------------------------------------------------------------
*/

$editMaterial = null;

if (isset($_GET['edit'])) {

    $editId = (int)$_GET['edit'];

    if ($editId > 0) {

        $stmt = $con->prepare("
            SELECT
                id,
                material_code,
                material_name,
                category,
                unit,
                minimum_stock,
                current_stock,
                status
            FROM materials
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $editId
        ]);

        $editMaterial = $stmt->fetch();
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
                    Material Master
                </h4>

                <div class="text-muted">
                    Manage canteen store materials and minimum stock levels.
                </div>

            </div>

            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#addMaterialModal">

                <i class="fa-solid fa-plus me-1"></i>

                Add Material

            </button>

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


        <!-- STAT CARDS -->
        <div class="row g-3 mb-4">

            <div class="col-6 col-lg-3">

                <div class="stat-card d-flex align-items-center gap-3">

                    <div class="stat-icon stat-icon-primary">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>

                    <div>
                        <div class="stat-label">Total Materials</div>
                        <div class="stat-value"><?= $totalMaterials ?></div>
                    </div>

                </div>

            </div>

            <div class="col-6 col-lg-3">

                <div class="stat-card d-flex align-items-center gap-3">

                    <div class="stat-icon stat-icon-green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div>
                        <div class="stat-label">Enabled</div>
                        <div class="stat-value"><?= $enabledCount ?></div>
                    </div>

                </div>

            </div>

            <div class="col-6 col-lg-3">

                <div class="stat-card d-flex align-items-center gap-3">

                    <div class="stat-icon stat-icon-purple">
                        <i class="fa-solid fa-ban"></i>
                    </div>

                    <div>
                        <div class="stat-label">Disabled</div>
                        <div class="stat-value"><?= $disabledCount ?></div>
                    </div>

                </div>

            </div>

            <div class="col-6 col-lg-3">

                <div class="stat-card d-flex align-items-center gap-3">

                    <div class="stat-icon stat-icon-amber">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>

                    <div>
                        <div class="stat-label">Low Stock</div>
                        <div class="stat-value"><?= $lowStockCount ?></div>
                    </div>

                </div>

            </div>

        </div>


        <!-- MATERIAL TABLE -->
        <div class="content-card">

            <div class="content-card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <strong>
                        <i class="fa-solid fa-boxes-stacked me-1"></i>
                        Materials
                    </strong>
                    <span class="text-muted small">
                        <?= count($materials) ?> material(s)
                    </span>
                </div>
            </div>

            <div class="p-3 border-bottom">
                <ul class="nav nav-tabs stock-tabs" id="stockTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="all-stock-tab" data-bs-toggle="tab"
                                data-bs-target="#all-stock" type="button" role="tab">
                            <i class="fa-solid fa-boxes-stacked me-1"></i>
                            All Stock
                            <span class="badge bg-secondary ms-1"><?= count($materials) ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="low-stock-tab" data-bs-toggle="tab"
                                data-bs-target="#low-stock" type="button" role="tab">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            Low Stock
                            <span class="badge bg-danger ms-1"><?= $lowStockCount ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="out-stock-tab" data-bs-toggle="tab"
                                data-bs-target="#out-stock" type="button" role="tab">
                            <i class="fa-solid fa-circle-xmark me-1"></i>
                            Out of Stock
                            <span class="badge bg-dark ms-1">
                                <?php
                                $outOfStockCount = 0;
                                foreach ($materials as $m) {
                                    if ((float)$m['current_stock'] <= 0) {
                                        $outOfStockCount++;
                                    }
                                }
                                echo $outOfStockCount;
                                ?>
                            </span>
                        </button>
                    </li>
                </ul>
            </div>

            <div class="tab-content" id="stockTabsContent">

                <!-- ALL STOCK -->
                <div class="tab-pane fade show active" id="all-stock" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Material</th>
                                    <th>Category</th>
                                    <th>Unit</th>
                                    <th>Minimum Stock</th>
                                    <th>Current Stock</th>
                                    <th>Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($materials)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-box-open fs-2 d-block mb-2"></i>
                                        No materials found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($materials as $index => $material): ?>
                                    <?php
                                    $currentStock = (float)$material['current_stock'];
                                    $minimumStock = (float)$material['minimum_stock'];
                                    $isLowStock = $currentStock <= $minimumStock;
                                    $initials = strtoupper(substr($material['material_name'], 0, 1));
                                    ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="row-avatar"><?= e($initials) ?></span>
                                                <div>
                                                    <div class="fw-semibold"><?= e($material['material_name']) ?></div>
                                                    <div class="text-muted small"><?= e($material['material_code']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= e($material['category'] ?: '-') ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= e($material['unit']) ?></span></td>
                                        <td><?= number_format($minimumStock, 2) ?></td>
                                        <td>
                                            <span class="<?= $isLowStock ? 'text-danger fw-bold' : 'text-success fw-semibold' ?>">
                                                <?= number_format($currentStock, 2) ?>
                                            </span>
                                            <?php if ($isLowStock): ?>
                                                <span class="badge badge-disabled ms-1">Low</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($material['status'] === 'Enable'): ?>
                                                <span class="badge badge-enable">Enable</span>
                                            <?php else: ?>
                                                <span class="badge badge-disabled">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <a href="?edit=<?= (int)$material['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                <?php if ($material['status'] === 'Enable'): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to disable this material?');">
                                                        <input type="hidden" name="action" value="disable_material">
                                                        <input type="hidden" name="material_id" value="<?= (int)$material['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Disable">
                                                            <i class="fa-solid fa-ban"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Enable this material?');">
                                                        <input type="hidden" name="action" value="enable_material">
                                                        <input type="hidden" name="material_id" value="<?= (int)$material['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Enable">
                                                            <i class="fa-solid fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- LOW STOCK -->
                <div class="tab-pane fade" id="low-stock" role="tabpanel">
                    <form method="POST" action="purchase_requests.php" id="lowStockPurchaseForm">
                        <input type="hidden" name="remarks" value="Purchase request created from Low Stock in Material Master.">

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-bottom">
                            <div>
                                <strong>Low Stock Materials</strong>
                                <div class="text-muted small">
                                    Select the materials you want to purchase. Requested quantity is calculated from the minimum stock level.
                                </div>
                            </div>
                            <button type="submit" class="btn btn-warning" id="sendPurchaseRequestBtn" disabled>
                                <i class="fa-solid fa-cart-plus me-1"></i>
                                Send Purchase Request
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:45px;">
                                            <input type="checkbox" class="form-check-input" id="selectAllLowStock" title="Select all">
                                        </th>
                                        <th>#</th>
                                        <th>Material</th>
                                        <th>Category</th>
                                        <th>Unit</th>
                                        <th>Minimum Stock</th>
                                        <th>Current Stock</th>
                                        <th style="width:150px;">Request Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $lowIndex = 0;
                                foreach ($materials as $material):
                                    $currentStock = (float)$material['current_stock'];
                                    $minimumStock = (float)$material['minimum_stock'];
                                    if ($currentStock > $minimumStock) {
                                        continue;
                                    }
                                    $lowIndex++;
                                    $shortage = max($minimumStock - $currentStock, 0);
                                    if ($shortage <= 0) {
                                        $shortage = $minimumStock > 0 ? $minimumStock : 1;
                                    }
                                    $initials = strtoupper(substr($material['material_name'], 0, 1));
                                ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox"
                                                   class="form-check-input low-stock-checkbox"
                                                   name="material_id[]"
                                                   value="<?= (int)$material['id'] ?>">
                                        </td>
                                        <td><?= $lowIndex ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="row-avatar"><?= e($initials) ?></span>
                                                <div>
                                                    <div class="fw-semibold"><?= e($material['material_name']) ?></div>
                                                    <div class="text-muted small"><?= e($material['material_code']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= e($material['category'] ?: '-') ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= e($material['unit']) ?></span></td>
                                        <td><?= number_format($minimumStock, 2) ?></td>
                                        <td>
                                            <span class="text-danger fw-bold"><?= number_format($currentStock, 2) ?></span>
                                            <?php if ($currentStock <= 0): ?>
                                                <span class="badge bg-dark ms-1">Out</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <input type="number"
                                                name="quantity[]"
                                                class="form-control form-control-sm low-stock-quantity"
                                                min="0.01"
                                                step="0.01"
                                                value="<?= e(number_format($shortage, 2, '.', '')) ?>"
                                                disabled>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if ($lowIndex === 0): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-5">
                                            <i class="fa-solid fa-circle-check fs-2 d-block mb-2"></i>
                                            No low-stock materials found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>

                <!-- OUT OF STOCK -->
                <div class="tab-pane fade" id="out-stock" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Material</th>
                                    <th>Category</th>
                                    <th>Unit</th>
                                    <th>Minimum Stock</th>
                                    <th>Current Stock</th>
                                    <th>Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $outIndex = 0;
                            foreach ($materials as $material):
                                $currentStock = (float)$material['current_stock'];
                                if ($currentStock > 0) {
                                    continue;
                                }
                                $outIndex++;
                                $initials = strtoupper(substr($material['material_name'], 0, 1));
                            ?>
                                <tr>
                                    <td><?= $outIndex ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="row-avatar"><?= e($initials) ?></span>
                                            <div>
                                                <div class="fw-semibold"><?= e($material['material_name']) ?></div>
                                                <div class="text-muted small"><?= e($material['material_code']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= e($material['category'] ?: '-') ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= e($material['unit']) ?></span></td>
                                    <td><?= number_format((float)$material['minimum_stock'], 2) ?></td>
                                    <td><span class="text-danger fw-bold">0.00</span> <span class="badge bg-dark ms-1">Out</span></td>
                                    <td>
                                        <?php if ($material['status'] === 'Enable'): ?>
                                            <span class="badge badge-enable">Enable</span>
                                        <?php else: ?>
                                            <span class="badge badge-disabled">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="?edit=<?= (int)$material['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if ($outIndex === 0): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-circle-check fs-2 d-block mb-2"></i>
                                        No out-of-stock materials found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

</main>


<!-- ==========================================================
     ADD MATERIAL MODAL
========================================================== -->

<div
    class="modal fade edit-user-modal"
    id="addMaterialModal"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="add_material">


                <div class="modal-header">

                    <div>

                        <div class="modal-title-text">

                            <i class="fa-solid fa-plus me-1"></i>

                            Add Material

                        </div>

                        <div class="modal-subtitle-text">
                            Create a new item in the material master.
                        </div>

                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">

                    <div class="edit-user-section">

                        <div class="edit-user-section-label">
                            Material Details
                        </div>

                        <div class="row g-3">

                            <!-- CODE -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Material Code
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="material_code"
                                    class="form-control"
                                    maxlength="50"
                                    required
                                    placeholder="Example: MAT006">

                            </div>


                            <!-- NAME -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Material Name
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="material_name"
                                    class="form-control"
                                    maxlength="150"
                                    required
                                    placeholder="Example: Sugar">

                            </div>


                            <!-- CATEGORY -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Category
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="category"
                                    class="form-select"
                                    required>

                                    <option value="">
                                        --- Select Category ---
                                    </option>

                                    <?php foreach ($categories as $categoryOption): ?>
                                        <option value="<?= e($categoryOption['category_name']) ?>">
                                            <?= e($categoryOption['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- UNIT -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Unit
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="unit"
                                    class="form-select"
                                    required>

                                    <option value="">
                                        --- Select Unit ---
                                    </option>

                                    <option value="KG">
                                        KG
                                    </option>

                                    <option value="GRAM">
                                        Gram
                                    </option>

                                    <option value="LTR">
                                        Litre
                                    </option>

                                    <option value="ML">
                                        ML
                                    </option>

                                    <option value="PCS">
                                        Pieces
                                    </option>

                                    <option value="PACKET">
                                        Packet
                                    </option>

                                    <option value="BOX">
                                        Box
                                    </option>

                                    <option value="BAG">
                                        Bag
                                    </option>

                                    <option value="BOTTLE">
                                        Bottle
                                    </option>

                                </select>

                            </div>


                            <!-- MINIMUM STOCK -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Minimum Stock
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="number"
                                    name="minimum_stock"
                                    class="form-control"
                                    min="0"
                                    step="0.01"
                                    value="0"
                                    required>

                                <small class="text-muted">
                                    Low-stock alert will appear when current
                                    stock reaches this value.
                                </small>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="fa-solid fa-save me-1"></i>

                        Save Material

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ==========================================================
     EDIT MATERIAL MODAL
========================================================== -->

<?php if ($editMaterial !== false && $editMaterial !== null): ?>

<div
    class="modal fade edit-user-modal"
    id="editMaterialModal"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="edit_material">

                <input
                    type="hidden"
                    name="material_id"
                    value="<?= (int)$editMaterial['id'] ?>">


                <div class="modal-header">

                    <div>

                        <div class="modal-title-text">

                            <i class="fa-solid fa-pen me-1"></i>

                            Edit Material

                        </div>

                        <div class="modal-subtitle-text">
                            Update details for this material.
                        </div>

                    </div>

                    <a
                        href="materials.php"
                        class="btn-close">
                    </a>

                </div>


                <div class="modal-body">

                    <div class="edit-user-identity">

                        <span class="row-avatar">
                            <?= e(strtoupper(substr($editMaterial['material_name'], 0, 1))) ?>
                        </span>

                        <div>

                            <div class="edit-user-identity-name">
                                <?= e($editMaterial['material_name']) ?>
                            </div>

                            <div class="edit-user-identity-meta">
                                <?= e($editMaterial['material_code']) ?>

                                <?php if ($editMaterial['status'] === 'Enable'): ?>

                                    <span class="badge badge-enable">
                                        Enable
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-disabled">
                                        Disabled
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>


                    <div class="edit-user-section">

                        <div class="edit-user-section-label">
                            Material Details
                        </div>

                        <div class="row g-3">

                            <!-- CODE -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Material Code
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="material_code"
                                    class="form-control"
                                    maxlength="50"
                                    required
                                    value="<?= e($editMaterial['material_code']) ?>">

                            </div>


                            <!-- NAME -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Material Name
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="material_name"
                                    class="form-control"
                                    maxlength="150"
                                    required
                                    value="<?= e($editMaterial['material_name']) ?>">

                            </div>


                            <!-- CATEGORY -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Category
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="category"
                                    class="form-select"
                                    required>

                                    <option value="">
                                        --- Select Category ---
                                    </option>

                                    <?php foreach ($categories as $categoryOption): ?>
                                        <option
                                            value="<?= e($categoryOption['category_name']) ?>"
                                            <?= $editMaterial['category'] === $categoryOption['category_name'] ? 'selected' : '' ?>>
                                            <?= e($categoryOption['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- UNIT -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Unit
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="unit"
                                    class="form-select"
                                    required>

                                    <option value="">
                                        --- Select Unit ---
                                    </option>

                                    <?php

                                    $units = [
                                        'KG' => 'KG',
                                        'GRAM' => 'Gram',
                                        'LTR' => 'Litre',
                                        'ML' => 'ML',
                                        'PCS' => 'Pieces',
                                        'PACKET' => 'Packet',
                                        'BOX' => 'Box',
                                        'BAG' => 'Bag',
                                        'BOTTLE' => 'Bottle'
                                    ];

                                    foreach ($units as $unitValue => $unitLabel):

                                    ?>

                                        <option
                                            value="<?= e($unitValue) ?>"
                                            <?= $editMaterial['unit'] === $unitValue
                                                ? 'selected'
                                                : ''
                                            ?>>

                                            <?= e($unitLabel) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                        </div>

                    </div>


                    <div class="edit-user-section">

                        <div class="edit-user-section-label">
                            Stock
                        </div>

                        <div class="row g-3">

                            <!-- CURRENT STOCK -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Current Stock
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    disabled
                                    value="<?= number_format(
                                        (float)$editMaterial['current_stock'],
                                        2
                                    ) ?>">

                                <small class="text-muted">
                                    Current stock must be changed through
                                    Stock Inward / Stock Issue.
                                </small>

                            </div>


                            <!-- MINIMUM STOCK -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Minimum Stock
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="number"
                                    name="minimum_stock"
                                    class="form-control"
                                    min="0"
                                    step="0.01"
                                    required
                                    value="<?= e(
                                        (string)$editMaterial['minimum_stock']
                                    ) ?>">

                            </div>

                        </div>

                    </div>

                </div>


                <div class="modal-footer">

                    <a
                        href="materials.php"
                        class="btn btn-secondary">

                        Cancel

                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="fa-solid fa-save me-1"></i>

                        Update Material

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const editModalElement =
        document.getElementById('editMaterialModal');

    if (editModalElement) {

        const editModal =
            new bootstrap.Modal(editModalElement);

        editModal.show();

    }

});
</script>

<?php endif; ?>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('selectAllLowStock');
    const checkboxes = Array.from(document.querySelectorAll('.low-stock-checkbox'));
    const quantities = Array.from(document.querySelectorAll('.low-stock-quantity'));
    const submitButton = document.getElementById('sendPurchaseRequestBtn');
    const form = document.getElementById('lowStockPurchaseForm');

    function updatePurchaseButton() {
        const selectedCount = checkboxes.filter(function (checkbox) {
            return checkbox.checked;
        }).length;

        submitButton.disabled = selectedCount === 0;
    }

    checkboxes.forEach(function (checkbox, index) {
        checkbox.addEventListener('change', function () {
            if (quantities[index]) {
                quantities[index].disabled = !checkbox.checked;
            }
            updatePurchaseButton();

            if (selectAll) {
                selectAll.checked = checkboxes.length > 0 &&
                    checkboxes.every(function (item) { return item.checked; });
                selectAll.indeterminate = selectedCountForHeader() > 0 &&
                    !selectAll.checked;
            }
        });
    });

    function selectedCountForHeader() {
        return checkboxes.filter(function (checkbox) {
            return checkbox.checked;
        }).length;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(function (checkbox, index) {
                checkbox.checked = selectAll.checked;
                if (quantities[index]) {
                    quantities[index].disabled = !selectAll.checked;
                }
            });
            selectAll.indeterminate = false;
            updatePurchaseButton();
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            const selected = checkboxes.filter(function (checkbox) {
                return checkbox.checked;
            });

            if (selected.length === 0) {
                event.preventDefault();
                alert('Please select at least one low-stock material.');
                return;
            }

            for (let i = 0; i < selected.length; i++) {
                const checkbox = selected[i];
                const index = checkboxes.indexOf(checkbox);
                const quantity = quantities[index];

                if (!quantity || parseFloat(quantity.value) <= 0) {
                    event.preventDefault();
                    alert('Requested quantity must be greater than zero.');
                    if (quantity) quantity.focus();
                    return;
                }
            }

            if (!confirm('Create a purchase request for the selected materials?')) {
                event.preventDefault();
            }
        });
    }

    updatePurchaseButton();
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>