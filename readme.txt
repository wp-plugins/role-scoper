=== Plugin Name ===
Contributors: kevinB
Donate link: http://agapetry.net/news/introducing-role-scoper/#role-scoper-download
Tags: restrict, access, permissions, cms, user, groups, members, admin, category, categories, pages, posts, page, Post, privacy, private, attachment, upload, files, rss, feed, feeds
Requires at least: 2.5
Tested up to: 2.8.4
Stable Tag: 1.0.8

CMS-like permissions for reading and editing. Content-specific restrictions and roles supplement/override WordPress roles. User groups optional.

== Description ==
Role Scoper is a comprehensive access control solution, giving you CMS-like control of reading and editing permissions.  Assign restrictions and roles to specific pages, posts or categories.

= How it works: =
Your WordPress core role definitions remain unchanged, and continue to function as default permissions.  User access is altered only as you expand it by assigning content-specific roles, or reduce it by setting content-specific restrictions.

Users of any level can be elevated to read or edit content of your choice.  Restricted content can be withheld from users lacking a content-specific role, regardless of their WP role.  Deactivation or removal of Role Scoper will return each user to their standard WordPress access (but all RS settings remain harmlessly in the database in case you change your mind).

Scoped role restrictions and assignments are reflected in every aspect of the WordPress interface, from front end content and navigation to administrative post and comment totals.  Although Role Scoper provides extreme flexibility and powerful bulk administration forms, basic usage is just a set of user checkboxes in the Post/Page Edit Form.

= Partial Feature List =
* Customize access for specific Pages, Posts, Categories
* Assign roles to User Groups (or directly to user)
* Control Read and/or Edit access
* Pages and Category listing match modified access
* Category post counts and tag cloud match modified access
* Page and category listings maintain tree structure even if some branches are hidden
* File Attachment filter blocks direct URL requests if user can't read corresponding post/page
* Customizable Hidden Content Teaser (or hide posts/pages completely) 
* Control which categories users can post to
* Control which pages users can associate sub-pages to
* Assign additional blog-wide, type-specific role(s) for any user
* Can elevate Subscribers to edit desired content (ensures safe failure mode)
* Inheritance of Restrictions and Roles to sub-categories / sub-pages
* Default Restrictions and Roles for new content
* Default Groups for new users
* Un-editable posts/pages are excluded from the editing list
* Specify element(s) in Edit Form to withhold from non-Editors
* RSS Feed Filter with HTTP authentication option
* Optimized to limit additional database queries
* Inline descriptive captions for each of the extensive options and settings
* Supports translation (contribute your own!)
* Pending Revisions allow Contributors to suggest changes to a currently published post/page

= Plugin API =
* Apply restrictions and roles for any custom taxonomy
* Abstract architecture and API allow other plugins to define their own role definitions for scoping
* Author provides some <a href="http://agapetry.net/category/plugins/role-scoper/role-scoper-extensions/">extensions to support integration with other plugins</a>

= Template Functions =
Theme code can utilize the is&#95;restricted&#95;rs() and is&#95;teaser&#95;rs() functions to customize front-end styling.

Other useful functions include users&#95;who&#95;can(), which accounts for all content-specific roles and restrictions.

For more information, see the <a href="http://agapetry.net/downloads/RoleScoper_UsageGuide.htm">Usage Guide</a> or <a href="http://agapetry.net/forum/">Support Forum</a>.

= Support =
* Most Bug Reports and Plugin Compatibility issues addressed promptly following your <a href="http://agapetry.net/forum/">support forum</a> submission.
* Author is available for professional consulting to meet your configuration, troubleshooting and customization needs.


== Installation ==
Role Scoper can be installed automatically via the Plugins tab in your blog administration panel.

= To install manually instead: =
1. Upload `role-scoper&#95;?.zip` to the `/wp-content/plugins/` directory
1. Extract `role-scoper&#95;?.zip` into the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

Note: For WP 2.2 and 2.3, use <a href="http://agapetry.net/downloads/role-scoper_legacy">Role Scoper 0.9</a>


== Frequently Asked Questions ==
<strong>How can I prevent low-level users from seeing the Roles/Restrictions menus and Edit boxes?</strong>
In your blog admin, navigate to Roles > Options.  In the "Content Maintenance" section, set the option "Roles and Restrictions can be set" to "by blog-wide Editors and Administrators" or "by Administrators only".  Click the Update button.


<strong>How does Role Scoper compare to <a href="http://sourceforge.net/projects/role-manager/">Role Manager</a> or <a href="http://wordpress.org/extend/plugins/capsman/">Capability Manager</a>?</strong>
Role Scoper's functionality is entirely different and complementary to RM and CM.  RM/CM do little more than alter WordPress' definition of the capabilities included in each role.  That's a valuable task, and in many cases will be all the role customization you need.  Since RM/CM modifications are stored in the main WordPress database, they remain even if RM/CM is deactivated.

