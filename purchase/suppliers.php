<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

if (!in_array($_SESSION['role_name'] ?? '', ['Purchase', 'Super Admin'], true)) {
    header('Location: ../index.php');
    exit;
}

$pageTitle = 'Supplier Management';

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| HANDLE ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | ADD SUPPLIER
    |--------------------------------------------------------------------------
    */

    if ($action === 'add_supplier') {

        $supplierCode = trim($_POST['supplier_code'] ?? '');
        $supplierName = trim($_POST['supplier_name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $gstNumber = trim($_POST['gst_number'] ?? '');

        if ($supplierCode === '') {
            $error = 'Please enter supplier code.';
        } elseif ($supplierName === '') {
            $error = 'Please enter supplier name.';
        } else {

            /*
            |--------------------------------------------------------------------------
            | CHECK DUPLICATE SUPPLIER CODE
            |--------------------------------------------------------------------------
            */

            $stmt = $con->prepare("
                SELECT id
                FROM suppliers
                WHERE supplier_code = ?
                LIMIT 1
            ");

            $stmt->execute([$supplierCode]);

            if ($stmt->fetch()) {

                $error = 'Supplier code already exists.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | INSERT SUPPLIER
                |--------------------------------------------------------------------------
                */

                $stmt = $con->prepare("
                    INSERT INTO suppliers
                    (
                        supplier_code,
                        supplier_name,
                        contact_person,
                        phone,
                        email,
                        address,
                        gst_number,
                        status,
                        created_by
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, 'Enable', ?)
                ");

                $stmt->execute([
                    $supplierCode,
                    $supplierName,
                    $contactPerson !== '' ? $contactPerson : null,
                    $phone !== '' ? $phone : null,
                    $email !== '' ? $email : null,
                    $address !== '' ? $address : null,
                    $gstNumber !== '' ? $gstNumber : null,
                    $_SESSION['user_id'] ?? null
                ]);

                $success = 'Supplier added successfully.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT SUPPLIER
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'edit_supplier') {

        $supplierId = (int)($_POST['supplier_id'] ?? 0);

        $supplierCode = trim($_POST['supplier_code'] ?? '');
        $supplierName = trim($_POST['supplier_name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $gstNumber = trim($_POST['gst_number'] ?? '');

        if ($supplierId <= 0) {

            $error = 'Invalid supplier.';

        } elseif ($supplierCode === '') {

            $error = 'Please enter supplier code.';

        } elseif ($supplierName === '') {

            $error = 'Please enter supplier name.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | CHECK DUPLICATE CODE
            |--------------------------------------------------------------------------
            */

            $stmt = $con->prepare("
                SELECT id
                FROM suppliers
                WHERE supplier_code = ?
                AND id != ?
                LIMIT 1
            ");

            $stmt->execute([
                $supplierCode,
                $supplierId
            ]);

            if ($stmt->fetch()) {

                $error = 'Another supplier already uses this supplier code.';

            } else {

                $stmt = $con->prepare("
                    UPDATE suppliers
                    SET
                        supplier_code = ?,
                        supplier_name = ?,
                        contact_person = ?,
                        phone = ?,
                        email = ?,
                        address = ?,
                        gst_number = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $supplierCode,
                    $supplierName,
                    $contactPerson !== '' ? $contactPerson : null,
                    $phone !== '' ? $phone : null,
                    $email !== '' ? $email : null,
                    $address !== '' ? $address : null,
                    $gstNumber !== '' ? $gstNumber : null,
                    $supplierId
                ]);

                $success = 'Supplier updated successfully.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DISABLE SUPPLIER
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'disable_supplier') {

        $supplierId = (int)($_POST['supplier_id'] ?? 0);

        if ($supplierId > 0) {

            $stmt = $con->prepare("
                UPDATE suppliers
                SET status = 'Disabled'
                WHERE id = ?
            ");

            $stmt->execute([$supplierId]);

            $success = 'Supplier disabled successfully.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ENABLE SUPPLIER
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'enable_supplier') {

        $supplierId = (int)($_POST['supplier_id'] ?? 0);

        if ($supplierId > 0) {

            $stmt = $con->prepare("
                UPDATE suppliers
                SET status = 'Enable'
                WHERE id = ?
            ");

            $stmt->execute([$supplierId]);

            $success = 'Supplier enabled successfully.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$statusFilter = $_GET['status'] ?? '';

/*
|--------------------------------------------------------------------------
| FETCH SUPPLIERS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        s.id,
        s.supplier_code,
        s.supplier_name,
        s.contact_person,
        s.phone,
        s.email,
        s.address,
        s.gst_number,
        s.status,
        s.created_at,
        u.employee_name AS created_by_name
    FROM suppliers s
    LEFT JOIN users u
        ON u.id = s.created_by
    WHERE 1=1
";

$params = [];

if ($search !== '') {

    $sql .= "
        AND (
            s.supplier_code LIKE ?
            OR s.supplier_name LIKE ?
            OR s.contact_person LIKE ?
            OR s.phone LIKE ?
            OR s.email LIKE ?
            OR s.gst_number LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($statusFilter === 'Enable' || $statusFilter === 'Disabled') {

    $sql .= " AND s.status = ? ";

    $params[] = $statusFilter;
}

$sql .= " ORDER BY s.id DESC ";

$stmt = $con->prepare($sql);
$stmt->execute($params);

$suppliers = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">

        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <div class="container-fluid py-4">

            <!-- PAGE HEADER -->

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>
                    <h4 class="mb-1">
                        <i class="fa-solid fa-truck-field me-2"></i>
                        Supplier Management
                    </h4>

                    <p class="text-muted mb-0">
                        Manage purchase suppliers.
                    </p>
                </div>

                <button
                    type="button"
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#addSupplierModal"
                >
                    <i class="fa-solid fa-plus me-2"></i>
                    Add Supplier
                </button>

            </div>


            <!-- ALERTS -->

            <?php if ($success !== ''): ?>

                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fa-solid fa-circle-check me-2"></i>
                    <?= e($success) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>
                </div>

            <?php endif; ?>


            <?php if ($error !== ''): ?>

                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                    <?= e($error) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>
                </div>

            <?php endif; ?>


            <!-- SEARCH -->

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-body">

                    <form method="get">

                        <div class="row g-3 align-items-end">

                            <div class="col-md-7">

                                <label class="form-label">
                                    Search Supplier
                                </label>

                                <input
                                    type="text"
                                    name="search"
                                    class="form-control"
                                    placeholder="Code, supplier name, contact, phone, email or GST..."
                                    value="<?= e($search) ?>"
                                >

                            </div>


                            <div class="col-md-3">

                                <label class="form-label">
                                    Status
                                </label>

                                <select
                                    name="status"
                                    class="form-select"
                                >

                                    <option value="">All Status</option>

                                    <option
                                        value="Enable"
                                        <?= $statusFilter === 'Enable' ? 'selected' : '' ?>
                                    >
                                        Enabled
                                    </option>

                                    <option
                                        value="Disabled"
                                        <?= $statusFilter === 'Disabled' ? 'selected' : '' ?>
                                    >
                                        Disabled
                                    </option>

                                </select>

                            </div>


                            <div class="col-md-2">

                                <button
                                    type="submit"
                                    class="btn btn-dark w-100"
                                >
                                    <i class="fa-solid fa-magnifying-glass me-1"></i>
                                    Search
                                </button>

                            </div>

                        </div>

                    </form>

                </div>

            </div>


            <!-- SUPPLIER TABLE -->

            <div class="card shadow-sm border-0">

                <div class="card-header bg-white">

                    <div class="d-flex justify-content-between align-items-center">

                        <strong>
                            Supplier List
                        </strong>

                        <span class="badge bg-secondary">
                            <?= count($suppliers) ?> Supplier(s)
                        </span>

                    </div>

                </div>


                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light">

                                <tr>
                                    <th>#</th>
                                    <th>Code</th>
                                    <th>Supplier</th>
                                    <th>Contact Person</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>GST Number</th>
                                    <th>Status</th>
                                    <th width="180">Action</th>
                                </tr>

                            </thead>


                            <tbody>

                            <?php if (!$suppliers): ?>

                                <tr>

                                    <td
                                        colspan="9"
                                        class="text-center text-muted py-5"
                                    >

                                        <i class="fa-solid fa-truck-field fa-2x mb-3"></i>

                                        <div>
                                            No suppliers found.
                                        </div>

                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($suppliers as $index => $supplier): ?>

                                    <tr>

                                        <td>
                                            <?= $index + 1 ?>
                                        </td>


                                        <td>
                                            <strong>
                                                <?= e($supplier['supplier_code']) ?>
                                            </strong>
                                        </td>


                                        <td>
                                            <strong>
                                                <?= e($supplier['supplier_name']) ?>
                                            </strong>

                                            <?php if (!empty($supplier['address'])): ?>

                                                <div class="small text-muted">
                                                    <?= e($supplier['address']) ?>
                                                </div>

                                            <?php endif; ?>

                                        </td>


                                        <td>
                                            <?= e($supplier['contact_person']) ?>
                                        </td>


                                        <td>
                                            <?= e($supplier['phone']) ?>
                                        </td>


                                        <td>
                                            <?= e($supplier['email']) ?>
                                        </td>


                                        <td>
                                            <?= e($supplier['gst_number']) ?>
                                        </td>


                                        <td>

                                            <?php if ($supplier['status'] === 'Enable'): ?>

                                                <span class="badge bg-success">
                                                    Enable
                                                </span>

                                            <?php else: ?>

                                                <span class="badge bg-secondary">
                                                    Disabled
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <div class="d-flex gap-1">

                                                <!-- EDIT -->

                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editSupplierModal<?= (int)$supplier['id'] ?>"
                                                    title="Edit"
                                                >
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>


                                                <?php if ($supplier['status'] === 'Enable'): ?>

                                                    <!-- DISABLE -->

                                                    <form
                                                        method="post"
                                                        onsubmit="return confirm('Are you sure you want to disable this supplier?');"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="disable_supplier"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="supplier_id"
                                                            value="<?= (int)$supplier['id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            title="Disable"
                                                        >
                                                            <i class="fa-solid fa-ban"></i>
                                                        </button>

                                                    </form>

                                                <?php else: ?>

                                                    <!-- ENABLE -->

                                                    <form method="post">

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="enable_supplier"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="supplier_id"
                                                            value="<?= (int)$supplier['id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-outline-success"
                                                            title="Enable"
                                                        >
                                                            <i class="fa-solid fa-check"></i>
                                                        </button>

                                                    </form>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    </tr>


                                    <!-- EDIT MODAL -->

                                    <div
                                        class="modal fade"
                                        id="editSupplierModal<?= (int)$supplier['id'] ?>"
                                        tabindex="-1"
                                    >

                                        <div class="modal-dialog modal-lg">

                                            <div class="modal-content">

                                                <form method="post">

                                                    <div class="modal-header">

                                                        <h5 class="modal-title">
                                                            <i class="fa-solid fa-pen me-2"></i>
                                                            Edit Supplier
                                                        </h5>

                                                        <button
                                                            type="button"
                                                            class="btn-close"
                                                            data-bs-dismiss="modal"
                                                        ></button>

                                                    </div>


                                                    <div class="modal-body">

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="edit_supplier"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="supplier_id"
                                                            value="<?= (int)$supplier['id'] ?>"
                                                        >


                                                        <div class="row g-3">

                                                            <div class="col-md-4">

                                                                <label class="form-label">
                                                                    Supplier Code
                                                                    <span class="text-danger">*</span>
                                                                </label>

                                                                <input
                                                                    type="text"
                                                                    name="supplier_code"
                                                                    class="form-control"
                                                                    value="<?= e($supplier['supplier_code']) ?>"
                                                                    required
                                                                >

                                                            </div>


                                                            <div class="col-md-8">

                                                                <label class="form-label">
                                                                    Supplier Name
                                                                    <span class="text-danger">*</span>
                                                                </label>

                                                                <input
                                                                    type="text"
                                                                    name="supplier_name"
                                                                    class="form-control"
                                                                    value="<?= e($supplier['supplier_name']) ?>"
                                                                    required
                                                                >

                                                            </div>


                                                            <div class="col-md-6">

                                                                <label class="form-label">
                                                                    Contact Person
                                                                </label>

                                                                <input
                                                                    type="text"
                                                                    name="contact_person"
                                                                    class="form-control"
                                                                    value="<?= e($supplier['contact_person']) ?>"
                                                                >

                                                            </div>


                                                            <div class="col-md-6">

                                                                <label class="form-label">
                                                                    Phone
                                                                </label>

                                                                <input
                                                                    type="text"
                                                                    name="phone"
                                                                    class="form-control"
                                                                    value="<?= e($supplier['phone']) ?>"
                                                                >

                                                            </div>


                                                            <div class="col-md-6">

                                                                <label class="form-label">
                                                                    Email
                                                                </label>

                                                                <input
                                                                    type="email"
                                                                    name="email"
                                                                    class="form-control"
                                                                    value="<?= e($supplier['email']) ?>"
                                                                >

                                                            </div>


                                                            <div class="col-md-6">

                                                                <label class="form-label">
                                                                    GST Number
                                                                </label>

                                                                <input
                                                                    type="text"
                                                                    name="gst_number"
                                                                    class="form-control"
                                                                    value="<?= e($supplier['gst_number']) ?>"
                                                                >

                                                            </div>


                                                            <div class="col-12">

                                                                <label class="form-label">
                                                                    Address
                                                                </label>

                                                                <textarea
                                                                    name="address"
                                                                    class="form-control"
                                                                    rows="3"
                                                                ><?= e($supplier['address']) ?></textarea>

                                                            </div>

                                                        </div>

                                                    </div>


                                                    <div class="modal-footer">

                                                        <button
                                                            type="button"
                                                            class="btn btn-secondary"
                                                            data-bs-dismiss="modal"
                                                        >
                                                            Cancel
                                                        </button>

                                                        <button
                                                            type="submit"
                                                            class="btn btn-primary"
                                                        >
                                                            <i class="fa-solid fa-save me-1"></i>
                                                            Update Supplier
                                                        </button>

                                                    </div>

                                                </form>

                                            </div>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>


<!-- ADD SUPPLIER MODAL -->

<div
    class="modal fade"
    id="addSupplierModal"
    tabindex="-1"
>

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form method="post">

                <div class="modal-header">

                    <h5 class="modal-title">
                        <i class="fa-solid fa-truck-field me-2"></i>
                        Add Supplier
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>


                <div class="modal-body">

                    <input
                        type="hidden"
                        name="action"
                        value="add_supplier"
                    >


                    <div class="row g-3">

                        <div class="col-md-4">

                            <label class="form-label">
                                Supplier Code
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="supplier_code"
                                class="form-control"
                                placeholder="SUP-001"
                                required
                            >

                        </div>


                        <div class="col-md-8">

                            <label class="form-label">
                                Supplier Name
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="supplier_name"
                                class="form-control"
                                placeholder="Enter supplier name"
                                required
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Contact Person
                            </label>

                            <input
                                type="text"
                                name="contact_person"
                                class="form-control"
                                placeholder="Contact person"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Phone
                            </label>

                            <input
                                type="text"
                                name="phone"
                                class="form-control"
                                placeholder="Phone number"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                placeholder="supplier@example.com"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                GST Number
                            </label>

                            <input
                                type="text"
                                name="gst_number"
                                class="form-control"
                                placeholder="GST number"
                            >

                        </div>


                        <div class="col-12">

                            <label class="form-label">
                                Address
                            </label>

                            <textarea
                                name="address"
                                class="form-control"
                                rows="3"
                                placeholder="Supplier address"
                            ></textarea>

                        </div>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="fa-solid fa-save me-1"></i>
                        Save Supplier
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>