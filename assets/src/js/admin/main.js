(($, window, document, undefined) => {
  const params = {
    main: trackmageAdmin,
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

  function blockUI(el) {
    $(el).block({
      message: null,
      overlayCSS: {
        background: "#fff",
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
    // Append alerts container.
    trackmageAlerts();
  });
})(jQuery, window, document);
