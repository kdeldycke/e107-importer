<?php
/*
Plugin Name: e107 Redirector
Plugin URI: http://github.com/kdeldycke/e107-importer/blob/master/e107-redirector.php
Description: Redirect URLs from previous e107 website to new WordPress blog after a migration. This plugin is only designed to be used with e107 Importer plugin and is a subproject of it.
Author: Kevin Deldycke
Author URI: http://kevin.deldycke.com
Version: 1.0.dev
Stable tag: 1.0
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/


class e107_Redirector {

  function execute() {
    // Requested URL
    $requested = $_SERVER['REQUEST_URI'];

    // Initialize mappings
    $news_mapping    = array();
    $page_mapping    = array();
    $comment_mapping = array();
    $user_mapping = array();

    // Load mappings
    if (get_option('e107_redirector_news_mapping'))    $news_mapping    = get_option('e107_redirector_news_mapping');
    if (get_option('e107_redirector_page_mapping'))    $page_mapping    = get_option('e107_redirector_page_mapping');
    if (get_option('e107_redirector_comment_mapping')) $comment_mapping = get_option('e107_redirector_comment_mapping');
    if (get_option('e107_redirector_user_mapping'))    $user_mapping    = get_option('e107_redirector_user_mapping');

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
                               , '/^.*\/userposts\.php(?:%3F|\?)0\.comments\.(\d+).*$/i'
                                   # /userposts.php?0.comments.29
                                   # TODO: /userposts.php?0.forums.29
                               )
           )
    );

    // Try to apply each redirect rule
    foreach ($redirect_rules as $rule_set) {
      $ctype   = $rule_set['type'];
      $mapping = $rule_set['mapping'];
      $rules   = $rule_set['rules'];
      if (sizeof($rules) > 0 && $mapping && is_array($mapping) && sizeof($mapping) > 0)
        foreach ($rules as $regexp) {
          if (preg_match($regexp, $requested, $matches))
            if (array_key_exists($matches[1], $mapping)) {
              $content_id = $mapping[$matches[1]];
              if ($ctype == 'comment') {
                $link = get_comment_link($content_id);
              } elseif ($ctype == 'user') {
                $link = get_author_posts_url($content_id);
                // TODO: Fallback to gravatar ?
              } else {
                $link = get_permalink($content_id);
              }
              wp_redirect($link, $status = 301);
            }
        }
    }

    // Is the e107 news page (aka home page) requested ?
    // If so, redirect all http://www.domain.com/anything/news.php* to the WordPress home page
    if (empty($link) && preg_match('/^.*\/news\.php.*$/i', $requested))
      wp_redirect(get_option('siteurl'), $status = 301);

    // Redirect feeds as explained there: http://kevin.deldycke.com/2007/05/feedburner-and-e107-integration/
    if (empty($link) && preg_match('/^.*\/e107_plugins\/rss_menu\/rss\.php(?:%3F|\?)?(.*)$/i', $requested, $matches)) {
      // Default feed redirections
      $feed_content = '';
      $feed_type    = 'rss2';
      // Analyze feed parameters
      $feed_params = explode('.', $matches[1]);
      if (sizeof($feed_params) > 0)
        switch (strtolower($feed_params[0])) {
          case 'news':
          case '1':
            $feed_content = '';
            break;
          case 'comments':
          case '5':
            $feed_content = 'comments_';
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
      // Redirect to proper WordPress feed
      $wordpress_feed = $feed_content.$feed_type.'_url';
      wp_redirect(get_bloginfo($wordpress_feed), $status = 301);
    }

    // Do nothing: let WordPress do its job (and probably show user a 404 error ;) )
  }
}


add_action('template_redirect', array('e107_Redirector', 'execute'));
