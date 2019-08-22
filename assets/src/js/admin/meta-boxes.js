(($, window, document, undefined) => {
  const params = {
    main: trackmageAdmin,
    metaBoxes: trackmageAdminMetaBoxes
  };

  /**
   * Initializes select2 on a select element to get order items.
   *
   * @param {object} el The select element.
   * @param {number} orderId Order ID.
   */
  function initWooSelectOrderItems(el, orderId) {
    $(el).selectWoo({
      ajax: {
        url: params.main.urls.ajax,
        method: "post",
        dataType: "json",
        delay: 250,
        data: function(params) {
          return {
            term: params.metaBoxes.term,
            action: "trackmage_order_get_order_items",
            order_id: orderId
          };
        },
        processResults: function(data) {
          return {
            results: data
          };
        }
      }
    });
  }

  /**
   * Toggle shipment tracking action group.
   */
  function toggleActionGroup(group) {
    // Hide all other action groups.
    $(
      "#poststuff #trackmage-shipment-tracking .trackmage-shipment-tracking__actions .actions-group"
    ).hide();

    // Display the action group.
    $(
      "#poststuff #trackmage-shipment-tracking .trackmage-shipment-tracking__actions .actions__" +
        group
    ).show();
  }

  /**
   * Show the add tracking number form.
   */
  $(document).on(
    "click",
    "#trackmage-shipment-tracking .trackmage-shipment-tracking__actions .actions__default .new",
    function(e) {
      e.preventDefault();

      // Toggle action group.
      toggleActionGroup("new");

      // Init wooSelect.
      const el = $(
        '#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number [name="order_item_id"]'
      );
      const orderId = $(
        '#trackmage-shipment-tracking [name="trackmage_order_id"]'
      ).val();
      initWooSelectOrderItems(el, orderId);
      $(
        '#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number [name="carrier"]'
      ).selectWoo();

      // Show the add tracking number form.
      $(
        "#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number"
      ).show();
    }
  );

  $(
    "#poststuff #trackmage-shipment-tracking .trackmage-shipment-tracking__actions .actions__new .cancel"
  ).on("click", function(e) {
    e.preventDefault();

    toggleActionGroup("default");
    $(
      "#poststuff #trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number"
    ).hide();
  });

  $(
    '#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number [name="carrier"]'
  ).select2({});

  $(document).on(
    "click",
    "#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number #add-item-row",
    function(e) {
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
      const orderId = $(
        '#trackmage-shipment-tracking [name="trackmage_order_id"]'
      ).val();
      initWooSelectOrderItems(el, orderId);

      // Append row.
      $(
        "#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number .items"
      ).append(row);

      // On remove row.
      $(row)
        .find(".delete-item-row")
        .on("click", function(e) {
          e.preventDefault();
          $(row).remove();
        });
    }
  );

  /*
   * Add new tracking number.
   */
  $(document).on(
    "click",
    "#trackmage-shipment-tracking #add-tracking-number",
    function(e) {
      e.preventDefault();

      let items = [];
      $(
        "#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number .item-row"
      ).each(function() {
        const order_item_id = $(this)
          .find('[name="order_item_id"]')
          .val();
        const qty = $(this)
          .find('[name="qty"]')
          .val();
        items.push({
          order_item_id: order_item_id,
          qty: qty
        });
      });

      // Request data.
      const data = {
        action: "trackmage_order_add_tracking_number",
        security: params.metaBoxes.add_tracking_number_nonce,
        order_id: $(
          '#trackmage-shipment-tracking [name="trackmage_order_id"]'
        ).val(),
        tracking_number: $(
          '#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number [name="tracking_number"]'
        ).val(),
        carrier: $(
          '#trackmage-shipment-tracking .trackmage-shipment-tracking__add-tracking-number [name="carrier"]'
        ).val(),
        items: items
      };

      $.ajax({
        url: params.main.urls.ajax,
        method: "post",
        data: data,
        beforeSend: function() {
          blockUI($("#trackmage-shipment-tracking .inside"));
        },
        success: function(response) {
          const alert = {
            title: params.metaBoxes.i18n.addTrackingNumber,
            message: response.data.message
              ? response.data.message
              : !response.success
              ? params.main.i18n.unknownError
              : "",
            type: response.success ? "success" : "failure"
          };

          trackmageAlert(alert.title, alert.message, alert.type, false);

          // Re-load the meta box.
          $("#trackmage-shipment-tracking .inside").html(response.data.html);
        },
        error: function(response) {
          console.log(response);
          trackmageAlert(
            params.metaBoxes.i18n.addTrackingNumber,
            response.data.message,
            "failure",
            false
          );
        },
        complete: function() {
          unblockUI($("#trackmage-shipment-tracking .inside"));
        }
      });
    }
  );
})(jQuery, window, document);
