(function ($) {
    "use strict";

    $(document).ready(function () {
        const $tableBody = $('#new-session-input-table-body');
        const templateHtml = $('#new-session-row-template').html();
        let rowCounter = 0;
        let initTableSize = 5;

        function updateRowIndices() {
            $tableBody.find('tr').each(function (index) {
                $(this).find('.row-index').text(index + 1);
            });
        }

        acf.addAction('new_field/key=field_usctdp_class_instructors', function (field) {
            const $targetCell = field.$el;
            setTimeout(function () {
                if ($targetCell.length && $targetCell.attr("js-source-id")) {

                    const sourceId = $targetCell.attr("js-source-id");
                    const $sourceSelectField = $("#" + sourceId);
                    const $targetSelectField = $targetCell.find("select").first();
                    if ($sourceSelectField.data('select2') && $targetSelectField.data('select2')) {
                        const sourceIDs = $sourceSelectField.val();
                        const sourceData = $sourceSelectField.select2('data');
                        if (sourceIDs && sourceIDs.length > 0) {
                            sourceData.forEach(function (item) {
                                const newOption = new Option(item.text, item.id, true, true);
                                $targetSelectField.append(newOption);
                            });
                            $targetSelectField.val(sourceIDs);
                            $targetSelectField.trigger("change");
                        }
                    }
                }
            }, 0);
        });

        function addRow($row, after) {
            var rowId = rowCounter;
            rowCounter++;

            $row.find('input, select, textarea').each(function () {
                var $input = $(this);
                var name = $input.attr('name');
                if (name) {
                    if (name.endsWith('[]')) {
                        var baseName = name.substring(0, name.length - 2);
                        $input.attr('name', 'usctdp_classes[' + rowId + '][' + baseName + '][]');
                    } else {
                        $input.attr('name', 'usctdp_classes[' + rowId + '][' + name + ']');
                    }
                }

                var id = $input.attr('id');
                if (id) {
                    var baseId = id.replace(/-\d+$/, '');
                    $input.attr('id', baseId + '-' + rowId);
                }
            });

            if (after) {
                after.after($row);
            } else {
                $tableBody.append($row);
            }
            acf.do_action('append', $row);
        }

        $tableBody.on('click', '.remove-row-btn', function () {
            const $rowToRemove = $(this).closest('tr');
            if ($tableBody.find('tr').length > 1) {
                $rowToRemove.remove();
                updateRowIndices();
            } else {
                alert("You must have at least one row.");
            }
        });

        $tableBody.on('click', '.dup-row-btn', function () {
            const $sourceRow = $(this).closest('tr');
            const $sourceCells = $sourceRow.find('td');
            var $newRow = $(templateHtml).clone();
            $newRow.addClass("js-dirty-row");
            $newRow.find('td').each(function (index) {
                const $sourceCell = $sourceCells.eq(index);
                if ($(this).hasClass("acf-field-post-object")) {
                    const sourceId = $sourceCell.find("select").first().attr("id");
                    console.log("Source Id: " + sourceId);
                    $(this).attr("js-source-id", sourceId);
                } else {
                    const $sourceElements = $sourceCell.find('select, input');
                    $(this).find('select, input').each(function (index) {
                        const $input = $sourceElements.eq(index);
                        $(this).val($input.val());
                    });
                }
            });
            addRow($newRow, $sourceRow);
            updateRowIndices();
        });

        $('#new-session-add-row-btn').on('click', function () {
            var numRowsField = $('#new-session-num-rows-field');
            if (numRowsField.val() === "") {
                numRowsField.val(1);
            }
            var numRows = parseInt(numRowsField.val());
            for (let i = 0; i < numRows; i++) {
                var $newRow = $(templateHtml).clone();
                addRow($newRow);
            }
            updateRowIndices();
        });

        for (let i = 0; i < initTableSize; i++) {
            const $newRow = $(templateHtml);
            addRow($newRow);
        }
        updateRowIndices();
    });
})(jQuery);
