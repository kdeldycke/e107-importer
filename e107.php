 <?php
/*
+---------------------------------------------------------------+
|   Wordpress filter to import e107 website.
|
|   Version: 0.7
|   Date: 08 mar 2007
|
|   For todo-list and changelog:
|     * http://kev.coolcavemen.com/2006/09/e107-to-wordpress-importer-v02-with-bbcode-support/
|     * http://kev.coolcavemen.com/2006/08/e107-to-wordpress-importer-alpha-version/
|
|   (c) Kevin Deldycke 2006-2007
|   http://kev.coolcavemen.com
|   kev@coolcavemen.com
|
|   Released under the terms and conditions of the
|   GNU General Public License (http://gnu.org).
+---------------------------------------------------------------+
*/


class e107_Import {

  var $file;

  function header() {
    echo '<div class="wrap">';
  }


  function footer() {
    echo '</div>';
  }


  // Convert unix timestamp to mysql datetimestamp
  function mysql_date($unix_time) {
    return date("Y-m-d H:i:s", $unix_time);
  }


  // Convert hexadecimal IP adresse string to decimal
  function ip_hex2dec($hex_ip) {
    if (strlen($hex_ip) != 8) {
      return '';
    }
    $dec_ip  = (string) hexdec(substr($hex_ip, 0, 2));
    $dec_ip .= '.';
    $dec_ip .= (string) hexdec(substr($hex_ip, 2, 2));
    $dec_ip .= '.';
    $dec_ip .= (string) hexdec(substr($hex_ip, 4, 2));
    $dec_ip .= '.';
    $dec_ip .= (string) hexdec(substr($hex_ip, 6, 2));
    return $dec_ip;
  }


  // Below isValidInetAddress() function come from PEAR's Mail package v1.1.14
  // See http://pear.php.net/package/Mail for details.
  // +-----------------------------------------------------------------------+
  // | Copyright (c) 2001-2002, Richard Heyes                                |
  // | All rights reserved.                                                  |
  // |                                                                       |
  // | Redistribution and use in source and binary forms, with or without    |
  // | modification, are permitted provided that the following conditions    |
  // | are met:                                                              |
  // |                                                                       |
  // | o Redistributions of source code must retain the above copyright      |
  // |   notice, this list of conditions and the following disclaimer.       |
  // | o Redistributions in binary form must reproduce the above copyright   |
  // |   notice, this list of conditions and the following disclaimer in the |
  // |   documentation and/or other materials provided with the distribution.|
  // | o The names of the authors may not be used to endorse or promote      |
  // |   products derived from this software without specific prior written  |
  // |   permission.                                                         |
  // |                                                                       |
  // | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS   |
  // | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT     |
  // | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR |
  // | A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT  |
  // | OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, |
  // | SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT      |
  // | LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, |
  // | DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY |
  // | THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT   |
  // | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE |
  // | OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.  |
  // |                                                                       |
  // +-----------------------------------------------------------------------+
  // | Authors: Richard Heyes <richard@phpguru.org>                          |
  // |          Chuck Hagenbuch <chuck@horde.org>                            |
  // +-----------------------------------------------------------------------+
  /**
    * This is a email validating function separate to the rest of the
    * class. It simply validates whether an email is of the common
    * internet form: <user>@<domain>. This can be sufficient for most
    * people. Optional stricter mode can be utilised which restricts
    * mailbox characters allowed to alphanumeric, full stop, hyphen
    * and underscore.
    *
    * @param  string  $data   Address to check
    * @param  boolean $strict Optional stricter mode
    * @return mixed           False if it fails, an indexed array
    *                         username/domain if it matches
    */
  function isValidInetAddress($data, $strict = false)
  {
    $regex = $strict ? '/^([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})$/i' : '/^([*+!.&#$|\'\\%\/0-9a-z^_`{}=?~:-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})$/i';
    if (preg_match($regex, trim($data), $matches)) {
      return array($matches[1], $matches[2]);
    } else {
      return false;
    }
  }


