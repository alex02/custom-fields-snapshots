<?php
/**
 * Custom Fields Snapshots Exporter class
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
 */
class Exporter {
	/**
	 * The field processor instance.
	 *
	 * @var Field_Processor
	 */
	private $processor;

	/**
	 * Export field groups data.
	 *
	 * @param array $field_groups  Array of field group keys to export.
	 * @param array $post_types    Array of post types to export.
	 * @param array $post_ids      Array of post IDs to export, keyed by post type.
	 * @param bool  $export_options Whether to export options pages.
	 * @return array The exported field group data.
	 */
	public function export_field_groups( $field_groups, $post_types, $post_ids, $export_options ) {
		$export_data = array();

		foreach ( $field_groups as $group_key ) {
			$export_data[ $group_key ] = $this->export_field_group_data( $group_key, $post_types, $post_ids, $export_options );

			// Remove empty groups from export.
			if ( ! $this->has_data( $export_data[ $group_key ] ) ) {
				unset( $export_data[ $group_key ] );
			}
		}

		return $export_data;
	}

	/**
	 * Export data for a single field group.
	 *
	 * @param string $group_key      The field group key.
	 * @param array  $post_types     Array of post types to export.
	 * @param array  $post_ids       Array of post IDs to export, keyed by post type.
	 * @param bool   $export_options Whether to export options pages.
	 * @return array The exported field group data.
	 */
	private function export_field_group_data( $group_key, $post_types, $post_ids, $export_options ) {
		$field_group = acf_get_field_group( $group_key );

		if ( ! $field_group ) {
			return array();
		}

		$fields = acf_get_fields( $field_group );

		return $this->get_fields_data( $fields, $post_types, $post_ids, $export_options );
	}

	/**
	 * Get data for fields, including special fields like repeaters and flexible content.
	 *
	 * @param array $fields         The fields to get data for.
	 * @param array $post_types     Array of post types to export.
	 * @param array $post_ids       Array of post IDs to export, keyed by post type.
	 * @param bool  $export_options Whether to export options pages.
	 * @return array The field data.
	 */
	private function get_fields_data( $fields, $post_types, $post_ids, $export_options ) {
		$data = array();

		// Load the field processor class.
		require_once CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_DIR . 'includes/class-field-processor.php';

		$this->processor = new Field_Processor();

		foreach ( $fields as $field ) {
			$data[ $field['name'] ] = $this->get_field_data( $field, $post_types, $post_ids, $export_options );
		}

		return $data;
	}

	/**
	 * Get data for a single field.
	 *
	 * @param array $field          The field to get data for.
	 * @param array $post_types     Array of post types to export.
	 * @param array $post_ids       Array of post IDs to export, keyed by post type.
	 * @param bool  $export_options Whether to export options pages.
	 * @return array The field data.
	 */
	private function get_field_data( $field, $post_types, $post_ids, $export_options ) {
		$field_data = array();

		if ( true === $export_options ) {
			$value = get_field( $field['name'], 'option', $this->processor->maybe_format_value( $field ) );

			if ( null !== $value ) {
				$field_data['option'] = apply_filters(
					sprintf( 'custom_fields_snapshots_export_option_%s_value', sanitize_key( $field['name'] ) ),
					$this->processor->process_field_value( $field, $value )
				);
			}
		}

		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				if ( ! empty( $post_ids[ $post_type ] ) ) {
					$query_args = array(
						'post_type'      => $post_type,
						'posts_per_page' => -1,
						'post_status'    => 'any',
					);

					if ( ! empty( $post_ids[ $post_type ] ) ) {
						$query_args['post__in'] = array_map( 'absint', $post_ids[ $post_type ] );
					}

					$posts = get_posts( $query_args );

					foreach ( $posts as $post ) {
						$value = get_field( $field['name'], $post->ID, $this->processor->maybe_format_value( $field ) );

						if ( null !== $value ) {
							$processed_value = apply_filters(
								sprintf( 'custom_fields_snapshots_export_field_%s_value', sanitize_key( $field['name'] ) ),
								$this->processor->process_field_value( $field, $value ),
								$post_type,
								$post->ID
							);

							if ( ! empty( $processed_value ) ) {
								$field_data[ $post_type ][ $post->ID ] = $processed_value;
							}
						}
					}

					// Remove the post type if it's empty.
					if ( empty( $field_data[ $post_type ] ) ) {
						unset( $field_data[ $post_type ] );
					}
				}
			}
		}

		return $field_data;
	}

	/**
	 * Check if the exported data is not empty.
	 *
	 * This function iterates through the data structure to determine
	 * if there are any non-empty fields.
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
