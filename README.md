Custom Fields Snapshots allow you to easily create backups of your Advanced Custom Fields (ACF) data by exporting post types, taxonomies, options, users, and comments. These snapshots enable version control, make it easier to share setups with team members, assist with migrations between WordPress environments, and allow quick restoration of previous configurations.

**This plugin requires Advanced Custom Fields (ACF) or ACF Pro to be installed and activated.**

## Features
* Export and import ACF field data for specific post types, taxonomies, options, users, and comments
* Fully compatible with all ACF field types, including repeaters, galleries, and flexible content
* Supports nested fields within complex field group structures
* Selective export: choose which field groups, post types, taxonomies, and individual posts, terms, users or user roles to export
* Rollbacks: automatically revert all changes if an import fails
* Detailed logging for import processes
* Developer-friendly: Extensive hook system for customization
* Multisite compatibility for seamless use across networked sites

Custom Fields Snapshots simplifies ACF data management, offering a reliable solution for site migrations, staging environment setup, and data backups. This tool ensures a smooth workflow and provides peace of mind for developers and site administrators.

## Installation

1. Navigate to **Tools > Custom Fields Snapshots** in your WordPress admin panel.
2. On the **Export** tab, select the field groups, post types, taxonomies, options, specific posts, terms, users, or user roles you want to export.
3. Click **Export Snapshot** to download a JSON file of your data.
4. To import, go to the **Import** tab, upload the JSON file, and click **Import Snapshot**.

## How to Use

1. Navigate to the 'Field Snapshots' menu in your WordPress admin panel.
2. Select the field groups, post types, options, specific posts, users, or user roles you want to export.
3. Click 'Export Snapshot' to download a JSON file of your data.
4. To import, upload the JSON file and click 'Import Snapshot'.

## Frequently Asked Questions

### Which ACF versions are compatible with the plugin?

Custom Fields Snapshots is compatible with both ACF Free and ACF Pro. While the plugin should work with versions prior to 5.0, as it relies on core ACF functions, it's recommended to use the latest version of ACF for optimal performance, security, and compatibility.

The plugin is regularly tested with the latest ACF versions to ensure continued compatibility. If you're using an older ACF version (prior to 5.0), the plugin should still function correctly with your setup.

If you encounter any compatibility issues, please don't hesitate to reach out for support.

### Can I export data from one site and import it to another?

Absolutely! This is one of the main features of the plugin. Just make sure both sites have the same ACF field groups set up before importing.

### What kind of fields and data can I export?

You can export any field types supported by ACF or ACF Pro, including repeater fields, galleries, and flexible content, from post types, taxonomies, options, users and comments.

### How does the rollback feature work?

If an import fails, the plugin will automatically attempt to revert any changes made during the import process, ensuring your data remains intact.

### Is there a limit to the size of snapshots?

There's no hard limit set by the plugin, but very large snapshots may be affected by PHP memory limits or max upload sizes set by your server.

### Is the plugin multisite compatible?

Yes, the plugin supports WordPress Multisite installations, allowing you to manage ACF data across all network sites.

### Will the plugin slow down my site? ###

No, the plugin is designed to be lightweight. It does not load any code on the front end of your site. In the admin area, it only loads the bare minimum code needed for functionality. Additional code is activated only when required, such as on the Export or Import pages or during the import process.

## Hooks

## custom_fields_snapshots_export_field_value

Filters the value of a field during the export.

### Description

This filter allows modification of field values during the export process. It can be used to customize or sanitize field values based on context or field name.

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$value` | mixed | The current value of the field. |
| `$context` | string | The context of the export. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'. |
| `$field` | array | The field configuration array. |
| `$context_data` | mixed | Additional context-specific data. |

### Usage

```php
add_filter( 'custom_fields_snapshots_export_field_value', 'modify_export_field_value', 10, 4 );

function modify_export_field_value( $value, $context, $field, $context_data ) {
	if ( 'user' === $context && 'my_prefixed_field' === $field['name'] ) {
		return 'prefix:' . $value;
	}

	return $value;
}
```

## custom_fields_snapshots_import_field_complete

Fires when a field import is successful during the import process.

### Description

This action is triggered after a field has been successfully imported. It provides information about the imported field, including its name, value, group key, and the context of the import. This can be useful for logging, notifications, or performing additional operations after a field is imported.

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$field` | string | The field configuration array. |
| `$value` | mixed | The value that was imported for the field. |
| `$group_key` | string | The key of the field group to which this field belongs. |
| `$context` | string | The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'. |

### Usage

```php
add_action( 'custom_fields_snapshots_import_field_complete', 'log_imported_field', 10, 4 );

function log_imported_field( $field, $value, $group_key, $context ) {
	error_log(
		sprintf(
			'Field "%s" imported with value "%s" in group "%s" for context "%s"',
			$field['name'],
			is_scalar( $value ) ? $value : wp_json_encode( $value ),
			$group_key,
			$context
		)
	);
}
```

## custom_fields_snapshots_import_field_failed

Fires when a field import fails during the import process.

