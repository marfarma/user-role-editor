<?php
/* 
 * * User Role Editor plugin Lirary general staff
 * Author: Vladimir Garagulya vladimir@shinephp.com
 * 
 */


if (!defined("WPLANG")) {
  die;  // Silence is golden, direct call is prohibited
}

$ure_siteURL = get_option( 'siteurl' );

// Pre-2.6 compatibility
if ( !defined( 'WP_CONTENT_URL' ) )
      define( 'WP_CONTENT_URL', $thanks_siteURL . '/wp-content' );
if ( ! defined( 'WP_CONTENT_DIR' ) )
      define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if ( ! defined( 'WP_PLUGIN_URL' ) )
      define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
if ( ! defined( 'WP_PLUGIN_DIR' ) )
      define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );

$urePluginDirName = substr(dirname(__FILE__), strlen(WP_PLUGIN_DIR) + 1, strlen(__FILE__) - strlen(WP_PLUGIN_DIR)-1);

define('URE_PLUGIN_URL', WP_PLUGIN_URL.'/'.$urePluginDirName);
define('URE_PLUGIN_DIR', WP_PLUGIN_DIR.'/'.$urePluginDirName);
define('URE_WP_ADMIN_URL', $ure_siteURL.'/wp-admin');
define('URE_ERROR', 'Error is encountered');


global $wpdb, $ure_OptionsTable;

$ure_OptionsTable = $wpdb->prefix .'options';


function ure_logEvent($message, $showMessage = false) {
  include(ABSPATH .'wp-includes/version.php');

  $fileName = URE_PLUGIN_DIR.'/user-role-editor.log';
  $fh = fopen($fileName,'a');
  $cr = "\n";
  $s = $cr.date("d-m-Y H:i:s").$cr.
      'WordPress version: '.$wp_version.', PHP version: '.phpversion().', MySQL version: '.mysql_get_server_info().$cr;
  fwrite($fh, $s);
  fwrite($fh, $message.$cr);
  fclose($fh);

  if ($showMessage) {
    ure_showMessage('Error is occur. Please check the log file.');
  }
}
// end of ure_logEvent()

function ure_optionSelected($value, $etalon) {
  $selected = '';
  if ($value==$etalon) {
    $selected = 'selected="selected"';
  }

  return $selected;
}
// end of ure_optionSelected()


function ure_showMessage($message) {

if ($message) {
  echo '<div class="updated" style="margin:0;">'.$message.'</div><br style="clear: both;"/>';
}

}
// end of ure_showMessage()


?>
