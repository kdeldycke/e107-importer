<?php
/*
+---------------------------------------------------------------+
|   Wordpress filter to import e107 website.
|
|   Version: 0.1
|   Date: 22 aug 2006
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

  function greet() {
    echo '<p>'.__('Hi! This importer allows you to extract news from e107 MySQL Database and import them as posts into your blog.').'</p>';
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
    echo '<input type="submit" name="submit" value="'.__('Import e107 News').'" />';
    echo '</form>';
  }

  // Convert unix timestamp to mysql datetimestamp
  function mysql_date($unix_time)
  {
    return date("Y-m-d H:i:s", $unix_time);
  }

  // Step 1 : get e107 news and save them as Wordpress posts
  function e107news2posts()
  {
    // General Housekeeping
    $e107db = new wpdb(get_option('e107user'), get_option('e107pass'), get_option('e107name'), get_option('e107host'));
    set_magic_quotes_runtime(0);
    $prefix = get_option('e107dbprefix');

    // Prepare the SQL request
    $e107_newsTable  = $prefix."news";
    $sql  = "SELECT * FROM `".$e107_newsTable."`";

    // Get News list
    $news_list = $e107db->get_results($sql, ARRAY_A);

    // This array contain the mapping between old e107 news and newly inserted wordpress posts
    $e107news2wpposts = array();

    // Convert news to post
    echo '<p>'.__('Importing e107 news as Wordpress posts...').'<br/><br/></p>';
    foreach($news_list as $news)
    {
      $count++;
      extract($news);

      // $news_category should be set as tag

      $ret_id = wp_insert_post(array('post_author'     => $news_author     //OK! users must be imported first then the user id mapping should be used
                                    , 'post_date'      => $this->mysql_date($news_datestamp)  //OK! convert date to iso timestamp
                                    , 'post_date_gmt'  => $this->mysql_date($news_datestamp)  //OK! ask or get the time offset
                                    , 'post_content'   => $news_body       //OK! translate bb tag to html tags
                                    , 'post_title'     => $news_title      //OK!
                                    , 'post_excerpt'   => $news_extended   //OK! add a global option in the importer to ignore this
                                    , 'post_status'    => 'publish'    //OK! news are always published in e107
                                    , 'comment_status' => $news_allow_comments //OK! get global config: it override this value
                                    , 'ping_status'    => 'open'
                                    //, 'post_modified'  => // Auto or now ?
                                    //, 'post_modified_gmt'  => // Auto or now ?
                                    , 'comment_count'  => $news_comment_total
                                   ));
      // Update post mapping
      $e107news2wpposts[$news_id] = $ret_id;
    }

  }

  ///////////// START OF non-KEfVIN CODE /////////////
  function news2posts($posts='')
  {
    // General Housekeeping
    global $wpdb;
    $count = 0;
    $dcposts2wpposts = array();
    $cats = array();

    // Do the Magic
    if(is_array($posts))
    {
      echo '<p>'.__('Importing Posts...').'<br /><br /></p>';
      foreach($posts as $post)
      {
        $count++;
        extract($post);

        // Set Dotclear-to-WordPress status translation
        $stattrans = array(0 => 'draft', 1 => 'publish');
        $comment_status_map = array (0 => 'closed', 1 => 'open');

        //Can we do this more efficiently?
        $uinfo = ( get_userdatabylogin( $user_id ) ) ? get_userdatabylogin( $user_id ) : 1;
        $authorid = ( is_object( $uinfo ) ) ? $uinfo->ID : $uinfo ;

        $Title = $wpdb->escape(csc ($post_titre));
        $post_content = textconv ($post_content);
        if ($post_chapo != "") {
          $post_excerpt = textconv ($post_chapo);
          $post_content = $post_excerpt ."\n<!--more-->\n".$post_content;
        }
        $post_excerpt = $wpdb->escape ($post_excerpt);
        $post_content = $wpdb->escape ($post_content);
        $post_status = $stattrans[$post_pub];

        // Import Post data into WordPress

        if($pinfo = post_exists($Title,$post_content))
        {
          $ret_id = wp_insert_post(array(
              'ID'      => $pinfo,
              'post_author'   => $authorid,
              'post_date'   => $post_dt,
              'post_date_gmt'   => $post_dt,
              'post_modified'   => $post_upddt,
              'post_modified_gmt' => $post_upddt,
              'post_title'    => $Title,
              'post_content'    => $post_content,
              'post_excerpt'    => $post_excerpt,
              'post_status'   => $post_status,
              'post_name'   => $post_titre_url,
              'comment_status'  => $comment_status_map[$post_open_comment],
              'ping_status'   => $comment_status_map[$post_open_tb],
              'comment_count'   => $post_nb_comment + $post_nb_trackback)
              );
        }
        else
        {
          $ret_id = wp_insert_post(array(
              'post_author'   => $authorid,
              'post_date'   => $post_dt,
              'post_date_gmt'   => $post_dt,
              'post_modified'   => $post_modified_gmt,
              'post_modified_gmt' => $post_modified_gmt,
              'post_title'    => $Title,
              'post_content'    => $post_content,
              'post_excerpt'    => $post_excerpt,
              'post_status'   => $post_status,
              'post_name'   => $post_titre_url,
              'comment_status'  => $comment_status_map[$post_open_comment],
              'ping_status'   => $comment_status_map[$post_open_tb],
              'comment_count'   => $post_nb_comment + $post_nb_trackback)
              );
        }
        $dcposts2wpposts[$post_id] = $ret_id;

        // Make Post-to-Category associations
        $cats = array();
        if($cat1 = get_catbynicename($post_cat_name)) { $cats[1] = $cat1; }

        if(!empty($cats)) { wp_set_post_cats('', $ret_id, $cats); }
      }
    }
    // Store ID translation for later use
    add_option('dcposts2wpposts',$dcposts2wpposts);

    echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> posts imported.'), $count).'<br /><br /></p>';
    return true;
  }
  ///////////// END OF non-KEVIN CODE /////////////

  function import_posts()
  {
    // Import e107 news as posts
    $this->e107news2posts();

    echo '<form action="admin.php?import=e107&amp;step=2" method="post">';
    printf('<input type="submit" name="submit" value="%s" />', __('Next Import Step !!!!!'));
    echo '</form>';
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
        if(get_option('e107user'))
          delete_option('e107user');
        add_option('e107user',$_POST['dbuser']);
      }
      if($_POST['dbpass'])
      {
        if(get_option('e107pass'))
          delete_option('e107pass');
        add_option('e107pass',$_POST['dbpass']);
      }

      if($_POST['dbname'])
      {
        if(get_option('e107name'))
          delete_option('e107name');
        add_option('e107name',$_POST['dbname']);
      }
      if($_POST['dbhost'])
      {
        if(get_option('e107host'))
          delete_option('e107host');
        add_option('e107host',$_POST['dbhost']);
      }
      if($_POST['dbprefix'])
      {
        if(get_option('e107dbprefix'))
          delete_option('e107dbprefix');
        add_option('e107dbprefix',$_POST['dbprefix']);
      }

    }

    switch ($step)
    {
      default:
      case 0 :
        $this->greet();
        break;
      case 1 :
        $this->import_posts();
        break;
    }

    $this->footer();
  }

  function e107_Import() {
    // Nothing.
  }
}

$e107_import = new e107_Import();

register_importer('e107', 'e107', __('Import news as posts from e107'), array ($e107_import, 'dispatch'));
?>
