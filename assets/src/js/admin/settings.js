(($, window, document, undefined) => {
  const params = {
    main: trackmageAdmin,
    settings: trackmageAdminSettings
  };

  let somethingChanged = false;

  $(document).ready(function() {
    $('#trackmage_workspace,  #trackmage_sync_statuses, #trackmage_sync_start_date').on('change',function() {
      somethingChanged = true;
      $('#btn-save-form').removeClass('disabled').removeAttr('disabled');
    });
    checkBgStats();
  });

  function execCron() {
    $.ajax({
      url: '/wp-cron.php?doing_wp_cron',
      method: "get",
      success: function () {
        setTimeout(function () {
          checkBgStats();
        }, 3000);
      },
    });
  }

  function checkBgStats() {
    if ($('#sync-in-progress').length === 0) {
      return;
    }
    $.ajax({
      url: params.main.urls.ajax,
      method: "post",
      data: {action: 'trackmage_get_bg_stats'},
      success: function (response) {
        if (response.ordersCount !== 0) {
          $('#sync-status').text(`Remaining orders: ${response.ordersCount}`);
          execCron();
        } else {
          location.reload();
        }
      }
    });
  }

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
          $('#btn-save-form').removeClass('disabled').removeAttr('disabled');
          $("form#general-settings-form").prop('cansubmit',true);
        } else if (response.data.errors) {
          message = response.data.errors.join(" ");
          $("form#general-settings-form").prop('cansubmit',false);
          $('#btn-save-form').addClass('disabled').attr('disabled','disabled');
        } else {
          message = params.main.i18n.unknownError;
          $("form#general-settings-form").prop('cansubmit',false);
          $('#btn-save-form').addClass('disabled').attr('disabled','disabled');
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

        $("form#general-settings-form").prop('cansubmit',false);
        $('#btn-save-form').addClass('disabled').attr('disabled','disabled');
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

  // Reload workspaces.
  $("#trackmage-settings-general #reloadWorkspaces").on("click", function (e) {
    let reloadWorkspaces = $(this);
    let twSelect = $('#trackmage_workspace');
    e.preventDefault();

    // Request data.
    let data = {
      action: "trackmage_reload_workspaces",
    };
    // Response message.
    let message = "";

    $.ajax({
      url: params.main.urls.ajax,
      method: "post",
      data: data,
      beforeSend: function () {
        trackmageToggleSpinner(reloadWorkspaces, "activate");
        trackmageToggleFormElement(reloadWorkspaces, "disable");
      },
      success: function (response) {
        trackmageToggleSpinner(reloadWorkspaces, "deactivate");
        trackmageToggleFormElement(reloadWorkspaces, "enable");

        if (response.data.status === "success") {
          message = response.data.message;
          let oldValue = twSelect.val();
          twSelect.find('option[value!=0]').remove();
          $.each(response.data.workspaces,function(idx, workspace){
            let opt = $('<option></option>').text(workspace.title).val(workspace.id).attr('selected',oldValue === workspace.id);
            twSelect.append(opt);
          });
        } else if (response.data.errors) {
          message = response.data.errors.join(" ");
        }
        // Response notification.
        trackmageAlert(
          '',
          message,
          response.data.status,
          true
        );
      },
      error: function () {
        trackmageToggleSpinner(reloadWorkspaces, "deactivate");
        trackmageToggleFormElement(reloadWorkspaces, "enable");

        message = params.main.i18n.unknownError;

        // Response notification.
        trackmageAlert(
          '',
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
    let differences = sync_statuses? sync_statuses.filter(value => -1 === params.settings.sync_statuses.indexOf(value)): [];
    if(params.settings.workspace !== "0" && params.settings.workspace != workspace){ // check if workspace is changed
      window.trackmageConfirmDialog(
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
    }else if(params.settings.workspace == "0" && workspace != "0" || (differences.length > 0 || !sync_statuses) && params.settings.workspace != "0"){ // check if workspace is set first time || sync statuses were changed and workspace is set
      window.trackmageConfirmDialog(
        '#trigger-sync-dialog',
        function(){
          return true;
        },
        'Settings Save Confirmation',
        'Yes'
      ).then(function(yesno) {
        if(yesno == 'yes'){
          $('#trigger-sync').val("1");
          $("form#general-settings-form").attr('cansubmit',true).submit();
        }else{
          return false;
        }
      });
      return false;
    }
    return true;
  });

  $("button#btn-trigger-sync").on('click', function(e){
    window.trackmageConfirmDialog(
      '#trigger-sync-dialog',
      function(){
        return true;
      },
      'Settings Save Confirmation',
      'Yes'
    ).then(function(yesno) {
      if(yesno == 'yes'){
        $('#trigger-sync').val("1");
        $("form#general-settings-form").attr('cansubmit',true).submit();
      }else{
        return false;
      }
    });
    return false;
  });

  $("button#btn-reset-plugin").on('click', function(e){
    window.trackmageConfirmDialog(
      '#reset-dialog',
      function(){
          if(!$('#agree_reset').is(':checked')) {
            $('#agree_reset').parent().addClass('error').find('p.description').show();
            return false;
          }
          return true;
      },
      'Reset Plugin Confirmation',
      'Reset'
    ).then(function(yesno) {
      if(yesno == 'yes'){
        $('#reset-plugin').val("1");
        $("form#general-settings-form").attr('cansubmit',true).submit();
      }else{
        return false;
      }
    });
    return false;
  });

  $('#change-workspace-dialog input[type=checkbox], #reset-dialog input[type=checkbox]').on('change', function(){
    let target = $(this).attr('rel');
    $(target).val($(this).is(':checked')?1:0);
  });

  $('#agree_to_change_cb').on('change', function(){
    let workspace = $("#trackmage_workspace").val();
    $('#trigger_sync_cb').prop('disabled', !$(this).is(':checked') || workspace == 0);
    $('#delete_data_cb').prop('disabled', !$(this).is(':checked'));
  })

})(jQuery, window, document);
