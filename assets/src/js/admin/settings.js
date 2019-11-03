(($, window, document, undefined) => {
  const params = {
    main: trackmageAdmin,
    settings: trackmageAdminSettings
  };

  let somethingChanged = false;

  let confirmDialog = function(container, okBtnCallback = null, dialogTitle = 'Confirm Changes', okBtnTitle = 'OK'){
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
      buttons["Cancel"] = function() {
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

  $(document).ready(function() {
    $('#general-settings-form input,  #general-settings-form select').on('change',function() {
      somethingChanged = true;
      $('#btn-save-form').removeClass('disabled').removeAttr('disabled');
    });
  });

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

  /**
   * Select field for Statuses
   */
  $("select#trackmage_sync_statuses").selectWoo({
    width: "350px",
    ajax: {
      url: params.main.urls.ajax,
      method: "post",
      dataType: "json",
      delay: 250,
      data: function(params) {
        return {
          term: params.term,
          action: "trackmage_get_order_statuses"
        };
      },
      processResults: function(data, params) {
        return {
          results: data.filter((s, index) => {
            let term = typeof params.term === "undefined" ? "" : params.term;
            if (
              term === "" ||
              (s.id.toLowerCase().includes(params.term.toLowerCase()) ||
                s.text.toLowerCase().includes(params.term.toLowerCase()))
            ) {
              return true;
            }

            return false;
          })
        };
      }
    }
  });

  /**
   * On form submit
   */
  $("form#general-settings-form").on('submit', function(e){

    let canSubmit = $(this).attr('cansubmit');
    if(canSubmit === 'true')
      return true;
    let form = $(this);
    let workspace = $("#trackmage_workspace").val();
    let sync_statuses = $('#trackmage_sync_statuses').val();
    let differences = sync_statuses.filter(value => -1 === params.settings.sync_statuses.indexOf(value));
    if(params.settings.workspace !== "0" && params.settings.workspace != workspace){ // check if workspace is changed
      confirmDialog(
        '#change-workspace-dialog',
        function(){
                      if(!$('#agree_to_change_cb').is(':checked')) {
                        $('#agree_to_change_cb').parent().addClass('error').find('p.description').show();
                        return false;
                      }
                      return true;
                    },
        'Confirm Workspace Change',
        'Apply')
        .then(function(yesno) {
          if(yesno === 'yes' ){
            form.attr('cansubmit',true).submit();
          } else {
            form.attr('cansubmit',false);
          }
        });
      return false;
    }else if(params.settings.workspace == "0" && workspace != "0" || differences.length > 0 && params.settings.workspace != "0"){ // check if workspace is set first time || sync statuses were changed and workspace is set
      confirmDialog(
        '#trigger-sync-dialog',
        function(){
          return true;
        },
        'Settings Save Confirmation',
        'Yes'
      ).then(function(yesno) {
        $('#trigger-sync').val((yesno === 'yes')?1:0);
        form.attr('cansubmit',true).submit();
      });
      return false;
    }
    return true;
  });

  $("button#btn-trigger-sync").on('click', function(e){
    confirmDialog(
      '#trigger-sync-dialog',
      function(){
        return true;
      },
      'Settings Save Confirmation',
      'Yes'
    ).then(function(yesno) {
      if(yesno == 'yes'){
        $('#trigger-sync').val("1");
        console.log($('#trigger-sync').val());
        $("form#general-settings-form").attr('cansubmit',true).submit();
      }else{
        return false;
      }
    });
    return false;
  });

  $('#change-workspace-dialog input[type=checkbox]').on('change', function(){
    let target = $(this).attr('rel');
    $(target).val($(this).is(':checked')?1:0);
  });

})(jQuery, window, document);
