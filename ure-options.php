<?php
/* 
 *
 * User Role Editor plugin management pages
 * 
 */

if (!defined('URE_PLUGIN_URL')) {
  die;  // Silence is golden, direct call is prohibited
}

$shinephpFavIcon = URE_PLUGIN_URL.'/images/vladimir.png';
$mess = '';

$ure_caps_readable = get_option('ure_caps_readable');
$option_name = $wpdb->prefix.'user_roles';

if (isset($_REQUEST['object'])) {
  $ure_object = $_REQUEST['object'];
} else {
  $ure_object = '';
}

if (isset($_REQUEST['action'])) {
  $action = $_REQUEST['action'];
  // restore roles capabilities from the backup record
  if ($action=='reset') {
    $mess = restoreUserRoles();
  } else if ($action=='addnewrole') {
    // process new role create request
    $mess = ure_newRoleCreate($ure_currentRole);
  } else if ($action=='delete') {
    $mess = ure_deleteRole();
  } else if ($action=='default') {
    $mess = ure_changeDefaultRole();
  } else if ($action=='capsreadable') {
    if ($ure_caps_readable) {
      $ure_caps_readable = 0;
    } else {
      $ure_caps_readable = 1;
    }
    update_option('ure_caps_readable', $ure_caps_readable);
  } else if ($action=='addnewcapability') {
    $mess = ure_AddNewCapability();
  } else if ($action=='removeusercapability') {
    $mess = ure_RemoveCapability();
  }
} else {
  $action = '';
}

$defaultRole = get_option('default_role');

if (isset($_POST['ure_apply_to_all'])) {
  $ure_apply_to_all = 1;
} else {
  $ure_apply_to_all = 0;
}

if (!isset($ure_roles) || !$ure_roles) {
// get roles data from database
  $ure_roles = ure_getUserRoles();
  if (!$ure_roles) {
    return;
  }
}

$ure_rolesId = array();
foreach ($ure_roles as $key=>$value) {
  $ure_rolesId[] = $key;
}


$fullCapabilities = array();
foreach($ure_roles as $role) {
    foreach ($role['capabilities'] as $key=>$value) {
      $cap = array();
      $cap['inner'] = $key;
      $cap['human'] = __(ure_ConvertCapsToReadable($key),'ure');
      $fullCapabilities[] = $cap;
    }
}
$fullCapabilities = ure_ArrayUnique($fullCapabilities);
if ($ure_caps_readable) {
  $column = 'human';  // sort by human readable form
} else {
  $column = 'inner';  // sort by inner capability name
}
$sorter = new ure_TableSorter($column); 
$fullCapabilities = $sorter->sort($fullCapabilities);


if ($ure_object=='user') {
  if (!isset($_REQUEST['user_id'])) {
    $mess .= ' user_id value is missed';
    return;
  }
  $user_id = $_REQUEST['user_id'];
  if (!is_numeric($user_id)) {
    return;
  }
  if (!$user_id) {
    return;
  }
  $ure_userToEdit = get_user_to_edit($user_id);
  if (empty($ure_userToEdit)) {
    return;
  }  
}

if (isset($_POST['action']) && $_POST['action'] == 'update' && isset($_POST['user_role'])) {
  $ure_currentRole = $_POST['user_role'];
  $ure_capabilitiesToSave = array();
  foreach ($fullCapabilities as $availableCapability) {
    $cap_id = str_replace(' ', URE_SPACE_REPLACER, $availableCapability['inner']);
    if (isset($_POST[$cap_id])) {
      $ure_capabilitiesToSave[$availableCapability['inner']] = 1;
    }
  }
  if ($ure_object == 'role') {  // save role changes to database
    if (count($ure_capabilitiesToSave) > 0) {
      if (!ure_updateRoles()) {
        return;
      }
      if ($mess) {
        $mess .= '<br/>';
      }
      $mess = __('Role', 'ure').' <em>'.__($ure_roles[$ure_currentRole]['name'], 'ure').'</em> '.__('is updated successfully', 'ure');
    }
  } else {
    if (!ure_updateUser($ure_userToEdit)) {
      return;
    }
    if ($mess) {
      $mess .= '<br/>';
    }
    $mess = __('User', 'ure').' &lt;<em>'.$ure_userToEdit->display_name.'</em>&gt; '.__('capabilities are updated successfully', 'ure');
  }
}

// options page display part
function ure_displayBoxStart($title, $style='') {
?>
			<div class="postbox" style="float: left; <?php echo $style; ?>">
				<h3 style="cursor:default;"><span><?php echo $title ?></span></h3>
				<div class="inside">
<?php
}
// 	end of ure_displayBoxStart()

function ure_displayBoxEnd() {
?>
				</div>
			</div>
<?php
}
// end of thanks_displayBoxEnd()


ure_showMessage($mess);

