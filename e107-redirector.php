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


class e107_Redirections {

  function execute() {
    // Requested URL
    $requested = $_SERVER['REQUEST_URI'];

    // Final destination
    $link = '';

    // TODO Get news mapping from WordPress database
    // $news_mapping =

    // TODO Get page mapping from WordPress database
    // $page_mapping =

    // TODO Get comment mapping from WordPress database
    // $comment_mapping =

    // Associate each mapping with their related regexp
    $redirect_rules = array(
      array( 'mapping' => $news_mapping
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
      array( 'mapping' => $page_mapping
           , 'rules'   => array( '/^\/*page\.php(?:%3F|\?)(\d+)(.*)$/i'
                                   # /page.php?16
                                   # /page.php?16&res=1680x1050
                                   # /page.php%3F16
                               )
           )
    );

    // Try to apply each redirect rule
    foreach ($redirect_rules as $rule_set) {
      $mapping = $rule_set['mapping'];
      $rules   = $rule_set['rules'];
      if ($mapping && is_array($mapping) && sizeof($mapping) > 0)
        foreach ($rules as $regexp) {
          if (preg_match($regexp, $requested, $matches))
            if (array_key_exists($matches[1], $mapping)) {
              $link = get_permalink($mapping[$matches[1]]);
              wp_redirect($link, $status = 301);
              // TODO: be sure that WP exit plugin here
            }
        }
    }

    // Is the e107 news page (aka home page) requested ?
    // If so, redirect all http://www.coolcavemen.com/news.php* to the wordpress home page
    if (empty($link) && preg_match('/^\/*news\.php(.*)$/i', $requested))
      wp_redirect(get_option('siteurl'), $status = 301);

    // Redirect feeds as explained there: http://kev.coolcavemen.com/2007/05/feedburner-and-e107-integration/
    // TODO: redirect to Atom feeds
    if (empty($link) && preg_match('/^\/*e107_plugins\/*rss_menu\/*rss\.php(.*)$/i', $requested, $matches))
      if(preg_match('/^\?(5|Comments)(.*)$/i', $matches[1])) {
        wp_redirect(get_bloginfo('comments_rss2_url'), $status = 301);
      } else {
        wp_redirect(get_bloginfo('rss2_url'), $status = 301);
      }

    // TODO: redirect user profiles ?? yes, but are user's profiles public ? I don't think so...

    // Do nothing: let wordpress do its job (and probably show user a 404 error ;) )
  }
}


add_action('template_redirect', array('e107_Redirections', 'execute'));
