<!-- ==========================================================
     WASTAGE MODAL (WITH UX IMPROVEMENTS)
=========================================================== -->

<div class="modal fade app-modal" id="wastageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-clipboard-list me-2"></i> Record Remaining Food
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="serving_id" id="wastage_serving_id">

                    <!-- ACTION TYPE RADIO BUTTONS (NEW) -->
                    <div class="mb-4 p-3 bg-light rounded border">
                        <label class="form-label d-block fw-bold text-dark mb-3">What are you recording? <span class="text-danger">*</span></label>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="record_type" id="type_wastage" value="Wastage" checked>
                            <label class="form-check-label text-danger fw-bold" for="type_wastage">
                                <i class="fa-solid fa-trash-can me-2"></i> Actual Wastage (Dustbin)
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="record_type" id="type_staff" value="Staff Consumption">
                            <label class="form-check-label text-primary fw-bold" for="type_staff">
                                <i class="fa-solid fa-users me-2"></i> Staff Consumption (Meal)
                            </label>
                        </div>
                    </div>

                    <!-- Food -->
                    <div class="mb-3">
                        <label class="form-label">Food Item</label>
                        <input type="text" class="form-control bg-light" id="wastage_food_name" readonly>
                    </div>

                    <div class="row">
                        <!-- Available -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Available Qty</label>
                            <input type="text" class="form-control bg-light text-primary fw-bold" id="wastage_available_display" readonly>
                        </div>

                        <!-- Quantity -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                Quantity <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="wastage_qty" id="wastage_qty" class="form-control border-primary" min="0.01" step="0.01" required>
                        </div>
                    </div>

                    <!-- Date -->
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="wastage_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <!-- WASTAGE SPECIFIC FIELDS (Hides when Staff Consumption is selected) -->
                    <div id="wastage_specific_fields" class="p-3 mb-3 border rounded border-danger bg-white">
                        
                        <div class="mb-3">
                            <label class="form-label text-danger">Waste Category (Wet/Dry) <span class="text-danger">*</span></label>
                            <select name="waste_type" id="waste_type" class="form-select border-danger">
                                <option value="">-- Select Type --</option>
                                <option value="Wet Waste">Wet Waste (Gravy, Rice, Spoiled Food)</option>
                                <option value="Dry Waste">Dry Waste (Dry Chapati, Bread, Wrappers)</option>
                            </select>
                        </div>

                        <div class="mb-0">
                            <label class="form-label text-danger">Wastage Reason <span class="text-danger">*</span></label>
                            <select name="reason" id="wastage_reason" class="form-select border-danger">
                                <option value="">-- Select Reason --</option>
                                <option value="Excess Food">Excess Food</option>
                                <option value="Spoiled Food">Spoiled Food</option>
                                <option value="Burnt Food">Burnt Food</option>
                                <option value="Damaged Food">Damaged Food</option>
                                <option value="Quality Issue">Quality Issue</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Optional remarks..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="record_wastage" class="btn btn-primary" id="save_btn">
                        <i class="fa-solid fa-save me-1"></i> Save Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const wastageModal = document.getElementById('wastageModal');
    const servingId = document.getElementById('wastage_serving_id');
    const foodName = document.getElementById('wastage_food_name');
    const availableDisplay = document.getElementById('wastage_available_display');
    const wastageQty = document.getElementById('wastage_qty');
    
    // UI Elements for Toggle
    const typeWastage = document.getElementById('type_wastage');
    const typeStaff = document.getElementById('type_staff');
    const wastageFields = document.getElementById('wastage_specific_fields');
    const reasonDropdown = document.getElementById('wastage_reason');
    const wasteTypeDropdown = document.getElementById('waste_type');
    const saveBtn = document.getElementById('save_btn');

    // Function to show/hide fields based on Radio button
    function toggleFields() {
        if (typeStaff.checked) {
            // Hide Wastage fields & remove required attribute
            wastageFields.style.display = 'none';
            reasonDropdown.removeAttribute('required');
            wasteTypeDropdown.removeAttribute('required');
            
            // Change button color for better UX
            saveBtn.classList.remove('btn-danger');
            saveBtn.classList.add('btn-primary');
        } else {
            // Show Wastage fields & make them required
            wastageFields.style.display = 'block';
            reasonDropdown.setAttribute('required', 'required');
            wasteTypeDropdown.setAttribute('required', 'required');
            
            // Change button color
            saveBtn.classList.remove('btn-primary');
            saveBtn.classList.add('btn-danger');
        }
    }

    // Attach event listeners to radio buttons
    typeWastage.addEventListener('change', toggleFields);
    typeStaff.addEventListener('change', toggleFields);

    wastageModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-serving-id');
        const food = button.getAttribute('data-food-name');
        const available = parseFloat(button.getAttribute('data-available')) || 0;

        servingId.value = id;
        foodName.value = food;
        availableDisplay.value = available.toFixed(2);
        wastageQty.value = '';
        wastageQty.max = available;

        // Reset to default (Wastage) on open
        typeWastage.checked = true;
        reasonDropdown.value = '';
        wasteTypeDropdown.value = '';
        toggleFields();
    });

    /* Client-side quantity validation */
    wastageQty.addEventListener('input', function () {
        const max = parseFloat(wastageQty.max) || 0;
        const value = parseFloat(wastageQty.value) || 0;
        if (value > max) {
            wastageQty.value = max.toFixed(2);
        }
        if (value < 0) {
            wastageQty.value = '';
        }
    });
});
</script>