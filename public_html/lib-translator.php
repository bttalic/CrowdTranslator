<?php

/* when using AJAX the function is specified, this is wherre it is retrieved
 */
if (isset($_REQUEST['function']) && !empty($_REQUEST['function']))
{
	$function = $_REQUEST['function'];
}

/* If the function is set we include the lib-common
  othervise lib-common should be included by the lib user page
 */
  if ((isset($function) && !empty($function)))
  	include_once '../lib-common.php';

  /* Geeklogs security protocol */
  if (strpos(strtolower($_SERVER['PHP_SELF']), 'lib-translator.php') !== false && $function == '')
  {
  	echo COM_refresh($_CONF['site_url'] . '/index.php');
  	exit;
  }

  require_once $_CONF['path_system'] . 'lib-database.php';


/* If the lib is used by AJAX this is where its decided
  which function will be called
 */
  if ($function == 'get_user_translations_table')
  {
  	echo get_user_translations_table();
  } elseif ($function == 'get_translations_table')
  {
  	echo get_translations_table();
  } elseif ($function == 'delete_translation')
  {
  	echo delete_translation();
  } elseif ($function == 'get_user_badges')
  {
  	echo get_user_badges();
  } else if ($function == 'block_user')
  {
  	echo block_user();
  } else if ($function == 'remove_block')
  {
  	echo remove_block();
  }

/**
 * Removing a single translation from translations table as well as its votes from votes table
 * @param integer id the unique id of the translation to be removed
 * @return boolean true if the deletion was successfull, false othervise
 */
function delete_translation($id = null)
{
	global $_TABLES;

	if (isset($_REQUEST['id']) && !empty($_REQUEST['id']))
	{
		$id = $_REQUEST['id'];
	}

	if ($id != null)
	{
		$query = "DELETE FROM {$_TABLES['translations']} WHERE `id`={$id}";
		$result = DB_query($query);
		if ($result)
		{
			$query = "DELETE FROM {$_TABLES['votes']} WHERE `translation_id` = {$id} ";
			$result = DB_query($query);
			return true;
		} else {
			return false;
		}
	}
}

/**
 * 	Get the sum of all aprovals accross translations for current user
 * @return the sum of all approvals for a single user
 */
function get_total_approval_for_user()
{
	global $_USER, $_TABLES;

	$query = "SELECT SUM(`approval_counts`) as sum FROM {$_TABLES['translations']} WHERE `user_id` = {$_USER['uid']}";
	$result = DB_query($query);
	$sum = DB_fetchArray($result)['sum'];
	return $sum > 0 ? $sum : "0";
}

/**
 * @param integer user_id If set to null the function will return all languages being translated
 * @return The HTML of the progress bars for each retrieved language
 */
function get_user_translated_languages($user_id = null)
{

	global $_TABLES;

	$query = "SELECT DISTINCT `language_full_name` as language FROM {$_TABLES['translations']} WHERE `user_id` = {$user_id}";
	$result = DB_query($query);

	$display = '';

	while ($row = DB_fetchArray($result))
	{
		$translated = get_translation_percent($row['language']);
		$translated = round($translated, 2);
		$not_translated = 100 - $translated;

		$query = "SELECT COUNT(`id`) as count FROM {$_TABLES['translations']} WHERE `user_id` = {$user_id} AND `language_full_name`='{$row['language']}'";
		$result2 = DB_query($query);
		$count = DB_fetchArray($result2)['count'];

		$display .= "<div class='index_language_graph'> <h3> {$row['language']} </h3>
		<div class='progress_bar'> <span class='translated' style='width: {$translated}%'> {$translated}% </span> "
		. "<span class='not_translated' style='width: {$not_translated}%'> {$not_translated}% </span> </div> </div>
		<span> Translated by you: {$count} </span>";
	}

	return $display;
}

/**
 *  Calculates the percentage of translation for a language
 * 	@param string language The for which the percentage is calculated
 *   @return float The percentage of translatiousn
 */
