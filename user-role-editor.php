<?php
/*
Plugin Name: User Role Editor
Plugin URI: http://www.shinephp.com/user-role-editor-wordpress-plugin/
Description: It allows you to change any standard WordPress user roles (except administrator) capabilities list with a few clicks.
Version: 1.0
Author: Vladimir Garagulya
Author URI: http://www.shinephp.com
Text Domain: ure
Domain Path: /lang/
*/

/*
Copyright 2009  Vladimir Garagulya  (email: vladimir@shinephp.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


if (!function_exists("get_option")) {
  die;  // Silence is golden, direct call is prohibited
}

global $wp_version;

$exit_msg = __('User Role Editor requires WordPress 2.8 or newer.').'<a href="http://codex.wordpress.org/Upgrading_WordPress">'.__('Please update!').'</a>';

if (version_compare($wp_version,"2.8","<"))
{
	return ($exit_msg);
}


require_once('ure-lib.php');

load_plugin_textdomain('ure','', $urePluginDirName.'/lang');


function ure_optionsPage() {
  
  global $wpdb, $ure_OptionsTable;

  if (!current_user_can('activate_plugins')) {
    die('action is forbidden');
  }
  
?>

<div class="wrap">
  <div class="icon32" id="icon-options-general"><br/></div>
    <h2><?php _e('User Role Editor', 'ure'); ?></h2>
		<?php require ('ure-options.php'); ?>
  </div>
<?php

}
// end of ure_optionsPage()


// Install plugin
function ure_install() {
	
  add_option('ure_auto_monitor', 0);

  ure_logEvent('URE Plugin is installed successfully.');
}
// end of ure_install()


function ure_init() {

  if(function_exists('register_setting')) {
    register_setting('ure-options', 'ure_option');
  }
}
// end of ure_init()


function ure_plugin_action_links($links, $file) {
    if ($file == plugin_basename(dirname(__FILE__).'/user-role-editor.php')){
        $settings_link = "<a href='options-general.php?page=user-role-editor.php'>".__('Settings','ure')."</a>";
        array_unshift( $links, $settings_link );
    }
    return $links;
}
// end of ure_plugin_action_links


function ure_plugin_row_meta($links, $file) {
  if ($file == plugin_basename(dirname(__FILE__).'/user-role_editor.php')){
		$links[] = '<a target="_blank" href="http://www.shinephp.com/user-role-editor-wordpress-plugin/#changelog">'.__('Changelog', 'ure').'</a>';
	}
	return $links;
} // end of ure_plugin_row_meta


function ure_settings_menu() {
	if ( function_exists('add_options_page') ) {
    $ure_page = add_options_page('User Role Editor', 'User Role Editor', 9, basename(__FILE__), 'ure_optionsPage');
		add_action( "admin_print_styles-$ure_page", 'ure_adminCssAction' );
	}
}
// end of ure_settings_menu()

function ure_adminCssAction() {

  wp_enqueue_style('ure_admin_css', URE_PLUGIN_URL.'/css/ure-admin.css', array(), false, 'screen');

}
// end of ure_adminCssAction()



if (is_admin()) {
  // activation action
  register_activation_hook(__FILE__, "ure_install");

  add_action('admin_init', 'ure_init');
  // add a Settings link in the installed plugins page
  add_filter('plugin_action_links', 'ure_plugin_action_links', 10, 2);
  add_filter('plugin_row_meta', 'ure_plugin_row_meta', 10, 2);
  add_action('admin_menu', 'ure_settings_menu');
}




?>