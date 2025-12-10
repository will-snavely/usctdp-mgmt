<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="selection-section">
        <div id="family-selection-section">
            <h2> Select a Family </h2>
            <select id="family-selector">
            </select>
        </div>
    </div>
    <div id="family-display-section">
        <div id="family-details-section">
            <h2> Family Details</h2>
            <div id="family-details">
                <div id="family-email-wrap" class="family-field">
                    <label for="family-email">E-mail</label>
                    <span id="family-email"></span>
                </div>
                <div id="family-address-wrap" class="family-field">
                    <label for="family-address">Address</label>
                    <span id="family-address"></span>
                </div>
                <div id="family-city-wrap" class="family-field">
                    <label for="family-city">City</label>
                    <span id="family-city"></span>
                </div>
                <div id="family-state-wrap" class="family-field">
                    <label for="family-state">State</label>
                    <span id="family-state"></span>
                </div>
                <div id="family-zip-wrap" class="family-field">
                    <label for="family-zip">Zip</label>
                    <span id="family-zip"></span>
                </div>
                <div id="family-phone-wrap" class="family-field">
                    <label for="family-phone">Phone</label>
                    <span id="family-phone_number"></span>
                </div>
 
                <h2> Family Members</h2>
                <div id="family-table-wrap">
                    <table id="family-members-table" class="usctdp-custom-post-table">
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Birthdate</th>
                                <th>Age</th>
                            </tr>
                        </thead>
                        <tbody id="family-members-table-body">
                        </tbody>
                    </table>
                </div>

                <h2> Notes</h2>
                <div id="family-notes-wrap">
                    <textarea id="family-notes" rows=5></textarea>
                    <button id="save-notes-button" class="button button-primary"> 
                        Save Notes 
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
