(($, window, document, undefined) => {
  let params = trackmageAdminParams;

  /**
   * Activate/deactivate spinner.
   *
   * @param {object} el - Spinner activator.
   * @param {string} action - Activate/deactivate.
   */
  window.trackmageToggleSpinner = function trackmageToggleSpinner(el, action) {
    if (action === 'activate') {
      $(el).siblings('.spinner').addClass('is-active');
    } else if (action === 'deactivate') {
      $(el).siblings('.spinner').removeClass('is-active');
    }
  }

  /**
   * Enable/Disable form element.
   *
   * @param {object} el - The element object.
   * @param {string} action - Enable/disable.
   */
  window.trackmageToggleFormElement = function trackmageToggleFormElement(el, action) {
    if (action === 'enable' || action === 'disable') {
      $(el).prop('disabled', action === 'enable' ? false : true);
    }
  }

  /**
   * Create and append overlay to the body.
   */
  window.trackmageOverlay = function trackmageOverlay() {
    if ($('#trackmage-overlay').length === 0) {
      let overlay = $('<div></div>').attr('id', 'trackmage-overlay');
      let loader = $('<div></div>').attr('class', 'loader');
      let trackmageImg = $('<img class="icon-trackmage" />').attr('src', params.images.iconTrackMage);
      let loaderImg = $('<img class="loader" />').attr('src', params.images.loader);

      $(trackmageImg).appendTo(loader);
      $(loaderImg).appendTo(loader);
      $(loader).appendTo(overlay);
      $('body').append(overlay);
    }
  }

  /**
   * Create and append alerts container to the body.
   */
  window.trackmageAlerts = function trackmageAlerts() {
    if ($('#trackmage-alerts').length === 0) {
      let alerts = $('<div></div>').attr('id', 'trackmage-alerts');

      $('body').append(alerts);
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

  window.trackmageAlert = function trackmageAlert(title, paragraph, type = 'default', autoClose = true) {
    if ($('#trackmage-alerts').length === 0) {
      trackmageAlerts();
    }

    let alert = $(`
      <div class="trackmage-alert trackmage-alert--${type}">
        <div class="trackmage-alert__content">
          <strong class="trackmage-alert__title">${title}</strong>
          <p class="trackmage-alert__paragraph">${paragraph}</p>
        </div>
        <div class="trackmage-alert__close">
          <span class="dashicons dashicons-dismiss"></span>
        </div>
      </div>
    `);

    if (autoClose) {
      setTimeout(function () {
        $(alert).slideUp(100);
      }, 10000);
    }

    $('#trackmage-alerts').append(alert);

    $(alert).find('.trackmage-alert__close span').on('click', function () {
      $(alert).slideUp(100);
    });
  }

  /**
   * On document ready.
   */
  $(document).ready(() => {
    /**
     * Select2.
     */
    if (typeof selectWoo === 'function') {
      $('select[name="trackmage_provider"]').selectWoo();
    }

    /**
     * Disable input fields, buttons and links inside disabled sections.
     */
    $('.wrap.trackmage .section.disabled').each(function (e) {
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

    /**
     * Edit status.
     */
    $('#statusManager .row-actions .edit-status').on('click', function (e) {
      let tbody = $(this).closest('tbody');
      let row = $(this).closest('tr');
      let isCustom = $(row).data('status-is-custom');
      let name = $(row).data('status-name');
      let currentSlug = $(row).data('status-slug');
      let slug = $(row).data('status-slug');
      let alias = $(row).data('status-alias');

      // Hide the selected row,
      // toggle hidden rows
      // and remove any other open edit row if any.
      $(tbody).children('tr.hidden').removeClass('hidden');
      $(row).addClass('hidden');
      $(tbody).children('.adjust-odd-even').remove();
      $(tbody).children('.inline-edit-row').remove();

      // Append edit row.
      let editRow = $(`
        <tr class="hidden adjust-odd-even"></tr>
        <tr id="edit-${slug}" class="inline-edit-row">
          <td colspan="4" class="colspanchange">
            <fieldset class="inline-edit-col-left">
              <legend class="inline-edit-legend">${params.messages.edit}</legend>
              <div class="inline-edit-col">
                <label>
                  <span class="title">${params.messages.name}</span>
                  <span class="input-text-wrap"><input type="text" name="status_name" value="" /></span>
                </label>
                <label>
                  <span class="title">${params.messages.slug}</span>
                  <span class="input-text-wrap"><input type="text" name="status_slug" value="" /></span>
                </label>
                <label>
                  <span class="title">${params.messages.alias}</span>
                  <select name="status_alias">
                    <option value="">${params.messages.noSelect}</option>
                    ${Object.keys(params.aliases).map(key => `<option value="${key}">${params.aliases[key]}</option>`).join('')}
                  </select>
                </label>
              </div>
            </fieldset>
            <div class="submit inline-edit-save">
              <button type="button" class="button cancel alignleft">${params.messages.cancel}</button>
              <button type="button" class="button button-primary save alignright">${params.messages.update}</button>
              <span class="spinner"></span>
              <br class="clear">
            </div>
          </div>
          </td>
        </tr>
      `);

      $(editRow).insertAfter(row);

      // Current values.
      $(editRow).find('input[name="status_name"]').val(name);
      $(editRow).find('input[name="status_slug"]').val(slug);
      $(editRow).find('select[name="status_alias"]').val(alias);

      // Disable slug field if not a custom status.
      if (isCustom != 1) {
        $(editRow).find('input[name="status_slug"]').prop('disabled', true);
      }

      // On cancel.
      $(editRow).find('button.cancel').on('click', function () {
        $(editRow).remove();
        $(row).removeClass('hidden');
      });

      // On save.
      $(editRow).find('button.save').on('click', function () {
        let save = $(this);
        let name = $(editRow).find('[name="status_name"]');
        let slug = $(editRow).find('[name="status_slug"]');
        let alias = $(editRow).find('[name="status_alias"]');

        // Request data.
        let data = {
          'action': 'trackmage_status_manager_save',
          'name': $(name).val(),
          'currentSlug': currentSlug,
          'slug': $(slug).val(),
          'alias': $(alias).val(),
          'isCustom': isCustom,
        };

        // Response message.
        let message = '';

        $.ajax({
          url: params.ajaxUrl,
          method: 'post',
          data: data,
          beforeSend: function () {
            trackmageToggleSpinner(save, 'activate');
            trackmageToggleFormElement(save, 'disable');
          },
          success: function (response) {
            trackmageToggleSpinner(save, 'deactivate');
            trackmageToggleFormElement(save, 'enable');

            if (response.data.status === 'success') {
              updateRow(response.data.result.name, response.data.result.slug, response.data.result.alias);
              message = params.messages.successUpdateStatus;
              $(editRow).remove();
              $(row).removeClass('hidden').effect('highlight', {color: '#c3f3d7'}, 500);
            } else if (response.data.errors) {
              message = response.data.errors.join(' ');
              $(editRow).removeClass('hidden').effect('highlight', {color: '#ffe0e3'}, 500);
            } else {
              message = params.messages.unknownError;
            }

            // Response notification.
            trackmageAlert(params.messages.updateStatus, message, response.data.status, true);
          },
          error: function () {
            trackmageToggleSpinner(save, 'deactivate');
            trackmageToggleFormElement(save, 'enable');

            message = params.messages.unknownError;

            // Response notification.
            trackmageAlert(params.messages.updateStatus, message, response.data.status, true);
          }
        });
      });

      function updateRow(name, slug, alias) {
        $(row).find('[data-update-status-name]').html(name);
        $(row).data('status-name', name);

        $(row).find('[data-update-status-slug]').html(slug);
        $(row).data('status-slug', slug);

        $(row).find('[data-update-status-alias]').html(params.aliases[alias] ? params.aliases[alias] : '');
        $(row).data('status-alias', alias);
      }
    });

    // Append overlay.
    trackmageOverlay();

    // Append alerts container.
    trackmageAlerts();

    // Test credentials.
    $('#trackmage-settings-general #testCredentials').on('click', function (e) {
      let testCredentials = $(this);
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
        url: params.ajaxUrl,
        method: 'post',
        data: data,
        beforeSend: function () {
          trackmageToggleSpinner(testCredentials, 'activate');
          trackmageToggleFormElement(testCredentials, 'disable');
        },
        success: function (response) {
          trackmageToggleSpinner(testCredentials, 'deactivate');
          trackmageToggleFormElement(testCredentials, 'enable');

          if (response.data.status === 'success') {
            message = params.messages.successValidKeys;
          } else if (response.data.errors) {
            message = response.data.errors.join(' ');
          } else {
            message = params.messages.unknownError;
          }

          // Response notification.
          trackmageAlert(params.messages.testCredentials, message, response.data.status, true);
        },
        error: function () {
          trackmageToggleSpinner(testCredentials, 'deactivate');
          trackmageToggleFormElement(testCredentials, 'enable');

          message = params.messages.unknownError;

          // Response notification.
          trackmageAlert(params.messages.testCredentials, message, response.data.status, true);
        }
      });
    });
  });
})(jQuery, window, document);
