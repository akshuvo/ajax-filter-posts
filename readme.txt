=== Post Grid with Ajax Filter ===
Contributors: mdshuvo, addonmaster
Tags: post grid, grid, post type grid, pagination, ajax pagination, grid display, filter, filtering, grid, layout, post, post filter, post layout, taxonomy, taxonomy filter,ajax grid, displaypost gridpost, type grid, wp post frid, ajax post filter, filter post ajax, ajaxify, mixitup, isotop, category filter, filter without reload, ajax filter, ajax plugin
Tested up to: 5.6.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Post Grid with Ajax Filter plugin is a simple WordPress plugin that helps you filter your post by category terms with Ajax. Ajax post grid will help you Load posts with grid layout and you can also filter by post category.

== Description ==
Post Grid with Ajax Filter plugin is a simple WordPress plugin that helps you filter your post by category terms with Ajax. Ajax post grid will help you Load posts with grid layout and you can also filter by post category.

Just use this shortcode anywhere. Below you can see all available shortcodes.

### Post Grid with Ajax Filter by  [AddonMaster.com](https://addonmaster.com)

### Shortcodes
* Default Shortcode
<pre>[am_post_grid]</pre>

* Control for Show or Hide the filter
Options: yes,no
Default: yes
<pre>[am_post_grid show_filter="no"]</pre>

* Control Number of Posts Per Page
Options: Integers, -1 for all posts
Default: WordPress Default
<pre>[am_post_grid posts_per_page="6"]</pre>

* Post pagination
Options: yes,no
Default: "no"
<pre>[am_post_grid posts_per_page="6" paginate="yes"]</pre>

* Show/Hide "All" Button before filter
Options: yes,no
Default: "yes"
<pre>[am_post_grid btn_all="yes"]</pre>

* Show/Hide Specific Category Terms
Options: 1,2,3,4
Default: ""
<pre>[am_post_grid cat="100,101,103"]</pre>
or
<pre>[am_post_grid terms="100,101,103"]</pre>

* Hide/Show Empty Category Terms
Options: true, false
Default: "true"
<pre>[am_post_grid hide_empty="false"]</pre>

* Post Order
Options: ASC, DESC
Default: "DESC"
<pre>[am_post_grid order="DESC"]</pre>

* Post Orderby
Default: "title"
<pre>[am_post_grid orderby="title"]</pre>

Here is the full documentation for post order/orderby: https://developer.wordpress.org/reference/classes/wp_query/#order-orderby-parameters


###Features
* Shortcodes for showing anywhere
* Pre-Build Layout
* Grid Layout
* Ajax Post Grid
* Category Filter with ajax
* Controlling Options
* Post pagination
* Show/Hide Specific category terms

== Installation ==
1. Upload "ajax-filter-posts.zip\" to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.

== Frequently Asked Questions ==
= How to use it? =
use this shortcode: [am_post_grid]

= Why the plugin is not working? =
Please post details on support forum.

= I need help with custom feature? =
email me at addonmasterwp@gmail.com


== Screenshots ==
1. Frontend Preview
2. Filter Preview
3. Pagination Preview

== Changelog ==

= 2.2.1 =
* March 8, 2021
Added: Order Parameter
Added: Orderby Parameter

= 2.2.0 =
* Feb 23, 2021
WordPress 5.6.2 Compatible
Added: Show/Hide Specific category terms
Added: Hide/Show Empty Category Terms


= 2.1.0 =
* Feb 3, 2021
WordPress 5.6 Compatible

= 2.0.4 =
* March 19, 2020
Issue Fixed: Draft posts are displayed

= 2.0.2 =
* March 9, 2020
Ajax pagination
CSS Improvement
Ajax Improvement
Speed Improvement

= 2.0.0 =
* Jan 22, 2020
Multiple grid support
Ajax Improvement
Speed Improvement
Shortcode Extended :
	posts_per_page - for controlling number of posts
	btn_all - Show/Hide "All" Button on filter
	show_filter - Show/Hide the filter

= 1.0.4 =
* Jan 21, 2020
Multiple grid support
Ajax Improvement
Speed Improvement
New Design Layout Added

= 1.0.4 =
* Initial release.
