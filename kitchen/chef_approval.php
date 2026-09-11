<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
| Do not change the existing login system.
| Kitchen, Chef and Super Admin can access this page.
|--------------------------------------------------------------------------
*/

$allowedRoles = [
    'Kitchen',
    'Chef',
    'Super Admin'
];

if (
    !in_array(
        $_SESSION['role_name'] ?? '',
        $allowedRoles,
        true
    )
) {
    header('Location: ../index.php');
    exit;
}


$pageTitle = 'Chef Approval';

$success = '';
$error = '';


/*
|--------------------------------------------------------------------------
| LOGGED-IN USER
|--------------------------------------------------------------------------
*/

$loginUserId = (int)($_SESSION['user_id'] ?? 0);

if (
    $loginUserId <= 0
    &&
    !empty($_SESSION['user_data'])
    &&
    is_array($_SESSION['user_data'])
) {

    foreach (
        ['id', 'user_id', 'userid']
        as $key
    ) {

        if (
            isset(
                $_SESSION['user_data'][$key]
            )
        ) {

            $loginUserId =
                (int)$_SESSION['user_data'][$key];

            break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| CREATE NEW COOKING PLAN TABLES IF NOT EXISTS
|--------------------------------------------------------------------------
| These tables are used by the new Kitchen flow.
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

        KEY idx_dcp_date (
            cooking_date
        ),

        KEY idx_dcp_status (
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

        KEY idx_dcpi_plan (
            cooking_plan_id
        ),

        KEY idx_dcpi_food (
            food_id
        )

    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_general_ci
");


/*
|--------------------------------------------------------------------------
| CREATE RECIPE TABLE IF NOT EXISTS
|--------------------------------------------------------------------------
*/

$con->exec("
    CREATE TABLE IF NOT EXISTS food_recipes (

        id INT UNSIGNED NOT NULL AUTO_INCREMENT,

        food_id INT UNSIGNED NOT NULL,

        material_id INT UNSIGNED NOT NULL,

        quantity_per_plate DECIMAL(12,4) NOT NULL DEFAULT 0.0000,

        created_by INT UNSIGNED DEFAULT NULL,

        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

        updated_at DATETIME DEFAULT NULL
            ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),

        UNIQUE KEY uq_food_material (
            food_id,
            material_id
        ),

        KEY idx_fr_food (
            food_id
        ),

        KEY idx_fr_material (
            material_id
        )

    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_general_ci
");


/*
|--------------------------------------------------------------------------
| APPROVE COOKING PLAN
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| When Chef clicks "Send to Store":
|
| 1. Daily cooking plan is locked.
| 2. Food items are checked.
| 3. Recipe materials are loaded.
| 4. Material quantity is calculated.
| 5. Chef can modify the quantity.
| 6. kitchen_requests is created.
| 7. kitchen_request_items are created.
| 8. Request is sent to Store.
|
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['approve_plan'])
) {

    $planId =
        (int)($_POST['plan_id'] ?? 0);

    $chefRemarks =
        trim(
            $_POST['chef_remarks'] ?? ''
        );

    $materialIds =
        $_POST['material_id'] ?? [];

    $approvedQty =
        $_POST['approved_qty'] ?? [];

    $materialRemarks =
        $_POST['material_remarks'] ?? [];


    if ($planId <= 0) {

        $error =
            'Invalid cooking plan.';

    } else {

        try {

            $con->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | LOCK COOKING PLAN
            |--------------------------------------------------------------------------
            */

            $stmt =
                $con->prepare("
                    SELECT
                        id,
                        cooking_date,
                        meal_type,
                        status
                    FROM daily_cooking_plans
                    WHERE id = ?
                    FOR UPDATE
                ");

            $stmt->execute([
                $planId
            ]);

            $plan =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$plan) {

                throw new RuntimeException(
                    'Cooking plan not found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | ONLY PENDING APPROVAL
            |--------------------------------------------------------------------------
            */

            if (
                $plan['status'] !==
                'Pending Approval'
            ) {

                throw new RuntimeException(
                    'This cooking plan has already been processed.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | GET PLAN FOOD ITEMS
            |--------------------------------------------------------------------------
            */

            $stmt =
                $con->prepare("
                    SELECT

                        dcpi.id AS plan_item_id,

                        dcpi.food_id,

                        dcpi.required_plates,

                        fi.food_name,
                        fi.food_code

                    FROM daily_cooking_plan_items dcpi

                    INNER JOIN food_items fi
                        ON fi.id = dcpi.food_id

                    WHERE dcpi.cooking_plan_id = ?

                    ORDER BY
                        dcpi.id ASC

                    FOR UPDATE
                ");

            $stmt->execute([
                $planId
            ]);

            $planFoods =
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


            if (!$planFoods) {

                throw new RuntimeException(
                    'No food items found in this cooking plan.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | BUILD AUTOMATIC MATERIAL CALCULATION
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | Rice = 0.10 KG per plate
            | Plates = 200
            |
            | 0.10 × 200 = 20 KG
            |
            |--------------------------------------------------------------------------
            */

            $calculatedMaterials = [];


            foreach ($planFoods as $food) {

                $foodId =
                    (int)$food['food_id'];

                $plates =
                    (int)$food['required_plates'];


                /*
                |--------------------------------------------------------------------------
                | GET RECIPE
                |--------------------------------------------------------------------------
                */

                $stmt =
                    $con->prepare("
                        SELECT

                            fr.material_id,

                            fr.quantity_per_plate,

                            m.material_code,
                            m.material_name,
                            m.unit,
                            m.current_stock

                        FROM food_recipes fr

                        INNER JOIN materials m
                            ON m.id = fr.material_id

                        WHERE fr.food_id = ?

                          AND m.status = 'Enable'

                        ORDER BY
                            m.material_name ASC
                    ");

                $stmt->execute([
                    $foodId
                ]);

                $recipe =
                    $stmt->fetchAll(
                        PDO::FETCH_ASSOC
                    );


                if (!$recipe) {

                    throw new RuntimeException(
                        'Recipe not found for ' .
                        $food['food_name'] .
                        '. Please assign materials per plate first.'
                    );
                }


                foreach (
                    $recipe
                    as $recipeItem
                ) {

                    $materialId =
                        (int)$recipeItem['material_id'];

                    $perPlate =
                        (float)$recipeItem[
                            'quantity_per_plate'
                        ];

                    $totalQty =
                        $perPlate * $plates;


                    /*
                    |--------------------------------------------------------------------------
                    | COMBINE SAME MATERIAL
                    |--------------------------------------------------------------------------
                    |
                    | Example:
                    |
                    | Idly uses Rice 10 KG
                    | Vada uses Rice 5 KG
                    |
                    | Store should receive:
                    |
                    | Rice = 15 KG
                    |
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !isset(
                            $calculatedMaterials[
                                $materialId
                            ]
                        )
                    ) {

                        $calculatedMaterials[
                            $materialId
                        ] = [

                            'material_id' =>
                                $materialId,

                            'material_code' =>
                                $recipeItem[
                                    'material_code'
                                ],

                            'material_name' =>
                                $recipeItem[
                                    'material_name'
                                ],

                            'unit' =>
                                $recipeItem[
                                    'unit'
                                ],

                            'current_stock' =>
                                (float)$recipeItem[
                                    'current_stock'
                                ],

                            'calculated_qty' =>
                                0,

                            'food_details' =>
                                []

                        ];
                    }


                    $calculatedMaterials[
                        $materialId
                    ]['calculated_qty'] +=
                        $totalQty;


                    $calculatedMaterials[
                        $materialId
                    ]['food_details'][] = [

                        'food_name' =>
                            $food['food_name'],

                        'plates' =>
                            $plates,

                        'per_plate' =>
                            $perPlate,

                        'total' =>
                            $totalQty

                    ];
                }
            }


           /*
|--------------------------------------------------------------------------
| VALIDATE CHEF QUANTITIES
|--------------------------------------------------------------------------
|
| Chef can change only the materials automatically calculated
| from the recipes of this cooking plan.
|
*/

if (
    !is_array($materialIds)
    ||
    !is_array($approvedQty)
) {

    throw new RuntimeException(
        'No material quantities received.'
    );
}


/*
|--------------------------------------------------------------------------
| BUILD ALLOWED MATERIAL LIST
|--------------------------------------------------------------------------
|
| Only materials calculated from the selected food recipes
| can be submitted.
|
*/

$allowedMaterialIds = [];

foreach ($calculatedMaterials as $calculatedMaterial) {

    $allowedMaterialIds[
        (int)$calculatedMaterial['material_id']
    ] = true;
}


$approvedMaterials = [];


foreach (
    $materialIds
    as $index => $materialId
) {

    $materialId =
        (int)$materialId;

    $qty =
        (float)(
            $approvedQty[$index]
            ?? 0
        );


    /*
    |--------------------------------------------------------------------------
    | IGNORE INVALID MATERIAL ROW
    |--------------------------------------------------------------------------
    */

    if ($materialId <= 0) {

        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | MATERIAL MUST BELONG TO THIS PLAN
    |--------------------------------------------------------------------------
    */

    if (
        !isset(
            $allowedMaterialIds[$materialId]
        )
    ) {

        throw new RuntimeException(
            'Invalid material selected for this cooking plan.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | QUANTITY VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($qty < 0) {

        throw new RuntimeException(
            'Material quantity cannot be negative.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ZERO QUANTITY
    |--------------------------------------------------------------------------
    |
    | Chef may remove a material by setting quantity to 0.
    |
    */

    if ($qty <= 0) {

        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE MATERIAL
    |--------------------------------------------------------------------------
    */

    $stmt =
        $con->prepare("
            SELECT
                id,
                material_name,
                unit
            FROM materials
            WHERE id = ?
              AND status = 'Enable'
            LIMIT 1
        ");

    $stmt->execute([
        $materialId
    ]);


    $material =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$material) {

        throw new RuntimeException(
            'One of the selected materials is invalid.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ADD FINAL CHEF QUANTITY
    |--------------------------------------------------------------------------
    */

    $approvedMaterials[] = [

        'material_id' =>
            $materialId,

        'qty' =>
            $qty,

        'remarks' =>
            trim(
                $materialRemarks[
                    $index
                ] ?? ''
            )

    ];
}



            /*
            |--------------------------------------------------------------------------
            | AT LEAST ONE MATERIAL REQUIRED
            |--------------------------------------------------------------------------
            */

            if (!$approvedMaterials) {

                throw new RuntimeException(
                    'Please approve at least one material.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | GENERATE REQUEST NUMBER
            |--------------------------------------------------------------------------
            */

            $requestDate =
                $plan['cooking_date'];

            $datePart =
                date(
                    'Ymd',
                    strtotime($requestDate)
                );


            $stmt =
                $con->prepare("
                    SELECT
                        request_no

                    FROM kitchen_requests

                    WHERE request_no LIKE ?

                    ORDER BY id DESC

                    LIMIT 1
                ");


            $stmt->execute([
                'KR-' .
                $datePart .
                '-%'
            ]);


            $lastRequestNo =
                $stmt->fetchColumn();


            if ($lastRequestNo) {

                $lastNumber =
                    (int)substr(
                        (string)$lastRequestNo,
                        -4
                    );

                $nextNumber =
                    $lastNumber + 1;

            } else {

                $nextNumber = 1;
            }


            $requestNo =
                'KR-' .
                $datePart .
                '-' .
                str_pad(
                    (string)$nextNumber,
                    4,
                    '0',
                    STR_PAD_LEFT
                );


            /*
|--------------------------------------------------------------------------
| CREATE KITCHEN REQUEST
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Save the cooking plan ID in kitchen_requests.plan_id.
| This allows Food Preparation to identify which cooking
| plan is ready after Store completes the material issue.
|
*/

$stmt = $con->prepare("
    INSERT INTO kitchen_requests
    (
        request_no,
        plan_id,
        requested_by,
        request_date,
        status,
        cook_remarks,
        chef_remarks,
        approved_by,
        approved_at,
        sent_to_store_at
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        'Sent to Store',
        ?,
        ?,
        ?,
        NOW(),
        NOW()
    )
");

$stmt->execute([

    $requestNo,

    // IMPORTANT: link request to Daily Cooking Plan
    $planId,

    $loginUserId,

    $requestDate,

    'Daily Cooking Plan - ' .
    $plan['meal_type'],

    $chefRemarks !== ''
        ? $chefRemarks
        : null,

    $loginUserId

]);

$requestId = (int)$con->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | INSERT MATERIAL REQUEST ITEMS
            |--------------------------------------------------------------------------
            */

            $insertItem =
                $con->prepare("
                    INSERT INTO kitchen_request_items
                    (
                        request_id,
                        material_id,
                        requested_qty,
                        approved_qty,
                        issued_qty,
                        remarks
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        0,
                        ?
                    )
                ");


            foreach (
                $approvedMaterials
                as $item
            ) {

                $insertItem->execute([

                    $requestId,

                    $item['material_id'],

                    $item['qty'],

                    $item['qty'],

                    $item['remarks'] !== ''
                        ? $item['remarks']
                        : null

                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE DAILY COOKING PLAN
            |--------------------------------------------------------------------------
            */

            $stmt =
                $con->prepare("
                    UPDATE daily_cooking_plans

                    SET
                        status = 'Sent to Store',
                        updated_at = NOW()

                    WHERE id = ?
                ");


            $stmt->execute([
                $planId
            ]);


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $con->commit();


            $success =
                'Cooking plan for ' .
                $plan['meal_type'] .
                ' approved successfully. ' .
                'Material request ' .
                $requestNo .
                ' has been sent to Store.';


        } catch (Throwable $e) {

            if (
                $con->inTransaction()
            ) {

                $con->rollBack();
            }


            $error =
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| REJECT COOKING PLAN
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['reject_plan'])
) {

    $planId =
        (int)($_POST['plan_id'] ?? 0);

    $chefRemarks =
        trim(
            $_POST['chef_remarks'] ?? ''
        );


    if ($planId <= 0) {

        $error =
            'Invalid cooking plan.';

    } else {

        try {

            /*
            | The daily_cooking_plans status enum does not have Rejected.
            |
            | We use Cancelled so the plan cannot be sent to Store.
            */

            $stmt =
                $con->prepare("
                    UPDATE daily_cooking_plans

                    SET
                        status = 'Cancelled',
                        updated_at = NOW()

                    WHERE id = ?

                      AND status = 'Pending Approval'
                ");


            $stmt->execute([
                $planId
            ]);


            if (
                $stmt->rowCount() !== 1
            ) {

                throw new RuntimeException(
                    'This cooking plan has already been processed.'
                );
            }


            $success =
                'Cooking plan rejected by Chef.';

        } catch (Throwable $e) {

            $error =
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET PENDING COOKING PLANS
|--------------------------------------------------------------------------
*/

$stmt =
    $con->query("
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

        WHERE dcp.status = 'Pending Approval'

        GROUP BY

            dcp.id,
            dcp.cooking_date,
            dcp.meal_type,
            dcp.status,
            dcp.created_at

        ORDER BY

            dcp.cooking_date ASC,

            FIELD(
                dcp.meal_type,
                'Breakfast',
                'Lunch',
                'Snacks',
                'Dinner'
            ),

            dcp.id ASC
    ");


$plans =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| GET DETAILS FOR EACH PLAN
|--------------------------------------------------------------------------
*/

$planDetails = [];


foreach ($plans as $plan) {

    $planId =
        (int)$plan['id'];


    /*
    |--------------------------------------------------------------------------
    | GET FOOD ITEMS
    |--------------------------------------------------------------------------
    */

    $stmt =
        $con->prepare("
            SELECT

                dcpi.id,
                dcpi.food_id,
                dcpi.required_plates,

                fi.food_code,
                fi.food_name,
                fi.unit

            FROM daily_cooking_plan_items dcpi

            INNER JOIN food_items fi
                ON fi.id = dcpi.food_id

            WHERE dcpi.cooking_plan_id = ?

            ORDER BY
                fi.food_name ASC
        ");


    $stmt->execute([
        $planId
    ]);


    $foods =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | MATERIAL CALCULATION
    |--------------------------------------------------------------------------
    */

    $materialsForPlan = [];


    foreach ($foods as $food) {

        $foodId =
            (int)$food['food_id'];

        $plates =
            (int)$food['required_plates'];


        /*
        |--------------------------------------------------------------------------
        | GET RECIPE MATERIALS
        |--------------------------------------------------------------------------
        */

        $stmt =
            $con->prepare("
                SELECT

                    fr.material_id,

                    fr.quantity_per_plate,

                    m.material_code,
                    m.material_name,
                    m.unit,
                    m.current_stock

                FROM food_recipes fr

                INNER JOIN materials m
                    ON m.id = fr.material_id

                WHERE fr.food_id = ?

                  AND m.status = 'Enable'

                ORDER BY
                    m.material_name ASC
            ");


        $stmt->execute([
            $foodId
        ]);


        $recipe =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        foreach (
            $recipe
            as $recipeItem
        ) {

            $materialId =
                (int)$recipeItem['material_id'];


            $perPlate =
                (float)$recipeItem[
                    'quantity_per_plate'
                ];


            $calculatedQty =
                $perPlate * $plates;


            if (
                !isset(
                    $materialsForPlan[
                        $materialId
                    ]
                )
            ) {

                $materialsForPlan[
                    $materialId
                ] = [

                    'material_id' =>
                        $materialId,

                    'material_code' =>
                        $recipeItem[
                            'material_code'
                        ],

                    'material_name' =>
                        $recipeItem[
                            'material_name'
                        ],

                    'unit' =>
                        $recipeItem[
                            'unit'
                        ],

                    'current_stock' =>
                        (float)$recipeItem[
                            'current_stock'
                        ],

                    'calculated_qty' =>
                        0,

                    'food_details' =>
                        []

                ];
            }


            $materialsForPlan[
                $materialId
            ]['calculated_qty'] +=
                $calculatedQty;


            $materialsForPlan[
                $materialId
            ]['food_details'][] = [

                'food_name' =>
                    $food['food_name'],

                'plates' =>
                    $plates,

                'per_plate' =>
                    $perPlate,

                'total' =>
                    $calculatedQty

            ];
        }
    }


    $planDetails[
        $planId
    ] = [

        'foods' =>
            $foods,

        'materials' =>
            array_values(
                $materialsForPlan
            )

    ];
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

                    Chef Approval

                </h4>


                <p class="text-muted mb-0">

                    Review the daily cooking plan,
                    verify automatically calculated materials,
                    make changes if required and send to Store.

                </p>

            </div>


            <a
                href="dashboard.php"
                class="btn btn-outline-secondary"
            >

                <i
                    class="fa-solid fa-arrow-left me-1"
                ></i>

                Dashboard

            </a>

        </div>


        <!-- =====================================================
             ALERTS
        ====================================================== -->

        <?php if ($success !== ''): ?>

            <div
                class="alert alert-success alert-dismissible fade show"
            >

                <i
                    class="fa-solid fa-circle-check me-2"
                ></i>

                <?= e($success) ?>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
            >

                <i
                    class="fa-solid fa-circle-exclamation me-2"
                ></i>

                <?= e($error) ?>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             NO PENDING PLANS
        ====================================================== -->

        <?php if (!$plans): ?>

            <div
                class="card border-0 shadow-sm"
            >

                <div
                    class="card-body text-center py-5"
                >

                    <i
                        class="fa-solid fa-clipboard-check fa-3x text-muted mb-3"
                    ></i>


                    <h5>

                        No Cooking Plans Pending Approval

                    </h5>


                    <p class="text-muted mb-0">

                        New daily cooking plans will appear here
                        when they are submitted by the Kitchen.

                    </p>

                </div>

            </div>


        <?php else: ?>


            <!-- =================================================
                 PENDING PLANS
            ================================================== -->

            <?php foreach ($plans as $plan): ?>


                <?php

                $planId =
                    (int)$plan['id'];

                $details =
                    $planDetails[
                        $planId
                    ] ?? [];

                $foods =
                    $details['foods'] ?? [];

                $calculatedMaterials =
                    $details['materials'] ?? [];

                ?>


                <div
                    class="card border-0 shadow-sm mb-4"
                >


                    <!-- =========================================
                         PLAN HEADER
                    ========================================== -->

                    <div
                        class="card-header bg-white py-3"
                    >

                        <div
                            class="row align-items-center"
                        >


                            <div class="col-md-7">

                                <h5 class="mb-1">

                                    <i
                                        class="fa-solid fa-utensils me-2"
                                    ></i>

                                    <?= e(
                                        $plan['meal_type']
                                    ) ?>

                                </h5>


                                <div
                                    class="text-muted small"
                                >

                                    Cooking Date:

                                    <strong>

                                        <?= e(
                                            date(
                                                'd-m-Y',
                                                strtotime(
                                                    $plan['cooking_date']
                                                )
                                            )
                                        ) ?>

                                    </strong>


                                    &nbsp; | &nbsp;

                                    Total Plates:

                                    <strong>

                                        <?= number_format(
                                            (int)$plan['total_plates']
                                        ) ?>

                                    </strong>


                                    &nbsp; | &nbsp;

                                    Food Items:

                                    <strong>

                                        <?= (int)$plan['food_count'] ?>

                                    </strong>

                                </div>

                            </div>


                            <div
                                class="col-md-5 text-md-end mt-2 mt-md-0"
                            >

                                <span
                                    class="badge bg-warning text-dark px-3 py-2"
                                >

                                    <i
                                        class="fa-solid fa-clock me-1"
                                    ></i>

                                    Waiting for Chef

                                </span>

                            </div>

                        </div>

                    </div>


                    <div class="card-body">


                        <!-- =====================================
                             FOOD PLAN
                        ====================================== -->

                        <div class="mb-4">

                            <h6 class="mb-3">

                                <i
                                    class="fa-solid fa-bowl-food me-2"
                                ></i>

                                Food to Cook

                            </h6>


                            <div
                                class="table-responsive"
                            >

                                <table
                                    class="table table-bordered align-middle mb-0"
                                >

                                    <thead
                                        class="table-light"
                                    >

                                        <tr>

                                            <th>
                                                #
                                            </th>

                                            <th>
                                                Food Code
                                            </th>

                                            <th>
                                                Food
                                            </th>

                                            <th>
                                                Required Plates
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>


                                        <?php foreach (
                                            $foods
                                            as $foodIndex => $food
                                        ): ?>


                                            <tr>

                                                <td>

                                                    <?= $foodIndex + 1 ?>

                                                </td>


                                                <td>

                                                    <?= e(
                                                        $food['food_code']
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <strong>

                                                        <?= e(
                                                            $food['food_name']
                                                        ) ?>

                                                    </strong>

                                                </td>


                                                <td>

                                                    <span
                                                        class="badge bg-primary"
                                                    >

                                                        <?= number_format(
                                                            (int)$food['required_plates']
                                                        ) ?>

                                                        Plates

                                                    </span>

                                                </td>

                                            </tr>


                                        <?php endforeach; ?>


                                    </tbody>

                                </table>

                            </div>

                        </div>


                        <!-- =====================================
                             MATERIAL CALCULATION
                        ====================================== -->

                        <form
                            method="POST"
                            onsubmit="
                                return confirm(
                                    'Are you sure you want to approve this cooking plan and send the materials to Store?'
                                );
                            "
                        >

                            <input
                                type="hidden"
                                name="plan_id"
                                value="<?= $planId ?>"
                            >


                            <div class="mb-3">

                                <h6 class="mb-1">

                                    <i
                                        class="fa-solid fa-calculator me-2"
                                    ></i>

                                    Automatically Calculated Materials

                                </h6>


                                <small class="text-muted">

                                    Material quantity is calculated
                                    using the recipe quantity per plate
                                    multiplied by the required plates.

                                    Chef can change the final quantity
                                    before sending the request to Store.

                                </small>

                            </div>


                            <?php if (
                                !$calculatedMaterials
                            ): ?>


                                <div
                                    class="alert alert-danger"
                                >

                                    <i
                                        class="fa-solid fa-triangle-exclamation me-2"
                                    ></i>

                                    No recipe materials were found
                                    for the selected food.

                                    Please return to
                                    <strong>
                                        Food Menu & Recipe
                                    </strong>
                                    and assign materials per plate.

                                </div>


                            <?php else: ?>


                                <div
                                    class="table-responsive"
                                >

                                    <table
                                        class="table table-bordered align-middle"
                                    >

                                        <thead
                                            class="table-light"
                                        >

                                            <tr>

                                                <th
                                                    style="width:5%;"
                                                >
                                                    #
                                                </th>

                                                <th
                                                    style="width:20%;"
                                                >
                                                    Material
                                                </th>

                                                <th
                                                    style="width:10%;"
                                                >
                                                    Unit
                                                </th>

                                                <th
                                                    style="width:15%;"
                                                >
                                                    Available Stock
                                                </th>

                                                <th
                                                    style="width:15%;"
                                                >
                                                    Calculated Qty
                                                </th>

                                                <th
                                                    style="width:15%;"
                                                >
                                                    Chef Final Qty
                                                </th>

                                                <th
                                                    style="width:20%;"
                                                >
                                                    Remarks
                                                </th>

                                            </tr>

                                        </thead>


                                        <tbody>


                                            <?php foreach (
                                                $calculatedMaterials
                                                as $materialIndex => $material
                                            ): ?>


                                                <tr>


                                                    <!-- NUMBER -->

                                                    <td>

                                                        <?= $materialIndex + 1 ?>

                                                    </td>


                                                    <!-- MATERIAL -->

                                                    <td>

                                                        <strong>

                                                            <?= e(
                                                                $material['material_name']
                                                            ) ?>

                                                        </strong>


                                                        <br>


                                                        <small
                                                            class="text-muted"
                                                        >

                                                            <?= e(
                                                                $material['material_code']
                                                            ) ?>

                                                        </small>


                                                        <input
                                                            type="hidden"
                                                            name="material_id[]"
                                                            value="<?= (int)$material['material_id'] ?>"
                                                        >

                                                    </td>


                                                    <!-- UNIT -->

                                                    <td>

                                                        <?= e(
                                                            $material['unit']
                                                        ) ?>

                                                    </td>


                                                    <!-- STOCK -->

                                                    <td>

                                                        <?php

                                                        $stock =
                                                            (float)$material[
                                                                'current_stock'
                                                            ];

                                                        $calculated =
                                                            (float)$material[
                                                                'calculated_qty'
                                                            ];

                                                        ?>


                                                        <span
                                                            class="<?= $stock < $calculated
                                                                ? 'text-danger fw-bold'
                                                                : 'text-success fw-bold' ?>"
                                                        >

                                                            <?= number_format(
                                                                $stock,
                                                                4
                                                            ) ?>

                                                        </span>


                                                        <?php if (
                                                            $stock <
                                                            $calculated
                                                        ): ?>

                                                            <br>

                                                            <small
                                                                class="text-danger"
                                                            >

                                                                Insufficient stock

                                                            </small>

                                                        <?php endif; ?>

                                                    </td>


                                                    <!-- CALCULATED -->

                                                    <td>

                                                        <span
                                                            class="badge bg-info text-dark"
                                                        >

                                                            <?= number_format(
                                                                $calculated,
                                                                4
                                                            ) ?>

                                                            <?= e(
                                                                $material['unit']
                                                            ) ?>

                                                        </span>


                                                        <!--
                                                        Show calculation
                                                        details
                                                        -->

                                                        <div
                                                            class="mt-2"
                                                        >

                                                            <?php foreach (
                                                                $material['food_details']
                                                                as $detail
                                                            ): ?>

                                                                <small
                                                                    class="d-block text-muted"
                                                                >

                                                                    <?= e(
                                                                        $detail['food_name']
                                                                    ) ?>

                                                                    :

                                                                    <?= number_format(
                                                                        $detail['per_plate'],
                                                                        4
                                                                    ) ?>

                                                                    ×

                                                                    <?= number_format(
                                                                        $detail['plates']
                                                                    ) ?>

                                                                    =

                                                                    <?= number_format(
                                                                        $detail['total'],
                                                                        4
                                                                    ) ?>

                                                                </small>

                                                            <?php endforeach; ?>

                                                        </div>

                                                    </td>


                                                    <!-- CHEF FINAL -->

                                                    <td>

                                                        <input
                                                            type="number"
                                                            name="approved_qty[]"
                                                            class="form-control"
                                                            min="0"
                                                            step="0.0001"
                                                            value="<?= htmlspecialchars(
                                                                number_format(
                                                                    $calculated,
                                                                    4,
                                                                    '.',
                                                                    ''
                                                                )
                                                            ) ?>"
                                                            required
                                                        >

                                                        <small
                                                            class="text-muted"
                                                        >

                                                            Chef can change this quantity.

                                                        </small>

                                                    </td>


                                                    <!-- REMARKS -->

                                                    <td>

                                                        <input
                                                            type="text"
                                                            name="material_remarks[]"
                                                            class="form-control"
                                                            placeholder="Optional remarks"
                                                        >

                                                    </td>

                                                </tr>


                                            <?php endforeach; ?>


                                        </tbody>

                                    </table>

                                </div>


                                <!-- =================================
                                     CHEF REMARKS
                                ================================== -->

                                <div class="mb-4 mt-4">

                                    <label
                                        class="form-label fw-semibold"
                                    >

                                        Chef Remarks

                                    </label>


                                    <textarea
                                        name="chef_remarks"
                                        class="form-control"
                                        rows="3"
                                        placeholder="Enter any instruction or change made by Chef..."
                                    ></textarea>

                                </div>


                                <!-- =================================
                                     ACTIONS
                                ================================== -->

                                <div
                                    class="d-flex justify-content-between align-items-center"
                                >


                                    <!-- REJECT -->

                                    <button
                                        type="submit"
                                        name="reject_plan"
                                        value="1"
                                        class="btn btn-outline-danger"
                                        formnovalidate
                                        onclick="
                                            return confirm(
                                                'Are you sure you want to reject this cooking plan?'
                                            );
                                        "
                                    >

                                        <i
                                            class="fa-solid fa-xmark me-1"
                                        ></i>

                                        Reject Plan

                                    </button>


                                    <!-- APPROVE -->

                                    <button
                                        type="submit"
                                        name="approve_plan"
                                        value="1"
                                        class="btn btn-success px-4"
                                    >

                                        <i
                                            class="fa-solid fa-paper-plane me-1"
                                        ></i>

                                        Approve & Send to Store

                                    </button>


                                </div>


                            <?php endif; ?>


                        </form>

                    </div>

                </div>


            <?php endforeach; ?>


        <?php endif; ?>


    </div>

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>