function get_translation_percent($language = null)
{

	global $_TABLES;

	if ($language == null)
	{
		$language = $_COOKIE['selected_language'];
	}

	$result = DB_query("SELECT COUNT(`id`) as count FROM {$_TABLES['originals']} ");
	$number_of_original_elements = DB_fetchArray($result)['count'];

	$result = DB_query("SELECT COUNT(DISTINCT `language_array`,`array_key`) as count FROM {$_TABLES['translations']} WHERE `language_full_name`='{$language}'");

	$number_of_translated_elements = DB_fetchArray($result)['count'];
	$translated = ($number_of_translated_elements / $number_of_original_elements) * 100;

	return (float) $translated;
}

/**
 * Retrieves badges accumulated in admin mode retrieves all available badges
 * @param int limit The number of badges to be displayed
 * @param int admin Weather to use user or admin mode 
 */
function get_user_badges($limit = -1, $admin = 0)
{
	global $_USER, $_TABLES;

	$display = '';

	if ($admin == 0)
		$admin = $_REQUEST['admin'];

	if (isset($admin) && !empty($admin) && $admin == 1)
	{

		if ($limit > 0)
			$limit = "LIMIT {$limit}";
		else
			$limit = "";
		$query = "SELECT  `title`, `tooltip`, `image` FROM {$_TABLES['gems']} WHERE '1' {$limit} ";
		$gems = DB_query($query);
		$count = 0;
		while ($gem = DB_fetchArray($gems))
		{
			$display .= display_badge($gem, $count);
			$count++;
		}

		return $display;
	}

	if ($limit > 0)
		$query = "SELECT g.title, g.tooltip, g.image FROM {$_TABLES['awarded_gems']} as a INNER JOIN {$_TABLES['gems']} as g ON a.gem_id = g.gem_id  WHERE a.user_id = {$_USER['uid']} LIMIT {$limit}";
	else
		$query = "SELECT g.title, g.tooltip, g.image FROM {$_TABLES['awarded_gems']} as a INNER JOIN {$_TABLES['gems']} as g ON a.gem_id = g.gem_id  WHERE a.user_id = {$_USER['uid']}";

	$result = DB_query($query);
	$count = 0;
	if (DB_numRows($result) > 0)
	{
		while ($row = DB_fetchArray($result))
		{
			$display .= display_badge($row, $count);
		}
	} else {
		$display = "You don't have any badges... :( Start translating!";
	}
	return $display;
}

/**
 * When displaying badges this is where the actuall HTML code is assembled
 * @param object gem The badge data retrieved from database
 * @param int count Keeps count on number of displayed gems, gems will be displayed 4 in a row
 */
function display_badge($gem, $count)
{

	global $_CONF;

	$base_url = $_CONF['site_url'] . "/crowdtranslator/images/badges/";
	$display = "<div class='achievement' title='{$gem['tooltip']}' >"
	. "<div class='badge' > <img src='{$base_url}{$gem['image']}' /></div>"
	. "<p class='achievement_name'>{$gem['title']}</p></div>";
	if (++$count % 4 == 0)
		$display .= "</br>";


	return $display;
}

/**
 * Get the number of votes casted by current user
 * @return returns number of votes casted by current user
 */
function get_user_votes()
{
	global $_USER, $_TABLES;

	$query = "SELECT COUNT(`user_id`) as count FROM {$_TABLES['votes']} WHERE `user_id` = {$_USER['uid']}";
	$result = DB_query($query);

	return DB_fetchArray($result)['count'];
}

/**
 * Returns number of translations submited by single user or in total depending on rge $admin param
 * @param int admin Indicates if the function will return the number of votes submited by one user or in total
 * @return int returns number of translations submited by current user/in total
 */
function get_translated_count($admin)
{

	global $_TABLES, $_USER;

	if ($admin == 1)
		$query = "SELECT COUNT(`id`) as count FROM {$_TABLES['translations']} WHERE 1";
	else
		$query = "SELECT COUNT(`id`) as count FROM {$_TABLES['translations']} WHERE `user_id`= {$_USER['uid']}";
	$result = DB_query($query);

	return DB_fetchArray($result)['count'];
}

