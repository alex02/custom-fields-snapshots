<?php
/**
 * Custom Fields Snapshots Exporter class
 *
 * @since 1.0.0
 *
 * @package CustomFieldsSnapshots
 */

namespace Custom_Fields_Snapshots;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Fields Snapshots Exporter Class
 *
 * @since 1.0.0
 */
class Exporter {
	/**
	 * The field processor instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Field_Processor
	 */
	private $processor;

	/**
	 * Exports data for specified field groups.
	 *
	 * This function exports data for the given field groups, including post types,
	 * specific posts, and options if specified.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field_groups Array of field group keys to export.
	 * @param array $exports      Export configuration array containing:
	 *                            'post_types' => Array of post types to export,
	 *                            'post_ids'   => Array of post IDs to export, keyed by post type,
	 *                            'options'    => Whether to export options pages (boolean).
	 * @return array The exported field group data.
	 */
	public function export_field_groups( $field_groups, $exports ) {
		$export_data = array();

		foreach ( $field_groups as $group_key ) {
			$export_data[ $group_key ] = $this->export_field_group_data( $group_key, $exports );

			// Remove empty groups from export.
			if ( ! $this->has_data( $export_data[ $group_key ] ) ) {
				unset( $export_data[ $group_key ] );
			}
		}

		return $export_data;
	}

	/**
	 * Exports data for a single field group.
	 *
	 * This function processes and exports data for a specific field group,
	 * including post types, specific posts, options, and user data if specified.
	 *
	 * @since 1.0.0
	 *
	 * @param string $group_key The field group key to export.
	 * @param array  $exports   Export configuration array containing:
	 *                          'post_types'  => Array of post types to export,
	 *                          'post_ids'    => Array of post IDs to export, keyed by post type,
	 *                          'options'     => Whether to export options pages (boolean),
	 *                          'users'       => Array with 'roles' and 'ids' for user data export.
	 * @return array The exported field group data.
	 */
	private function export_field_group_data( $group_key, $exports ) {
		$field_group = acf_get_field_group( $group_key );

		if ( ! $field_group ) {
			return array();
		}

		$fields = acf_get_fields( $field_group );

		return $this->get_fields_data( $fields, $exports );
	}

	/**
	 * Get data for fields, including special fields like repeaters and flexible content.
	 *
	 * This function retrieves data for specified fields across various contexts,
	 * including post types, specific posts, options, and user data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $fields  The fields to get data for.
	 * @param array $exports Export configuration array containing:
	 *                       'post_types' => Array of post types to export,
	 *                       'post_ids'   => Array of post IDs to export, keyed by post type,
	 *                       'options'    => Whether to export options pages (boolean),
	 *                       'users'      => Array with 'roles' and 'ids' for user data export.
	 * @return array The collected field data.
	 */
	private function get_fields_data( $fields, $exports ) {
		$data = array();

		// Load the field processor class.
		require_once CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_DIR . 'includes/class-field-processor.php';

		$this->processor = new Field_Processor();

		foreach ( $fields as $field ) {
			$data[ $field['name'] ] = $this->get_field_data( $field, $exports );
		}

		return $data;
	}

	/**
	 * Retrieves data for a single field across various contexts.
	 *
	 * This function collects field data from options, users, and specified post types.
	 * It processes the data through filters and ensures only non-empty values are included.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field   The field configuration array.
	 * @param array $exports Export configuration array containing:
	 *                       'options'    => (bool) Whether to export options,
	 *                       'users'      => array(
	 *                           'roles' => (array) User roles to export,
	 *                           'ids'   => (array) User IDs to export
	 *                       ),
	 *                       'post_types' => (array) Post types to export,
	 *                       'post_ids'   => (array) Post IDs to export, keyed by post type.
	 * @return array The collected field data, organized by context (options, users, post types).
	 */
	private function get_field_data( $field, $exports ) {
		$field_data = array(
			'options'    => array(),
			'users'      => array(),
			'post_types' => array(),
		);

		// Handle options.
		if ( $exports['options'] ) {
			$value = get_field( $field['name'], 'option', $this->processor->maybe_format_value( $field ) );

			if ( null !== $value ) {
				/**
				 * Filters the value of an option field before exporting.
				 *
				 * @since 1.0.0
				 *
				 * @param mixed $value The option field value.
				 * @return mixed The filtered option field value.
				 */
				$field_data['options'] = apply_filters(
					sprintf( 'custom_fields_snapshots_export_option_%s_value', sanitize_key( $field['name'] ) ),
					$this->processor->process_field_value( $field, $value )
				);

				if ( ! $this->processor->has_nested_field_value( $field_data['options'] ) ) {
					unset( $field_data['options'] );
				}
			}
		}

		// Handle post types.
		if ( ! empty( $exports['post_types'] ) ) {
			$post_type_data = $this->get_post_type_field_data( $field, $exports['post_types'], $exports['post_ids'] );

			if ( ! empty( $post_type_data ) ) {
				$field_data['post_types'] = $post_type_data;
			}
		}

		// Handle users.
		if ( ! empty( $exports['users']['roles'] ) || ! empty( $exports['users']['ids'] ) ) {
			$user_data = $this->get_user_field_data( $field, $exports['users'] );

			if ( ! empty( $user_data ) ) {
				$field_data['users'] = $user_data;
			}
		}

		// Remove any empty arrays.
		$field_data = array_filter(
			$field_data,
			function ( $value ) {
				return $this->processor->has_nested_field_value( $value );
			}
		);

		return $field_data;
	}

