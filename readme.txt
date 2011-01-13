=== Plugin Name ===
Contributors: coolkevman
Tags: importer, e107, cms, migration
Requires at least: 3.1.0
Tested up to: 3.1.0
Stable tag: 1.0
License: GPLv2

Import posts and comments from an e107 CMS.

== Description ==

This plugin allows you to extract the most important content and data from an e107 CMS instance and import them into your WordPress blog.

Features:

* Import news and their categories,
* Handle extended part of news nicely,
* Import preferences (like site name and description),
* Import custom pages (and take care of their private / public visibility),
* Import images from news and pages,
* Import comments (both from news and custom pages),
* Convert embedded bbcode to plain HTML,
* Import users and their profile (or try to update the profile if user already exist),
* Try to map users to an appropriate role,
* Mails can be sent to each user to warn them about their new password.

== Installation ==

1. Upload the `e107-importer` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to the Tools -> Import screen, Click on e107

== Frequently Asked Questions ==

= What is the status of this plugin ? =

I plan to update this plugin in the future as I still have old e107 sites to migrate. As long as I have these migrations to do, I will not officially declare my plugin dead and
unmaintained. But this future can be quite distant as I currently have much higher priority work to do.

= Can I update the plugin ? =

That's nice from you to propose to update it. All contributions are welcome ! I'll be happy to apply all your patches in the original code to let anyone benefits your work.

= How can I contribute code ? =

Feel free to send me patches and code by mail. Or better yet, use GitHub fork/merge requests.

= Where can I find the source code ? =

Development of this plugin happened in a dedicated GitHub repository: http://github.com/kdeldycke/e107-importer

== Screenshots ==

1. News and categories imported. Custom pages import options displayed. (e107-importer 0.7)
2. User import completed. Plugin ask about news import options. (e107-importer 0.6)
3. News imported. Plugin show page import options. (e107-importer 0.5)

== Tested with... ==

Here is a list of e107 and WordPress versions I tested my plugin with:

* e107-importer 1.0 : e107 0.7.24 / WordPress 3.1.0
* e107-importer 0.9 : e107 0.7.11 / WordPress 2.3.2
* e107-importer 0.8 : e107 0.7.8  / WordPress 2.1.3
* e107-importer 0.7 : e107 0.7.8  / WordPress 2.1.2
* e107-importer 0.6 : e107 0.7.6  / WordPress 2.0.5
* e107-importer 0.5 : e107 0.7.5  / WordPress 2.0.5
* e107-importer 0.4 : e107 0.7.5  / WordPress 2.0.4
* e107-importer 0.3 : e107 0.7.5  / WordPress 2.0.4

== Changelog ==

= 1.0 =
* current revision

== Upgrade Notice ==

= 1.0 =
First release compatible with latest WordPress 3.x.
