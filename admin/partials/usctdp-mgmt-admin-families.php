<?php
$family_fields = [
    'email' => 'E-mail',
    'address' => 'Address',
    'city' => 'City',
    'state' => 'State',
    'zip' => 'Zip',
    'phone' => 'Phone'
];
?>
<div class="main-content">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="selection-section">
        <div id="family-selection-section">
            <h2> Select a Family </h2>
            <select id="family-selector">
            </select>
        </div>
    </div>

    <div id="family-container" class="hidden">
        <div class="family-details">
            <div id="family-info-list">
                <?php foreach ($family_fields as $field => $label): ?>
                    <div id="family-<?php echo esc_attr($field); ?>" class="family-field">
                        <label>
                            <?php echo esc_html($label); ?>
                        </label>
                        <span class="view-mode"></span>
                        <input type="text" class="edit-mode hidden">
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="family-actions">
                <button id="edit-family-button" class="button button-primary">
                    <span id="edit-family-text" class="button-text">Edit</span>
                </button>
            </div>
        </div>
        <div class="family-notes">
            <label>Notes</label>
            <div id="family-notes-wrap">
                <textarea id="family-notes" rows=10></textarea>
                <div id="save-notes-action">
                    <button id="save-notes-button" class="button button-primary">
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