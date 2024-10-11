=== Custom Fields Snapshots ===
Contributors: alexgeorgiev
Tags: acf, custom fields, export, import, snapshot
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create backups of your Advanced Custom Fields data for easy migration, version control, and restoration.

== Description ==

Custom Fields Snapshots allow you to easily create backups of your Advanced Custom Fields (ACF) data by exporting selected field groups, post types, and options. These snapshots enable version control, make it easier to share setups with team members, assist with migrations between WordPress environments, and enable quick restoration of previous configurations.

**Important: This plugin requires Advanced Custom Fields (ACF) or ACF Pro to be installed and activated.**

Key Features:

* Export ACF field data for specific field groups, post types, and global options
* Import ACF field data from JSON snapshots
* Fully compatible with all ACF field types, including repeaters and flexible content
* Supports nested fields within complex field group structures
* Selective export: choose which field groups, post types, and individual posts to include.
* Rollbacks: Automatically revert changes if an import fails.
* Detailed logging for import processes.
* Developer-friendly: Extensive hook system for customization, including filters for data modification during export/import and actions for post-import processing.
* Multisite compatibility for seamless use across networked WordPress installations.

Custom Fields Snapshots simplifies ACF data management, offering a reliable solution for site migrations, staging environment setup, and data backups. This tool ensures a smooth workflow and provides peace of mind for developers and site administrators.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/custom-fields-snapshots` directory, or install the plugin through the WordPress plugins screen directly.
2. Ensure that Advanced Custom Fields (ACF) or ACF Pro is installed and activated.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Use the Field Snapshots screen to configure the plugin and manage your snapshots.

== How to Use ==

1. Go to the 'Field Snapshots' menu in your WordPress admin panel
2. Select the field groups, post types, options, and specific posts you want to export
3. Click 'Export Snapshot' to download a JSON file of your data
4. To import, upload the JSON file and click 'Import Snapshot'

== Frequently Asked Questions ==

= Does this plugin work with the free version of ACF? =

Yes, Custom Fields Snapshots works with both the free version of Advanced Custom Fields and ACF Pro.

= Can I export data from one site and import it to another? =

Absolutely! This is one of the main features of the plugin. Just make sure both sites have the same ACF field groups set up before importing.

= What kind of fields and data can I export? =

You can export any field types supported by ACF or ACF Pro, including repeater fields and flexible content, from post types and options.

= How does the rollback feature work? =

If an import fails, the plugin will automatically attempt to revert any changes made during the import process, ensuring your data remains intact.

= Is there a limit to the size of snapshots? =

There's no hard limit set by the plugin, but very large snapshots may be affected by PHP memory limits or max upload sizes set by your server.

= Is the plugin multisite compatible? =

Yes, the plugin supports WordPress Multisite installations, allowing you to manage ACF data across all network sites.

== Screenshots ==

1. Export interface - Select field groups, post types and/or global options to export
2. Import interface - Upload and import your snapshot file
3. Settings page - Configure plugin settings

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
This is the initial release of Custom Fields Snapshots.

== Support ==

For support, please use the support forum on WordPress.org or the [GitHub repository](https://github.com/alex02/custom-fields-snapshots).

== Future Plans ==

* Snapshot manager: Store snapshots on your server with an easy interface to manage, download, and restore snapshots.
* Scheduled snapshots: Automate the process of creating snapshots at regular intervals.
* WP-CLI support: Command-line interface for advanced users and automation.