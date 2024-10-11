<?php
/**
 * Custom Fields Snapshots Importer class
 *
 * @package CustomFieldsSnapshots
 */

namespace Custom_Fields_Snapshots;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Fields Snapshots Importer Class
 */
class Importer {

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * The field processor instance.
	 *
	 * @var Field_Processor
	 */
	private $processor;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger The logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Import field data.
	 *
	 * @param string $json_data    The JSON data to import.
	 * @param bool   $rollback Whether to use rollback on failure.
	 * @return bool True on success, false on failure.
	 */
	public function import_field_data( $json_data, $rollback = true ) {
		// Load the field processor class.
		require_once CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_DIR . 'includes/class-field-processor.php';

		$this->processor = new Field_Processor();

		$data = json_decode( $json_data, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->logger->log( 'error', __( 'Invalid JSON data', 'custom-fields-snapshots' ) );

			return false;
		}

		if ( ! is_array( $data ) ) {
			$this->logger->log( 'error', __( 'Import failed: Invalid data format.', 'custom-fields-snapshots' ) );

			return false;
		}

		$original_data  = array();
		$import_success = true;

		foreach ( $data as $group_key => $fields ) {
			/* translators: %s: group key */
			$this->logger->log( 'info', sprintf( __( 'Importing group: "%s"', 'custom-fields-snapshots' ), $group_key ) );

			if ( ! $this->import_group_data( $group_key, $fields, $original_data ) ) {
				$import_success = false;
				break;
			}
		}

		if ( ! $import_success && $rollback ) {
			$this->rollback_changes( $original_data );

			$this->logger->log( 'error', __( 'Changes rolled back due to import failure.', 'custom-fields-snapshots' ) );
		}

		$this->logger->log( $import_success ? 'success' : 'error', __( 'Import process finished. Status:', 'custom-fields-snapshots' ) . ' ' . ( $import_success ? __( 'Success', 'custom-fields-snapshots' ) : __( 'Failed', 'custom-fields-snapshots' ) ) );

		return $import_success;
	}

