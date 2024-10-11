<?php
/**
 * Field_Processor class for handling ACF field data processing.
 *
 * This class provides methods to process various ACF field types
 * for export and import operations.
 *
 * @package Custom_Fields_Snapshots
 */

namespace Custom_Fields_Snapshots;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Fields Snapshots Field Processor Class
 */
class Field_Processor {

	/**
	 * Process field value based on field type.
	 *
	 * @param array $field The field configuration.
	 * @param mixed $value The field value.
	 * @return mixed Processed field value.
	 */
	public function process_field_value( $field, $value ) {
		switch ( $field['type'] ) {
			case 'group':
				return $this->process_group_data( $field, $value );
			case 'repeater':
				$value = $this->process_repeater_data( $field, $value );
				return ! empty( $value ) ? $value : null;
			case 'flexible_content':
				return $this->process_flexible_content_data( $field, $value );
			default:
				return $value;
		}
	}

	/**
	 * Determines if a field type requires value formatting.
	 *
	 * This function checks if the given ACF field type needs special formatting
	 * when processing its value. It returns true for complex field types like
	 * group, repeater, and flexible content, and false for all other types.
	 *
	 * @param string $field The ACF field object to check.
	 * @return bool True if the field type requires formatting, false otherwise.
	 */
	public function maybe_format_value( $field ) {
		if ( ! isset( $field['type'] ) ) {
			return false;
		}

		$format_types = apply_filters(
			'custom_fields_snapshots_field_type_format',
			array(
				'group',
				'repeater',
				'flexible_content',
			),
			$field
		);

		return in_array( $field['type'], $format_types, true );
	}

	/**
	 * Process repeater field data.
	 *
	 * @param array $field The field configuration.
	 * @param array $value The field value.
	 * @return array Processed repeater data.
	 */
	private function process_repeater_data( $field, $value ) {
		$processed_value = array();

		if ( is_array( $value ) ) {
			foreach ( $value as $row_index => $row ) {
				$processed_row = array();

				foreach ( $field['sub_fields'] as $sub_field ) {
					$sub_field_name = $sub_field['name'];

					if ( isset( $row[ $sub_field_name ] ) ) {
						$processed_row[ $sub_field_name ] = $this->process_field_value( $sub_field, $row[ $sub_field_name ] );
					}
				}

				$processed_value[ $row_index ] = $processed_row;
			}
		}

		return $processed_value;
	}

	/**
	 * Process group field data.
	 *
	 * @param array $field The field configuration.
	 * @param array $value The field value.
	 * @return array Processed group data.
	 */
	private function process_group_data( $field, $value ) {
		$processed_value = array();

		if ( is_array( $value ) ) {
			foreach ( $field['sub_fields'] as $sub_field ) {
				$sub_field_name = $sub_field['name'];
				if ( isset( $value[ $sub_field_name ] ) ) {
					$processed_value[ $sub_field_name ] = $this->process_field_value( $sub_field, $value[ $sub_field_name ] );
				}
			}
		}

		return $processed_value;
	}

	/**
	 * Process flexible content field data.
	 *
	 * @param array $field The field configuration.
	 * @param array $value The field value.
	 * @return array Processed flexible content data.
	 */
	private function process_flexible_content_data( $field, $value ) {
		$processed_value = array();

		if ( is_array( $value ) ) {
			foreach ( $value as $layout_index => $layout ) {
				$layout_name      = $layout['acf_fc_layout'];
				$processed_layout = array( 'acf_fc_layout' => $layout_name );

				foreach ( $field['layouts'] as $defined_layout ) {
					if ( $defined_layout['name'] === $layout_name ) {
						foreach ( $defined_layout['sub_fields'] as $sub_field ) {
							$sub_field_name = $sub_field['name'];
							if ( isset( $layout[ $sub_field_name ] ) ) {
								$processed_layout[ $sub_field_name ] = $this->process_field_value( $sub_field, $layout[ $sub_field_name ] );
							}
						}
						break;
					}
				}

				$processed_value[ $layout_index ] = $processed_layout;
			}
		}

		return $processed_value;
	}
}
