<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';


// =========================================================
// ACCESS CONTROL
// =========================================================

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


$pageTitle = 'Food Preparation';

$success = '';
$error = '';

$loginUserId = (int)($_SESSION['user_id'] ?? 0);


// =========================================================
// SEND FOOD TO CANTEEN
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['send_to_canteen'])
) {

    $preparationId =
        (int)($_POST['preparation_id'] ?? 0);

    $sendQty =
        (float)($_POST['send_qty'] ?? 0);

    $remarks =
        trim($_POST['transfer_remarks'] ?? '');


    if ($preparationId <= 0) {

        $error =
            'Invalid food preparation record.';

    } elseif ($sendQty <= 0) {

        $error =
            'Please enter a valid quantity.';

    } elseif ($loginUserId <= 0) {

        $error =
            'Invalid logged-in user.';

    } else {

        try {

            $con->beginTransaction();


            // =================================================
            // GET AND LOCK PREPARATION
            // =================================================

            $stmt = $con->prepare("
                SELECT
                    fp.id,
                    fp.preparation_no,
                    fp.food_id,
                    fp.prepared_qty,
                    fp.status,
                    fi.food_name,
                    fi.unit

                FROM food_preparations fp

                INNER JOIN food_items fi
                    ON fi.id = fp.food_id

                WHERE fp.id = ?

                FOR UPDATE
            ");

            $stmt->execute([
                $preparationId
            ]);

            $preparation =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$preparation) {

                throw new RuntimeException(
                    'Food preparation record not found.'
                );
            }


            // =================================================
            // CHECK STATUS
            // =================================================

            if (
                $preparation['status'] ===
                'Sent to Canteen'
            ) {

                throw new RuntimeException(
                    'All food from this preparation has already been sent to Canteen.'
                );
            }


            // =================================================
            // PREPARED QUANTITY
            // =================================================

            $preparedQty =
                (float)$preparation['prepared_qty'];


            // =================================================
            // GET TOTAL ALREADY SENT
            //
            // We calculate this from food_transfers.
            // No sent_qty column is required.
            // =================================================

            $stmt = $con->prepare("
                SELECT
                    COALESCE(SUM(quantity), 0)

                FROM food_transfers

                WHERE preparation_id = ?
            ");

            $stmt->execute([
                $preparationId
            ]);

            $alreadySent =
                (float)$stmt->fetchColumn();


            // =================================================
            // CALCULATE REMAINING
            // =================================================

            $remainingQty =
                $preparedQty - $alreadySent;


            if ($remainingQty <= 0) {

                throw new RuntimeException(
                    'All prepared food has already been sent to Canteen.'
                );
            }


            // =================================================
            // PREVENT OVER TRANSFER
            // =================================================

            if ($sendQty > $remainingQty) {

                throw new RuntimeException(
                    'Cannot send more than the remaining quantity. '
                    .
                    'Remaining quantity: '
                    .
                    number_format(
                        $remainingQty,
                        2
                    )
                    .
                    ' '
                    .
                    $preparation['unit']
                );
            }


            // =================================================
            // CREATE TRANSFER NUMBER
            // =================================================

            $transferNo =
                'FT-'
                .
                date('YmdHis')
                .
                '-'
                .
                random_int(100, 999);


            // =================================================
            // INSERT FOOD TRANSFER
            // =================================================

            $stmt = $con->prepare("
                INSERT INTO food_transfers
                (
                    transfer_no,
                    preparation_id,
                    food_id,
                    quantity,
                    transfer_date,
                    sent_by,
                    status,
                    remarks
                )

                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    CURDATE(),
                    ?,
                    'Sent',
                    ?
                )
            ");

            $stmt->execute([
                $transferNo,
                $preparationId,
                $preparation['food_id'],
                $sendQty,
                $loginUserId,
                $remarks !== ''
                    ? $remarks
                    : null
            ]);


            // =================================================
            // CALCULATE NEW TOTAL SENT
            // =================================================

            $newSentQty =
                $alreadySent + $sendQty;


            // =================================================
            // UPDATE PREPARATION STATUS
            // =================================================

            if (
                $newSentQty >=
                $preparedQty
            ) {

                $stmt = $con->prepare("
                    UPDATE food_preparations

                    SET status = 'Sent to Canteen'

                    WHERE id = ?
                ");

                $stmt->execute([
                    $preparationId
                ]);
            }


            $con->commit();


            $success =
                'Food sent to Canteen successfully. '
                .
                'Transfer No: '
                .
                $transferNo;

        } catch (Throwable $e) {

            if ($con->inTransaction()) {

                $con->rollBack();
            }

            $error =
                $e->getMessage();
        }
    }
}


// =========================================================
// CREATE FOOD PREPARATION
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['save_preparation'])
) {

    $foodId =
        (int)($_POST['food_id'] ?? 0);

    $preparedQty =
        (float)($_POST['prepared_qty'] ?? 0);

    $remarks =
        trim(
            $_POST['preparation_remarks'] ?? ''
        );


    if ($foodId <= 0) {

        $error =
            'Please select a food item.';

    } elseif ($preparedQty <= 0) {

        $error =
            'Please enter a valid prepared quantity.';

    } elseif ($loginUserId <= 0) {

        $error =
            'Invalid logged-in user.';

    } else {

        try {

            // =================================================
            // CHECK FOOD
            // =================================================

            $stmt = $con->prepare("
                SELECT
                    id,
                    food_code,
                    food_name,
                    unit

                FROM food_items

                WHERE id = ?

                AND status = 'Enable'

                LIMIT 1
            ");

            $stmt->execute([
                $foodId
            ]);

            $food =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$food) {

                throw new RuntimeException(
                    'Selected food item does not exist or is disabled.'
                );
            }


            // =================================================
            // GENERATE UNIQUE PREPARATION NUMBER
            // =================================================

            $preparationNo =
                'FP-'
                .
                date('YmdHis')
                .
                '-'
                .
                random_int(100, 999);


            // =================================================
            // INSERT PREPARATION
            // =================================================

            $stmt = $con->prepare("
                INSERT INTO food_preparations
                (
                    preparation_no,
                    food_id,
                    preparation_date,
                    prepared_qty,
                    status,
                    prepared_by,
                    remarks
                )

                VALUES
                (
                    ?,
                    ?,
                    CURDATE(),
                    ?,
                    'Prepared',
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $preparationNo,
                $foodId,
                $preparedQty,
                $loginUserId,
                $remarks !== ''
                    ? $remarks
                    : null
            ]);


            $success =
                'Food preparation recorded successfully. '
                .
                'Preparation No: '
                .
                $preparationNo;

        } catch (Throwable $e) {

            $error =
                $e->getMessage();
        }
    }
}