Role Scoper is useful when you want to customize access to specific content, not just blog-wide.  It will work with the WP roles as a starting point, whether customized by RM/CM or not.  To see how Role Scoper's role definitions correlate to your WordPress roles, navigate to Roles > Options > RS Role Definitions in your blog admin.  Role Scoper's modifications remain only while it stays active.


<strong>Why are there so many options? Do I really need Role Scoper?</strong>
It depends on what you're trying to accomplish with your WordPress installation.  Role Scoper is designed to be functionally comprehensive and flexible.  Great pains were taken to maintain performance and user-friendliness.  Yet there are simpler permission plugins out there, particularly if you only care about read access.  Review Role Scoper's feature list and decide what's important to you.

<strong>Why doesn't Role Scoper limit direct access to files that I've uploaded via FTP?</strong>
Role Scoper only filters files in the WP uploads folder (or a subfolder).  The uploads folder must be a branch of the WordPress directory tree.  The files must be formally attached to a post / page via the WordPress uploader or via the RS Attachments Utility.

In your blog admin, navigate to Roles > Options > Features > Attachments > Attachments Utility.

<strong>Where does Role Scoper store its settings?  How can I completely remove it from my database?</strong>
Role Scoper creates and uses the following tables: groups&#95;rs, user2group&#95;rs, role&#95;scope&#95;rs, user2role2object&#95;rs.  All RS-specific options stored to the WordPress options table have an option name prefixed with "scoper&#95;".

Due to the potential damage incurred by accidental deletion, no automatic removal is currently available.  You can use a SQL editing tool such as phpMyAdmin to drop the tables and delete the scoper options.


== Screenshots ==

1. Admin menus
2. Role boxes in Edit Post Form
3. Role boxes in Edit Page Form
4. <a href="http://agapetry.net/demos/category_roles/index.html">View an html sample of the Category Roles bulk admin form</a>
5. <a href="http://agapetry.net/demos/rs-options_demo.htm">View an html sample of Role Scoper Options</a>
6. <a href="http://agapetry.net/news/introducing-role-scoper/">View more screenshots</a>


== Changelog ==
= 1.0.8 - 18 Aug 2009 =
* Feature : Option to prevent non-Administrators from assigning or viewing content-specific roles
* Feature : For front-end Page and Category listings, parent remapping behavior is now adjustable via RS Options
* Change : When a depth limit is specified for pages/categories listing, default to enforcing that limit based on actual depth prior to parent remap
* Bugfix : Fix compatibility with various custom child_of / depth / exclude / order combinations in pages, categories listing
* BugFix : Pages listing filter did not honor number, offset arguments
* BugFix : Terms (categories) listing filter did not apply custom ordering filter with WP 2.8
* Change : Work around WP bug when page / category listing is generated with child_of arg, but first element in result array is not a direct child
* Compat : Simple Section Navigation plugin displayed misplaced subpages in some situations
* Compat : My Category Order plugin
* Compat : Flutter/FreshPage plugin (disable custom menu indexing if plugin is active)
* Compat : Capability Manager plugin (automatically re-sync role defs on role creation / deletion)
* BugFix : When hiding other users' unattached uploads in Media Library, attachment count did not match 
* Change : Blog-wide Editors always see all unattached uploads in Media Library, regardless of option setting
* Doc : Replaced standalone change log and plugin compat documents with readme.txt sections


= 1.0.7 - 10 August 2009 =
* BugFix : With WP 2.8, new users were not assigned membership in WP Role metagroup until cache flush
* Change : Exclude role metagroups from groups column in users list (to reduce clutter)
* Feature : Option for whether non-administrators can see unattached uploads from other users
* Change : By default, non-administrators see only their own unattached uploads in Media Library
* Compat : Disable caching of pages, categories listing if QTranslate plugin is active
* Change : remap get&#95;pages exclude argument to exclude&#95;tree if called with depth=1
* Change : remap get&#95;terms exclude argument to exclude&#95;tree if called with depth=1
* Change : more descriptive error message when another plugin prevents RS initialization


= 1.0.6 - 6 August 2009 =
* BugFix : Failed to re-activate after WordPress auto-update
* BugFix : In WP-mu, Category Roles not inherited from parent on new category creation
* BugFix : Users with Category Manager role for a limited no. of cats could change Cat Parent to None
* BugFix : Users with Category Manager role for a limited no. of cats could create new top-level cats
* BugFix : Category Edit Form offered selection of a category as its own parent (though not stored)
* BugFix : In Bulk Roles / Restrictions form, "Collapse All" script hid some Categories / Pages inappropriately


= 1.0.5.1 - 5 August 2009 =
* Bump up version number to force wordpress.org to regenerate .zip.  The 1.0.5 zip was missing many files.