  // Generic code to initialize e107 context
  function inite107context()
  {
    /* Some part of the code below is copy of (and/or inspired by) code from the e107 project, licensed
    ** under the GPL and (c) copyrighted to Steve Dunstan (see copyright headers).
    */

    /*========== START of code inspired by e107_handlers/e107_class.php file ==========
    + ----------------------------------------------------------------------------+
    |     e107 website system
    |
    |     (c) Steve Dunstan 2001-2002
    |     http://e107.org
    |     jalist@e107.org
    |
    |     Released under the terms and conditions of the
    |     GNU General Public License (http://gnu.org).
    |
    |     $Source: /cvsroot/e107/e107_0.7/e107_handlers/e107_class.php,v $
    |     $Revision: 1.51 $
    |     $Date: 2006/04/05 12:03:04 $
    |     $Author: mcfly_e107 $
    +----------------------------------------------------------------------------+
    */
    $path = "";
    $i = 0;
    while (!file_exists("{$path}wp-config.php")) {
      $path .= "../";
      $i++;
    }

    $e107_to_wordpress_includes = 'wp-admin/import/e107-includes/';

    // Redifine som globals to match wordpress importer file hierarchy
    define("e_BASE", $path);
    define("e_PLUGIN" , e_BASE);
    define("e_FILE"   , e_BASE.'wp-admin/import/');
    define("e_HANDLER", e_BASE.$e107_to_wordpress_includes);
    /*========== END of code inspired by e107_handlers/e107_class.php file ==========*/


    /*========== START of code inspired by class2.php file ==========
    + ----------------------------------------------------------------------------+
    |     e107 website system
    |
    |       Steve Dunstan 2001-2002
    |     http://e107.org
    |     jalist@e107.org
    |
    |     Released under the terms and conditions of the
    |     GNU General Public License (http://gnu.org).
    |
    |     $Source: /cvsroot/e107/e107_0.7/class2.php,v $
    |     $Revision: 1.322 $
    |     $Date: 2006/11/25 03:38:19 $
    |     $Author: mcfly_e107 $
    +----------------------------------------------------------------------------+
    */
    define("e107_INIT", TRUE);

    require_once(e_HANDLER.'e_parse_class.php');
    global $tp;
    $tp = new e_parse;

    define("THEME", "");
    define("E107_DEBUG_LEVEL", FALSE);
    define('E107_DBG_BBSC',    FALSE);    // Show BBCode / Shortcode usage in postings

    // Use these to combine isset() and use of the set value. or defined and use of a constant
    // i.e. to fix  if($pref['foo']) ==> if ( varset($pref['foo']) ) will use the pref, or ''.
    // Can set 2nd param to any other default value you like (e.g. false, 0, or whatever)
    // $testvalue adds additional test of the value (not just isset())
    // Examples:
    // $something = pref;  // Bug if pref not set         ==> $something = varset(pref);
    // $something = isset(pref) ? pref : "";              ==> $something = varset(pref);
    // $something = isset(pref) ? pref : default;         ==> $something = varset(pref,default);
    // $something = isset(pref) && pref ? pref : default; ==> $something = varset(pref,default, true);
    //
    function varset(&$val,$default='',$testvalue=false) {
            if (isset($val)) {
                    return (!$testvalue || $val) ? $val : $default;
            }
            return $default;
    }
    function defset($str,$default='',$testvalue=false) {
            if (defined($str)) {
                    return (!$testvalue || constant($str)) ? constant($str) : $default;
            }
            return $default;
    }
    /*========== END of code inspired by class2.php file ==========*/

    // Set global preferences
    global $pref;

    // Get e107 site preferences from user database
    $e107_db_user = get_option('e107_db_user');
    $e107_db_pass = get_option('e107_db_pass');
    $e107_db_name = get_option('e107_db_name');
    $e107_db_host = get_option('e107_db_host');
    $prefix       = get_option('e107_db_prefix');
    if ($e107_db_user != '' and $e107_db_pass != '' and $e107_db_name != '' and $e107_db_host != '' and $prefix != '') {
      $e107db = new wpdb( $e107_db_user
                        , $e107_db_pass
                        , $e107_db_name
                        , $e107_db_host
                        );
      set_magic_quotes_runtime(0);
      $e107_coreTable  = $prefix."core";
      $sql = "SELECT `e107_value` FROM `".$e107_coreTable."` WHERE `e107_name`='SitePrefs'";
      $site_pref = $e107db->get_results($sql, ARRAY_A);
      extract($site_pref[0]);
      $pref = '';
      $array_data = '$pref = '.trim($e107_value).';';
      @eval($array_data);
    }

    // Override bbcode definition files configuration
    if (!isset($pref) || !is_array($pref)) {
      $pref = array();
    }
    $pref['bbcode_list'] = array();
    $pref['bbcode_list'][$e107_to_wordpress_includes] = array();
    // This $core_bb array come from bbcode_handler.php
    $core_bb = array(
    'blockquote', 'img', 'i', 'u', 'center',
    '*br', 'color', 'size', 'code',
    'html', 'flash', 'link', 'email',
    'url', 'quote', 'left', 'right',
    'b', 'justify', 'file', 'stream',
    'textarea', 'list', 'php', 'time',
    'spoiler', 'hide'
    );
    foreach($core_bb as $c) {
      $pref['bbcode_list'][$e107_to_wordpress_includes][$c] = 'dummy_u_class';
    }

    // TODO: support image handling
    unset($pref['image_post']);

    // Don't transform smileys to <img>, Wordpress will do it automaticcaly
    unset($pref['smiley_activate']);
  }


