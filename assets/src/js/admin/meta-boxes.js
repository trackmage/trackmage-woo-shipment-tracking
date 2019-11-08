(($, window, document, undefined) => {
  const params = {
    main: window.trackmageAdmin,
    metaBoxes: window.trackmageAdminMetaBoxes
  };

  /**
   * Init selectWoo on a `<select>` element to get order items.
   *
   * @param {object} el The select element.
   * @param {number} orderId Order ID.
   */
  function initSelectWooOrderItems(el, orderId) {
    $(el).selectWoo({
      width: "100%",
      ajax: {
        url: params.main.urls.ajax,
        method: "post",
        dataType: "json",
        delay: 250,
        data: function(p) {
          return {
            term: p.term,
            action: "trackmage_get_order_items",
            orderId: orderId
          };
        },
        processResults: function(data) {
          return {
            results: data.map(item => {
              return {
                id: item.id,
                text: item.name
              };
            })
          };
        }
      }
    });
  }

  /**
   * Toggle action group in the meta box.
   *
   * @param {string} group The id of the group to be displayed.
   * @return {object} The element of the displayed group.
   */
  function toggleActionGroup(group) {
    // Hide all other groups and the associated elements.
    $("#trackmage-shipment-tracking .actions__action-group").hide();
    $("#trackmage-shipment-tracking [data-action-group]").hide();

    // Display the desired group and its associated elements.
    const groupEl = $(
      `#trackmage-shipment-tracking .actions__action-group--${group}`
    );
    $(groupEl).show();
    $(`#trackmage-shipment-tracking [data-action-group=${group}]`).show();

    // Return the element of the displayed group.
    return groupEl;
  }

  // Add items row.
  $(document).on(
    "click",
    "#trackmage-shipment-tracking .items__btn-add-row",
    function(e) {
      e.preventDefault();

      const btnAddRow = $(this);

      // Get row HTML.
      $.ajax({
        url: params.main.urls.ajax,
        method: "post",
        data: {
          action: "trackmage_get_view",
          path: "meta-boxes/order-add-shipment-items-row.php"
        },
        success: function(response) {
          const row = $(response.data.html);

          // Show the delete icon.
          $(row)
            .find(".items__delete")
            .css("display", "block");

          // Init selectWoo.
          initSelectWooOrderItems(
            $(row).find('[name="order_item_id"]'),
            params.metaBoxes.orderId
          );

          // Append row.
          $(btnAddRow).before(row);
        },
        error: function(response) {
          trackmageAlert(
            params.main.i18n.failure,
            response.data.message,
            "failure",
            true
          );
        }
      });
    }
  );

  // Remove items row.
  $(document).on(
    "click",
    "#trackmage-shipment-tracking .items__row .items__delete",
    function(e) {
      e.preventDefault();
      const row = $(this).closest(".items__row");
      $(row).remove();
    }
  );

  /*
   * Edit shipment.
   */
  $(document).on(
    "click",
    "#trackmage-shipment-tracking .shipment__actions__action--edit",
    function(e) {
      const shipment = $(this).closest(".shipment");
      const shipmentId = $(shipment).data("id");

      // Show the edit shipment form.
      $("#trackmage-shipment-tracking .edit-shipment").show();

      // Toggle action group.
      const actionGroup = toggleActionGroup("edit");

      // On cancel.
      $(actionGroup)
        .off("click", ".btn-cancel")
        .on("click", ".btn-cancel", e => {
          e.preventDefault();
          toggleActionGroup("default");
          $("#trackmage-shipment-tracking .edit-shipment").hide();
        });

      // On save.
      $(document)
        .off(
          "click",
          "#trackmage-shipment-tracking .actions__action-group--edit .btn-save"
        )
        .on(
          "click",
          "#trackmage-shipment-tracking .actions__action-group--edit .btn-save",
          function(e) {
            e.preventDefault();

            let items = [];
            $(
              "#trackmage-shipment-tracking .edit-shipment .items__rows .items__row"
            ).each(function() {
              const id = $(this)
                .find('[name="id"]')
                .val();
              const orderItemId = $(this)
                .find('[name="order_item_id"]')
                .val();
              const qty = $(this)
                .find('[name="qty"]')
                .val();
              items.push({
                id: id,
                order_item_id: orderItemId,
                qty: qty
              });
            });

            // Request data.
            const data = {
              action: "trackmage_update_shipment",
              security: params.metaBoxes.nonces.updateShipment,
              orderId: params.metaBoxes.orderId,
              id: shipmentId,
              trackingNumber: $(
                '#trackmage-shipment-tracking .edit-shipment [name="tracking_number"]'
              ).val(),
              carrier: $(
                '#trackmage-shipment-tracking .edit-shipment [name="carrier"]'
              ).val(),
              items: items
            };

            $.ajax({
              url: params.main.urls.ajax,
              method: "post",
              data: data,
              beforeSend: function() {
                trackmageBlockUi($("#trackmage-shipment-tracking .inside"));
              },
              success: function(response) {
                const alert = {
                  title: response.success
                    ? params.main.i18n.success
                    : params.main.i18n.failure,
                  message: response.data.message
                    ? response.data.message
                    : !response.success
                    ? params.main.i18n.unknownError
                    : "",
                  type: response.success ? "success" : "failure"
                };

                trackmageAlert(alert.title, alert.message, alert.type, true);

                // Re-load the meta box.
                $("#trackmage-shipment-tracking .inside").html(
                  response.data.html
                );
              },
              error: function(response) {
                trackmageAlert(
                  params.main.i18n.failure,
                  response.data.message,
                  "failure",
                  true
                );
              },
              complete: function() {
                trackmageUnblockUi($("#trackmage-shipment-tracking .inside"));
              }
            });
          }
        );

      // Get the edit shipment form.
      $.ajax({
        url: params.main.urls.ajax,
        method: "post",
        data: {
          action: "trackmage_edit_shipment",
          id: shipmentId,
          security: params.metaBoxes.nonces.editShipment,
          orderId: params.metaBoxes.orderId
        },
        success: function(response) {
          if (!response.success) {
            toggleActionGroup("default");
            return;
          }

          const html = $(response.data.html);
          const trackingNumber = response.data.trackingNumber;
          const carrier = response.data.carrier;
          const items = response.data.items;

          // Show the delete icon for all rows except the first one.
          $(html)
            .find(".items__rows .items__row:not(:first-of-type) .items__delete")
            .css("display", "block");

          // Append the HTML.
          $("#trackmage-shipment-tracking .edit-shipment").html(html);

          // Init selectWoo and set values.
          $(html)
            .find('[name="carrier"]')
            .selectWoo({
              width: "100%"
            })
            .val(carrier)
            .trigger("change");

          let index = 1;
          items.forEach(item => {
            const itemEl = $(
              `#trackmage-shipment-tracking .edit-shipment .items__rows .items__row:nth-of-type(${index})`
            );
            const itemIdEl = $(itemEl).find('[name="id"]');
            const itemProductEl = $(itemEl).find('[name="order_item_id"]');
            const itemQtyEl = $(itemEl).find('[name="qty"]');

            $(itemIdEl).val(item.id);
            $(itemQtyEl).val(item.qty);

            // Select product item.
            const selectedProduct = new Option(
              item.name,
              item.order_item_id,
              true,
              true
            );
            $(itemProductEl)
              .append(selectedProduct)
              .trigger("change");
            initSelectWooOrderItems(itemProductEl, params.metaBoxes.orderId);

            index++;
          });
        }
      });
    }
  );

  /*
   * Add new shipment.
   */
  $(document).on(
    "click",
    "#trackmage-shipment-tracking .actions__action-group--default .btn-new",
    e => {
      e.preventDefault();

      // Show the add shipment form.
      $("#trackmage-shipment-tracking .add-shipment").show();

      // Toggle action group.
      const actionGroup = toggleActionGroup("new");

      // Listen to add all order items button.
      $(
        '#trackmage-shipment-tracking .add-shipment [name="add_all_order_items"]'
      )
        .off("change")
        .on("change", function(e) {
          const checked = $(this).is(":checked");
          const itemRows = $(
            "#trackmage-shipment-tracking .add-shipment .items__rows"
          );
          if (checked) {
            $(itemRows).hide();
          } else {
            $(itemRows).show();
          }
        })
        .trigger("click");

      // Init selectWoo.
      $(
        '#trackmage-shipment-tracking .add-shipment [name="carrier"]'
      ).selectWoo({
        width: "100%"
      });
      initSelectWooOrderItems(
        $('#trackmage-shipment-tracking .add-shipment [name="order_item_id"]'),
        params.metaBoxes.orderId
      );

      // On cancel.
      $(actionGroup)
        .off("click", ".btn-cancel")
        .on("click", ".btn-cancel", e => {
          e.preventDefault();
          toggleActionGroup("default");
          $("#trackmage-shipment-tracking .add-shipment").hide();
        });

      // On add shipment.
      $(document)
        .off(
          "click",
          "#trackmage-shipment-tracking .actions__action-group--new .btn-add-shipment"
        )
        .on(
          "click",
          "#trackmage-shipment-tracking .actions__action-group--new .btn-add-shipment",
          function(e) {
            e.preventDefault();

            let items = [];
            $(
              "#trackmage-shipment-tracking .add-shipment .items__rows .items__row"
            ).each(function() {
              const orderItemId = $(this)
                .find('[name="order_item_id"]')
                .val();
              const qty = $(this)
                .find('[name="qty"]')
                .val();
              items.push({
                id: '',
                order_item_id: orderItemId,
                qty: qty
              });
            });

            // Request data.
            const data = {
              action: "trackmage_add_shipment",
              security: params.metaBoxes.nonces.addShipment,
              orderId: params.metaBoxes.orderId,
              trackingNumber: $(
                '#trackmage-shipment-tracking .add-shipment [name="tracking_number"]'
              ).val(),
              carrier: $(
                '#trackmage-shipment-tracking .add-shipment [name="carrier"]'
              ).val(),
              addAllOrderItems: $(
                '#trackmage-shipment-tracking .add-shipment [name="add_all_order_items"]'
              ).is(":checked"),
              items: items
            };

            $.ajax({
              url: params.main.urls.ajax,
              method: "post",
              data: data,
              beforeSend: function() {
                trackmageBlockUi($("#trackmage-shipment-tracking .inside"));
              },
              success: function(response) {
                const alert = {
                  title: response.success
                    ? params.main.i18n.success
                    : params.main.i18n.failure,
                  message: response.data.message
                    ? response.data.message
                    : !response.success
                    ? params.main.i18n.unknownError
                    : "",
                  type: response.success ? "success" : "failure"
                };

                trackmageAlert(alert.title, alert.message, alert.type, true);

                // Re-load the meta box.
                $("#trackmage-shipment-tracking .inside").html(
                  response.data.html
                );
              },
              error: function(response) {
                trackmageAlert(
                  params.main.i18n.failure,
                  response.data.message,
                  "failure",
                  true
                );
              },
              complete: function() {
                trackmageUnblockUi($("#trackmage-shipment-tracking .inside"));
              }
            });
          }
        );
    }
  );

  /*
   * Delete shipment.
   */
  $(document).on(
    "click",
    "#trackmage-shipment-tracking .shipment__actions__action--delete",
    function (e) {
      e.preventDefault();

      if (confirm(params.metaBoxes.i18n.confirmDeleteShipment)) {
        const shipment = $(this).closest(".shipment");
        const shipmentId = $(shipment).data("id");

        $.ajax({
          url: params.main.urls.ajax,
          method: "post",
          data: {
            action: 'trackmage_delete_shipment',
            security: params.metaBoxes.nonces.deleteShipment,
            orderId: params.metaBoxes.orderId,
            id: shipmentId,
          },
          beforeSend: function() {
            trackmageBlockUi($("#trackmage-shipment-tracking .inside"));
          },
          success: function(response) {
            const alert = {
              title: response.success
                ? params.main.i18n.success
                : params.main.i18n.failure,
              message: response.data.message
                ? response.data.message
                : !response.success
                ? params.main.i18n.unknownError
                : "",
              type: response.success ? "success" : "failure"
            };

            trackmageAlert(alert.title, alert.message, alert.type, true);

            // Re-load the meta box.
            $("#trackmage-shipment-tracking .inside").html(
              response.data.html
            );
          },
          error: function(response) {
            trackmageAlert(
              params.main.i18n.failure,
              response.data.message,
              "failure",
              true
            );
          },
          complete: function() {
            trackmageUnblockUi($("#trackmage-shipment-tracking .inside"));
          }
        });
      }
    }
  );
})(jQuery, window, document);
