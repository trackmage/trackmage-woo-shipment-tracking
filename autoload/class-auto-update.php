<?php
/**
 * Automatic plugin updates via API Manager WooCommerce extension
 *
 * @since      1.0.0
 */

namespace TrackMage;


class AutoUpdate{


   /**
    * Site URL where the updates will be taken from
    *
    * @since 1.0.0
    * @var string
    */
   public static $site_url = AUTO_UPDATE_URL;


   /**
   * @since    1.0.0
   * @access   public
   */
   public static $updates;


   /**
    * API licence key
    *
    * @since 1.0.0
    * @var string
    */
   public static $key;


   /**
    * Activation email
    *
    * @since 1.0.0
    * @var string
    */
   public static $email;



   /**
    * @since 1.0.0
    */
   public static function init(){

      add_action('init', __CLASS__ . '::run_on_init');

      add_action('install_plugins_pre_plugin-information', __CLASS__ . '::display_changelog', 9);
      add_filter('transient_update_plugins', __CLASS__ . '::update_notification');
      add_filter('site_transient_update_plugins', __CLASS__ . '::update_notification');

   }



   /**
    * Run on init
    *
    * @since 1.0.0
    * @return void
    */
   public static function run_on_init(){

      self::$updates = get_option(PREFIX.'_plugin_info', array());
      self::$key     = get_option(PREFIX.'_license_key');
      self::$email   = get_option(PREFIX.'_license_email');

      //check manually for updates
      if(is_admin() && Utility::rgar($_GET, 'action') == PREFIX.'_check_updates'){

         $check = self::check_for_updates();

         // Check the versions if we need to do an update
         $do_update = version_compare( Utility::rgar(self::$updates, 'new_version'), PLUGIN_VERSION );

         if( $check && ($do_update == 0 || $do_update == -1) ){
            Utility::show_notice(sprintf(
               __('%s plugin is already up to date, no new updates were found.', TEXT_DOMAIN),
               '<b>'.PLUGIN_NAME.'</b>'
            ), 'success');
         }
      }

      //check periodically for updates
      if(get_transient(PREFIX.'_plugin_checked') === false){
         self::check_for_updates();
      }
   }




   /**
    * Get the license status
    *
    * @since 1.0.0
    * @param string $key
    * @param string $email
    * @return string|object
    */
   public static function get_license_status($key, $email){

      $errors = false;

      $remote = wp_remote_get(self::$site_url, array(
         'body' => array(
            'wc-api'      => 'am-software-api',
            'request'     => 'status',
            'email'       => $email,
            'licence_key' => $key,
            'product_id'  => PLUGIN_NAME,
            'platform'    => $_SERVER['SERVER_NAME'],
            'instance'    => PLUGIN_INSTANCE,
         )
      ));

      if(is_wp_error($remote)){

         return (object) array('error' => $remote->get_error_message());

      }else{

         $data = json_decode($remote['body']);

         if(isset($data->error) && !isset($data->status_check)){

            return $data;

         }else{

            return $data->status_check;
         }
      }
   }



   /**
    * Activate the license
    *
    * @since 1.0.0
    * @param string $key
    * @param string $email
    * @return string|object
    */
   public static function activate_license($key, $email){

      $errors = false;

      $remote = wp_remote_get(self::$site_url, array(
         'body' => array(
            'wc-api'           => 'am-software-api',
            'request'          => 'activation',
            'email'            => $email,
            'licence_key'      => $key,
            'product_id'       => PLUGIN_NAME,
            'platform'         => $_SERVER['SERVER_NAME'],
            'instance'         => PLUGIN_INSTANCE,
            'software_version' => PLUGIN_VERSION,
         )
      ));

      if(is_wp_error($remote)){

         return (object) array('error' => $remote->get_error_message());

      }else{

         $data = json_decode($remote['body']);

         if(isset($data->error)){

            return (object) array('error' => $data->error);

         }else{

            return $data;
         }
      }
   }



   /**
    * Deactivate the license
    *
    * @since 1.0.0
    * @param string $key
    * @param string $email
    * @return string|object
    */
   public static function deactivate_license($key, $email){

      $errors = false;

      $remote = wp_remote_get(self::$site_url, array(
         'body' => array(
            'wc-api'           => 'am-software-api',
            'request'          => 'deactivation',
            'email'            => $email,
            'licence_key'      => $key,
            'product_id'       => PLUGIN_NAME,
            'platform'         => $_SERVER['SERVER_NAME'],
            'instance'         => PLUGIN_INSTANCE,
            'software_version' => PLUGIN_VERSION,
         )
      ));

      if(is_wp_error($remote)){

         return (object) array('error' => $remote->get_error_message());

      }else{

         $data = json_decode($remote['body']);

         if(isset($data->error)){

            return (object) array('error' => $data->error);

         }else{

            return $data;
         }
      }
   }



