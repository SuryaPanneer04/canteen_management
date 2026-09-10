<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

$role = $_SESSION['role_name'] ?? '';

if (!in_array($role, ['Kitchen', 'Super Admin'], true)) {

    header('Location: ../index.php');
    exit;
}


$pageTitle = 'Daily Cooking Plan';

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
| CREATE DAILY COOKING PLAN TABLES
|--------------------------------------------------------------------------
*/

$con->exec("
    CREATE TABLE IF NOT EXISTS daily_cooking_plans (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,

        cooking_date DATE NOT NULL,

        meal_type ENUM(
            'Breakfast',
            'Lunch',
            'Snacks',
            'Dinner'
        ) NOT NULL,

        status ENUM(
            'Draft',
            'Pending Approval',
            'Approved',
            'Sent to Store',
            'Completed',
            'Cancelled'
        ) NOT NULL DEFAULT 'Draft',

        created_by INT UNSIGNED DEFAULT NULL,

        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

        updated_at DATETIME DEFAULT NULL
        ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),

        KEY idx_cooking_date (
            cooking_date
        ),

        KEY idx_meal_type (
            meal_type
        ),

        KEY idx_status (
            status
        )
    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_general_ci
");


$con->exec("
    CREATE TABLE IF NOT EXISTS daily_cooking_plan_items (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,

        cooking_plan_id INT UNSIGNED NOT NULL,

        food_id INT UNSIGNED NOT NULL,

        required_plates INT UNSIGNED NOT NULL DEFAULT 1,

        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),

        KEY idx_plan (
            cooking_plan_id
        ),

        KEY idx_food (
            food_id
        )
    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_general_ci
");


/*
|--------------------------------------------------------------------------
| SAVE COOKING PLAN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {

        /*
        |--------------------------------------------------------------------------
        | CREATE DAILY COOKING PLAN
        |--------------------------------------------------------------------------
        */

        if ($action === 'save_plan') {

            $cookingDate =
                trim($_POST['cooking_date'] ?? '');

            $mealType =
                trim($_POST['meal_type'] ?? '');

            $foodIds =
                $_POST['food_id'] ?? [];

            $requiredPlates =
                $_POST['required_plates'] ?? [];


            /*
            |--------------------------------------------------------------------------
            | VALIDATE DATE
            |--------------------------------------------------------------------------
            */

            if ($cookingDate === '') {

                throw new RuntimeException(
                    'Please select the cooking date.'
                );
            }


            $dateObject =
                DateTime::createFromFormat(
                    'Y-m-d',
                    $cookingDate
                );

            if (
                !$dateObject ||
                $dateObject->format('Y-m-d') !== $cookingDate
            ) {

                throw new RuntimeException(
                    'Invalid cooking date.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDATE MEAL TYPE
            |--------------------------------------------------------------------------
            */

            $allowedMeals = [
                'Breakfast',
                'Lunch',
                'Snacks',
                'Dinner'
            ];


            if (
                !in_array(
                    $mealType,
                    $allowedMeals,
                    true
                )
            ) {

                throw new RuntimeException(
                    'Please select a valid meal type.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDATE FOOD ITEMS
            |--------------------------------------------------------------------------
            */

            if (
                !is_array($foodIds) ||
                !is_array($requiredPlates)
            ) {

                throw new RuntimeException(
                    'Please add at least one food item.'
                );
            }


            $planItems = [];


            foreach ($foodIds as $index => $foodId) {

                $foodId =
                    (int)$foodId;

                $plates =
                    (int)(
                        $requiredPlates[$index]
                        ?? 0
                    );


                if (
                    $foodId <= 0 ||
                    $plates <= 0
                ) {
                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | CHECK FOOD AND RECIPE
                |--------------------------------------------------------------------------
                */

                $stmt = $con->prepare("
                    SELECT
                        fi.id,
                        fi.food_name,

                        COUNT(fr.id)
                        AS recipe_count

                    FROM food_items fi

                    LEFT JOIN food_recipes fr
                        ON fr.food_id = fi.id

                    WHERE fi.id = ?
                      AND fi.status = 'Enable'

                    GROUP BY
                        fi.id,
                        fi.food_name
                ");

                $stmt->execute([
                    $foodId
                ]);

                $food =
                    $stmt->fetch(PDO::FETCH_ASSOC);


                if (!$food) {

                    throw new RuntimeException(
                        'One of the selected foods is invalid.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | FOOD MUST HAVE RECIPE
                |--------------------------------------------------------------------------
                */

                if ((int)$food['recipe_count'] <= 0) {

                    throw new RuntimeException(
                        $food['food_name'] .
                        ' does not have a recipe. ' .
                        'Please assign materials per plate first.'
                    );
                }


                $planItems[] = [
                    'food_id' => $foodId,
                    'plates' => $plates
                ];
            }


            if (!$planItems) {

                throw new RuntimeException(
                    'Please select at least one food and enter the required plates.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | PREVENT DUPLICATE FOOD
            |--------------------------------------------------------------------------
            */

            $usedFoods = [];


            foreach ($planItems as $item) {

                if (
                    in_array(
                        $item['food_id'],
                        $usedFoods,
                        true
                    )
                ) {

                    throw new RuntimeException(
                        'The same food cannot be added twice.'
                    );
                }


                $usedFoods[] =
                    $item['food_id'];
            }


            /*
            |--------------------------------------------------------------------------
            | SAVE PLAN
            |--------------------------------------------------------------------------
            */

            $con->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | CREATE PLAN HEADER
            |--------------------------------------------------------------------------
            */

            $insertPlan =
                $con->prepare("
                    INSERT INTO daily_cooking_plans
                    (
                        cooking_date,
                        meal_type,
                        status,
                        created_by
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        'Pending Approval',
                        ?
                    )
                ");


            $insertPlan->execute([
                $cookingDate,
                $mealType,
                $loginUserId > 0
                    ? $loginUserId
                    : null
            ]);


            $planId =
                (int)$con->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | INSERT FOOD ITEMS
            |--------------------------------------------------------------------------
            */

            $insertItem =
                $con->prepare("
                    INSERT INTO daily_cooking_plan_items
                    (
                        cooking_plan_id,
                        food_id,
                        required_plates
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?
                    )
                ");


            foreach ($planItems as $item) {

                $insertItem->execute([
                    $planId,
                    $item['food_id'],
                    $item['plates']
                ]);
            }


            $con->commit();


            $success =
                'Daily cooking plan created successfully and sent for Chef approval.';
        }


        /*
        |--------------------------------------------------------------------------
        | CANCEL PLAN
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'cancel_plan') {

            $planId =
                (int)($_POST['plan_id'] ?? 0);


            if ($planId <= 0) {

                throw new RuntimeException(
                    'Invalid cooking plan.'
                );
            }


            $stmt =
                $con->prepare("
                    UPDATE daily_cooking_plans
                    SET status = 'Cancelled'
                    WHERE id = ?
                      AND status IN (
                          'Draft',
                          'Pending Approval'
                      )
                ");


            $stmt->execute([
                $planId
            ]);


            if ($stmt->rowCount() <= 0) {

                throw new RuntimeException(
                    'This cooking plan cannot be cancelled.'
                );
            }


            $success =
                'Cooking plan cancelled successfully.';
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
| GET FOOD ITEMS WITH RECIPE
|--------------------------------------------------------------------------
*/

$stmt = $con->query("
    SELECT

        fi.id,
        fi.food_code,
        fi.food_name,
        fi.unit,

        COUNT(fr.id)
        AS material_count

    FROM food_items fi

    INNER JOIN food_recipes fr
        ON fr.food_id = fi.id

    WHERE fi.status = 'Enable'

    GROUP BY
        fi.id,
        fi.food_code,
        fi.food_name,
        fi.unit

    HAVING COUNT(fr.id) > 0

    ORDER BY fi.food_name ASC
");


$foods =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| FILTER DATE
|--------------------------------------------------------------------------
*/

$filterDate =
    trim($_GET['date'] ?? '');


if ($filterDate === '') {

    $filterDate =
        date('Y-m-d');
}


/*
|--------------------------------------------------------------------------
| GET COOKING PLANS
|--------------------------------------------------------------------------
*/

$stmt =
    $con->prepare("
        SELECT

            dcp.id,
            dcp.cooking_date,
            dcp.meal_type,
            dcp.status,
            dcp.created_at,

            COUNT(dcpi.id)
            AS food_count,

            COALESCE(
                SUM(dcpi.required_plates),
                0
            )
            AS total_plates

        FROM daily_cooking_plans dcp

        LEFT JOIN daily_cooking_plan_items dcpi
            ON dcpi.cooking_plan_id = dcp.id

        WHERE dcp.cooking_date = ?

        GROUP BY
            dcp.id,
            dcp.cooking_date,
            dcp.meal_type,
            dcp.status,
            dcp.created_at

        ORDER BY
            FIELD(
                dcp.meal_type,
                'Breakfast',
                'Lunch',
                'Snacks',
                'Dinner'
            ),
            dcp.id DESC
    ");


$stmt->execute([
    $filterDate
]);


$plans =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| GET PLAN DETAILS
|--------------------------------------------------------------------------
*/

$planDetails = [];


if ($plans) {

    $planIds =
        array_column(
            $plans,
            'id'
        );


    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($planIds),
                '?'
            )
        );


    $stmt =
        $con->prepare("
            SELECT

                dcpi.id,
                dcpi.cooking_plan_id,
                dcpi.required_plates,

                fi.food_code,
                fi.food_name,
                fi.unit

            FROM daily_cooking_plan_items dcpi

            INNER JOIN food_items fi
                ON fi.id = dcpi.food_id

            WHERE dcpi.cooking_plan_id
                IN ($placeholders)

            ORDER BY
                fi.food_name ASC
        ");


    $stmt->execute(
        $planIds
    );


    $items =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    foreach ($items as $item) {

        $planId =
            (int)$item['cooking_plan_id'];


        if (
            !isset(
                $planDetails[$planId]
            )
        ) {

            $planDetails[$planId] = [];
        }


        $planDetails[$planId][] =
            $item;
    }
}


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

        <div
            class="d-flex justify-content-between align-items-center mb-4"
        >

            <div>

                <h4 class="mb-1">

                    Daily Cooking Plan

                </h4>


                <p class="text-muted mb-0">

                    Select the food and number of plates required
                    for Breakfast, Lunch, Snacks and Dinner.

                </p>

            </div>

        </div>


        <!-- =====================================================
             SUCCESS MESSAGE
        ====================================================== -->

        <?php if ($success !== ''): ?>

            <div class="alert alert-success">

                <i
                    class="fa-solid fa-circle-check me-2"
                ></i>

                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             ERROR MESSAGE
        ====================================================== -->

        <?php if ($error !== ''): ?>

            <div class="alert alert-danger">

                <i
                    class="fa-solid fa-circle-exclamation me-2"
                ></i>

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             CREATE COOKING PLAN
        ====================================================== -->

        <div class="card shadow-sm mb-4">

            <div class="card-header">

                <h5 class="mb-0">

                    <i
                        class="fa-solid fa-calendar-plus me-2"
                    ></i>

                    Create Daily Cooking Plan

                </h5>

            </div>


            <div class="card-body">

                <form
                    method="POST"
                    id="cookingPlanForm"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="save_plan"
                    >


                    <div class="row g-3 mb-4">


                        <!-- COOKING DATE -->

                        <div class="col-md-4">

                            <label class="form-label">

                                Cooking Date

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <input
                                type="date"
                                name="cooking_date"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $filterDate
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- MEAL TYPE -->

                        <div class="col-md-4">

                            <label class="form-label">

                                Meal

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <select
                                name="meal_type"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Meal
                                </option>

                                <option value="Breakfast">
                                    Breakfast
                                </option>

                                <option value="Lunch">
                                    Lunch
                                </option>

                                <option value="Snacks">
                                    Snacks
                                </option>

                                <option value="Dinner">
                                    Dinner
                                </option>

                            </select>

                        </div>


                        <!-- PLAN STATUS -->

                        <div class="col-md-4">

                            <label class="form-label">

                                Status

                            </label>


                            <input
                                type="text"
                                class="form-control"
                                value="Pending Approval"
                                readonly
                            >

                        </div>

                    </div>


                    <!-- =================================================
                         FOOD ITEMS TABLE
                    ================================================== -->

                    <div class="table-responsive">

                        <table
                            class="table table-bordered align-middle"
                        >

                            <thead>

                                <tr>

                                    <th
                                        style="width:60%;"
                                    >

                                        Food Item

                                    </th>


                                    <th
                                        style="width:25%;"
                                    >

                                        Required Plates

                                    </th>


                                    <th
                                        style="width:15%;"
                                    >

                                        Action

                                    </th>

                                </tr>

                            </thead>


                            <tbody
                                id="foodRows"
                            >

                                <tr
                                    class="food-row"
                                >

                                    <td>

                                        <select
                                            name="food_id[]"
                                            class="form-select food-select"
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

                                                    (

                                                    <?= htmlspecialchars(
                                                        $food['unit']
                                                    ) ?>

                                                    )

                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </td>


                                    <td>

                                        <input
                                            type="number"
                                            name="required_plates[]"
                                            class="form-control"
                                            min="1"
                                            step="1"
                                            placeholder="Example: 200"
                                            required
                                        >

                                    </td>


                                    <td>

                                        <button
                                            type="button"
                                            class="btn btn-outline-danger btn-sm remove-food"
                                        >

                                            <i
                                                class="fa-solid fa-trash"
                                            ></i>

                                        </button>

                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>


                    <!-- =================================================
                         FORM BUTTONS
                    ================================================== -->

                    <div
                        class="d-flex justify-content-between mt-3"
                    >

                        <button
                            type="button"
                            id="addFood"
                            class="btn btn-outline-primary"
                        >

                            <i
                                class="fa-solid fa-plus me-1"
                            ></i>

                            Add Food

                        </button>


                        <button
                            type="submit"
                            class="btn btn-success"
                        >

                            <i
                                class="fa-solid fa-paper-plane me-1"
                            ></i>

                            Send for Approval

                        </button>

                    </div>

                </form>

            </div>

        </div>


        <!-- =====================================================
             DATE FILTER
        ====================================================== -->

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <form
                    method="GET"
                    class="row g-3 align-items-end"
                >

                    <div class="col-md-4">

                        <label class="form-label">

                            View Cooking Plans

                        </label>


                        <input
                            type="date"
                            name="date"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $filterDate
                            ) ?>"
                        >

                    </div>


                    <div class="col-md-2">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >

                            <i
                                class="fa-solid fa-filter me-1"
                            ></i>

                            Filter

                        </button>

                    </div>

                </form>

            </div>

        </div>


        <!-- =====================================================
             DAILY COOKING PLANS
        ====================================================== -->

        <div class="card shadow-sm">

            <div class="card-header">

                <h5 class="mb-0">

                    <i
                        class="fa-solid fa-clipboard-list me-2"
                    ></i>

                    Cooking Plans for

                    <?= htmlspecialchars(
                        date(
                            'd-m-Y',
                            strtotime($filterDate)
                        )
                    ) ?>

                </h5>

            </div>


            <div class="card-body">


                <?php if (!$plans): ?>

                    <div
                        class="text-center text-muted py-5"
                    >

                        <i
                            class="fa-solid fa-utensils fa-2x mb-3"
                        ></i>


                        <p class="mb-0">

                            No cooking plans found for this date.

                        </p>

                    </div>

                <?php else: ?>


                    <div
                        class="table-responsive"
                    >

                        <table
                            class="table table-bordered align-middle"
                        >

                            <thead>

                                <tr>

                                    <th>#</th>

                                    <th>Meal</th>

                                    <th>Food Items</th>

                                    <th>Total Plates</th>

                                    <th>Status</th>

                                    <th>Created</th>

                                    <th>Action</th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach ($plans as $index => $plan): ?>


                                    <tr>

                                        <!-- NUMBER -->

                                        <td>

                                            <?= $index + 1 ?>

                                        </td>


                                        <!-- MEAL -->

                                        <td>

                                            <strong>

                                                <?php

                                                $mealIcons = [

                                                    'Breakfast' =>
                                                        'fa-mug-hot',

                                                    'Lunch' =>
                                                        'fa-bowl-food',

                                                    'Snacks' =>
                                                        'fa-cookie-bite',

                                                    'Dinner' =>
                                                        'fa-utensils'

                                                ];

                                                ?>

                                                <i
                                                    class="fa-solid <?= $mealIcons[
                                                        $plan['meal_type']
                                                    ] ?? 'fa-utensils' ?> me-1"
                                                ></i>

                                                <?= htmlspecialchars(
                                                    $plan['meal_type']
                                                ) ?>

                                            </strong>

                                        </td>


                                        <!-- FOOD ITEMS -->

                                        <td>


                                            <?php

                                            $planId =
                                                (int)$plan['id'];

                                            $items =
                                                $planDetails[
                                                    $planId
                                                ] ?? [];

                                            ?>


                                            <?php if ($items): ?>


                                                <ul
                                                    class="mb-0 ps-3"
                                                >

                                                    <?php foreach ($items as $item): ?>

                                                        <li>

                                                            <?= htmlspecialchars(
                                                                $item['food_name']
                                                            ) ?>

                                                            -

                                                            <strong>

                                                                <?= number_format(
                                                                    (int)$item['required_plates']
                                                                ) ?>

                                                            </strong>

                                                            Plates

                                                        </li>

                                                    <?php endforeach; ?>

                                                </ul>


                                            <?php else: ?>


                                                <span class="text-muted">

                                                    No food items

                                                </span>


                                            <?php endif; ?>

                                        </td>


                                        <!-- TOTAL PLATES -->

                                        <td>

                                            <strong>

                                                <?= number_format(
                                                    (int)$plan['total_plates']
                                                ) ?>

                                            </strong>

                                        </td>


                                        <!-- STATUS -->

                                        <td>


                                            <?php if (
                                                $plan['status'] ===
                                                'Pending Approval'
                                            ): ?>

                                                <span
                                                    class="badge bg-warning text-dark"
                                                >

                                                    Pending Approval

                                                </span>


                                            <?php elseif (
                                                $plan['status'] ===
                                                'Approved'
                                            ): ?>

                                                <span
                                                    class="badge bg-success"
                                                >

                                                    Approved

                                                </span>


                                            <?php elseif (
                                                $plan['status'] ===
                                                'Sent to Store'
                                            ): ?>

                                                <span
                                                    class="badge bg-primary"
                                                >

                                                    Sent to Store

                                                </span>


                                            <?php elseif (
                                                $plan['status'] ===
                                                'Completed'
                                            ): ?>

                                                <span
                                                    class="badge bg-success"
                                                >

                                                    Completed

                                                </span>


                                            <?php elseif (
                                                $plan['status'] ===
                                                'Cancelled'
                                            ): ?>

                                                <span
                                                    class="badge bg-danger"
                                                >

                                                    Cancelled

                                                </span>


                                            <?php else: ?>

                                                <span
                                                    class="badge bg-secondary"
                                                >

                                                    <?= htmlspecialchars(
                                                        $plan['status']
                                                    ) ?>

                                                </span>

                                            <?php endif; ?>


                                        </td>


                                        <!-- CREATED DATE -->

                                        <td>

                                            <?= htmlspecialchars(
                                                date(
                                                    'd-m-Y h:i A',
                                                    strtotime(
                                                        $plan['created_at']
                                                    )
                                                )
                                            ) ?>

                                        </td>


                                        <!-- ACTION -->

                                        <td>


                                            <?php if (
                                                in_array(
                                                    $plan['status'],
                                                    [
                                                        'Draft',
                                                        'Pending Approval'
                                                    ],
                                                    true
                                                )
                                            ): ?>


                                                <form
                                                    method="POST"
                                                    onsubmit="
                                                        return confirm(
                                                            'Are you sure you want to cancel this cooking plan?'
                                                        );
                                                    "
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="cancel_plan"
                                                    >


                                                    <input
                                                        type="hidden"
                                                        name="plan_id"
                                                        value="<?= (int)$plan['id'] ?>"
                                                    >


                                                    <button
                                                        type="submit"
                                                        class="btn btn-outline-danger btn-sm"
                                                    >

                                                        <i
                                                            class="fa-solid fa-xmark me-1"
                                                        ></i>

                                                        Cancel

                                                    </button>

                                                </form>


                                            <?php else: ?>

                                                -

                                            <?php endif; ?>


                                        </td>

                                    </tr>


                                <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                <?php endif; ?>


            </div>

        </div>


    </div>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        const foodRows =
            document.getElementById(
                'foodRows'
            );


        const addFood =
            document.getElementById(
                'addFood'
            );


        /*
        |--------------------------------------------------------------------------
        | ADD FOOD ROW
        |--------------------------------------------------------------------------
        */

        addFood.addEventListener(
            'click',
            function () {


                const firstRow =
                    foodRows.querySelector(
                        '.food-row'
                    );


                const newRow =
                    firstRow.cloneNode(
                        true
                    );


                /*
                | Reset food selection.
                */

                newRow
                    .querySelector(
                        '.food-select'
                    )
                    .value = '';


                /*
                | Reset plate quantity.
                */

                newRow
                    .querySelector(
                        'input[name="required_plates[]"]'
                    )
                    .value = '';


                foodRows.appendChild(
                    newRow
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | REMOVE FOOD ROW
        |--------------------------------------------------------------------------
        */

        foodRows.addEventListener(
            'click',
            function (event) {


                const button =
                    event.target.closest(
                        '.remove-food'
                    );


                if (!button) {

                    return;
                }


                const rows =
                    foodRows.querySelectorAll(
                        '.food-row'
                    );


                /*
                | Keep minimum one row.
                */

                if (rows.length === 1) {


                    rows[0]
                        .querySelector(
                            '.food-select'
                        )
                        .value = '';


                    rows[0]
                        .querySelector(
                            'input[name="required_plates[]"]'
                        )
                        .value = '';


                    return;
                }


                button
                    .closest(
                        '.food-row'
                    )
                    .remove();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | PREVENT DUPLICATE FOOD SELECTION
        |--------------------------------------------------------------------------
        */

        foodRows.addEventListener(
            'change',
            function (event) {


                if (
                    !event.target.classList.contains(
                        'food-select'
                    )
                ) {

                    return;
                }


                const selectedFoods =
                    [];


                document
                    .querySelectorAll(
                        '.food-select'
                    )
                    .forEach(
                        function (select) {


                            if (
                                select.value !== ''
                            ) {

                                selectedFoods.push(
                                    select.value
                                );

                            }

                        }
                    );


                const uniqueFoods =
                    [...new Set(
                        selectedFoods
                    )];


                if (
                    selectedFoods.length !==
                    uniqueFoods.length
                ) {

                    alert(
                        'The same food cannot be selected twice.'
                    );


                    event.target.value = '';

                }

            }
        );


    }
);

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>

