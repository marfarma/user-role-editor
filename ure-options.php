<?php
/* 
 * Silence Is Golden Guard plugin Settings form
 * 
 */

if (!defined('URE_PLUGIN_URL')) {
  die;  // Silence is golden, direct call is prohibited
}

$shinephpFavIcon = URE_PLUGIN_URL.'/images/vladimir.png';
$mess = '';

$option_name = $wpdb->prefix.'user_roles';

// restore roles capabilities from the backup record
if (isset($_GET['action']) && $_GET['action']=='reset') {
  $backup_option_name = $wpdb->prefix.'backup_user_roles';
  $query = "select option_value
              from $ure_OptionsTable
              where option_name='$backup_option_name'
              limit 0, 1";
  $option_value = $wpdb->get_var($query);
  if ($wpdb->last_error) {
    ure_logEvent($wpdb->last_error, true);
    return;
  }
  if ($option_value) {
    $query = "update $ure_OptionsTable
                    set option_value='$option_value'
                    where option_name='$option_name'
                    limit 1";
    $record = $wpdb->query($query);
    if ($wpdb->last_error) {
        ure_logEvent($wpdb->last_error, true);
        return;
    }
    if ($mess) {
        $mess .= '<br/';
    }
    $mess = __('Roles capabilities are restored from the backup data', 'ure');
  } else {
    if ($mess) {
      $mess .= '<br/';
    }
    $mess = __('No backup data. It is created automatically before the first role data update.', 'ure');
  }
  if (isset($_REQUEST['user_role'])) {
    $_REQUEST['user_role'] = null;
  }
}

$query = "select option_id, option_value
            from $ure_OptionsTable
            where option_name='$option_name'
            limit 0, 1";
$record = $wpdb->get_results($query);
if ($wpdb->last_error) {
  ure_logEvent($wpdb->last_error);
  return;
}
$roles = unserialize($record[0]->option_value);

$rolesId = array();
foreach ($roles as $key=>$value) {
  $rolesId[] = $key;
}

$currentRole = $rolesId[count($rolesId) - 1];
if (isset($_REQUEST['user_role']) && $_REQUEST['user_role']) {
  $currentRole = $_REQUEST['user_role'];
}

$roleSelectHTML = '<select id="user_role" name="user_role" onchange="ure_Actions(\'role-change\', this.value);">';
foreach ($roles as $key=>$value) {
  $selected = ure_optionSelected($key, $currentRole);
  if ($key!='administrator') {
    $roleSelectHTML .= '<option value="'.$key.'" '.$selected.'>'.$value['name'].'</option>';
  }
}
$roleSelectHTML .= '</select>';

$fullCapabilities = array();
$role = $roles['administrator'];
foreach ($role['capabilities'] as $key=>$value) {
  $fullCapabilities[] = $key;
}

// save role changes to database block
if (isset($_POST['action']) && $_POST['action']=='update' && isset($_POST['user_role'])) {
  $currentRole = $_POST['user_role'];
  $capabilityToSave = array();
  foreach($roles['administrator']['capabilities'] as $availableCapability=>$value) {
    if (isset($_POST[$availableCapability])) {
      $capabilityToSave[$availableCapability] = 1;
    }
  }
  if (count($capabilityToSave)>0) {
    // check if backup user roles record exists already
    $backup_option_name = $wpdb->prefix.'backup_user_roles';
    $query = "select option_id
                from $ure_OptionsTable
                where option_name='$backup_option_name'
            limit 0, 1";
    $option_id = $wpdb->get_var($query);
    if ($wpdb->last_error) {
      ure_logEvent($wpdb->last_error, true);
      return;
    }
    if (!$option_id) {
      // create user roles record backup
      $serialized_roles = mysql_real_escape_string(serialize($roles));
      $query = "insert into $ure_OptionsTable
                  (option_name, option_value, autoload)
                  values ('$backup_option_name', '$serialized_roles', 'yes')";
      $record = $wpdb->query($query);
      if ($wpdb->last_error) {
        ure_logEvent($wpdb->last_error, true);
        return;
      }
      $mess .= __('Backup record is created for the current role capabilities', 'ure');
    }
    // save role changes into the database
    $roles[$currentRole]['capabilities'] = $capabilityToSave;
    $serialized_roles = serialize($roles);
    $query = "update $ure_OptionsTable
                set option_value='$serialized_roles'
                where option_name='$option_name'
                limit 1";
    $record = $wpdb->query($query);
    if ($wpdb->last_error) {
      ure_logEvent($wpdb->last_error, true);
      return;
    }
    if ($mess) {
      $mess .= '<br/';
    }
    $mess = __('Role ', 'ure').$roles[$currentRole]['name'].__(' is updated successfully', 'ure');
  }
}


// options page display part
function ure_displayBoxStart($title) {
?>
			<div class="postbox" style="float: left;">
				<h3 style="cursor:default;"><span><?php echo $title ?></span></h3>
				<div class="inside">
<?php
}
// 	end of thanks_displayBoxStart()

function ure_displayBoxEnd() {
?>
				</div>
			</div>
<?php
}
// end of thanks_displayBoxEnd()


ure_showMessage($mess);

?>
  <form method="post" action="options-general.php?page=user-role-editor.php" onsubmit="return ure_onSubmit();">