  // Step 1: import e107 users
  function importUsers()
  {
    // General Housekeeping
    // TODO: reuse this code (create a method or something a little bit more generic)
    $e107db = new wpdb( get_option('e107_db_user')
                      , get_option('e107_db_pass')
                      , get_option('e107_db_name')
                      , get_option('e107_db_host')
                      );
    set_magic_quotes_runtime(0);
    $prefix = get_option('e107_db_prefix');

    // Prepare the SQL request
    $e107_userTable         = $prefix."user";
    $e107_userExtendedTable = $prefix."user_extended";
    $sql  = "SELECT ".$e107_userTable.".* FROM ".$e107_userTable." ";
    $sql .= "LEFT JOIN ".$e107_userExtendedTable." ON ".$e107_userTable.".user_id = ".$e107_userExtendedTable.".user_extended_id ";
    $sql .= "WHERE user_ban = 0"; // Exclude banned and un-verified users
    //echo $sql;

    // Get user list
    $user_list = $e107db->get_results($sql, ARRAY_A);

    // This array contain the mapping between old e107 users and new wordpress users
    $user_mapping = array();

    // Send a mail to each user to tell them about password change ?
    $send_mail = False;
    if (get_option('e107_mail_user') == 'send_mail') {
      $send_mail = True;
    }

    foreach($user_list as $user)
    {
      $count++;
      extract($user);
      // Cast to int
      $user_id = (int) $user_id;

      //echo '$user_loginname='.$user_loginname.'<br/>';
      //echo '$user='.print_r($user).'<br/><br/>';

      // Create a new user
      if (! username_exists($user_loginname)) {
        // New password is required because we are not able to decrypt e107 password
        $new_password = substr(md5(uniqid(microtime())), 0, 6);

        // $user_image // XXX how to handle this ? export to gravatar ???

        // Build up the description based on signature, location and birthday.
        $desc = '';
        if (!empty($user_signature))
          $desc .= $user_signature.".\n";
        if (!empty($user_location))
          $desc .= __("Location: ").$user_location.".\n";
        if (!empty($user_birthday) && $user_birthday != '0000-00-00')
          $desc .= __("Birthday: ").$user_birthday.".\n";

        // Decode strings from UTF-8
        $user_customtitle = utf8_decode($user_customtitle);
        $user_name        = utf8_decode($user_name);

        // Get the best nickname
        $nickname = utf8_decode($user_loginname);
        if (!empty($user_customtitle)) {
          $nickname = $user_customtitle;
        } elseif (!empty($user_name)) {
          $nickname = $user_name;
        }

        $ret_id = wp_insert_user(array(
            'user_login'      => $e107db->escape(utf8_decode($user_loginname))
          , 'first_name'      => $e107db->escape($user_name)
          , 'nickname'        => $e107db->escape($nickname)
          , 'user_pass'       => $e107db->escape($new_password)
          , 'user_email'      => $e107db->escape($user_email)
          , 'user_registered' => $this->mysql_date($user_join)
          , 'user_url'        => $e107db->escape($user_homepage)
          , 'aim'             => $e107db->escape($user_aim)
          , 'yim'             => $e107db->escape($user_msn)  // Put MSN contact here because MSN and Yahoo! have merged some months ago: http://slashdot.org/articles/05/10/12/0227207.shtml
          , 'description'     => $e107db->escape(utf8_decode($desc))
          ));

        // Send mail notification to users to warn them of a new password (and new login because of UTF-8)
        if ($send_mail) {
          wp_new_user_notification($ret_id, $new_password);
        }

      } else {
        // User already exist, get it and his ID
        $userdata = get_userdatabylogin($user_loginname);
        $ret_id = $userdata->ID;
        // TODO: update user data !
      }
      // Update user mapping, cast to int
      $user_mapping[$user_id] = (int) $ret_id;
    }

    // Return user mapping to let us pass it as global variable via options
    return $user_mapping;
  }