/**
 * @return int number of votes casted accross translations
 */
function get_votes_count()
{
	global $_TABLES;
	$query = "SELECT COUNT(`user_id`) as count FROM {$_TABLES['votes']} WHERE 1";
	$result = DB_query($query);

	return DB_fetchArray($result)['count'];
}

/**
 * Returns translation with the most upvotes
 * @param int criterion if set to zero returns the bigest number of upvotes for translations by current user, otherwise the bigest number of upvotes
 * @return int bigest number of upvotes for single translation
 */
function get_most_upvotes($criterion)
{
	global $_TABLES, $_USER;

	if ($criterion == 0)
		$criterion = "`user_id`={$_USER['uid']}";

	$query = "SELECT MAX(`approval_counts`) as max FROM {$_TABLES['translations']} WHERE {$criterion}";
	$result = DB_query($query);

	$result = DB_fetchArray($result)['max'];

	if (!$result)
		$result = 0;

	return $result;
}

/**
 * Returns number of users translating
 * @return int number of users using the plugin
 */
function get_users_translating()
{
	global $_TABLES;

	$query = "SELECT COUNT( DISTINCT (`user_id`) ) as count FROM {$_TABLES['translations']} WHERE 1";
	$result = DB_query($query);

	return DB_fetchArray($result)['count'];
}

/**
 * Returns number of languages being translated
 * @return int number of distinct language names in the database
 */
function get_languages_translated_count()
{
	global $_TABLES;

	$query = "SELECT  COUNT( DISTINCT `language_full_name`) as count FROM {$_TABLES['translations']} WHERE 1";
	$result = DB_query($query);

	return DB_fetchArray($result)['count'];
}

/**
 * Returns number of translations with negative approval count
 * @return int number of translations with negative aproval_count
 */
function get_translations_with_negative_vote_count()
{
	global $_TABLES;

	$query = "SELECT  COUNT( `id`) as count FROM {$_TABLES['translations']} WHERE `approval_counts`<0 ";
	$result = DB_query($query);

	return DB_fetchArray($result)['count'];
}

/**
 * Returns HTML code for the progress bars of languages being translated
 * @return string HTML code of progress bars for languages being translated
 */
function get_translated_languages()
{
	global $_TABLES;


	$query = "SELECT DISTINCT `language_full_name` as language FROM {$_TABLES['translations']} WHERE 1";
	$result = DB_query($query);

	$display = '';

	while ($row = DB_fetchArray($result))
	{
		$translated = get_translation_percent($row['language']);
		$translated = round($translated, 2);
		$not_translated = 100 - $translated;

		$query = "SELECT COUNT(`id`) as count FROM {$_TABLES['translations']} WHERE `language_full_name`='{$row['language']}'";
		$result2 = DB_query($query);
		$count = DB_fetchArray($result2)['count'];

		$display .= "<div class='index_language_graph'> <h3> {$row['language']} </h3>
		<div class='progress_bar'> <span class='translated' style='width: {$translated}%'> {$translated}% </span> "
		. "<span class='not_translated' style='width: {$not_translated}%'> {$not_translated}% </span> </div> </div>
		<span> Translated: {$count} </span>";
	}

	return $display;
}

/**
 * When an AJAX call is used to show the table with translations the params are retrieved here 
 * @param int &limit the number of translations to be shown
 * @param int &start the first translation to be shown
 * @param string &order_by the ordering of translations
 * @see get_translations_table_query
 */
function get_translations_options(&$limit, &$start, &$order_by)
{


	if (isset($_REQUEST['limit']) && !empty($_REQUEST['limit']))
	{
		$limit = $_REQUEST['limit'];
	}

	if (isset($_REQUEST['start']) && !empty($_REQUEST['start']))
	{
		$start = $_REQUEST['start'];
	}

	if (isset($_REQUEST['order_by']) && !empty($_REQUEST['order_by']))
	{
		$order_by = $_REQUEST['order_by'];
	}
}

