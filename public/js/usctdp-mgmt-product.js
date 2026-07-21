(function ($) {
  "use strict";

  jQuery(document).ready(function ($) {
    const daysPerWeek = {
      "One": 1,
      "Two": 2
    };

    const int_to_day = {
      1: 'Monday',
      2: 'Tuesday',
      3: 'Wednesday',
      4: 'Thursday',
      5: 'Friday',
      6: 'Saturday',
      7: 'Sunday'
    };

    const modal = document.querySelector('#new-student-modal');
    const waitlistedEntries = new Set();
    let cartBlocked = false;

    function waitlistKey(studentId, activityId) {
      return studentId + ':' + activityId;
    }

    function clear_day_selectors() {
      $('#usctdp-day-selectors').empty();
      cartBlocked = false;
      $('.single_add_to_cart_button').removeClass('usctdp-cart-disabled');
    }

    function format_time(timeString) {
      const [hours, minutes, seconds] = timeString.split(':');
      const date = new Date();
      date.setHours(hours, minutes, seconds);

      const formattedTime = date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      });
      return formattedTime;
    }

    function syncDaySelectors(source, target) {
      const selectedDay = $(source).find(':selected').data('day-of-week');

      $(target).find('option').prop('disabled', false);

      if (selectedDay) {
        $(target).find(`option[data-day-of-week="${selectedDay}"]`).each(function () {
          const $option = $(this);
          $option.prop('disabled', true);
        });
      }

      $(target).select2({
        placeholder: 'Select a day...',
        allowClear: true,
        width: '100%'
      });
    }

    function updateCartAvailability() {
      cartBlocked = $('.usctdp-day-selector select').toArray().some(function (el) {
        return $(el).find(':selected').data('full') === true;
      });
      $('.single_add_to_cart_button').toggleClass('usctdp-cart-disabled', cartBlocked);
    }

    function updateDayStatus($select, $statusWrap) {
      const $selected = $select.find(':selected');
      const isFull = $selected.data('full') === true;
      const $btn = $statusWrap.find('.add-waitlist-btn');

      $statusWrap.toggleClass('force-hidden', !isFull);
      if (isFull) {
        const activityId = String($selected.val());
        const studentId = $('#student_select').val();
        const alreadyWaitlisted = !!studentId && waitlistedEntries.has(waitlistKey(studentId, activityId));
        $btn.data('activity-id', activityId);
        $btn.prop('disabled', alreadyWaitlisted);
        $btn.text(alreadyWaitlisted ? 'Added to Waitlist' : 'Add to Waitlist');
      }

      updateCartAvailability();
    }

    function refreshAllDayStatuses() {
      $('.usctdp-day-selector').each(function () {
        const $wrapper = $(this);
        updateDayStatus($wrapper.find('select'), $wrapper.find('.usctdp-day-status'));
      });
    }

    function add_day_selector(clinics, day_index, label_text, session_label) {
      var wrapper = $('<div></div>');
      wrapper.addClass('usctdp-day-selector');
      var label = $('<label></label>');
      label.attr('for', 'day_of_week_' + day_index);
      label.text(label_text);
      wrapper.append(label);
      var selector = $('<select></select>');
      selector.attr('name', 'day_of_week_' + day_index);
      selector.attr('id', 'day_of_week_' + day_index);
      selector.prop('required', true);
      selector.append('<option value=""></option>');
      clinics.forEach(function (clinic) {
        var dowStr = int_to_day[clinic.day_of_week];
        var startTime = format_time(clinic.start_time);
        var dayLabel = dowStr + ' at ' + startTime;
        var isFull = clinic.enrolled_count >= clinic.capacity;
        var optionText = isFull ? dayLabel + ' (Full)' : dayLabel;
        selector.append($('<option></option>')
          .attr('value', clinic.id)
          .attr('data-day-of-week', clinic.day_of_week)
          .attr('data-start-time', clinic.start_time)
          .attr('data-full', isFull)
          .text(optionText));
      });
      wrapper.append(selector);

      var statusWrap = $('<div></div>').addClass('usctdp-day-status force-hidden');
      statusWrap.append($('<span></span>').addClass('usctdp-full-message').text('This class is full.'));
      statusWrap.append($('<button></button>')
        .attr('type', 'button')
        .addClass('button add-waitlist-btn')
        .text('Add to Waitlist'));
      wrapper.append(statusWrap);

      $('#usctdp-day-selectors').append(wrapper);
      $('#day_of_week_' + day_index).select2({
        placeholder: 'Select a day...',
        allowClear: true,
        width: '100%'
      });

      $('#day_of_week_' + day_index).on('change', function () {
        syncDaySelectors(this, '#day_of_week_' + (day_index == 1 ? 2 : 1));
        updateDayStatus($(this), statusWrap);
      });
    }

    function populateStudentSelect($select, initial_value = null) {
      $select.prop('disabled', true);
      return fetch(siteData.root + 'usctdp-mgmt/v1/students/', {
        method: 'GET',
        headers: {
          'X-WP-Nonce': siteData.nonce
        }
      })
        .then(response => response.json())
        .then(data => {
          const formattedData = data.map(item => ({
            id: item.id,
            text: item.title
          }));
          if ($select.data('select2')) {
            $select.select2('destroy').empty();
          }
          $select.select2({
            data: formattedData,
            placeholder: 'Select a student...',
            allowClear: true,
            width: '100%'
          });
        })
        .catch(error => console.error('Error loading options:', error))
        .finally(() => {
          // Always re-enable
          $select.prop('disabled', false);
          $select.val(initial_value).trigger('change');
        });
    }

    function refreshStudentDropDown(initial_value = null) {
      populateStudentSelect($('#student_select'), initial_value);
    }

    // Listen for the event on the variations form
    $('.variations_form').on('found_variation', function (event, variation) {
      var daysPerWeekStr = variation.attributes["attribute_days-per-week"];
      var session = variation.attributes["attribute_session"];
      var session_id = siteData.session_map[session];
      fetch(siteData.root + 'usctdp-mgmt/v1/clinics/' + session_id + '/' + siteData.usctdp_id, {
        method: 'GET',
        headers: {
          'X-WP-Nonce': siteData.nonce
        }
      })
        .then(response => response.json())
        .then(data => {
          clear_day_selectors();
          var days = daysPerWeek[daysPerWeekStr];
          if (days == 1) {
            add_day_selector(data, 1, 'Select Day', session);
          } else {
            add_day_selector(data, 1, 'Select 1st Day', session);
            add_day_selector(data, 2, 'Select 2nd Day', session);
          }
        })
        .catch(error => console.error('Error loading options:', error));
      $('#usctdp-woocommerce-extra').removeClass('force-hidden');
    });

    $('.variations_form').on('reset_data', function () {
      clear_day_selectors();
      $('#usctdp-woocommerce-extra').addClass('force-hidden');
    });

    // When a variation attribute (e.g. Session) has only one real option,
    // there's nothing for the customer to choose. Auto-select it, hide the
    // dropdown, and trigger the same 'change' event WooCommerce's own
    // variation-form script listens for, so found_variation still fires
    // normally and the rest of the flow (day selectors, etc.) is unaffected.
    $('.variations_form select[name^="attribute_"]').each(function () {
      var $select = $(this);
      var $realOptions = $select.find('option').filter(function () {
        return $(this).val() !== '';
      });
      if ($realOptions.length === 1) {
        var $wrapper = $select.closest('tr');
        if (!$wrapper.length) {
          $wrapper = $select.parent();
        }
        $wrapper.addClass('force-hidden');
        $select.val($realOptions.first().val()).trigger('change');
      }
    });

    // Open modal
    $('#new-student-button').on('click', (e) => {
      e.preventDefault();
      modal.showModal();
    });

    // Close modal on "Cancel"
    $('#close-modal').on('click', () => {
      modal.close();
    });

    // Handle Form Submission
    $('#new-student-form').on('submit', async (e) => {
      // Prevent the default form close for the API call
      e.preventDefault();
      const studentForm = document.querySelector('#new-student-form');
      const formData = new FormData(studentForm);
      const studentData = Object.fromEntries(formData.entries());

      try {
        const response = await fetch(siteData.root + 'usctdp-mgmt/v1/students/', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': siteData.nonce
          },
          body: JSON.stringify(studentData),
        });

        if (response.ok) {
          studentForm.reset();
          const student_id = await response.json();
          refreshStudentDropDown(student_id);
          modal.close();
        } else {

        }
      } catch (error) {
        console.error('Error:', error);
      }
    });

    // Add the currently selected student to the waitlist for a full day
    $('#usctdp-day-selectors').on('click', '.add-waitlist-btn', async function (e) {
      e.preventDefault();
      const $btn = $(this);
      const studentId = $('#student_select').val();
      if (!studentId) {
        alert('Please select a student before adding them to the waitlist.');
        $('#student_select').select2('open');
        return;
      }

      const activityId = $btn.data('activity-id');
      $btn.prop('disabled', true).text('Adding...');

      try {
        const response = await fetch(siteData.root + 'usctdp-mgmt/v1/waitlist/', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': siteData.nonce
          },
          body: JSON.stringify({
            student_id: studentId,
            activity_id: activityId
          }),
        });

        if (response.ok) {
          waitlistedEntries.add(waitlistKey(studentId, activityId));
          $btn.text('Added to Waitlist');
        } else {
          console.error('Error joining waitlist:', await response.text());
          $btn.prop('disabled', false).text('Add to Waitlist');
          alert('Failed to add student to the waitlist. Please try again.');
        }
      } catch (error) {
        console.error('Error:', error);
        $btn.prop('disabled', false).text('Add to Waitlist');
        alert('Failed to add student to the waitlist. Please try again.');
      }
    });

    // Block checkout while a full day is selected
    $('.variations_form').on('submit', function (e) {
      if (cartBlocked) {
        e.preventDefault();
        alert('One or more selected days are full. Please choose a different day, or add the student to the waitlist instead.');
      }
    });

    // Re-evaluate "Add to Waitlist" button state for the newly selected student
    $('#student_select').on('change', function () {
      refreshAllDayStatuses();
    });

    refreshStudentDropDown();
  });
})(jQuery);