?>
				<div id="poststuff" class="metabox-holder has-right-sidebar">
					<div class="inner-sidebar" >
						<div id="side-sortables" class="meta-box-sortabless ui-sortable" style="position:relative;">
									<?php ure_displayBoxStart(__('About this Plugin:', 'ure')); ?>
											<a class="ure_rsb_link" style="background-image:url(<?php echo $shinephpFavIcon; ?>);" target="_blank" href="http://www.shinephp.com/"><?php _e("Author's website", 'ure'); ?></a>
											<a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/user-role-editor-icon.png'; ?>" target="_blank" href="http://www.shinephp.com/user-role-editor-wordpress-plugin/"><?php _e('Plugin webpage', 'ure'); ?></a>
											<a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/changelog-icon.png'; ?>)" target="_blank" href="http://www.shinephp.com/user-role-editor-wordpress-plugin/#changelog"><?php _e('Changelog', 'ure'); ?></a>
											<a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/faq-icon.png'; ?>)" target="_blank" href="http://www.shinephp.com/user-role-editor-wordpress-plugin/#faq"><?php _e('FAQ', 'ure'); ?></a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/donate-icon.png'; ?>)" target="_blank" href="http://www.shinephp.com/donate"><?php _e('Donate', 'ure'); ?></a>
									<?php ure_displayBoxEnd();
	ure_displayBoxStart(__('Greetings:','ure')); ?>
											<a class="ure_rsb_link" style="background-image:url(<?php echo $shinephpFavIcon; ?>);" target="_blank" title="<?php _e("It's me, the author", 'ure'); ?>" href="http://www.shinephp.com/">Vladimir</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/marsis.png'; ?>)" target="_blank" title="<?php _e("For the help with Belorussian translation", 'ure'); ?>" href="http://pc.de">Marsis G.</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/rafael.png'; ?>)" target="_blank" title="<?php _e("For the help with Brasilian translation", 'ure'); ?>" href="http://www.arquiteturailustrada.com.br/">Rafael Galdencio</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/jackytsu.png'; ?>)" target="_blank" title="<?php _e("For the help with Chinese translation", 'ure'); ?>" href="http://www.jackytsu.com">Jackytsu</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/remi.png'; ?>)" target="_blank" title="<?php _e("For the help with Dutch translation", 'ure'); ?>" href="http://www.remisan.be">Rémi Bruggeman</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/lauri.png'; ?>)" target="_blank" title="<?php _e("For the help with Finnish translation", 'ure'); ?>" href="http://www.viidakkorumpu.fi">Lauri Merisaari</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/whiler.png'; ?>)" target="_blank" title="<?php _e("For the help with French translation", 'ure'); ?>" href="http://blogs.wittwer.fr/whiler/">Whiler</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/peter.png'; ?>)" target="_blank" title="<?php _e("For the help with German translation", 'ure'); ?>" href="http://www.red-socks-reinbek.de">Peter</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/blacksnail.png'; ?>)" target="_blank" title="<?php _e("For the help with Hungarian translation", 'ure'); ?>" href="http://www.blacksnail.hu">István</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/venezialog.png'; ?>)" target="_blank" title="<?php _e("For the help with Italian translation", 'ure'); ?>" href="http://venezialog.net">Umberto Sartori</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/talksina.png'; ?>)" target="_blank" title="<?php _e("For the help with Italian translation", 'ure'); ?>" href="http://www.iadkiller.org">Talksina</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/alessandro.png'; ?>);" target="_blank" title="<?php _e("For the help with Italian translation",'pgc');?>" href="http://technodin.org">Alessandro Mariani</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/tristano.png'; ?>);" target="_blank" title="<?php _e("For the help with Italian translation",'pgc');?>" href="http://www.zenfactor.org ">Tristano Ajmone</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/technologjp.png'; ?>)" target="_blank" title="<?php _e("For the help with Japanese translation", 'ure'); ?>" href="http://technolog.jp">Technolog.jp</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/good-life.png'; ?>)" target="_blank" title="<?php _e("For the help with Persian translation", 'ure'); ?>" href="http://good-life.ir">Good Life</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/tagsite.png'; ?>)" target="_blank" title="<?php _e("For the help with Polish translation", 'ure'); ?>" href="http://www.tagsite.eu">TagSite</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/dario.png'; ?>)" target="_blank" title="<?php _e("For the help with Spanish translation", 'ure'); ?>" href="http://www.darioferrer.com">Dario  Ferrer</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/christer.png'; ?>)" target="_blank" title="<?php _e("For the help with Swedish translation", 'ure'); ?>" href="www.startlinks.eu">Christer Dahlbacka</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/sadri.png'; ?>)" target="_blank" title="<?php _e("For the help with Turkish translation", 'ure'); ?>" href="http://www.faydaliweb.com">Sadri Ercan</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/cartaca.png'; ?>)" target="_blank" title="<?php _e("For the help with Turkish translation", 'ure'); ?>" href="http://www.kartaca.com">Can KAYA</a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/fullthrottle.png'; ?>)" target="_blank" title="<?php _e("For the code to hide administrator role", 'ure'); ?>" href="http://fullthrottledevelopment.com/how-to-hide-the-adminstrator-on-the-wordpress-users-screen">FullThrottle</a>
											<?php _e('Do you wish to see your name with link to your site here? You are welcome! Your help with translation and new ideas are very appreciated.', 'ure'); ?>
									<?php ure_displayBoxEnd(); ?>
						</div>
					</div>
          <div class="has-sidebar" >
            <form method="post" action="<?php echo URE_PARENT; ?>?page=user-role-editor.php" onsubmit="return ure_onSubmit();">
              <?php
              settings_fields('ure-options');
              ?>

              <?php
              if ($ure_object == 'user') {
                require_once('ure-user-edit.php');
              } else {
                require_once('ure-role-edit.php');
              }
              ?>
            </form>
          </div>
        </div>