= 1.0.5 - 5 August 2009 =
* Change : Hidden Editing Elements now hidden securely on server side, not via CSS.
* Change : In RS Options, recaption "Hidden Editing Elements" as "Limited Editing Elements"
* Change : Updated sample IDs displayed on Role Scoper Options form for Hidden Editing Elements
* Change : Updated default IDs for Hidden Editing Elements
* Compat : Conflict with QTranslation plugin - translation of page titles, term names, bulk admin post titles
* Compat : Support SCOPER&#95;DISABLE&#95;MENU&#95;TWEAK definition for compat with Flutter plugin
* BugFix : New pages by non-Editors initially saved as Pending even if Publish was clicked
* BugFix : Administrator could not modify default category with WP 2.8
* BugFix : Default Groups could not be edited with WP 2.8
* BugFix : Attachments Utility (in RS Options) was not accessible under WP 2.8
* BugFix : In some configurations, fatal error when unavailable user&#95;can&#95;for&#95;any&#95;object() function called with administrator logged in
* BugFix : When editing group, could not remove last group administrator
* BugFix : Group roles were not displayed in group edit form if no members in group
* BugFix : Eliminated orphaned role deletion (no longer needed and deleted non-orphan group roles in some situations)
* BugFix : Object Roles, Blog Roles cache was not flushed following group membership change
* BugFix : On some server, the internal cache did not update following user profile edit
* BugFix : RS menu links were broken if role scoper activated within custom-named directory


= 1.0.4.1 - 28 June 2009 =
* BugFix : Roles, Restrictions menu links were broken for administrators (since 1.0.4)


= 1.0.4 - 26 June 2009 =
* Change : Deny implicit comment moderation rights to Authors if they lack moderate&#95;comments cap
* BugFix : In Edit Post form, non-editors could see / select other users as "author"
* BugFix : Option "role assignment requires blog-wide editor role" was only requiring blog-wide contributor role
* BugFix : Page Parent filtering was broken for Quick Edit
* BugFix : Category Restrictions were not inherited upon new category creation
* BugFix : Option "role assignment requires blog-wide editor role" did not suppress Roles, Restrictions sidebar menu
* BugFix : XML-RPC support (ScribeFire, WLW) was broken for non-administrators
* BugFix : User groups were unusable on DB servers that do not support default value on text columns
* BugFix : exclude&#95;tree argument was ineffective in get&#95;terms / wp&#95;list&#95;categories call
* BugFix : invalid Category / Object role edit links displayed in user profile for non-editors in some configurations 
* BugFix : Role Scoper Options inaccessable to administrator with WP 2.8.1
* Change : Moved option "Role administration requires a blog-wide Editor role" to main Options tab


= 1.0.3.4 - 8 May 2009 =
* BugFix : Fifth attempt to prevent re-activation failure following Role Scoper update via WP auto-updater
* BugFix : WP 2.8 Compat: Moved Restrictions, Roles menus back to familiar location unders Users menu


= 1.0.3.3 - 8 May 2009 =
* BugFix : Fourth attempt to prevent plugin activity during WordPress update operation, to prevent re-activation failure


= 1.0.3.2 - 8 May 2009 =
* BugFix : Third attempt to prevent plugin activity during WordPress update operation, to prevent re-activation failure


= 1.0.3.1 - 8 May 2009 =
* BugFix : Second attempt to prevent plugin activity during WordPress update operation, to prevent re-activation failure


= 1.0.3 - 8 May 2009 =
* BugFix : Prevent plugin activity during WordPress update operation, to prevent re-activation failure


= 1.0.2 - 7 May 2009 =
* BugFix : Template function is&#95;restricted&#95;rs / is&#95;exclusive&#95;rs was non-functional on home page (since rc9.9311)
* BugFix : With Attachments Filter enabled, attachments larger than 10MB fail to download on some installations
* BugFix : Fatal Error when viewing a single post entry after RS Options modified to disable front-end filtering
* BugFix : Auto-delete orphaned role assignments left in DB by previous versions following category / group deletion
* BugFix : After an empty group was deleted, its role assignments were left in the database
* BugFix : Event Calendar events without an associated post were not displayed without calendar refresh 
* BugFix : Post Restrictions and Post Roles did not display on PHP 4 servers
* BugFix : In Post/Page Edit Form, Author selection was inappropriately available to non-editors
* BugFix : Orphaned role assignments already stored to database will be autodeleted on RS version update
* BugFix : If the object type of a requested attachment parent cannot be determined, assume post
* BugFix : Teaser message displayed in header with some themes
* BugFix : http authentication prompt for RSS feeds with logged administrators on some installations
* BugFix : Hidden Editing Elements settings were not effective for unpublished posts/pages
* BugFix : If a memberless group was deleted, any assigned roles were left (orphaned) in the database
* Plugin :  Conflict with WP-Wall plugin caused non-listing or double-listing of wall comments
* Feature : Option to accept CSV entry for user role assignment
* Feature : Bottom-right submit button on bulk admin forms if SCOPER&#95;EXTRA&#95;SUBMIT&#95;BUTTON is defined


