<?php
/**
 * This contains all CSS and JS files that will be enqueued
 *
 * @since      1.0.0
 */

namespace TrackMage;


class Assets{


   /**
    * Enqueue files
    *
    * @since 1.0.0
    * @return void
    */
   public static function enqueue(){

      //---- CSS
      add_action('admin_enqueue_scripts', __CLASS__ . '::admin_styles', 9999);
      add_action('wp_enqueue_scripts', __CLASS__ . '::frontend_styles', 9999);

      //---- JS
      add_action('admin_enqueue_scripts', __CLASS__ . '::admin_scripts', 9999);
      add_action('wp_enqueue_scripts', __CLASS__ . '::frontend_scripts', 9999);
   }



   /**
    * Enqueue styles in admin area
    *
    * @since 1.0.0
    * @return void
    */
   public static function admin_styles(){

      wp_enqueue_style(
         __NAMESPACE__ . '_admin',
         PLUGIN_URL .'/assets/css/admin.min.css',
         array(),
         PLUGIN_VERSION
      );

   }



   /**
    * Enqueue styles in frontend
    *
    * @since 1.0.0
    * @return void
    */
   public static function frontend_styles(){

      wp_enqueue_style(
         __NAMESPACE__ . '_frontend',
         PLUGIN_URL .'/assets/css/frontend.min.css',
         array(),
         PLUGIN_VERSION
      );

   }



   /**
    * Enqueue scripts in admin area
    *
    * @since 1.0.0
    * @return void
    */
    public static function admin_scripts(){

      wp_enqueue_script(
         __NAMESPACE__ . '_admin',
         PLUGIN_URL .'/assets/js/admin.min.js',
         array('jquery'),
         PLUGIN_VERSION,
         true
      );
   }



   /**
    * Enqueue scripts in frontend
    *
    * @since 1.0.0
    * @return void
    */
   public static function frontend_scripts(){

      wp_enqueue_script(
         __NAMESPACE__ . '_frontend',
         PLUGIN_URL .'/assets/js/frontend.min.js',
         array('jquery'),
         PLUGIN_VERSION,
         true
      );


      wp_localize_script( 'jquery', __NAMESPACE__, array(
         'ajax' => array(
            'url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( __NAMESPACE__ . '-nonce' )
         ),
         'prefix' => PREFIX
      ));
   }

}