### Description

This action is triggered when an attempt to import a field fails. It provides detailed information about the field, including the attempted value, the existing value, and the context of the import. This can be useful for logging, error handling, or notifying administrators about import failures.

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$field` | string | The field configuration array. |
| `$value` | mixed | The value that was attempted to be imported. |
| `$existing_value` | mixed | The current value of the field before the import attempt. |
| `$context` | string | The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'. |
| `$group_key` | string | The key of the field group to which this field belongs. |
| `$context_data` | array | Additional context-specific data. |

### Usage

```php
add_action( 'custom_fields_snapshots_import_field_failed', 'log_failed_field_import', 10, 6 );

function log_failed_field_import( $field, $value, $existing_value, $context, $group_key, $context_data ) {
	error_log(
		sprintf(
			'Failed to import field "%s" with value "%s". Existing value: "%s". Context: "%s", Group: "%s"',
			$field['name'],
			is_scalar( $value ) ? $value : json_encode( $value ),
			is_scalar( $existing_value ) ? $existing_value : wp_json_encode( $existing_value ),
			$context,
			$group_key
		)
	);
}
```

## custom_fields_snapshots_import_field_value

Filters the value of a field before importing in the import process.

### Description

This filter allows modification of field values before they are imported during the import process. It can be used to adjust, validate, or transform the data as needed based on various factors such as the field name, context, or existing values.

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$value` | mixed | The field value to be imported. |
| `$existing_value` | mixed | The existing value of the field in the database. |
| `$field` | string | The field configuration array. |
| `$context` | string | The context of the import. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'. |
| `$group_key` | string | The key of the field group to which this field belongs. |
| `$context_data` | array | Additional context-specific data. |

### Usage

```php
add_filter( 'custom_fields_snapshots_import_field_value', 'modify_import_field_value', 10, 6 );

function modify_import_field_value( $value, $existing_value, $field, $context, $group_key, $context_data ) {
	if ( 'taxonomy' === $context && 'my_numeric_field' === $field['name'] && absint( $value ) > 300 ) {
		return 300;
	}

	return $value;
}
```

## custom_fields_snapshots_export_args

Filters the arguments for retrieving data during the export process, e.g. for get_posts(), get_users(), get_terms().

### Description

This filter allows modification of the arguments passed to get_comments() when exporting comment data. It can be used to adjust which comments are included in the export.

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$args` | array | The current arguments for the export. |
| `$context` | string | The context of the export. Possible values are 'post', 'taxonomy', 'option', 'user', or 'comment'. |
| `$field` | array | The field configuration array. |
| `$context_data` | mixed | Additional context-specific data. |

### Usage

```php
add_filter( 'custom_fields_snapshots_export_args', 'my_custom_export_args_filter', 10, 4 );

function my_custom_export_args_filter( $args, $context, $field, $context_data ) {
	if ( 'comments' === $context ) {
		$args['number'] = 10; // Limit to 10 comments
	}

	if ( 'my_special_field' === $field['name'] ) {
		$args['meta_key']   = 'special_flag';
		$args['meta_value'] = 'yes';
	}

	return $args;
}
```

## custom_fields_snapshots_export_pre

Fires before exporting the field data in the export process.

### Description

This action is triggered just before the export process begins. It can be used for logging, performing pre-export operations, or modifying the export data.

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$field_groups` | array | The field groups to be exported. |
| `$exports` | array | The data types to export. |

### Usage

```php
add_action( 'custom_fields_snapshots_export_pre', 'log_export_start', 10, 2 );

function log_export_start( $field_groups, $exports ) {
    error_log( 'Export started: ' . wp_date( 'Y-m-d H:i:s' ) );
}
```

## custom_fields_snapshots_export_post

Fires after exporting the field data in the export process.

### Description

This action is triggered immediately after the export process completes. It can be used for logging, performing post-export operations, or processing the exported data.

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$field_groups` | array | The field groups that were exported. |
| `$exports` | array | The data types that were exported. |

### Usage

```php
add_action( 'custom_fields_snapshots_export_post', 'log_export_completion', 10, 2 );

function log_export_completion( $field_groups, $exports ) {
	error_log( 'Export completed: ' . wp_date( 'Y-m-d H:i:s' ) );
}
```

## custom_fields_snapshots_import_pre

Fires before importing the field data in the import process.

### Description

This action is triggered just before the import process begins. It provides access to the JSON data that will be imported, allowing for pre-import operations, validation, or modification of the import data.

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$json_data` | array | The JSON data to be imported. |

### Usage

```php
add_action( 'custom_fields_snapshots_import_pre', 'log_import_start', 10, 1 );

function log_import_start( $json_data ) {
	error_log( 'Import started: ' . wp_date( 'Y-m-d H:i:s' ) . ' with ' . count( $json_data ) . ' items.' );
}
```

## custom_fields_snapshots_import_complete

Fires after successfully importing the field data in the import process.

### Description

This action is triggered immediately after the import process completes successfully. It can be used for logging, performing post-import operations, or triggering other processes that depend on the import being finished.

