<?php
declare(strict_types=1);

// Admin auth check
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is Super Admin
$role = $_SESSION['role_name'] ?? '';
if ($role !== 'Super Admin') {
    header('Location: ../index.php');
    exit;
}

$pageTitle = 'Price Master';
$success = null;
$error = null;

/*
|--------------------------------------------------------------------------
| UPDATE PRICES ON FORM SUBMIT
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_prices'])) {
    try {
        $prices = $_POST['prices'] ?? [];
        
        $con->beginTransaction();
        
        // Prepare update query
        $updateStmt = $con->prepare("
            UPDATE materials 
            SET unit_price = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        foreach ($prices as $materialId => $price) {
            $materialId = (int)$materialId;
            $price = (float)$price;
            
            if ($price >= 0 && $materialId > 0) {
                $updateStmt->execute([$price, $materialId]);
            }
        }
        
        $con->commit();
        $success = "Material prices updated successfully.";
        
    } catch (Throwable $e) {
        if ($con->inTransaction()) {
            $con->rollBack();
        }
        $error = "Failed to update prices: " . $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| FETCH ALL MATERIALS
|--------------------------------------------------------------------------
*/
$materials = $con->query("
    SELECT id, material_code, material_name, category, unit, unit_price, status 
    FROM materials 
    ORDER BY material_name ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <div class="page-body">
        
        <div class="mb-4">
            <h4 class="mb-1">Price Master</h4>
            <div class="text-muted">Manage standard rates for all cooking materials.</div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fa-solid fa-circle-check me-2"></i> <?= e($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= e($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="content-card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong><i class="fa-solid fa-indian-rupee-sign me-2"></i> Material Pricing list</strong>
                </div>
                <!-- Submit form button triggers the form below -->
                <button type="submit" form="priceForm" name="update_prices" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-save me-1"></i> Save Changes
                </button>
            </div>

            <div class="content-card-body p-0">
                <form method="POST" id="priceForm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Material Code</th>
                                    <th>Material Name</th>
                                    <th>Category</th>
                                    <th>Unit</th>
                                    <th width="200">Rate / Price (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($materials)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No materials found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($materials as $index => $material): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><strong><?= e($material['material_code']) ?></strong></td>
                                            <td><?= e($material['material_name']) ?></td>
                                            <td><?= e($material['category']) ?></td>
                                            <td><span class="badge bg-secondary"><?= e($material['unit']) ?></span></td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">₹</span>
                                                    <!-- Array input to submit all rows at once -->
                                                    <input 
                                                        type="number" 
                                                        name="prices[<?= $material['id'] ?>]" 
                                                        class="form-control" 
                                                        min="0" 
                                                        step="0.01" 
                                                        value="<?= e($material['unit_price'] ?? '0.00') ?>"
                                                        <?= $material['status'] === 'Disable' ? 'readonly' : '' ?>
                                                    >
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>