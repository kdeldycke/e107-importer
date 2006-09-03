<?php
/*
+---------------------------------------------------------------+
|   Wordpress filter to import e107 website.
|
|   Version: 0.2
|   Date: 3 sep 2006
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

      // Create a new bb code parser only once
      require_once(e_HANDLER.'bbcode_handler.php');
      if (!is_object($this->e_bb)) {
        $this->e_bb = new e_bbcode;
      }

      $ret_id = wp_insert_post(array(
          'post_author'    => $news_author     //OK! users must be imported first then the user id mapping should be used
        , 'post_date'      => $this->mysql_date($news_datestamp)  //OK! convert date to iso timestamp
        , 'post_date_gmt'  => $this->mysql_date($news_datestamp)  //OK! ask or get the time offset
        , 'post_content'   => $this->e_bb->parseBBCodes($news_body, $news_id) //OK! translate bb tag to html tags
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



//////////////////////////////////////////////////////////////////////
// The code below is inspired by code from the e107 project, licensed
// under the GPL and (c) copyrighted to Steve Dunstan (see copyright
// headers).
//////////////////////////////////////////////////////////////////////



////////////////// Start of code inspired by e107_handlers/e107_class.php file //////////////////
/*
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

/////////////////// END of code inspired by e107_handlers/e107_class.php file ///////////////////



////////////////// START of code inspired by class2.php file //////////////////
/*
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

/////////////////// END of code inspired by class2.php file ///////////////////

?>