   /**
    * Display available update notification
    *
    * @since 1.0.0
    */
   public static function update_notification($update_plugins){

      if (!is_object($update_plugins)) return $update_plugins;

      if (!isset( $update_plugins->response) || !is_array($update_plugins->response)) $update_plugins->response = array();

      $check = get_transient(PREFIX.'_plugin_checked');

      // Check the versions if we need to do an update
      $do_update = version_compare( Utility::rgar(self::$updates, 'new_version'), PLUGIN_VERSION );

      if($check !== false && $do_update == 1 ){
         $update_plugins->response[PLUGIN_BASENAME] = (object) array(
            'slug'         => PLUGIN_FOLDER,
            'url'          => self::$site_url,
            'new_version'  => Utility::rgar(self::$updates, 'new_version'),
            'package'      => Utility::rgar(self::$updates, 'package'),
         );
      }

      return $update_plugins;
   }



   /**
    * Display plugin changelog
    *
    * @since 1.0.0
    */
   public static function display_changelog(){

      if ( $_REQUEST['plugin'] != PLUGIN_FOLDER ) {
			return;
      }

      if(isset(self::$updates['changelog'])){
         ?>
         <style>
            body{
               margin: 0;
               color: #4e4e4e;
               font-size: 14px;
               line-height: 1.6em;
               font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
            }
            .plugin-information__cover{
               background-image: url("<?php echo CHANGELOG_COVER;?>");
               background-size: cover;
               background-position: center center;
               height: 250px;
            }
            .plugin-information__cover h2{
               position: relative;
               font-family: "Helvetica Neue",sans-serif;
               display: inline-block;
               font-size: 30px;
               line-height: 50px;
               box-sizing: border-box;
               max-width: 100%;
               padding: 0 15px;
               margin: 174px 0 0 25px;
               color: #fff;
               background: rgba(30,30,30,.9);
               text-shadow: 0 1px 3px rgba(0,0,0,.4);
               box-shadow: 0 0 30px rgba(255,255,255,.1);
               border-radius: 8px;
            }
            .plugin-content{
               padding: 25px;
            }
            .plugin-content ul{
               margin: 0;
               padding: 0 0 0 15px;
            }
               .plugin-content ul li{
                  margin-bottom: 10px;
                  list-style: none;
               }
            .plugin-content .changelog {
               padding: 5px 8px;
               border-radius: 4px;
               font-size: 12px;
               text-transform: uppercase;
               letter-spacing: 0.2px;
               font-weight: 600;
               color: white;
            }
            .plugin-content .changelog.tweak {
               background: #6aa84f;
            }
            .plugin-content .changelog.feature {
               background: #3c78d8;
            }
            .plugin-content .changelog.fix {
               background: #cc0000;
            }
         </style>
         <div class="plugin-information">
            <div class="plugin-information__cover">
               <h2><?php echo PLUGIN_NAME;?></h2>
            </div>
            <div class="plugin-content">
               <?php echo self::$updates['changelog'];?>
            </div>
         </div>
         <?php
      }

		exit;
   }



