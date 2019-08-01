(($, window, document, undefined) => {
  /**
   * Display a spinner.
   *
   * @param {string} spinner - Spinner data ID.
   */
  window.trackmageShowSpinner = function trackmageShowSpinner(spinner) {
    $('.trackmage-spinner[data-spinner="' + spinner + '"]').css('display', 'inline-block');
  }

  /**
   * Hide a spinner.
   * @param {string} spinner - Spinner data ID.
   */
  window.trackmageHideSpinner = function trackmageHideSpinner(spinner) {
    $('.trackmage-spinner[data-spinner="' + spinner + '"').css('display', 'none');
  }

  /**
   * Append a `<div>` overlay to the `<body>` tag.
   */
  window.trackmageLoadOverlay = function trackmageLoadOverlay() {
    if ($('#trackmage-overlay').length === 0) {
      let overlay = $('<div></div>').attr('id', 'trackmage-overlay');
      let loader = $('<div></div>').attr('class', 'loader');
      let trackmageImg = $('<img class="icon-trackmage" />').attr('src', trackmageAdminParams.images.iconTrackMage);
      let loaderImg = $('<img class="loader" />').attr('src', trackmageAdminParams.images.loader);

      $(trackmageImg).appendTo(loader);
      $(loaderImg).appendTo(loader);
      $(loader).appendTo(overlay);
      $('body').append(overlay);
    }
  }

  /**
   * Display the overlay.
   */
  window.trackmageShowOverlay = function trackmageShowOverlay() {
    $('#trackmage-overlay').css('display', 'table').animate({
      opacity: 1
    }, 200);
  }

  /**
   * Hide the overlay.
   */
  window.trackmageHideOverlay = function trackmageHideOverlay() {
    $('#trackmage-overlay').animate({
      opacity: 0
    }, 200, () => $('#trackmage-overlay').css('display', 'none'));
  }

  /**
   * Display inline notification.
   *
   * @param {string} notification - Notification ID.
   * @param {string} type - Notification type.
   * @param {string} message - Notification message.
   */
  window.trackmageNotification = function trackmageNotification(notification, type, message) {
    notification = $('.trackmage-notification[data-notification="' + notification + '"]');

    // Remove any appeneded classes.
    notification.attr('class', 'trackmage-notification');

    // Append notification type class.
    let typeClass = 'trackmage-' + type;
    notification.addClass(typeClass);

    // Add notification message.
    notification.html('<span class="message">' + message + '</span>');

    // Display notification message.
    notification.slideDown(500);
  }

  /**
   * On document ready.
   */
  $(document).ready(() => {
    /**
     * Disable input fields, buttons and links inside disabled sections.
     */
    $('.wrap.trackmage .section.disabled').each(function(e) {
      let section = $(this);

      section.find('select, input, button').prop('disabled', true);
      section.find('.input-toggle').addClass('disabled');
      section.find('a, button, input').on('click', (e) => {
        e.preventDefault();
      });
    });
    /**
     * Hide notifications on click.
     */
    $('.trackmage-notification').on('click', function () {
      $(this).slideUp(500);
    });

    /**
     * Adjust all toggle inputs.
     */
    $('.trackmage-input-toggle').each(function (e) {
      let toggle = $(this).children('.input-toggle');
      let input = $(this).children('input[type="checkbox"]');

      if (!input.is(':checked')) {
        toggle.addClass('off');
      }

      $(this).css('display', 'inline-block');
    });

    /**
     * Toggle inputs.
     */
    $('.trackmage-input-toggle').on('click', function (e) {
      let toggle = $(this).children('.input-toggle');
      let input = $(this).children('input[type="checkbox"]');

      if (toggle.hasClass('disabled')) {
        return;
      }
      
      if (toggle.hasClass('off')) {
        toggle.removeClass('off');
        input.attr('checked', true);
      } else {
        toggle.addClass('off');
        input.attr('checked', false);
      }
    });

    // Append overlay.
    trackmageLoadOverlay();

    // Test credentials.
    $('#trackmage-settings-general #testCredentials').on('click', (e) => {
      e.preventDefault();

      // Request data.
      let data = {
        'action': 'trackmage_test_credentials',
        'clientId': $('#trackmage-settings-general [name="trackmage_client_id"]').val(),
        'clientSecret': $('#trackmage-settings-general [name="trackmage_client_secret"]').val(),
      };

      // Response message.
      let message = '';

      $.ajax({
        url: trackmageAdminParams.ajaxUrl,
        method: 'post',
        data: data,
        beforeSend: function () {
          // Show overlay.
          trackmageShowOverlay();
        },
        success: function (response) {
          // Hide overlay.
          trackmageHideOverlay();

          if (response.data.status === 'success') {
            message = trackmageAdminParams.messages.successValidKeys;
          } else if (response.data.errors) {
            message = response.data.errors.join(' ');
          } else {
            message = trackmageAdminParams.messages.unknownError;
          }

          // Response notification.
          trackmageNotification('test-credentials', response.data.status, message);
        },
        error: function () {
          // Hide overlay.
          trackmageHideOverlay();

          message = trackmageAdminParams.messages.unknownError;

          // Response notification.
          trackmageNotification('test-credentials', response.data.status, message);
        }
      });
    });
  });
})(jQuery, window, document);
