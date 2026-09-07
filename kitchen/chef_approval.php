<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';


// =========================================================
// ACCESS CONTROL
// =========================================================
//
// If Chef has a separate role, "Chef" is allowed.
// If Chef uses the Kitchen login, "Kitchen" is allowed.
//

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


// =========================================================
// APPROVE REQUEST
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['approve_request'])
) {

    $requestId =
        (int) ($_POST['request_id'] ?? 0);

    $chefRemarks =
        trim($_POST['chef_remarks'] ?? '');

    $itemIds =
        $_POST['item_id'] ?? [];

    $approvedQty =
        $_POST['approved_qty'] ?? [];

    $materialIds =
        $_POST['material_id'] ?? [];


    if ($requestId <= 0) {

        $error =
            'Invalid kitchen request.';

    } else {

        try {

            $con->beginTransaction();


            // =================================================
            // LOCK REQUEST
            // =================================================

            $stmt = $con->prepare("
                SELECT
                    id,
                    request_no,
                    requested_by,
                    status
                FROM kitchen_requests
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([
                $requestId
            ]);

            $request =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$request) {

                throw new RuntimeException(
                    'Kitchen request not found.'
                );
            }


            if (
                $request['status'] !== 'Submitted'
            ) {

                throw new RuntimeException(
                    'This request has already been processed.'
                );
            }


            // =================================================
            // VALIDATE REQUEST ITEMS
            // =================================================

            if (
                !is_array($itemIds)
                ||
                !is_array($approvedQty)
                ||
                !is_array($materialIds)
            ) {

                throw new RuntimeException(
                    'No material details received.'
                );
            }


            /*
             * Get the original Cook request items.
             */

            $stmt = $con->prepare("
                SELECT
                    id,
                    material_id,
                    requested_qty
                FROM kitchen_request_items
                WHERE request_id = ?
                FOR UPDATE
            ");

            $stmt->execute([
                $requestId
            ]);

            $originalItems =
                $stmt->fetchAll(PDO::FETCH_ASSOC);


            $originalById = [];

            foreach (
                $originalItems
                as $original
            ) {

                $originalById[
                    (int) $original['id']
                ] = $original;
            }


            // =================================================
            // UPDATE ORIGINAL ITEMS
            // =================================================

            $updateItem =
                $con->prepare("
                    UPDATE kitchen_request_items

                    SET
                        approved_qty = ?,
                        remarks = ?

                    WHERE id = ?
                      AND request_id = ?
                ");


            foreach (
                $itemIds
                as $index => $itemId
            ) {

                $itemId =
                    (int) $itemId;

                $qty =
                    (float) (
                        $approvedQty[$index]
                        ?? 0
                    );


                if (
                    !isset(
                        $originalById[$itemId]
                    )
                ) {

                    continue;
                }


                $original =
                    $originalById[$itemId];


                $requestedQty =
                    (float)
                    $original['requested_qty'];


                // ---------------------------------------------
                // APPROVED QTY CANNOT BE NEGATIVE
                // ---------------------------------------------

                if ($qty < 0) {

                    throw new RuntimeException(
                        'Approved quantity cannot be negative.'
                    );
                }


                // ---------------------------------------------
                // CHEF CAN ADD MORE, BUT WE WARN IF MORE
                // THAN REQUESTED.
                //
                // We allow it because your requirement says:
                // "Chef can add some materials if he wants."
                // ---------------------------------------------


                $itemRemark =
                    trim(
                        $_POST['item_remarks'][$index]
                        ?? ''
                    );


                $updateItem->execute([
                    $qty,
                    $itemRemark ?: null,
                    $itemId,
                    $requestId
                ]);
            }


            // =================================================
            // ADD NEW MATERIALS FROM CHEF
            // =================================================

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
                        0,
                        ?,
                        0,
                        ?
                    )
                ");


            foreach (
                $materialIds
                as $index => $materialId
            ) {

                $materialId =
                    (int) $materialId;

                $qty =
                    (float) (
                        $approvedQty[$index]
                        ?? 0
                    );


                /*
                 * Existing rows already contain item_id.
                 *
                 * New rows have item_id = 0.
                 */

                $currentItemId =
                    (int) (
                        $itemIds[$index]
                        ?? 0
                    );


                if (
                    $currentItemId > 0
                ) {

                    continue;
                }


                if (
                    $materialId <= 0
                    ||
                    $qty <= 0
                ) {

                    continue;
                }


                // ---------------------------------------------
                // CHECK MATERIAL EXISTS
                // ---------------------------------------------

                $stmt = $con->prepare("
                    SELECT
                        id
                    FROM materials
                    WHERE id = ?
                      AND status = 'Enable'
                ");

                $stmt->execute([
                    $materialId
                ]);

                if (!$stmt->fetchColumn()) {

                    throw new RuntimeException(
                        'Selected material is invalid.'
                    );
                }


                $itemRemark =
                    trim(
                        $_POST['item_remarks'][$index]
                        ?? ''
                    );


                $insertItem->execute([
                    $requestId,
                    $materialId,
                    $qty,
                    $itemRemark ?: null
                ]);
            }


            // =================================================
            // CHECK AT LEAST ONE APPROVED MATERIAL
            // =================================================

            $stmt = $con->prepare("
                SELECT COUNT(*)
                FROM kitchen_request_items
                WHERE request_id = ?
                  AND approved_qty > 0
            ");

            $stmt->execute([
                $requestId
            ]);

            $approvedItemCount =
                (int) $stmt->fetchColumn();


            if ($approvedItemCount <= 0) {

                throw new RuntimeException(
                    'Please approve at least one material.'
                );
            }


            // =================================================
            // UPDATE REQUEST
            // =================================================

            $stmt = $con->prepare("
                UPDATE kitchen_requests

                SET
                    status = 'Chef Approved',
                    chef_remarks = ?,
                    approved_by = ?,
                    approved_at = NOW(),
                    sent_to_store_at = NOW()

                WHERE id = ?
            ");

            $stmt->execute([
                $chefRemarks ?: null,
                $_SESSION['user_id'],
                $requestId
            ]);


            $con->commit();


            $success =
                'Request '
                . $request['request_no']
                . ' approved and sent to Store.';

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


// =========================================================
// REJECT REQUEST
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['reject_request'])
) {

    $requestId =
        (int) ($_POST['request_id'] ?? 0);

    $chefRemarks =
        trim($_POST['chef_remarks'] ?? '');


    if ($requestId <= 0) {

        $error =
            'Invalid kitchen request.';

    } else {

        try {

            $stmt = $con->prepare("
                UPDATE kitchen_requests

                SET
                    status = 'Rejected',
                    chef_remarks = ?,
                    approved_by = ?,
                    approved_at = NOW()

                WHERE id = ?
                  AND status = 'Submitted'
            ");

            $stmt->execute([
                $chefRemarks ?: 'Rejected by Chef',
                $_SESSION['user_id'],
                $requestId
            ]);


            if (
                $stmt->rowCount() !== 1
            ) {

                throw new RuntimeException(
                    'Request was already processed.'
                );
            }


            $success =
                'Kitchen request rejected.';

        } catch (Throwable $e) {

            $error =
                $e->getMessage();
        }
    }
}


// =========================================================
// GET PENDING REQUESTS
// =========================================================

$stmt = $con->query("
    SELECT

        kr.id,
        kr.request_no,
        kr.request_date,
        kr.status,
        kr.cook_remarks,

        u.employee_name,
        u.employee_code

    FROM kitchen_requests kr

    INNER JOIN users u
        ON u.id = kr.requested_by

    WHERE kr.status = 'Submitted'

    ORDER BY
        kr.id DESC
");

$requests =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// =========================================================
// GET MATERIAL MASTER
// =========================================================

$stmt = $con->query("
    SELECT

        id,
        material_code,
        material_name,
        unit,
        current_stock

    FROM materials

    WHERE status = 'Enable'

    ORDER BY
        material_name ASC
");

$materials =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/sidebar.php';

require_once __DIR__ . '/../includes/topbar.php';

?>


<div class="main-content">


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

                Review Cook requests, modify materials
                and send the final request to Store.

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
         REQUEST LIST
    ====================================================== -->

    <?php foreach (
        $requests
        as $request
    ): ?>


        <?php

        $stmt = $con->prepare("
            SELECT

                kri.id AS item_id,

                kri.material_id,

                kri.requested_qty,

                kri.approved_qty,

                kri.issued_qty,

                kri.remarks,

                m.material_code,

                m.material_name,

                m.unit,

                m.current_stock

            FROM kitchen_request_items kri

            INNER JOIN materials m
                ON m.id = kri.material_id

            WHERE kri.request_id = ?

            ORDER BY
                kri.id ASC
        ");

        $stmt->execute([
            $request['id']
        ]);

        $requestItems =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

        ?>


        <div
            class="card border-0 shadow-sm mb-4"
        >

            <!-- =================================================
                 REQUEST HEADER
            ================================================== -->

            <div
                class="card-header bg-white py-3"
            >

                <div
                    class="d-flex justify-content-between align-items-center"
                >

                    <div>

                        <h5 class="mb-1">

                            <?= e(
                                $request['request_no']
                            ) ?>

                        </h5>


                        <div
                            class="text-muted small"
                        >

                            Cook:

                            <strong>

                                <?= e(
                                    $request['employee_name']
                                ) ?>

                            </strong>


                            <?php if (
                                !empty(
                                    $request['employee_code']
                                )
                            ): ?>

                                |

                                Employee Code:

                                <?= e(
                                    $request['employee_code']
                                ) ?>

                            <?php endif; ?>


                            |

                            Date:

                            <?= e(
                                $request['request_date']
                            ) ?>

                        </div>

                    </div>


                    <span
                        class="badge bg-warning text-dark"
                    >

                        Waiting for Chef

                    </span>

                </div>

            </div>



            <!-- =================================================
                 REQUEST BODY
            ================================================== -->

            <div class="card-body">


                <!-- COOK REMARKS -->

                <?php if (
                    !empty(
                        $request['cook_remarks']
                    )
                ): ?>

                    <div
                        class="alert alert-info"
                    >

                        <strong>
                            Cook Remarks:
                        </strong>

                        <br>

                        <?= nl2br(
                            e(
                                $request['cook_remarks']
                            )
                        ) ?>

                    </div>

                <?php endif; ?>



                <!-- =================================================
                     APPROVAL FORM
                ================================================== -->

                <form
                    method="post"
                >

                    <input
                        type="hidden"
                        name="request_id"
                        value="<?= (int) $request['id'] ?>"
                    >


                    <div
                        class="table-responsive"
                    >

                        <table
                            class="table table-bordered align-middle"
                            id="items_<?= (int) $request['id'] ?>"
                        >

                            <thead
                                class="table-light"
                            >

                                <tr>

                                    <th
                                        style="width:5%"
                                    >
                                        #
                                    </th>

                                    <th
                                        style="width:25%"
                                    >
                                        Material
                                    </th>

                                    <th
                                        style="width:12%"
                                    >
                                        Current Stock
                                    </th>

                                    <th
                                        style="width:12%"
                                    >
                                        Cook Requested
                                    </th>

                                    <th
                                        style="width:15%"
                                    >
                                        Chef Approved
                                    </th>

                                    <th
                                        style="width:20%"
                                    >
                                        Remarks
                                    </th>

                                    <th
                                        style="width:8%"
                                    >
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach (
                                $requestItems
                                as $index => $item
                            ): ?>


                                <tr>

                                    <!-- NUMBER -->

                                    <td>

                                        <?= $index + 1 ?>

                                    </td>


                                    <!-- MATERIAL -->

                                    <td>

                                        <input
                                            type="hidden"
                                            name="item_id[]"
                                            value="<?= (int) $item['item_id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="material_id[]"
                                            value="<?= (int) $item['material_id'] ?>"
                                        >


                                        <strong>

                                            <?= e(
                                                $item['material_name']
                                            ) ?>

                                        </strong>


                                        <br>


                                        <small
                                            class="text-muted"
                                        >

                                            <?= e(
                                                $item['material_code']
                                            ) ?>

                                            -

                                            <?= e(
                                                $item['unit']
                                            ) ?>

                                        </small>

                                    </td>


                                    <!-- STOCK -->

                                    <td>

                                        <span
                                            class="badge bg-secondary"
                                        >

                                            <?= number_format(
                                                (float)
                                                $item['current_stock'],
                                                2
                                            ) ?>

                                            <?= e(
                                                $item['unit']
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- REQUESTED -->

                                    <td>

                                        <strong>

                                            <?= number_format(
                                                (float)
                                                $item['requested_qty'],
                                                2
                                            ) ?>

                                        </strong>

                                        <?= e(
                                            $item['unit']
                                        ) ?>

                                    </td>


                                    <!-- APPROVED -->

                                    <td>

                                        <input
                                            type="number"
                                            name="approved_qty[]"
                                            class="form-control"
                                            value="<?= htmlspecialchars(
                                                number_format(
                                                    (float)
                                                    $item['requested_qty'],
                                                    2,
                                                    '.',
                                                    ''
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            min="0"
                                            step="0.01"
                                        >

                                    </td>


                                    <!-- REMARKS -->

                                    <td>

                                        <input
                                            type="text"
                                            name="item_remarks[]"
                                            class="form-control"
                                            value="<?= e(
                                                $item['remarks']
                                                ?? ''
                                            ) ?>"
                                            placeholder="Remarks"
                                        >

                                    </td>


                                    <!-- REMOVE -->

                                    <td>

                                        <button
                                            type="button"
                                            class="btn btn-outline-danger btn-sm"
                                            onclick="removeApprovalRow(this)"
                                        >

                                            <i
                                                class="fa-solid fa-trash"
                                            ></i>

                                        </button>

                                    </td>

                                </tr>


                            <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>



                    <!-- =================================================
                         ADD MATERIAL
                    ================================================== -->

                    <button
                        type="button"
                        class="btn btn-outline-primary mb-4"
                        onclick="addChefMaterial(<?= (int) $request['id'] ?>)"
                    >

                        <i
                            class="fa-solid fa-plus me-1"
                        ></i>

                        Add Material

                    </button>



                    <!-- =================================================
                         CHEF REMARKS
                    ================================================== -->

                    <div class="mb-3">

                        <label
                            class="form-label fw-semibold"
                        >

                            Chef Remarks

                        </label>


                        <textarea
                            name="chef_remarks"
                            class="form-control"
                            rows="3"
                            placeholder="Enter Chef remarks..."
                        ></textarea>

                    </div>



                    <!-- =================================================
                         ACTION BUTTONS
                    ================================================== -->

                    <div
                        class="d-flex gap-2"
                    >

                        <button
                            type="submit"
                            name="approve_request"
                            class="btn btn-success"
                            onclick="return confirm('Approve this request and send it to Store?');"
                        >

                            <i
                                class="fa-solid fa-check me-1"
                            ></i>

                            Approve & Send to Store

                        </button>


                        <button
                            type="submit"
                            name="reject_request"
                            class="btn btn-danger"
                            onclick="return confirm('Reject this Kitchen request?');"
                        >

                            <i
                                class="fa-solid fa-xmark me-1"
                            ></i>

                            Reject

                        </button>

                    </div>

                </form>

            </div>

        </div>


    <?php endforeach; ?>



    <!-- =====================================================
         NO REQUESTS
    ====================================================== -->

    <?php if (!$requests): ?>

        <div
            class="card border-0 shadow-sm"
        >

            <div
                class="card-body text-center py-5"
            >

                <div
                    class="fs-1 text-muted mb-3"
                >

                    <i
                        class="fa-solid fa-circle-check"
                    ></i>

                </div>


                <h5>
                    No Pending Requests
                </h5>


                <p class="text-muted mb-0">

                    There are no Cook requests
                    waiting for Chef approval.

                </p>

            </div>

        </div>

    <?php endif; ?>


</div>



<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

const materialOptions = <?=
    json_encode(
        $materials,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    )
?>;


/*
|--------------------------------------------------------------------------
| ADD MATERIAL
|--------------------------------------------------------------------------
*/

function addChefMaterial(requestId)
{

    const table =
        document.getElementById(
            'items_' + requestId
        );


    const tbody =
        table.querySelector('tbody');


    const row =
        document.createElement('tr');


    let options =
        '<option value="">Select Material</option>';


    materialOptions.forEach(function(material)
    {

        options +=
            '<option value="' +
            material.id +
            '">' +

            escapeHtml(
                material.material_name
            ) +

            ' (' +

            escapeHtml(
                material.material_code
            ) +

            ')</option>';

    });


    row.innerHTML = `

        <td>
            +
        </td>

        <td>

            <select
                name="material_id[]"
                class="form-select material-select"
                onchange="updateMaterialStock(this)"
                required
            >

                ${options}

            </select>

            <small
                class="text-muted stock-display"
            >
                Select material
            </small>

            <input
                type="hidden"
                name="item_id[]"
                value="0"
            >

        </td>

        <td>

            <span
                class="badge bg-secondary new-stock"
            >
                -
            </span>

        </td>

        <td>

            <span
                class="text-muted"
            >
                Added by Chef
            </span>

        </td>

        <td>

            <input
                type="number"
                name="approved_qty[]"
                class="form-control"
                min="0.01"
                step="0.01"
                value="0"
                required
            >

        </td>

        <td>

            <input
                type="text"
                name="item_remarks[]"
                class="form-control"
                placeholder="Why is this material required?"
            >

        </td>

        <td>

            <button
                type="button"
                class="btn btn-outline-danger btn-sm"
                onclick="removeApprovalRow(this)"
            >

                <i
                    class="fa-solid fa-trash"
                ></i>

            </button>

        </td>

    `;


    tbody.appendChild(row);

}


/*
|--------------------------------------------------------------------------
| REMOVE ROW
|--------------------------------------------------------------------------
*/

function removeApprovalRow(button)
{

    const row =
        button.closest('tr');


    if (row)
    {
        row.remove();
    }

}


/*
|--------------------------------------------------------------------------
| UPDATE STOCK DISPLAY
|--------------------------------------------------------------------------
*/

function updateMaterialStock(select)
{

    const materialId =
        parseInt(
            select.value
        );


    const row =
        select.closest('tr');


    const stockBadge =
        row.querySelector(
            '.new-stock'
        );


    const stockDisplay =
        row.querySelector(
            '.stock-display'
        );


    if (!materialId)
    {

        stockBadge.textContent = '-';

        stockDisplay.textContent =
            'Select material';

        return;

    }


    const material =
        materialOptions.find(
            function(item)
            {
                return parseInt(item.id)
                    === materialId;
            }
        );


    if (!material)
    {

        stockBadge.textContent = '-';

        stockDisplay.textContent =
            'Material not found';

        return;

    }


    stockBadge.textContent =
        parseFloat(
            material.current_stock
        ).toFixed(2)
        + ' '
        + material.unit;


    stockDisplay.textContent =
        'Available stock: '
        + parseFloat(
            material.current_stock
        ).toFixed(2)
        + ' '
        + material.unit;

}


/*
|--------------------------------------------------------------------------
| HTML ESCAPE
|--------------------------------------------------------------------------
*/

function escapeHtml(value)
{

    return String(value)
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );

}

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>