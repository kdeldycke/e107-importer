=== e107 Importer ===
Contributors: Coolkevman
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=XEXREDEHXSQUJ
Tags: importer, e107, cms, migration, bbPress
Requires at least: 3.1
Tested up to: 3.2
Stable tag: 1.4
License: GPLv2

e107 import plugin for WordPress.

== Description ==

This plugin allows you to extract the most important content and data from an e107 CMS instance and import them into your WordPress blog.

**Features**:

* Import news (both body and extended parts).
* Import news categories.
* Import custom pages (and take care of their private / public visibility).
* Import comments (both from news and custom pages).
* Import forums and threads to bbPress.
* Import images embedded in HTML as attachments.
* Let you choose which kind of images you want to upload to WordPress (external or not).
* Import preferences (site name, description, ...).
* Import new users and their profile (or update existing users).
* Send new credentials to users by mail.
* Try to map users to an appropriate role.
* Convert embedded BBCode to plain HTML.
* Clean-up HTML to align it with what WordPress produce by default.
* Redirect old e107 news, pages, users, forums, threads and feeds URLs to new WordPress content via an integrated plugin (for SEO).
* Replace old e107 URLs in content by new WordPress permalinks.

This tool was tested with:

* [e107 0.7.25](http://e107.org/news.php?item.880),
* [WordPress 3.2-RC3](http://wordpress.org/news/2011/06/wordpress-3-1-4/) and
* [bbPress 2.0-beta-3b](http://bbpress.org/blog/2011/06/bbpress-2-0-beta-3/)

If you have any older versions, please upgrade first.

== Installation ==

1. Upload the `e107-importer` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to the Tools -> Import screen, Click on e107

== Frequently Asked Questions ==

= What is the status of this plugin ? =

This plugin is **not in activate development**.

As I succeeded in moving to WordPress all my old e107 sites, I have no longer interest of maintaining this plugin. But I'll still integrate code other developers are willing to contribute.

= How can I know the import process finished well ? =

The only way you can tell is by having the "Finished !" / "Have fun !" message at the end of the import report. If not, it means the import process didn't had enough time to finish or encountered an error.

= Why the import process failed, or did not end well ? =

While importing content from e107, you may encounter one of the following error:

* *Internal Server Error*
* *MySQL has gone away*
* *PHP Fatal error: Maximum execution time exceeded*
* or not at all ([like the issue described here](http://github.com/kdeldycke/e107-importer/issues/5))

These means that the script has failed one way or another.

Generally, this is due to constraints set by your hosting provider, limiting the execution time of PHP scripts. This issue affect all scripts consuming lots of CPU and memory resources, like all import scripts. The timeout can come from MySQL, Apache or PHP.

The issue and [solutions are detailed in WordPress FAQ](http://codex.wordpress.org/FAQ_Working_with_WordPress#How_do_I_Import_a_WordPress_WXR_file_when_it_says_it_is_too_large_to_import.3F), please read this article before complaining to me.

= How long this plugin takes to import content ? =

Importing big forums takes a lot of time. For example, on my 4-cores 1.5GHz laptop, it takes more than an hour to import a forum with 18000+ replies. That's expected as I didn't designed this plugin for performances: it doesn't make sense to spend time working on performance for a plugin you'll only use once in the life of your WordPress site.

= Can I import content step by step ? =

Yes, you can. I designed this plugin to let you have the opportunity to import one kind of content at a time. So you should be able to import news first, then re-run the importer process to only import pages, then do it again for forums and so on...

= Why accents in my imported content are replaced by strange characters ? =

Looks like you have some kind of Unicode transcoding errors. Before running e107 Importer, your e107 site must be fully encoded in UTF-8. If it's not the case, please have a look at the [*Upgrading database content to UTF-8*](http://wiki.e107.org/?title=Upgrading_database_content_to_UTF8) article on e107 wiki.

= Can you add import of e107 forums to BuddyPress and bbPress 1.x ? =

This plugin currently import forums to bbPress. But [the brand new 2.x plugin version](http://wordpress.org/extend/plugins/bbpress/), not the legacy standalone 1.x version. As for [BuddyPress](http://buddypress.org/) forums, they are [planed to be replaced](http://bbpress.org/blog/2011/05/bbpress-2-0-beta-1/) by a future version of the new 2.x bbPress.

So as you can see, there is no need to add specific support of the these forums. You just have to be patient.

= Why profanities show up in imported content ? =

This plugin ignore the configuration of the profanity filter from e107. If you want to hide words, it should be done by a dedicated WordPress plug-in. As [suggested by Jon Freger](http://kevin.deldycke.com/2006/11/wordpress-to-e107-v06-better-content-rendering-and-extended-news-support/#comment-2937), you can use the [WebPurify plugin](http://wordpress.org/extend/plugins/webpurifytextreplace).

= Why links generated by e107's Linkwords plugin are not preserved ? =

This plugin disable all extra HTML rendering hooks added by e107 plugins. Which means Linkwords plugin will be ignored while rendering imported content. So as for profanities (see above), you have to use a third-party WordPress plugin to apply Linkwords-like transformations to your imported content.

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

1. e107 Importer options. (e107-importer 1.3)

== Tested with... ==

Here is a list of e107 and WordPress versions I tested my plugin with:

* e107-importer 1.4 : e107 0.7.25 / WordPress 3.2-RC3 / bbPress 2.0-beta-3b
* e107-importer 1.3 : e107 0.7.25 / WordPress 3.1.2 / bbPress plugin SVN r3113
* e107-importer 1.2 : e107 0.7.25-rc1 / WordPress 3.1 / bbPress plugin SVN r2992
* e107-importer 1.1 : e107 0.7.24 / WordPress 3.1 / bbPress plugin SVN r2942
* e107-importer 1.0 : e107 0.7.24 / WordPress 3.1-RC3
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

= 1.4 =
* Declare this plugin as unmaintained.
* Based on the official bbPress 2.0-beta-3b release.
* Reuse already imported content.
* Fix BBCode's quote tag transformation by enhanced parser.

= 1.3 =
* Upgrade embedded e107 code with latest 0.7.25.
* Redirect imported images to attachments.
* Purge invalid mapping entries on import.
* Replace old e107 URLs in content by new WordPress permalinks.
* Allow both imported and already-existing content to by updated with new permalinks.
* Let user specify the list of e107 forums to import.
* Phased imports should work without major problems.

= 1.2 =
* Upgrade e107 code to match latest 0.7.25-rc1.
* Fix variable bleeding when importing items in batches.
* Add a new way of handling e107 extended news using WordPress' excerpts.
* Parse BBCode and replace e107 constants in news excerpt.
* Use internal WordPress library (kses) to parse HTML in the image upload step.
* Do not upload the same images more than once.
* Add a new enhanced BBCode parser on top of the one from e107. Make it the default parser.
* Each time we alter the original imported content, we create a post revision.

= 1.1 =
* Add import of forums and threads to bbPress WordPress plugin.
* Parse BBCode and e107 constants in forums and thread.
* Add forums and threads redirections.
* Make e107 user import optional. This needs you to set a pre-existing WordPress user that will take ownership of all imported content.
* Parse BBCode in titles too.
* Import images embedded in comments and forum threads.
* Description update of existing users is no longer destructive.
* Add an entry in the FAQ regarding script ending prematurely.
* Disable all extra HTML rendering hooks like the one coming from e107 linkwords plugin.
* Allow news and pages import to be skipped.
* Add missing news category redirects.
* Minimal requirement set to WordPress 3.1.
* Some pages are not tied to a user. In this case, default to the current user.

= 1.0 =
* Upgrade e107 code from e107 v0.7.24.
* Minimal requirement set to WordPress 3.0.0.
* Use new WordPress importer framework.
* Add an e107 to WordPress 301 redirector plugin (support news, pages, users and feeds).
* Disable the URL rewriting feature introduced in v0.9.
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
= 1.4 =
Based on official bbPress 2.0-beta-1 release.

= 1.3 =
Upgraded against e107 0.7.25. Replace old e107 URLs by permalinks in content. Allow phased import.

= 1.2 =
Upgraded against e107 0.7.25-rc1. Add new enhanced BBCode parser.

= 1.1 =
Add import of forums and threads. User, news and pages import now optional.

= 1.0 =
First release compatible with the WordPress 3.x series.
