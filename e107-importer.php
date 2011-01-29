<?php
/*
Plugin Name: e107 Importer
Plugin URI: http://github.com/kdeldycke/e107-importer
Description: e107 import plugin for WordPress.
Author: Kevin Deldycke
Author URI: http://kevin.deldycke.com
Version: 1.1.dev
Stable tag: 1.0
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
  var $e107_extended_news;
  var $e107_bbcode_parser;
  var $e107_import_images;

  var $user_mapping;
  var $news_mapping;
  var $category_mapping;
  var $page_mapping;
  var $comment_mapping;
  var $forum_mapping;
  var $forum_post_mapping;

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
    define("e107_INIT", TRUE);

    // Create a new e107 parser
    require_once(e_HANDLER.'e_parse_class.php');
    $this->e107_parser = new e_parse;

    // $tp is required by bbcode_handler.php
    global $tp;
    $tp = $this->e107_parser;

    define("THEME", "");
    define("E107_DEBUG_LEVEL", FALSE);
    define('E107_DBG_BBSC',    FALSE);    // Show BBCode / Shortcode usage in postings

    function check_class($var, $userclass='', $peer=FALSE, $debug=FALSE) {
      return TRUE;
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
      $this->loadPreferences();

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

    // Set global SITEURL as it's used by replaceConstants() method
    $site_url = $this->e107_pref['siteurl'];
    // Normalize URL: it must end with a single slash
    define("SITEURL", $this->deleteTrailingChar($site_url, '/').'/');

    // Required to make default e107 methods aware of preferences
    global $pref;
    $pref = $this->e107_pref;
  }


  // This method search for images path in a post (or page) content,
  //   then it import the images from the remote location and
  //   finally update the HTML code accordingly.
  function importImagesFromPost($post_id, $allowed_domains=array()) {
    global $wpdb;
    $image_counter = 0;
    // Get post content
    $post = get_post($post_id);
    $html_content = $post->post_content;

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
      $domain_ok = true;
      if ($allowed_domains && is_array($allowed_domains) && sizeof($allowed_domains) > 0) {
        $domain_ok = false;
        foreach ($allowed_domains as $domain) {
          $domain = $this->deleteTrailingChar($domain, '/');
          if (substr($img_url, 0, strlen($domain)) == $domain) {
            $domain_ok = true;
            break;
          }
        }
      }
      if (!$domain_ok)
        continue;

      // XXX Hack attempting to fix http://core.trac.wordpress.org/ticket/16330
      // $img_url = "http://home.nordnet.fr/francois.jankowski/pochette avant thumb.jpg";
      // $img_url = str_replace(' ', '%20', html_entity_decode($img_url));

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
        wp_update_post(array(
            'ID'           => $post_id
          , 'post_content' => $wpdb->escape($html_content)
          ));
        // TODO: save image original path and its final permalink to not upload file twice
      }
    }
    return $image_counter;
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


  function loadPreferences() {
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
    $sql .= "WHERE user_ban = 0"; // Exclude banned and un-verified users
    // Return user list
    return $this->queryE107DB($sql);
  }


  function getE107CategoryList() {
    // Prepare the SQL request
    $e107_newsCategoryTable = $this->e107_db_prefix."news_category";
    $sql = "SELECT * FROM `".$e107_newsCategoryTable."`";
    // Return category list
    return $this->queryE107DB($sql);
  }


  function getE107NewsList() {
    // Prepare the SQL request
    $e107_newsTable = $this->e107_db_prefix."news";
    $sql = "SELECT * FROM `".$e107_newsTable."`";
    // Return news list
    return $this->queryE107DB($sql);
  }


  function getE107PageList() {
    // Prepare the SQL request
    $e107_pagesTable = $this->e107_db_prefix."page";
    $sql = "SELECT * FROM `".$e107_pagesTable."`";
    // Return page list
    return $this->queryE107DB($sql);
  }


  function getE107CommentList() {
    // Prepare the SQL request
    $e107_commentsTable = $this->e107_db_prefix."comments";
    $sql  = "SELECT * FROM `".$e107_commentsTable."`";
    // Return comment list
    return $this->queryE107DB($sql);
  }


  function getE107ForumList() {
    // Prepare the SQL request
    $e107_forumsTable = $this->e107_db_prefix."forum";
    $sql  = "SELECT * FROM `".$e107_forumsTable."` ORDER BY forum_parent";
    // Return comment list
    return $this->queryE107DB($sql);
  }


  function getE107ForumPostList() {
    // Prepare the SQL request
    $e107_postsTable = $this->e107_db_prefix."forum_t";
    $sql  = "SELECT * FROM `".$e107_postsTable."`";
    // Return comment list
    return $this->queryE107DB($sql);
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
    update_option('gzipcompression'     ,  $this->e107_pref['compress_output']);

    $tag_line = $this->e107_pref['sitetag'];
    if (strlen($tag_line) <= 0)
      $tag_line = $this->e107_pref['sitedescription'];
    update_option('blogdescription', $tag_line);

    $gmt_offset = $this->e107_pref['time_offset'];
    if (!(empty($this->e107_pref['timezone']) or (strrpos(strtolower($this->e107_pref['timezone']), strtolower('GMT')) === false))) {
      $x = 0;
      $gmt_offset = (int) $gmt_offset + $x;
    }
    update_option('gmt_offset', $gmt_offset);
  }


  // Method to force ownership of all imported content to a songle user
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
    // Get user list
    $user_list = $this->getE107UserList();

    global $wpdb;

    // This array contain the mapping between old e107 users and new wordpress users
    $this->user_mapping = array();

    // Send a mail to each user to tell them about password change ?
    $send_mail = False;
    if ($this->e107_mail_user == 'send_mail')
      $send_mail = True;

    foreach ($user_list as $user) {
      $count++;
      extract($user);
      $user_id = (int) $user_id;

      // e107 user details mapping
      // $user_loginname => WP login
      // $user_name      => WP nickname (the one to display)
      // $user_login     => WP First + Last name

      // Try to get first and last name
      $first_name = '';
      $last_name  = '';
      if (!empty($user_login)) {
        $words = explode(" ", $user_login, 2);
        $first_name = $words[0];
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

      // Build up the description based on signature, location and birthday.
      $desc = '';
      if (!empty($user_signature))
        $desc .= $user_signature.".\n";
      if (!empty($user_customtitle))
        $desc .= __("Custom Title: ").$user_customtitle.".\n";
      if (!empty($user_location))
        $desc .= __("Location: ").$user_location.".\n";
      if (!empty($user_birthday) && $user_birthday != '0000-00-00')
        $desc .= __("Birthday: ").$user_birthday.".\n";

      $user_data = array(
          'first_name'      => $wpdb->escape($first_name)
        , 'last_name'       => $wpdb->escape($last_name)
        , 'nickname'        => $wpdb->escape($user_name)
        , 'display_name'    => $wpdb->escape($display_name)
        , 'user_email'      => $wpdb->escape($user_email)
        , 'user_registered' => $this->mysql_date($user_join)
        , 'user_url'        => $wpdb->escape($user_homepage)
        , 'aim'             => $wpdb->escape($user_aim)
        , 'yim'             => $wpdb->escape($user_msn)  // Put MSN contact here because they have merged with Yahoo!: http://slashdot.org/articles/05/10/12/0227207.shtml
        , 'description'     => $wpdb->escape($desc)
        );

      // In case of an update, do not reset previous user profile properties by an empty value
      foreach ($user_data as $k=>$v)
        if (strlen($v) <= 0)
          unset($user_data[$k]);

      // Sanitize login string
      $user_loginname = sanitize_user($user_loginname, $strict=true);

      // Try to find a previous user and its ID
      $wp_user_ID = False;
      if (email_exists($user_email))
        $wp_user_ID = email_exists($user_email);
      elseif (username_exists($user_loginname))
        $wp_user_ID = username_exists($user_loginname);

      // Create a new user
      if (!$wp_user_ID) {
        // New password is required because we can't decrypt e107 password
        $new_password = wp_generate_password( 12, false);
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
    }
  }


  // Get e107 news and save them as WordPress posts
  function importNewsAndCategories() {
    // Phase 1: import categories

    // Get category list
    $category_list = $this->getE107CategoryList();

    global $wpdb;

    // This array contain the mapping between old e107 news categories and new WordPress categories
    $this->category_mapping = array();
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

    // This array contain the mapping between old e107 news and newly inserted wordpress posts
    $this->news_mapping = array();

    foreach ($news_list as $news) {
      $count++;
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
    // Get static pages list
    $page_list = $this->getE107PageList();

    global $wpdb;

    // This array contain the mapping between old e107 static pages and newly inserted WordPress pages
    $this->page_mapping = array();

    foreach ($page_list as $page) {
      $count++;
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
      $author_id = $this->user_mapping[$page_author];
      $author = new WP_User($author_id);
      if (! $author->has_cap('edit_posts'))
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
          'post_author'    => $author_id                           // use the new wordpress user ID
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
    // Get News list
    $comment_list = $this->getE107CommentList();

    global $wpdb;

    // This array contain the mapping between old e107 comments and newly inserted WordPress comments
    $this->comment_mapping = array();

    foreach ($comment_list as $comment) {
      $count++;
      extract($comment);
      $comment_id      = (int) $comment_id;
      $comment_item_id = (int) $comment_item_id;

      // Get the post_id from $news_mapping or $pages_mapping depending of the comment type
      if ($comment_type == 'page')
        $post_id = $this->page_mapping[$comment_item_id];
      else
        $post_id = $this->news_mapping[$comment_item_id];

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

        // Save e107 comment in WordPress database
        $ret_id = wp_insert_comment(array(
            'comment_post_ID'      => $post_id
          , 'comment_author'       => $wpdb->escape($author_name)
          , 'comment_author_email' => $wpdb->escape($author_email)
          , 'comment_author_url'   => $wpdb->escape($author_url)
          , 'comment_author_IP'    => $author_ip
          , 'comment_date'         => $this->mysql_date($comment_datestamp)  //XXX ask or get the time offset ?
          , 'comment_date_gmt'     => $this->mysql_date($comment_datestamp)  //XXX ask or get the time offset ?
          , 'comment_content'      => $wpdb->escape($comment_comment)
          , 'comment_approved'     => ! (int) $comment_blocked
          , 'user_id'              => $author_id
          , 'user_ID'              => $author_id
          , 'filtered'             => true
          ));

        // Update post mapping
        $this->comment_mapping[$comment_id] = (int) $ret_id;
      }
    }
  }


  // Import e107 forums to bbPress WordPress plugin
  function importForums() {
    global $wpdb;
    $this->forum_mapping = array();

    // Get all forum
    $forum_list = $this->getE107ForumList();

    foreach ($forum_list as $forum) {
      extract($forum);
      $forum_id     = (int) $forum_id;
      $forum_parent = (int) $forum_parent;

      // The moderator of the forum is set as author
      // XXX Does e107 put several user IDs in this $forum_moderators field ?
      $author_id = (int) $this->user_mapping[$forum_moderators];

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
        //, 'post_status'    => 'publish'
        , 'post_type'      => 'bbp_forum'
        //, 'comment_status' => 'closed'
        //, 'ping_status'    => 'closed'
        , 'post_parent'    => $updated_parent
        , 'menu_order'     => (int) $forum_order
        ));

      // Update forum mapping
      $this->forum_mapping[$forum_id] = (int) $ret_id;

      do_action('publish_phone', $post_ID);

      // Set forum type
      if ($forum_parent == 0) {
        // The forum is a category
        bbp_categorize_forum($ret_id);
      } else {
        // The forum is a normal forum
        bbp_normalize_forum($ret_id);
      }

      // Set forum visibility. 0 is public for e107.
      if ($forum_parent == 0) {
        bbp_publicize_forum($ret_id);
      } else {
        bbp_privatize_forum($ret_id);
      }

      // XXX What to do with e107's $forum_postclass ?

      // XXX How to handle e107's $forum_sub field ?

      // Does e107 support open and close forum ?
      //bbp_close_forum()
      //bbp_open_forum()
    }
  }


  // Import e107 forum content to bbPress WordPress plugin
  function importForumContent() {
    global $wpdb;
    $this->forum_post_mapping = array();

    // Get all forum posts
    $forum_post_list = $this->getE107ForumPostList();

    // XXX This is just a placeholder for now

  }


  // This method parse content of news, pages and comments to replace all {e_SOMETHING} e107's constants
  function replaceConstants() {
    global $wpdb;
    // Get the list of WordPress news and page IDs
    $news_and_pages_ids = array_merge(array_values($this->news_mapping), array_values($this->page_mapping));
    // Parse BBCode in each news and page
    foreach ($news_and_pages_ids as $post_id) {
      // Get post content
      $post = get_post($post_id);
      $content = $post->post_content;
      // Apply constants transformation
      $new_content = $this->e107_parser->replaceConstants($content, $nonrelative = "full", $all = True);
      // Update post content if necessary
      if ($new_content != $content) {
        wp_update_post(array(
            'ID'           => $post_id
          , 'post_content' => $wpdb->escape($new_content)
          ));
      }
    }

    // Get the list of all WP comments IDs
    $comments_ids = array_values($this->comment_mapping);
    // Parse BBCode in each news and page
    foreach ($comments_ids as $comment_id) {
      // Get comment content
      $comment = get_comment($comment_id);
      $content = $comment->comment_content;
      // Apply constants transformation
      $new_content = $this->e107_parser->replaceConstants($content, $nonrelative = "full", $all = True);
      // Update comment content if necessary
      if ($new_content != $content)
        wp_update_comment(array(
            'comment_ID'      => $comment_id
          , 'comment_content' => $wpdb->escape($new_content)
          ));
    }
  }


  // This method replace old e107 URLs embeded in news, pages and comments by WP permalinks
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


  // Transform BBCode to HTML using original e107 parser
  // TODO: parse BBCode in titles (both posts and pages) !
  // TODO: factorize with replaceConstants() -> less code & less database IO
  function parseBBCodeWithE107() {
    global $wpdb;
    // Get the list of WordPress news and page IDs
    $news_and_pages_ids = array_merge(array_values($this->news_mapping), array_values($this->page_mapping));
    // Parse BBCode in each news and page
    foreach ($news_and_pages_ids as $post_id) {
      // Get post content
      $post = get_post($post_id);
      $content = $post->post_content;
      // Apply BBCode transformation
      $new_content = $this->e107_parser->toHTML($content, $parseBB = TRUE);
      // Update post content if necessary
      if ($new_content != $content)
        wp_update_post(array(
            'ID'           => $post_id
          , 'post_content' => $wpdb->escape($new_content)
          ));
    }

    // Get the list of all WP comments IDs
    $comments_ids = array_values($this->comment_mapping);
    // Parse BBCode in each news and page
    foreach ($comments_ids as $comment_id) {
      // Get comment content
      $comment = get_comment($comment_id);
      $content = $comment->comment_content;
      // Apply BBCode transformation
      $new_content = $this->e107_parser->toHTML($content, $parseBB = TRUE);
      // Update comment content if necessary
      if ($new_content != $content)
        wp_update_comment(array(
            'comment_ID'      => $comment_id
          , 'comment_content' => $wpdb->escape($new_content)
          ));
    }
  }


  // Transform BBCode to HTML using enhanced parser
  function parseBBCodeWithEnhancedParser() {
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
  }


  // This method import all images embedded in news and pages to WordPress
  function importImages($local_only = false) {
    $image_counter = 0;

    // Build the list of authorized domains from which we are allowed to import images
    $allowed_domains = array();
    if ($local_only == true)
      $allowed_domains[] = $this->e107_pref['siteurl'];

    // Get the list of WordPress news and page IDs
    $news_and_pages_ids = array_merge(array_values($this->news_mapping), array_values($this->page_mapping));
    foreach ($news_and_pages_ids as $post_id)
      $image_counter = $image_counter + $this->importImagesFromPost($post_id, $allowed_domains);
    return $image_counter;
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
    if ($action == 'import')
      $this->import();
    else
      $this->printWelcomeScreen();
  }


  function header() {
    echo '<div class="wrap">';
    screen_icon();
    echo '<h2>'.__('e107 Importer', 'e107-importer').'</h2>';
  }


  function footer() {
    echo '</div>';
  }


  function printWelcomeScreen() {
    $this->header();
    // TODO: get the description from the readme.txt and display it here
    // TODO: use AJAX to validate the form ?
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
      <p><?php _e('This importer can read the preferences set for e107 and apply them to this current blog. Supported preferences are: site title, site description, admin e-mail address, open user registration, users registration for comment, emoticons graphical convertion, number of posts per pages, GZip compression and timezone offset.', 'e107-importer'); ?></p>
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
              , __(get_settings('default_role'))
              , get_option('siteurl')
              );
      ?></p>

      <h3><?php _e('News Extends', 'e107-importer'); ?></h3>
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
        <table class="form-table">
          <tr valign="top">
            <th scope="row"><?php _e('Do you want to import forums ?', 'e107-importer'); ?></th>
            <td>
              <label for="import-forums"><input name="e107_import_forums" type="radio" id="import-forums" value="import_forums"/> <?php _e('Yes: import forums from e107', 'e107-importer'); ?><?php if (!is_plugin_active(BBPRESS_PLUGIN)) _e(' and activate the bbPress plugin', 'e107-importer'); ?>.</label><br/>
              <label for="ignore-forums"><input name="e107_import_forums" type="radio" id="ignore-forums" value="ignore_forums" checked="checked"/> <?php _e('No: do not import forums from e107 to bbPress.', 'e107-importer'); ?></label><br/>
            </td>
          </tr>
        </table>
      <?php } ?>

      <h3><?php _e('BBCode', 'e107-importer'); ?></h3>
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><?php _e('Which kind of <a href="http://wikipedia.org/wiki/Bbcode">BBCode</a> parser you want to use ?', 'e107-importer'); ?></th>
          <td>
            <label for="original"><input name="e107_bbcode_parser" type="radio" id="original" value="original" checked="checked"/> <?php _e('e107\'s parser (content will be rendered exactly as they appear in e107).', 'e107-importer'); ?></label><br/>
            <!--label for="semantic"><input name="e107_bbcode_parser" type="radio" id="enhanced" value="enhanced"/--> <!--?php _e('Enhanced parser (try to create HTML code similar to what WordPress produce by default).', 'e107-importer'); ?></label><br/-->
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
            <label for="upload-all"><input name="e107_import_images" type="radio" id="upload-all" value="upload_all"/> <?php _e('Yes: upload all images, even those located on external sites.', 'e107-importer'); ?></label><br/>
            <label for="site-upload"><input name="e107_import_images" type="radio" id="site-upload" value="site_upload" checked="checked"/> <?php _e('Yes, but: upload files from the e107 site only, not external images.', 'e107-importer'); ?></label><br/>
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
                              , 'e107_extended_news'
                              , 'e107_import_forums'
                              , 'e107_bbcode_parser'
                              , 'e107_import_images'
                              );

    // Register each option as class global variables
    foreach ($e107_option_names as $o)
      if ($_POST[$o])
        $this->$o = $_POST[$o];

    // TODO: use AJAX to display a progress bar http://wordpress.com/blog/2007/02/06/new-blogger-importer/
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
      <?php $this->loadPreferences(); ?>
      <li><?php _e('Preferences loaded.', 'e107-importer'); ?></li>
      <li><?php _e('Import preferences...', 'e107-importer'); ?></li>
      <?php if ($this->e107_preferences == 'import_pref') { ?>
        <?php $this->importPreferences(); ?>
        <li><?php _e('All preferences imported.', 'e107-importer'); ?></li>
      <?php } else { ?>
        <li><?php _e('Do not import preferences.', 'e107-importer'); ?></li>
      <?php } ?>
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

    <h3><?php _e('News and Categories', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <li><?php _e('Import news and categories...', 'e107-importer'); ?></li>
      <?php $this->importNewsAndCategories(); ?>
      <li><?php printf(__('%s news imported.', 'e107-importer'), sizeof($this->news_mapping)); ?></li>
      <li><?php printf(__('%s categories imported.', 'e107-importer'), sizeof($this->category_mapping)); ?></li>
      <li><?php _e('Update redirection plugin with news mapping...', 'e107-importer'); ?></li>
      <?php $this->updateRedirectorSettings('news_mapping', $this->news_mapping); ?>
      <li><?php _e('Old news URLs are now redirected to permalinks.', 'e107-importer'); ?></li>
    </ul>

    <h3><?php _e('Pages', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <li><?php _e('Import pages...', 'e107-importer'); ?></li>
      <?php $this->importPages(); ?>
      <li><?php printf(__('%s pages imported.', 'e107-importer'), sizeof($this->page_mapping)); ?></li>
      <li><?php _e('Update redirection plugin with page mapping...', 'e107-importer'); ?></li>
      <?php $this->updateRedirectorSettings('page_mapping', $this->page_mapping); ?>
      <li><?php _e('Old page URLs are now redirected to permalinks.', 'e107-importer'); ?></li>
    </ul>

    <h3><?php _e('Comments', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <li><?php _e('Import comments...', 'e107-importer'); ?></li>
      <?php $this->importComments(); ?>
      <li><?php printf(__('%s comments imported.', 'e107-importer'), sizeof($this->comment_mapping)); ?></li>
      <li><?php _e('Update redirection plugin with comment mapping...', 'e107-importer'); ?></li>
      <?php $this->updateRedirectorSettings('comment_mapping', $this->comment_mapping); ?>
      <li><?php _e('Old comments URLs are now redirected.', 'e107-importer'); ?></li>
    </ul>

    <h3><?php _e('Forums', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <?php if ($this->e107_import_forums == 'import_forums') { ?>
        <?php if (!is_plugin_active(BBPRESS_PLUGIN)) { ?>
          <li><?php _e('Activate bbPress plugin...', 'e107-importer'); ?></li>
          <?php activate_plugin(BBPRESS_PLUGIN, '', false, true); ?>
          <li><?php _e('Plugin activated.', 'e107-importer'); ?></li>
        <?php } ?>
        <li><?php _e('Import forums and forum categories...', 'e107-importer'); ?></li>
        <?php $this->importForums(); ?>
        <li><?php printf(__('%s forums and forum categories imported.', 'e107-importer'), sizeof($this->forum_mapping)); ?></li>
        <li><?php _e('Import forum content...', 'e107-importer'); ?></li>
        <?php $this->importForumContent(); ?>
        <li><?php printf(__('%s forum posts imported.', 'e107-importer'), sizeof($this->forum_post_mapping)); ?></li>
        <li><?php _e('Update redirection plugin with forum and forum post mapping...', 'e107-importer'); ?></li>
        <?php $this->updateRedirectorSettings('forum_mapping',      $this->forum_mapping); ?>
        <?php $this->updateRedirectorSettings('forum_post_mapping', $this->forum_post_mapping); ?>
        <li><?php _e('Old forum URLs are now redirected.', 'e107-importer'); ?></li>
      <?php } else { ?>
        <li><?php _e('e107 forums import skipped.', 'e107-importer'); ?></li>
      <?php } ?>
    </ul>

    <h3><?php _e('Content Parsing', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <li><?php _e('Initialize e107 context...', 'e107-importer'); ?></li>
      <?php $this->inite107Context(); ?>
      <li><?php _e('e107 context initialized.', 'e107-importer'); ?></li>
      <li><?php _e('Replace e107 constants...', 'e107-importer'); ?></li>
      <?php $this->replaceConstants(); ?>
      <li><?php _e('All e107 constants replaced by proper URLs.', 'e107-importer'); ?></li>
      <li><?php _e('Parse BBCode...', 'e107-importer'); ?></li>
      <?php if ($this->e107_bbcode_parser == 'enhanced') { ?>
        <?php $this->parseBBCodeWithEnhancedParser(); ?>
        <li><?php _e('BBCode converted to HTML using e107 Importer\'s enhanced parser.', 'e107-importer'); ?></li>
      <?php } elseif ($this->e107_bbcode_parser == 'original') { ?>
        <?php $this->parseBBCodeWithE107(); ?>
        <li><?php _e('BBCode converted to HTML using the original e107 parser.', 'e107-importer'); ?></li>
      <?php } else { ?>
        <li><?php _e('BBCode tags left as-is.', 'e107-importer'); ?></li>
      <?php } ?>
    </ul>

    <h3><?php _e('Images', 'e107-importer'); ?></h3>
    <ul class="ul-disc">
      <li><?php _e('Upload images...', 'e107-importer'); ?></li>
      <?php $images = 0; ?>
      <?php if ($this->e107_import_images == 'upload_all') { ?>
        <?php $images = $this->importImages(); ?>
        <li><?php printf(__('%s images from news and pages uploaded.', 'e107-importer'), $images); ?></li>
      <?php } elseif ($this->e107_import_images == 'site_upload') { ?>
        <li><?php printf(__('Only upload images from <code>%s</code>.', 'e107-importer'), $this->e107_pref['siteurl']); ?></li>
        <?php $images = $this->importImages(true); ?>
        <li><?php printf(__('%s images from news and pages uploaded.', 'e107-importer'), $images); ?></li>
      <?php } else { ?>
        <li><?php _e('Image upload skipped.', 'e107-importer'); ?></li>
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
      <?php activate_plugin(E107_REDIRECTOR_PLUGIN, '', false, true); ?>
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

register_importer('e107', __('e107'), __("Import news, categories, users, custom pages, comments, images and preferences from e107. Also takes care of redirections."), array ($e107_import, 'start'));

} // class_exists( 'WP_Importer' )

?>
