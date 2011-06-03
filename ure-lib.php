<?php
/* 
 * * User Role Editor plugin Library for general staff
 * Author: Vladimir Garagulya vladimir@shinephp.com
 * 
 */


if (!defined("WPLANG")) {
  die;  // Silence is golden, direct call is prohibited
}

$ure_siteURL = get_option( 'siteurl' );

$urePluginDirName = substr(strrchr(dirname(__FILE__), DIRECTORY_SEPARATOR), 1);

define('URE_PLUGIN_URL', WP_PLUGIN_URL.'/'.$urePluginDirName);
define('URE_PLUGIN_DIR', WP_PLUGIN_DIR.'/'.$urePluginDirName);
define('URE_WP_ADMIN_URL', $ure_siteURL.'/wp-admin');
define('URE_ERROR', 'Error is encountered');
define('URE_SPACE_REPLACER', '_URE-SR_');
define('URE_PARENT', 'users.php');

global $wpdb, $ure_roles, $ure_capabilitiesToSave, $ure_currentRole, $ure_toldAboutBackup, $ure_apply_to_all, 
       $ure_userToEdit, $fullCapabilities;

$ure_roles = false; $ure_capabilitiesToSave = false; $ure_toldAboutBackup = false; $ure_apply_to_all = false; 
$ure_userToEdit = false; $fullCapabilities = false;

// this array will be used to cash users checked for Administrator role
$ure_userToCheck = array();

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
    ure_showMessage('Error! '.__('Error is occur. Please check the log file.', 'ure'));
  }
}
// end of ure_logEvent()


// returns true is user has Role "Administrator"
function ure_has_administrator_role($user_id) {
  global $wpdb, $ure_userToCheck;

  if (!isset($user_id) || !$user_id) {
    return false;
  }

  $tableName = $wpdb->prefix.'usermeta';
  $metaKey = $wpdb->prefix.'capabilities';
  $query = "SELECT count(*)
                FROM $tableName
                WHERE user_id=$user_id AND meta_key='$metaKey' AND meta_value like '%administrator%'";
  $hasAdminRole = $wpdb->get_var($query);
  if ($hasAdminRole>0) {
    $result = true;
  } else {
    $result = false;
  }
  $ure_userToCheck[$user_id] = $result;

  return $result;
}
// end of ure_has_administrator_role()


// true if user is superadmin under multi-site environment or has administrator role
function ure_is_admin( $user_id = false ) {
  global $current_user;

	if ( ! $user_id ) {
    if (empty($current_user) && function_exists('get_currentuserinfo')) {
      get_currentuserinfo();
    }
		$user_id = ! empty($current_user) ? $current_user->id : 0;
	}

	if ( ! $user_id )
		return false;

	$user = new WP_User($user_id);

  $simpleAdmin = ure_has_administrator_role($user_id);

	if ( is_multisite() ) {
		$super_admins = get_super_admins();
		$superAdmin =  is_array( $super_admins ) && in_array( $user->user_login, $super_admins );
	} else {
    $superAdmin = false;
  }

	return $simpleAdmin || $superAdmin;
}
// end of ure_is_super_admin()


function ure_optionSelected($value, $etalon) {
  $selected = '';
  if (strcasecmp($value,$etalon)==0) {
    $selected = 'selected="selected"';
  }

  return $selected;
}
// end of ure_optionSelected()


function ure_showMessage($message) {

  if ($message) {
    if (strpos(strtolower($message), 'error')===false) {
      $class = 'updated fade';
    } else {
      $class = 'error';
    }
    echo '<div class="'.$class.'" style="margin:0;">'.$message.'</div><br style="clear: both;"/>';
  }

}
// end of ure_showMessage()


function ure_getUserRoles() {
  global $wpdb, $wp_roles;

  if (!isset($wp_roles)) {
    $ure_OptionsTable = $wpdb->prefix . 'options';
    $option_name = $wpdb->prefix . 'user_roles';
    $getRolesQuery = "select option_id, option_value
                      from $ure_OptionsTable
                      where option_name='$option_name'
                      limit 0, 1";
    $record = $wpdb->get_results($getRolesQuery);
    if ($wpdb->last_error) {
      ure_logEvent($wpdb->last_error);
      return;
    }
    $ure_roles = unserialize($record[0]->option_value);
  } else {
    $ure_roles = $wp_roles->roles;
  }

  return $ure_roles;
}
// end of getUserRoles()


