<?php
/**
 * This creates settings area in payments gateway section
 *
 * @since      1.0.0
 */

namespace TrackMage;

abstract class Abstract_Settings_Page {

  /**
   * Holds the values to be used in the fields callbacks
   */
  protected static $page_title;
  protected static $menu_title;
  protected static $capability;
  protected static $menu_slug;

  public static $fields_id;


  /**
   * Abstract_Settings_Page constructor.
   */
  public function __construct() {
    add_action( 'admin_menu', array( $this, 'add_page' ) );
    add_action( 'admin_init', array( $this, 'setup_sections' ) );
    add_action( 'admin_init', array( $this, 'setup_fields' ) );
  }

  /**
   * Add options page
   */
  public function add_page() {
    // This page will be under "Settings"
    add_options_page(
      self::$page_title,
      self::$menu_title,
      self::$capability,
      self::$menu_slug,
      array( $this, 'create_admin_page' )
    );
  }

  public function create_admin_page() {
    ?> <div class="wrap">
      <h2><?php echo self::$page_title; ?></h2>
      <form method="post" action="options.php">
        <?php
        settings_fields( self::$fields_id );
        do_settings_sections( self::$fields_id );
        submit_button();
        ?>
      </form>
    </div> <?php
  }

  public function setup_sections() {
    $sections = $this->get_sections();

    if (!empty($sections)) :
      foreach ($sections as $id => $section) :
        if (!isset($section['callback']) || !$section['callback'])
          $section['callback'] = '';

        add_settings_section( $id, $section['title'], $section['callback'], self::$fields_id );
      endforeach;
    endif;
  }

  public function section_callback( $arguments ) { }

  public function setup_fields() {
    $fields = $this->get_fields();

    foreach( $fields as $field ) :

      add_settings_field( $field['uid'], $field['label'], array( $this, 'field_callback' ), self::$fields_id, $field['section'], $field );
      register_setting( self::$fields_id, $field['uid'] );

    endforeach;
  }

  public function field_callback( $arguments ) {
    $value = get_option( $arguments['uid'] ); // Get the current value, if there is one
    if( ! $value ) { // If no value exists
      $value = $arguments['default']; // Set to our default
    }

    // Check which type of field we want
    switch( $arguments['type'] ){
      case 'text':
      case 'password':
      case 'number':
        printf( '<input name="%1$s" id="%1$s" type="%2$s" placeholder="%3$s" style="%4$s" value="%5$s" />', $arguments['uid'], $arguments['type'], $arguments['placeholder'], 'min-width: 50%;', $value );
        break;
      case 'textarea':
        printf( '<textarea name="%1$s" id="%1$s" placeholder="%2$s" rows="5" cols="50">%3$s</textarea>', $arguments['uid'], $arguments['placeholder'], $value );
        break;
      case 'select':
      case 'multiselect':
        if( ! empty ( $arguments['options'] ) && is_array( $arguments['options'] ) ){
          $attributes = '';
          $options_markup = '';
          foreach( $arguments['options'] as $key => $label ){
            $options_markup .= sprintf( '<option value="%s" %s>%s</option>', $key, selected( $value[ array_search( $key, $value, true ) ], $key, false ), $label );
          }
          if( $arguments['type'] === 'multiselect' ){
            $attributes = ' multiple="multiple" ';
          }
          printf( '<select name="%1$s[]" id="%1$s" %2$s>%3$s</select>', $arguments['uid'], $attributes, $options_markup );
        }
        break;
      case 'radio':
      case 'checkbox':
        if( ! empty ( $arguments['options'] ) && is_array( $arguments['options'] ) ){
          $options_markup = '';
          $iterator = 0;
          foreach( $arguments['options'] as $key => $label ){
            $iterator++;
            $options_markup .= sprintf(
              '<label for="%1$s_%6$s"><input id="%1$s_%6$s" name="%1$s[]" type="%2$s" value="%3$s" %4$s /> %5$s</label><br/>',
              $arguments['uid'],
              $arguments['type'],
              $key,
              checked( $value[ array_search( $key, $value, true ) ],
                $key, false ),
              $label,
              $iterator
            );
          }
          printf( '<fieldset>%s</fieldset>', $options_markup );
        }
        break;
    }

    // If there is help text
    if( $helper = $arguments['helper'] ){
      printf( '<span class="helper"> %s</span>', $helper ); // Show it
    }

    // If there is supplemental text
    if( $supplimental = $arguments['supplemental'] ){
      printf( '<p class="description">%s</p>', $supplimental ); // Show it
    }
  }

  public static function get_field_id($arguments) {
    return $arguments['uid'];
  }

  public static function get_field($arguments) {
    $option = isset($arguments['uid']) ? get_option(PREFIX . '_' . $arguments['uid']) : get_option(PREFIX . '_' . $arguments);

    return $option;
  }

  public function get_sections() {
    return array();
  }

  public function get_fields() {
    return array();
  }

}