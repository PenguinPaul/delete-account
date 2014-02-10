<?php

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Nope.  Also, the game.");
}


//add our hooks
$plugins->add_hook("usercp_start", "deleteaccount");
$plugins->add_hook("pre_output_page", "deleteaccount_menu");
//admin
$plugins->add_hook('admin_load', 'deleteaccount_admin');
$plugins->add_hook('admin_user_menu', 'deleteaccount_admin_user_menu');
$plugins->add_hook('admin_user_action_handler', 'deleteaccount_admin_user_action_handler');


//basic plugin info for ACP
function deleteaccount_info()
{
	global $lang;

	// Save loading if we already have our language variables
	if(!$lang->da_delete_account_name)
	{
		$lang->load('deleteaccount');
	}

	return array(
		"name"			=> $lang->da_delete_account_name,
		"description"	=> $lang->da_delete_account_desc,
		"website"		=> "https://github.com/PenguinPaul/delete-account",
		"author"		=> "MyBB Security Group",
		"authorsite"	=> "http://www.mybbsecurity.net",
		"version"		=> "1.1",
		"guid" 			=> "",
		"compatibility" => "*"
	);
}

function deleteaccount_install()
{
	global $db, $lang;

	// Save loading if we already have our language variables
	if(!$lang->da_delete_account_name)
	{
		$lang->load('deleteaccount');
	}

	// Settings group
	$group = array(
		'name'			=> 'deleteaccount',
		'title'			=> $lang->da_settings_name,
		'description'	=> $lang->da_settings_desc,
		'disporder'		=> 23,
		'isdefault'		=> 0,
	);
	$db->insert_query('settinggroups', $group);
	$gid = intval($db->insert_id());

	// Settings

	$setting = array(
		'name'			=> 'deleteaccount_normgroups',
		'title'			=> $lang->da_settings_normgroup_name,
		'description'	=> $lang->da_settings_normgroup_desc,
		'optionscode'	=> 'text',
		'value'			=> '2,3,4,6',
		'disporder'		=> 1,
		'gid'			=> $gid,
	);
	$db->insert_query('settings', $setting);


	rebuild_settings();

	// Make our DB table - a clone of the defualt MyBB users table plus some added fields
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."deleted_users AS (SELECT * FROM ".TABLE_PREFIX."users WHERE 0);");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."deleted_users
		ADD deletedtime bigint(30) NOT NULL default '0',
		ADD deletereason text NOT NULL,
		ADD deleteip varchar(50) NOT NULL default '',
		ADD longdeleteip int(11) NOT NULL default '0';");

}

function deleteaccount_is_installed()
{
	global $db;

	// It's installed if the DB table exists
	return $db->table_exists("deleted_users");
}

function deleteaccount_activate()
{
	global $db;

	// Make the necessary replacements
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("usercp_nav_profile", "#".preg_quote('{$changesigop}')."#i", '{$changesigop}{delete_account_link}');

	// Create our template
	$template_content = '<html>
<head>
<title>{$lang->user_cp}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
{$usercpnav}
<td valign="top">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="{$colspan}"><strong>{$lang->da_delete_account}</strong></td>
</tr>
<tr>
<td class="trow1" align="center">
{$lang->da_are_you_sure}
<br /><br />
<form action="usercp.php" method="post">
<textarea id="message" name="message" rows="5" cols="50">{$lang->da_why}</textarea><br /><br />
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="action" value="do_deleteaccount" />
<input type="submit" value="{$lang->da_yes}" />
</form>
</td>
</tr>
</table>
</td>
</tr>
</table>
{$footer}
</body>
</html>';

	// Insert array
	$template = array(
		"title"		=> "da_delete_account",
		"template"	=> $db->escape_string($template_content),
		"sid"		=> "-1"
	);

	// Add to the global templates set
	$db->insert_query("templates", $template);
}

function deleteaccount_deactivate()
{
	global $db;

	// Undo our replacements
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("usercp_nav_profile", "#".preg_quote('{delete_account_link}')."#i", '');

	// Delete our templates
	$db->delete_query("templates", "title IN ('da_delete_account')");
}