/**
 * The html code of the table header for the table displaying translations
 * @param int admin if set to 1 the table will have a username header
 * @param int limit number of translations to be shown, used for onclick method setting inside the headers
 * @return string HTML code for the table  header
 */
function get_translations_table_headers($admin, $limit)
{

	$display = "<table class='translations_view'> <tbody> <tr> ";

	if ($admin == 1)
		$display .= "<th> <a href='javascript:void(0)' onclick='show_more_translations({$limit}, -1, 1, \"`user_id` DESC\")'> User </a> 
	<a href='javascript:void(0)' onclick='show_more_translations({$limit}, -1, {$admin}, \"`user_id` ASC\")'> (ASC) </a> </th>";

	$display .= "<th> <a href='javascript:void(0)' onclick='show_more_translations({$limit}, -1, 1, \"`language_full_name` DESC\")'> Language </a>
	<a href='javascript:void(0)' onclick='show_more_translations({$limit}, -1, {$admin}, \"`language_full_name` ASC\")'> (ASC) </a> </th>
	<th> Translation </a> </th>
	<th> <a href='javascript:void(0)' onclick='show_more_translations({$limit}, -1, {$admin}, \"`approval_counts` DESC\")'> Upvotes </a>
	<a href='javascript:void(0)' onclick='show_more_translations({$limit}, -1, {$admin}, \"`approval_counts` ASC\")'> (ASC) </a> </th> 
	<th> <a href='javascript:void(0)' onclick='show_more_translations({$limit}, -1, {$admin}, \"`posted` DESC\")'> Posted </a> 
	<a href='javascript:void(0)' onclick='show_more_translations({$limit}, -1, {$admin}, \"`posted` ASC\")'> (ASC) </a></th> 
	<th> </th> </tr> ";

	return $display;
}

/**
 * Assembles last row of the translations table ncludes the click for Previous show, Next show and input box for limit
 * @param int previous indicates the first translation to be shown
 * @param int next indicates the first translation to be shown
 * @param int admin used for onclick method inside the code indicating if the table is admin mode or user mode
 * @param int limit used for onclick method inside the code indicating the number of translations to be shown
 * @return string display HTML code of the last table row which includes the click for Previous show, Next show and input box for limit
 */
function get_translations_table_finalize($previous, $next, $admin, $limit)
{

	$display = '';

	if ($previous >= 0)
	{
		$display.="<td>  <a href='javascript:void(0)' onclick='show_more_translations({$limit}, {$previous}, {$admin}, \"{$order_by}\")'> <- Show previous </a> </td>";
	} else {
		$display .= "<td></td>";
	}

	$display .= "<td><label for='limit' >Show: </label> <input type='text' id='limit' class='small' value='{$limit}' onblur='translation_table_change_limit()'/></td>";

	if ($next < get_translated_count($admin))
	{
		$display .=" <td> <a id='show_next' href='javascript:void(0)' onclick='show_more_translations({$limit}, {$next}, {$admin}, \"{$order_by}\")'> Show next -> </a> </td> <td> </td> </tr>";
	} else {
		$display .= "<td></td>";
	}

	return $display;
}

/**
 * Assembles query used for the translations table
 * @param int criterions If 1 we are using admin mode where the translators user name has to be shown
 * @param int start First translation to be shown
 * @param string order_by The ordering rule used in the query
 * @param int limit The number of translations to be shown
 * @return string returns The sql query assembled 
 */