= 1.0.1 - 27 March 2009 =
* BugFix : In some situations, non-attachments were included in Media Library listing
* BugFix : Low level users could not edit uploads from Media Library based on a Post/Page/Category role assignment
* BugFix : Cannot set static front page with Role Scoper activated
* BugFix : Users with editing role via Page / Category assignment could not bulk-delete posts/pages
* BugFix : Post/Page Edit divs configured as Hidden Editing Elements were not hidden for draft posts/pages
* BugFix : After a group was deleted, its role assignments were left in the database
* BugFix : PHP warnings viewing users list with WP < 2.8
* Change : WP 2.7 users with hacked WP template.php user&#95;row code must define("scoper&#95;users&#95;custom&#95;column", "true");
* BugFix : Failed to return results for manual WP&#95;Query calls which include category exclusion argument
* BugFix : Role Scoper error messages were formatted with unreadable colors with WP 2.7
* BugFix : Conflict with ozhAdminMenus plugin - Page menus missing in some configurations
* BugFix : Conflict with WP-Wall plugin caused fatal error
* Feature : Options to hide User Groups, Scoped Roles from user profile


= 1.0.0 - 21 March 2009 =
* BugFix : In some installations, DB error for anonymous user front-end access (since rc9.9220)


== Other Notes ==

= Documentation =
* A slightly outdated <a href="http://agapetry.net/downloads/RoleScoper_UsageGuide.htm">Usage Guide</a> is available.  It includes both an overview of the permissions model and a How-To section with step by step directions.  Volunteer contributions to expand, revise or reformat this document are welcome.
* Role Scoper's menus, onscreen captions and inline descriptive footnotes <a href="http://weblogtoolscollection.com/archives/2007/08/27/localizing-a-wordpress-plugin-using-poedit/">can be translated using poEdit</a>.  I will gladly include any user-contributed languages!.

= General Plugin Compatibility Requirements =
* No other plugin or theme shall define function wp&#95;set&#95;current&#95;user() or function set&#95;current&#95;user().  A custom merge of the code may be possible in some situations.
* No other plugin or theme shall make an include or require call to force early execution of the file pluggable.php (for the reason listed above).

= Specific Plugin Compatibility Issues =
* WP Super Cache : set WPSC option to disable caching for logged users (unless you only use Role Scoper to customize editing access).
* QTranslate : use Role Scoper 1.0.7 or later, which disables caching of pages, terms listing.  To enable caching, change QTranslate get&#95;pages and get&#95;terms filter priority to 2 or higher, then add the following line to wp-config.php: `define('SCOPER_QTRANSLATE_COMPAT', true);`
* Get Recent Comments : not compatible due to direct database query. Use WP Recent Comments widget instead.
* Maintenance Mode : not compatible due to forced early login check.  To resolve conflict, disable front-end access by administrators during maintenance. Comment out the following line in maintenance-mode.php: `&& !mw&#95;current&#95;user&#95;can&#95;access&#95;on&#95;maintenance()`


**Attachment Filtering**

Read access to uploaded file attachments is normally filtered to match post/page access.

To disable this attachment filtering, copy the following line to wp-config.php:
    `define('DISABLE&#95;ATTACHMENT&#95;FILTERING', true);`

If manually disabling attachment filtering on an Apache server, remove the following line from the .htaccess file in your WP folder: 
    `RewriteRule ^(.*)wp-content/uploads/(.*) /YOUR&#95;WP&#95;FOLDER/index.php?attachment=$2&scoper&#95;rewrite=1 [NC,L]`

To reinstate attachment filtering, remove the definition from wp-config.php and deactivate/reactivate Role Scoper.

To fail with a null response (no WP 404 screen, but still includes a 404 in response header), copy the folling line to wp-config.php: 
    `define ('SCOPER&#95;QUIET&#95;FILE&#95;404', true);`

Normally, files which are in the uploads directory but have no post/page attachment will not be blocked.  To block such files, copy the following line to wp-config.php: 
    `define('SCOPER&#95;BLOCK&#95;UNATTACHED&#95;UPLOADS', true);`


**Hidden Content Teaser**

The Hidden Content Teaser may be configured to display the first X characters of a post/page if no excerpt or more tag is available.

To specify the number of characters (default is 50), copy the following line to wp-config.php: 
    `define('SCOPER&#95;TEASER&#95;NUM&#95;CHARS', 100); // set to any number of your choice`