function deleteaccount_uninstall()
{
	global $mybb;

	// Confirm that they really want to uninstall...
	if($mybb->input['no'])
	{
		// User clicked no, return to plugins page
		admin_redirect("index.php?module=config-plugins");
	}
	else
	{
		// Have we show the confirmation?
		if($mybb->request_method == "post")
		{
			// Yes, they really want this plugin gone, ohwell...
			global $db;

			// Remove settings
			$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name IN ('deleteaccount_normgroups')");
			$db->query("DELETE FROM ".TABLE_PREFIX."settinggroups WHERE name='deleteaccount'");

			rebuild_settings();

			// Drop our database
			$db->write_query("DROP TABLE ".TABLE_PREFIX."deleted_users");
		}
		else
		{
			global $lang, $page;

			// Save loading if we already have our language variables
			if(!$lang->da_delete_account_name)
			{
				$lang->load('deleteaccount');
			}

			$page->output_confirm_action("index.php?module=config-plugins&action=deactivate&uninstall=1&plugin=deleteaccount&my_post_key={$mybb->post_code}", $lang->da_uninstall_warning);
		}
	}
}


function deleteaccount()
{
	global $mybb;

	if($mybb->input['action'] == "deleteaccount" || $mybb->input['action'] == "do_deleteaccount")
	{
		global $lang, $db, $header, $footer, $headerinclude, $theme, $lang, $usercpnav, $usercpmenu, $templates;

		// Save loading if we already have our language variables
		if(!$lang->da_delete_account_name)
		{
			$lang->load('deleteaccount');
		}
	}

	if($mybb->input['action'] == "deleteaccount")
	{
		// If you can't delete your account you can't see this!
		if(!can_delete_account()) { error_no_permission(); }

		add_breadcrumb($lang->da_delete_account);

		eval("\$page = \"".$templates->get("da_delete_account")."\";");

		// Spit out the page!
		output_page($page);
	}

	if($mybb->input['action'] == 'do_deleteaccount')
	{
		// No CSRF here!
		verify_post_check($mybb->input['my_post_key']);

		// Get the user
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='{$mybb->user['uid']}'");
		$user_array = $db->fetch_array($query);

		// Get fields from deleted_users table
		$query = $db->query("SHOW FIELDS FROM ".TABLE_PREFIX."deleted_users");
		$d_fields_array = array();
		while($row = $db->fetch_array($query))
		{
			$d_fields_array[] = $row;
		}

		// Get fields from users table
		$query = $db->query("SHOW FIELDS FROM ".TABLE_PREFIX."users");
		$fields_array = array();
		while($row = $db->fetch_array($query))
		{
			$fields_array[] = $row;
		}

		$update_array = array(
			'deletedtime'  => TIME_NOW,
			'deletereason' => $db->escape_string($mybb->input['message']),
			'deleteip'     => $db->escape_string(get_ip()),
			'longdeleteip' => intval(my_ip2long(get_ip()))
		);

		// Clean up the array of columns for deleted_users table
		$i = 0;
		$cols = array();
		while($i < count($d_fields_array))
		{
			$cols[$d_fields_array[$i]['Field']] = 1;
			$i++;
		}

		// Clean up the array of columns for users table
		$i = 0;
		$user_cols = array();
		while($i < count($fields_array))
		{
			$user_cols[$fields_array[$i]['Field']] = $fields_array[$i];
			$i++;
		}

		// Go through each of the user's fields
		$add_columns = "";
		$add_keys = "";
		foreach($user_array as $key => $val)
		{
			// Is this field in deleted_users table?
			if(!isset($cols[$key]))
			{
				// It's not, we better get it there quick
				$add_columns .= "ADD ".$key." ".$user_cols[$key]['Type'];

				if($user_cols[$key]['Null'] == "NO")
				{
					$add_columns .= " NOT NULL";
				}
				else
				{
					$add_columns .= " NULL";
				}

				if(!empty($user_cols[$key]['Default']))
				{
					$add_columns .= " default '".$user_cols[$key]['Default']."'";
				}

				if(!empty($user_cols[$key]['Extra']))
				{
					$add_columns .= " ".$user_cols[$key]['Extra'];
				}

				$add_columns .= ",";

				// Does this have a key?
				if(!empty($user_cols[$key]['Key']))
				{
					$add_keys .= $user_cols[$key]['Extra'].$key.",";
				}
			}

			$update_array[$key] = $val;
		}

		if(!empty($add_columns))
		{
			// Trim trailing comma
			$add_columns = rtrim($add_columns, ",");

			if(!empty($add_keys))
			{
				$add_keys = " ".rtrim($add_keys, ",");
			}

			// If there were columns missing from our table, add them in
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."deleted_users {$add_columns}{$add_keys};");
		}

		// Insert into deleted users table
		$db->insert_query("deleted_users", $update_array);

		// Delete from users table
		$db->delete_query("users", "uid='{$mybb->user['uid']}'");

		redirect("index.php", $lang->da_redirect);
	}

}

