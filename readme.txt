=== XWidgets ===
Contributors: bankofcanada, bboudreau, ncrawford, jairus
Tags: xwidgets, widget, sidebar, CMS, page, post, template, theme, content
Requires at least: 2.8.4
Tested up to: 3.1.3
Stable tag: 2.2

Gives you the ability to configure widgets on a per page basis.

== Description ==

**XWidgets lets you choose your widget layouts and settings for each individual page and post, instead of only having one layout applied to the whole site!**  For example, you could have a Twitter feed in your website's sidebar, but have your 'about me' page also display Flickr/Last.FM widgets in that page's sidebar, with a completely different widget layout from the rest of your site -- or you could have each page of your site pull in a different Google News feed via RSS -- or you could add a poll to the sidebar of a specific post, without changing any others.


This plugin was developed alongside the PBox plugin. Using the two together greatly simplifies CMS-like layouts and functionality.

= Features =

 * Choose widget settings from each indivdual page/post edit screen.
 * Pages can inherit widgets from their parent.
 * Pages/posts without widgets can use the global widgets (or if you prefer, none at all).
 
= Tips =

 * Use the [PBox](http://wordpress.org/extend/plugins/pbox/) plugin to add highly customizable content widgets to your page sidebars.
 * Visit [Smashing Magazine - Advanced Power Tips For WordPress Template Developers](http://www.smashingmagazine.com/2009/11/25/advanced-power-tips-for-wordpress-template-developers/) for ideas about Multiple Column Content Techniques.
 
= Future Plans =

 * Add widgets to taxonomies (Categories, Tags...)
 * Improve support for customized post types.

== Installation ==

1. Upload the xwidgets folder to the /wp-content/plugins/  directory.
2. Apply the minor core patch to /wp-includes/functions.php. See the patch file in the downloaded archive.
3. Activate XWidgets through the 'Plugins' menu in WordPress.

That's it! (Make sure you're using a theme with Widget support, of course.)

== Frequently Asked Questions ==

= How do I get pages/posts to use the global widgets? =

Make sure the page/post in question has no widgets set. Then, go to the XWidgets Settings page (via the Settings Menu) and Select Use global widgets - When no widgets on page.

= How does this work under the hood? =

We're using the postmeta and options tables to save the widget settings. We haven't added any tables or columns anywhere. We intercept calls to sidebars_widgets and widget option lookups, and tell them to use the settings stored in postmeta instead of the option table.


== Screenshots ==

 1. The XWidget Interface
 2. Access the XWidgets interface from the post/page list
 3. Inheriting from a parent page
 4. The Result
 5. Configure XWidgets link from edit page/post

== Changelog ==

= 2.2 =
 * Fixed a bug involving widgets being modified on post update

= 2.1 =
 * Minor bug fixes

= 2.0 =

 * First public release
 * Relying on the core Widgets.php interface
 * Started adding support for Custom Types and Taxonomies
 * Now Supporting WP 2.8 Widgets
