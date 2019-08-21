(($, window, document, undefined) => {
  let params = trackmage_admin_params;

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

  function blockUI(el) {
    $(el).block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6
      }
    });
  }

  function unblockUI(el) {
    $(el).unblock();
  }

  /**
   * On document ready.
   */
  $(document).ready(() => {
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
    $(document).on('click', '#statusManager .row-actions .edit-status', function (e) {
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
      $(editRow).find('[name="status_name"]').val(name);
      $(editRow).find('[name="status_slug"]').val(slug);
      $(editRow).find('[name="status_alias"]').val(alias);

      // Disable slug field if not a custom status.
      if (isCustom != 1) {
        $(editRow).find('[name="status_slug"]').prop('disabled', true);
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

    /**
     * Add status.
     */
    $('#statusManager .add-status #addStatus').on('click', function (e) {
      let add = $(this);

      let name = $('#statusManager .add-status [name="status_name"]');
      let slug = $('#statusManager .add-status [name="status_slug"]');
      let alias = $('#statusManager .add-status [name="status_alias"]');

      // Request data.
      let data = {
        'action': 'trackmage_status_manager_add',
        'name': $(name).val(),
        'slug': $(slug).val(),
        'alias': $(alias).val(),
      };

      // Response message.
      let message = '';

      $.ajax({
        url: params.ajaxUrl,
        method: 'post',
        data: data,
        beforeSend: function () {
          trackmageToggleSpinner(add, 'activate');
          trackmageToggleFormElement(add, 'disable');
        },
        success: function (response) {
          trackmageToggleSpinner(add, 'deactivate');
          trackmageToggleFormElement(add, 'enable');

          if (response.data.status === 'success') {
            addRow(response.data.result.name, response.data.result.slug, response.data.result.alias);
            message = params.messages.successAddStatus;
          } else if (response.data.errors) {
            message = response.data.errors.join(' ');
          } else {
            message = params.messages.unknownError;
          }

          // Response notification.
          trackmageAlert(params.messages.addStatus, message, response.data.status, true);
        },
        error: function () {
          trackmageToggleSpinner(add, 'deactivate');
          trackmageToggleFormElement(add, 'enable');

          message = params.messages.unknownError;

          // Response notification.
          trackmageAlert(params.messages.addStatus, message, response.data.status, true);
        }
      });

      function addRow(name, slug, alias) {
        let statusManagerBody = $('#statusManager tbody');
        let row = `
          <tr id="status-${slug}" data-status-name="${name}" data-status-slug="${slug}" data-status-alias="${alias}" data-status-is-cusotm="1">
            <td>
              <span data-update-status-name>${name}</span>
              <div class="row-actions">
                <span class="inline"><button type="button" class="button-link edit-status">${params.messages.edit}</button> | </span>
                <span class="inline delete"><button type="button" class="button-link delete-status">${params.messages.delete}</button></span>
              </div>
            </td>
            <td><span data-update-status-slug>${slug}</span></td>
            <td colspan="2"><span data-update-status-alias>${params.aliases[alias]}</span></td>
          </tr>
        `;

        $(row).appendTo(statusManagerBody).effect('highlight', {color: '#c3f3d7'}, 500);
      }
    });

    /**
     * Delete status.
     */
    $(document).on('click', '#statusManager .row-actions .delete-status', function (e) {
      if (confirm(params.messages.confirmDeleteStatus)) {
        let row = $(this).closest('tr');
        let slug = $(row).data('status-slug');

        // Request data.
        let data = {
          'action': 'trackmage_status_manager_delete',
          'slug': slug,
        };

        // Response message.
        let message = '';

        $.ajax({
          url: params.ajaxUrl,
          method: 'post',
          data: data,
          beforeSend: function () {
          },
          success: function (response) {
            if (response.data.status === 'success') {
              deleteRow(response.data.result.slug);
              message = params.messages.successDeleteStatus;
            } else if (response.data.errors) {
              message = response.data.errors.join(' ');
            } else {
              message = params.messages.unknownError;
            }

            // Response notification.
            trackmageAlert(params.messages.deleteStatus, message, response.data.status, true);
          },
          error: function () {
            message = params.messages.unknownError;

            // Response notification.
            trackmageAlert(params.messages.deleteStatus, message, response.data.status, true);
          }
        });

        function deleteRow(slug) {
          $(row).effect('highlight', {color: '#ffe0e3'}, 500);
          setTimeout(() => {
            $(row).remove();
          }, 500);
        }
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

    $('select#trackmage_sync_statuses').selectWoo({
      width: '350px',
      ajax: {
        url: params.ajaxUrl,
        method: 'post',
        dataType: 'json',
        delay: 250,
        data: function (params) {
          return {
            term: params.term,
            action: 'trackmage_wooselect_order_statuses',
          };
        },
        processResults: function( data, params ) {
          return {
            results: data.filter((s, index) => {
              let term = typeof params.term === 'undefined' ? '' : params.term;
              if (
                term === '' ||
                (s.id.toLowerCase().includes(params.term.toLowerCase()) || s.text.toLowerCase().includes(params.term.toLowerCase()))
              ) {
                return true;
              }

              return false;
            })
          };
        },
      }
    });

    /**
     * Initializes select2 on a select element to get order items.
     *
     * @param {object} el The select element. 
     * @param {number} orderId Order ID. 
     */
    function initWooSelectOrderItems(el, orderId) {
      $(el).selectWoo({
        ajax: {
          url: params.ajaxUrl,
          method: 'post',
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
              term: params.term,
              action: 'trackmage_order_get_order_items',
              order_id: orderId,
            };
          },
          processResults: function(data) {
            return {
              results: data
            };
          },
        }
      });
    }

    /**
     * Toggle shipment tracking action group.
     */
    function toggleActionGroup(group) {
      // Hide all other action groups.
      $('#poststuff #trackmage-shipment-tracking .trackmage-shipment-tracking__actions .actions-group').hide();

      // Display the action group.
      $('#poststuff #trackmage-shipment-tracking .trackmage-shipment-tracking__actions .actions__' + group).show();
    }

    /**
     * Show the add tracking number form.
     */
    $(document).on('click', '#trackmage-shipment-tracking .trackmage-shipment-tracking__actions .actions__default .new', function(e) {
      e.preventDefault();

      // Toggle action group.
      toggleActionGroup('new');

      // Init wooSelect.
      const el = $('#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number [name="order_item_id"]');
      const orderId = $('#trackmage-shipment-tracking [name="trackmage_order_id"]').val();
      initWooSelectOrderItems(el, orderId);
      $('#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number [name="carrier"]').selectWoo();

      // Show the add tracking number form.
      $('#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number').show();
    });

    $('#poststuff #trackmage-shipment-tracking .trackmage-shipment-tracking__actions .actions__new .cancel').on('click', function(e) {
      e.preventDefault();

      toggleActionGroup('default');
      $('#poststuff #trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number').hide();
    });

    $('#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number [name="carrier"]').select2({
    });

    $(document).on('click', '#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number #add-item-row', function(e) {
      e.preventDefault();

      const row = $(`
        <div class="item-row">
          <select name="order_item_id" data-placeholder="Search for a product&hellip;"></select>
          <input type="number" name="qty" placeholder="Qty" />
          <a class="delete-item-row" href=""></a>
        </div>
      `);

      // Init wooSelect.
      const el = $(row).find('[name="order_item_id"]');
      const orderId = $('#trackmage-shipment-tracking [name="trackmage_order_id"]').val();
      initWooSelectOrderItems(el, orderId);

      // Append row.
      $('#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number .items').append(row);

      // On remove row.
      $(row).find('.delete-item-row').on('click', function(e) {
        e.preventDefault();
        $(row).remove();
      });
    });

    /*
     * Add new tracking number.
     */
    $(document).on('click', '#trackmage-shipment-tracking #add-tracking-number', function(e) {
      e.preventDefault();

      let items = [];
      $('#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number .item-row').each(function() {
        const order_item_id = $(this).find('[name="order_item_id"]').val();
        const qty = $(this).find('[name="qty"]').val(); 
        items.push( {
          order_item_id: order_item_id,
          qty: qty
        } );
      });

      // Request data.
      const data = {
        'action': 'trackmage_order_add_tracking_number',
        'security': params.add_tracking_number_nonce,
        'order_id': $('#trackmage-shipment-tracking [name="trackmage_order_id"]').val(),
        'tracking_number': $('#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number [name="tracking_number"]').val(),
        'carrier': $('#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number [name="carrier"]').val(),
        'items': items
      };

      $.ajax({
        url: params.ajaxUrl,
        method: 'post',
        data: data,
        beforeSend: function () {
          blockUI($('#trackmage-shipment-tracking .inside'));
        },
        success: function (response) {
          const alert = {
            title: params.messages.addTrackingNumber,
            message: response.data.message ? response.data.message : (!response.success ? params.messages.unknownError : ''),
            type: response.success ? 'success' : 'failure',
          };

          trackmageAlert(alert.title, alert.message, alert.type, false);

          // Re-load the meta box.
          $('#trackmage-shipment-tracking .inside').html(response.data.html);
        },
        error: function (response) {
          console.log(response);
          trackmageAlert(
            params.messages.addTrackingNumber,
            response.data.message,
            'failure',
            false
          );
        },
        complete: function() {
          unblockUI($('#trackmage-shipment-tracking .inside'));
        }
      });
    })
  });
})(jQuery, window, document);
