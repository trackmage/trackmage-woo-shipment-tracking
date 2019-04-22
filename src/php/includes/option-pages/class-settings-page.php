<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2/9/19
 * Time: 3:49 PM
 */

namespace TrackMage;

class SettingsPage extends Abstract_Settings_Page {

  public function __construct() {
    self::$page_title = __('TrackMage Settings', PREFIX);
    self::$menu_title = __('TrackMage Settings', PREFIX);
    self::$capability = 'manage_options';
    self::$menu_slug  = PREFIX . '-settings';

    self::$fields_id = PREFIX;

    if (self::$menu_slug)
      parent::__construct();
  }

  public function get_fields() {
    return array(
      array(
        'uid'   => PREFIX . '_license_key',
        'label' => __('License key', PREFIX),
        'section' => 'auto-updates',
        'type'    => 'text',
        'options' => false,
      ),
      array(
        'uid'   => PREFIX . '_license_email',
        'label' => __('Email', PREFIX),
        'section' => 'auto-updates',
        'type'    => 'text',
        'options' => false,
      ),

      array(
        'uid'   => PREFIX . '_title',
        'label' => __('Title', PREFIX),
        'section' => 'settings',
        'type'    => 'text',
        'options' => false,
      ),
      array(
        'uid'   => PREFIX . '_description',
        'label' => __('Description', PREFIX),
        'section' => 'settings',
        'type'    => 'textarea',
        'options' => false,
      )
    );
  }

  public function get_sections() {
    return array(
      'auto-updates' => array(
        'title' => __('Auto Updates', PREFIX)
      ),
      'settings' => array(
        'title' => __('Settings', PREFIX)
      ),
    );
  }

}

new SettingsPage();