// restores User Roles from the backup record
function restoreUserRoles() {

  global $wpdb;

  $errorMessage = 'Error! '.__('Database operation error. Check log file.', 'ure');
  $ure_OptionsTable = $wpdb->prefix .'options';
  $option_name = $wpdb->prefix.'user_roles';
  $backup_option_name = $wpdb->prefix.'backup_user_roles';
  $query = "select option_value
              from $ure_OptionsTable
              where option_name='$backup_option_name'
              limit 0, 1";
  $option_value = $wpdb->get_var($query);
  if ($wpdb->last_error) {
    ure_logEvent($wpdb->last_error, true);
    return $errorMessage;
  }
  if ($option_value) {
    $query = "update $ure_OptionsTable
                    set option_value='$option_value'
                    where option_name='$option_name'
                    limit 1";
    $record = $wpdb->query($query);
    if ($wpdb->last_error) {
        ure_logEvent($wpdb->last_error, true);
        return $errorMessage;
    }
    $mess = __('Roles capabilities are restored from the backup data', 'ure');
  } else {
    $mess = __('No backup data. It is created automatically before the first role data update.', 'ure');
  }
  if (isset($_REQUEST['user_role'])) {
    unset($_REQUEST['user_role']);
  }

  return $mess;
}
// end of restorUserRoles()


function ure_makeRolesBackup() {
  global $wpdb, $mess, $ure_roles, $ure_capabilitiesToSave, $ure_toldAboutBackup;

  $ure_OptionsTable = $wpdb->prefix .'options';
  // check if backup user roles record exists already
  $backup_option_name = $wpdb->prefix.'backup_user_roles';
  $query = "select option_id
              from $ure_OptionsTable
              where option_name='$backup_option_name'
          limit 0, 1";
  $option_id = $wpdb->get_var($query);
  if ($wpdb->last_error) {
    ure_logEvent($wpdb->last_error, true);
    return false;
  }
  if (!$option_id) {
    // create user roles record backup
    $serialized_roles = mysql_real_escape_string(serialize($ure_roles));
    $query = "insert into $ure_OptionsTable
                (option_name, option_value, autoload)
                values ('$backup_option_name', '$serialized_roles', 'yes')";
    $record = $wpdb->query($query);
    if ($wpdb->last_error) {
      ure_logEvent($wpdb->last_error, true);
      return false;
    }
    if (!$ure_toldAboutBackup) {
      $ure_toldAboutBackup = true;
      $mess .= __('Backup record is created for the current role capabilities', 'ure');
    }
  }

  return true;
}
// end of ure_makeRolesBackup()


// Save Roles to database
function ure_saveRolesToDb() {
  global $wpdb, $ure_roles, $ure_capabilitiesToSave, $ure_currentRole;

  $ure_OptionsTable = $wpdb->prefix .'options';
  if (!isset($ure_roles[$ure_currentRole])) {
    $ure_roles[$ure_currentRole]['name'] = $ure_currentRole;
  }
  $ure_roles[$ure_currentRole]['capabilities'] = $ure_capabilitiesToSave;
  $option_name = $wpdb->prefix.'user_roles';
  $serialized_roles = serialize($ure_roles);
  $query = "update $ure_OptionsTable
                set option_value='$serialized_roles'
                where option_name='$option_name'
                limit 1";
  $record = $wpdb->query($query);
  if ($wpdb->last_error) {
    ure_logEvent($wpdb->last_error, true);
    return false;
  }

  return true;
}
// end of saveRolesToDb()


