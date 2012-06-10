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

// Tell MyBB when to run the hooks
$plugins->add_hook("global_end", "resetpoll_run");
$plugins->add_hook("showthread_poll", "resetpoll_link");
$plugins->add_hook("showthread_poll_results", "resetpoll_link");

// The information that shows up on the plugin manager
function resetpoll_info()
{
	return array(
		"name"				=> "Reset Poll",
		"description"		=> "Allows moderators and administrators to reset a poll.",
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.1.1",
		"guid"				=> "43bfc97eb3b89db97f52b2ddeb739bf7",
		"compatibility"		=> "16*"
	);
}

// This function runs when the plugin is activated.
function resetpoll_activate()
{
	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("showthread_poll", "#".preg_quote('{$edit_poll}')."#i", '{$edit_poll}<!-- resetpoll -->');
	find_replace_templatesets("showthread_poll_results", "#".preg_quote('{$edit_poll}')."#i", '{$edit_poll}<!-- resetpoll -->');
}

// This function runs when the plugin is deactivated.
function resetpoll_deactivate()
{
	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("showthread_poll", "#".preg_quote('<!-- resetpoll -->')."#i", '', 0);
	find_replace_templatesets("showthread_poll_results", "#".preg_quote('<!-- resetpoll -->')."#i", '', 0);
}

// Reset a poll
function resetpoll_run()
{
	global $db, $mybb, $lang, $headerinclude, $theme;
	$lang->load("resetpoll");

	if(THIS_SCRIPT == "polls.php" && $mybb->input['action'] == "do_reset")
	{
		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		$query = $db->simple_select("polls", "*", "pid='".intval($mybb->input['pid'])."'");
		$poll = $db->fetch_array($query);

		if(!$poll['pid'])
		{
			error($lang->error_invalidpoll);
		}

		$thread = get_thread($poll['tid']);

		if(!is_moderator($thread['fid'], "caneditposts"))
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
	global $db, $mybb, $lang, $thread, $pollbox, $poll;
	$lang->load("resetpoll");

	if(is_moderator($thread['fid'], "caneditposts") && $poll['numvotes'] > 0)
	{
		$reset_poll = " | <a href=\"polls.php?action=do_reset&amp;pid={$thread['poll']}&amp;my_post_key={$mybb->post_code}\" onclick=\"if(confirm(&quot;{$lang->reset_poll_confirm}&quot;)) window.location=this.href.replace('action=do_reset','action=do_reset'); return false;\">{$lang->reset_poll}</a>";
		$pollbox = str_replace("<!-- resetpoll -->", $reset_poll, $pollbox);
	}
}

?>