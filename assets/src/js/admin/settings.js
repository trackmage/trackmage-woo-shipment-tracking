(($, window, document, undefined) => {
  const params = {
    main: trackmageAdmin,
    settings: trackmageAdminSettings
  };

  // Test credentials.
  $("#trackmage-settings-general #testCredentials").on("click", function (e) {
    let testCredentials = $(this);
    e.preventDefault();

    // Request data.
    let data = {
      action: "trackmage_test_credentials",
      clientId: $(
        '#trackmage-settings-general [name="trackmage_client_id"]'
      ).val(),
      clientSecret: $(
        '#trackmage-settings-general [name="trackmage_client_secret"]'
      ).val()
    };

    // Response message.
    let message = "";

    $.ajax({
      url: params.main.urls.ajax,
      method: "post",
      data: data,
      beforeSend: function () {
        trackmageToggleSpinner(testCredentials, "activate");
        trackmageToggleFormElement(testCredentials, "disable");
      },
      success: function (response) {
        console.log(response);
        trackmageToggleSpinner(testCredentials, "deactivate");
        trackmageToggleFormElement(testCredentials, "enable");

        if (response.data.status === "success") {
          message = params.settings.i18n.successValidKeys;
        } else if (response.data.errors) {
          message = response.data.errors.join(" ");
        } else {
          message = params.main.i18n.unknownError;
        }

        // Response notification.
        trackmageAlert(
          params.settings.i18n.testCredentials,
          message,
          response.data.status,
          true
        );
      },
      error: function () {
        trackmageToggleSpinner(testCredentials, "deactivate");
        trackmageToggleFormElement(testCredentials, "enable");

        message = params.main.i18n.unknownError;

        // Response notification.
        trackmageAlert(
          params.settings.i18n.testCredentials,
          message,
          response.data.status,
          true
        );
      }
    });
  });

  /**
   * Disable input fields, buttons and links inside disabled sections.
   */
  $(".wrap.trackmage .section.disabled").each(function (e) {
    let section = $(this);

    section.find("select, input, button").prop("disabled", true);
    section.find(".input-toggle").addClass("disabled");
    section.find("a, button, input").on("click", e => {
      e.preventDefault();
    });
  });

  /**
   * Adjust all toggle inputs.
   */
  $(".trackmage-input-toggle").each(function (e) {
    let toggle = $(this).children(".input-toggle");
    let input = $(this).children('input[type="checkbox"]');

    if (!input.is(":checked")) {
      toggle.addClass("off");
    }

    $(this).css("display", "inline-block");
  });

  /**
   * Toggle inputs.
   */
  $(".trackmage-input-toggle").on("click", function (e) {
    let toggle = $(this).children(".input-toggle");
    let input = $(this).children('input[type="checkbox"]');

    if (toggle.hasClass("disabled")) {
      return;
    }

    if (toggle.hasClass("off")) {
      toggle.removeClass("off");
      input.attr("checked", true);
    } else {
      toggle.addClass("off");
      input.attr("checked", false);
    }
  });
})(jQuery, window, document);