  // Step 2: get e107 news and save them as Wordpress posts
  function e107news2posts()
  {
    // General Housekeeping
    $e107db = new wpdb( get_option('e107_db_user')
                      , get_option('e107_db_pass')
                      , get_option('e107_db_name')
                      , get_option('e107_db_host')
                      );
    set_magic_quotes_runtime(0);
    $prefix        = get_option('e107_db_prefix');
    $user_mapping  = get_option('e107_user_mapping');
    $extended_news = get_option('e107_extended_news');

    // Phase 1: import categories
    echo '<p>'.__('Importing e107 categories in Wordpress...').'</p>';
    // Prepare the SQL request
    $e107_newsCategoryTable  = $prefix."news_category";
    $sql = "SELECT * FROM `".$e107_newsCategoryTable."`";
    // Get category list
    $category_list = $e107db->get_results($sql, ARRAY_A);
    // This array contain the mapping between old e107 news categories and new wordpress categories
    $category_mapping = array();
    foreach($category_list as $category) {
      extract($category);
      $cat_id = category_exists($category_name);
      if (! $cat_id) {
        $new_cat = array();
        $new_cat['cat_name'] = $category_name;
        $cat_id = wp_insert_category($new_cat);
      }
      $category_mapping[$category_id] = (int) $cat_id;
    }
    echo '<p><strong>'.sizeof($category_mapping).'</strong>'.__(' categories imported from e107 to Wordpress.').'</p>';


    // Phase 2: Convert news to post
    echo '<p>'.__('Importing e107 news as Wordpress posts...').'</p>';

    // Prepare the SQL request
    $e107_newsTable  = $prefix."news";
    $sql = "SELECT * FROM `".$e107_newsTable."`";

    // Get News list
    $news_list = $e107db->get_results($sql, ARRAY_A);

    // This array contain the mapping between old e107 news and newly inserted wordpress posts
    $news_mapping = array();

    foreach($news_list as $news)
    {
      $count++;
      extract($news);
      // Cast to int
      $news_id = (int) $news_id;

      // Special actions for extended news
      if ($extended_news == 'import_all') {
        $news_body = $news_body."\n\n".$news_extended;
      } elseif ($extended_news == 'ignore_body') {
        $news_body = $news_extended;
      }

      // Update author role if necessary;
      // If the user has the minimum role (aka subscriber) he is not able to post
      //   news. In this case, we raise is role by 1 level (aka contributor).
      $author_id = $user_mapping[$news_author];
      $author = new WP_User($author_id);
      if (! $author->has_cap('edit_posts')) {
        $author->set_role('contributor');
      }

      // Create a new html parser
      if (!is_object($this->e_parse)) {
        require_once(e_HANDLER.'e_parse_class.php');
        $this->e_parse = new e_parse;
      }

      // Save e107 news in Wordpress database
      $ret_id = wp_insert_post(array(
          'post_author'    => $author_id                          // use the new wordpress user ID
        , 'post_date'      => $this->mysql_date($news_datestamp)  //XXX ask or get the time offset ?
        , 'post_date_gmt'  => $this->mysql_date($news_datestamp)  //XXX ask or get the time offset ?
        , 'post_content'   => $e107db->escape($this->e_parse->toHTML($news_body, $parseBB = TRUE))
        , 'post_title'     => $e107db->escape($news_title)        //XXX bbcode allowed in titles ?
        , 'post_status'    => 'publish'                           // News are always published in e107
        , 'comment_status' => $news_allow_comments                //TODO: get global config to set this value dynamiccaly
        , 'ping_status'    => 'open'                              //XXX is there such a concept in e107 ?
        //, 'post_modified'  =>     //XXX Auto or now() ?
        //, 'post_modified_gmt'  => //XXX Auto or now() ?
        , 'comment_count'  => $news_comment_total
        ));

      // Update post mapping
      $news_mapping[$news_id] = (int) $ret_id;

      // Link post to category
      $news_category = (int) $news_category;
      if (array_key_exists($news_category, $category_mapping)) {
        $cats = array();
        $cats[1] = $category_mapping[$news_category];
        wp_set_post_categories($ret_id, $cats);
      }
    }

    // Return news mapping to let us pass it as global variable via options
    return $news_mapping;
  }