function get_translations_table_query($criterion, $start, $order_by, $limit)
{

	global $_TABLES;

	if ($criterion == 1)
	{
		if ($start >= 0)
		{
			$query = "SELECT t.id, t.language_full_name, concat(hour(TIMEDIFF(NOW(), t.timestamp)), ' hours ago') as `posted`, t.approval_counts,
			t.translation, t.user_id, u.username FROM {$_TABLES['translations']} as t JOIN {$_TABLES['users']} as u ON t.user_id = u.uid WHERE {$criterion} ORDER BY  {$order_by}  LIMIT {$start}, {$limit}";
		} else {
			$query = "SELECT t.id, t.language_full_name, concat(hour(TIMEDIFF(NOW(), t.timestamp)), ' hours ago') as `posted`, t.approval_counts,
			t.translation, t.user_id, u.username  FROM {$_TABLES['translations']} as t JOIN {$_TABLES['users']} as u ON t.user_id = u.uid WHERE {$criterion} ORDER BY  {$order_by} LIMIT  {$limit}";
		}
	} else {
		if ($start >= 0)
		{
			$query = "SELECT `id`, `language_full_name`, concat(hour(TIMEDIFF(NOW(), `timestamp`)), ' hours ago') as `posted`, `approval_counts`,
			`translation`,  `user_id` FROM {$_TABLES['translations']} WHERE {$criterion} ORDER BY  {$order_by}  LIMIT {$start}, {$limit}";
		} else {
			$query = "SELECT `id`, `language_full_name`, concat(hour(TIMEDIFF(NOW(), `timestamp`)), ' hours ago') as `posted`, `approval_counts`,
			`translation`, `user_id` FROM {$_TABLES['translations']} WHERE {$criterion} ORDER BY {$order_by}  LIMIT {$limit}";
		}
	}

	return $query;
}

/**
 * Makes the translations table for admins
 * @see get_user_translations_table for translations table in user mode
 * @param int limit number of translations shown per page, default value is 5
 * @param int start first translation to be shown , default value -1
 * @param string order_by the ordering of the translation default value '`posted`, `id` DESC'
 * @return string HTML code of the table 
 */
function get_translations_table($limit = 5, $start = -1, $order_by = '`posted`, `id` DESC')
{
	global $_TABLES, $_USER;

	get_translations_options($limit, $start, $order_by);

	$query = get_translations_table_query(1, $start, $order_by, $limit);

	$result = DB_query($query);

	$next = ($start + $limit) % $limit == 0 ? $start + $limit : ($start + $limit + 1);
	$previous = $start - $limit;


	$user_names = array();

	if (DB_numRows($result) > 0)
	{
		$display = get_translations_table_headers(1, $limit);
		while ($row = DB_fetchArray($result))
		{

			$class = '';
			if ($row['approval_counts'] < 0)
			{
				$class = 'error';
			}
			$user_id = $row['user_id'];
			$row['posted'] = (int) ($row['posted']) < 24 ? $row['posted'] : ((int) ($row['posted'] / 24) . " days ago");
			$display .= "<tr id='translation_{$row['id']}' class='{$class}'> <td> {$row['username']}<a href='javascript:void(0)' onclick='block_user($user_id)'>(block)</a> </td>
			<td> {$row['language_full_name']} </td> <td> {$row['translation']} </td>  <td> {$row['approval_counts']} </td>
			<td> {$row['posted']} </td>"
			. " <td> <a href='javascript:void(0)' onclick=\"delete_translation({$row['id']}, '{$row['translation']}' )\"> delete </a> </td> </tr>";
		}


		$display .= "<tr> <td> </td> <td> </td>   ";
		$display .= get_translations_table_finalize($previous, $next, 1, $limit);
	} else {
		$display .= "<tr> You have not submited any translations yet </tr>";
	}

	$display .= " </tbody></table>";
	return $display;
}

/**
 * Makes the translations table for users
 * @see get_translations_table_table for translations table in admin mode
 * @param int limit number of translations shown per page, default value is 5
 * @param int start first translation to be shown , default value -1
 * @param string order_by the ordering of the translation default value '`posted`, `id` DESC'
 * @return string HTML code of the table 
 */