function deleteaccount_menu($page)
{
	if(THIS_SCRIPT == 'usercp.php')
	{
		if(can_delete_account())
		{
			global $lang;

			// Save loading if we already have our language variables
			if(!$lang->da_delete_account_name)
			{
				$lang->load('deleteaccount');
			}

			// Show link if you can delete your account
			$page = str_replace('{delete_account_link}', '<div><a href="usercp.php?action=deleteaccount" class="usercp_nav_item usercp_nav_trash_pmfolder">'.$lang->da_delete_account.'</a></div>', $page);
		} else {
			// If you can't, then don't show the link
			$page = str_replace('{delete_account_link}', '', $page);
		}
	}

	return $page;
}

function can_delete_account()
{
	global $mybb;

	$groups = explode(",", $mybb->settings['deleteaccount_normgroups']);

	if(in_array($mybb->user['usergroup'], $groups))
	{
		return true;
	} else {
		return false;
	}
}

////////////////////////////////////////////
//admin stuff
////////////////////////////////////////////

function deleteaccount_admin_user_menu(&$sub_menu)
{
	global $lang;

	// Save loading if we already have our language variables
	if(!$lang->da_delete_account_name)
	{
		$lang->load('deleteaccount');
	}

	$sub_menu[] = array('id' => 'deleteaccount', 'title' => $lang->da_deleted_accounts, 'link' => 'index.php?module=user-deleteaccount');
}

function deleteaccount_admin_user_action_handler(&$actions)
{
	$actions['deleteaccount'] = array('active' => 'deleteaccount', 'file' => 'deleteaccount');
}