   /**
    * Check for available updates
    *
    * @since 1.0.0
    */
   public static function check_for_updates(){

      // reset checking for updates
      delete_transient(PREFIX.'_plugin_checked');

      //no license
      if(self::$key == '' || self::$email == ''){

         Utility::show_notice(sprintf(
            __('Please %sprovide%s a valid license key for %s plugin to receive automatic updates and support. Need a license key? %sPurchase one now!%s', TEXT_DOMAIN),
            '<a href="'.PLUGIN_SETTINGS_URL.'">',
            '</a>',
            '<b>'.PLUGIN_NAME.'</b>',
            '<a href="'.self::$site_url.'" target="_blank">',
            '</a>'
         ), 'error');

         update_option(PREFIX.'_license_active', 'false');

         return false;
      }


      $license  = self::get_license_status(self::$key, self::$email);

      //license error
      if (isset($license->error)){

         $msg = $license->error;

         //invalide api key
         if($license->code == '101'){

            $msg = sprintf(
               __('Invalid API License Key for %s plugin. Login to your %sMy Account%s page to find a valid API License Key.', TEXT_DOMAIN),
               '<b>'.PLUGIN_NAME.'</b>',
               '<a href="'.self::$site_url.'" target="_blank">',
               '</a>'
            );
         }

         //subscription is inactive
         if($license->code == '106'){

            $msg = sprintf(
               __('The subscription for license key of %s plugin is inactive. This means you will no longer receive automatic updates and support. Please check your %s account %s to activate the subscription.', TEXT_DOMAIN),
               '<b>'.PLUGIN_NAME.'</b>',
               '<a href="'.self::$site_url.'" target="_blank">',
               '</a>'
            );
         }

         Utility::show_notice($msg);

         update_option(PREFIX.'_license_active', 'false');

         return false;
      }

      //license inactive
      if($license == 'inactive'){

         $activate = AutoUpdate::activate_license(self::$key, self::$email);

         if(!isset($activate->error)){

            update_option(PREFIX.'_license_active', 'true');

         }else{

            Utility::show_notice(sprintf(
               __('It looks like the license key of %s plugin has been set inactive by our shop. Please %s contact us %s for more details.', TEXT_DOMAIN),
               '<b>'.PLUGIN_NAME.'</b>',
               '<a href="'.self::$site_url.'" target="_blank">',
               '</a>'
            ));

            update_option(PREFIX.'_license_active', 'false');

            return false;
         }
      }


      $remote = wp_remote_get(self::$site_url, array(
         'body' => array(
            'wc-api' => 'upgrade-api',
            'request' => 'pluginupdatecheck',
            'plugin_name' => PLUGIN_BASENAME,
            'product_id' => PLUGIN_NAME,
            'api_key' => self::$key,
            'activation_email' => self::$email,
            'instance' => PLUGIN_INSTANCE,
            'domain' => $_SERVER['SERVER_NAME'],
         )
      ));


      if(is_wp_error($remote)){

         Utility::show_notice($remote->get_error_message());

      }else{

         $data = unserialize($remote['body']);

         if(isset($data->errors)){

            if(isset($data->errors['no_key'])){
               Utility::show_notice(sprintf(
                  __('The license key for %s plugin could not be found in the system. Please check your %s account %s to find a valid API License Key.', TEXT_DOMAIN),
                  '<b>'.PLUGIN_NAME.'</b>',
                  '<a href="'.self::$site_url.'" target="_blank">',
                  '</a>'
               ));
            }

            if(isset($data->errors['no_activation'])){
               Utility::show_notice(sprintf(
                  __('The license key for %s plugin has not yet been activated. Please %s contact us %s for more details.', TEXT_DOMAIN),
                  '<b>'.PLUGIN_NAME.'</b>',
                  '<a href="'.self::$site_url.'" target="_blank">',
                  '</a>'
               ));
               update_option(PREFIX.'_license_active', 'false');
            }

         } else {

            if (isset($data->package) && isset($data->new_version)) {

               $info = self::plugin_information();

               if($info !== false){
                  update_option(PREFIX.'_plugin_info', array(
                     'new_version' => $data->new_version,
                     'package' => $data->package,
                     'changelog' => $info->sections['changelog'],
                  ));
                  //set as checked
                  set_transient(PREFIX.'_plugin_checked', 'true', 60*60*48);
               }

            } else {
               //reset checking for updates
               delete_transient(PREFIX.'_plugin_checked');
            }
         }
      }

      return true;

   }



   /**
    * Get plugin information
    *
    * @since 1.0.0
    * @return mixed
    */
   protected static function plugin_information(){

      $remote = wp_remote_get(self::$site_url, array(
         'body' => array(
            'wc-api' => 'upgrade-api',
            'request' => 'plugininformation',
            'plugin_name' => PLUGIN_BASENAME,
            'product_id' => PLUGIN_NAME,
            'api_key' => self::$key,
            'activation_email' => self::$email,
            'instance' => PLUGIN_INSTANCE,
            'domain' => $_SERVER['SERVER_NAME'],
         )
      ));

      if(!is_wp_error($remote)){
         return unserialize($remote['body']);
      }

      //reset checking for updates
      delete_transient(PREFIX.'_plugin_checked');

      return false;
   }



   /**
    * Show auto updates status
    *
    * @since 1.0.0
    */
   public static function api_status(){
      return get_option(PREFIX . '_license_active') != 'true' || self::$key == '' || self::$email == '' ? sprintf(__('%sStatus:%s %sInactive%s', TEXT_DOMAIN), '<b>', '</b>', '<span style="color: #cc0000;">', '</span>') : sprintf(__('%sStatus:%s %sActive%s', TEXT_DOMAIN), '<b>', '</b>', '<span style="color: green;">', '</span>');
   }

}