<?php
/*
Plugin Name: e107 Importer
Plugin URI: https://github.com/kdeldycke/kev-code/tree/master/e107-importer
Description: Import posts and comments from an e107 CMS.
Author: coolkevman
Author URI: http://kevin.deldycke.com
Version: 1.0.dev
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
define("IMPORT_PATH"         , 'wp-admin/import/');
define("E107_INCLUDES_PATH"  , IMPORT_PATH . 'e107-includes/');
define("E107_REDIRECT_PLUGIN", 'e107-to-wordpress-redirect.php');


if ( class_exists( 'WP_Importer' ) ) {
class e107_Import extends WP_Importer {
  // Class wide variables
  var $e107_db;

  var $e107_db_host;
  var $e107_db_user;
  var $e107_db_pass;
  var $e107_db_name;
  var $e107_db_prefix;

  var $e107_mail_user;
  var $e107_extended_news;
  var $e107_patch_theme;
  var $e107_bbcode_parser;
  var $e107_import_images;

  var $user_mapping;
  var $news_mapping;
  var $category_mapping;
  var $page_mapping;
  var $comment_mapping;

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


  // Convert hexadecimal IP adresse string to decimal
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
  function isValidInetAddress($data, $strict = false) {
    $regex = $strict ? '/^([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})$/i' : '/^([*+!.&#$|\'\\%\/0-9a-z^_`{}=?~:-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})$/i';
    if (preg_match($regex, trim($data), $matches)) {
      return array($matches[1], $matches[2]);
    } else {
      return false;
    }
  }


  // Generic code to initialize the e107 context
  function inite107Context() {
    /* Some part of the code below is copy of (and/or inspired by) code from the e107 project, licensed
    ** under the GPL and (c) copyrighted to Steve Dunstan (see copyright headers).
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

    define("e_IMAGE", "/".$IMAGES_DIRECTORY); // XXX still used ???

    // Redifine some globals to match wordpress importer file hierarchy
    define("e_BASE"   , ABSPATH);
    define("e_PLUGIN" , e_BASE);
    define("e_FILE"   , e_BASE . IMPORT_PATH);
    define("e_HANDLER", e_BASE . E107_INCLUDES_PATH);

    // CHARSET is normally set in e107_languages/English/English.php file
    define("CHARSET", "utf-8");

    /*========== START of code inspired by class2.php file ==========
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
    |     $Source: /cvsroot/e107/e107_0.7/class2.php,v $
    |     $Revision: 1.322 $
    |     $Date: 2006/11/25 03:38:19 $
    |     $Author: mcfly_e107 $
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
    /*========== END of code inspired by class2.php file ==========*/

    // Load preferences if not already loaded
    if (!isset($this->e107_pref) || !is_array($this->e107_pref))
      $this->loadPreferences();

    // Override bbcode definition files configuration
    $this->e107_pref['bbcode_list'] = array();
    $this->e107_pref['bbcode_list'][E107_INCLUDES_PATH] = array();
    // This $core_bb array come from bbcode_handler.php
    $core_bb = array(
    'blockquote', 'img', 'i', 'u', 'center',
    '_br', 'color', 'size', 'code',
    'html', 'flash', 'link', 'email',
    'url', 'quote', 'left', 'right',
    'b', 'justify', 'file', 'stream',
    'textarea', 'list', 'php', 'time',
    'spoiler', 'hide'
    );
    foreach ($core_bb as $c)
      $this->e107_pref['bbcode_list'][E107_INCLUDES_PATH][$c] = 'dummy_u_class';

    unset($this->e107_pref['image_post']);

    // Don't transform smileys to <img>, Wordpress will do it automaticcaly
    $this->e107_pref['smiley_activate'] = False;

    // Turn-off profanity filter: if profanities must be hidden in content,
    //   it should be done by a dedicated Wordpress plug-in,
    //   not by a direct alteration of original content.
    // As suggested by Jon Freger ( http://kevin.deldycke.com/2006/11/wordpress-to-e107-v06-better-content-rendering-and-extended-news-support/#comment-2937 ), use  WebPurify WP Plugin ( http://www.webpurify.com/wp-plugin.php ).
    // TODO: show this suggestion in the UI if profanity_filter is activated on e107.
    $this->e107_pref['profanity_filter'] = False;

    // Set global SITEURL as it's used by replaceConstants() method
    $site_url = $this->e107_pref['siteurl'];
    // Normalize URL: it must end with a single slash
    define("SITEURL", $this->deleteTrailingChar($site_url, '/').'/');

    // Required to make default e107 methods aware of preferences
    global $pref;
    $pref = $this->e107_pref;
  }


  // wp_handle_upload_2() function below is a slightly modified original wp_handle_upload() function from Wordpress 2.1.3 (wp-admin/admin-functions.php).
  // Modifications:
  //   * is_uploaded_file() block is commented.
  //   * move_uploaded_file() function is replaced by rename().
  // TODO: try to find a solution to use the standard wp_handle_upload() method (maybe submit a patch to Wordpress ?)
  function wp_handle_upload_2( &$file, $overrides = false ) {
    global $wpdb;

    // The default error handler.
    if (! function_exists( 'wp_handle_upload_error' ) ) {
      function wp_handle_upload_error( &$file, $message ) {
        return array( 'error'=>$message );
      }
    }

    // You may define your own function and pass the name in $overrides['upload_error_handler']
    $upload_error_handler = 'wp_handle_upload_error';

    // $_POST['action'] must be set and its value must equal $overrides['action'] or this:
    $action = 'wp_handle_upload';

    // Courtesy of php.net, the strings that describe the error indicated in $_FILES[{form field}]['error'].
    $upload_error_strings = array( false,
      __( "The uploaded file exceeds the <code>upload_max_filesize</code> directive in <code>php.ini</code>." ),
      __( "The uploaded file exceeds the <em>MAX_FILE_SIZE</em> directive that was specified in the HTML form." ),
      __( "The uploaded file was only partially uploaded." ),
      __( "No file was uploaded." ),
      __( "Missing a temporary folder." ),
      __( "Failed to write file to disk." ));

    // All tests are on by default. Most can be turned off by $override[{test_name}] = false;
    $test_form = true;
    $test_size = true;

    // If you override this, you must provide $ext and $type!!!!
    $test_type = true;

    // Install user overrides. Did we mention that this voids your warranty?
    if ( is_array( $overrides ) )
      extract( $overrides, EXTR_OVERWRITE );

    // A correct form post will pass this test.
    if ( $test_form && (!isset( $_POST['action'] ) || ($_POST['action'] != $action ) ) )
      return $upload_error_handler( $file, __( 'Invalid form submission.' ));

    // A successful upload will pass this test. It makes no sense to override this one.
    if ( $file['error'] > 0 )
      return $upload_error_handler( $file, $upload_error_strings[$file['error']] );

    // A non-empty file will pass this test.
    if ( $test_size && !($file['size'] > 0 ) )
      return $upload_error_handler( $file, __( 'File is empty. Please upload something more substantial.' ));

  //   // A properly uploaded file will pass this test. There should be no reason to override this one.
  //   if (! @ is_uploaded_file( $file['tmp_name'] ) )
  //     return $upload_error_handler( $file, __( 'Specified file failed upload test.' ));

    // A correct MIME type will pass this test. Override $mimes or use the upload_mimes filter.
    if ( $test_type ) {
      $wp_filetype = wp_check_filetype( $file['name'], $mimes );

      extract( $wp_filetype );

      if ( !$type || !$ext )
        return $upload_error_handler( $file, __( 'File type does not meet security guidelines. Try another.' ));
    }

    // A writable uploads dir will pass this test. Again, there's no point overriding this one.
    if ( ! ( ( $uploads = wp_upload_dir() ) && false === $uploads['error'] ) )
      return $upload_error_handler( $file, $uploads['error'] );

    // Increment the file number until we have a unique file to save in $dir. Use $override['unique_filename_callback'] if supplied.
    if ( isset( $unique_filename_callback ) && function_exists( $unique_filename_callback ) ) {
      $filename = $unique_filename_callback( $uploads['path'], $file['name'] );
    } else {
      $number = '';
      $filename = str_replace( '#', '_', $file['name'] );
      $filename = str_replace( array( '\\', "'" ), '', $filename );
      if ( empty( $ext) )
        $ext = '';
      else
        $ext = ".$ext";
      while ( file_exists( $uploads['path'] . "/$filename" ) ) {
        if ( '' == "$number$ext" )
          $filename = $filename . ++$number . $ext;
        else
          $filename = str_replace( "$number$ext", ++$number . $ext, $filename );
      }
      $filename = str_replace( $ext, '', $filename );
      $filename = sanitize_title_with_dashes( $filename ) . $ext;
    }

    // Move the file to the uploads dir
    $new_file = $uploads['path'] . "/$filename";
  //   if ( false === @ move_uploaded_file( $file['tmp_name'], $new_file ) )
  //     wp_die( printf( __('The uploaded file could not be moved to %s.' ), $uploads['path'] ));
    if ( false === @ rename($file['tmp_name'], $new_file))
      wp_die( printf( __('The uploaded file could not be moved to %s.' ), $uploads['path'] ));

    // Set correct file permissions
    $stat = stat( dirname( $new_file ));
    $perms = $stat['mode'] & 0000666;
    @ chmod( $new_file, $perms );

    // Compute the URL
    $url = $uploads['url'] . "/$filename";

    $return = apply_filters( 'wp_handle_upload', array( 'file' => $new_file, 'url' => $url, 'type' => $type ) );

    return $return;
  }


  // This method search for images path in a post (or page) content,
  //   then it import the images from the remote location and
  //   finally update the HTML code accordingly.
  function importImagesFromPost($post_id, $allowed_domains=array()) {
    global $wpdb;
    // Get post content
    $post = get_post($post_id);
    $html_content = $post->post_content;

    // Locate all <img/> tags and import them into Wordpress
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

      // The fopen() function in wp_remote_fopen() don't like URLs with space chars not translated to html entities
      $img_url = str_replace(' ', '%20', html_entity_decode($img_url));

      // Upload remote image if exist
      $img_data = wp_remote_fopen($img_url);
      if (! $img_data) {
        printf( "<p><span style='color: #f00; font-weight: bold;'>Warning</span>: <a href='%s'>%s</a> image can't be imported (probably because it doesn't exist...). Please fix <a href='%s'>%s</a> content manually.</p>"
              , $img_url
              , $img_url
              , get_permalink($post_id)
              , $post->post_title
              );
        // Skip this image
        continue;
      }

      // Temporary save the image in the wordpress upload folder
      // XXX Bad solution: if the script fail "e170-import-tmp-*" files will not be erased...
      $upload_dir = wp_upload_dir(); // TODO: write a patch for this method to specify the year and the month
      $upload_dir = $upload_dir['path'];
      $tmp_file = tempnam($upload_dir, 'e170-import-tmp-');

      $f = fopen($tmp_file, 'wb');
      $img_size = strlen($img_data);
      if ($f) {
        fwrite($f, $img_data, $img_size);
        fclose($f);
      }

      // Upload file to wordpress database
      // Code inspired by wp-admin/upload-functions.php: wp_upload_tab_upload_action() function
      // XXX Maybe wp_upload_tab_upload_action() can be directly used ?
      $overrides = array('action'=>'upload');
      $_POST['action'] = 'upload';

      $img_path     = pathinfo($img_url);
      $img_basename = $img_path['basename'];
      $img_type     = wp_check_filetype($img_basename);
      $img_type     = $img_type['type'];

      $img_file_array = array( 'name'     => $img_basename
                             , 'type'     => $img_type
                             , 'tmp_name' => $tmp_file
                             , 'size'     => $img_size
                             );

      // XXX should be: $img_object = wp_handle_upload($img_file_array, $overrides);
      $img_object = $this->wp_handle_upload_2($img_file_array, $overrides);
      $new_img_url  = $img_object['url'];
      $new_img_file = $img_object['file'];

      // Link the image to the post
      $attachment = array( 'post_title'     => $img_basename
                         , 'post_author'    => $post->post_author
                         , 'post_date'      => $post->post_date
                         , 'post_date_gmt'  => $post->post_date_gmt
                         , 'post_parent'    => $post_id
                         , 'post_mime_type' => $img_object['type']
                         , 'guid'           => $new_img_url
                         );

      $img_id = wp_insert_attachment($attachment, $new_img_file, $post_id);
      wp_update_attachment_metadata($img_id, wp_generate_attachment_metadata($img_id, $new_img_file));

      // Update the post content to replace the previous image URL to its new local copy
      $new_tag = '<img src="'.$new_img_url.'"/>'; // XXX is this the standard wordpress image tag template ?
      $html_content = str_replace($img_tag, $new_tag, $html_content);
      wp_update_post(array(
          'ID'           => $post_id
        , 'post_content' => $wpdb->escape($html_content)
        ));
      // TODO: save image original path and its final permalink to not upload file twice
    }
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
    $e107_newsTable  = $this->e107_db_prefix."news";
    $sql = "SELECT * FROM `".$e107_newsTable."`";
    // Return news list
    return $this->queryE107DB($sql);
  }


  function getE107PageList() {
    // Prepare the SQL request
    $e107_pagesTable  = $this->e107_db_prefix."page";
    $sql = "SELECT * FROM `".$e107_pagesTable."`";
    // Return page list
    return $this->queryE107DB($sql);
  }


  function getE107CommentList() {
    // Prepare the SQL request
    $e107_commentsTable  = $this->e107_db_prefix."comments";
    $sql  = "SELECT * FROM `".$e107_commentsTable."`";
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


  // Install the redirections plugin
  function installRedirectionPlugin() {
    global $wpdb;
    $source = ABSPATH . E107_INCLUDES_PATH . E107_REDIRECT_PLUGIN;
    $dest   = ABSPATH . PLUGINDIR . '/' . E107_REDIRECT_PLUGIN;

    // Get current active plugins
    $current = get_option('active_plugins');

    // Remove previous plugin if exist
    if (is_file($dest))
      // Desactivate the plugin before removing it
      if (in_array(E107_REDIRECT_PLUGIN, $current)) {
        array_splice($current, array_search(E107_REDIRECT_PLUGIN, $current), 1 ); // Array-fu!
        update_option('active_plugins', $current);
        do_action('deactivate_' . E107_REDIRECT_PLUGIN);
      }
      // No need to unlink() here: copy() will overwrite the $dest file

    // Copy the plugin to plugin directory
    copy($source, $dest);
  }


  // Update the redirection plugin with data related to this import
  // Mainly used to add the mapping of old e107 content to their Wordpress equivalent
  function updateRedirectionPlugin($keyword, $data) {
    global $wpdb;
    // Get plugin file path
    $real_file = get_real_file_to_edit(PLUGINDIR . '/' . E107_REDIRECT_PLUGIN);

    // Extract plugin content
    $f = fopen($real_file, 'r+');
    $content = fread($f, filesize($real_file));
    //fclose($f);

    // Create the tag
    $tag = '// XXX '.strtoupper(str_replace('_', ' ', trim($keyword))).' XXX';

    // Generate the PHP code
    $data_code = '$'.$keyword.' = '.var_export($data, true).';';

    // Replace the tag by the php code in the plugin
    $updated_content = str_replace($tag, $data_code, $content);

    // Save modifications in the plugin
    rewind($f);
    //$f = fopen($real_file, 'w+');
    fwrite($f, $updated_content);
    fclose($f);
  }


  // Validate and activate the redirection plugin
  function activateRedirectionPlugin() {
    global $wpdb;
    // Get current active plugins
    $current = get_option('active_plugins');

    // Validate the plugin
    if (validate_file(E107_REDIRECT_PLUGIN))
      wp_die(__('Invalid plugin.'));

    // Activate the plugin
    $current[] = E107_REDIRECT_PLUGIN;
    update_option('active_plugins', $current);
    do_action('activate_' . E107_REDIRECT_PLUGIN);
  }


  // Import e107 users to Wordpress
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

      // $user_image // XXX how to handle this ? export to gravatar ???

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
        $new_password = substr(md5(uniqid(microtime())), 0, 6);
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


  // Get e107 news and save them as Wordpress posts
  function importNewsAndCategories() {
    // Phase 1: import categories

    // Get category list
    $category_list = $this->getE107CategoryList();

    global $wpdb;

    // This array contain the mapping between old e107 news categories and new wordpress categories
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

      // Save e107 news in Wordpress database
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


  // Convert static pages to Wordpress pages
  function importPages() {
    // Get static pages list
    $page_list = $this->getE107PageList();

    global $wpdb;

    // This array contain the mapping between old e107 static pages and newly inserted wordpress pages
    $this->page_mapping = array();

    foreach ($page_list as $page) {
      $count++;
      extract($page);
      $page_id = (int) $page_id;

      if ($this->e107_patch_theme == 'patch_theme') {
        // Auto-patch kubrick Page Template file to show comments by default
        // Switch to default kubrick theme
        $ct = current_theme_info();
        if ($ct->name != 'default') {
          update_option('template', 'default');
          update_option('stylesheet', 'default');
        }
        // Patch sent to Wordpress: http://trac.wordpress.org/ticket/3753 -> Wait and see...
        $real_file = get_real_file_to_edit("wp-content/themes/default/page.php");
        $f = fopen($real_file, 'r');
        $content = fread($f, filesize($real_file));
        fclose($f);
        // Check if patch already applied
        if (!strpos($content, "comments_template()")) {
          // Look at the last "</div>" tag
          require_once(ABSPATH . E107_INCLUDES_PATH . 'strripos.php');
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

      // Set the status of the post to 'publish' or 'private'.
      // There is no 'draft' in e107.
      $post_status = 'publish';
      if ($page_class != '0')
        $post_status = 'private';

      // Update author role if necessary;
      // If the user has the minimum role (aka subscriber) he is not able to post
      //   news. In this case, we raise is role by 1 level (aka contributor).
      $author_id = $this->user_mapping[$page_author];
      $author = new WP_User($author_id);
      if (! $author->has_cap('edit_posts'))
        $author->set_role('contributor');
      // If user is the author of a private page give him the 'editor' role else he can't view private pages
      if (($post_status == 'private') and (!$author->has_cap('read_private_pages')))
        $author->set_role('editor');

      // Define comment status
//      $page_template;
      if (!$page_comment_flag) {
        $comment_status = 'closed';
//        unset($page_template);
      } else {
        $comment_status = 'open';
      }

      // Save e107 static page in Wordpress database
      $ret_id = wp_insert_post(array(
          'post_author'    => $author_id                           // use the new wordpress user ID
        , 'post_date'      => $this->mysql_date($page_datestamp)
        , 'post_date_gmt'  => $this->mysql_date($page_datestamp)
        , 'post_content'   => $wpdb->escape($page_text)
        , 'post_title'     => $wpdb->escape($page_title)
        , 'post_status'    => $post_status
        , 'post_type'      => 'page'
        , 'comment_status' => $comment_status
        , 'ping_status'    => 'closed'               // XXX is there a global variable in wordpress or e107 to guess this ?
//        , 'page_template'  => $page_template
        ));

      // Update page mapping
      $this->page_mapping[$page_id] = (int) $ret_id;
    }
  }


  // Import e107 comments as Wordpress comments
  function importComments() {
    // Get News list
    $comment_list = $this->getE107CommentList();

    global $wpdb;

    // This array contain the mapping between old e107 comments and newly inserted wordpress comments
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

        // Get author details from Wordpress if registered.
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
          if ($author_email == '' and $this->isValidInetAddress($author_name, $strict=True)) {
            $author_email = $author_name;
            $author_name = substr($author_name, 0, strpos($author_name, '@'));
          }
        }

        // Save e107 comment in Wordpress database
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


  // This method parse content of news, pages and comments to replace all {e_SOMTHING} e107 constants
  function replaceConstants() {
    global $wpdb;
    // Get the list of WP news and page IDs
    $news_and_pages_ids = array_merge(array_values($this->news_mapping), array_values($this->page_mapping));
    // Parse BBcode in each news and page
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
    // Parse BBcode in each news and page
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
    // TODO: Load mappings from the e107-to-wordpress-redirect.php plugin
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


  // Transform BBcode to html using original e107 parser
  // TODO: parse bbcode in post and page title !
  // TODO: factorize with replaceConstants() -> less code & less database IO
  function parseBBcodeWithE107() {
    global $wpdb;
    // Get the list of WP news and page IDs
    $news_and_pages_ids = array_merge(array_values($this->news_mapping), array_values($this->page_mapping));
    // Parse BBcode in each news and page
    foreach ($news_and_pages_ids as $post_id) {
      // Get post content
      $post = get_post($post_id);
      $content = $post->post_content;
      // Apply BBcode transformation
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
    // Parse BBcode in each news and page
    foreach ($comments_ids as $comment_id) {
      // Get comment content
      $comment = get_comment($comment_id);
      $content = $comment->comment_content;
      // Apply BBcode transformation
      $new_content = $this->e107_parser->toHTML($content, $parseBB = TRUE);
      // Update comment content if necessary
      if ($new_content != $content)
        wp_update_comment(array(
            'comment_ID'      => $comment_id
          , 'comment_content' => $wpdb->escape($new_content)
          ));
    }
  }


  // Transform BBcode to html using Kevin's custom parser
  function parseBBcodeWithCustomParser() {
    /*
    // cleanup html and semantics enhancements

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


  // This method import all images embedded in news and pages to WP
  function importImages($local_only = false) {
    // Build the list of authorized domains from which we are allowed to import images
    $allowed_domains = array();
    if ($local_only == true)
      $allowed_domains[] = $this->e107_pref['siteurl'];

    // Get the list of WP news and page IDs
    $news_and_pages_ids = array_merge(array_values($this->news_mapping), array_values($this->page_mapping));
    foreach ($news_and_pages_ids as $post_id)
      $this->importImagesFromPost($post_id, $allowed_domains);
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


  function printWelcomeScreen(){
    echo '<div class="wrap">';

    echo '<h2>'.__('Import e107: Introduction').'</h2>';
    echo '<p>'.__('This tool allows you to extract the most important content and data from a e107 database and import them into your Wordpress blog.').'</p>';

    echo '<p>'.__('Features:').'<ul><li>';
    echo __('Import news and categories,').'</li><li>';
    echo __('Take care of news extended part,').'</li><li>';
    echo __('Import custom pages,').'</li><li>';
    echo __('Take care of page visibility (private / public),').'</li><li>';
    echo __('Import comments (both from news and custom pages),').'</li><li>';
    echo __('Import images from news and pages,').'</li><li>';
    echo __('Let you choose which kind of images you want to upload to Wordpress (external or not),').'</li><li>';
    echo __('Import preferences (like site name, description, ...),').'</li><li>';
    echo __('Convert embedded bbcode to plain HTML,').'</li><li>';
    echo __('Import users and their profile (or try to update the profile if user already exist),').'</li><li>';
    echo __('Try to map users to appropriate roles,').'</li><li>';
    echo __('Send mails to users to inform them about their new credentials,').'</li><li>';
    echo __('Redirect old e107 URLs to new permalinks via an integrated plugin (for SEO).');
    echo '</li></ul></p>';

    echo '<p>'.'<strong>'.__('Warning').'</strong>: '.__("Your e107 site <u>must</u> be fully encoded in UTF-8. If it's not the case, please look at <a href='http://wiki.e107.org/?title=Upgrading_database_content_to_UTF8'>Upgrading database content to UTF-8</a> article on e107 wiki.").'</p>';

    echo '<p>'.__('This tool was tested with <a href="http://e107.org/news.php?item.824">e107 v0.7.11</a> and <a href="http://wordpress.org/development/2007/12/wordpress-232/">Wordpress v2.3.2</a>. If you have older versions, please upgrade.').'</p>';

    // TODO: use AJAX to validate the form ?

    echo '<h2>'.__('e107 database connexion').'</h2>';

    echo '<p>'.__('Parameters below must match your actual e107 MySQL database connexion settings.').'</p>';
    echo '<form action="admin.php?import=e107&amp;action=import" method="post">';
    wp_nonce_field('import-e107');
    echo '<table class="optiontable">';
    printf( '<tr valign="top"><th scope="row">%s</th><td><input type="text" name="e107_db_host" id="e107_db_host" value="%s" size="40"/></td></tr>'
          , __('e107 Database Host:')
          , DB_HOST
          );
    printf( '<tr valign="top"><th scope="row">%s</th><td><input type="text" name="e107_db_user" id="e107_db_user" value="%s" size="40"/></td></tr>'
          , __('e107 Database User:')
          , DB_USER
          );
    printf( '<tr valign="top"><th scope="row">%s</th><td><input type="password" name="e107_db_pass" id="e107_db_pass" value="" size="40"/></td></tr>'
          , __('e107 Database Password:')
          );
    printf( '<tr valign="top"><th scope="row">%s</th><td><input type="text" name="e107_db_name" id="e107_db_name" value="%s" size="40"/></td></tr>'
          , __('e107 Database Name:')
          , DB_NAME
          );
    printf( '<tr valign="top"><th scope="row">%s</th><td><input type="text" name="e107_db_prefix" id="e107_db_prefix" value="e107_" size="40"/></td></tr>'
          , __('e107 Table prefix:')
          );
    echo '</table>';

    echo '<h2>'.__('Import options').'</h2>';

    echo '<fieldset class="options"><legend>'.__('Users').'</legend>';
    printf(__('<p>All users will be imported in the Wordpress database with the <code>%s</code> role. You can change the default role in the <a href="'.get_option('siteurl').'/wp-admin/options-general.php"><code>Options</code> &gt; <code>General</code> panel</a>. If a user is the author of at least one post or static page, its level will be raised to <code>contributor</code>.').'</p>'
          , __(get_settings('default_role'))
          );
    echo __("<p><strong>Warning 1</strong>: e107 users' password are encrypted. All passwords will be resetted.</p>");
    echo __("<p>Do you want to inform each user of their new password ?");
    echo '<ul><li>';
    echo __('<label><input name="e107_mail_user" type="radio" value="send_mail"/> Yes: reset each password and send each user a mail to inform them.</label>');
    echo '</li><li>';
    echo __('<label><input name="e107_mail_user" type="radio" value="no_mail" checked="checked"/> No: reset each password but don\'t send a mail to users.</label>');
    echo '</li></ul>';
    echo '</p>';
    echo __("<p><strong>Warning 2</strong>: Unlike e107, Wordpress don't accept strange char (like accents, etc) in login. When a user will be added to WP, all non-ascii chars will be deleted from the login string.</p>");
    echo '</fieldset>';

    echo '<fieldset class="options"><legend>'.__('News Extends').'</legend>';
    echo '<p>Wordpress doesn\'t support extended news.</p>';
    echo '<p>Do you want to import the extended part of all e107 news ?';
    echo '<ul><li>';
    echo '<label><input name="e107_extended_news" type="radio" value="import_all"/> Yes: import both extended part and body and merge them.</label>';
    echo '</li><li>';
    echo '<label><input name="e107_extended_news" type="radio" value="ignore_body"/> Yes, but: ignore body and import extended part only.</label>';
    echo '</li><li>';
    echo '<label><input name="e107_extended_news" type="radio" value="ignore_extended" checked="checked"/> No: ignore extended part of news, import the body only.</label>';
    echo '</li></ul></p>';
    echo '</fieldset>';

    echo '<fieldset class="options"><legend>'.__('Comments on Custom Pages').'</legend>';
    echo '<p>'.__("Wordpress <a href='http://trac.wordpress.org/ticket/3753'>doesn't display comments on pages</a> by default. However, this tool can fix this by patching the default Wordpress theme (Kubrick).").'</p>';
    echo '<p>Do you want to patch Kubrick ?';
    echo '<ul><li>';
    echo '<label><input name="e107_patch_theme" type="radio" value="patch_theme"/> Yes: I want to let this import tool patch the Kubrick theme and set it as the current one.</label>';
    echo '</li><li>';
    echo '<label><input name="e107_patch_theme" type="radio" value="no_patch_theme" checked="checked"/> No: I don\'t want to patch the default theme</label>';
    echo '</li></ul></p>';
    echo '</fieldset>';

    echo '<fieldset class="options"><legend>'.__('BBcode').'</legend>';
    echo '<p>Which kind of <a href="http://en.wikipedia.org/wiki/Bbcode">bbcode</a> parser you want to use ?';
    echo '<ul><li>';
    echo '<label><input name="e107_bbcode_parser" type="radio" value="original" checked="checked"/> e107 parser (content will be rendered exactly as they appear in e107).</label>';
    echo '</li><li>';
    echo '<label><input name="e107_bbcode_parser" type="radio" value="semantic"/> WordPress-like (enhance semantics and output html code very similar to what WP produce by default).</label>';
    echo '</li><li>';
    echo '<label><input name="e107_bbcode_parser" type="radio" value="none"/> Do not translate bbcode to html and let them appear as is.</label>';
    echo '</li></ul></p>';
    echo '</fieldset>';

    echo '<fieldset class="options"><legend>'.__('Images upload').'</legend>';
    echo '<p>This tool can find images URLs embedded in news and pages and upload them to this blog autommaticcaly.</p>';
    echo '<p>Do you want to upload image files ?';
    echo '<ul><li>';
    echo '<label><input name="e107_import_images" type="radio" value="upload_all"/> Yes: upload all images, even those located on external sites.</label>';
    echo '</li><li>';
    echo '<label><input name="e107_import_images" type="radio" value="site_upload" checked="checked"/> Yes, but: upload files from the e107 site only, not external images.</label>';
    echo '</li><li>';
    echo '<label><input name="e107_import_images" type="radio" value="no_upload"/> No: do not upload image files to Wordpress.</label>';
    echo '</li></ul></p>';
    echo '</fieldset>';

    echo '<input type="submit" name="submit" value="'.__('Import e107 to Wordpress').' &raquo;" />';
    echo '</form>';
    echo '</div>';
  }


  // Main import function
  function import() {
    // Collect parameters and options from the welcome screen
    $e107_option_names = array( 'e107_db_host', 'e107_db_user', 'e107_db_pass', 'e107_db_name', 'e107_db_prefix'
                              , 'e107_mail_user'
                              , 'e107_extended_news'
                              , 'e107_patch_theme'
                              , 'e107_bbcode_parser'
                              , 'e107_import_images'
                              );

    // Register each option as class global variables
    foreach ($e107_option_names as $o)
      if ($_POST[$o])
        $this->$o = $_POST[$o];

    // TODO: use AJAX to display a progress bar http://wordpress.com/blog/2007/02/06/new-blogger-importer/
    echo '<div class="wrap">';

    echo '<h2>'.__('Import e107: Report').'</h2>';

    echo '<h3>'.__('Connect to e107 database').'</h3>';
    $this->connectToE107DB();
    echo '<p>'.__('Connected.').'</p>';

    echo '<h3>'.__('Load e107 preferences').'</h3>';
    $this->loadPreferences();
    echo '<p>'.__('Preferences loaded.').'</p>';

    echo '<h3>'.__('Import preferences').'</h3>';
    $this->importPreferences();
    echo '<p>'.__('All e107 preferences imported.').'</p>';

    echo '<h3>'.__('Install the redirection plugin').'</h3>';
    $this->installRedirectionPlugin();
    echo '<p>'.__('Plugin installed.').'</p>';

    echo '<h3>'.__('Import users').'</h3>';
    $this->importUsers();
    echo '<p><strong>'.sizeof($this->user_mapping).'</strong>'.__(' users imported.').'</p>';

    echo '<h3>'.__('Import news, images and categories').'</h3>';
    $this->importNewsAndCategories();
    echo '<p><strong>'.sizeof($this->news_mapping).'</strong>'.__(' news imported.').'</p>';
    echo '<p><strong>'.sizeof($this->category_mapping).'</strong>'.__(' categories imported.').'</p>';
    // TODO: echo '<p><strong>'.sizeof($images).'</strong>'.__(' images uploaded.').'</p>';

    echo '<h3>'.__('Update redirection plugin').'</h3>';
    $this->updateRedirectionPlugin('news_mapping', $this->news_mapping);
    echo '<p>'.__('Old news URLs are now redirected to permalinks.').'</p>';

    echo '<h3>'.__('Import custom pages').'</h3>';
    $this->importPages();
    echo '<p><strong>'.sizeof($this->page_mapping).'</strong>'.__(' custom pages imported.').'</p>';
    // TODO: echo '<p><strong>'.sizeof($images).'</strong>'.__(' images uploaded.').'</p>';

    echo '<h3>'.__('Update redirection plugin').'</h3>';
    $this->updateRedirectionPlugin('page_mapping', $this->page_mapping);
    echo '<p>'.__('Old static pages URLs are now redirected to permalinks.').'</p>';

    echo '<h3>'.__('Import comments').'</h3>';
    $this->importComments();
    echo '<p><strong>'.sizeof($this->comment_mapping).'</strong>'.__(' comments imported.').'</p>';

    $this->inite107Context(); // e107 context is required by replaceConstants() and some othe method called below

    echo '<h3>'.__('Replace e107 constants').'</h3>';
    $this->replaceConstants();
    echo '<p>'.__('All e107 constants replaced in content.').'</p>';

    echo '<h3>'.__('Replace old URLs by permalinks').'</h3>';
    $this->replaceWithPermalinks();
    echo '<p>'.__('All migrated content use permalinks now.').'</p>';

    echo '<h3>'.__('Parse BBcode').'</h3>';
    if ($this->e107_bbcode_parser == 'semantic') {
      $this->parseBBcodeWithCustomParser();
      echo '<p>'.__("BBcode converted to pure html using kevin's custom parser.").'</p>';
    } elseif ($this->e107_bbcode_parser == 'original') {
      $this->parseBBcodeWithE107();
      echo '<p>'.__('BBcode converted to pure html using original e107 parser.').'</p>';
    } else {
      echo '<p>'.__('BBcode tags left as-is.').'</p>';
    }

    echo '<h3>'.__('Upload images').'</h3>';
    if ($this->e107_import_images == 'upload_all') {
      $this->importImages();
      echo '<p>'.__('All image embedded in news and pages uploaded to Wordpress.').'</p>';
    } elseif ($this->e107_import_images == 'site_upload') {
      $this->importImages(true);
      printf('<p>'.__('All image files from %s domain and which are used in news and pages were uploaded to Wordpress.').'</p>', '<a href="'.$this->e107_pref['siteurl'].'">'.$this->e107_pref['siteurl'].'</a>');
    } else {
      echo '<p>'.__('Image upload skipped.').'</p>';
    }

    echo '<h3>'.__('Activate the redirection plugin').'</h3>';
    $this->activateRedirectionPlugin();
    echo '<p>'.__('Plugin active !').'</p>';

    echo '<h2>'.__('Import e107: Finished !').'</h2>';
    printf('<p><a href="%s">'.__('Have fun !').'</a></p>', get_option('siteurl'));

    echo '</div>';
  }
}

}

// Add e107 importer in the list of default Wordpress import filter
$e107_import = new e107_Import();

// Show all database errors
global $wpdb;
$wpdb->show_errors();

register_importer('e107', __('e107'), __("Import e107 news, categories, users, custom pages, comments, images and preferences to Wordpress. Also take care of URL redirections, but don't make coffee (yet <img src='".get_option('siteurl')."/wp-includes/images/smilies/icon_wink.gif' alt=';)' class='wp-smiley'/>)."), array ($e107_import, 'start'));

} // class_exists( 'WP_Importer' )

?>
