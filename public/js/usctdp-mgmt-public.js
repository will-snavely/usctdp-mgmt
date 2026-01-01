(function ($) {
  "use strict";

  $(function () {
    // Session Change Handler
    $('#usctdp_session').on('change', function () {
      var sessionId = $(this).val();
      var courseId = $('#usctdp_course_id').val();
      var $classesContainer = $('#usctdp_classes_container');
      var $classesList = $('#usctdp_classes_list');

      if (!sessionId) {
        $classesContainer.hide();
        $classesList.empty();
        return;
      }

      // Fetch classes via AJAX
      $.ajax({
        url: usctdp_mgmt_params.ajax_url,
        type: 'GET',
        data: {
          action: 'usctdp_get_classes',
          session_id: sessionId,
          course_id: courseId
        },
        success: function (response) {
          if (response.success && response.data.length > 0) {
            var html = '';
            $.each(response.data, function (index, cls) {
              html += '<div class="usctdp-class-option">';
              html += '<label>';
              html += '<input type="checkbox" name="usctdp_classes[]" value="' + cls.id + '"> ';
              html += cls.title + ' (' + cls.dow + ')';
              html += '</label>';
              html += '</div>';
            });
            $classesList.html(html);
            $classesContainer.show();
          } else {
            $classesList.html('<p>No classes available for this session.</p>');
            $classesContainer.show();
          }
        },
        error: function () {
          alert('Error loading classes. Please try again.');
        }
      });
    });

    // Student Change Handler
    $('#usctdp_student').on('change', function () {
      var val = $(this).val();
      if (val === 'new') {
        $('#usctdp_new_student_fields').show();
        $('#usctdp_new_student_first_name').prop('required', true);
        $('#usctdp_new_student_last_name').prop('required', true);
        $('#usctdp_new_student_dob').prop('required', true);
      } else {
        $('#usctdp_new_student_fields').hide();
        $('#usctdp_new_student_first_name').prop('required', false);
        $('#usctdp_new_student_last_name').prop('required', false);
        $('#usctdp_new_student_dob').prop('required', false);
      }
    });
  });

})(jQuery);