function ure_updateRoles() {
  global $wpdb, $ure_apply_to_all, $ure_roles;

  $ure_toldAboutBackup = false;
  if (is_multisite() && $ure_apply_to_all) {  // update Role for the all blogs/sites in the network
    $old_blog = $wpdb->blogid;
    // Get all blog ids
    $blogIds = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));
    foreach ($blogIds as $blog_id) {
      switch_to_blog($blog_id);
      $ure_roles = ure_getUserRoles();
      if (!$ure_roles) {
        return false;
      }
      if (!ure_makeRolesBackup()) {
        return false;
      }
      if (!ure_saveRolesToDb()) {
        return false;
      }
    }
    switch_to_blog($old_blog);
    $ure_roles = ure_getUserRoles();
  } else {
    if (!ure_makeRolesBackup()) {
      return false;
    }
    if (!ure_saveRolesToDb()) {
      return false;
    }
  }

  return true;
}
// end of ure_updateRoles()


// process new role create request
function ure_newRoleCreate(&$ure_currentRole) {

  global $wp_roles;
  
  $mess = '';
  $ure_currentRole = '';
  if (isset($_GET['user_role']) && $_GET['user_role']) {
    $user_role = utf8_decode(urldecode($_GET['user_role']));
    // sanitize user input for security
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*/', $user_role)) {
      return 'Error! '.__('Error: Role name must contain latin characters and digits only!', 'ure');;
    }  
    if ($user_role) {
      if (!isset($wp_roles)) {
        $wp_roles = new WP_Roles();
      }
      if (isset($wp_roles->roles[$user_role])) {      
        return sprintf('Error! '.__('Role %s exists already', 'ure'), $user_role);
      }
      // add new role to the roles array
      $ure_currentRole = strtolower($user_role);
      $result = add_role($ure_currentRole, $user_role, array('read'=>1, 'level_0'=>1));
      if (!isset($result) || !$result) {
        $mess = 'Error! '.__('Error is encountered during new role create operation', 'ure');
      } else {
        $mess = sprintf(__('Role %s is created successfully', 'ure'), $user_role);
      }
    }
  }
  return $mess;
}
// end of newRoleCreate()


// define roles which we could delete, e.g self-created and not used with any blog user
function getRolesCanDelete($ure_roles) {
  global $wpdb;

  $tableName = $wpdb->prefix.'usermeta';
  $metaKey = $wpdb->prefix.'capabilities';
  $defaultRole = get_option('default_role');
  $standardRoles = array('administrator', 'editor', 'author', 'contributor', 'subscriber');
  $ure_rolesCanDelete = array();
  foreach ($ure_roles as $key=>$role) {
    $canDelete = true;
    // check if it is default role for new users
    if ($key==$defaultRole) {
      $canDelete = false;
      continue;
    }
    // check if it is standard role
    foreach ($standardRoles as $standardRole) {
      if ($key==$standardRole) {
        $canDelete = false;
        break;
      }
    }
    if (!$canDelete) {
      continue;
    }
    // check if user with such role exists
    $query = "SELECT meta_value
                FROM $tableName
                WHERE meta_key='$metaKey' AND meta_value like '%$key%'";
    $ure_rolesUsed = $wpdb->get_results($query);
    if ($ure_rolesUsed && count($ure_rolesUsed>0)) {
      foreach ($ure_rolesUsed as $roleUsed) {
        $roleName = unserialize($roleUsed->meta_value);
        foreach ($roleName as $key1=>$value1) {
          if ($key==$key1) {
            $canDelete = false;
            break;
          }
        }
        if (!$canDelete) {
          break;
        }
      }
    }
    if ($canDelete) {
      $ure_rolesCanDelete[$key] = $role['name'];
    }
  }

  return $ure_rolesCanDelete;
}
// end of getRolesCanDelete()


function ure_deleteRole() {
  global $wp_roles;

  $mess = '';
  if (isset($_GET['user_role']) && $_GET['user_role']) {
    $role = $_GET['user_role'];
    //$result = remove_role($_GET['user_role']);
    // use this modified code from remove_role() directly as remove_role() returns nothing to check
    if (!isset($wp_roles)) {
      $wp_roles = new WP_Roles();
    }
    if (isset($wp_roles->roles[$role])) {
      unset($wp_roles->role_objects[$role]);
      unset($wp_roles->role_names[$role]);
      unset($wp_roles->roles[$role]);
      $result = update_option($wp_roles->role_key, $wp_roles->roles);
    } else {
      $result = false;
    }
    if (!isset($result) || !$result) {
      $mess = 'Error! '.__('Error encountered during role delete operation', 'ure');
    } else {
      $mess = sprintf(__('Role %s is deleted successfully', 'ure'), $role);
    }
    unset($_REQUEST['user_role']);
  }

  return $mess;
}
// end of ure_deleteRole()


