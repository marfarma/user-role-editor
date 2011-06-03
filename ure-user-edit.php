<?php

/*
 * 
 * User Role Editor plugin: user capabilities editor page
 * 
 */

if (!defined('URE_PLUGIN_URL')) {
  die;  // Silence is golden, direct call is prohibited
}

if (!isset($ure_currentRole) || !$ure_currentRole) {
  if (isset($_REQUEST['user_role']) && $_REQUEST['user_role']) {
    $ure_currentRole = $_REQUEST['user_role'];
  } else if (count($ure_userToEdit->roles)>0) {
    $ure_currentRole = $ure_userToEdit->roles[0];
  } else {
   $ure_currentRole = '';
  }
}


$roleSelectHTML = '<select id="user_role" name="user_role" onchange="ure_Actions(\'role-change\', this.value);">';
foreach ($ure_roles as $key=>$value) {
  $selected = ure_optionSelected($key, $ure_currentRole);
  $roleSelectHTML .= '<option value="'.$key.'" '.$selected.'>'.__($value['name'], 'ure').'</option>';
}
if ($ure_currentRole==-1) {
  $selected = 'selected="selected"';
} else {
  $selected = '';
}
$roleSelectHTML .= '<option value="-1" '.$selected.' >&mdash; No role for this site &mdash;</option>';
$roleSelectHTML .= '</select>';


?>

<div id="post-body-content" class="has-sidebar-content">
<script language="javascript" type="text/javascript">
  function ure_Actions(action, value) {
    var url = '<?php echo URE_WP_ADMIN_URL.'/'.URE_PARENT; ?>?page=user-role-editor.php&object=user&user_id=<?php echo $ure_userToEdit->ID; ?>';
    if (action=='cancel') {
      document.location = url;
      return true;
    } if (action!='update') {
      url += '&action='+ action;
      if (value!='' && value!=undefined) {
        url = url +'&user_role='+ escape(value);
      }
      document.location = url;
    } else {
      document.getElementById('ure-form').submit();
    }
    
  }// end of ure_Actions()


  function ure_onSubmit() {
    if (!confirm('<?php echo sprintf(__('User "%s" update: please confirm to continue', 'ure'), $ure_userToEdit->display_name); ?>')) {
      return false;
    }
  }

</script>
<?php
	ure_displayBoxStart(__('Change capabilities for user', 'ure').' &lt;'.$ure_userToEdit->display_name.'&gt;');
 
?>
  <div style="float: left;"><?php echo __('Role:', 'ure').' '.$roleSelectHTML; ?></div>
  <?php
  if ($ure_caps_readable) {
    $checked = 'checked="checked"';
  } else {
    $checked = '';
  }
?>
  <div style="display:inline;float: right;"><input type="checkbox" name="ure_caps_readable" id="ure_caps_readable" value="1" <?php echo $checked; ?> onclick="ure_Actions('capsreadable');"/>
    <label for="ure_caps_readable"><?php _e('Show capabilities in human readable form', 'ure'); ?></label>
  </div>

  <br/><br/><hr/>  
  <h3><?php _e('Add capabilities to this user:', 'ure'); ?></h3>
  <table class="form-table" style="clear:none;" cellpadding="0" cellspacing="0">
    <tr>
      <td style="vertical-align:top;">
        <?php
        $quant = count($fullCapabilities);
        $quantInColumn = (int) $quant / 3;
        $quantInCell = 0;
        foreach ($fullCapabilities as $capability) {
          $checked = ''; $disabled = '';
          if (isset($ure_roles[$ure_currentRole]['capabilities'][$capability['inner']])) {
            $checked = 'checked="checked"';
            $disabled = 'disabled="disabled"';
          } else if (isset($ure_userToEdit->caps[$capability['inner']])) {
            $checked = 'checked="checked"';
          }
          $cap_id = str_replace(' ', URE_SPACE_REPLACER, $capability['inner']);
        ?>
          <input type="checkbox" name="<?php echo $cap_id; ?>" id="<?php echo $cap_id; ?>" value="<?php echo $capability['inner']; ?>" <?php echo $checked; ?> <?php echo $disabled; ?>/>
        <?php
          if ($ure_caps_readable) {
        ?>
            <label for="<?php echo $cap_id; ?>" title="<?php echo $capability['inner']; ?>" ><?php echo $capability['human']; ?></label><br/>
        <?php
          } else {
        ?>
            <label for="<?php echo $cap_id; ?>" title="<?php echo $capability['human']; ?>" ><?php echo $capability['inner']; ?></label><br/>
        <?php
          }
          $quantInCell++;
          if ($quantInCell >= $quantInColumn) {
            $quantInCell = 0;
            echo '</td>
           <td style="vertical-align:top;">';
          }
        }
        ?>
      </td>
    </tr>
  </table>
  <hr/>
  <input type="hidden" name="object" value="user" />
  <input type="hidden" name="user_id" value="<?php echo $ure_userToEdit->ID; ?>" />
  <div class="submit" style="padding-top: 0px;">
    <div style="float:left; padding-bottom: 10px;">
        <input type="submit" name="submit" value="<?php _e('Update', 'ure'); ?>" title="<?php _e('Save Changes', 'ure'); ?>" />
        <input type="button" name="cancel" value="<?php _e('Cancel', 'ure') ?>" title="<?php _e('Cancel not saved changes','ure');?>" onclick="ure_Actions('cancel');"/>
    </div>
  </div>

<?php
  ure_displayBoxEnd();
?>
  
</div>

