(function ($) {
  "use strict";

$(document).ready(function() {
        const $tableBody = $('#input-table-body');
        const templateHtml = $('#row-template').html();

        function updateRowNames() {
            // Select all actual data rows (excluding the hidden template)
            const $dataRows = $tableBody.find('tr:not(#row-template)');
            
            $dataRows.each(function(index) {
                const $row = $(this);
                const rowNum = index + 1;

                // Update the visible row number
                $row.find('.row-index').text(rowNum);
            });
            
            // If all rows are removed, ensure one row is always present
            if ($dataRows.length === 0) {
                 addRow();
            }
        }

        /**
         * Clones the template row and appends it to the table.
         */
          function addRow() {
              const $newRow = $(templateHtml); 
              const $numRows = $('#num-rows-field').val()
              $newRow.find('input').val('');
              for (let i = 0; i < $numRows; i++) {
                var $clone = $newRow.clone(); 
                $tableBody.append($clone);
              }
              updateRowNames();
          }

          $tableBody.on('click', '.remove-row-btn', function() {
            const $rowToRemove = $(this).closest('tr');
            
            if ($tableBody.find('tr:not(#row-template)').length > 1) {
                $rowToRemove.remove();
                updateRowNames();
            } else {
                alert("You must have at least one row.");
            }
        });

        $tableBody.on('click', '.dup-row-btn', function() {
            const $targetRow = $(this).closest('tr');
            const $cloned = $targetRow.clone(true);
            $cloned.insertAfter($targetRow);
            $cloned.find('select').each(function() {
                var originalValue = $targetRow.find('#' + this.id).val();
                $(this).val(originalValue);
            });
            updateRowNames();
        });

        // --- INITIALIZATION ---
        
        // 1. Attach event listener for the "Add Row" button
        $('#add-row-btn').on('click', addRow);
        
        // 2. Add the initial, default row to start
        addRow();

        acf.addAction('ready', function(){
            var $admin_page_wrapper = $('#usctdp-admin-new-session-wrapper'); 
            acf.do_action('append', $admin_page_wrapper);
        });
    });
})(jQuery);