function ure_changeDefaultRole() {
  global $wp_roles;

  $mess = '';
  if (!isset($wp_roles)) {
		$wp_roles = new WP_Roles();
  }
  if (isset($_GET['user_role']) && $_GET['user_role']) {
    $errorMessage = 'Error! '.__('Error encountered during default role change operation', 'ure');
    if (isset($wp_roles->role_objects[$_GET['user_role']])) {
      $result = update_option('default_role', $_GET['user_role']);
      if (!isset($result) || !$result) {
        $mess = $errorMessage;
      } else {
        $mess = sprintf(__('Default role for new users is set to %s successfully', 'ure'), $wp_roles->role_names[$_GET['user_role']]);
      }
    } else {
      $mess = $errorMessage;
    }
    unset($_REQUEST['user_role']);
  }

  return $mess;
}
// end of ure_changeDefaultRole()


function ure_ConvertCapsToReadable($capsName) {

  $capsName = str_replace('_', ' ', $capsName);
  $capsName = ucfirst($capsName);

  return $capsName;
}
// ure_ConvertCapsToReadable


function ure_TranslationData() {

// for the translation purpose
  if (false) {
// Standard WordPress roles
    __('Editor', 'ure');
    __('Author', 'ure');
    __('Contributor', 'ure');
    __('Subscriber', 'ure');
// Standard WordPress capabilities
    __('Switch themes', 'ure');
    __('Edit themes', 'ure');
    __('Activate plugins', 'ure');
    __('Edit plugins', 'ure');
    __('Edit users', 'ure');
    __('Edit files', 'ure');
    __('Manage options', 'ure');
    __('Moderate comments', 'ure');
    __('Manage categories', 'ure');
    __('Manage links', 'ure');
    __('Upload files', 'ure');
    __('Import', 'ure');
    __('Unfiltered html', 'ure');
    __('Edit posts', 'ure');
    __('Edit others posts', 'ure');
    __('Edit published posts', 'ure');
    __('Publish posts', 'ure');
    __('Edit pages', 'ure');
    __('Read', 'ure');
    __('Level 10', 'ure');
    __('Level 9', 'ure');
    __('Level 8', 'ure');
    __('Level 7', 'ure');
    __('Level 6', 'ure');
    __('Level 5', 'ure');
    __('Level 4', 'ure');
    __('Level 3', 'ure');
    __('Level 2', 'ure');
    __('Level 1', 'ure');
    __('Level 0', 'ure');
    __('Edit others pages', 'ure');
    __('Edit published pages', 'ure');
    __('Publish pages', 'ure');
    __('Delete pages', 'ure');
    __('Delete others pages', 'ure');
    __('Delete published pages', 'ure');
    __('Delete posts', 'ure');
    __('Delete others posts', 'ure');
    __('Delete published posts', 'ure');
    __('Delete private posts', 'ure');
    __('Edit private posts', 'ure');
    __('Read private posts', 'ure');
    __('Delete private pages', 'ure');
    __('Edit private pages', 'ure');
    __('Read private pages', 'ure');
    __('Delete users', 'ure');
    __('Create users', 'ure');
    __('Unfiltered upload', 'ure');
    __('Edit dashboard', 'ure');
    __('Update plugins', 'ure');
    __('Delete plugins', 'ure');
    __('Install plugins', 'ure');
    __('Update themes', 'ure');
    __('Install themes', 'ure');
    __('Update core', 'ure');
    __('List users', 'ure');
    __('Remove users', 'ure');
    __('Add users', 'ure');
    __('Promote users', 'ure');
    __('Edit theme options', 'ure');
    __('Delete themes', 'ure');
    __('Export', 'ure');
  }
}
// end of ure_TranslationData()