  // Step 3: get e107 static pages and save them as Wordpress pages
  function importPages()
  {
    // General Housekeeping
    $e107db = new wpdb( get_option('e107_db_user')
                      , get_option('e107_db_pass')
                      , get_option('e107_db_name')
                      , get_option('e107_db_host')
                      );
    set_magic_quotes_runtime(0);
    $prefix       = get_option('e107_db_prefix');
    $user_mapping = get_option('e107_user_mapping');
    $patch_theme  = get_option('e107_patch_theme');

    // Convert static pages to Wordpress pages
    echo '<p>'.__('Importing e107 static pages as Wordpress pages...').'</p>';

    // Prepare the SQL request
    $e107_pagesTable  = $prefix."page";
    $sql = "SELECT * FROM `".$e107_pagesTable."`";

    // Get Static Pages list
    $page_list = $e107db->get_results($sql, ARRAY_A);

    // This array contain the mapping between old e107 static pages and newly inserted wordpress pages
    $pages_mapping = array();

    foreach($page_list as $page)
    {
      $count++;
      extract($page);
      // Cast to int
      $page_id = (int) $page_id;

      // Create a new html parser
      if (!is_object($this->e_parse)) {
        require_once(e_HANDLER.'e_parse_class.php');
        $this->e_parse = new e_parse;
      }

      if ($patch_theme == 'patch_theme') {
        // Auto-patch kubrick Page Template file to show comments by default
        // Switch to default kubrick theme
        $ct = current_theme_info();
        if ($ct->name != 'default')
        {
          update_option('template', 'default');
          update_option('stylesheet', 'default');
        }
        // TODO: send patch to fix this in trunk kubrik
        // Patch sent to Wordpress: http://trac.wordpress.org/ticket/3753 -> Wait and see...
        $real_file = get_real_file_to_edit("wp-content/themes/default/page.php");
        $f = fopen($real_file, 'r');
        $content = fread($f, filesize($real_file));
        fclose($f);
        // Check if patch already applied
        if (!strpos($content, "comments_template()"))
        {
          // Look at the last "</div>" tag
          require_once(e_BASE.'wp-admin/import/e107-includes/strripos.php');
          $cut_position = strripos($content, "</div>");
          $patched_content  = substr($content, 0, $cut_position);
          $patched_content .= "<?php comments_template(); ?>";
          $patched_content .= substr($content, $cut_position, strlen($content)-1);
          $f = fopen($real_file, 'w+');
          fwrite($f, $patched_content);
          fclose($f);
        }
        // TODO: support K2 theme ? In this case, just add "$page_template = 'page-comments.php';" when inserting static page to Wordpress
      }

      // Set the status of the post: 'publish' or 'private'. 'draft' has no equivalent in e107.
      $post_status = 'publish';
      if ($page_class != '0') {
        $post_status = 'private';
      }

      // Update author role if necessary;
      // If the user has the minimum role (aka subscriber) he is not able to post
      //   news. In this case, we raise is role by 1 level (aka contributor).
      $author_id = $user_mapping[$page_author];
      $author = new WP_User($author_id);
      if (! $author->has_cap('edit_posts')) {
        $author->set_role('contributor');
      }
      // If user is the author of a private page give him the 'editor' role else he can't view private pages
      if (($post_status == 'private') and (! $author->has_cap('read_private_pages'))) {
        $author->set_role('editor');
      }

      // Define comment status
      $page_template;
      if (! $page_comment_flag) {
        $comment_status = 'closed';
        unset($page_template);
      } else {
        $comment_status = 'open';
      }

      // Save e107 static page in Wordpress database
      $ret_id = wp_insert_post(array(
          'post_author'    => $author_id     // use the new wordpress user ID
        , 'post_date'      => $this->mysql_date($page_datestamp)  //XXX ask or get the time offset ?
        , 'post_date_gmt'  => $this->mysql_date($page_datestamp)  //XXX ask or get the time offset ?
        , 'post_content'   => $e107db->escape($this->e_parse->toHTML($page_text, $parseBB = TRUE))
        , 'post_title'     => $e107db->escape($page_title)      //XXX bbcode allowed in titles ?
        , 'post_status'    => $post_status
        , 'post_type'      => 'page'
        , 'comment_status' => $comment_status
        , 'ping_status'    => 'closed'               //XXX is there a global variable in wordpress or e107 to guess this ?
        //, 'post_modified'  =>     //XXX Auto or now() ?
        //, 'post_modified_gmt'  => //XXX Auto or now() ?
        , 'page_template'  => $page_template
        ));

      // Update page mapping
      $pages_mapping[$page_id] = (int) $ret_id;
    }

    // Return page mapping to let us pass it as global variable via options
    return $pages_mapping;
  }