// =========================================================
// FOOD ITEMS
// =========================================================

$stmt = $con->query("
    SELECT
        id,
        food_code,
        food_name,
        unit

    FROM food_items

    WHERE status = 'Enable'

    ORDER BY
        food_name ASC
");

$foodItems =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// =========================================================
// PREPARED FOOD
//
// sent_qty is calculated from food_transfers.
// =========================================================

$stmt = $con->query("
    SELECT

        fp.id,
        fp.preparation_no,
        fp.preparation_date,
        fp.prepared_qty,
        fp.status,
        fp.remarks,

        fi.food_code,
        fi.food_name,
        fi.unit,

        u.employee_name AS prepared_by_name,

        COALESCE(
            (
                SELECT
                    SUM(ft.quantity)

                FROM food_transfers ft

                WHERE
                    ft.preparation_id = fp.id
            ),
            0
        ) AS sent_qty

    FROM food_preparations fp

    INNER JOIN food_items fi
        ON fi.id = fp.food_id

    LEFT JOIN users u
        ON u.id = fp.prepared_by

    WHERE
        fp.preparation_date >=
        DATE_SUB(CURDATE(), INTERVAL 7 DAY)

    ORDER BY
        fp.id DESC
");

$preparations =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// =========================================================
// FOOD SENT TO CANTEEN
// =========================================================

$stmt = $con->query("
    SELECT

        ft.transfer_no,
        ft.quantity,
        ft.transfer_date,
        ft.status,
        ft.remarks,

        fi.food_name,
        fi.unit

    FROM food_transfers ft

    INNER JOIN food_items fi
        ON fi.id = ft.food_id

    WHERE
        ft.transfer_date >=
        DATE_SUB(CURDATE(), INTERVAL 7 DAY)

    ORDER BY
        ft.id DESC

    LIMIT 20
");

$transfers =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/sidebar.php';

require_once __DIR__ . '/../includes/topbar.php';

?>


<div class="main-content">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div
        class="d-flex justify-content-between align-items-center mb-4"
    >

        <div>

            <h4 class="mb-1">
                Food Preparation
            </h4>

            <p class="text-muted mb-0">

                Record prepared food and send it
                from Kitchen to Canteen.

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

    <?php if ($success): ?>

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


    <?php if ($error): ?>

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
         PREPARATION FORM
    ====================================================== -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">

                <i
                    class="fa-solid fa-fire-burner me-2"
                ></i>

                Record Food Preparation

            </h5>

        </div>


        <div class="card-body">

            <form method="post">

                <div class="row g-3">


                    <!-- FOOD -->

                    <div class="col-md-5">

                        <label
                            class="form-label fw-semibold"
                        >

                            Food Item
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


                            <?php foreach (
                                $foodItems
                                as $food
                            ): ?>

                                <option
                                    value="<?= (int)$food['id'] ?>"
                                >

                                    <?= e(
                                        $food['food_name']
                                    ) ?>

                                    -

                                    <?= e(
                                        $food['food_code']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- QUANTITY -->

                    <div class="col-md-3">

                        <label
                            class="form-label fw-semibold"
                        >

                            Prepared Quantity
                            <span class="text-danger">*</span>

                        </label>


                        <input
                            type="number"
                            name="prepared_qty"
                            class="form-control"
                            min="0.01"
                            step="0.01"
                            required
                        >

                    </div>


                    <!-- REMARKS -->

                    <div class="col-md-4">

                        <label
                            class="form-label fw-semibold"
                        >

                            Remarks

                        </label>


                        <input
                            type="text"
                            name="preparation_remarks"
                            class="form-control"
                            maxlength="255"
                            placeholder="Optional remarks"
                        >

                    </div>


                    <!-- BUTTON -->

                    <div class="col-12">

                        <button
                            type="submit"
                            name="save_preparation"
                            class="btn btn-success"
                        >

                            <i
                                class="fa-solid fa-floppy-disk me-1"
                            ></i>

                            Save Preparation

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <!-- =====================================================
         PREPARED FOOD
    ====================================================== -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">
                Prepared Food
            </h5>

            <small class="text-muted">

                Food prepared during the last 7 days

            </small>

        </div>


        <div class="card-body p-0">

            <div class="table-responsive">

                <table
                    class="table table-hover align-middle mb-0"
                >

                    <thead class="table-light">

                        <tr>

                            <th>
                                Preparation No
                            </th>

                            <th>
                                Food
                            </th>

                            <th>
                                Prepared
                            </th>

                            <th>
                                Sent
                            </th>

                            <th>
                                Remaining
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        empty($preparations)
                    ): ?>

                        <tr>

                            <td
                                colspan="8"
                                class="text-center text-muted py-4"
                            >

                                No food preparation records found.

                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach (
                            $preparations
                            as $preparation
                        ): ?>


                            <?php

                            $prepared =
                                (float)
                                $preparation['prepared_qty'];

                            $sent =
                                (float)
                                $preparation['sent_qty'];

                            $remaining =
                                max(
                                    0,
                                    $prepared - $sent
                                );

                            ?>


                            <tr>


                                <!-- PREPARATION NO -->

                                <td>

                                    <strong>

                                        <?= e(
                                            $preparation[
                                                'preparation_no'
                                            ]
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- FOOD -->

                                <td>

                                    <strong>

                                        <?= e(
                                            $preparation[
                                                'food_name'
                                            ]
                                        ) ?>

                                    </strong>

                                    <br>

                                    <small
                                        class="text-muted"
                                    >

                                        <?= e(
                                            $preparation[
                                                'food_code'
                                            ]
                                        ) ?>

                                    </small>

                                </td>


                                <!-- PREPARED -->

                                <td>

                                    <?= number_format(
                                        $prepared,
                                        2
                                    ) ?>

                                    <?= e(
                                        $preparation[
                                            'unit'
                                        ]
                                    ) ?>

                                </td>


                                <!-- SENT -->

                                <td>

                                    <?= number_format(
                                        $sent,
                                        2
                                    ) ?>

                                    <?= e(
                                        $preparation[
                                            'unit'
                                        ]
                                    ) ?>

                                </td>


                                <!-- REMAINING -->

                                <td>

                                    <?php if (
                                        $remaining > 0
                                    ): ?>

                                        <span
                                            class="badge bg-warning text-dark"
                                        >

                                            <?= number_format(
                                                $remaining,
                                                2
                                            ) ?>

                                            <?= e(
                                                $preparation[
                                                    'unit'
                                                ]
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="badge bg-success"
                                        >

                                            0

                                            <?= e(
                                                $preparation[
                                                    'unit'
                                                ]
                                            ) ?>

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?= e(
                                        $preparation[
                                            'preparation_date'
                                        ]
                                    ) ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php

                                    $status =
                                        $preparation['status'];

                                    if (
                                        $status ===
                                        'Sent to Canteen'
                                    ):

                                    ?>

                                        <span
                                            class="badge bg-success"
                                        >

                                            Sent to Canteen

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="badge bg-warning text-dark"
                                        >

                                            Prepared

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ACTION -->

                                <td>

                                    <?php if (
                                        $remaining > 0
                                    ): ?>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#sendModal<?= (int)$preparation['id'] ?>"
                                        >

                                            <i
                                                class="fa-solid fa-truck me-1"
                                            ></i>

                                            Send to Canteen

                                        </button>

                                    <?php else: ?>

                                        <span
                                            class="text-success"
                                        >

                                            <i
                                                class="fa-solid fa-circle-check"
                                            ></i>

                                            Sent

                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>


                            <!-- =================================================
                                 SEND MODAL
                            ================================================== -->

                            <div
                                class="modal fade"
                                id="sendModal<?= (int)$preparation['id'] ?>"
                                tabindex="-1"
                                aria-hidden="true"
                            >

                                <div
                                    class="modal-dialog"
                                >

                                    <div
                                        class="modal-content"
                                    >


                                        <div
                                            class="modal-header"
                                        >

                                            <h5
                                                class="modal-title"
                                            >

                                                <i
                                                    class="fa-solid fa-truck me-2"
                                                ></i>

                                                Send Food to Canteen

                                            </h5>


                                            <button
                                                type="button"
                                                class="btn-close"
                                                data-bs-dismiss="modal"
                                            ></button>

                                        </div>


                                        <form method="post">

                                            <div
                                                class="modal-body"
                                            >


                                                <div
                                                    class="mb-3"
                                                >

                                                    <label
                                                        class="form-label fw-semibold"
                                                    >

                                                        Preparation No

                                                    </label>


                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        value="<?= e($preparation['preparation_no']) ?>"
                                                        readonly
                                                    >

                                                </div>


                                                <div
                                                    class="mb-3"
                                                >

                                                    <label
                                                        class="form-label fw-semibold"
                                                    >

                                                        Food

                                                    </label>


                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        value="<?= e($preparation['food_name']) ?>"
                                                        readonly
                                                    >

                                                </div>


                                                <div
                                                    class="row g-3 mb-3"
                                                >

                                                    <div
                                                        class="col-md-6"
                                                    >

                                                        <label
                                                            class="form-label"
                                                        >

                                                            Prepared

                                                        </label>


                                                        <input
                                                            type="text"
                                                            class="form-control"
                                                            value="<?= number_format($prepared, 2) . ' ' . $preparation['unit'] ?>"
                                                            readonly
                                                        >

                                                    </div>


                                                    <div
                                                        class="col-md-6"
                                                    >

                                                        <label
                                                            class="form-label"
                                                        >

                                                            Remaining

                                                        </label>


                                                        <input
                                                            type="text"
                                                            class="form-control"
                                                            value="<?= number_format($remaining, 2) . ' ' . $preparation['unit'] ?>"
                                                            readonly
                                                        >

                                                    </div>

                                                </div>


                                                <div
                                                    class="mb-3"
                                                >

                                                    <label
                                                        class="form-label fw-semibold"
                                                    >

                                                        Quantity to Send
                                                        <span class="text-danger">*</span>

                                                    </label>


                                                    <input
                                                        type="number"
                                                        name="send_qty"
                                                        class="form-control"
                                                        min="0.01"
                                                        max="<?= htmlspecialchars((string)$remaining) ?>"
                                                        step="0.01"
                                                        required
                                                    >


                                                    <small
                                                        class="text-muted"
                                                    >

                                                        Maximum:

                                                        <?= number_format(
                                                            $remaining,
                                                            2
                                                        ) ?>

                                                        <?= e(
                                                            $preparation[
                                                                'unit'
                                                            ]
                                                        ) ?>

                                                    </small>

                                                </div>


                                                <div
                                                    class="mb-3"
                                                >

                                                    <label
                                                        class="form-label"
                                                    >

                                                        Remarks

                                                    </label>


                                                    <textarea
                                                        name="transfer_remarks"
                                                        class="form-control"
                                                        rows="3"
                                                        maxlength="255"
                                                        placeholder="Optional remarks"
                                                    ></textarea>

                                                </div>


                                                <input
                                                    type="hidden"
                                                    name="preparation_id"
                                                    value="<?= (int)$preparation['id'] ?>"
                                                >

                                            </div>


                                            <div
                                                class="modal-footer"
                                            >

                                                <button
                                                    type="button"
                                                    class="btn btn-secondary"
                                                    data-bs-dismiss="modal"
                                                >

                                                    Cancel

                                                </button>


                                                <button
                                                    type="submit"
                                                    name="send_to_canteen"
                                                    class="btn btn-primary"
                                                >

                                                    <i
                                                        class="fa-solid fa-truck me-1"
                                                    ></i>

                                                    Send to Canteen

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


    <!-- =====================================================
         TRANSFER HISTORY
    ====================================================== -->

    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">

                <i
                    class="fa-solid fa-clock-rotate-left me-2"
                ></i>

                Food Transfer History

            </h5>

        </div>


        <div class="card-body p-0">

            <div class="table-responsive">

                <table
                    class="table table-hover align-middle mb-0"
                >

                    <thead class="table-light">

                        <tr>

                            <th>
                                Transfer No
                            </th>

                            <th>
                                Food
                            </th>

                            <th>
                                Quantity
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Remarks
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        empty($transfers)
                    ): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="text-center text-muted py-4"
                            >

                                No food transfers found.

                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach (
                            $transfers
                            as $transfer
                        ): ?>

                            <tr>

                                <td>

                                    <strong>

                                        <?= e(
                                            $transfer[
                                                'transfer_no'
                                            ]
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= e(
                                        $transfer[
                                            'food_name'
                                        ]
                                    ) ?>

                                </td>


                                <td>

                                    <?= number_format(
                                        (float)
                                        $transfer[
                                            'quantity'
                                        ],
                                        2
                                    ) ?>

                                    <?= e(
                                        $transfer[
                                            'unit'
                                        ]
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        $transfer[
                                            'transfer_date'
                                        ]
                                    ) ?>

                                </td>


                                <td>

                                    <span
                                        class="badge bg-success"
                                    >

                                        <?= e(
                                            $transfer[
                                                'status'
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= e(
                                        $transfer[
                                            'remarks'
                                        ] ?? ''
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


</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>