function ure_ArrayUnique($myArray) {
    if (!is_array($myArray)) {
      return $myArray;
    }
    
    foreach ($myArray as $key=>$value) {
      $myArray[$key] = serialize($value);
    }

    $myArray = array_unique($myArray);

    foreach ($myArray as $key=>$value) {
      $myArray[$key] = unserialize($value);
    }

    return $myArray;

} 
// end of ure_ArrayUnique()


// sort 2 dimensional array by column of its sub-array
class ure_TableSorter {
  protected $column;
  
  function __construct($column) {
    $this->column = $column;
  }
  
  function sort($table) {
    usort($table, array($this, 'compare'));
    
    return $table;
  }
  
  function compare($a, $b) {
    if ($a[$this->column] == $b[$this->column]) {
      return 0;
    }
    
    return ($a[$this->column] < $b[$this->column]) ? -1 : 1;
  }
}
// enf of ure_CapsSorter()


function ure_updateUser($user) {
  global $wpdb, $ure_capabilitiesToSave, $ure_currentRole;

  $user->remove_all_caps();
  if (count($user->roles)>0) {
    $userRole = $user->roles[0];
  } else {
    $userRole = '';
  }
  $user->set_role($ure_currentRole);
    
  if (count($ure_capabilitiesToSave)>0) {
    foreach ($ure_capabilitiesToSave as $key=>$value) {
      $user->add_cap($key);
    }
  }
  $user->update_user_level_from_caps();

  return true;
}
// end of ure_updateUser()


function ure_AddNewCapability() {
  global $wp_roles;
  
  $mess = '';
  if (isset($_GET['new_user_capability']) && $_GET['new_user_capability']) {
    $user_capability = utf8_decode(urldecode($_GET['new_user_capability']));
    // sanitize user input for security
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*/', $user_capability)) {
      return 'Error! '.__('Error: Capability name must contain latin characters and digits only!', 'ure');;
    }
   
    if ($user_capability) {
      $user_capability = strtolower($user_capability);
      if (!isset($wp_roles)) {
        $wp_roles = new WP_Roles();
      }
      $wp_roles->use_db = true;
      $administrator = $wp_roles->get_role('administrator');
      if (!$administrator->has_cap($user_capability)) {
        $wp_roles->add_cap('administrator', $user_capability);
        $mess = sprintf(__('Capability %s is added successfully', 'ure'), $user_capability);
      } else {
        $mess = sprintf('Error! '.__('Capability %s exists already', 'ure'), $user_capability);
      }
    }
  }
  
  return $mess;
  
}
// end of ure_AddNewCapability


// returns array of built-in WP capabilities (WP 3.1 wp-admin/includes/schema.php) 
function getBuiltInWPCaps() {
  $caps = array();
	$caps['switch_themes'] = 1;
	$caps['edit_themes'] = 1;
	$caps['activate_plugins'] = 1;
	$caps['edit_plugins'] = 1;
	$caps['edit_users'] = 1;
	$caps['edit_files'] = 1;
	$caps['manage_options'] = 1;
	$caps['moderate_comments'] = 1;
	$caps['manage_categories'] = 1;
	$caps['manage_links'] = 1;
	$caps['upload_files'] = 1;
	$caps['import'] = 1;
	$caps['unfiltered_html'] = 1;
	$caps['edit_posts'] = 1;
	$caps['edit_others_posts'] = 1;
	$caps['edit_published_posts'] = 1;
	$caps['publish_posts'] = 1;
	$caps['edit_pages'] = 1;
	$caps['read'] = 1;
	$caps['level_10'] = 1;
	$caps['level_9'] = 1;
	$caps['level_8'] = 1;
	$caps['level_7'] = 1;
	$caps['level_6'] = 1;
	$caps['level_5'] = 1;
	$caps['level_4'] = 1;
	$caps['level_3'] = 1;
	$caps['level_2'] = 1;
	$caps['level_1'] = 1;
	$caps['level_0'] = 1;
  $caps['edit_others_pages'] = 1;
  $caps['edit_published_pages'] = 1;
  $caps['publish_pages'] = 1;
  $caps['delete_pages'] = 1;
  $caps['delete_others_pages'] = 1;
  $caps['delete_published_pages'] = 1;
  $caps['delete_posts'] = 1;
  $caps['delete_others_posts'] = 1;
  $caps['delete_published_posts'] = 1;
  $caps['delete_private_posts'] = 1;
  $caps['edit_private_posts'] = 1;
  $caps['read_private_posts'] = 1;
  $caps['delete_private_pages'] = 1;
  $caps['edit_private_pages'] = 1;
  $caps['read_private_pages'] = 1;
  $caps['unfiltered_upload'] = 1; 
  $caps['edit_dashboard'] = 1;
  $caps['update_plugins'] = 1;
  $caps['delete_plugins'] = 1;
  $caps['install_plugins'] = 1;
  $caps['update_themes'] = 1;
  $caps['install_themes'] = 1;
  $caps['update_core'] = 1;
  $caps['list_users'] = 1;
  $caps['remove_users'] = 1;
  $caps['add_users'] = 1;
  $caps['promote_users'] = 1;
  $caps['edit_theme_options'] = 1;
  $caps['delete_themes'] = 1;
  $caps['export'] = 1;
  $caps['delete_users'] = 1;
  $caps['create_users'] = 1;

  return $caps;
}
//

