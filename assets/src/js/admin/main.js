(($, window, document, undefined) => {
  const params = {
    main: trackmageAdmin
  };

  /**
   * Activate/deactivate spinner.
   *
   * @param {object} el - Spinner activator.
   * @param {string} action - Activate/deactivate.
   */
  window.trackmageToggleSpinner = function trackmageToggleSpinner(el, action) {
    if (action === "activate") {
      $(el)
        .siblings(".spinner")
        .addClass("is-active");
    } else if (action === "deactivate") {
      $(el)
        .siblings(".spinner")
        .removeClass("is-active");
    }
  };

  /**
   * Enable/Disable form element.
   *
   * @param {object} el - The element object.
   * @param {string} action - Enable/disable.
   */
  window.trackmageToggleFormElement = function trackmageToggleFormElement(
    el,
    action
  ) {
    if (action === "enable" || action === "disable") {
      $(el).prop("disabled", action === "enable" ? false : true);
    }
  };

  /**
   * Create and append alerts container to the body.
   */
  window.trackmageAlerts = function trackmageAlerts() {
    if ($("#trackmage-alerts").length === 0) {
      let alerts = $("<div></div>").attr("id", "trackmage-alerts");

      $("body").append(alerts);
    }
  };

  window.trackmageAlert = function trackmageAlert(
    title,
    paragraph,
    type = "default",
    autoClose = true
  ) {
    if ($("#trackmage-alerts").length === 0) {
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
      setTimeout(function() {
        $(alert).slideUp(100);
      }, 10000);
    }

    $("#trackmage-alerts").append(alert);

    $(alert)
      .find(".trackmage-alert__close span")
      .on("click", function() {
        $(alert).slideUp(100);
      });
  };

  window.trackmageBlockUi = function trackmageBlockUi(el) {
    $(el).block({
      message: null,
      overlayCSS: {
        background: "#fff",
        opacity: 0.6
      }
    });
  };

  window.trackmageUnblockUi = function trackmageUnblockUi(el) {
    $(el).unblock();
  };

  window.trackmageConfirmDialog = function(container, okBtnCallback = null, dialogTitle = 'Confirm Changes', okBtnTitle = (params.main.ok || 'OK')){
    let defer = $.Deferred();
    let buttons = {};
    buttons[okBtnTitle] = function() {
      let canClose = (okBtnCallback !== null)? okBtnCallback() : true;
      if(canClose){
        defer.resolve("yes");
        $(this).attr('yesno', true);
        $(this).dialog("close");
      }
    };
    buttons[params.main.cancel || "Cancel"] = function() {
      $(this).dialog('close');
    };
    $(container).dialog({
      title: dialogTitle,
      dialogClass: 'wp-dialog',
      autoOpen: true,
      draggable: false,
      width: $(window).width() > 600 ? 600 : $(window).width(),
      height: 'auto',
      modal: true,
      resizable: false,
      closeOnEscape: true,
      position: {
        my: "center",
        at: "center",
        of: window
      },
      buttons: buttons,
      open: function () {
        $('.ui-widget-overlay').bind('click', function () {
          $(container).dialog('close');
        })
      },
      create: function () {
        $('.ui-dialog-titlebar-close').addClass('ui-button');
      },
      close: function () {
        if ($(this).attr('yesno') === undefined) {
          defer.resolve("no");
        }
        $(this).dialog('destroy');
      },
    });
    return defer.promise();
  };

  /**
   * On document ready.
   */
  $(document).ready(() => {
    // Append alerts container.
    trackmageAlerts();
  });
})(jQuery, window, document);
