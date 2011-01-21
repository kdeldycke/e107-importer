=== e107 Importer ===
Contributors: coolkevman
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=XEXREDEHXSQUJ
Tags: importer, e107, cms, migration
Requires at least: 3.0.0
Tested up to: 3.1-RC2
Stable tag: 1.0
License: GPLv2

e107 import plugin for WordPress.

== Description ==

This plugin allows you to extract the most important content and data from an e107 CMS instance and import them into your WordPress blog.

**Features**:

* Import news and their categories,
* Handle extended part of news nicely,
* Import custom pages (and take care of their private / public visibility),
* Import comments (both from news and custom pages),
* Import images from news and pages,
* Let you choose which kind of images you want to upload to WordPress (external or not),
* Import preferences (site name, description, ...),
* Convert embedded BBCode to plain HTML,
* Import users and their profile (or try to update the profile if user already exist),
* Try to map users to an appropriate role,
* Send mails to users to inform them about their new credentials,
* Redirect old e107 news, pages, users and feeds URLs to new WordPress content via an integrated plugin (for SEO).

This tool was tested with [e107 0.7.24](http://e107.org/news.php?item.877) and [WordPress 3.1-RC2](http://wordpress.org/news/2011/01/wordpress-3-1-release-candidate-2/). If you have older versions, please upgrade first.

== Installation ==

1. Upload the `e107-importer` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to the Tools -> Import screen, Click on e107

== Frequently Asked Questions ==

= Why accents in my imported content are replaced by strange characters ? =

Looks like you have some kind of Unicode transcoding errors. Before running e107 Importer, your e107 site must be fully encoded in UTF-8. If it's not the case, please have a look at the [*Upgrading database content to UTF-8*](http://wiki.e107.org/?title=Upgrading_database_content_to_UTF8) article on e107 wiki.

= Why profanities show up in imported content ? =

This plugin ignore the configuration of the profanity filter from e107. If you want to hide words, it should be done by a dedicated WordPress plug-in. As [suggested by Jon Freger](http://kevin.deldycke.com/2006/11/wordpress-to-e107-v06-better-content-rendering-and-extended-news-support/#comment-2937), you can use the [WebPurify plugin](http://www.webpurify.com/wp-plugin.php).

= What is the status of this plugin ? =

I plan to update this plugin in the future as I still have old e107 sites to migrate. As long as I have these migrations to do, I will not officially declare my plugin dead and unmaintained. But this future can be quite distant as I currently have much higher priority work to do.

= Can I give you money to fix my problem ? =

That's nice from you to propose a donation but quite frankly, money is not the kind of incentives that will push the development of my plugins. But code, bug reports and testing is the kind of contributions I'm looking for. In fact getting rid of my old e107 instances is the best motivator I have. But by popular demand, here is my [donation link](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=XEXREDEHXSQUJ) anyway...

= Where can I report bugs ? =

Bug reports and feature requests must be done via [GitHub's ticket system](http://github.com/kdeldycke/e107-importer/issues).

= Can I update the plugin ? =

That's nice from you to propose to update it. All contributions are welcome ! I'll be happy to apply all your patches in the original code to let anyone benefits your work. Even after I will declare this plugin dead.

= How can I contribute code ? =

Feel free to send me patches and code by mail. Or better yet, use GitHub fork/merge features.

= Where can I find the source code ? =

Development of this plugin happen in a [dedicated GitHub repository](http://github.com/kdeldycke/e107-importer). The latter is the official repository of this plugin. All developments are done there. This repository is the reference repository.

FYI, this plugin is [also hosted on WordPress plugins' Subversion](http://plugins.svn.wordpress.org/e107-importer), but this repository is just a copy of GitHub's. **No original development should be performed in the Subversion repository**: changes made there will be ignored and deleted if not mirrored in the GitHub repository.

== Screenshots ==

1. News and categories imported. Custom pages import options displayed. (e107-importer 0.7)
2. User import completed. Plugin ask about news import options. (e107-importer 0.6)
3. News imported. Plugin show page import options. (e107-importer 0.5)

== Tested with... ==

Here is a list of e107 and WordPress versions I tested my plugin with:

* e107-importer 1.0 : e107 0.7.24 / WordPress 3.1-RC2
* e107-importer 0.9 : e107 0.7.11 / WordPress 2.3.2
* e107-importer 0.8 : e107 0.7.8  / WordPress 2.1.3
* e107-importer 0.7 : e107 0.7.8  / WordPress 2.1.2
* e107-importer 0.6 : e107 0.7.6  / WordPress 2.0.5
* e107-importer 0.5 : e107 0.7.5  / WordPress 2.0.5
* e107-importer 0.4 : e107 0.7.5  / WordPress 2.0.4
* e107-importer 0.3 : e107 0.7.5  / WordPress 2.0.4

== Copyright notice ==

This plugin contain original code from the e107 project, licensed under the GPL.

== Changelog ==

= 1.0 =
* Upgrade e107 code from e107 v0.7.24.
* Minimal requirement set to WordPress 3.0.0.
* Use new WordPress importer framework.
* Add an e107 to WordPress 301 redirector plugin (support news, pages, users and feeds).
* Disable the URL rewrting feature introduced in v0.9.
* Make image import optional.
* Add an option to upload images from allowed domains only.
* Align naming conventions with other WordPress importer.
* Add a complete WordPress plugin hosting compatible readme file with full metadatas.
* Add screenshots.
* List all versions of e107 and WordPress I tested this plugin with.
* Add a PayPal donation link.
* Add a minimal FAQ.
* Add an overview of features in description.
* Update source code repository location.
* Remove patching of Kubrick theme to support comments on static pages.

= 0.9 =
* "One-click migration" instead of multiple step process (more user-friendly).
* Better error management (a must-have for precise bug reports).
* Replace all links to old content with permalinks (increased SEO).
* Better database management.
* Code cleaned up.

= 0.8 =
* Import images embedded in e107 news and custom pages.
* Import e107 site preferences.
* Better import of user profile data.
* An existing user on the blog can be detected and updated automatically.
* Fix the profanity filter bug.

= 0.7 =
* Import e107 news categories.
* Mails can be sent to each user to warn them about their new password.
* Static pages can be set as private.
* Simplify the import process.
* Some little UI improvements.

= 0.6 =
* Render content according user's preferences.
* Take care of extended news.

= 0.5 =
* Add import of static pages.

= 0.4 =
* Fix lots of bugs, especially due to non-escaped SQL queries.
* Import news comments and link them to users.

= 0.3 =
* Import all users and associate them with their posts.

= 0.2 =
* Add BBCode support to news content.

= 0.1 =
* First draft of e107 to WordPress importer.

== Upgrade Notice ==

= 1.0 =
First release compatible with the WordPress 3.x series.
