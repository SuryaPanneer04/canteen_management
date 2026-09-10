<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
| Existing Kitchen and Super Admin login only.
*/

$role = $_SESSION['role_name'] ?? '';

if (!in_array($role, ['Kitchen', 'Super Admin'], true)) {
    header('Location: ../index.php');
    exit;
}

$pageTitle = 'Food Menu & Recipe';

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| LOGGED-IN USER
|--------------------------------------------------------------------------
*/

$loginUserId = (int)($_SESSION['user_id'] ?? 0);

if ($loginUserId <= 0 && !empty($_SESSION['user_data'])) {

    if (is_array($_SESSION['user_data'])) {

        foreach (['id', 'user_id', 'userid'] as $key) {

            if (isset($_SESSION['user_data'][$key])) {

                $loginUserId = (int)$_SESSION['user_data'][$key];

                break;
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| CREATE RECIPE TABLE IF NOT EXISTS
|--------------------------------------------------------------------------
| This makes the page work with the existing database without changing
| any existing login or existing table.
*/

$con->exec("
    CREATE TABLE IF NOT EXISTS food_recipes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        food_id INT UNSIGNED NOT NULL,
        material_id INT UNSIGNED NOT NULL,
        quantity_per_plate DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
        created_by INT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),

        UNIQUE KEY uq_food_material (
            food_id,
            material_id
        ),

        KEY idx_recipe_food (
            food_id
        ),

        KEY idx_recipe_material (
            material_id
        )
    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_general_ci
");


/*
|--------------------------------------------------------------------------
| CREATE FOOD
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {

        /*
        |--------------------------------------------------------------------------
        | SAVE FOOD
        |--------------------------------------------------------------------------
        */

        if ($action === 'save_food') {

            $foodCode = trim($_POST['food_code'] ?? '');
            $foodName = trim($_POST['food_name'] ?? '');
            $unit = trim($_POST['unit'] ?? 'PLATE');

            if ($foodName === '') {
                throw new RuntimeException(
                    'Please enter the food name.'
                );
            }

            /*
            | Generate food code automatically when empty.
            */

            if ($foodCode === '') {

                $stmt = $con->query("
                    SELECT food_code
                    FROM food_items
                    WHERE food_code LIKE 'FOOD%'
                    ORDER BY id DESC
                    LIMIT 1
                ");

                $lastCode = $stmt->fetchColumn();

                if ($lastCode) {

                    $number = (int)preg_replace(
                        '/[^0-9]/',
                        '',
                        (string)$lastCode
                    );

                    $number++;

                } else {

                    $number = 1;
                }

                $foodCode =
                    'FOOD' .
                    str_pad(
                        (string)$number,
                        3,
                        '0',
                        STR_PAD_LEFT
                    );
            }


            /*
            | Check duplicate food code.
            */

            $stmt = $con->prepare("
                SELECT id
                FROM food_items
                WHERE food_code = ?
                LIMIT 1
            ");

            $stmt->execute([$foodCode]);

            if ($stmt->fetch()) {

                throw new RuntimeException(
                    'Food code already exists.'
                );
            }


            /*
            | Check duplicate food name.
            */

            $stmt = $con->prepare("
                SELECT id
                FROM food_items
                WHERE LOWER(food_name) = LOWER(?)
                LIMIT 1
            ");

            $stmt->execute([$foodName]);

            if ($stmt->fetch()) {

                throw new RuntimeException(
                    'This food already exists.'
                );
            }


            /*
            | Insert food.
            */

            $stmt = $con->prepare("
                INSERT INTO food_items
                (
                    food_code,
                    food_name,
                    unit,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    'Enable'
                )
            ");

            $stmt->execute([
                $foodCode,
                $foodName,
                $unit
            ]);

            $success =
                'Food menu "' .
                $foodName .
                '" created successfully.';
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE RECIPE
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'save_recipe') {

            $foodId =
                (int)($_POST['food_id'] ?? 0);

            $materialIds =
                $_POST['material_id'] ?? [];

            $quantities =
                $_POST['quantity_per_plate'] ?? [];


            if ($foodId <= 0) {

                throw new RuntimeException(
                    'Please select a food.'
                );
            }


            if (
                !is_array($materialIds) ||
                !is_array($quantities)
            ) {

                throw new RuntimeException(
                    'Please add at least one material.'
                );
            }


            /*
            | Verify food exists.
            */

            $stmt = $con->prepare("
                SELECT id
                FROM food_items
                WHERE id = ?
                  AND status = 'Enable'
                LIMIT 1
            ");

            $stmt->execute([$foodId]);

            if (!$stmt->fetch()) {

                throw new RuntimeException(
                    'Selected food was not found.'
                );
            }


            $recipeItems = [];

            foreach ($materialIds as $index => $materialId) {

                $materialId =
                    (int)$materialId;

                $quantity =
                    (float)(
                        $quantities[$index]
                        ?? 0
                    );


                if (
                    $materialId <= 0 ||
                    $quantity <= 0
                ) {
                    continue;
                }


                /*
                | Verify material exists and is enabled.
                */

                $stmt = $con->prepare("
                    SELECT
                        id,
                        material_name,
                        unit
                    FROM materials
                    WHERE id = ?
                      AND status = 'Enable'
                    LIMIT 1
                ");

                $stmt->execute([$materialId]);

                $material = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$material) {
                    continue;
                }


                $recipeItems[] = [
                    'material_id' => $materialId,
                    'quantity' => $quantity
                ];
            }


            if (!$recipeItems) {

                throw new RuntimeException(
                    'Please add at least one material with a valid quantity.'
                );
            }


            /*
            | Prevent duplicate materials in the same recipe.
            */

            $usedMaterials = [];

            foreach ($recipeItems as $item) {

                if (
                    in_array(
                        $item['material_id'],
                        $usedMaterials,
                        true
                    )
                ) {

                    throw new RuntimeException(
                        'The same material cannot be added twice.'
                    );
                }

                $usedMaterials[] =
                    $item['material_id'];
            }


            /*
            |--------------------------------------------------------------------------
            | SAVE RECIPE
            |--------------------------------------------------------------------------
            */

            $con->beginTransaction();


            /*
            | Remove previous recipe.
            |
            | This allows the Chef to edit the recipe.
            */

            $delete = $con->prepare("
                DELETE FROM food_recipes
                WHERE food_id = ?
            ");

            $delete->execute([
                $foodId
            ]);


            /*
            | Insert new recipe.
            */

            $insert = $con->prepare("
                INSERT INTO food_recipes
                (
                    food_id,
                    material_id,
                    quantity_per_plate,
                    created_by
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");


            foreach ($recipeItems as $item) {

                $insert->execute([
                    $foodId,
                    $item['material_id'],
                    $item['quantity'],
                    $loginUserId > 0
                        ? $loginUserId
                        : null
                ]);
            }


            $con->commit();


            /*
            | Get food name for message.
            */

            $stmt = $con->prepare("
                SELECT food_name
                FROM food_items
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $foodId
            ]);

            $foodName =
                (string)$stmt->fetchColumn();


            $success =
                'Recipe for "' .
                $foodName .
                '" saved successfully.';
        }

    } catch (Throwable $e) {

        if ($con->inTransaction()) {
            $con->rollBack();
        }

        $error =
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| ENABLED FOOD ITEMS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT
        id,
        food_code,
        food_name,
        unit,
        status
    FROM food_items
    WHERE status = 'Enable'
    ORDER BY food_name ASC
");

$foods = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| ENABLED MATERIALS
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT
        id,
        material_code,
        material_name,
        unit,
        current_stock
    FROM materials
    WHERE status = 'Enable'
    ORDER BY material_name ASC
");

$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FOOD RECIPES
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT

        fi.id AS food_id,
        fi.food_code,
        fi.food_name,
        fi.unit,

        COUNT(fr.id) AS material_count

    FROM food_items fi

    LEFT JOIN food_recipes fr
        ON fr.food_id = fi.id

    WHERE fi.status = 'Enable'

    GROUP BY
        fi.id,
        fi.food_code,
        fi.food_name,
        fi.unit

    ORDER BY fi.food_name ASC
");

$recipeFoods =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/sidebar.php';

require_once __DIR__ . '/../includes/topbar.php';

?>

<div class="main-content">

    <div class="page-body">

        <!-- =====================================================
             PAGE HEADER
        ====================================================== -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h4 class="mb-1">
                    Food Menu & Recipe
                </h4>

                <p class="text-muted mb-0">
                    Create food menus and define the material
                    required for one plate.
                </p>

            </div>

        </div>


        <!-- =====================================================
             ALERTS
        ====================================================== -->

        <?php if ($success !== ''): ?>

            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check me-2"></i>

                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation me-2"></i>

                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <div class="row g-4">

            <!-- =================================================
                 CREATE FOOD
            ================================================== -->

            <div class="col-xl-4">

                <div class="card shadow-sm">

                    <div class="card-header">

                        <h5 class="mb-0">
                            <i class="fa-solid fa-utensils me-2"></i>

                            Create Food Menu
                        </h5>

                    </div>

                    <div class="card-body">

                        <form method="POST">

                            <input
                                type="hidden"
                                name="action"
                                value="save_food"
                            >


                            <div class="mb-3">

                                <label class="form-label">
                                    Food Code
                                </label>

                                <input
                                    type="text"
                                    name="food_code"
                                    class="form-control"
                                    placeholder="Auto generated"
                                >

                                <small class="text-muted">
                                    Leave empty to generate automatically.
                                </small>

                            </div>


                            <div class="mb-3">

                                <label class="form-label">
                                    Food Name
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="food_name"
                                    class="form-control"
                                    placeholder="Example: Idly"
                                    required
                                >

                            </div>


                            <div class="mb-3">

                                <label class="form-label">
                                    Serving Unit
                                </label>

                                <select
                                    name="unit"
                                    class="form-select"
                                >

                                    <option value="PLATE">
                                        PLATE
                                    </option>

                                    <option value="PORTION">
                                        PORTION
                                    </option>

                                </select>

                            </div>


                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                            >

                                <i class="fa-solid fa-plus me-1"></i>

                                Create Food

                            </button>

                        </form>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 RECIPE FORM
            ================================================== -->

            <div class="col-xl-8">

                <div class="card shadow-sm">

                    <div class="card-header">

                        <h5 class="mb-0">

                            <i class="fa-solid fa-list-check me-2"></i>

                            Assign Materials Per Plate

                        </h5>

                    </div>


                    <div class="card-body">

                        <form method="POST">

                            <input
                                type="hidden"
                                name="action"
                                value="save_recipe"
                            >


                            <div class="mb-3">

                                <label class="form-label">
                                    Food
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="food_id"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        Select Food
                                    </option>

                                    <?php foreach ($foods as $food): ?>

                                        <option
                                            value="<?= (int)$food['id'] ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $food['food_code']
                                            ) ?>

                                            -
                                            <?= htmlspecialchars(
                                                $food['food_name']
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="table-responsive">

                                <table
                                    class="table table-bordered align-middle"
                                    id="recipeTable"
                                >

                                    <thead>

                                        <tr>

                                            <th style="width:45%;">
                                                Material
                                            </th>

                                            <th style="width:25%;">
                                                Quantity / Plate
                                            </th>

                                            <th style="width:20%;">
                                                Unit
                                            </th>

                                            <th style="width:10%;">
                                                Action
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody id="recipeRows">

                                        <tr class="recipe-row">

                                            <td>

                                                <select
                                                    name="material_id[]"
                                                    class="form-select material-select"
                                                    required
                                                >

                                                    <option value="">
                                                        Select Material
                                                    </option>

                                                    <?php foreach ($materials as $material): ?>

                                                        <option
                                                            value="<?= (int)$material['id'] ?>"
                                                            data-unit="<?= htmlspecialchars(
                                                                $material['unit']
                                                            ) ?>"
                                                        >

                                                            <?= htmlspecialchars(
                                                                $material['material_code']
                                                            ) ?>

                                                            -
                                                            <?= htmlspecialchars(
                                                                $material['material_name']
                                                            ) ?>

                                                        </option>

                                                    <?php endforeach; ?>

                                                </select>

                                            </td>


                                            <td>

                                                <input
                                                    type="number"
                                                    name="quantity_per_plate[]"
                                                    class="form-control"
                                                    min="0.0001"
                                                    step="0.0001"
                                                    placeholder="0.0000"
                                                    required
                                                >

                                            </td>


                                            <td>

                                                <span class="material-unit text-muted">
                                                    -
                                                </span>

                                            </td>


                                            <td>

                                                <button
                                                    type="button"
                                                    class="btn btn-outline-danger btn-sm remove-row"
                                                >

                                                    <i class="fa-solid fa-trash"></i>

                                                </button>

                                            </td>

                                        </tr>

                                    </tbody>

                                </table>

                            </div>


                            <div class="d-flex justify-content-between mt-3">

                                <button
                                    type="button"
                                    id="addMaterial"
                                    class="btn btn-outline-primary"
                                >

                                    <i class="fa-solid fa-plus me-1"></i>

                                    Add Material

                                </button>


                                <button
                                    type="submit"
                                    class="btn btn-success"
                                >

                                    <i class="fa-solid fa-floppy-disk me-1"></i>

                                    Save Recipe

                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             EXISTING RECIPES
        ====================================================== -->

        <div class="card shadow-sm mt-4">

            <div class="card-header">

                <h5 class="mb-0">

                    <i class="fa-solid fa-book-open me-2"></i>

                    Food Recipes

                </h5>

            </div>


            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>

                                <th>#</th>

                                <th>Food Code</th>

                                <th>Food Name</th>

                                <th>Unit</th>

                                <th>Materials</th>

                                <th>Status</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php if (!$recipeFoods): ?>

                                <tr>

                                    <td
                                        colspan="6"
                                        class="text-center text-muted py-4"
                                    >

                                        No food menus found.

                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($recipeFoods as $index => $food): ?>

                                    <tr>

                                        <td>
                                            <?= $index + 1 ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $food['food_code']
                                            ) ?>
                                        </td>

                                        <td>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $food['food_name']
                                                ) ?>
                                            </strong>

                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $food['unit']
                                            ) ?>
                                        </td>

                                        <td>

                                            <span class="badge bg-primary">

                                                <?= (int)$food['material_count'] ?>

                                                Material(s)

                                            </span>

                                        </td>

                                        <td>

                                            <?php if ((int)$food['material_count'] > 0): ?>

                                                <span class="badge bg-success">
                                                    Recipe Ready
                                                </span>

                                            <?php else: ?>

                                                <span class="badge bg-warning text-dark">
                                                    Recipe Not Set
                                                </span>

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

</div>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const recipeRows =
        document.getElementById('recipeRows');

    const addMaterial =
        document.getElementById('addMaterial');


    /*
    |--------------------------------------------------------------------------
    | UPDATE MATERIAL UNIT
    |--------------------------------------------------------------------------
    */

    function updateUnit(select) {

        const row =
            select.closest('.recipe-row');

        const unit =
            row.querySelector('.material-unit');

        const option =
            select.options[
                select.selectedIndex
            ];

        if (
            option &&
            option.dataset.unit
        ) {

            unit.textContent =
                option.dataset.unit;

        } else {

            unit.textContent = '-';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | MATERIAL SELECT CHANGE
    |--------------------------------------------------------------------------
    */

    recipeRows.addEventListener(
        'change',
        function (event) {

            if (
                event.target.classList.contains(
                    'material-select'
                )
            ) {

                updateUnit(
                    event.target
                );
            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | ADD MATERIAL ROW
    |--------------------------------------------------------------------------
    */

    addMaterial.addEventListener(
        'click',
        function () {

            const firstRow =
                recipeRows.querySelector(
                    '.recipe-row'
                );

            const newRow =
                firstRow.cloneNode(true);


            newRow
                .querySelector(
                    '.material-select'
                )
                .value = '';


            newRow
                .querySelector(
                    'input[name="quantity_per_plate[]"]'
                )
                .value = '';


            newRow
                .querySelector(
                    '.material-unit'
                )
                .textContent = '-';


            recipeRows.appendChild(
                newRow
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | REMOVE MATERIAL ROW
    |--------------------------------------------------------------------------
    */

    recipeRows.addEventListener(
        'click',
        function (event) {

            const button =
                event.target.closest(
                    '.remove-row'
                );

            if (!button) {
                return;
            }


            const rows =
                recipeRows.querySelectorAll(
                    '.recipe-row'
                );


            /*
            | Always keep at least one row.
            */

            if (rows.length === 1) {

                rows[0]
                    .querySelector(
                        '.material-select'
                    )
                    .value = '';

                rows[0]
                    .querySelector(
                        'input[name="quantity_per_plate[]"]'
                    )
                    .value = '';

                rows[0]
                    .querySelector(
                        '.material-unit'
                    )
                    .textContent = '-';

                return;
            }


            button
                .closest('.recipe-row')
                .remove();

        }
    );

});

</script>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>