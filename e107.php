 <?php
/*
+---------------------------------------------------------------+
|   Wordpress filter to import e107 website.
|
|   Version: 0.5
|   Date: 19 nov 2006
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


  // Convert hexadecimal IP adresse string to decimal
  function ip_hex2dec($hex_ip)
  {
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
    $sql  = "SELECT ".$e107_userTable.".* FROM ".$e107_userTable." ";
    $sql .= "LEFT JOIN ".$e107_userExtendedTable." ON ".$e107_userTable.".user_id = ".$e107_userExtendedTable.".user_extended_id ";
    $sql .= "WHERE user_ban = 0"; // Exclude banned and un-verified users
    //echo $sql;

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
        // TODO: This should be optionnal
        // wp_new_user_notification($ret_id, $new_password)

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
      // Cast to int
      $news_id = (int) $news_id;

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
        , 'post_content'   => $e107db->escape($this->e_bb->parseBBCodes($news_body, $news_id))
        , 'post_title'     => $e107db->escape($news_title)      //XXX bbcode allowed in titles ?
        , 'post_excerpt'   => $e107db->escape($news_extended)   //TODO: add a global option in the importer to ignore this
        , 'post_status'    => 'publish'        // News are always published in e107
        , 'comment_status' => $news_allow_comments //TODO: get global config to set this value dynamiccaly
        , 'ping_status'    => 'open'               //XXX is there such a concept in e107 ?
        //, 'post_modified'  =>     //XXX Auto or now() ?
        //, 'post_modified_gmt'  => //XXX Auto or now() ?
        , 'comment_count'  => $news_comment_total
        ));

      // Update post mapping
      $news_mapping[$news_id] = (int) $ret_id;
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
    $prefix                  = get_option('e107_db_prefix');
    $user_mapping            = get_option('e107_user_mapping');
    $import_type             = get_option('e107_page_import_type');
    $protected_page_password = get_option('e107_page_password');
    $patch_theme             = get_option('e107_patch_theme');

    // Prepare the SQL request
    $e107_pagesTable  = $prefix."page";
    $sql = "SELECT * FROM `".$e107_pagesTable."`";

    // Get Static Pages list
    $page_list = $e107db->get_results($sql, ARRAY_A);

    // This array contain the mapping between old e107 static pages and newly inserted wordpress pages
    $pages_mapping = array();

    // Convert static pages to Wordpress pages
    echo '<p>'.__('Importing e107 static pages as Wordpress pages...').'<br/><br/></p>';
    foreach($page_list as $page)
    {
      $count++;
      extract($page);
      // Cast to int
      $page_id = (int) $page_id;

      // Special actions for private static pages
      $post_password;
      unset($post_password);
      $skip = False;
      if ($page_class != '0')
      {
        if ($import_type != 'import_as_public' and $import_type != 'import_and_protect')
        {
          $skip = True;
        }
        if ($import_type == 'import_and_protect')
        {
          $post_password = $protected_page_password;
          // TODO: send mail to the admin as a password reminder. When this is done, make the "import and protect" option as default one
        }
      }

      if (!$skip)
      {
        // Create a new bb code parser only once
        require_once(e_HANDLER.'bbcode_handler.php');
        if (!is_object($this->e_bb)) {
          $this->e_bb = new e_bbcode;
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

        // Update author role if necessary;
        // If the user has the minimum role (aka subscriber) he is not able to post
        //   news. In this case, we raise is role by 1 level (aka contributor).
        $author_id = $user_mapping[$page_author];
        $author = new WP_User($author_id);
        if (! $author->has_cap('edit_posts'))
        {
          $author->set_role('contributor');
        }

        // Define comment status
        $page_template;
        if (! $page_comment_flag)
        {
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
          , 'post_content'   => $e107db->escape($this->e_bb->parseBBCodes($page_text, $page_id))
          , 'post_title'     => $e107db->escape($page_title)      //XXX bbcode allowed in titles ?
          , 'post_status'    => 'static'
          , 'comment_status' => $comment_status
          , 'post_password'  => $post_password
          , 'ping_status'    => 'closed'               //XXX is there a global variable in wordpress or e107 to guess this ?
          //, 'post_modified'  =>     //XXX Auto or now() ?
          //, 'post_modified_gmt'  => //XXX Auto or now() ?
          , 'page_template'  => $page_template
          ));

        // Update page mapping
        $pages_mapping[$page_id] = (int) $ret_id;
      }
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

    // Prepare the SQL request
    $e107_commentsTable  = $prefix."comments";
    $sql  = "SELECT * FROM `".$e107_commentsTable."`";

    // Get News list
    $comment_list = $e107db->get_results($sql, ARRAY_A);

    // This array contain the mapping between old e107 comments and newly inserted wordpress comments
    $comments_mapping = array();

    // Convert news to post
    echo '<p>'.__('Importing e107 comments as Wordpress comments...').'<br/><br/></p>';
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
        // Create a new bb code parser only once
        require_once(e_HANDLER.'bbcode_handler.php');
        if (!is_object($this->e_bb)) {
          $this->e_bb = new e_bbcode;
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

          // TODO: guess author name from email if exist
  /*
  $comment_author=0.mail_user@wanadoo.fr
  $author_id=0
  $author_name=mail_user@wanadoo.fr
  $author_id=
  $author_email=
  $author_name=mail_user@wanadoo.fr
  $author_url=
  */
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
          , 'comment_content'      => $e107db->escape($this->e_bb->parseBBCodes($comment_comment, $comment_id))
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
    echo '<p>'.__('This importer allows you to extract the most important content and data from e107 MySQL database and import them into your Wordpress blog.').'</p>';
    echo '<p>'.__('Features:').'<br/><ul><li>';
    echo __('Import news').'</li><li>';
    echo __('Import static pages').'</li><li>';
    echo __('Import users and their profile').'</li><li>';
    echo __('Import comments').'</li><li>';
    echo __('Support bbcode');
    echo '</li></ul></p>';
    // TODO: support all kind of charset
    echo '<p>'.'<strong>'.__('Warning').'</strong>: '.__("This plugin assume that your e107 site is fully encoded in UTF-8. If it's not the case, please look at <a href='http://wiki.e107.org/?title=Upgrading_database_content_to_UTF8'>Upgrading database content to UTF8</a> article on e107 wiki.").'</p>';
    echo '<p>'.__('This plugin was tested with e107 v0.7.5 and Wordpress v2.0.5.').'</p>';

    echo '<hr/>';

    echo '<p>'.__('Your e107 configuration settings are as follows (filled by default with current Wordpress config):').'</p>';
    echo '<form action="admin.php?import=e107&amp;step=1" method="post">';
    echo '<ul>';
    printf( '<li><label for="dbuser">%s</label> <input type="text" name="dbuser" id="dbuser" value="%s"/></li>'
          , __('e107 Database User:')
          , DB_USER
          );
    printf( '<li><label for="dbpass">%s</label> <input type="password" name="dbpass" id="dbpass" value="%s"/></li>'
          , __('e107 Database Password:')
          , DB_PASSWORD
          );
    printf( '<li><label for="dbname">%s</label> <input type="text" name="dbname" id="dbname" value="%s"/></li>'
          , __('e107 Database Name:')
          , DB_NAME
          );
    printf( '<li><label for="dbhost">%s</label> <input type="text" name="dbhost" id="dbhost" value="%s"/></li>'
          , __('e107 Database Host:')
          , DB_HOST
          );
    printf('<li><label for="dbprefix">%s</label> <input type="text" name="dbprefix" value="e107_"/></li>'
          , __('e107 Table prefix:')
          );
    echo '</ul>';

    echo '<hr/>';

    printf(__("<p>All users will be imported in the Wordpress database with the '%s' role. If you want to change this, change default role from the 'Options' > 'General' panel of the admin area.").'</p>'
          , __(get_settings('default_role'))
          );
    echo __("<p><strong>Warning</strong>:<ul><li>");
    echo __("Wordpress don't like UTF-8 char (like accents, etc) in login. So, when the user will be added, all non-ascii chars will be deleted from the login string.").'</li><li>';
    echo __("All users' password will be resetted.").'</li></ul>';
    echo '<input type="submit" name="submit" value="'.__('Next step: Import e107 users').'" />';
    echo '</form>';
  }


  // Step 1 screen
  function import_users()
  {
    // Import e107 users
    $user_mapping = $this->importUsers();
    add_option('e107_user_mapping', $user_mapping);
    echo '<p>'.__('Database connexion successful !').'</p>';
    echo '<p><strong>'.sizeof($user_mapping).'</strong>'.__(' users imported from e107 to Wordpress.').'</p><hr/>';

    echo '<p>'.__('The next step consist of importing all e107 news as Wordpress posts.').'</p>';
    echo '<form action="admin.php?import=e107&amp;step=2" method="post">';
    printf('<input type="submit" name="submit" value="%s" />', __('Next step: Import e107 news'));
    echo '</form>';
  }


  // Step 2 screen
  function import_posts()
  {
    // Import e107 news as posts
    $news_mapping = $this->e107news2posts();
    add_option('e107_news_mapping', $news_mapping);
    echo '<p><strong>'.sizeof($news_mapping).'</strong>'.__(' news imported from e107 to Wordpress.').'</p><hr/>';

    echo '<p>'.__('The next step consist of importing all e107 static pages as Wordpress pages.').'</p>';

    echo '<form action="admin.php?import=e107&amp;step=3" method="post">';

    echo '<p><strong>'.__('Warning 1').':</strong> '.__("Wordpress doesn't display comments on static pages by default. However, this import tool can do this by patching the default Wordpress theme (Kubrick).").'</p>';

    echo '<p>Do you want to patch Kubrick ?';
    echo '<ul><li>';
    echo '<label><input name="patch_default_theme" type="radio" value="no_patch_theme" checked="checked"/> No, I don\'t want to patch the default theme</label>';
    echo '</li><li>';
    echo '<label><input name="patch_default_theme" type="radio" value="patch_theme"/> Yes, I want to let this import tool patch the Kubrick theme and set it as the current one.</label>';
    echo '</li></ul></p>';


    echo '<p><strong>'.__('Warning 2').':</strong> '.__('all protected pages in e107 will be publicly visible on your blog after this step, because there is no mechanism to make pages private in Wordpress.').'</p>';

    echo '<p>How private static pages should be handled ?';
    echo '<ul><li>';
    echo '<label><input name="page_import_type" type="radio" value="no_import" checked="checked"/> Do not import private pages</label>';
    echo '</li><li>';
    echo '<label><input name="page_import_type" type="radio" value="import_as_public"/> Import private pages and change visibility to public</label>';
    echo '</li><li>';
    printf( '<label><input name="page_import_type" type="radio" value="import_and_protect"/> Import private pages and protect them with the following password: <input type="text" name="page_password" id="page_password" value="%s"/></label>'
          , substr(md5(uniqid(microtime())), 0, 6)
          );
    echo '</li></ul></p>';
    printf('<input type="submit" name="submit" value="%s" />', __('Next step: Import e107 static pages'));
    echo '</form>';
  }


  // Step 3 screen
  function import_pages()
  {
    // Import e107 static pages
    $pages_mapping = $this->importPages();
    add_option('e107_pages_mapping', $pages_mapping);
    echo '<p><strong>'.sizeof($pages_mapping).'</strong>'.__(' static pages imported from e107 to Wordpress.').'</p><hr/>';

    echo '<p>'.__('The next step consist of importing all e107 comments to Wordpress.').'</p>';
    echo '<form action="admin.php?import=e107&amp;step=4" method="post">';
    printf('<input type="submit" name="submit" value="%s" />', __('Next step: Import e107 comments'));
    echo '</form>';
  }


  // Step 4 screen
  function import_comments()
  {
    // Import e107 news comments
    $comments_mapping = $this->importComments();
    add_option('e107_comments_mapping', $comments_mapping);
    echo '<p><strong>'.sizeof($comments_mapping).'</strong>'.__(' comments imported from e107 to Wordpress.').'</p><hr/>';

    echo '<p>'.__('The last step is needed to clean-up the import process.').'</p>';
    echo '<form action="admin.php?import=e107&amp;step=5" method="post">';
    printf('<input type="submit" name="submit" value="%s" />', __('Last step: Finish import process'));
    echo '</form>';
  }


  // Step 5
  function cleanup_e107import()
  {
    delete_option('e107_db_user');
    delete_option('e107_db_pass');
    delete_option('e107_db_name');
    delete_option('e107_db_host');
    delete_option('e107_db_prefix');
    delete_option('e107_user_mapping');
    delete_option('e107_news_mapping');
    delete_option('e107_pages_mapping');
    delete_option('e107_page_import_type');
    delete_option('e107_page_password');
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

    // Keep static pages options on step 3
    if ($step == 3)
    {
      if($_POST['page_import_type'])
      {
        if(get_option('e107_page_import_type'))
          delete_option('e107_page_import_type');
        add_option('e107_page_import_type', $_POST['page_import_type']);
      }
      if($_POST['page_password'])
      {
        if(get_option('e107_page_password'))
          delete_option('e107_page_password');
        add_option('e107_page_password', $_POST['page_password']);
      }
      if($_POST['patch_default_theme'])
      {
        if(get_option('e107_patch_theme'))
          delete_option('e107_patch_theme');
        add_option('e107_patch_theme', $_POST['patch_default_theme']);
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
        $this->import_pages();
        break;
      case 4 :
        $this->import_comments();
        break;
      case 5 :
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
define("e_HANDLER", e_BASE.'wp-admin/import/e107-includes/');

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