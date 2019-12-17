<!DOCTYPE html>
<!--[if IE 9]>
		<html class="ie9" <?php language_attributes(); ?> >
		<![endif]-->
<!--[if !(IE 9) ]><!-->
<html <?php language_attributes(); ?>>
<!--<![endif]-->
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title><?php echo esc_html( $wizard_title ); ?></title>
    <?php
     wp_print_head_scripts();
    ?>
</head>
<body class="wp-admin wp-core-ui">
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-11 col-sm-9 col-md-7 col-lg-6 col-xl-5 text-center p-0 mt-3 mb-2">
            <div class="card px-0 pt-4 pb-0 mt-3 mb-3">
                <div class="card-header">
                    <img class="img-fluid mx-auto d-block main-logo"  src="<?php echo TRACKMAGE_URL . 'assets/dist/images/trackmage_logo_big.png'?>" alt="><?php echo esc_html( $wizard_title ); ?>">
                    <h2 id="heading"><?php echo esc_html( $wizard_title ); ?></h2>
                    <p>Complete all steps to get plugin works properly</p>
                </div>
                <div class="card-body border shadow p-3 mb-5">
                    <div class="wizard-container">
                        <div class="card wizard-card wizard-container" id="wizard" style="display: block;">
                                <div class="wizard-navigation">
                                    <ul id="progressbar">
                                    </ul>
                                </div>
                                <div class="wizard-header">
                                </div>
                                <div class="tab-content steps-container">

                                </div>
                                <div class="wizard-footer">
                                    <div class="pull-right">
                                        <button type="button" class="btn btn-next btn-primary next action-button" name="next"><?php echo __('Next', 'trackmage');?></button>
                                        <button type="button" class="btn btn-finish  btn-fill btn-wd btn-info action-button" name="finish" style="display: none;"><?php echo __('Finish', 'trackmage');?></button>
                                    </div>
                                    <div class="pull-left">
                                        <button type="button" class="btn btn-primary btn-previous previous action-button-previous disabled" name="previous"><?php echo __('Previous', 'trackmage');?></button>
                                    </div>
                                    <div class="clearfix"></div>
                                </div>
                                <div class="wizard-loader" style="display: none;"><div class="trackmage-loader"></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php

wp_print_footer_scripts();

?>
</body>
</html>