	/**
	 * Import data for a single field group.
	 *
	 * @param string $group_key     The field group key.
	 * @param array  $fields        The fields data to import.
	 * @param array  $original_data Reference to the original data array for potential rollback.
	 * @return bool True on success, false on failure.
	 */
	private function import_group_data( $group_key, $fields, &$original_data ) {
		if ( empty( array_filter( $fields ) ) ) {
			$this->logger->log( 'info', __( 'No fields to import, skipping update.', 'custom-fields-snapshots' ) );

			return true; // Return true as this is not a failure case.
		}

		foreach ( $fields as $field_name => $field_data ) {
			if ( ! is_array( $field_data ) ) {
				/* translators: %1$s: field name, %2$s: group key */
				$this->logger->log( 'info', sprintf( __( 'Invalid data structure for field "%1$s" in group "%2$s"', 'custom-fields-snapshots' ), $field_name, $group_key ) );

				return false;
			}

			foreach ( $field_data as $post_type => $posts ) {
				if ( 'option' === $post_type ) {
					if ( ! $this->import_options_field( $field_name, $posts, $original_data, $group_key ) ) {
						return false;
					}

					continue;
				}

				foreach ( $posts as $post_id => $value ) {
					if ( ! $this->import_post_field( $field_name, $post_id, $value, $original_data, $group_key, $post_type ) ) {
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Import a field for an options page.
	 *
	 * @param string $field_name    The field name.
	 * @param mixed  $value         The field value.
	 * @param array  $original_data Reference to the original data array for potential rollback.
	 * @param string $group_key     The field group key.
	 * @return bool True on success, false on failure.
	 */
	private function import_options_field( $field_name, $value, &$original_data, $group_key ) {
		$field_objects = get_field_objects( 'option' );

		$existing_value = get_field( $field_name, 'option', $this->processor->maybe_format_value( $field_objects[ $field_name ] ?? array() ) );

		$original_data[ $group_key ][ $field_name ]['option'] = $existing_value;

		$value = apply_filters( sprintf( 'custom_fields_snapshots_import_option_%s_value', sanitize_key( $field_name ) ), $value, $existing_value, $group_key );

		if ( $existing_value === $value ) {
			/* translators: %s: option name */
			$this->logger->log( 'info', sprintf( __( 'Option "%s" has the same value. Skipping update.', 'custom-fields-snapshots' ), $field_name ) );

			return true;
		}

		$update_result = update_field( $field_name, $value, 'option' );

		if ( false === $update_result && $this->verify_update_failed( $field_name, $existing_value, 'option' ) ) {
			/* translators: %s: option name */
			$this->logger->log( 'error', sprintf( __( 'Failed to update option "%s".', 'custom-fields-snapshots' ), $field_name ) );

			do_action( 'custom_fields_snapshots_import_field_failed', $field_name, $value, $group_key, $post_type );

			return false;
		}

		/* translators: %s: field name */
		$this->logger->log( 'success', sprintf( __( 'Successfully updated option "%s"', 'custom-fields-snapshots' ), $field_name ) );

		do_action( 'custom_fields_snapshots_import_field_complete', $field_name, $value, $group_key, $post_type );

		return true;
	}

	/**
	 * Import a field for a post.
	 *
	 * @param string $field_name    The field name.
	 * @param int    $post_id       The post ID.
	 * @param mixed  $value         The field value.
	 * @param array  $original_data Reference to the original data array for potential rollback.
	 * @param string $group_key     The field group key.
	 * @param string $post_type     The post type.
	 * @return bool True on success, false on failure.
	 */
	private function import_post_field( $field_name, $post_id, $value, &$original_data, $group_key, $post_type ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			/* translators: %1$s: field name, %2$s: group key */
			$this->logger->log( 'error', sprintf( __( 'Invalid post ID for field "%1$s" in group "%2$s"', 'custom-fields-snapshots' ), $field_name, $group_key ) );

			return false;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			/* translators: %1$d: post ID, %2$s: field name */
			$this->logger->log( 'error', sprintf( __( 'Permission denied for post ID %1$d. Cannot edit field "%2$s"', 'custom-fields-snapshots' ), $post_id, $field_name ) );

			return false;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			/* translators: %1$d: post ID, %2$s: field name */
			$this->logger->log( 'error', sprintf( __( 'Post with ID %1$d does not exist. Cannot update field "%2$s"', 'custom-fields-snapshots' ), $post_id, $field_name ) );

			return false;
		}

		$field_object = get_field_object( $field_name, $post_id );

		// Check if the value is different before updating.
		$existing_value = get_field( $field_name, $post_id, $this->processor->maybe_format_value( $field_object ?? array() ) );

		// Store original value for potential rollback.
		$original_data[ $group_key ][ $field_name ][ $post_type ][ $post_id ] = $existing_value;

		$value = apply_filters( sprintf( 'custom_fields_snapshots_import_field_%s_value', sanitize_key( $field_name ) ), $value, $existing_value, $group_key, $post_type, $post_id );

		if ( $existing_value === $value ) {
			/* translators: %1$s: field name, %2$d: post ID */
			$this->logger->log( 'info', sprintf( __( 'Field "%1$s" for post ID %2$d has the same value. Skipping update.', 'custom-fields-snapshots' ), $field_name, $post_id ) );

			return true;
		}

		$update_result = update_field( $field_name, $value, $post_id );

		if ( false === $update_result && $this->verify_update_failed( $field_name, $existing_value, $post_id ) ) {
			/* translators: %1$s: field name, %2$d: post ID */
			$this->logger->log( 'error', sprintf( __( 'Failed to update field "%1$s" for post ID %2$d.', 'custom-fields-snapshots' ), $field_name, $post_id ) );

			do_action( 'custom_fields_snapshots_import_field_failed', $field_name, $value, $group_key, $post_type, $post_id );

			return false;
		}

		/* translators: %1$s: field name, %2$d: post ID */
		$this->logger->log( 'success', sprintf( __( 'Successfully updated field "%1$s" for post ID %2$d.', 'custom-fields-snapshots' ), $field_name, $post_id ) );

		do_action( 'custom_fields_snapshots_import_field_complete', $field_name, $value, $group_key, $post_type, $post_id );

		return true;
	}

	/**
	 * Rollback changes made during import.
	 *
	 * @param array $original_data The original data to restore.
	 */
	private function rollback_changes( $original_data ) {
		foreach ( $original_data as $group_key => $fields ) {
			foreach ( $fields as $field_name => $field_data ) {
				if ( isset( $field_data['option'] ) ) {
					// Handle options.
					$value = $field_data['option'];

					$field_objects = get_field_objects( 'option' );
					$field_object  = $field_objects[ $field_name ];

					$existing_value = get_field( $field_name, 'option', $this->processor->maybe_format_value( $field_object ?? array() ) );

					// Rollback only if the value is different.
					if ( $value !== $existing_value ) {
						$rollback_result = update_field( $field_name, $value, 'option' );
						if ( false === $rollback_result && $this->verify_update_failed( $field_name, $existing_value, 'option' ) ) {
							/* translators: %s: option name */
							$this->logger->log( 'error', sprintf( __( 'Failed to rollback option "%s"', 'custom-fields-snapshots' ), $field_name ) );
						} else {
							/* translators: %s: option name */
							$this->logger->log( 'info', sprintf( __( 'Rolled back option "%s"', 'custom-fields-snapshots' ), $field_name ) );
						}
					}

					// Unset the 'option' key so it's not treated as a post type.
					unset( $field_data['option'] );
				}

				// Handle post types.
				foreach ( $field_data as $post_type => $posts ) {
					foreach ( $posts as $post_id => $value ) {
						$field_object = get_field_object( $field_name, $post_id );

						$existing_value = get_field( $field_name, $post_id, $this->processor->maybe_format_value( $field_object ?? array() ) );

						// Rollback only if the value is different.
						if ( $value !== $existing_value ) {
							$rollback_result = update_field( $field_name, $value, $post_id );
							if ( false === $rollback_result && $this->verify_update_failed( $field_name, $existing_value, $post_id ) ) {
								/* translators: %1$s: field name, %2$s: post type, %3$d: post ID */
								$this->logger->log( 'error', sprintf( __( 'Failed to rollback field "%1$s" for %2$s ID %3$d.', 'custom-fields-snapshots' ), $field_name, $post_type, $post_id ) );
							} else {
								/* translators: %1$s: field name, %2$s: post type, %3$d: post ID */
								$this->logger->log( 'info', sprintf( __( 'Rolled back field "%1$s" for %2$s ID %3$d.', 'custom-fields-snapshots' ), $field_name, $post_type, $post_id ) );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Verify if the update operation actually failed.
	 *
	 * This function is used to double-check if an update operation failed,
	 * as sometimes update_field() returns false even when the data was successfully updated.
	 * It compares the current field value with the previously existing value to determine
	 * if an actual change occurred.
	 *
	 * @param string          $field_name    The name of the field to check.
	 * @param mixed           $existing_value The value of the field before the update attempt.
	 * @param int|string|null $post_id The post ID or 'option' for options page. Null for current post.
	 *
	 * @return bool True if the update failed (values are the same), false if it succeeded (values differ).
	 */
	private function verify_update_failed( $field_name, $existing_value, $post_id = null ) {
		if ( 'option' === $post_id || 'options' === $post_id ) {
			$field_objects = get_field_objects( $post_id );
			$field_object  = $field_objects[ $field_name ] ?? array();
		} else {
			$field_object = get_field_object( $field_name, $post_id );
		}

		return get_field( $field_name, $post_id, $this->processor->maybe_format_value( $field_object ?? array() ) ) === $existing_value;
	}
}