	/**
	 * Retrieves field data for specified post types and post IDs.
	 *
	 * This function fetches ACF field data for given post types and post IDs,
	 * processes the data, and applies relevant filters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field      The ACF field configuration array.
	 * @param array $post_types Array of post types to fetch data for.
	 * @param array $post_ids   Array of post IDs, keyed by post type.
	 * @return array Processed field data for each post, keyed by post type and post ID.
	 */
	private function get_post_type_field_data( $field, $post_types, $post_ids ) {
		$post_type_data = array();

		foreach ( $post_types as $post_type ) {
			if ( ! empty( $post_ids[ $post_type ] ) ) {
				$query_args = array(
					'post_type'      => $post_type,
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'post__in'       => array_map( 'absint', $post_ids[ $post_type ] ),
				);

				$posts = get_posts( $query_args );

				foreach ( $posts as $post ) {
					$value = get_field( $field['name'], $post->ID, $this->processor->maybe_format_value( $field ) );
					if ( null !== $value ) {
						if ( ! isset( $post_type_data[ $post_type ] ) ) {
							$post_type_data[ $post_type ] = array();
						}

						/**
						 * Filters the value of a post field before exporting.
						 *
						 * @since 1.0.0
						 *
						 * @param mixed  $value     The post field value.
						 * @param string $post_type The post type.
						 * @param int    $post_id   The post ID.
						 * @return mixed The filtered post field value.
						 */
						$post_type_data[ $post_type ][ $post->ID ] = apply_filters(
							sprintf( 'custom_fields_snapshots_export_field_%s_value', sanitize_key( $field['name'] ) ),
							$this->processor->process_field_value( $field, $value ),
							$post_type,
							$post->ID
						);

						if ( ! $this->processor->has_nested_field_value( $post_type_data[ $post_type ][ $post->ID ] ) ) {
							unset( $post_type_data[ $post_type ][ $post->ID ] );
						}
					}
				}
			}
		}

		return $post_type_data;
	}

	/**
	 * Retrieves field data for specified users based on roles and/or user IDs.
	 *
	 * This function fetches ACF field data for users, either by role, specific IDs,
	 * or both. It processes the data and applies relevant filters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field        The ACF field configuration array.
	 * @param array $user_exports Array containing 'roles' and/or 'ids' for user selection.
	 * @return array Processed field data for each user, keyed by user ID.
	 */
	private function get_user_field_data( $field, $user_exports ) {
		$user_data       = array();
		$user_query_args = array( 'fields' => 'ID' );

		if ( ! empty( $user_exports['roles'] ) ) {
			$user_query_args['role__in'] = $user_exports['roles'];
		}

		if ( ! empty( $user_exports['ids'] ) ) {
			if ( isset( $user_query_args['role__in'] ) ) {
				// Both roles and IDs are selected.
				$role_users = get_users( $user_query_args );
				$id_users   = get_users(
					array(
						'include' => $user_exports['ids'],
						'fields'  => 'ID',
					)
				);
				$users      = array_unique( array_merge( $role_users, $id_users ) );
			} else {
				$user_query_args['include'] = $user_exports['ids'];
				$users                      = get_users( $user_query_args );
			}
		} else {
			$users = get_users( $user_query_args );
		}

		foreach ( $users as $user_id ) {
			$value = get_field( $field['name'], 'user_' . $user_id, $this->processor->maybe_format_value( $field ) );

			if ( null !== $value ) {
				/**
				 * Filters the value of a user field before exporting.
				 *
				 * @since 1.1.0
				 *
				 * @param mixed $value   The user field value.
				 * @param int   $user_id The user ID.
				 * @return mixed The filtered user field value.
				 */
				$user_data[ $user_id ] = apply_filters(
					sprintf( 'custom_fields_snapshots_export_user_field_%s_value', sanitize_key( $field['name'] ) ),
					$this->processor->process_field_value( $field, $value ),
					$user_id
				);

				if ( ! $this->processor->has_nested_field_value( $user_data[ $user_id ] ) ) {
					unset( $user_data[ $user_id ] );
				}
			}
		}

		return $user_data;
	}

	/**
	 * Check if the exported data is not empty.
	 *
	 * This function iterates through the data structure to determine
	 * if there are any non-empty fields.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data The data to check.
	 * @return bool True if data is not empty, false otherwise.
	 */
	public function has_data( $data ) {
		if ( ! is_array( $data ) ) {
			return false;
		}

		foreach ( $data as $datum ) {
			if ( ! is_array( $datum ) ) {
				continue;
			}

			foreach ( $datum as $field ) {
				if ( ! empty( $field ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
