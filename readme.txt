=== Plugin Name ===
Contributors: kevinB
Donate link: http://agapetry.net/news/introducing-role-scoper/#role-scoper-download
Tags: restrict, access, cms, members, user, groups, admin, categories, pages, posts
Requires at least: 2.5
Tested up to: 2.7.1
Stable Tag: 1.0.3.1

CMS-like permissions for reading and editing. Content-specific restrictions and roles supplement/override WordPress roles. User groups optional.

== Description ==
Role Scoper is a comprehensive enrichment for access control in WordPress, giving you CMS-like control of permissions. Assign reading, editing or administration roles to users or groups on a page-specific, category-specific or other content-specific basis.

= Existing WordPress roles can be: =
* supplemented with content-specific role assignment
* disregarded if the role is restricted for the category or page/post

Scoped role requirements and assignments are reflected in every aspect of the WordPress interface, from front end content and navigation to administrative post and comment totals. Content administrators control who can view/edit/administer specified content, and what content anonymous users see.

= Partial Feature List =
* Control Read and/or Edit access
* Optionally, assign roles to User Groups
* Basic Usage is via tabs in Post/Page Edit Form, no further configuration required
* Customize access for any number of Categories, Posts or Pages
* Restrictions and Roles can be inherited by subcategories / subpages or as defaults
* Customizable Hidden Content Teaser (optional)
* Denial of direct file URL requests if the user can't read a post/page which contains the attachment
* RSS Filtering: select content/excerpt/name only, http authentication option
* Pending Revisions allow Contributors to suggest changes to a currently published post/page
* Bulk Editing of Restrictions and Roles
* Type-specific role definitions allow you to supplement user capabilities without redefining WP roles
* Extensive options to specify which portions of your site are affected
* Most Bugs and Plugin Compatibility issues addressed promptly following your <a href="http://agapetry.net/forum/">support forum</a> submission

Note: For WP 2.2 and 2.3, use <a href="http://agapetry.net/downloads/role-scoper_legacy">Role Scoper 0.9</a>

= Template Functions =
Theme code can utilize the is_restricted_rs() and is_teaser_rs() functions to customize front-end styling.

= Plugin API =
Other plugin and core developers will be interested in the underlying users_who_can function, made possible by a new roles storage schema.  The abstract data model and API support additional data sources, object types, capabilities and taxonomies (using term_taxonomy or other custom schema). If your plugin uses the WordPress current_user_can function and supports filtering of its listing query, you can use Role Scoper’s API to define your data source, object types, taxonomies and scopeable roles. These will supplement any other assigned roles; there is no need to merge all capabilities into an all-inclusive role.

For more information, see the <a href="http://agapetry.net/downloads/RoleScoper_UsageGuide.htm">Usage Guide</a> or <a href="http://agapetry.net/forum/">Support Forum</a>.


== Installation ==
1. Upload `role-scoper_?.zip` to the `/wp-content/plugins/` directory
2. Extract `role-scoper_?.zip` into the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
