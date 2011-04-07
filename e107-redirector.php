<?php
/*
Plugin Name: e107 Redirector
Plugin URI: http://github.com/kdeldycke/e107-importer/blob/master/e107-redirector.php
Description: Redirect URLs from previous e107 website to new WordPress blog after a migration. This plugin is only designed to be used with e107 Importer plugin and is a subproject of it.
Author: Kevin Deldycke
Author URI: http://kevin.deldycke.com
Version: 1.3.dev
Stable tag: 1.2
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/


// Mapping naming conventions
define('MAPPING_SUFFIX', '_mapping');
define('OPTION_PREFIX' , 'e107_redirector_');


class e107_Redirector {

  // e107 to WordPress content mapping
  var $user_mapping;
  var $news_mapping;
  var $category_mapping;
  var $page_mapping;
  var $comment_mapping;
  var $forum_mapping;
  var $forum_post_mapping;
  var $image_mapping;


  // PHP5 constructor
  function __construct() {
    // Load mappings
    $this->load_mappings();
    // Register the redirect action
    add_action('template_redirect', array(&$this, 'redirect'));
  }


  // PHP4 constructor
  function e107_Redirector() {
    $this->__construct();
  }


  // This method encode all non-alphanumerical characters of an URL path but keeps slashes
  function normalize_urlpath($urlpath) {
    return str_replace('%2F', '/', rawurlencode(rawurldecode($urlpath)));
  }


  // Load pre-existing mappings and clean them
  function load_mappings() {
    // Here is the list of mappings and the type of WordPress content they can point to
    $mapping_list = array(
        array('name' => 'user'      , 'types' => array('user')                                              )
      , array('name' => 'news'      , 'types' => array('post')                                              )
      , array('name' => 'category'  , 'types' => array('category')                                          )
      , array('name' => 'page'      , 'types' => array('page')                                              )
      , array('name' => 'comment'   , 'types' => array('comment')                                           )
      , array('name' => 'forum'     , 'types' => array(bbp_get_forum_post_type())                           )
      , array('name' => 'forum_post', 'types' => array(bbp_get_reply_post_type(), bbp_get_topic_post_type()))
      , array('name' => 'image'     , 'types' => array('attachment')                                        )
      );

    // List of content types that are not based on posts
    $non_post_types = array('category', 'comment', 'user');

    // Load pre-existing mappings
    foreach ($mapping_list as $map_data) {
      $map_name = $map_data['name'].MAPPING_SUFFIX;
      $option_name = OPTION_PREFIX.$map_name;
      if (get_option($option_name)) {
        $this->$map_name = get_option($option_name);
      } else {
        $this->$map_name = array();
      }
    }

    // Purge existing mapping entries which have invalid content destination
    foreach ($mapping_list as $map_data) {
      $allowed_types = $map_data['types'];
      if (sizeof(array_intersect($allowed_types, $non_post_types)) == 0) {
        $map_name = $map_data['name'].MAPPING_SUFFIX;
        $cleaned_map = array();
        foreach ($this->$map_name as $source => $post_id) {
          if (in_array(get_post_type($post_id), $allowed_types)) {
            $cleaned_map[$source] = $post_id;
          }
        }
        $this->$map_name = $cleaned_map;
        e107_Redirector::update_mapping($map_name, $this->$map_name);
      }
    }
  }


  // Update content mapping
  function update_mapping($name, $data) {
    $option_name = OPTION_PREFIX.$name;
    if (sizeof($data) == 0) {
      delete_option($option_name);
    } else {
      if (!get_option($option_name))
        add_option($option_name);
      update_option($option_name, $data);
    }
  }


  // Parse an e107 URL and return its new destination according the data found in the mappings
  function translate_url($url) {
    // Associate each mapping with their related regexp
    $redirect_rules = array(
      array( 'type'    => 'post'
           , 'mapping' => $this->news_mapping
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
           , 'mapping' => $this->category_mapping
           , 'rules'   => array( '/^.*\/news\.php(?:%3F|\?)cat\.(\d+).*$/i'
                                   # /news.php?cat.3
                               )
           ),
      array( 'type'    => 'post'
           , 'mapping' => $this->page_mapping
           , 'rules'   => array( '/^.*\/page\.php(?:%3F|\?)(\d+).*$/i'
                                   # /page.php?16
                                   # /page.php?16&res=1680x1050
                                   # /page.php%3F16
                               )
           ),
      array( 'type'    => 'comment'
           , 'mapping' => $this->comment_mapping
           , 'rules'   => array( # XXX Looks like there is no direct link to comments in e107
                               )
           ),
      array( 'type'    => 'user'
           , 'mapping' => $this->user_mapping
           , 'rules'   => array( '/^.*\/user\.php(?:%3F|\?)id\.(\d+).*$/i'
                                   # /user.php?id.29
                               , '/^.*\/userposts\.php(?:%3F|\?).*\.comments\.(\d+).*$/i'
                                   # /userposts.php?0.comments.29
                               )
           ),
      array( 'type'    => 'forum'
           , 'mapping' => $this->forum_mapping
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
           , 'mapping' => $this->forum_post_mapping
           , 'rules'   => array( '/^.*\/forum_viewtopic\.php(?:%3F|\?).*#post_(\d+).*$/i'
                                   # /forum_viewtopic.php?12301.0#post_19026
                                   # /forum_viewtopic.php?12301.100#post_19026
                               , '/^.*\/forum_viewtopic\.php(?:%3F|\?)(\d+).*$/i'
                                   # /forum_viewtopic.php?19026
                                   # /forum_viewtopic.php?19026.post
                               )
           ),
      array( 'type'    => 'forum_thread_last_page'
           , 'mapping' => $this->forum_post_mapping
           , 'rules'   => array( '/^.*\/forum_viewtopic\.php(?:%3F|\?)(\d+)\.last.*$/i'
                                   # /forum_viewtopic.php?19026.last
                               )
           ),
      array( 'type'    => 'forum_user'
           , 'mapping' => $this->user_mapping
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
          if (preg_match($regexp, $url, $matches)) {
            if (array_key_exists($matches[1], $mapping)) {
              $content_id = $mapping[$matches[1]];
              switch ($ctype) {
                case 'comment':
                  return get_comment_link($content_id);
                case 'category':
                  return get_category_link($content_id);
                case 'user':
                  return get_author_posts_url($content_id);
                case 'forum':
                  return bbp_get_forum_permalink($content_id);
                case 'forum_thread_last_page':
                  $content_id = bbp_topic_last_reply_id($content_id);
                case 'forum_post':
                  if (bbp_is_topic($content_id))
                    return bbp_get_topic_permalink($content_id);
                  else
                    return bbp_get_reply_permalink($content_id);
                case 'forum_user':
                  return bbp_get_user_profile_url($content_id);
                default:
                  return get_permalink($content_id);
              }
            }
          }
        }
      }
    }

    // Redirect feeds as explained there: http://kevin.deldycke.com/2007/05/feedburner-and-e107-integration/
    if (preg_match('/^.*\/e107_plugins\/(?:rss_menu\/|forum\/e_)rss\.php(?:%3F|\?)?(.*)$/i', $url, $matches)) {
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
        return get_bloginfo($wordpress_feed);
      }
    }

    // Normalize image URLs in the mapping: strip domains and unencode characters
    $image_regexps = array();
    foreach ($this->image_mapping as $orig_url => $attachment_id) {
      $img_path = $orig_url;
      // Add a dummy domain to relative and absolute URLs to let parse_url work
      if (!preg_match('/^https?:\/\//i', $img_path))
        $img_path = "http://example.com/".$img_path;
      $img_path = parse_url($img_path, PHP_URL_PATH);
      $img_path = trim($img_path, '/');
      // Build up the matching regular expression
      $img_regexp = $this->normalize_urlpath($img_path);
      $img_regexp = str_replace('/', '\/', $img_regexp);
      $img_regexp = str_replace('.', '\.', $img_regexp);
      $img_regexp = '/^.*'.$img_regexp.'.*$/i';
      $image_regexps[$img_regexp] = $attachment_id;
    }
    // Redirect images
    foreach ($image_regexps as $regexp => $attachment_id) {
      $normalized_url = $this->normalize_urlpath($url);
      if (preg_match($regexp, $normalized_url)) {
        $image_data = wp_get_attachment_image_src($attachment_id, $size='full');
        return $image_data[0];
      }
    }

    // Generic redirects and catch-alls

    // Redirect to the WordPress home page
    if (preg_match('/^.*\/news\.php.*$/i', $url))
      return get_option('siteurl');

    // Redirect to bbPress home page
    elseif (preg_match('/^.*\/forum(.*).php.*$/i', $url))
      return get_option('siteurl').'/'.get_option('_bbp_root_slug');

    // Redirects to forum stats
#    elseif (preg_match('/^.*\/forum_stats\.php.*$/i', $url))
#      return XXX;

    // Redirects to most active threads of all time
#    elseif (preg_match('/^.*\/top\.php(?:%3F|\?)0\.active.*$/i', $url))
#      return XXX;

      // Redirects to ???
#    elseif (preg_match('/^.*\/top\.php(?:%3F|\?)0\.top\.forum\.10.*$/i', $url))
#      return XXX;

    return False;
  }


  function redirect() {
    // Requested URL
    $requested = $_SERVER['REQUEST_URI'];
    // Final destination
    $new_destination = $this->translate_url($requested);
    if (!empty($new_destination))
      wp_redirect($new_destination, $status = 301);
    // Do nothing: let WordPress do its job (and probably show user a 404 error ;) )
  }


}


$e107_Redirector = new e107_Redirector();