  // Step 4: get e107 comments and save them as Wordpress comments
  function importComments()
  {
    // General Housekeeping
    $e107db = new wpdb( get_option('e107_db_user')
                      , get_option('e107_db_pass')
                      , get_option('e107_db_name')
                      , get_option('e107_db_host')
                      );
    set_magic_quotes_runtime(0);
    $prefix        = get_option('e107_db_prefix');
    $user_mapping  = get_option('e107_user_mapping');
    $news_mapping  = get_option('e107_news_mapping');
    $pages_mapping = get_option('e107_pages_mapping');

    // Convert news to post
    echo '<p>'.__('Importing e107 comments as Wordpress comments...').'</p>';

    // Prepare the SQL request
    $e107_commentsTable  = $prefix."comments";
    $sql  = "SELECT * FROM `".$e107_commentsTable."`";

    // Get News list
    $comment_list = $e107db->get_results($sql, ARRAY_A);

    // This array contain the mapping between old e107 comments and newly inserted wordpress comments
    $comments_mapping = array();

    foreach($comment_list as $comment)
    {
      $count++;
      extract($comment);
      // Cast to int
      $comment_id      = (int) $comment_id;
      $comment_item_id = (int) $comment_item_id;

      // Get the post_id from $news_mapping or $pages_mapping depending of the comment type
      if ($comment_type == 'page')
      {
        $post_id = $pages_mapping[$comment_item_id];
      } else {
        $post_id = $news_mapping[$comment_item_id];
      }

      // Don't import comments not linked with news
      $post_status = get_post_status($post_id);
      if ($post_status != False)
      {
        // Create a new html parser
        if (!is_object($this->e_parse)) {
          require_once(e_HANDLER.'e_parse_class.php');
          $this->e_parse = new e_parse;
        }

        // Get author details from Wordpress if registered.
        $author_name  = substr($comment_author, strpos($comment_author, '.') + 1);
        $author_id    = (int) strrev(substr(strrev($comment_author), strpos(strrev($comment_author), '.') + 1));
        $author_ip    = $this->ip_hex2dec($comment_ip);
        $author_email = $comment_author_email;
        unset($author_url);

        //echo '$comment_author='.$comment_author.'<br/>';
        //echo '$author_id='.$author_id.'<br/>';
        //echo '$author_name='.$author_name.'<br/>';
        //echo '$user_mapping='.print_r($user_mapping).'<br/>';

        // Registered user
        if (array_key_exists($author_id, $user_mapping)) {
          $author_id = $user_mapping[$author_id];
          $author = new WP_User($author_id);
          //echo '$author->data='.print_r($author->data).'<br/>';
          $author_name  = $author->display_name;
          $author_email = $author->user_email;
          $author_url   = $author->user_url;
        // Unregistered user
        } else {
          unset($author_id);
          // Sometimes $author_name is of given as email address. In this case, try to guess the user name.
          if ($author_email == '' and $this->isValidInetAddress($author_name, $strict=True))
          {
            $author_email = $author_name;
            $author_name = substr($author_name, 0, strpos($author_name, '@'));
          }
        }

        // Save e107 comment in Wordpress database
        $ret_id = wp_insert_comment(array(
            'comment_post_ID'      => $post_id
          , 'comment_author'       => $e107db->escape($author_name)
          , 'comment_author_email' => $e107db->escape($author_email)
          , 'comment_author_url'   => $e107db->escape($author_url)
          , 'comment_author_IP'    => $author_ip
          , 'comment_date'         => $this->mysql_date($comment_datestamp)  //XXX ask or get the time offset ?
          , 'comment_date_gmt'     => $this->mysql_date($comment_datestamp)  //XXX ask or get the time offset ?
          , 'comment_content'      => $e107db->escape($this->e_parse->toHTML($comment_comment,  $parseBB = TRUE))
          , 'comment_approved'     => ! (int) $comment_blocked
          , 'user_id'              => $author_id
          , 'user_ID'              => $author_id
          , 'filtered'             => true
          ));

        //echo '<hr/>';
        // Update post mapping
        $comments_mapping[$comment_id] = (int) $ret_id;
      }
    } /* else {
      // TODO: Report the incident if the post was not found
    }*/

    // Return news mapping to let us pass it as global variable via options
    return $comments_mapping;
  }



  // Step 0 screen
  function greet() {
    echo '<h2>'.__('Import e107: Introduction').'</h2>';
    echo '<p>'.__('This tool allows you to extract the most important content and data from e107 database and import them into your Wordpress blog.').'</p>';
    echo '<p>'.__('Features:').'<ul><li>';
    echo __('Import news and categories').'</li><li>';
    echo __('Handle extended part of news nicely').'</li><li>';
    echo __('Import custom pages').'</li><li>';
    echo __('Import users and their profile').'</li><li>';
    echo __('Import comments (both from news and custom pages)').'</li><li>';
    echo __('Support bbcode');
    echo '</li></ul></p>';
    echo '<p>'.'<strong>'.__('Warning').'</strong>: '.__("This plugin assume that your e107 site is fully encoded in UTF-8. If it's not the case, please look at <a href='http://wiki.e107.org/?title=Upgrading_database_content_to_UTF8'>Upgrading database content to UTF8</a> article on e107 wiki.").'</p>';
    echo '<p>'.__('This plugin was tested with <a href="http://e107.org/news.php?item.803.1">e107 v0.7.8</a> and <a href="http://wordpress.org/development/2007/03/upgrade-212/">Wordpress v2.1.2</a>. If you have lower versions, please upgrade e107 and Wordpress before using this tool.').'</p>';

    echo '<h2>'.__('e107 database connexion').'</h2>';
    echo '<p>'.__('Parameters below must match your actual e107 MySQL database connexion settings. Default values are taken from your current Wordpress configuration, please update if e107 is located in another database:').'</p>';
    echo '<form action="admin.php?import=e107&amp;step=1" method="post">';
    echo '<table class="optiontable">';
    printf( '<tr valign="top"><th scope="row">%s</th><td><input type="text" name="dbhost" id="dbhost" value="%s" size="40"/></td></tr>'
          , __('e107 Database Host:')
          , DB_HOST
          );
    printf( '<tr valign="top"><th scope="row">%s</th><td><input type="text" name="dbuser" id="dbuser" value="%s" size="40"/></td></tr>'
          , __('e107 Database User:')
          , DB_USER
          );
    printf( '<tr valign="top"><th scope="row">%s</th><td><input type="password" name="dbpass" id="dbpass" value="%s" size="40"/></td></tr>'
          , __('e107 Database Password:')
          , DB_PASSWORD
          );
    printf( '<tr valign="top"><th scope="row">%s</th><td><input type="text" name="dbname" id="dbname" value="%s" size="40"/></td></tr>'
          , __('e107 Database Name:')
          , DB_NAME
          );
    printf( '<tr valign="top"><th scope="row">%s</th><td><input type="text" name="dbprefix" value="e107_" size="40"/></td></tr>'
          , __('e107 Table prefix:')
          );
    echo '</table>';

    echo '<h2>'.__('e107 Users: Import options').'</h2>';
    printf(__('<p>All users will be imported in the Wordpress database with the <code>%s</code> role. If you want to change this, change default role in the <a href="'.get_option('siteurl').'/wp-admin/options-general.php"><code>Options</code> &gt; <code>General</code> panel</a>.').'</p>'
          , __(get_settings('default_role'))
          );
    echo __("<p><strong>Warning 1</strong>: Wordpress don't like UTF-8 char (like accents, etc) in login. So, when the user will be added, all non-ascii chars will be deleted from the login string.</p>");
    echo __("<p><strong>Warning 2</strong>: because e107 store users' password as encrypted, it's impossible to decrypt them. So, all users' password will be resetted in Wordpress. To make the transition smoother, we can send a mail to each user:");
    echo '<ul><li>';
    echo __('<label><input name="mail_user" type="radio" value="no_mail" checked="checked"/> No: reset each password but don\'t send a mail to users.</label>');
    echo '</li><li>';
    echo __('<label><input name="mail_user" type="radio" value="send_mail"/> Yes: reset each password and send each user a mail to inform them.</label>');
    echo '</li></ul>';
    echo '</p>';
    echo '<input type="submit" name="submit" value="'.__('Next step: Import e107 users').'" />';
    echo '</form>';
  }


