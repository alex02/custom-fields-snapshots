<?php
/**
 * Custom Fields Snapshots Logger class
 *
 * @package CustomFieldsSnapshots
 */

namespace Custom_Fields_Snapshots;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Fields Snapshots Logger Class
 */
class Logger {

	/**
	 * Stores log entries.
	 *
	 * @var array
	 */
	private $log = array();

	/**
	 * Log an event.
	 *
	 * @param string $type    The type of event ('success', 'error', 'info').
	 * @param string $message The event message.
	 */
	public function log( $type, $message ) {
		$this->log[] = array(
			'type'    => sanitize_key( $type ),
			'message' => wp_kses_post( $message ),
			'time'    => current_time( 'mysql' ),
		);
	}

	/**
	 * Get all log entries.
	 *
	 * @return array The log entries.
	 */
	public function get_log() {
		$log_types = array(
			'info'    => esc_html__( 'INFO', 'custom-fields-snapshots' ), // translators: Log entry type.
			'error'   => esc_html__( 'ERROR', 'custom-fields-snapshots' ), // translators: Log entry type.
			'success' => esc_html__( 'SUCCESS', 'custom-fields-snapshots' ), // translators: Log entry type.
		);

		return array_map(
			function ( $entry ) use ( $log_types ) {
				return sprintf(
					// translators: 1: Log entry type, 2: Log entry time, 3: Log entry message.
					esc_html__( '[%1$s] %2$s: %3$s', 'custom-fields-snapshots' ),
					$log_types[ $entry['type'] ] ?? $entry['type'],
					esc_html( $entry['time'] ),
					esc_html( $entry['message'] )
				);
			},
			$this->log
		);
	}

	/**
	 * Clear all log entries.
	 */
	public function clear_log() {
		$this->log = array();
	}

	/**
	 * Get log entries as a formatted string.
	 *
	 * @return string Formatted log entries.
	 */
	public function get_formatted_log() {
		$formatted_log = '';

		foreach ( $this->log as $entry ) {
			$formatted_log .= sprintf(
				"%s %s %s\n",
				esc_html( $entry['time'] ),
				esc_html( $entry['type'] ),
				esc_html( $entry['message'] )
			);
		}

		return $formatted_log;
	}
}