<?php
    settings_fields('ure-quard-options');
?>
				<div id="poststuff" class="metabox-holder has-right-sidebar">
					<div class="inner-sidebar" >
						<div id="side-sortables" class="meta-box-sortabless ui-sortable" style="position:relative;">
									<?php ure_displayBoxStart(__('About this Plugin:', 'ure')); ?>
											<a class="ure_rsb_link" style="background-image:url(<?php echo $shinephpFavIcon; ?>);" target="_blank" href="http://www.shinephp.com/"><?php _e("Author's website", 'ure'); ?></a>
											<a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/user-role-editor-icon.png'; ?>" target="_blank" href="http://www.shinephp.com/user-role-editor-wordpress-plugin/"><?php _e('Plugin webpage', 'ure'); ?></a>
											<a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/changelog-icon.png'; ?>);" target="_blank" href="http://www.shinephp.com/user-role-editor-wordpress-plugin/#changelog"><?php _e('Changelog', 'ure'); ?></a>
											<a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/faq-icon.png'; ?>)" target="_blank" href="http://www.shinephp.com/user-role-editor-wordpress-plugin/#faq"><?php _e('FAQ', 'ure'); ?></a>
                      <a class="ure_rsb_link" style="background-image:url(<?php echo URE_PLUGIN_URL.'/images/donate-icon.png'; ?>)" target="_blank" href="http://www.shinephp.com/donate"><?php _e('Donate', 'ure'); ?></a>
									<?php ure_displayBoxEnd(); ?>
									<?php ure_displayBoxStart(__('Greetings:','ure')); ?>
											<a class="ure_rsb_link" style="background-image:url(<?php echo $shinephpFavIcon; ?>);" target="_blank" title="<?php _e("It's me, the author", 'ure'); ?>" href="http://www.shinephp.com/">Vladimir</a>
											<?php _e('Do you wish to see your name with link to your site here? You are welcome! Your help with translation and new ideas are very appreciated.', 'ure'); ?>
									<?php ure_displayBoxEnd(); ?>
						</div>
					</div>
					<div class="has-sidebar" >
						<div id="post-body-content" class="has-sidebar-content">
<script language="javascript" type="text/javascript">
  function ure_Actions(action, value) {
    if (action=='cancel') {
      document.location = '<?php echo URE_WP_ADMIN_URL; ?>/options-general.php?page=user-role-editor.php';
    } else {
      if (action!='role-change' && !confirm(action +'<?php _e(': Please confirm to continue', 'ure'); ?>')) {
        return false;
      }
      if (action!='update') {
        $url = '<?php echo URE_WP_ADMIN_URL; ?>/options-general.php?page=user-role-editor.php&action='+ action;
        if (value!='') {
          $url = $url +'&user_role='+ value;
        }
        document.location = $url;
      } else {
        document.getElementById('ure-form').submit();
      }
    }
  }

  function ure_onSubmit() {
    if (!confirm('<?php echo sprintf(__('Role "%s" update: please confirm to continue', 'ure'), $roles[$currentRole]['name']); ?>')) {
      return false;
    }
  }


</script>
<?php
						ure_displayBoxStart(__('Select Role and change its capabilities list', 'ure')); ?>
        <table class="form-table" style="clear:none;" cellpadding="0" cellspacing="0">          
          <tr>
            <td style="vertical-align:top;width:200px;" colspan="3">
              <?php echo __('Select Role:', 'ure').' '.$roleSelectHTML; ?>
            </td>
          </tr>
          <tr>
            <td style="vertical-align:top;">
<?php
  $quant = count($fullCapabilities);
  $i = 0; $quantInCell = 0;
  while($i<$quant) {        
    $checked = '';
    //$capability = $roles[$currentRole]['capabilities']; if (isset($capability[$fullCapabilities[$i]])) {
    if (isset($roles[$currentRole]['capabilities'][$fullCapabilities[$i]])) {
      $checked = 'checked="checked"';
    }
?>
   <input type="checkbox" name="<?php echo $fullCapabilities[$i]; ?>" id="<?php echo $fullCapabilities[$i]; ?>" value="<?php echo $fullCapabilities[$i]; ?>" <?php echo $checked; ?>/> <?php echo $fullCapabilities[$i]; ?><br/>
<?php
   $i++; $quantInCell++;
   if ($quantInCell>=23) {
     $quantInCell = 0;
     echo '</td>
           <td style="vertical-align:top;">';
   }
  }
?>
            </td>
          </tr>
      </table>
			<?php ure_displayBoxEnd();?>
      <div class="fli submit" style="padding-top: 0px;">
          <input type="submit" name="submit" value="<?php _e('Update', 'ure'); ?>" title="<?php _e('Save Changes', 'ure'); ?>" />
          <input type="button" name="cancel" value="<?php _e('Cancel', 'ure') ?>" title="<?php _e('Cancel not saved changes','ure');?>" onclick="ure_Actions('cancel');"/>
          <input type="button" name="default" value="<?php _e('Reset', 'ure') ?>" title="<?php _e('Return to default WordPress user role capabilities','ure');?>" onclick="ure_Actions('reset');"/>
      </div>
						</div>
					</div>
				</div>
    </form>