  // Step 1 screen
  function import_users()
  {
    // Import e107 users
    $user_mapping = $this->importUsers();
    add_option('e107_user_mapping', $user_mapping);
    echo '<h2>'.__('e107 Import: users').'</h2>';
    echo '<p>'.__('Database connexion successful !').'</p>';
    echo '<p><strong>'.sizeof($user_mapping).'</strong>'.__(' users imported from e107 to Wordpress.').'</p>';

    echo '<h2>'.__('e107 News: Import options').'</h2>';
    echo '<p>'.__('The next step consist of importing all e107 news (and categories) as Wordpress posts.').'</p>';
    echo '<form action="admin.php?import=e107&amp;step=2" method="post">';

    echo '<p><strong>'.__('Warning').':</strong> '.__("Wordpress doesn't support extended news. This tool can aggregate the extended part and the body of e107 news in the main body of Wordpress posts.").'</p>';

    echo '<p>Do you want to import extended part of e107 news ?';
    echo '<ul><li>';
    echo '<label><input name="extended_news" type="radio" value="ignore_extended" checked="checked"/> No: Ignore extended part of news, import the body only.</label>';
    echo '</li><li>';
    echo '<label><input name="extended_news" type="radio" value="import_all"/> Yes: Import both extended part and body.</label>';
    echo '</li><li>';
    echo '<label><input name="extended_news" type="radio" value="ignore_body"/> Ignore body and import extended part only.</label>';
    echo '</li></ul></p>';

    printf('<input type="submit" name="submit" value="%s" />', __('Next step: Import e107 news and categories'));
    echo '</form>';
  }


  // Step 2 screen
  function import_posts()
  {
    echo '<h2>'.__('e107 Import: news').'</h2>';
    // Import e107 news as posts
    $news_mapping = $this->e107news2posts();
    add_option('e107_news_mapping', $news_mapping);
    echo '<p><strong>'.sizeof($news_mapping).'</strong>'.__(' news imported from e107 to Wordpress.').'</p>';

    echo '<h2>'.__('e107 Custom Pages: Import options').'</h2>';
    echo '<p>'.__('The next step consist of importing all e107 custom pages as Wordpress pages.').'</p>';

    echo '<form action="admin.php?import=e107&amp;step=3" method="post">';

    echo '<p><strong>'.__('Warning 1').':</strong> '.__("Wordpress doesn't display comments on pages by default. However, this tool can do this by patching the default Wordpress theme (Kubrick).").'</p>';

    echo '<p>Do you want to patch Kubrick ?';
    echo '<ul><li>';
    echo '<label><input name="patch_default_theme" type="radio" value="no_patch_theme" checked="checked"/> No, I don\'t want to patch the default theme</label>';
    echo '</li><li>';
    echo '<label><input name="patch_default_theme" type="radio" value="patch_theme"/> Yes, I want to let this import tool patch the Kubrick theme and set it as the current one.</label>';
    echo '</li></ul></p>';

    printf('<input type="submit" name="submit" value="%s" />', __('Next step: Import e107 custom pages'));
    echo '</form>';
  }