function get_user_translations_table($limit = 5, $start = -1, $order_by = '`posted`, `id` DESC')
{
	global $_USER;
	get_translations_options($limit, $start, $order_by);

	$query = get_translations_table_query("`user_id`={$_USER['uid']}", $start, $order_by, $limit);
	$result = DB_query($query);

	if (DB_numRows($result) > 0)
	{
		$display = get_translations_table_headers(0, $limit);
		while ($row = DB_fetchArray($result))
		{
			$class = '';
			if ($row['approval_counts'] < 0)
			{
				$class = 'error';
			}
			$row['posted'] = (int) ($row['posted']) < 24 ? $row['posted'] : ((int) ($row['posted'] / 24) . " days ago");
			$display .= "<tr id='translation_{$row['id']}' class='{$class}'> <td> {$row['language_full_name']} </td> <td> {$row['translation']} </td>  <td> {$row['approval_counts']} </td>  <td> {$row['posted']} </td>"
			. " <td> <a href='javascript:void(0)' onclick=\"delete_translation({$row['id']}, '{$row['translation']}' )\"> delete </a> </td>   </tr>";
		}
		$next = ($start + $limit) % $limit == 0 ? $start + $limit : ($start + $limit + 1);
		$previous = $start - $limit;

		$display .= "<tr>  <td> </td>  ";
		$display .= get_translations_table_finalize($previous, $next, 0, $limit);
	} else {
		$display .= "<tr> No submited translations </tr>";
	}

	$display .= "</tbody></table>";
	return $display;
}

/**
 * Puts specified user on block list, deletes his translations and votes for those translations and awarded gems 
 * @param int user_id ID of user to be blocked
 */
function block_user($user_id = null)
{

	global $_TABLES;

	if (isset($_REQUEST['user_id']) && !empty($_REQUEST['user_id']))
	{
		$user_id = $_REQUEST['user_id'];
	}

	if ($user_id != null)
	{
		$query = "INSERT INTO {$_TABLES['blocked_users']} (`user_id`, `timestamp`) VALUES ({$user_id}, now() ) ";
		$result = DB_query($query);
		echo $result;
		if ($result == true)
		{
			$query = "DELETE t.*, v.* FROM {$_TABLES['translations']} as t JOIN {$_TABLES['votes']} as v ON t.id=v.translation_id WHERE t.user_id = {$user_id}";
			$result = DB_query($query);
			$query = "DELETE FROM {$_TABLES['awarded_gems']} WHERE `user_id` = {$user_id} ";
			$result = DB_query($query);
		}
	}
}

/**
 * Assebles list of blocked users
 * @return string HTML code of a table containing usernames and blocking times of blocked users
 */
function get_blocked_users_table()
{
	global $_TABLES;

	$query = "SELECT b.*, u.username FROM {$_TABLES['blocked_users']} as b JOIN {$_TABLES['users']} AS u ON b.user_id = u.uid";
	$result = DB_query($query);

	if (DB_numRows($result) > 0)
	{
		$display .= "<table class='translations_view'> <tbody>";
		$display .= "<tr> <th> Username </th> <th> Time Blocked </th> <th> </th>";
		$count = 0;
		while ($row = DB_fetchArray($result))
		{

			$user_id = $row['user_id'];
			if (++$count % 2 != 0)
				$display .= "<tr class='error'> ";
			else
				$display .= "<tr>";
			$display .= "<td> {$row['username']} </td> <td> {$row['timestamp']}  </td> <td> <a href='javascript:void(0)' onclick='remove_block($user_id)'> remove block </a> </td> </tr>";
		}
		$display .= "</tbody></table>";
	} else {
		$display .= "No users on this list";
	}
	return $display;
}

/**
 * Removes specified user from block list
 * @param int user_id ID of user to be un-blocked
 */
function remove_block($user_id = null)
{
	global $_TABLES;

	if (isset($_REQUEST['user_id']) && !empty($_REQUEST['user_id']))
	{
		$user_id = $_REQUEST['user_id'];
	}

	if ($user_id != null)
	{
		$query = "DELETE FROM {$_TABLES['blocked_users']} WHERE `user_id` = {$user_id}";
		DB_query($query);
	}
}

?>