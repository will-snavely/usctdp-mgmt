(function ($) {
    "use strict";

    $(document).ready(function () {
        const $tableBody = $('#new-session-input-table-body');
        const templateHtml = $('#new-session-row-template').html();
        let rowCounter = 0;
        let initTableSize = 1;

        $('#usctdp-new-session-form').on('submit', function (event) {
            event.preventDefault();
            const $sessionInputs = $(this).find('#session-fields').find('input');
            const $classCells = $(this).find('#class-info-section').find('td.acf-field');
            let formIsValid = true;

            $sessionInputs.each(function () {
                if ($(this).val() === '') {
                    formIsValid = false;
                    $(this).addClass('invalid-field');
                } else {
                    $(this).removeClass('invalid-field');
                }
            });

            $classCells.each(function () {
                const dataType = $(this).attr('data-type');
                const dataRequired = $(this).attr('data-required');

                if (dataRequired === '1') {
                    if (dataType === 'select') {
                        const $select = $(this).find('select').first();
                        if ($select.val() === '') {
                            formIsValid = false;
                            $select.addClass('invalid-field');
                        } else {
                            $select.removeClass('invalid-field');
                        }
                    } else if (dataType === 'time_picker') {
                        const $hiddenTimePicker = $(this).find('input').eq(0);
                        const $visibleInput = $(this).find('input').eq(1);
                        if ($hiddenTimePicker.val() === '') {
                            formIsValid = false;
                            $visibleInput.addClass('invalid-field');
                        } else {
                            $visibleInput.removeClass('invalid-field');
                        }
                    } else {
                        const $input = $(this).find('input').first();
                        if ($input.val() === '') {
                            formIsValid = false;
                            $input.addClass('invalid-field');
                        } else {
                            $input.removeClass('invalid-field');
                        }
                    }
                }
            });

            if (formIsValid) {
                this.submit();
            } else {
                $("div#form-submission-errors .error-message").show();
            }
        });

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
                const acfRegex = /acf\[([a-zA-Z0-9_-]*)\](.*)/;
                const match = name ? name.match(acfRegex) : null;
                if (match) {
                    const fieldName = match[1];
                    const remainder = match[2];
                    $input.attr('name', 'usctdp_classes[' + rowId + '][' + fieldName + ']' + remainder);
                }

                var id = $input.attr('id');
                if (id) {
                    var baseId = id.replace(/-\d+$/, '');
                    $input.attr('id', baseId + '-' + rowId);
                }
            });


            $row.find('.multi-date-picker').each(function () {
                $(this).flatpickr({
                    mode: 'multiple',
                    dateFormat: 'Y-m-d'
                });
                $(this).on('change', function () {
                    $(this).attr('title', $(this).val());
                });
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
