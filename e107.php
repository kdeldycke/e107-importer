 <?php
/*
+---------------------------------------------------------------+
|   Wordpress filter to import e107 website.
|
|   Version: 0.3
|   Date: 10 sep 2006
|
|   For todo-list and changelog:
|     * http://kev.coolcavemen.com/2006/09/e107-to-wordpress-importer-v02-with-bbcode-support/
|     * http://kev.coolcavemen.com/2006/08/e107-to-wordpress-importer-alpha-version/
|
|   (c) Kevin Deldycke 2006
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
    echo '<h2>'.__('Import e107').'</h2>';
    echo '<p>'.__('Steps may take a few minutes depending on the size of your database. Please be patient.').'</p>';
  }


  function footer() {
    echo '</div>';
  }


  // Convert unix timestamp to mysql datetimestamp
  function mysql_date($unix_time)
  {
    return date("Y-m-d H:i:s", $unix_time);
  }


  // Step 1: import e107 users
  // TODO: support subtile role migration (see: http://codex.wordpress.org/Roles_and_Capabilities )
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
    $sql  = "SELECT * FROM `".$e107_userTable."`, `".$e107_userExtendedTable."` ";
    $sql .= "WHERE ".$e107_userTable.".user_id = ".$e107_userExtendedTable.".user_extended_id";

    // Get user list
    $user_list = $e107db->get_results($sql, ARRAY_A);

    // This array contain the mapping between old e107 users and new wordpress users
    $user_mapping = array();

    // Convert news to post
    echo '<p>'.__('Importing e107 users...').'<br/><br/></p>';
    foreach($user_list as $user)
    {
      $count++;
      extract($user);

      // Add a user
      if (! username_exists($user_loginname) ) {
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
            , 'first_name'      => $user_name
            , 'nickname'        => $nickname
            , 'user_pass'       => $new_password
            , 'user_email'      => $e107db->escape($user_email)
            , 'user_registered' => $this->mysql_date($user_join)
            , 'user_url'        => $user_homepage
            , 'aim'             => $user_aim
            , 'yim'             => $user_msn  // Put MSN contact here because MSN and Yahoo! have merged some months ago: http://slashdot.org/articles/05/10/12/0227207.shtml
            , 'description'     => utf8_decode($desc)
            ));

          // Send mail notification to users to warn them of a new password (and new login because of UTF-8)
          // TODO: This should be optionnal
          // wp_new_user_notification($ret_id, $new_password)
      }

      // Update user mapping
      $user_mapping[$user_id] = $ret_id;
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
    $prefix = get_option('e107_db_prefix');
    $user_mapping = get_option('e107_user_mapping');

    // Prepare the SQL request
    $e107_newsTable  = $prefix."news";
    $sql  = "SELECT * FROM `".$e107_newsTable."`";

    // Get News list
    $news_list = $e107db->get_results($sql, ARRAY_A);

    // This array contain the mapping between old e107 news and newly inserted wordpress posts
    $news_mapping = array();

    // Convert news to post
    echo '<p>'.__('Importing e107 news as Wordpress posts...').'<br/><br/></p>';
    foreach($news_list as $news)
    {
      $count++;
      extract($news);

      //TODO: $news_category should be set as tag

      // Create a new bb code parser only once
      require_once(e_HANDLER.'bbcode_handler.php');
      if (!is_object($this->e_bb)) {
        $this->e_bb = new e_bbcode;
      }

      // Update author role if necessary;
      // If the user has the minimum role (aka subscriber) he is not able to post
      //   news. In this case, we raise is role by 1 level (aka contributor).
      $author_id = $user_mapping[$news_author];
      $author = new WP_User($author_id);
      if (! $author->has_cap('edit_posts'))
      {
        $author->set_role('contributor');
      }

      // Save e107 news in Wordpress database
      $ret_id = wp_insert_post(array(
          'post_author'    => $author_id     // use the new wordpress user ID
        , 'post_date'      => $this->mysql_date($news_datestamp)  //XXX ask or get the time offset ?
        , 'post_date_gmt'  => $this->mysql_date($news_datestamp)  //XXX ask or get the time offset ?
        , 'post_content'   => $this->e_bb->parseBBCodes($news_body, $news_id)
        , 'post_title'     => $news_title      //XXX bbcode allowed in titles ?
        , 'post_excerpt'   => $news_extended   //TODO: add a global option in the importer to ignore this
        , 'post_status'    => 'publish'        // News are always published in e107
        , 'comment_status' => $news_allow_comments //TODO: get global config to set this value dynamiccaly
        , 'ping_status'    => 'open'               //XXX is there such a concept in e107 ?
        //, 'post_modified'  =>     //XXX Auto or now() ?
        //, 'post_modified_gmt'  => //XXX Auto or now() ?
        , 'comment_count'  => $news_comment_total
        ));
      // Update post mapping
      $news_mapping[$news_id] = $ret_id;
    }

    // Return news mapping to let us pass it as global variable via options
    return $news_mapping;
  }


  // Step 0 screen
  function greet() {
    echo '<p>'.__('Hi! This importer allows you to extract news and users from e107 MySQL Database and import them into your Wordpress blog.').'</p>';
    // TODO: support all kind of charset
    echo '<p>'.__('WARNING: This plugin assume that your e107 site is fully encoded in UTF-8.').'</p>';
    echo '<p>'.__('This plugin was tested with e107 v0.7.5 and Wordpress v2.0.4.').'</p>';
    echo '<p>'.__('Your e107 Configuration settings are as follows:').'</p>';
    echo '<form action="admin.php?import=e107&amp;step=1" method="post">';
    echo '<ul>';
    printf( '<li><label for="dbuser">%s</label> <input type="text" name="dbuser" id="dbuser"/></li>'
          , __('e107 Database User:')
          );
    printf( '<li><label for="dbpass">%s</label> <input type="password" name="dbpass" id="dbpass"/></li>'
          , __('e107 Database Password:')
          );
    printf( '<li><label for="dbname">%s</label> <input type="text" name="dbname" id="dbname"/></li>'
          , __('e107 Database Name:')
          );
    printf( '<li><label for="dbhost">%s</label> <input type="text" name="dbhost" id="dbhost" value="localhost"/></li>'
          , __('e107 Database Host:')
          );
    printf('<li><label for="dbprefix">%s</label> <input type="text" name="dbprefix" value="e107_"/></li>'
          , __('e107 Table prefix:')
          );
    echo '</ul>';
    echo '<p>'.__("Click on the 'Next Step' button to import users.").'</p>';
    printf('<p>'.__("All users will be imported in the Wordpress database with the '%s' role. If you want to change this, change default role from the 'Options' > 'General' panel of the admin area.").'</p>'
          , __(get_settings('default_role'))
          );
    echo '<p>'.__("WARNING ! Wordpress don't like UTF-8 char (like é, è, à, etc) in login. So, when the user will be added, all non-ascii chars will be deleted from the login string.").'</p>';
    echo '<input type="submit" name="submit" value="'.__('Next Step: Import e107 Users').'" />';
    echo '</form>';
  }


  // Step 1 screen
  function import_users()
  {
    // Import e107 users
    $user_mapping = $this->importUsers();
    add_option('e107_user_mapping', $user_mapping);

    echo '<p>'.__('Database connexion successful !').'</p>';
    echo '<p>'.__('All e107 users were imported !').'</p>';
    echo '<p>'.__('The next step consist of importing all e107 news as Wordpress posts.').'</p>';
    echo '<form action="admin.php?import=e107&amp;step=2" method="post">';
    printf('<input type="submit" name="submit" value="%s" />', __('Next Step: Import e107 news'));
    echo '</form>';
  }


  // Step 2 screen
  function import_posts()
  {
    // Import e107 news as posts
    $news_mapping = $this->e107news2posts();
    add_option('e107_news_mapping', $news_mapping);

    echo '<p>'.__('All e107 news imported !').'</p>';
    echo '<form action="admin.php?import=e107&amp;step=3" method="post">';
    printf('<input type="submit" name="submit" value="%s" />', __('Next Import Step !!!!!'));
    echo '</form>';
  }


  function cleanup_e107import()
  {
    delete_option('e107_db_user');
    delete_option('e107_db_pass');
    delete_option('e107_db_name');
    delete_option('e107_db_host');
    delete_option('e107_db_prefix');
    delete_option('e107_user_mapping');
    delete_option('e107_news_mapping');
  }


  function dispatch()
  {
    if (empty ($_GET['step']))
      $step = 0;
    else
      $step = (int) $_GET['step'];
    $this->header();

    if ( $step > 0 )
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
    }

    // TODO: split user import step and database connexion step to make things easier to understand
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
        $this->cleanup_e107import();
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
register_importer('e107', 'e107', __('Import news as posts from e107'), array ($e107_import, 'dispatch'));




/* The code below is copy of (and/or inspired by) code from the e107 project, licensed
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

// Redifine som globals to match wordpress importer file hierarchy
define("e_BASE", $path);
define("e_FILE", e_BASE.'wp-admin/import/');
define("e_HANDLER", e_BASE.'wp-admin/import/bbcode/');

/*========== END of code inspired by e107_handlers/e107_class.php file ==========*/




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
|     $Revision: 1.277 $
|     $Date: 2006/04/30 23:48:39 $
|     $Author: mcfly_e107 $
+----------------------------------------------------------------------------+
*/

define("e107_INIT", TRUE);

require_once(e_HANDLER.'e_parse_class.php');
$tp = new e_parse;

define("THEME", "");
define("E107_DEBUG_LEVEL", FALSE);

/*========== END of code inspired by class2.php file ==========*/
?>