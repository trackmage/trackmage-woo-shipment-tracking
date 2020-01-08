(($, window, document, undefined) => {
  const params = {
    wizard: trackmageWizard
  };

  let wizard = {
    _instanse: null,
    _container: '',
    _progressbar: '#progressbar',
    _steps: [],
    _stepsContainer: '.steps-container',
    _stepContainerClass: 'tab-pane',
    _loader: '.wizard-loader',
    _errorsContainer: '.wizard-header',
    _validators: [],
    init : function(container, steps){
      let self = this;
      self._container = container;
      self._steps = steps;
      self._buildHtml();
      self._instanse = $(this._container).bootstrapWizard({
        'tabClass': 'nav nav-pills',
        'nextSelector': '.btn-next',
        'previousSelector': '.btn-previous',
        'finishSelector': '.btn-finish',

        onFinish: function(tab, navigation, index) {
          let $step = $(tab).attr('id');
          if(typeof self._validators[$step] !== "undefined" && !$('#step-' + $step).find('form').eq(0).valid()) {
            self._validators[$step].focusInvalid();
            return false;
          }
            if ($(tab).hasClass('changed') || !$(tab).hasClass('completed')) {
              $(self._loader).show();
              let $data = $('#step-' + $step).find('input, select').serialize();
              $data += "&step="+$step;
              self.processStep($data, $step ,function(){
                $(self._container).bootstrapWizard('finish');
              });
              return false;
            } else {
              window.location.href = params.wizard.urls.completed;
            }
        },

        onNext: function(tab, navigation, index) {
          let $step = $(tab).attr('id');
          if(typeof self._validators[$step] !== "undefined" && !$('#step-' + $step).find('form').eq(0).valid()) {
            self._validators[$step].focusInvalid();
            return false;
          }
            if ($(tab).hasClass('changed') || !$(tab).hasClass('completed')) {
              $(self._loader).show();
              let $data = $('#step-' + $step).find('input, select').serialize();
              $data += "&step="+$step;
              self.processStep($data, $step , function(){
                $(self._container).bootstrapWizard('next');
              });
              return false;
            } else {
              $(tab).addClass('completed');
              return true;
            }
        },

        onInit : function(tab, navigation, index){

        },

        onTabClick : function(tab, navigation, index){
            return false;
        },

        onTabShow: function(tab, navigation, index) {
          if($(tab).hasClass('reload'))
            self.getStepContent(tab);
          navigation.find('li').removeClass('active');
          $(tab).addClass('active');
          let $total = navigation.find('li').length;
          let $current = index+1;

          let $wizard = navigation.closest('.wizard-card');

          // If it's the last tab then hide the last button and show the finish instead
          if($current >= $total) {
            $($wizard).find('.btn-next').hide();
            $($wizard).find('.btn-finish').show();
          }else if($current == 1){
            $($wizard).find('.btn-previous').hide();
          } else {
            $($wizard).find('.btn-next').show();
            $($wizard).find('.btn-previous').show();
            $($wizard).find('.btn-finish').hide();
          }
        }
      }).data('bootstrapWizard');
    },

    _buildHtml : function () {
      let self = this;
      $(self._progressbar).html('');
      $(self._stepsContainer).html('');
      $.each(this._steps, function(idx, step){
          let _btn = $('<li><a data-toggle="tab" href="#step-'+step.code+'">'+step.title+'</a></li>').attr('id',step.code).addClass('reload');
          let _step = $('<div></div>').addClass(self._stepContainerClass).attr('id','step-'+step.code).html('Step '+step.title);
          $(self._progressbar).append(_btn);
          $(self._stepsContainer).append(_step);
      })
    },

    getStepContent : function(tab){
      let self = this;
      let step = $(tab).attr('id');
      let data = {
        action: "trackmage_wizard_get_step_content",
        step: step
      };
      $.ajax({
        url: params.wizard.urls.ajax,
        method: "post",
        data: data,
        beforeSend: function () {
          $(self._loader).show();
        },
        success: function success(response) {
          if (response.success) {
            $('#step-'+step).html(response.data.html);
            $('#step-'+step).find('input, select').on('paste, change', function(){
                $(tab).addClass('changed');
            });
            let required = $('#step-'+step).find('.required');
            if(required.length > 0){
              let rules = {};
              let messages = {};
              $.each(required, function(idx,el){
                let name = $(el).attr('name');
                rules[name] = {required: true};
                messages[name] = {required: "Field is required!"};
              });
              self._validators[step] = $('#step-'+step).find('form').eq(0).validate({
                  rules: rules,
                  messages: messages,
                  onsubmit: false,
                  errorElement: "div",
                  errorPlacement: function(error, element) {
                    error.appendTo(element.parent()).addClass('invalid-feedback');
                  },
                  highlight: function ( element, errorClass, validClass ) {
                    $( element ).addClass('is-invalid');
                    $( element ).parents( "form" ).eq(0).addClass( "was-validated" );
                  },
                  unhighlight: function ( element, errorClass, validClass ) {
                    $( element ).removeClass('is-invalid');
                    $( element ).parents( "form" ).eq(0).addClass( "was-validated" );
                  }
                });
            }
            let wooSelects = $('#step-'+step).find('.woo-select');
            $.each(wooSelects, function(idx, ws){
              self._initSelectWoo(ws);
            });
            $(tab).removeClass('reload');
          }
          $(self._loader).hide();
        },
        error: function error() {
          $(self._loader).hide();
          $(self._errorsContainer).html('');
          $('<div></div>').addClass('alert').addClass('alert-danger').html(params.wizard.i18n.unknownError).attr('role','alert').appendTo($(self._errorsContainer));
        },
      });
    },

    processStep: function($data, $step, cb){
      let self = this;
      $data += "&action=trackmage_wizard_process_step";
      console.log($data);
      $.ajax({
        url: params.wizard.urls.ajax,
        method: "post",
        data: $data,
        beforeSend: function beforeSend() {

        },
        success: function success(response) {
          if (response.success) {
            $('#'+$step).addClass('completed').removeClass('changed');
            //self._instanse.nextTab();
            cb();
          }else{
            if(response.data.status == 'error'){
              $('#'+$data.step).removeClass('completed');
              $(self._errorsContainer).html('');
              $.each(response.data.errors, function(idx, err){
                $('<div></div>').addClass('alert').addClass('alert-danger').html(err).attr('role','alert').appendTo($(self._errorsContainer));
              });
              $(self._loader).hide();
            }
          }
        },
        error: function error(jqXHR, textStatus, errorThrown) {
          $(self._loader).hide();
          $(self._errorsContainer).html('');
          $('<div></div>').addClass('alert').addClass('alert-danger').html(params.wizard.i18n.unknownError).attr('role','alert').appendTo($(self._errorsContainer));
        }
      });
    },
    queryStringToJSON: function($str) {
      let pairs = $str.split('&');

      let result = {};
      pairs.forEach(function(pair) {
        pair = pair.split('=');
        result[pair[0]] = decodeURIComponent(pair[1] || '');
      });

      return JSON.parse(JSON.stringify(result));
    },

    _initSelectWoo: function($el){
      $($el).selectWoo({
        width: "100%",
        ajax: {
          url: params.wizard.urls.ajax,
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
    }

};

  wizard.init('#wizard', params.wizard.steps);

})(jQuery, window, document);
