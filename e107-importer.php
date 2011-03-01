<?php
/*
Plugin Name: e107 Importer
Plugin URI: http://github.com/kdeldycke/e107-importer
Description: e107 import plugin for WordPress.
Author: Kevin Deldycke
Author URI: http://kevin.deldycke.com
Version: 1.2.dev
Stable tag: 1.1
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

if ( !defined('WP_LOAD_IMPORTERS') )
  return;

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
  $class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
  if ( file_exists( $class_wp_importer ) )
    require_once $class_wp_importer;
}


// Constant
define("E107_IMPORTER_PATH"    , WP_PLUGIN_DIR . '/e107-importer/');
define("E107_INCLUDES_FOLDER"  , 'e107-includes');
define("E107_REDIRECTOR_PLUGIN", 'e107-importer/e107-redirector.php');
define("BBPRESS_PLUGIN"        , 'bbpress/bbpress.php');


if ( class_exists( 'WP_Importer' ) ) {
class e107_Import extends WP_Importer {
  // Class wide variables
  var $e107_db;

  var $e107_db_host;
  var $e107_db_user;
  var $e107_db_pass;
  var $e107_db_name;
  var $e107_db_prefix;

  var $e107_content_ownership;
  var $e107_mail_user;
  var $e107_import_news;
  var $e107_extended_news;
  var $e107_import_pages;
  var $e107_bbcode_parser;
  var $e107_import_images;

  var $user_mapping       = array();
  var $news_mapping       = array();
  var $category_mapping   = array();
  var $page_mapping       = array();
  var $comment_mapping    = array();
  var $forum_mapping      = array();
  var $forum_post_mapping = array();

  // Initialized in initImportContext()
  var $e107_pref;
  var $e107_parser;

  // Convert unix timestamp to mysql datetimestamp
  function mysql_date($unix_time) {
    return date("Y-m-d H:i:s", $unix_time);
  }


  // Delete all occurrences of a given char at the end of the string
  function deleteTrailingChar($s, $c) {
    $s = (string) $s;
    $c = (string) $c;
    while ($s[strlen($s)-1] == $c)
      $s = substr($s, 0, strlen($s)-1);
    return $s;
  }


  // Convert hexadecimal IP address string to decimal
  function ip_hex2dec($hex_ip) {
    if (strlen($hex_ip) != 8)
      return '';
    $dec_ip  = (string) hexdec(substr($hex_ip, 0, 2));
    $dec_ip .= '.';
    $dec_ip .= (string) hexdec(substr($hex_ip, 2, 2));
    $dec_ip .= '.';
    $dec_ip .= (string) hexdec(substr($hex_ip, 4, 2));
    $dec_ip .= '.';
    $dec_ip .= (string) hexdec(substr($hex_ip, 6, 2));
    return $dec_ip;
  }


  // Generic code to initialize the e107 context
  function inite107Context() {
    /* Some part of the code below is copy of (and/or inspired by) code from the e107 project, licensed
    ** under the GPL and (c) copyrighted to e107's contributors.
    */

    // Global variables used in replaceConstants() method
    global $ADMIN_DIRECTORY, $FILES_DIRECTORY, $IMAGES_DIRECTORY, $THEMES_DIRECTORY, $PLUGINS_DIRECTORY, $HANDLERS_DIRECTORY, $LANGUAGES_DIRECTORY, $HELP_DIRECTORY, $DOWNLOADS_DIRECTORY;

    // Define e107 original path structure
    $ADMIN_DIRECTORY     = "e107_admin/";
    $FILES_DIRECTORY     = "e107_files/";
    $IMAGES_DIRECTORY    = "e107_images/";
    $THEMES_DIRECTORY    = "e107_themes/";
    $PLUGINS_DIRECTORY   = "e107_plugins/";
    $HANDLERS_DIRECTORY  = "e107_handlers/";
    $LANGUAGES_DIRECTORY = "e107_languages/";
    $HELP_DIRECTORY      = "e107_docs/help/";
    $DOWNLOADS_DIRECTORY = "e107_files/downloads/";

    // Redifine some globals to match WordPress file hierarchy
    define("e_BASE"   , ABSPATH);
    define("e_FILE"   , E107_IMPORTER_PATH);
    define("e_PLUGIN" , E107_IMPORTER_PATH);
    define("e_HANDLER", E107_IMPORTER_PATH . E107_INCLUDES_FOLDER . '/');

    // Don't know why but in certain cases e_IMAGE was not defined
    define("e_IMAGE" , $IMAGES_DIRECTORY);

    // Set user-related globals referenced in e_parse_class.php
    define("ADMIN" , True);  // Will replace {e_ADMIN} constant with $ADMIN_DIRECTORY
    define("USER"  , False); // Will not replace {USERID} constant
    define("USERID", 0);

    // CHARSET is normally set in e107_languages/English/English.php file
    define("CHARSET", "utf-8");

    /*========== START of code inspired by class2.php file ==========
    + ----------------------------------------------------------------------------+
    |     e107 website system
    |
    |     Copyright (C) 2001-2002 Steve Dunstan (jalist@e107.org)
    |     Copyright (C) 2008-2010 e107 Inc (e107.org)
    |
    |     Released under the terms and conditions of the
    |     GNU General Public License (http://gnu.org).
    |
    |     $URL: https://e107.svn.sourceforge.net/svnroot/e107/trunk/e107_0.7/class2.php $
    |     $Revision: 11786 $
    |     $Id: class2.php 11786 2010-09-15 22:12:49Z e107coders $
    |     $Author: e107coders $
    +----------------------------------------------------------------------------+
    */
    define("e107_INIT", True);

    // Create a new e107 parser
    require_once(e_HANDLER.'e_parse_class.php');
    $this->e107_parser = new e_parse;

    // $tp is required by bbcode_handler.php
    global $tp;
    $tp = $this->e107_parser;

    define("THEME", "");
    define("E107_DEBUG_LEVEL", False);
    define('E107_DBG_BBSC',    False);    // Show BBCode / Shortcode usage in postings

    function check_class($var, $userclass='', $peer=False, $debug=False) {
      return True;
    }

    function include_lan($path, $force=False) {
      return '';
    }

    // Use these to combine isset() and use of the set value. or defined and use of a constant
    // i.e. to fix  if($pref['foo']) ==> if ( varset($pref['foo']) ) will use the pref, or ''.
    // Can set 2nd param to any other default value you like (e.g. false, 0, or whatever)
    // $testvalue adds additional test of the value (not just isset())
    // Examples:
    // $something = pref;  // Bug if pref not set         ==> $something = varset(pref);
    // $something = isset(pref) ? pref : "";              ==> $something = varset(pref);
    // $something = isset(pref) ? pref : default;         ==> $something = varset(pref,default);
    // $something = isset(pref) && pref ? pref : default; ==> use varsettrue(pref,default)
    //
    function varset(&$val,$default='') {
            if (isset($val)) {
                    return $val;
            }
            return $default;
    }
    function defset($str,$default='') {
            if (defined($str)) {
                    return constant($str);
            }
            return $default;
    }
    //
    // These variants are like the above, but only return the value if both set AND 'true'
    //
    function varsettrue(&$val,$default='') {
            if (isset($val) && $val) return $val;
            return $default;
    }
    function defsettrue($str,$default='') {
            if (defined($str) && constant($str)) return constant($str);
            return $default;
    }
    /*========== END of code inspired by class2.php file ==========*/

    // Load preferences if not already loaded
    if (!isset($this->e107_pref) || !is_array($this->e107_pref))
      $this->loadE107Preferences();

    // Override BBCode definition files configuration
    $this->e107_pref['bbcode_list'] = array();
    $this->e107_pref['bbcode_list'][E107_INCLUDES_FOLDER] = array();
    // This $core_bb array come from bbcode_handler.php
    $core_bb = array(
    'blockquote', 'img', 'i', 'u', 'center',
    '_br', 'color', 'size', 'code',
    'html', 'flash', 'link', 'email',
    'url', 'quote', 'left', 'right',
    'b', 'justify', 'file', 'stream',
    'textarea', 'list', 'php', 'time',
    'spoiler', 'hide', 'youtube', 'sanitised'
    );

    foreach ($core_bb as $c)
      $this->e107_pref['bbcode_list'][E107_INCLUDES_FOLDER][$c] = 'dummy_u_class';

    // Don't transform smileys to <img>, WordPress will do it automaticcaly
    $this->e107_pref['smiley_activate'] = False;

    // Turn-off profanity filter
    $this->e107_pref['profanity_filter'] = False;

    // Disable all extra HTML rendering hooks like the one comming from e107 Linkwords plugin
    $this->e107_pref['tohtml_hook'] = '';

    // Set global SITEURL as it's used by replaceConstants() method
    $site_url = $this->e107_pref['siteurl'];
    // Normalize URL: it must end with a single slash
    define("SITEURL", $this->deleteTrailingChar($site_url, '/').'/');

    // Required to make default e107 methods aware of preferences
    global $pref;
    $pref = $this->e107_pref;
  }


  // Establish a connection to the e107 database.
  // This code is kept in a separate method to not mess with $wpdb ...
  function connectToE107DB() {
    $this->e107db = mysql_connect($this->e107_db_host, $this->e107_db_user, $this->e107_db_pass) or
      wp_die("Can't connect to e107 database server: " . mysql_error());
    $this->e107_db_prefix = $this->e107_db_name.'`.`'.$this->e107_db_prefix;
    set_magic_quotes_runtime(0);
  }


  function queryE107DB($sql) {
    $result = mysql_query($sql, $this->e107db);
    if (!$result)
      wp_die('Invalid query: ' . mysql_error());
    $result_array = array();
    $num_rows = 0;
    while ($row = @mysql_fetch_object($result)) {
      $result_array[$num_rows] = (array) $row;
      $num_rows++;
    }
    @mysql_free_result($result);
    return $result_array;
  }


  function loadE107Preferences() {
    $e107_coreTable = $this->e107_db_prefix.'core';
    $sql = "SELECT e107_value FROM `".$e107_coreTable."` WHERE e107_name = 'SitePrefs'";
    $site_pref = $this->queryE107DB($sql);
    extract($site_pref[0]);
    $this->e107_pref = '';
    @eval('$this->e107_pref = '.trim($e107_value).';');
  }


  function getE107UserList() {
    // Prepare the SQL request
    $e107_userTable         = $this->e107_db_prefix."user";
    $e107_userExtendedTable = $this->e107_db_prefix."user_extended";
    $sql  = "SELECT `".$e107_userTable."`.* FROM `".$e107_userTable."` ";
    $sql .= "LEFT JOIN `".$e107_userExtendedTable."` ON `".$e107_userTable."`.user_id = `".$e107_userExtendedTable."`.user_extended_id ";
    // Exclude banned and un-verified users
    $sql .= "WHERE user_ban = 0";
    // Perform the request and return rows
    return $this->queryE107DB($sql);
  }


  function getE107CategoryList() {
    // Prepare the SQL request
    $e107_newsCategoryTable = $this->e107_db_prefix."news_category";
    $sql = "SELECT * FROM `".$e107_newsCategoryTable."`";
    // Perform the request and return rows
    return $this->queryE107DB($sql);
  }


  function getE107NewsList() {
    // Prepare the SQL request
    $e107_newsTable = $this->e107_db_prefix."news";
    $sql = "SELECT * FROM `".$e107_newsTable."`";
    // Perform the request and return rows
    return $this->queryE107DB($sql);
  }


  function getE107PageList() {
    // Prepare the SQL request
    $e107_pagesTable = $this->e107_db_prefix."page";
    $sql = "SELECT * FROM `".$e107_pagesTable."`";
    // Perform the request and return rows
    return $this->queryE107DB($sql);
  }


  function getE107CommentList() {
    // Prepare the SQL request
    $e107_commentsTable = $this->e107_db_prefix."comments";
    $sql  = "SELECT * FROM `".$e107_commentsTable."`";
    // Perform the request and return rows
    return $this->queryE107DB($sql);
  }


  function getE107ForumList() {
    // Prepare the SQL request
    $e107_forumsTable = $this->e107_db_prefix."forum";
    $sql  = "SELECT * FROM `".$e107_forumsTable."` ORDER BY forum_parent, forum_id";
    // Perform the request and return rows
    return $this->queryE107DB($sql);
  }


  function getE107ForumPostList() {
    // Prepare the SQL request
    $e107_postsTable = $this->e107_db_prefix."forum_t";
    $sql  = "SELECT * FROM `".$e107_postsTable."` ORDER BY thread_parent, thread_id";
    // Perform the request and return rows
    return $this->queryE107DB($sql);
  }


  // Get a list of all WordPress's user IDs
  function getWPUserIDs() {
    global $wpdb;
    return $wpdb->get_col($wpdb->prepare("SELECT $wpdb->users.ID FROM $wpdb->users ORDER BY %s ASC", 'ID'));
  }


  // Load pre-existing e107 Redirector mappings
  function loadE107Mapping() {
    if (get_option('e107_redirector_news_mapping'))       $this->news_mapping       = get_option('e107_redirector_news_mapping');
    if (get_option('e107_redirector_category_mapping'))   $this->category_mapping   = get_option('e107_redirector_category_mapping');
    if (get_option('e107_redirector_page_mapping'))       $this->page_mapping       = get_option('e107_redirector_page_mapping');
    if (get_option('e107_redirector_comment_mapping'))    $this->comment_mapping    = get_option('e107_redirector_comment_mapping');
    if (get_option('e107_redirector_user_mapping'))       $this->user_mapping       = get_option('e107_redirector_user_mapping');
    if (get_option('e107_redirector_forum_mapping'))      $this->forum_mapping      = get_option('e107_redirector_forum_mapping');
    if (get_option('e107_redirector_forum_post_mapping')) $this->forum_post_mapping = get_option('e107_redirector_forum_post_mapping');
  }


  // Import e107 preferences (aka global config)
  function importPreferences() {
    global $wpdb;
    update_option('blogname'            ,  $this->e107_pref['sitename']);
    update_option('admin_email'         ,  $this->e107_pref['siteadminemail']);
    update_option('users_can_register'  ,  $this->e107_pref['user_reg']);
    update_option('comment_registration', !$this->e107_pref['anon_post']);
    update_option('use_smilies'         ,  $this->e107_pref['smiley_activate']);
    update_option('posts_per_page'      ,  $this->e107_pref['newsposts']);

    $tag_line = $this->e107_pref['sitetag'];
    if (strlen($tag_line) <= 0)
      $tag_line = $this->e107_pref['sitedescription'];
    update_option('blogdescription', $tag_line);

    $gmt_offset = $this->e107_pref['time_offset'];
    if (!(empty($this->e107_pref['timezone']) or (strrpos(strtolower($this->e107_pref['timezone']), strtolower('GMT')) === False))) {
      $x = 0;
      $gmt_offset = (int) $gmt_offset + $x;
    }
    update_option('gmt_offset', $gmt_offset);
  }


  // Method to force ownership of all imported content to a single user
  function setGlobalOwnership($new_owner_id) {
    // Get the list of all e107 user IDs
    $user_list = $this->getE107UserList();

    // The new user mapping is set to our given global owner
    $this->user_mapping = array();
    foreach ($user_list as $user) {
      extract($user);
      $user_id = (int) $user_id;
      $this->user_mapping[$user_id] = (int) $new_owner_id;
    }
  }


  // Import e107 users to WordPress
  function importUsers() {
    global $wpdb;

    // Get user list
    $user_list = $this->getE107UserList();

    // Send a mail to each user to tell them about password change ?
    $send_mail = False;
    if ($this->e107_mail_user == 'send_mail')
      $send_mail = True;

    foreach ($user_list as $user) {
      extract($user);
      $user_id = (int) $user_id;

      // e107 user details mapping
      // $user_loginname => WP login
      // $user_name      => WP nickname (the one to display)
      // $user_login     => WP First + Last name

      // Try to get first and last name
      if (!empty($user_login)) {
        $words = explode(" ", $user_login, 2);
        $first_name = $words[0];
        if (sizeof($words) > 1)
          $last_name  = $words[1];
      }

      // Try to get the display name
      $display_name = '';
      if (!empty($user_name))
        $display_name = $user_name;
      elseif (!empty($user_login))
        $display_name = $user_login;
      elseif (!empty($user_loginname))
        $display_name = $user_loginname;

      $user_data = array(
          'first_name'      => empty($first_name   ) ? '' : $wpdb->escape($first_name)
        , 'last_name'       => empty($last_name    ) ? '' : $wpdb->escape($last_name)
        , 'nickname'        => empty($user_name    ) ? '' : $wpdb->escape($user_name)
        , 'display_name'    => empty($display_name ) ? '' : $wpdb->escape($display_name)
        , 'user_email'      => empty($user_email   ) ? '' : $wpdb->escape($user_email)
        , 'user_registered' => empty($user_join    ) ? '' : $this->mysql_date($user_join)
        , 'user_url'        => empty($user_homepage) ? '' : $wpdb->escape($user_homepage)
        , 'aim'             => empty($user_aim     ) ? '' : $wpdb->escape($user_aim)
        , 'yim'             => empty($user_msn     ) ? '' : $wpdb->escape($user_msn)  // Put MSN contact here because they have merged with Yahoo!: http://slashdot.org/articles/05/10/12/0227207.shtml
        );

      // In case of an update, do not reset previous user profile properties by an empty value
      foreach ($user_data as $k=>$v)
        if (strlen($v) <= 0)
          unset($user_data[$k]);

      // Sanitize login string
      $user_loginname = sanitize_user($user_loginname, $strict=True);

      // Try to find a previous user and its ID
      $wp_user_ID = False;
      if (email_exists($user_email))
        $wp_user_ID = email_exists($user_email);
      elseif (username_exists($user_loginname))
        $wp_user_ID = username_exists($user_loginname);

      // Create a new user
      if (!$wp_user_ID) {
        // New password is required because we can't decrypt e107 password
        $new_password = wp_generate_password(12, False);
        $user_data['user_pass'] = $wpdb->escape($new_password);
        // Don't reset login name on user update
        $user_data['user_login'] = $wpdb->escape($user_loginname);
        $ret_id = wp_insert_user($user_data);
        // Send mail notification to users to warn them of a new password (and new login because of UTF-8)
        if ($send_mail)
          wp_new_user_notification($ret_id, $new_password);
      } else {
        // User already exist, update its profile
        $user_data['ID'] = $wp_user_ID;
        $ret_id = wp_update_user($user_data);
      }
      // Update user mapping, cast to int
      $this->user_mapping[$user_id] = (int) $ret_id;

      // Update user's description with remaining parameters like signature, location and birthday.
      $extra_info_list = array();
      if (!empty($user_signature))                                  $extra_info_list[] = __("Signature: ").$user_signature;
      if (!empty($user_customtitle))                                $extra_info_list[] = __("Custom title: ").$user_customtitle;
      if (!empty($user_location))                                   $extra_info_list[] = __("Location: ").$user_location;
      if (!empty($user_birthday) && $user_birthday != '0000-00-00') $extra_info_list[] = __("Birthday: ").$user_birthday;
      $wp_user = new WP_User($ret_id);
      $old_description = $wp_user->description;
      $new_description = $old_description;
      foreach (array_reverse($extra_info_list) as $extra_info)
        if (stristr($new_description, $extra_info) === False)
          $new_description = $extra_info."\n".$new_description;
      if ($new_description != $old_description)
        wp_update_user(array('ID' => $wp_user->ID, 'description' => $new_description));
    }
  }


  // Get e107 news and save them as WordPress posts
  function importNewsAndCategories() {
    global $wpdb;

    // Phase 1: import categories

    // Get category list
    $category_list = $this->getE107CategoryList();

    foreach ($category_list as $category) {
      extract($category);
      $cat_id = category_exists($category_name);
      if (!$cat_id) {
        $new_cat = array();
        $new_cat['cat_name'] = $category_name;
        $cat_id = wp_insert_category($new_cat);
      }
      $this->category_mapping[$category_id] = (int) $cat_id;
    }

    // Phase 2: Convert news to post

    // Get news list
    $news_list = $this->getE107NewsList();

    foreach ($news_list as $news) {
      extract($news);
      $news_id = (int) $news_id;

      // Special actions for extended news
      if ($this->e107_extended_news == 'import_all')
        $news_body = $news_body."\n\n".$news_extended;
      elseif ($this->e107_extended_news == 'ignore_body')
        $news_body = $news_extended;

      // Update author role if necessary;
      // If the user has the minimum role (aka subscriber) he is not able to post
      //   news. In this case, we increase his role by one level (aka contributor).
      $author_id = $this->user_mapping[$news_author];
      $author = new WP_User($author_id);
      if (! $author->has_cap('edit_posts'))
        $author->set_role('contributor');

      // Save e107 news in WordPress database
      $post_id = wp_insert_post(array(
          'post_author'    => $author_id                          // use the new wordpress user ID
        , 'post_date'      => $this->mysql_date($news_datestamp)
        , 'post_date_gmt'  => $this->mysql_date($news_datestamp)
        , 'post_content'   => $wpdb->escape($news_body)
        , 'post_title'     => $wpdb->escape($news_title)
        , 'post_status'    => 'publish'                           // News are always published in e107
        , 'comment_status' => $news_allow_comments                // TODO: get global config to set this value dynamiccaly
        , 'ping_status'    => 'open'                              // XXX is there such a concept in e107 ?
        , 'comment_count'  => $news_comment_total
        ));

      // Link post to category
      $news_category = (int) $news_category;
      if (array_key_exists($news_category, $this->category_mapping)) {
        $cats = array();
        $cats[] = $this->category_mapping[$news_category];
        wp_set_post_categories($post_id, $cats);
      }

      // Update post mapping
      $this->news_mapping[$news_id] = (int) $post_id;
    }
  }


  // Convert static pages to WordPress pages
  function importPages() {
    global $wpdb;

    // Get static pages list
    $page_list = $this->getE107PageList();

    foreach ($page_list as $page) {
      extract($page);
      $page_id = (int) $page_id;

      // Set the status of the post to 'publish' or 'private'.
      // There is no 'draft' in e107.
      $post_status = 'publish';
      if ($page_class != '0')
        $post_status = 'private';

      // Update author role if necessary;
      // If the user has the minimum role (aka subscriber) he is not able to post
      //   pages. In this case, we raise is role by 1 level (aka contributor).
      if (array_key_exists($page_author, $this->user_mapping)) {
        $author_id = $this->user_mapping[$page_author];
        $author = new WP_User($author_id);
      } else {
        // Can't find the original user, use the current one.
        $author = wp_get_current_user();
      }
      if (!$author->has_cap('edit_posts'))
        $author->set_role('contributor');
      // If user is the author of a private page give him the 'editor' role else he can't view private pages
      if (($post_status == 'private') and (!$author->has_cap('read_private_pages')))
        $author->set_role('editor');

      // Define comment status
      if (!$page_comment_flag) {
        $comment_status = 'closed';
      } else {
        $comment_status = 'open';
      }

      // Save e107 static page in WordPress database
      $ret_id = wp_insert_post(array(
          'post_author'    => $author->ID
        , 'post_date'      => $this->mysql_date($page_datestamp)
        , 'post_date_gmt'  => $this->mysql_date($page_datestamp)
        , 'post_content'   => $wpdb->escape($page_text)
        , 'post_title'     => $wpdb->escape($page_title)
        , 'post_status'    => $post_status
        , 'post_type'      => 'page'
        , 'comment_status' => $comment_status
        , 'ping_status'    => 'closed'               // XXX is there a global variable in WordPress or e107 to guess this ?
        ));

      // Update page mapping
      $this->page_mapping[$page_id] = (int) $ret_id;
    }
  }


  // Import e107 comments as WordPress comments
  function importComments() {
    global $wpdb;

    // Get News list
    $comment_list = $this->getE107CommentList();

    foreach ($comment_list as $comment) {
      extract($comment);
      $comment_id      = (int) $comment_id;
      $comment_item_id = (int) $comment_item_id;

      // Get the post_id from $news_mapping or $pages_mapping depending of the comment type
      if ($comment_type == 'page' and $this->e107_import_pages) {
        $post_id = $this->page_mapping[$comment_item_id];
      } elseif ($comment_type == '0' and $this->e107_import_news) {
        $post_id = $this->news_mapping[$comment_item_id];
      } else {
        // Do not import this comment: its either a non-news or non-page comment, or the user choosed to skip news and/or pages comments import
        continue;
      }

      // Don't import comments not linked with news
      $post_status = get_post_status($post_id);
      if ($post_status != False) {

        // Get author details from WordPress if registered.
        $author_name  = substr($comment_author, strpos($comment_author, '.') + 1);
        $author_id    = (int) strrev(substr(strrev($comment_author), strpos(strrev($comment_author), '.') + 1));
        $author_ip    = $this->ip_hex2dec($comment_ip);
        $author_email = $comment_author_email;
        unset($author_url);

        // Registered user
        if (array_key_exists($author_id, $this->user_mapping)) {
          $author_id = $this->user_mapping[$author_id];
          $author = new WP_User($author_id);
          $author_name  = $author->display_name;
          $author_email = $author->user_email;
          $author_url   = $author->user_url;
        // Unregistered user
        } else {
          unset($author_id);
          // Sometimes $author_name is of given as email address. In this case, try to guess the user name.
          if ($author_email == '' and filter_var($author_name, FILTER_VALIDATE_EMAIL)) {
            $author_email = $author_name;
            $author_name = substr($author_name, 0, strpos($author_name, '@'));
          }
        }

        // Build up comment data array
        $comment_data = array(
            'comment_post_ID'      => empty($post_id          ) ? '' : $post_id
          , 'comment_author'       => empty($author_name      ) ? '' : $wpdb->escape($author_name)
          , 'comment_author_email' => empty($author_email     ) ? '' : $wpdb->escape($author_email)
          , 'comment_author_url'   => empty($author_url       ) ? '' : $wpdb->escape($author_url)
          , 'comment_author_IP'    => empty($author_ip        ) ? '' : $author_ip
          , 'comment_date'         => empty($comment_datestamp) ? '' : $this->mysql_date($comment_datestamp)  //XXX ask or get the time offset ?
          , 'comment_date_gmt'     => empty($comment_datestamp) ? '' : $this->mysql_date($comment_datestamp)  //XXX ask or get the time offset ?
          , 'comment_content'      => empty($comment_comment  ) ? '' : $wpdb->escape($comment_comment)
          , 'comment_approved'     => empty($comment_blocked  ) ? '' : ! (int) $comment_blocked
          , 'user_id'              => empty($author_id        ) ? '' : $author_id
          , 'user_ID'              => empty($author_id        ) ? '' : $author_id
          , 'filtered'             => True
          );

        // Clean-up the array
        foreach ($comment_data as $k=>$v)
          if (strlen($v) <= 0)
            unset($comment_data[$k]);

        // Save e107 comment in WordPress database
        $ret_id = wp_insert_comment($comment_data);

        // Update post mapping
        $this->comment_mapping[$comment_id] = (int) $ret_id;
      }
    }
  }


  // Import e107 forums to bbPress WordPress plugin
  function importForums() {
    global $wpdb;

    // Group users by class
    $user_classes = array();
    $user_list = $this->getE107UserList();
    foreach ($user_list as $user) {
      extract($user);
      if (!empty($user_class)) {
        $user_class = (int) $user_class;
        $updated_user_list = array();
        if (array_key_exists($user_class, $user_classes))
          $updated_user_list = $user_classes[$user_class];
        $updated_user_list[] = (int) $user_id;
        $user_classes[$user_class] = $updated_user_list;
      }
    }

    // Get all forum
    $forum_list = $this->getE107ForumList();

    foreach ($forum_list as $forum) {
      extract($forum);
      $forum_id         = (int) $forum_id;
      $forum_parent     = (int) $forum_parent;
      $forum_moderators = (int) $forum_moderators;

      // Create a list of potential author based on all moderators
      $potential_authors = array();

      // If moderator ID is not 254 then moderators are defined as a e107 user class.
      // Else, do nothing: it means moderators are all e107 admins, which is the default bbPress behaviour.
      if ($forum_moderators != 254 and array_key_exists($forum_moderators, $user_classes)) {
        // Migrate moderator roles from e107 users to WordPress
        foreach ($user_classes[$forum_moderators] as $moderator) {
          $mod_id = (int) $this->user_mapping[$moderator];
          $potential_authors[] = $mod_id;
          $mod_user = new WP_User($mod_id);
          $mod_role = array_shift($mod_user->roles);
          // Increase user role to forum moderator only if user has no role nor moderate capability
          if ((empty($mod_role) or $mod_role == 'subscriber') and !$mod_user->has_cap('moderate'))
            $mod_user->set_role('bbp_moderator');
        }
      }

      // Set the author of the forum: the oldest moderator or the oldest admin.
      if (!empty($potential_authors)) {
        ksort($potential_authors);
        $author_id = array_shift($potential_authors);
      } else {
        $user_ids = $this->getWPUserIDs();
        foreach ($user_ids as $user_id) {
          if (user_can($user_id, 'publish_forums')) {
            $author_id = $user_id;
            break;
          }
        }
      }

      // Calculate forum's parent
      $updated_parent = 0;
      if (array_key_exists($forum_parent, $this->forum_mapping)) {
        $updated_parent = (int) $this->forum_mapping[$forum_parent];
      }

      // Save e107 forum in WordPress database
      $ret_id = wp_insert_post(array(
          'post_author'    => $author_id
        , 'post_date'      => $this->mysql_date($forum_datestamp)  //XXX ask or get the time offset ?
        , 'post_date_gmt'  => $this->mysql_date($forum_datestamp)  //XXX ask or get the time offset ?
        , 'post_content'   => $forum_description
        , 'post_title'     => $forum_name
        , 'post_name'      => sanitize_title($forum_name)
        , 'post_type'      => bbp_get_forum_post_type()
        , 'comment_status' => 'closed'
        , 'ping_status'    => 'closed'
        , 'post_parent'    => $updated_parent
        , 'menu_order'     => (int) $forum_order
        ));

      // Update forum mapping
      $this->forum_mapping[$forum_id] = (int) $ret_id;

      // Set forum visibility.
      //   0 -> Everyone (public)
      // 253 -> Members
      // 254 -> Admin
      // 255 -> No One (inactive)
      //   X -> user class ID
      if ($forum_class == 0) {
        bbp_open_forum($ret_id);
        bbp_publicize_forum($ret_id);
      } elseif ($forum_class == 254 or $forum_class == 255) {
        bbp_close_forum($ret_id);
        bbp_privatize_forum($ret_id);
      } elseif ($forum_class == 253) {
        bbp_open_forum($ret_id);
        bbp_privatize_forum($ret_id);
      } else {
        bbp_open_forum($ret_id);
        bbp_privatize_forum($ret_id);
      }

      // TODO: $forum_postclass is for "Post permission (indicates who can post to the forum)"
      //   0 -> Everyone (public)
      // 253 -> Members
      // 254 -> Admin
      // 255 -> No One (inactive)
      //   X -> user class ID

      // Set forum type
      if ($forum_parent == 0) {
        // The forum is a category
        bbp_categorize_forum($ret_id);
      } else {
        // The forum is a normal forum
        bbp_normalize_forum($ret_id);
      }

      // XXX How to handle e107's $forum_sub field ?

      // Publish the forum
      wp_publish_post($ret_id);
    }
  }


  // Import e107 forum content to bbPress WordPress plugin
  // This method mimick bbp_new_topic_handler() and bbp_new_reply_handler()
  function importForumThreads() {
    global $wpdb;

    // Get all forum posts
    $forum_post_list = $this->getE107ForumPostList();

    foreach ($forum_post_list as $thread) {
      extract($thread);
      $thread_id       = (int) $thread_id;
      $thread_forum_id = (int) $thread_forum_id;
      $thread_parent   = (int) $thread_parent;
      $thread_s        = (int) $thread_s;
      $thread_active   = (int) $thread_active;

      // Compute thread author's new ID
      $author_fragments = explode(".", $thread_user, 2);
      $author_id        = (int) $author_fragments[0];
      $author_name      = $author_fragments[1];
      $author_ip        = '';
      // Author is anonymous, its name and IP are intertwined
      if ($author_id == 0) {
        $last_valid_cut = 0;
        for ($cut_index=1; $cut_index<strlen($author_name); $cut_index++) {
          if (filter_var(substr($author_name, -$cut_index), FILTER_VALIDATE_IP))
            $last_valid_cut = $cut_index;
        }
        $author_ip = substr($author_name, -$last_valid_cut);
        $author_name = substr($author_name, 0, -$last_valid_cut);
      // Author is not anonymous, we should find him in WordPress
      } else {
        if (array_key_exists($author_id, $this->user_mapping)) {
          $author_id = (int) $this->user_mapping[$author_id];
        } else{
          // Some users had an account but were deleted for any other reason.
          // In this case, let's declare them anonymous. $author_name is still set.
          $author_id = 0;
        }
      }

      // Top message of threads are topics, attached to a forum.
      // Others are replies, attached to a topic.
      if ($thread_parent > 0) {
        $post_type_id = bbp_get_reply_post_type();
        $thread_parent_id = (int) $this->forum_post_mapping[$thread_parent];
      } else {
        $post_type_id = bbp_get_topic_post_type();
        $thread_parent_id = $this->forum_mapping[$thread_forum_id];
      }

      // Apply pre filters
      $post_title   = apply_filters('bbp_new_'.$post_type_id.'_pre_title'  , $thread_name);
      $post_content = apply_filters('bbp_new_'.$post_type_id.'_pre_content', $thread_thread);

      // Get creation date
      $post_date = $this->mysql_date($thread_datestamp);  //XXX ask or get the time offset ?
      // TODO: How-to handle $thread_edit_datestamp ?

      // Save e107 forum in WordPress database
      $ret_id = wp_insert_post(array(
          'post_author'    => $author_id
        , 'post_date'      => $post_date
        , 'post_date_gmt'  => $post_date
        , 'post_content'   => $post_content
        , 'post_title'     => $post_title
        , 'post_name'      => sanitize_title($post_title)
        , 'post_type'      => $post_type_id
        , 'comment_status' => 'closed'
        , 'ping_status'    => 'closed'
        , 'post_parent'    => $thread_parent_id
        ));

      // Update forum post mapping
      $this->forum_post_mapping[$thread_id] = (int) $ret_id;

      // Publish the post
      wp_publish_post($ret_id);

      // Sticky threads stays sticky, Announcements are promoted super-sticky.
      if ($thread_s == 1) {
        bbp_stick_topic($ret_id);
      } elseif ($thread_s > 2) {
        bbp_stick_topic($ret_id, True);
      }

      // Update inserted post with Anonymous related data.
      // Both bbp_anonymous_name and bbp_anonymous_email are required, that's why we use dummy default values.
      $anonymous_data = array();
      if ($author_id == 0) {
        $anonymous_data = array( 'bbp_anonymous_name'  => empty($author_name) ? 'Anonymous user' : $author_name
                               , 'bbp_anonymous_ip'    => empty($author_ip  ) ? '192.0.2.0'      : $author_ip   # See RFC 5735
                               , 'bbp_anonymous_email' => 'anonymous@example.com'
                               // Website is optionnal in bbPress
                               );
      }

      // Update reply metadata
      if (bbp_is_topic($ret_id)) {
        $forum_id = $thread_parent_id;
        do_action('bbp_new_topic', $ret_id, $forum_id, $anonymous_data, $author_id);
        // Fix the last active time autommaticaly set by bbp_new_topic
        bbp_update_topic_last_active_time($ret_id, $post_date);
        bbp_update_topic_walker($ret_id, $post_date, $forum_id, 0, False);
      } else {
        $topic_id = $thread_parent_id;
        $forum_id = bbp_get_topic_forum_id($topic_id);
        do_action('bbp_new_reply', $ret_id, $topic_id, $forum_id, $anonymous_data, $author_id);
        // Fix the last active time autommaticaly set by bbp_new_reply
        bbp_update_reply_walker($ret_id, $post_date, $forum_id, $topic_id, False);
      }

      // Close the topic if necessary
      if ($thread_active < 1)
        bbp_close_topic($ret_id);
    }
  }


  // This method recount all forum metadata
  // Code inspired by the bbp_admin_tools() method
  function recountForumStats() {
    $recount_list = bbp_recount_list();
    foreach ((array)$recount_list as $item)
      if (isset($item[2]) && is_callable($item[2]))
        call_user_func($item[2]);
  }


  // This method replace old e107 URLs embeded in news, pages and comments by WP permalinks
  // TODO: merge with parseAndUpdate() method
  function replaceWithPermalinks() {
    global $wpdb;
    // Associate each mapping with their related regexp
    // TODO: Load mappings from the e107-redirector.php plugin
    $redirect_rules = array(
      array( 'mapping' => $this->news_mapping
           , 'rules'   => array( '/^\/*comment\.php(?:%3F|\?)comment\.news\.(\d+)(.*)$/i'
                                   # /comment.php?comment.news.138
                                   # /comment.php?comment.news.138&dfsd
                               , '/^\/*news\.php(?:%3F|\?)item\.(\d+)(.*)$/i'
                                   # /news.php?item.138
                                   # /news.php?item.100.3
                                   # /news.php?item.138&res=1680x1050
                               , '/^\/*news\.php(?:%3F|\?)extend\.(\d+)(.*)$/i'
                                   # /news.php?extend.17
                               )
           ),
      array( 'mapping' => $this->page_mapping
           , 'rules'   => array( '/^\/*page\.php(?:%3F|\?)(\d+)(.*)$/i'
                                   # /page.php?16
                                   # /page.php?16&res=1680x1050
                                   # /page.php%3F16
                               )
           )
    );

    // Transform the site url string to make it regexp compatible
    $siteurl_regexp = $this->e107_pref['siteurl'];
    // Delete trailing slashes
    $siteurl_regexp = $this->deleteTrailingChar($siteurl_regexp, '/');
    // Escape all slashes with anti-slash
    $siteurl_regexp = str_replace('/', '\/', $siteurl_regexp);
    // Escape all dots
    $siteurl_regexp = str_replace('.', '\.', $siteurl_regexp);

    // Add the e107 site url at the beginning of each regexp
    for ($i=0; $i<count($redirect_rules); $i++) {
      $rule_set = $redirect_rules[$i];
      $mapping  = $rule_set['mapping'];
      $rules    = $rule_set['rules'];
      if ($mapping && is_array($mapping) && sizeof($mapping) > 0) {
        for ($j=0; $j<count($rules); $j++) {
          $regexp    = $rules[$j];
          // Remove the "absolutiveness" of the regexp (aka "^$")
          $regexp    = str_replace('/^', '/'.$siteurl_regexp, $regexp);
          $rules[$j] = str_replace('(.*)$/i', '(.*)/i', $regexp);
        }
        $redirect_rules[$i]['rules'] = $rules;
      }
    }

    // Get the list of WP news and page IDs
    $news_and_pages_ids = array_merge(array_values($this->news_mapping), array_values($this->page_mapping));
    foreach ($news_and_pages_ids as $post_id) {
      // Get post content
      $post    = get_post($post_id);
      $content = $post->post_content;
      // Search content for something that look like e107 URL
      foreach ($redirect_rules as $rule_set) {
        $mapping = $rule_set['mapping'];
        $rules   = $rule_set['rules'];
        if ($mapping && is_array($mapping) && sizeof($mapping) > 0)
          foreach ($rules as $regexp) {
            preg_match_all($regexp, $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match)
              if (array_key_exists($match[1], $mapping)) {
                $new_link = get_permalink($mapping[$match[1]]);
                $old_link = substr($match[0], 0, strlen($match[0]) - strlen($match[2]));
                $content = str_replace($old_link, $new_link, $content);
              }
          }
      }
      // Update post content
      wp_update_post(array(
          'ID'           => $post_id
        , 'post_content' => $content
        ));
    }
  }


  // Perform some transformation in WordPress content
  function parseAndUpdate($content_ids, $content_type, $property, $parser) {
    // $content_ids   is a list of WordPress IDs we want to modify.
    // $content_type  can be 'post' or 'comment'.
    // $property      is the name of the property we would like to apply the parser to (only tested on 'title' and 'content').
    // $parser        is either 'bbcode' for e107 BBCode parsing or 'constants' for e107 constants replacement. 'clean_markup' is like 'bbcode' but add an extra step to clean the resulting markup.
    global $wpdb;

    foreach ($content_ids as $content_id) {
      if ($content_type == 'comment') {
        $content_object = get_comment($content_id);
      } else {
        $content_object = get_post($content_id);
      }
      $content_property = $content_type.'_'.$property;
      $content = $content_object->$content_property;
      $new_content = $content;

      // Apply the specified transformation
      $local_image_upload = False;
      switch ($parser) {
        case 'constants':
          // Replace all {e_SOMETHING} e107's constants to fully qualified URLs
          $new_content = $this->e107_parser->replaceConstants($content, $nonrelative = "full", $all = True);
          break;
        case 'bbcode':
          // Transform BBCode to HTML using original e107 parser
          $new_content = $this->e107_parser->toHTML($content, $parseBB = True);
          break;
        case 'clean_markup':
          // Transform BBCode to HTML using original e107 parser
          $new_content = $this->e107_parser->toHTML($content, $parseBB = True);
          // Clean-up markup produced by e107's BBCode parser
          $new_content = $this->cleanUpMarkup($new_content);
          break;
        case 'upload_local_images':
          $local_image_upload = True;
        case 'upload_all_images':
          $ret = $this->importImages($content, $content_id, $local_image_upload);
          $new_content = $ret[0];
          $counter = (empty($counter)) ? $ret[1] : $counter + $ret[1];
          break;
      }

      // Update WordPress content
      if ($new_content != $content) {
        if ($content_type == 'comment') {
          wp_update_comment(array(
              'comment_ID'      => $content_id
            , 'comment_content' => $wpdb->escape($new_content)
            ));
        } else {
          wp_update_post(array(
              'ID'           => $content_id
            , 'post_content' => $wpdb->escape($new_content)
            ));
        }
      }
    }

    // Return our generic counter variables
    if (!empty($counter))
      return $counter;
  }


  // Clean-up markup produced by e107's BBCode parser
  function cleanUpMarkup($content) {
    /*
    // cleanup HTML and semantics enhancements

    "<br />" -> "\n"
    "<br /><br />" -> "\n\n"
    "<br /><br /><br />" -> "\n\n"
    ...

    "<em class='bbcode italic'>" -> <em>

    "class='bbcode'" -> ''

    "<strong class='bbcode bold'>" -> "<strong>"

    " class='bbcode underline'" -> ''

    "alt=''"

    "style='vertical-align:middle; border:0'"

    "style='vertical-align:middle; border:0'"

    *
    *
    *
    -> wiki style lists

    -
    -
    -
    -> wiki style lists

    1
    2
    3
    -> wiki style lists

    */
    $new_content = $content;
    return $new_content;
  }


  // This method import all images embedded in HTML content
  function importImages($html_content, $post_id, $local_only = False) {
    $image_counter = 0;

    // Build the list of authorized domains from which we are allowed to import images
    $allowed_domains = array();
    if ($local_only == True)
      $allowed_domains[] = $this->e107_pref['siteurl'];

    // Locate all <img/> tags and import them into WordPress
    // Look at http://kevin.deldycke.com/2007/03/ultimate-regular-expression-for-html-tag-parsing-with-php/ for details about this regex
    $img_regex = "/<\s*img((\s+\w+(\s*=\s*(?:\".*?\"|'.*?'|[^'\">\s]+))?)+\s*|\s*)\/?>/i";
    preg_match_all($img_regex, $html_content, $matches, PREG_SET_ORDER);
    foreach ($matches as $val) {
      $img_tag = $val[0];

      // Get image URL from the src attribute
      $src_regex = "/\s+src\s*=\s*(?:\"(.*?)\"|'(.*?)'|[^'\">\s]+)/i"; // This regex is a variation of the main one
      preg_match_all($src_regex, $img_tag, $src_matches, PREG_SET_ORDER);
      // URL is in the second or the third index of the array depending of the quotes (double or single)
      $img_url = '';
      for ($i = 1; $i <= 2 and strlen($img_url) == 0; $i += 1)
        if (sizeof($src_matches[0]) > $i)
          $img_url = $src_matches[0][$i];

      // Clean-up the URL
      // If url doesn't start with "http[s]://", add e107 site url in front to build an absolute url
      $http_prefix_regex = '/^https?:\/\//i';
      if (! preg_match($http_prefix_regex, $img_url))
        $img_url = SITEURL.$img_url;

      // Only import files from authorized domains
      $domain_ok = True;
      if ($allowed_domains && is_array($allowed_domains) && sizeof($allowed_domains) > 0) {
        $domain_ok = False;
        foreach ($allowed_domains as $domain) {
          $domain = $this->deleteTrailingChar($domain, '/');
          if (substr($img_url, 0, strlen($domain)) == $domain) {
            $domain_ok = True;
            break;
          }
        }
      }
      if (!$domain_ok)
        continue;

      //$img_url = "http://home.nordnet.fr/francois.jankowski/pochette avant thumb.jpg";
      // URLs with spaces are not considered valid by WordPress (see: http://core.trac.wordpress.org/ticket/16330#comment:5 )
      // Replace spaces by their percent-encoding equivalent
      $img_url = str_replace(' ', '%20', html_entity_decode($img_url));

      // Download remote file and attach it to the post
      $new_tag = media_sideload_image($img_url, $post_id);
      $image_counter++;

      if (is_wp_error($new_tag)) {
        ?>
        <li>
          <?php printf(__('Error while trying to upload image <code>%s</code>:', 'e107-importer'), $img_url); ?><br/>
          <?php printf(__('<pre>%s</pre>', 'e107-importer'), $new_tag->get_error_message()); ?><br/>
          <?php _e('Ignore this image upload and proceed with the next...', 'e107-importer'); ?>
        </li>
        <?php
      } else {
        // Update post content with the new image tag pointing to the local image
        $html_content = str_replace($img_tag, $new_tag, $html_content);

        // TODO: save image original path and its final permalink to not upload file twice
      }
    }

    return array($html_content, $image_counter);
  }


  // Update the e107 Redirector plugin with content mapping
  function updateRedirectorSettings($keyword, $data) {
    global $wpdb;
    $option_name = 'e107_redirector_'.$keyword;
    if (!get_option($option_name))
      add_option($option_name);
    update_option($option_name, $data);
  }


  function start() {
    // Get requested action
    if (!empty($_GET['action']))
      $action = $_GET['action'];
    // Dispatch action
    if (!empty($action) and $action == 'import')
      $this->import();
    else
      $this->printOptionScreen();
  }


  function header() {
    echo '<div class="wrap">';
    screen_icon();
    echo '<h2>'.__('e107 Importer', 'e107-importer').'</h2>';
  }


  function footer() {
    echo '</div>';
  }


  function printOptionScreen() {
    $this->header();
    ?>

    <form action="admin.php?import=e107&amp;action=import" method="post">
      <?php wp_nonce_field('import-e107'); ?>

      <h3><?php _e('e107 Database', 'e107-importer'); ?></h3>
      <p><?php _e('Parameters below must match your actual e107 MySQL database connexion settings.', 'e107-importer'); ?></p>
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><label for="e107-db-host"><?php _e('e107 Database Host', 'e107-importer'); ?></label></th>
          <td><input type="text" name="e107_db_host" id="e107-db-host" value="<?php echo esc_attr(DB_HOST); ?>" size="40"/></td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="e107-db-user"><?php _e('e107 Database User', 'e107-importer'); ?></label></th>
          <td><input type="text" name="e107_db_user" id="e107-db-user" value="<?php echo esc_attr(DB_USER); ?>" size="40"/></td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="e107-db-pass"><?php _e('e107 Database Password', 'e107-importer'); ?></label></th>
          <td><input type="password" name="e107_db_pass" id="e107-db-pass" value="" size="40"/></td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="e107-db-name"><?php _e('e107 Database Name', 'e107-importer'); ?></label></th>
          <td><input type="text" name="e107_db_name" id="e107-db-name" value="<?php echo esc_attr(DB_NAME); ?>" size="40"/></td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="e107-db-prefix"><?php _e('e107 Table Prefix', 'e107-importer'); ?></label></th>
          <td><input type="text" name="e107_db_prefix" id="e107-db-prefix" value="e107_" size="40"/></td>
        </tr>
      </table>

      <h3><?php _e('Site Preferences', 'e107-importer'); ?></h3>
      <p><?php _e('This importer can read the preferences set for e107 and apply them to this current blog. Supported preferences are: site title, site description, admin e-mail address, open user registration, users registration for comment, emoticons graphical convertion, number of posts per pages and timezone offset.', 'e107-importer'); ?></p>
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><?php _e('Do you want to import e107 preferences ?', 'e107-importer'); ?></th>
          <td>
            <label for="import-pref"><input name="e107_preferences" type="radio" id="import-pref" value="import_pref"/> <?php _e('Yes: get preferences from e107 and apply them to the current blog.', 'e107-importer'); ?></label><br/>
            <label for="ignore-pref"><input name="e107_preferences" type="radio" id="ignore-pref" value="ignore_pref" checked="checked"/> <?php _e('No: don\'t mess the configuration of this blog with e107 preferences.', 'e107-importer'); ?></label><br/>
          </td>
        </tr>
      </table>

      <h3><?php _e('Users', 'e107-importer'); ?></h3>
      <p><?php _e('This importer will try to merge e107 users with WordPress existing users based on email address and login. If it can\'t find something that match, a new user will be created.', 'e107-importer'); ?></p>
      <p><?php _e('If for any reason (<a href="http://kevin.deldycke.com/2011/01/e107-importer-wordpress-plugin-v1-0-released/comment-page-1/#comment-8242">like Simon Paul\'s</a>), you would like to not import e107 users in WordPress, you may use the option below, but you have to select which existing WordPress user will received content ownership.', 'e107-importer'); ?></p>
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><?php _e('Do you want to force ownership of news, comments and pages ?', 'e107-importer'); ?></th>
          <td>
            <label for="take-ownership"><input name="e107_content_ownership" type="radio" id="take-ownership" value="take_ownership"/> <?php _e('Yes: attribute all imported content to ', 'e107-importer'); ?><?php wp_dropdown_users(); ?></label><br/>
            <label for="keep-ownership"><input name="e107_content_ownership" type="radio" id="keep-ownership" value="keep_ownership" checked="checked"/> <?php _e('No: keep original ownership and import e107 users.', 'e107-importer'); ?></label><br/>
          </td>
        </tr>
      </table>
      <p><?php _e('e107 users\' password are encrypted. All passwords will be resetted. And unlike e107, WordPress don\'t accept strange characters (like accents, etc.) in login. When a user will be added to WordPress, all non-ascii chars will be deleted from the login string. These are two good reasons to send a mail with new credentials to your users.', 'e107-importer'); ?></p>
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><?php _e('Do you want to inform each user of their new credentials ?', 'e107-importer'); ?></th>
          <td>
            <label for="send-mail"><input name="e107_mail_user" type="radio" id="send-mail" value="send_mail"/> <?php _e('Yes: reset each password and send each user a mail to inform them.', 'e107-importer'); ?></label><br/>
            <label for="no-mail"><input name="e107_mail_user" type="radio" id="no-mail" value="no_mail" checked="checked"/> <?php _e('No: reset each password but don\'t send a mail to users.', 'e107-importer'); ?></label><br/>
          </td>
        </tr>
      </table>
      <p><?php
        printf( __('All users will be imported in the WordPress database with the <code>%s</code> role. You can change the default role in the <a href="%s/wp-admin/options-general.php"><code>Options</code> &gt; <code>General</code> panel</a>. If a user is the author of at least one post or static page, its level will be raised to <code>contributor</code>.', 'e107-importer')
              , __(get_option('default_role'))
              , get_option('siteurl')
              );
      ?></p>

      <h3><?php _e('News', 'e107-importer'); ?></h3>
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><?php _e('Do you want to import news and their associated comments and categories ?', 'e107-importer'); ?></th>
          <td>
            <label for="import-news"><input name="e107_import_news" type="radio" id="import-news" value="import_news" checked="checked"/> <?php _e('Yes: import news, comments and categories.', 'e107-importer'); ?></label><br/>
            <label for="skip-news"><input name="e107_import_news" type="radio" id="skip-news" value="skip-news"/> <?php _e('No: do not import news nor their comments or categories.', 'e107-importer'); ?></label><br/>
          </td>
        </tr>
      </table>
      <p><?php _e('WordPress doesn\'t support extended news.', 'e107-importer'); ?></p>
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><?php _e('Do you want to import the extended part of all e107 news ?', 'e107-importer'); ?></th>
          <td>
            <label for="import-all"><input name="e107_extended_news" type="radio" id="import-all" value="import_all"/> <?php _e('Yes: import both extended part and body and merge them.', 'e107-importer'); ?></label><br/>
            <label for="ignore-body"><input name="e107_extended_news" type="radio" id="ignore-body" value="ignore_body"/> <?php _e('Yes, but: ignore body and import extended part only.', 'e107-importer'); ?></label><br/>
            <label for="ignore-extended"><input name="e107_extended_news" type="radio" id="ignore-extended" value="ignore_extended" checked="checked"/> <?php _e('No: ignore extended part of news, import the body only.', 'e107-importer'); ?></label><br/>
          </td>
        </tr>
      </table>

      <h3><?php _e('Pages', 'e107-importer'); ?></h3>
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><?php _e('Do you want to import pages and their associated comments ?', 'e107-importer'); ?></th>
          <td>
            <label for="import-pages"><input name="e107_import_pages" type="radio" id="import-pages" value="import_pages" checked="checked"/> <?php _e('Yes: import pages and comments.', 'e107-importer'); ?></label><br/>
            <label for="skip-pages"><input name="e107_import_pages" type="radio" id="skip-pages" value="skip-pages"/> <?php _e('No: do not import pages nor their comments.', 'e107-importer'); ?></label><br/>
          </td>
        </tr>
      </table>

      <h3><?php _e('Forums', 'e107-importer'); ?></h3>
      <p><?php _e('e107 forums can be imported to <a href="http://wordpress.org/extend/plugins/bbpress/">bbPress plugin</a>.', 'e107-importer'); ?></p>
      <?php if (!array_key_exists(BBPRESS_PLUGIN, get_plugins())) { ?>
        <p><?php _e('bbPress plugin is not available on your system. If you want to import forums, please install it first before comming back to this screen. ', 'e107-importer'); ?></p>
      <?php } elseif (!is_plugin_active(BBPRESS_PLUGIN)) { ?>
        <p><?php _e('bbPress plugin is available on your system, but is not active. It will be activated automaticcaly if you choose to import forums below.', 'e107-importer'); ?></p>
      <?php } else { ?>
        <p><?php _e('bbPress plugin is available on your system, and ready to receive forum content from e107.', 'e107-importer'); ?></p>
      <?php } ?>
      <?php if (array_key_exists(BBPRESS_PLUGIN, get_plugins())) { ?>
        <p><?php _e('e107 allows you to define moderators per-forum. On the other hand, bbPress moderation rights applies to all forums. Importing forums means that all users which were moderators in e107 will be granted to bbPress\' <code>Forum Moderator</code> role, but only if they currently have no role or are <code>Subscribers</code>.', 'e107-importer'); ?></p>
        <table class="form-table">
          <tr valign="top">
            <th scope="row"><?php _e('Do you want to import forums ?', 'e107-importer'); ?></th>
            <td>
              <label for="import-forums"><input name="e107_import_forums" type="radio" id="import-forums" value="import_forums"/> <?php _e('Yes: import forums from e107', 'e107-importer'); ?><?php if (!is_plugin_active(BBPRESS_PLUGIN)) _e(' and activate the bbPress plugin', 'e107-importer'); ?>.</label><br/>
              <label for="skip-forums"><input name="e107_import_forums" type="radio" id="skip-forums" value="skip_forums" checked="checked"/> <?php _e('No: do not import forums from e107 to bbPress.', 'e107-importer'); ?></label><br/>
            </td>
          </tr>
        </table>
      <?php } ?>

      <h3><?php _e('BBCode', 'e107-importer'); ?></h3>
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><?php _e('Which kind of <a href="http://wikipedia.org/wiki/Bbcode">BBCode</a> parser you want to use ?', 'e107-importer'); ?></th>
          <td>
            <label for="bbcode"><input name="e107_bbcode_parser" type="radio" id="bbcode" value="bbcode" checked="checked"/> <?php _e('e107\'s parser (content will be rendered exactly as they appear in e107).', 'e107-importer'); ?></label><br/>
            <!--label for="clean-markup"><input name="e107_bbcode_parser" type="radio" id="clean-markup" value="clean_markup"/--> <!--?php _e('Enhanced parser (try to create HTML code similar to what WordPress produce by default).', 'e107-importer'); ?></label><br/-->
            <label for="none"><input name="e107_bbcode_parser" type="radio" id="none" value="none"/> <?php _e('Do not translate BBCode to HTML and let them appear as is.', 'e107-importer'); ?></label><br/>
          </td>
        </tr>
      </table>

      <h3><?php _e('Images Upload', 'e107-importer'); ?></h3>
      <p><?php _e('This tool can find image URLs embedded in news and pages, then upload them to this blog autommaticcaly.', 'e107-importer'); ?></p>
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><?php _e('Do you want to upload image files ?', 'e107-importer'); ?></th>
          <td>
            <label for="upload-all-images"><input name="e107_import_images" type="radio" id="upload-all-images" value="upload_all_images"/> <?php _e('Yes: upload all images, even those located on external sites.', 'e107-importer'); ?></label><br/>
            <label for="upload_local_images"><input name="e107_import_images" type="radio" id="upload-local-images" value="upload_local_images" checked="checked"/> <?php _e('Yes, but: upload local files from the e107 site only, not external images.', 'e107-importer'); ?></label><br/>
            <label for="no-upload"><input name="e107_import_images" type="radio" id="no-upload" value="no_upload"/> <?php _e('No: do not upload image files to WordPress.', 'e107-importer'); ?></label><br/>
          </td>
        </tr>
      </table>

      <p class="submit">
        <input type="submit" class="button-primary" id="submit" name="submit" value="<?php esc_attr_e('Import e107 to WordPress', 'e107-importer'); ?>"/>
      </p>
    </form>
    <?php
    $this->footer();
  }


  // Main import function
  function import() {
    // Collect parameters and options from the welcome screen
    $e107_option_names = array( 'e107_db_host', 'e107_db_user', 'e107_db_pass', 'e107_db_name', 'e107_db_prefix'
                              , 'e107_preferences'
                              , 'e107_content_ownership'
                              , 'e107_mail_user'
                              , 'e107_import_news'
                              , 'e107_extended_news'
                              , 'e107_import_pages'
                              , 'e107_import_forums'
                              , 'e107_bbcode_parser'
                              , 'e107_import_images'
                              );

    // Register each option as class global variables
    foreach ($e107_option_names as $o)
      if ($_POST[$o])
        $this->$o = $_POST[$o];

    // Normalize boolean options
    $this->e107_import_news   == 'import_news'   ? $this->e107_import_news   = True : $this->e107_import_news   = False;
    $this->e107_import_pages  == 'import_pages'  ? $this->e107_import_pages  = True : $this->e107_import_pages  = False;
    $this->e107_import_forums == 'import_forums' ? $this->e107_import_forums = True : $this->e107_import_forums = False;

    $this->header();
    ?>

    <h3><?php _e('e107 Database', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <li><?php _e('Connecting...', 'e107-importer'); ?></li>
      <?php $this->connectToE107DB(); ?>
      <li><?php _e('Connected.', 'e107-importer'); ?></li>
    </ul>

    <h3><?php _e('Site Preferences', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <li><?php _e('Load preferences...', 'e107-importer'); ?></li>
      <?php $this->loadE107Preferences(); ?>
      <li><?php _e('Preferences loaded.', 'e107-importer'); ?></li>
      <li><?php _e('Import preferences...', 'e107-importer'); ?></li>
      <?php if ($this->e107_preferences == 'import_pref') { ?>
        <?php $this->importPreferences(); ?>
        <li><?php _e('All preferences imported.', 'e107-importer'); ?></li>
      <?php } else { ?>
        <li><?php _e('Do not import preferences.', 'e107-importer'); ?></li>
      <?php } ?>
    </ul>

    <h3><?php _e('Content mapping', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <li><?php _e('Load pre-existing e107 content mapping...', 'e107-importer'); ?></li>
      <?php $this->loadE107Mapping(); ?>
      <li><?php _e('Existing content mapping from previous imports loaded.', 'e107-importer'); ?></li>
    </ul>

    <h3><?php _e('Users', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <?php if ($this->e107_content_ownership == 'take_ownership') { ?>
        <li><?php _e('Skip user import.', 'e107-importer'); ?></li>
        <li><?php _e('Get the user that will take ownership of all imported content...', 'e107-importer'); ?></li>
        <?php $owner_id = (int)$_REQUEST['user'];
              $owner = new WP_User($owner_id);
        ?>
        <li><?php printf(__('<em>%s</em> (#%s) will take ownership of all imported content.', 'e107-importer'), $owner->user_login, $owner_id); ?></li>
        <li><?php _e('Force ownership of all imported content...', 'e107-importer'); ?></li>
        <?php $this->setGlobalOwnership($owner_id); ?>
        <li><?php _e('Ownership forced.', 'e107-importer'); ?></li>
      <?php } else { ?>
        <li><?php _e('Import users...', 'e107-importer'); ?></li>
        <?php $this->importUsers(); ?>
        <li><?php printf(__('%s users imported.', 'e107-importer'), sizeof($this->user_mapping)); ?></li>
      <?php } ?>
      <li><?php _e('Update redirection plugin with user mapping...', 'e107-importer'); ?></li>
      <?php $this->updateRedirectorSettings('user_mapping', $this->user_mapping); ?>
      <li><?php _e('Old user URLs are now redirected.', 'e107-importer'); ?></li>
    </ul>

    <h3><?php _e('News', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <?php if ($this->e107_import_news) { ?>
        <li><?php _e('Import news and categories...', 'e107-importer'); ?></li>
        <?php $this->importNewsAndCategories(); ?>
        <li><?php printf(__('%s news imported.', 'e107-importer'), sizeof($this->news_mapping)); ?></li>
        <li><?php printf(__('%s categories imported.', 'e107-importer'), sizeof($this->category_mapping)); ?></li>
        <li><?php _e('Update redirection plugin with news mapping...', 'e107-importer'); ?></li>
        <?php $this->updateRedirectorSettings('news_mapping', $this->news_mapping); ?>
        <li><?php _e('Old news URLs are now redirected to permalinks.', 'e107-importer'); ?></li>
        <li><?php _e('Update redirection plugin with category mapping...', 'e107-importer'); ?></li>
        <?php $this->updateRedirectorSettings('category_mapping', $this->category_mapping); ?>
        <li><?php _e('Old news category URLs are now redirected to permalinks.', 'e107-importer'); ?></li>
      <?php } else { ?>
        <li><?php _e('e107 news and categories import skipped.', 'e107-importer'); ?></li>
      <?php } ?>
    </ul>

    <h3><?php _e('Pages', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <?php if ($this->e107_import_pages) { ?>
        <li><?php _e('Import pages...', 'e107-importer'); ?></li>
        <?php $this->importPages(); ?>
        <li><?php printf(__('%s pages imported.', 'e107-importer'), sizeof($this->page_mapping)); ?></li>
        <li><?php _e('Update redirection plugin with page mapping...', 'e107-importer'); ?></li>
        <?php $this->updateRedirectorSettings('page_mapping', $this->page_mapping); ?>
        <li><?php _e('Old page URLs are now redirected to permalinks.', 'e107-importer'); ?></li>
      <?php } else { ?>
        <li><?php _e('e107 pages import skipped.', 'e107-importer'); ?></li>
      <?php } ?>
    </ul>

    <h3><?php _e('Comments', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <?php if (!$this->e107_import_news) { ?>
        <li><?php _e('e107 news comment import skipped.', 'e107-importer'); ?></li>
      <?php } ?>
      <?php if (!$this->e107_import_pages) { ?>
        <li><?php _e('e107 pages comment import skipped.', 'e107-importer'); ?></li>
      <?php } ?>
      <?php if ($this->e107_import_news or $this->e107_import_pages) { ?>
        <li><?php _e('Import comments...', 'e107-importer'); ?></li>
        <?php $this->importComments(); ?>
        <li><?php printf(__('%s comments imported.', 'e107-importer'), sizeof($this->comment_mapping)); ?></li>
        <li><?php _e('Update redirection plugin with comment mapping...', 'e107-importer'); ?></li>
        <?php $this->updateRedirectorSettings('comment_mapping', $this->comment_mapping); ?>
        <li><?php _e('Old comments URLs are now redirected.', 'e107-importer'); ?></li>
      <?php } ?>
    </ul>

    <h3><?php _e('Forums', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <?php if ($this->e107_import_forums) { ?>
        <?php if (!is_plugin_active(BBPRESS_PLUGIN)) { ?>
          <li><?php _e('Activate bbPress plugin...', 'e107-importer'); ?></li>
          <?php activate_plugin(BBPRESS_PLUGIN, '', false, true); ?>
          <li><?php _e('Plugin activated.', 'e107-importer'); ?></li>
        <?php } ?>
        <li><?php _e('Import forums and forum categories...', 'e107-importer'); ?></li>
        <?php $this->importForums(); ?>
        <li><?php printf(__('%s forums and forum categories imported.', 'e107-importer'), sizeof($this->forum_mapping)); ?></li>
        <li><?php _e('Import forum threads...', 'e107-importer'); ?></li>
        <?php $this->importForumThreads(); ?>
        <li><?php printf(__('%s forum posts imported.', 'e107-importer'), sizeof($this->forum_post_mapping)); ?></li>
        <li><?php _e('Update redirection plugin with forum and forum post mapping...', 'e107-importer'); ?></li>
        <?php $this->updateRedirectorSettings('forum_mapping',      $this->forum_mapping); ?>
        <?php $this->updateRedirectorSettings('forum_post_mapping', $this->forum_post_mapping); ?>
        <li><?php _e('Old forum URLs are now redirected.', 'e107-importer'); ?></li>
        <li><?php _e('Recount forum stats...', 'e107-importer'); ?></li>
        <?php $this->recountForumStats(); ?>
        <li><?php _e('Forums stats up to date.', 'e107-importer'); ?></li>
      <?php } else { ?>
        <li><?php _e('e107 forums import skipped.', 'e107-importer'); ?></li>
      <?php } ?>
    </ul>

    <h3><?php _e('Content Parsing', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <li><?php _e('Initialize e107 context...', 'e107-importer'); ?></li>
      <?php $this->inite107Context(); ?>
      <li><?php _e('e107 context initialized.', 'e107-importer'); ?></li>
      <?php if ($this->e107_import_news) { ?>
        <li><?php _e('Replace e107 constants in news...', 'e107-importer'); ?></li>
        <?php $this->parseAndUpdate(array_values($this->news_mapping), 'post', 'title'  , 'constants'); ?>
        <?php $this->parseAndUpdate(array_values($this->news_mapping), 'post', 'content', 'constants'); ?>
      <?php } ?>
      <?php if ($this->e107_import_pages) { ?>
        <li><?php _e('Replace e107 constants in pages...', 'e107-importer'); ?></li>
        <?php $this->parseAndUpdate(array_values($this->page_mapping), 'post', 'title'  , 'constants'); ?>
        <?php $this->parseAndUpdate(array_values($this->page_mapping), 'post', 'content', 'constants'); ?>
      <?php } ?>
      <?php if ($this->e107_import_news or $this->e107_import_pages) { ?>
        <li><?php _e('Replace e107 constants in comments...', 'e107-importer'); ?></li>
        <?php $this->parseAndUpdate(array_values($this->comment_mapping),    'comment', 'content', 'constants'); ?>
      <?php } ?>
      <?php if ($this->e107_import_forums) { ?>
        <li><?php _e('Replace e107 constants in forums...', 'e107-importer'); ?></li>
        <?php $this->parseAndUpdate(array_values($this->forum_mapping), 'post', 'title'  , 'constants'); ?>
        <?php $this->parseAndUpdate(array_values($this->forum_mapping), 'post', 'content', 'constants'); ?>
        <li><?php _e('Replace e107 constants in forum threads...', 'e107-importer'); ?></li>
        <?php $this->parseAndUpdate(array_values($this->forum_post_mapping), 'post', 'title'  , 'constants'); ?>
        <?php $this->parseAndUpdate(array_values($this->forum_post_mapping), 'post', 'content', 'constants'); ?>
      <?php } ?>
      <li><?php _e('All e107 constants replaced by proper URLs.', 'e107-importer'); ?></li>
      <?php if ($this->e107_bbcode_parser == 'none') { ?>
        <li><?php _e('BBCode tags left as-is.', 'e107-importer'); ?></li>
      <?php } else { ?>
        <?php if ($this->e107_bbcode_parser == 'clean_markup') { ?>
          <li><?php _e('Parse BBCode using enhanced parser...', 'e107-importer'); ?></li>
        <?php } else { ?>
          <li><?php _e('Parse BBCode using the original e107 parser...', 'e107-importer'); ?></li>
        <?php } ?>
        <?php if ($this->e107_import_news) { ?>
          <li><?php _e('Parse news title and content...', 'e107-importer'); ?></li>
          <?php $this->parseAndUpdate(array_values($this->news_mapping), 'post', 'title'  , $this->e107_bbcode_parser); ?>
          <?php $this->parseAndUpdate(array_values($this->news_mapping), 'post', 'content', $this->e107_bbcode_parser); ?>
        <?php } ?>
        <?php if ($this->e107_import_pages) { ?>
          <li><?php _e('Parse pages title and content...', 'e107-importer'); ?></li>
          <?php $this->parseAndUpdate(array_values($this->page_mapping), 'post', 'title'  , $this->e107_bbcode_parser); ?>
          <?php $this->parseAndUpdate(array_values($this->page_mapping), 'post', 'content', $this->e107_bbcode_parser); ?>
        <?php } ?>
        <?php if ($this->e107_import_news or $this->e107_import_pages) { ?>
          <li><?php _e('Parse comments...', 'e107-importer'); ?></li>
          <?php $this->parseAndUpdate(array_values($this->comment_mapping), 'comment', 'content', $this->e107_bbcode_parser); ?>
        <?php } ?>
        <?php if ($this->e107_import_forums) { ?>
          <li><?php _e('Pars forums title and content...', 'e107-importer'); ?></li>
          <?php $this->parseAndUpdate(array_values($this->forum_mapping), 'post', 'title', $this->e107_bbcode_parser); ?>
          <?php $this->parseAndUpdate(array_values($this->forum_mapping), 'post', 'content', $this->e107_bbcode_parser); ?>
          <li><?php _e('Parse forum threads title and content...', 'e107-importer'); ?></li>
          <?php $this->parseAndUpdate(array_values($this->forum_post_mapping), 'post', 'title', $this->e107_bbcode_parser); ?>
          <?php $this->parseAndUpdate(array_values($this->forum_post_mapping), 'post', 'content', $this->e107_bbcode_parser); ?>
        <?php } ?>
        <li><?php _e('All BBCodes converted to HTML.', 'e107-importer'); ?></li>
      <?php } ?>
    </ul>

    <h3><?php _e('Images', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <?php if ($this->e107_import_images == 'no_upload') { ?>
        <li><?php _e('Image upload skipped.', 'e107-importer'); ?></li>
      <?php } else { ?>
        <li><?php _e('Upload images...', 'e107-importer'); ?></li>
        <?php if ($this->e107_import_images == 'upload_local_images') { ?>
          <li><?php printf(__('Only upload local images comming from <code>%s</code>.', 'e107-importer'), $this->e107_pref['siteurl']); ?></li>
        <?php } ?>
        <?php if ($this->e107_import_news) { ?>
          <li><?php _e('Import images embedded in news content ...', 'e107-importer'); ?></li>
          <?php $images = $this->parseAndUpdate(array_values($this->news_mapping), 'post', 'content', $this->e107_import_images); ?>
          <li><?php printf(__('%s images uploaded from news.', 'e107-importer'), $images); ?></li>
        <?php } ?>
        <?php if ($this->e107_import_pages) { ?>
          <li><?php _e('Import images embedded in page content...', 'e107-importer'); ?></li>
          <?php $images = $this->parseAndUpdate(array_values($this->page_mapping), 'post', 'content', $this->e107_import_images); ?>
        <li><?php printf(__('%s images uploaded from pages.', 'e107-importer'), $images); ?></li>
        <?php } ?>
        <?php if ($this->e107_import_news or $this->e107_import_pages) { ?>
          <li><?php _e('Import images embedded in comments...', 'e107-importer'); ?></li>
          <?php $images = $this->parseAndUpdate(array_values($this->comment_mapping), 'comment', 'content', $this->e107_import_images); ?>
          <li><?php printf(__('%s images uploaded from comments.', 'e107-importer'), $images); ?></li>
        <?php } ?>
        <?php if ($this->e107_import_forums) { ?>
          <li><?php _e('Import images embedded in forum thread content...', 'e107-importer'); ?></li>
          <?php $images = $this->parseAndUpdate(array_values($this->forum_post_mapping), 'post', 'content', $this->e107_import_images); ?>
          <li><?php printf(__('%s images uploaded from forum threads.', 'e107-importer'), $images); ?></li>
        <?php } ?>
      <?php } ?>
    </ul>

    <h3><?php _e('Internal links', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <li><?php _e('Update internal links...', 'e107-importer'); ?></li>
      <!--?php $this->replaceWithPermalinks(); ?-->
      <li><?php _e('Not implemented yet.', 'e107-importer'); ?><!--?php _e('All internal links updated.', 'e107-importer'); ?--></li>
    </ul>

    <h3><?php _e('e107 Redirector', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <li><?php _e('Activate plugin...', 'e107-importer'); ?></li>
      <?php activate_plugin(E107_REDIRECTOR_PLUGIN, '', False, True); ?>
      <li><?php _e('Plugin activated.', 'e107-importer'); ?></li>
    </ul>

    <h3><?php _e('Finished !', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <li><?php printf(__('<a href="%s">Have fun !</a>', 'e107-importer'), get_option('siteurl')); ?></li>
    </ul>

    <?php
    $this->footer();
  }
}


// Add e107 importer in the list of default WordPress import filter
$e107_import = new e107_Import();

// Show all database errors
global $wpdb;
$wpdb->show_errors();

register_importer('e107', __('e107'), __("Import news, categories, users, pages, comments, forums, threads, images and preferences from e107. Also takes care of redirections."), array ($e107_import, 'start'));

} // class_exists( 'WP_Importer' )

?>
