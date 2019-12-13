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
<div id="wizard">Wizard Steps!</div>
<div role="contentinfo" class="yoast-wizard-return-link-container">
    <a class="button yoast-wizard-return-link" href="<?php echo esc_url( $settings_url ); ?>">
        <span aria-hidden="true" class="dashicons dashicons-no"></span>
        <?php
        esc_html_e( 'Close the Wizard', 'trackmage' );
        ?>
    </a>
</div>
<?php
wp_print_media_templates();
wp_print_footer_scripts();

?>
</body>
</html>
