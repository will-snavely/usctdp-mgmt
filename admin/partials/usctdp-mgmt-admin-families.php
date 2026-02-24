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
            <div id="family-selection-section">
                <h2> Select a Family </h2>
                <select id="family-selector">
                </select>
            </div>
            <div id="new-family-section">
                <button id="new-family-button" class="button button-primary">
                    <span id="new-family-text" class="button-text">Add New Family</span>
                </button>
            </div>
        </div>

        <div id="family-section" class="hidden">
            <h2 class="family-title"> Family: <span id="family-title"></span></h2>
            <div id="family-container">
                <div id="family-properties">
                    <div id="family-details">
                        <div id="family-info-list">
                            <?php foreach ($family_fields as $field => $data): ?>
                                <div id="family-<?php echo esc_attr($field); ?>" class="family-field">
                                    <label>
                                        <?php echo esc_html($data[0]); ?>
                                    </label>
                                    <div class="view-mode">
                                        <?php if ($data[1] === "text") : ?>
                                            <span class="view-mode-text"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="edit-mode hidden">
                                        <?php if ($data[1] === "textarea") : ?>
                                            <textarea rows="5" class="editor"></textarea>
                                        <?php else : ?>
                                            <input type="text" class="editor">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div id="family-actions">
                            <button id="edit-family-button" class="button">
                                <span id="edit-family-text" class="button-text">Edit</span>
                            </button>
                        </div>
                    </div>
                    <div class="family-notes">
                        <label>Notes</label>
                        <div id="family-notes-wrap">
                            <textarea id="family-notes" rows=10></textarea>
                            <div id="save-notes-action">
                                <button id="save-notes-button" class="button">
                                    <span id="save-notes-text">Save Notes</span>
                                </button>
                                <div id="save-notes-status">
                                    <span id="save-notes-success" class="hidden success">
                                        Notes Saved!
                                    </span>
                                    <span id="save-notes-error" class="hidden error">
                                        Failed to save notes.
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="family-actions">
                    <h2>Actions</h2>
                    <div id="family-actions-list">
                        <div>
                            <a href=" #" class="button button-primary" id="new-student-button">
                                Add New Student
                            </a>
                        </div>
                        <div>
                            <a href="#" class="button button-primary" id="family-registration-history-link">
                                View History
                            </a>
                        </div>
                    </div>
                </div>
                <div class="family-members">
                    <h2> Family Members</h2>
                    <div id="family-table-wrap">
                        <table id="family-members-table" class="usctdp-custom-post-table">
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