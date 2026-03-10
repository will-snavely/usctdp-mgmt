<?php
$family_fields = [
    'email' => ['E-mail', "text"],
    'address' => ['Address', "text"],
    'city' => ['City', "text"],
    'state' => ['State', "text"],
    'zip' => ['Zip', "text"],
    'phone' => ['Phone (One per line)', "textarea"]
];
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div class="main-content">
        <dialog id="new-student-modal">
            <form id="new-student-form" method="dialog">
                <h2>Add New Student</h2>
                <div class="modal_field">
                    <label for="student_modal_first_name">First Name</label>
                    <input type="text" id="student_modal_first_name" name="first_name" required>
                </div>

                <div class="modal_field">
                    <label for="student_modal_last_name">Last Name</label>
                    <input type="text" id="student_modal_last_name" name="last_name" required>
                </div>

                <div class="modal_field">
                    <label for="student_modal_birthdate">Birthday</label>
                    <input type="date" id="student_modal_birthdate" name="birthdate" required>
                </div>

                <div class="modal_field">
                    <label for="student_modal_level">Level</label>
                    <input type="text" id="student_modal_level" name="level" required>
                </div>

                <div class="actions">
                    <button type="button" class="button" id="close-student-modal">Cancel</button>
                    <button type="submit" class="button" id="save-student-modal">Save Student</button>
                </div>
            </form>
        </dialog>

        <dialog id="new-family-modal">
            <form id="new-family-form" method="dialog">
                <h2>Add New Family</h2>
                <div class="modal_field">
                    <label for="family_modal_last_name">Last Name</label>
                    <input type="text" id="family_modal_last_name" name="last_name" required>
                </div>

                <div class="modal_field">
                    <label for="family_modal_address">Address</label>
                    <input type="text" id="family_modal_address" name="address" required>
                </div>

                <div class="modal_field">
                    <label for="family_modal_city">City</label>
                    <input type="text" id="family_modal_city" name="city" required>
                </div>

                <div class="modal_field">
                    <label for="family_modal_state">State</label>
                    <input type="text" id="family_modal_state" name="state" required>
                </div>

                <div class="modal_field">
                    <label for="family_modal_zip">Zip</label>
                    <input type="text" id="family_modal_zip" name="zip" required>
                </div>

                <div class="modal_field">
                    <label for="family_modal_email">Email</label>
                    <input type="email" id="family_modal_email" name="email" required>
                </div>

                <div class="modal_field">
                    <label for="family_modal_phone">Phone</label>
                    <input type="text" id="family_modal_phone" name="phone" required>
                </div>

                <div class="actions">
                    <button type="button" class="button" id="close-family-modal">Cancel</button>
                    <button type="submit" class="button" id="save-family-modal">Save Family</button>
                </div>
            </form>
        </dialog>

        <div id="selection-section">
            <div id="context-selectors"></div>
            <div id="new-family-section">
                <button id="new-family-button" class="button button-primary">
                    <span id="new-family-text" class="button-text">Add New Family...</span>
                </button>
            </div>
        </div>

        <div id="family-section" class="hidden">
            <h2 class="family-title"> Family: <span id="family-title"></span></h2>
            <div id="family-container">
                <div>
                    <a href="#" class="button button-primary" id="family-registration-history-link">
                        View Registration History...
                    </a>
                </div>
                <div id="fields-panel">
                    <div id="fields-container">
                    </div>
                    <div class="controls">
                        <button class="button button-primary" id="save-btn" disabled> Save Changes </button>
                        <div class="fields-status">
                            <span id="fields-status-msg"></span>
                        </div>
                    </div>
                </div>

                <div class="family-members">
                    <h2> Family Members</h2>
                    <div>
                        <a href=" #" class="button button-primary" id="new-student-button">
                            Add New Member...
                        </a>
                    </div>
                    <div id="family-table-wrap">
                        <table id="family-members-table" class="usctdp-datatable">
                            <thead>
                                <tr>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>Birthdate</th>
                                    <th>Age</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="family-members-table-body">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>