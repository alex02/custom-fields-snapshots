<?php
/**
 * Custom Fields Snapshots Importer class
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
 * Custom Fields Snapshots Importer Class
 *
 * @since 1.0.0
 */
class Importer {

	/**
	 * The logger instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * The field processor instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Field_Processor
	 */
	private $processor;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Logger $logger The logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Import field data.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
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
				$this->logger->log(
					'info',
					/* translators: %1$s: field name, %2$s: group key */
					sprintf( __( 'Invalid data structure for field "%1$s" in group "%2$s"', 'custom-fields-snapshots' ), $field_name, $group_key )
				);
				return false;
			}

			if ( isset( $field_data['options'] ) ) {
				if ( ! $this->import_options_field( $field_name, $field_data['options'], $original_data, $group_key ) ) {
					return false;
				}
			}

			if ( isset( $field_data['users'] ) ) {
				if ( ! $this->import_user_fields( $field_name, $field_data['users'], $original_data, $group_key ) ) {
					return false;
				}
			}

			if ( isset( $field_data['post_types'] ) ) {
				foreach ( $field_data['post_types'] as $post_type => $posts ) {
					foreach ( $posts as $post_id => $value ) {
						if ( ! $this->import_post_field( $field_name, $post_id, $value, $original_data, $group_key, $post_type ) ) {
							return false;
						}
					}
				}
			}
		}

		return true;
	}

	/**
	 * Import fields for users.
	 *
	 * @since 1.1.0
	 *
	 * @param string $field_name    The field name.
	 * @param array  $user_data     The user data to import.
	 * @param array  $original_data Reference to the original data array for potential rollback.
	 * @param string $group_key     The field group key.
	 * @return bool True on success, false on failure.
	 */
	private function import_user_fields( $field_name, $user_data, &$original_data, $group_key ) {
		foreach ( $user_data as $user_id => $value ) {
			$user_id = absint( $user_id );
			if ( ! $user_id ) {
				$this->logger->log(
					'error',
					/* translators: %1$s: field name, %2$s: group key */
					sprintf( __( 'Invalid user ID for field "%1$s" in group "%2$s"', 'custom-fields-snapshots' ), $field_name, $group_key )
				);
				return false;
			}

			$user = get_userdata( $user_id );
			if ( ! $user ) {
				$this->logger->log(
					'error',
					/* translators: %1$d: user ID, %2$s: field name */
					sprintf( __( 'User with ID %1$d does not exist. Cannot update field "%2$s"', 'custom-fields-snapshots' ), $user_id, $field_name )
				);
				return false;
			}

			$field_object   = get_field_object( $field_name, 'user_' . $user_id );
			$existing_value = get_field( $field_name, 'user_' . $user_id, $this->processor->maybe_format_value( $field_object ?? array() ) );

			$original_data[ $group_key ][ $field_name ]['users'][ $user_id ] = $existing_value;

			/**
			 * Filters the value of a user field before importing.
			 *
			 * @since 1.1.0
			 *
			 * @param mixed  $value          The field value.
			 * @param mixed  $existing_value The existing field value.
			 * @param string $group_key      The field group key.
			 * @param int    $user_id        The user ID.
			 */
			$value = apply_filters( "custom_fields_snapshots_import_user_field_{$field_name}_value", $value, $existing_value, $group_key, $user_id );

			if ( $existing_value === $value ) {
				$this->logger->log(
					'info',
					/* translators: %1$s: field name, %2$d: user ID */
					sprintf( __( 'Field "%1$s" for user ID %2$d has the same value. Skipping update.', 'custom-fields-snapshots' ), $field_name, $user_id )
				);
				continue;
			}

			$update_result = update_field( $field_name, $value, 'user_' . $user_id );

			if ( false === $update_result && $this->verify_update_failed( $field_name, $existing_value, 'user_' . $user_id ) ) {
				$this->logger->log(
					'error',
					/* translators: %1$s: field name, %2$d: user ID */
					sprintf( __( 'Failed to update field "%1$s" for user ID %2$d.', 'custom-fields-snapshots' ), $field_name, $user_id )
				);

				/**
				 * Fires when a user field import fails.
				 *
				 * @since 1.1.0
				 *
				 * @param string $field_name The field name.
				 * @param mixed  $value      The field value.
				 * @param string $group_key  The field group key.
				 * @param int    $user_id    The user ID.
				 */
				do_action( 'custom_fields_snapshots_import_user_field_failed', $field_name, $value, $group_key, $user_id );

				return false;
			}

			$this->logger->log(
				'success',
				/* translators: %1$s: field name, %2$d: user ID */
				sprintf( __( 'Successfully updated field "%1$s" for user ID %2$d.', 'custom-fields-snapshots' ), $field_name, $user_id )
			);

			/**
			 * Fires when a user field import is successful.
			 *
			 * @since 1.1.0
			 *
			 * @param string $field_name The field name.
			 * @param mixed  $value      The field value.
			 * @param string $group_key  The field group key.
			 * @param int    $user_id    The user ID.
			 */
			do_action( 'custom_fields_snapshots_import_user_field_complete', $field_name, $value, $group_key, $user_id );
		}

		return true;
	}

	/**
	 * Import a field for an options page.
	 *
	 * @since 1.1.0
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

		$original_data[ $group_key ][ $field_name ]['options'] = $existing_value;

		/**
		 * Filters the value of an options field before importing.
		 *
		 * @since 1.0.0
		 *
		 * @param mixed  $value          The field value.
		 * @param mixed  $existing_value The existing field value.
		 * @param string $group_key      The field group key.
		 */
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

			/**
			 * Fires when an options field import fails.
			 *
			 * @since 1.1.0
			 *
			 * @param string $field_name The field name.
			 * @param mixed  $value      The field value.
			 * @param string $group_key  The field group key.
			 */
			do_action( 'custom_fields_snapshots_import_options_field_failed', $field_name, $value, $group_key );

			return false;
		}

		/* translators: %s: field name */
		$this->logger->log( 'success', sprintf( __( 'Successfully updated option "%s"', 'custom-fields-snapshots' ), $field_name ) );

		/**
		 * Fires when an options field import is successful.
		 *
		 * @since 1.1.0
		 *
		 * @param string $field_name The field name.
		 * @param mixed  $value      The field value.
		 * @param string $group_key  The field group key.
		 */
		do_action( 'custom_fields_snapshots_import_options_field_complete', $field_name, $value, $group_key );

		return true;
	}

	/**
	 * Import a field for a post.
	 *
	 * @since 1.1.0
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

		/**
		 * Filters the value of a post field before importing.
		 *
		 * @since 1.0.0
		 *
		 * @param mixed  $value          The field value.
		 * @param mixed  $existing_value The existing field value.
		 * @param string $group_key      The field group key.
		 * @param string $post_type      The post type.
		 * @param int    $post_id        The post ID.
		 */
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

			/**
			 * Fires when a post field import fails.
			 *
			 * @since 1.0.0
			 *
			 * @param string $field_name The field name.
			 * @param mixed  $value      The field value.
			 * @param string $group_key  The field group key.
			 * @param string $post_type  The post type.
			 * @param int    $post_id    The post ID.
			 */
			do_action( 'custom_fields_snapshots_import_field_failed', $field_name, $value, $group_key, $post_type, $post_id );

			return false;
		}

		/* translators: %1$s: field name, %2$d: post ID */
		$this->logger->log( 'success', sprintf( __( 'Successfully updated field "%1$s" for post ID %2$d.', 'custom-fields-snapshots' ), $field_name, $post_id ) );

		/**
		 * Fires when a post field import is successful.
		 *
		 * @since 1.0.0
		 *
		 * @param string $field_name The field name.
		 * @param mixed  $value      The field value.
		 * @param string $group_key  The field group key.
		 * @param string $post_type  The post type.
		 * @param int    $post_id    The post ID.
		 */
		do_action( 'custom_fields_snapshots_import_field_complete', $field_name, $value, $group_key, $post_type, $post_id );

		return true;
	}

	/**
	 * Rollback changes made during import.
	 *
	 * @since 1.0.0
	 *
	 * @param array $original_data The original data to restore.
	 */
	private function rollback_changes( $original_data ) {
		foreach ( $original_data as $group_key => $fields ) {
			foreach ( $fields as $field_name => $field_data ) {
				if ( isset( $field_data['options'] ) ) {
					// Handle options.
					$this->rollback_option( $field_name, $field_data['options'] );
					unset( $field_data['options'] );
				}

				if ( isset( $field_data['users'] ) ) {
					// Handle users.
					foreach ( $field_data['users'] as $user_id => $value ) {
						$this->rollback_user_field( $field_name, $user_id, $value );
					}
					unset( $field_data['users'] );
				}

				// Handle post types.
				foreach ( $field_data as $post_type => $posts ) {
					foreach ( $posts as $post_id => $value ) {
						$this->rollback_post_field( $field_name, $post_id, $value, $post_type );
					}
				}
			}
		}
	}

	/**
	 * Rollback an option field.
	 *
	 * @since 1.1.0
	 *
	 * @param string $field_name The field name.
	 * @param mixed  $value      The original value to restore.
	 */
	private function rollback_option( $field_name, $value ) {
		$field_objects  = get_field_objects( 'option' );
		$field_object   = $field_objects[ $field_name ] ?? array();
		$existing_value = get_field( $field_name, 'option', $this->processor->maybe_format_value( $field_object ) );

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
	}

	/**
	 * Rollback a user field.
	 *
	 * @since 1.1.0
	 *
	 * @param string $field_name The field name.
	 * @param int    $user_id    The user ID.
	 * @param mixed  $value      The original value to restore.
	 */
	private function rollback_user_field( $field_name, $user_id, $value ) {
		$field_object   = get_field_object( $field_name, 'user_' . $user_id );
		$existing_value = get_field( $field_name, 'user_' . $user_id, $this->processor->maybe_format_value( $field_object ?? array() ) );

		if ( $value !== $existing_value ) {
			$rollback_result = update_field( $field_name, $value, 'user_' . $user_id );

			if ( false === $rollback_result && $this->verify_update_failed( $field_name, $existing_value, 'user_' . $user_id ) ) {
				/* translators: %1$s: field name, %2$d: user ID */
				$this->logger->log( 'error', sprintf( __( 'Failed to rollback field "%1$s" for user ID %2$d.', 'custom-fields-snapshots' ), $field_name, $user_id ) );
			} else {
				/* translators: %1$s: field name, %2$d: user ID */
				$this->logger->log( 'info', sprintf( __( 'Rolled back field "%1$s" for user ID %2$d.', 'custom-fields-snapshots' ), $field_name, $user_id ) );
			}
		}
	}

	/**
	 * Rollback a post field.
	 *
	 * @since 1.1.0
	 *
	 * @param string $field_name The field name.
	 * @param int    $post_id    The post ID.
	 * @param mixed  $value      The original value to restore.
	 * @param string $post_type  The post type.
	 */
	private function rollback_post_field( $field_name, $post_id, $value, $post_type ) {
		$field_object   = get_field_object( $field_name, $post_id );
		$existing_value = get_field( $field_name, $post_id, $this->processor->maybe_format_value( $field_object ?? array() ) );

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

	/**
	 * Verify if the update operation actually failed.
	 *
	 * This function is used to double-check if an update operation failed,
	 * as sometimes update_field() returns false even when the data was successfully updated.
	 * It compares the current field value with the previously existing value to determine
	 * if an actual change occurred.
	 *
	 * @since 1.0.0
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
