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

    function clear_day_selectors() {
      $('#usctdp-day-selectors').empty();
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

      $(target).find('option').each(function () {
        const $option = $(this);
        const isFull = $option.data('full');
        if (!isFull) {
          $option.prop('disabled', false);
        }
      });

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

    function add_day_selector(clinics, day_index, label_text) {
      var wrapper = $('<div></div>');
      wrapper.addClass('usctdp-day-selector');
      var label = $('<label></label>');
      label.attr('for', 'day_of_week_' + day_index);
      label.text(label_text);
      wrapper.append(label);
      var selector = $('<select></select>');
      selector.attr('name', 'day_of_week_' + day_index);
      selector.attr('id', 'day_of_week_' + day_index);
      selector.append('<option value=""></option>');
      clinics.forEach(function (clinic) {
        var dowStr = int_to_day[clinic.day_of_week];
        var startTime = format_time(clinic.start_time);
        var optionText = dowStr + ' at ' + startTime;
        var optionId = clinic.id;
        var disabled = false;
        if (clinic.enrolled_count >= clinic.capacity) {
          optionText += ' (Full)';
          disabled = true;
        }
        selector.append($('<option></option>')
          .attr('value', optionId)
          .attr('data-day-of-week', clinic.day_of_week)
          .attr('data-start-time', clinic.start_time)
          .attr('data-full', disabled)
          .text(optionText)
          .prop('disabled', disabled));
      });
      wrapper.append(selector);
      $('#usctdp-day-selectors').append(wrapper);
      $('#day_of_week_' + day_index).select2({
        placeholder: 'Select a day...',
        allowClear: true,
        width: '100%'
      });

      $('#day_of_week_' + day_index).on('change', function () {
        syncDaySelectors(this, '#day_of_week_' + (day_index == 1 ? 2 : 1));
      });
    }

    function refreshStudentDropDown(initial_value = null) {
      const $select = $('#student_select');
      $select.prop('disabled', true);
      fetch(siteData.root + 'usctdp-mgmt/v1/students/', {
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
            add_day_selector(data, 1, 'Select Day');
          } else {
            add_day_selector(data, 1, 'Select 1st Day');
            add_day_selector(data, 2, 'Select 2nd Day');
          }
        })
        .catch(error => console.error('Error loading options:', error));
      $('#usctdp-woocommerce-extra').removeClass('force-hidden');
    });

    $('.variations_form').on('reset_data', function () {
      clear_day_selectors();
      $('#usctdp-woocommerce-extra').addClass('force-hidden');
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

    refreshStudentDropDown();
  });
})(jQuery);
