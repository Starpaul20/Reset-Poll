<?php
/**
 * Reset Poll
 * Copyright 2010 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Neat trick for caching our custom template(s)
if(THIS_SCRIPT == 'showthread.php')
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'showthread_poll_resetpoll';
}

// Tell MyBB when to run the hooks
$plugins->add_hook("polls_start", "resetpoll_run");
$plugins->add_hook("showthread_poll", "resetpoll_link");
$plugins->add_hook("showthread_poll_results", "resetpoll_link");

// The information that shows up on the plugin manager
function resetpoll_info()
{
	global $lang;
	$lang->load("resetpoll", true);

	return array(
		"name"				=> $lang->resetpoll_info_name,
		"description"		=> $lang->resetpoll_info_desc,
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.0.1",
		"codename"			=> "resetpoll",
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is activated.
function resetpoll_activate()
{
	global $db;

	$insert_array = array(
		'title'		=> 'showthread_poll_resetpoll',
		'template'	=> $db->escape_string(' | <a href="polls.php?action=do_reset&amp;pid={$thread[\'poll\']}&amp;my_post_key={$mybb->post_code}" onclick="if(confirm(&quot;{$lang->reset_poll_confirm}&quot;)) window.location=this.href.replace(\'action=do_reset\',\'action=do_reset\'); return false;">{$lang->reset_poll}</a>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("showthread_poll", "#".preg_quote('{$edit_poll}')."#i", '{$edit_poll}<!-- resetpoll -->');
	find_replace_templatesets("showthread_poll_results", "#".preg_quote('{$edit_poll}')."#i", '{$edit_poll}<!-- resetpoll -->');
}

// This function runs when the plugin is deactivated.
function resetpoll_deactivate()
{
	global $db;
	$db->delete_query("templates", "title IN('showthread_poll_resetpoll')");

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("showthread_poll", "#".preg_quote('<!-- resetpoll -->')."#i", '', 0);
	find_replace_templatesets("showthread_poll_results", "#".preg_quote('<!-- resetpoll -->')."#i", '', 0);
}

// Reset a poll
function resetpoll_run()
{
	global $db, $mybb, $lang;
	$lang->load("resetpoll");

	if($mybb->input['action'] == "do_reset")
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
		$query = $db->simple_select("polls", "*", "pid='{$pid}'");
		$poll = $db->fetch_array($query);

		if(!$poll['pid'])
		{
			error($lang->error_invalidpoll);
		}

		$thread = get_thread($poll['tid']);

		if(!is_moderator($thread['fid'], "canmanagepolls"))
		{
			error_no_permission();
		}

		$query2 = $db->simple_select("pollvotes", "*", "pid='".$poll['pid']."'");
		$vote = $db->fetch_array($query2);

		if(!$vote)
		{
			error($lang->error_novotes);
		}

		$votesarray = explode("||~|~||", $poll['votes']);
		foreach($votesarray as $i => $votes)
		{
			if($votes > 0)
			{
				$votesarray[$i] = 0;
			}
		}
		$voteslist = implode("||~|~||", $votesarray);

		$updatedpoll = array(
			"votes" => $db->escape_string($voteslist),
			"numvotes" => 0,
		);

		$db->delete_query("pollvotes", "pid='".$poll['pid']."'");
		$db->update_query("polls", $updatedpoll, "pid='".$poll['pid']."'");

		log_moderator_action(array("tid" => $thread['tid'], "fid" => $thread['fid'], "poll" => $poll['pid']), $lang->poll_reset);

		redirect(get_thread_link($poll['tid']), $lang->redirect_pollreset);
	}
}

// Show link to reset poll on show thread page
function resetpoll_link()
{
	global $mybb, $lang, $thread, $pollbox, $poll, $templates;
	$lang->load("resetpoll");

	if(is_moderator($thread['fid'], "canmanagepolls") && $poll['numvotes'] > 0)
	{
		eval("\$reset_poll = \"".$templates->get("showthread_poll_resetpoll")."\";");
		$pollbox = str_replace("<!-- resetpoll -->", $reset_poll, $pollbox);
	}
}