  // Step 3 screen
  function import_pages()
  {
    echo '<h2>'.__('e107 Import: custom pages').'</h2>';
    // Import e107 static pages
    $pages_mapping = $this->importPages();
    add_option('e107_pages_mapping', $pages_mapping);
    echo '<p><strong>'.sizeof($pages_mapping).'</strong>'.__(' custom pages imported from e107 to Wordpress.').'</p>';

    echo '<h2>'.__('e107 comments import').'</h2>';
    echo '<p>'.__('The next step consist of importing all e107 comments to Wordpress.').'</p>';
    echo '<form action="admin.php?import=e107&amp;step=4" method="post">';
    printf('<input type="submit" name="submit" value="%s" />', __('Next step: Import e107 comments'));
    echo '</form>';
  }


  // Step 4 screen
  function import_comments()
  {
    echo '<h2>'.__('e107 Import: comments').'</h2>';
    // Import e107 news comments
    $comments_mapping = $this->importComments();
    add_option('e107_comments_mapping', $comments_mapping);
    echo '<p><strong>'.sizeof($comments_mapping).'</strong>'.__(' comments imported from e107 to Wordpress.').'</p>';

    $this->cleanup_e107import();

    echo '<h2>'.__('e107 Import: finished !').'</h2>';
    printf('<p><a href="%s">Have fun !</a></p>', get_option('siteurl'));
  }


  function cleanup_e107import()
  {
    delete_option('e107_db_user');
    delete_option('e107_db_pass');
    delete_option('e107_db_name');
    delete_option('e107_db_host');
    delete_option('e107_db_prefix');
    delete_option('e107_mail_user');
    delete_option('e107_user_mapping');
    delete_option('e107_extended_news');
    delete_option('e107_news_mapping');
    delete_option('e107_pages_mapping');
    delete_option('e107_patch_theme');
    delete_option('e107_comments_mapping');
  }


  function dispatch()
  {
    if (empty ($_GET['step']))
      $step = 0;
    else
      $step = (int) $_GET['step'];
    $this->header();

    if ($step > 0)
    {
      if($_POST['dbuser'])
      {
        if(get_option('e107_db_user'))
          delete_option('e107_db_user');
        add_option('e107_db_user', $_POST['dbuser']);
      }
      if($_POST['dbpass'])
      {
        if(get_option('e107_db_pass'))
          delete_option('e107_db_pass');
        add_option('e107_db_pass', $_POST['dbpass']);
      }
      if($_POST['dbname'])
      {
        if(get_option('e107_db_name'))
          delete_option('e107_db_name');
        add_option('e107_db_name', $_POST['dbname']);
      }
      if($_POST['dbhost'])
      {
        if(get_option('e107_db_host'))
          delete_option('e107_db_host');
        add_option('e107_db_host', $_POST['dbhost']);
      }
      if($_POST['dbprefix'])
      {
        if(get_option('e107_db_prefix'))
          delete_option('e107_db_prefix');
        add_option('e107_db_prefix', $_POST['dbprefix']);
      }
      // Init e107 context
      $this->inite107context();
    }

    // Keep users options on step 1
    if ($step == 1)
    {
      if($_POST['mail_user'])
      {
        if(get_option('e107_mail_user'))
          delete_option('e107_mail_user');
        add_option('e107_mail_user', $_POST['mail_user']);
      }
    }

    // Keep news options on step 2
    if ($step == 2)
    {
      if($_POST['extended_news'])
      {
        if(get_option('e107_extended_news'))
          delete_option('e107_extended_news');
        add_option('e107_extended_news', $_POST['extended_news']);
      }
    }

    // Keep static pages options on step 3
    if ($step == 3)
    {
      if($_POST['patch_default_theme'])
      {
        if(get_option('e107_patch_theme'))
          delete_option('e107_patch_theme');
        add_option('e107_patch_theme', $_POST['patch_default_theme']);
      }
    }

    // TODO: simplify UI: 1 screen for all options, 1 screen for the results look at http://wordpress.com/blog/2007/02/06/new-blogger-importer/ for inspiration
    switch ($step)
    {
      default:
      case 0 :
        $this->greet();
        break;
      case 1 :
        $this->import_users();
        break;
      case 2 :
        $this->import_posts();
        break;
      case 3 :
        $this->import_pages();
        break;
      case 4 :
        $this->import_comments();
        break;
    }

    $this->footer();
  }

  function e107_Import() {
    // This space intentionally left blank.
  }
}



// Add e107 importer in the list of default Wordpress import filter
$e107_import = new e107_Import();
register_importer('e107', 'e107', __('Import e107 news, categories, users, custom pages and comments to Wordpress'), array ($e107_import, 'dispatch'));

?>