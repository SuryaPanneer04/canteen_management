<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/store_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Material Categories';
$message = '';
$messageType = '';

/* ADD CATEGORY */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_category') {
    $categoryName = trim($_POST['category_name'] ?? '');

    if ($categoryName === '') {
        $message = 'Category name is required.';
        $messageType = 'danger';
    } else {
        $stmt = $con->prepare("SELECT COUNT(*) FROM material_categories WHERE LOWER(category_name) = LOWER(:category_name)");
        $stmt->execute([':category_name' => $categoryName]);

        if ((int)$stmt->fetchColumn() > 0) {
            $message = 'Category already exists.';
            $messageType = 'danger';
        } else {
            $stmt = $con->prepare("INSERT INTO material_categories (category_name, status) VALUES (:category_name, 'Enable')");
            $stmt->execute([':category_name' => $categoryName]);
            $message = 'Category added successfully.';
            $messageType = 'success';
        }
    }
}

/* EDIT CATEGORY */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_category') {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $categoryName = trim($_POST['category_name'] ?? '');

    if ($categoryId <= 0) {
        $message = 'Invalid category.';
        $messageType = 'danger';
    } elseif ($categoryName === '') {
        $message = 'Category name is required.';
        $messageType = 'danger';
    } else {
        $stmt = $con->prepare("SELECT category_name FROM material_categories WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $categoryId]);
        $oldName = $stmt->fetchColumn();

        if ($oldName === false) {
            $message = 'Category not found.';
            $messageType = 'danger';
        } else {
            $stmt = $con->prepare("SELECT COUNT(*) FROM material_categories WHERE LOWER(category_name) = LOWER(:category_name) AND id != :id");
            $stmt->execute([':category_name' => $categoryName, ':id' => $categoryId]);

            if ((int)$stmt->fetchColumn() > 0) {
                $message = 'Another category already uses this name.';
                $messageType = 'danger';
            } else {
                try {
                    $con->beginTransaction();

                    $stmt = $con->prepare("UPDATE material_categories SET category_name = :category_name WHERE id = :id");
                    $stmt->execute([':category_name' => $categoryName, ':id' => $categoryId]);

                    // Keep existing materials linked to the renamed category because
                    // the current materials table stores category as its name.
                    $stmt = $con->prepare("UPDATE materials SET category = :new_name WHERE category = :old_name");
                    $stmt->execute([':new_name' => $categoryName, ':old_name' => $oldName]);

                    $con->commit();
                    $message = 'Category updated successfully.';
                    $messageType = 'success';
                } catch (Throwable $e) {
                    if ($con->inTransaction()) {
                        $con->rollBack();
                    }
                    $message = 'Unable to update category: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }
    }
}

/* DISABLE CATEGORY */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'disable_category') {
    $categoryId = (int)($_POST['category_id'] ?? 0);

    if ($categoryId > 0) {
        $stmt = $con->prepare("UPDATE material_categories SET status = 'Disabled' WHERE id = :id");
        $stmt->execute([':id' => $categoryId]);
        $message = 'Category disabled successfully.';
        $messageType = 'success';
    }
}

/* ENABLE CATEGORY */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enable_category') {
    $categoryId = (int)($_POST['category_id'] ?? 0);

    if ($categoryId > 0) {
        $stmt = $con->prepare("UPDATE material_categories SET status = 'Enable' WHERE id = :id");
        $stmt->execute([':id' => $categoryId]);
        $message = 'Category enabled successfully.';
        $messageType = 'success';
    }
}

$stmt = $con->query("SELECT id, category_name, status, created_at, updated_at FROM material_categories ORDER BY category_name ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$editCategory = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        $stmt = $con->prepare("SELECT id, category_name, status FROM material_categories WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $editId]);
        $editCategory = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <div class="page-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Material Categories</h4>
                <div class="text-muted">Manage categories used in Material Master.</div>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fa-solid fa-plus me-1"></i> Add Category
            </button>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">#</th>
                                <th>Category Name</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-center pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$categories): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">No categories found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $index => $category): ?>
                                <tr>
                                    <td class="ps-4"><?= $index + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($category['category_name']) ?></strong></td>
                                    <td>
                                        <?php if ($category['status'] === 'Enable'): ?>
                                            <span class="badge bg-success">Enable</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($category['created_at']))) ?></td>
                                    <td class="text-center pe-4">
                                        <a href="categories.php?edit=<?= (int)$category['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </a>

                                        <?php if ($category['status'] === 'Enable'): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Disable this category?');">
                                                <input type="hidden" name="action" value="disable_category">
                                                <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fa-solid fa-ban"></i> Disable
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Enable this category?');">
                                                <input type="hidden" name="action" value="enable_category">
                                                <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                    <i class="fa-solid fa-check"></i> Enable
                                                </button>
                                            </form>
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
    </div>
</main>

<!-- ADD CATEGORY MODAL -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_category">
                <div class="modal-header">
                    <h5 class="modal-title">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="category_name" class="form-control" maxlength="100" required autofocus>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT CATEGORY MODAL -->
<?php if ($editCategory): ?>
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="category_id" value="<?= (int)$editCategory['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <a href="categories.php" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <label class="form-label">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="category_name" class="form-control" maxlength="100" required value="<?= htmlspecialchars($editCategory['category_name']) ?>">
                </div>
                <div class="modal-footer">
                    <a href="categories.php" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('editCategoryModal');
    if (modalElement && typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(modalElement).show();
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