// return the array of unused capabilities
function getCapsToRemove() {
  global $wp_roles, $wpdb;

  $fullCapsList = array();
  foreach($wp_roles->roles as $role) {
    foreach ($role['capabilities'] as $key=>$value) {
      $fullCapsList[] = $key;
    }
  }
  $fullCapsList = ure_ArrayUnique($fullCapsList);
  sort($fullCapsList);
  $capsToExclude = getBuiltInWPCaps();
  
  $capsToRemove = array();
  foreach ($fullCapsList as $capability) {
    if (!isset($capsToExclude[$capability])) {
      // check roles
      $capInUse = false;
      foreach ($wp_roles->role_objects as $wp_role) {
        if ($wp_role->name!='administrator') {
          if ($wp_role->has_cap($capability)) {
            $capInUse = true;
            break;
          }
        }
      }
      if (!$capInUse) {
      // check users
        $usersId = $wpdb->get_col( $wpdb->prepare("SELECT $wpdb->users.ID FROM $wpdb->users"));
        foreach ($usersId as $user_id) {
          $user = get_user_to_edit($user_id);
          if (isset($user->roles[0]) && $user->roles[0]=='administrator') {
            continue;
          }
          if ($user->has_cap($capability)) {
            $capInUse = true;
            break;
          }
        }
      }
      if (!$capInUse) {
        $capsToRemove[] = $capability;
      }
    }
  }

  return $capsToRemove;
}
// end of getCapsToRemove()


function getCapsToRemoveHTML() {
  $capsToRemove = getCapsToRemove();
  if (count($capsToRemove)>0) {
    $html = '<select id="remove_user_capability" name="remove_user_capability" width="200" style="width: 200px">';
  foreach ($capsToRemove as $value) {
    $html .= '<option value="'.$value.'">'.$value.'</option>';
  }
    $html .= '</select>';
  } else {
    $html = '';
  }
  
  return $html;
}
// end of getCapsToRemoveHTML()


function ure_removeCapability() {
  global $wp_roles;

  $mess = '';
  if (isset($_GET['removeusercapability']) && $_GET['removeusercapability']) {
    $capability = $_GET['removeusercapability'];
    $capsToRemove = getCapsToRemove();    
    $found = false;
    foreach ($capsToRemove as $cap) {
      if ($cap===$capability) {
        $found = true;
      }
    }
    if (!$found) {
      return sprintf(__('Error! You do not have permission to delete this capability: %s!', 'ure'), $capability);
    }

    foreach ($wp_roles->role_objects as $wp_role) {
      if ($wp_role->has_cap($capability)) {
        $wp_role->remove_cap($capability);
      }
    }
    $mess = sprintf(__('Capability %s is removed successfully', 'ure'), $capability);
  }

  return $mess;
}

// end of ure_removeCapability()
?>