### Parameters

This action does not pass any parameters.

### Usage

```php
add_action( 'custom_fields_snapshots_import_complete', 'log_import_completion' );

function log_import_completion() {
	error_log( 'Custom Fields Snapshots import completed successfully at: ' . wp_date( 'Y-m-d H:i:s' ) );
}
```
## custom_fields_snapshots_import_failed

Fires when the import process fails.

### Description

This action is triggered when the import process encounters an error and fails to complete successfully. It can be used for error logging, notifying administrators, or performing any necessary cleanup operations after a failed import.

### Parameters

This action does not pass any parameters.

### Usage

```php
add_action( 'custom_fields_snapshots_import_failed', 'log_import_failure' );

function log_import_failure() {
	error_log( 'Custom Fields Snapshots import failed at: ' . wp_date( 'Y-m-d H:i:s' ) );
}
```
## custom_fields_snapshots_render_export_users

Filters the arguments used to retrieve users for export in the Custom Fields Snapshots plugin.

## Description

The `custom_fields_snapshots_render_export_users` filter allows you to modify the arguments passed to `get_users()` when retrieving users for export. This can be used to customize which users are included in the export process and what user data is retrieved.

## Parameters

- `$args` (array) The arguments for `get_users()`.

## Usage

```php
add_filter( 'custom_fields_snapshots_render_export_users', 'my_custom_export_users', 10, 1 );

function my_custom_export_users( $args ) {
	$args['exclude'] = array(
		2, // Exclude user with ID 2
	);

	return $args;
}
```

## custom_fields_snapshots_render_export_user_roles

Filters the user roles to be exported in the export process.

### Description

This filter allows modification of the list of user roles that will be included in the export. It can be used to add, remove, or modify the roles that will be exported.

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$roles` | array | The user roles to be exported. |

### Usage

```php
add_filter( 'custom_fields_snapshots_render_export_user_roles', 'modify_export_user_roles', 10, 1 );

function modify_export_user_roles( $roles ) {
	if ( isset( $roles['contributor'] ) ) {
		unset( $roles['contributor'] );
	}

	return $roles;
}
```
## custom_fields_snapshots_export_exclude_post_types

Filters the list of post types to exclude from the export.

### Description

This filter allows modification of the list of post types that should be excluded from the export. It's particularly useful for excluding specific post types based on the export type.

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$post_types` | array | The post types to be excluded from export. |
| `$type` | string | The type of post types to render ('public' or 'private'). |

### Usage

```php
add_filter( 'custom_fields_snapshots_export_exclude_post_types', 'exclude_revisions_from_private_export', 10, 2 );

function exclude_revisions_from_private_export( $post_types, $type ) {
	if ( 'private' === $type ) {
		$post_types[] = 'revision';
	}

	return $post_types;
}
```
## custom_fields_snapshots_export_exclude_taxonomies

Filters the taxonomies to be excluded from export.

## Description

The `custom_fields_snapshots_export_exclude_taxonomies` filter allows you to modify the list of taxonomies that should be excluded from the export process. This can be useful for customizing which taxonomies are available for export based on the taxonomy type (public or private).

## Parameters

- `$excluded_taxonomies` (array) The default array of taxonomies to be excluded from export.
- `$type` (string) The type of taxonomies being processed. Possible values are 'public' or 'private'.

## Usage

```php
add_filter( 'custom_fields_snapshots_export_exclude_taxonomies', 'my_custom_exclude_taxonomies', 10, 2 );

function my_custom_exclude_taxonomies( $excluded_taxonomies, $type ) {
	if ( 'public' === $type ) {
		// Add 'category' to the list of excluded public taxonomies
		$excluded_taxonomies[] = 'category';
	} else {
		// Remove 'nav_menu' from the list of excluded private taxonomies
		$excluded_taxonomies = array_diff( $excluded_taxonomies, array( 'nav_menu' ) );
	}
	return $excluded_taxonomies;
}
```

## custom_fields_snapshots_field_type_format

Filters the field types that require value formatting.

## Description

The `custom_fields_snapshots_field_type_format` filter allows you to modify the list of ACF field types that require special value formatting during export or import operations.

## Parameters

- `$format_types` (array) The default array of field types that require value formatting.
- `$field` (array) The ACF field object being processed.

## Usage

```php
add_filter( 'custom_fields_snapshots_field_type_format', 'my_custom_field_type_format', 10, 2 );

function my_custom_field_type_format( $format_types, $field ) {
	// Add 'gallery' to the list of field types that require formatting
	$format_types[] = 'gallery';

	return $format_types;
}
```

## custom_fields_snapshots_render_inactive_field_groups

Filters whether to include inactive field groups in the `render_field_groups_selection()` function.

## Description

This filter allows you to control whether inactive field groups should be included when rendering the field groups selection. By default, inactive field groups are excluded.

## Parameters

- `$include_inactive` (bool) Whether to include inactive field groups. Default false.

## Usage

```php
add_filter( 'custom_fields_snapshots_render_inactive_field_groups', '__return_true' );
```
