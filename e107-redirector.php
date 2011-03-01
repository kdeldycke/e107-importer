<?php
/*
Plugin Name: e107 Redirector
Plugin URI: http://github.com/kdeldycke/e107-importer/blob/master/e107-redirector.php
Description: Redirect URLs from previous e107 website to new WordPress blog after a migration. This plugin is only designed to be used with e107 Importer plugin and is a subproject of it.
Author: Kevin Deldycke
Author URI: http://kevin.deldycke.com
Version: 1.1
Stable tag: 1.1
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/


class e107_Redirector {

  function execute() {
    // Requested URL
    $requested = $_SERVER['REQUEST_URI'];

    // Initialize mappings
    $news_mapping       = array();
    $category_mapping   = array();
    $page_mapping       = array();
    $comment_mapping    = array();
    $user_mapping       = array();
    $forum_mapping      = array();
    $forum_post_mapping = array();

    // Load mappings
    if (get_option('e107_redirector_news_mapping'))       $news_mapping       = get_option('e107_redirector_news_mapping');
    if (get_option('e107_redirector_category_mapping'))   $category_mapping   = get_option('e107_redirector_category_mapping');
    if (get_option('e107_redirector_page_mapping'))       $page_mapping       = get_option('e107_redirector_page_mapping');
    if (get_option('e107_redirector_comment_mapping'))    $comment_mapping    = get_option('e107_redirector_comment_mapping');
    if (get_option('e107_redirector_user_mapping'))       $user_mapping       = get_option('e107_redirector_user_mapping');
    if (get_option('e107_redirector_forum_mapping'))      $forum_mapping      = get_option('e107_redirector_forum_mapping');
    if (get_option('e107_redirector_forum_post_mapping')) $forum_post_mapping = get_option('e107_redirector_forum_post_mapping');

    // Final destination
    $link = '';

    // Associate each mapping with their related regexp
    $redirect_rules = array(
      array( 'type'    => 'post'
           , 'mapping' => $news_mapping
           , 'rules'   => array( '/^.*\/comment\.php(?:%3F|\?)comment\.news\.(\d+).*$/i'
                                   # /comment.php?comment.news.138
                                   # /comment.php?comment.news.138&dfsd
                               , '/^.*\/news\.php(?:%3F|\?)item\.(\d+).*$/i'
                                   # /news.php?item.138
                                   # /news.php?item.100.3
                                   # /news.php?item.138&res=1680x1050
                               , '/^.*\/news\.php(?:%3F|\?)extend\.(\d+).*$/i'
                                   # /news.php?extend.17
                               )
           ),
      array( 'type'    => 'category'
           , 'mapping' => $category_mapping
           , 'rules'   => array( '/^.*\/news\.php(?:%3F|\?)cat\.(\d+).*$/i'
                                   # /news.php?cat.3
                               )
           ),
      array( 'type'    => 'post'
           , 'mapping' => $page_mapping
           , 'rules'   => array( '/^.*\/page\.php(?:%3F|\?)(\d+).*$/i'
                                   # /page.php?16
                                   # /page.php?16&res=1680x1050
                                   # /page.php%3F16
                               )
           ),
      array( 'type'    => 'comment'
           , 'mapping' => $comment_mapping
           , 'rules'   => array( # XXX Looks like there is no direct link to comments in e107
                               )
           ),
      array( 'type'    => 'user'
           , 'mapping' => $user_mapping
           , 'rules'   => array( '/^.*\/user\.php(?:%3F|\?)id\.(\d+).*$/i'
                                   # /user.php?id.29
                               , '/^.*\/userposts\.php(?:%3F|\?).*\.comments\.(\d+).*$/i'
                                   # /userposts.php?0.comments.29
                               )
           ),
      array( 'type'    => 'forum'
           , 'mapping' => $forum_mapping
           , 'rules'   => array( '/^.*\/forum_viewforum\.php(?:%3F|\?)(\d+).*$/i'
                                   # /forum_viewforum.php?4

                                   # TODO ###
                                   # /forum_viewforum.php?4.100
                                   # /forum_viewforum.php?4.200
                                   # Looks like Second number here seams to indicate the "page" of the forum browsing (= offset added). Maybe we should transform this to the page number itself based on prefs.
                                   # At Line 400 of forum_viewforum.php, second number is: ($a * $pref['forum_postspage'])
                               )
           ),
      array( 'type'    => 'forum_post'
           , 'mapping' => $forum_post_mapping
           , 'rules'   => array( '/^.*\/forum_viewtopic\.php(?:%3F|\?).*#post_(\d+).*$/i'
                                   # /forum_viewtopic.php?12301.0#post_19026
                                   # /forum_viewtopic.php?12301.100#post_19026
                               , '/^.*\/forum_viewtopic\.php(?:%3F|\?)(\d+).*$/i'
                                   # /forum_viewtopic.php?19026
                                   # /forum_viewtopic.php?19026.post
                               )
           ),
      array( 'type'    => 'forum_thread_last_page'
           , 'mapping' => $forum_post_mapping
           , 'rules'   => array( '/^.*\/forum_viewtopic\.php(?:%3F|\?)(\d+)\.last.*$/i'
                                   # /forum_viewtopic.php?19026.last
                               )
           ),
      array( 'type'    => 'forum_user'
           , 'mapping' => $user_mapping
           , 'rules'   => array( '/^.*\/userposts\.php(?:%3F|\?).*\.forums\.(\d+).*$/i'
                                   # /userposts.php?0.forums.29
                               )
           )
    );

    // Try to apply each redirect rule
    foreach ($redirect_rules as $rule_set) {
      $ctype   = $rule_set['type'];
      $mapping = $rule_set['mapping'];
      $rules   = $rule_set['rules'];
      if (sizeof($rules) > 0 && $mapping && is_array($mapping) && sizeof($mapping) > 0) {
        foreach ($rules as $regexp) {
          if (preg_match($regexp, $requested, $matches)) {
            if (array_key_exists($matches[1], $mapping)) {
              $content_id = $mapping[$matches[1]];
              switch ($ctype) {
                case 'comment':
                  $link = get_comment_link($content_id);
                  break;
                case 'category':
                  $link = get_category_link($content_id);
                  break;
                case 'user':
                  $link = get_author_posts_url($content_id);
                  break;
                case 'forum':
                  $link = bbp_get_forum_permalink($content_id);
                  break;
                case 'forum_thread_last_page':
                  $content_id = bbp_topic_last_reply_id($content_id);
                case 'forum_post':
                  if (bbp_is_topic($content_id)) {
                    $link = bbp_get_topic_permalink($content_id);
                  } else {
                    $link = bbp_get_reply_permalink($content_id);
                  }
                  break;
                case 'forum_user':
                  $link = bbp_get_user_profile_url($content_id);
                  break;
                default:
                  $link = get_permalink($content_id);
              }
              wp_redirect($link, $status = 301);
            }
          }
        }
      }
    }

    // Redirect feeds as explained there: http://kevin.deldycke.com/2007/05/feedburner-and-e107-integration/
    if (empty($link) && preg_match('/^.*\/e107_plugins\/(?:rss_menu\/|forum\/e_)rss\.php(?:%3F|\?)?(.*)$/i', $requested, $matches)) {
      // Default feed redirections
      $feed_content = '';
      $feed_type    = 'rss2';
      // Analyze feed parameters
      $feed_params = explode('.', $matches[1]);
      if (sizeof($feed_params) > 0)
        switch (strtolower($feed_params[0])) {
          case '1':
          case 'news':
            $feed_content = '';
            break;
          case '5':
          case 'comments':
            $feed_content = 'comments_';
            break;
          case 'forum':
            # TODO: get forum feed
            # XXX Not implemented yet in bbPress, see: http://trac.bbpress.org/ticket/1422
            break;
          case '6':
          case 'threads':      # TODO: Test both
          case 'forumthreads': # TODO: Test both
          case '7':
          case 'posts':
          case 'forumposts':
          case '8':
          case 'topic':
          case 'forumtopic':
          case '11':
          case 'name':
          case 'forumname':
            # TODO: get the feed of the thread (= feed of the topic + replies) to which the post is part of (all e107 feeds here returns to forum/forum_viewtopic.php?XXX like URLs anyway).
            # XXX Not implemented yet in bbPress, see: http://trac.bbpress.org/ticket/1422
            break;
        }
      if (sizeof($feed_params) > 1)
        switch (strtolower($feed_params[1])) {
          case '1':
            $feed_type = 'rss';
            // Comments are not served as RSS, fall back to RSS2
            if ($feed_content == 'comments_')
              $feed_type = 'rss2';
            break;
          case '2':
            $feed_type = 'rss2';
            break;
          case '3':
            $feed_type = 'rdf';
            // Comments are not served as RDF, fall back to Atom
            if ($feed_content == 'comments_')
              $feed_type = 'atom';
            break;
          case '4':
            $feed_type = 'atom';
            break;
        }
      if ($feed_content != '') {
      // Redirect to proper WordPress feed
        $wordpress_feed = $feed_content.$feed_type.'_url';
        wp_redirect(get_bloginfo($wordpress_feed), $status = 301);
      }
    }

    // Generic redirects and catch-alls
    if (empty($link)) {

      // Redirect to the WordPress home page
      if (preg_match('/^.*\/news\.php.*$/i', $requested))
        wp_redirect(get_option('siteurl'), $status = 301);

      // Redirect to bbPress home page
      elseif (preg_match('/^.*\/forum(.*).php.*$/i', $requested))
        wp_redirect(get_option('siteurl').'/'.get_option('_bbp_root_slug'), $status = 301);

      // Redirects to forum stats
#      elseif (preg_match('/^.*\/forum_stats\.php.*$/i', $requested))
#        wp_redirect(XXX, $status = 301);

      // Redirects to most active threads of all time
#      elseif (preg_match('/^.*\/top\.php(?:%3F|\?)0\.active.*$/i', $requested))
#        wp_redirect(XXX, $status = 301);

       // Redirects to ???
#      elseif (preg_match('/^.*\/top\.php(?:%3F|\?)0\.top\.forum\.10.*$/i', $requested))
#        wp_redirect(XXX, $status = 301);
    }

    // Do nothing: let WordPress do its job (and probably show user a 404 error ;) )
  }
}


add_action('template_redirect', array('e107_Redirector', 'execute'));