function deleteaccount_admin()
{
	global $run_module, $action_file;

	if($run_module == 'user' && $action_file == 'deleteaccount')
	{
		global $db, $lang, $mybb, $page, $plugins, $cache;

		// Save loading if we already have our language variables
		if(!$lang->da_delete_account_name)
		{
			$lang->load('deleteaccount');
		}

		$page->add_breadcrumb_item($lang->da_deleted_accounts, 'index.php?module=user-deleteaccount');

		if($mybb->input['action'] == 'restore')
		{
			verify_post_check($mybb->input['my_post_key']);

			$uid = intval($mybb->input['uid']);
			$query = $db->simple_select("deleted_users", "*", "uid='{$uid}'");

			if($db->num_rows($query) == 0)
			{
				flash_message($lang->da_invalid_user, 'error');
				admin_redirect('index.php?module=user-deleteaccount');
			} else {
				$user = $db->fetch_array($query);
			}

			unset($user['deletereason']);
			unset($user['deletedtime']);
			unset($user['deleteip']);
			unset($user['longdeleteip']);

			$db->insert_query("users", $user);
			$db->delete_query("deleted_users", "uid='{$user['uid']}'");

			log_admin_action($lang->da_restored, $user['username']);

			flash_message($lang->da_user_restored, 'success');
			admin_redirect('index.php?module=user-deleteaccount');
		}

		if($mybb->input['action'] == 'delete')
		{
			verify_post_check($mybb->input['my_post_key']);

			$uid = intval($mybb->input['uid']);
			$query = $db->simple_select("deleted_users", "*", "uid='{$uid}'");

			if($db->num_rows($query) == 0)
			{
				flash_message($lang->da_invalid_user, 'error');
				admin_redirect('index.php?module=user-deleteaccount');
			} else {
				$user = $db->fetch_array($query);
			}

			// Delete the user
			$db->update_query("posts", array('uid' => 0), "uid='{$user['uid']}'");
			$db->delete_query("userfields", "ufid='{$user['uid']}'");
			$db->delete_query("privatemessages", "uid='{$user['uid']}'");
			$db->delete_query("events", "uid='{$user['uid']}'");
			$db->delete_query("forumsubscriptions", "uid='{$user['uid']}'");
			$db->delete_query("threadsubscriptions", "uid='{$user['uid']}'");
			$db->delete_query("sessions", "uid='{$user['uid']}'");
			$db->delete_query("banned", "uid='{$user['uid']}'");
			$db->delete_query("threadratings", "uid='{$user['uid']}'");
			$db->delete_query("users", "uid='{$user['uid']}'");
			$db->delete_query("joinrequests", "uid='{$user['uid']}'");
			$db->delete_query("warnings", "uid='{$user['uid']}'");
			$db->delete_query("reputation", "uid='{$user['uid']}' OR adduid='{$user['uid']}'");
			$db->delete_query("awaitingactivation", "uid='{$uid}'");

			// Update forum stats
			update_stats(array('numusers' => '-1'));

			// Did this user have an uploaded avatar?
			if($user['avatartype'] == "upload")
			{
				// Removes the ./ at the beginning the timestamp on the end...
				@unlink("../".substr($user['avatar'], 2, -20));
			}

			// Was this user a moderator?
			if(is_moderator($user['uid']))
			{
				$db->delete_query("moderators", "id='{$user['uid']}' AND isgroup = '0'");
				$cache->update_moderators();
			}

			$plugins->run_hooks("admin_user_users_delete_commit");

			$db->delete_query("deleted_users", "uid='{$mybb->user['uid']}'");

			log_admin_action($lang->da_deleted, $user['username']);

			flash_message($lang->da_permanently_deleted, 'success');
			admin_redirect('index.php?module=user-deleteaccount');
		}

		if(!$mybb->input['action'])
		{
			$page->output_header($lang->da_deleted_accounts);

			$sub_tabs['deleted_accounts'] = array(
				'title'	      => $lang->da_deleted_accounts,
				'link'            => 'index.php?module=user-deleteaccount',
				'description' => $lang->da_deleted_accounts_desc
			);

			$page->output_nav_tabs($sub_tabs, 'deleted_accounts');

			$table = new Table;
			$table->construct_header('UID', array('class' => "align_center"));
			$table->construct_header($lang->da_username, array('class' => "align_center"));
			$table->construct_header($lang->da_delete_reason, array('class' => "align_center"));
			$table->construct_header($lang->da_delete_time, array('class' => "align_center"));
			$table->construct_header($lang->da_delete_ip, array('class' => "align_center"));
			$table->construct_header($lang->controls, array('class' => "align_center", 'colspan' => 2));

			$query = $db->simple_select('deleted_users', '*');

			while($user = $db->fetch_array($query))
			{
				$table->construct_cell($user['uid'], array('width' => 1));
				$table->construct_cell($user['username'], array('class' => "align_center"));
				$table->construct_cell($user['deletereason'], array('class' => "align_center"));
				$table->construct_cell(my_date("F j, Y, g:i a", $user['deletedtime']), array('class' => "align_center"));
				$table->construct_cell($user['deleteip'], array('class' => "align_center"));
				$table->construct_cell("<a href=\"index.php?module=user-deleteaccount&amp;action=restore&amp;uid={$user['uid']}&amp;my_post_key={$mybb->post_code}\">{$lang->da_restore}</a>", array("class" => "align_center"));
				$table->construct_cell("<a href=\"index.php?module=user-deleteaccount&amp;action=delete&amp;uid={$user['uid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->da_confirm_account_deletion}')\">{$lang->da_delete}</a>", array("class" => "align_center"));
				$table->construct_row();
			}

			if($table->num_rows() == 0)
			{
			   	$table->construct_cell($lang->da_no_deleted_accounts, array('colspan' => 5));
			   	$table->construct_row();
			}

			$table->output($lang->da_deleted_accounts);

			$page->output_footer();
		}
	}
}
?>
