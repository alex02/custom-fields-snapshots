<?php
/**
 * Admin class for Custom Fields Snapshots.
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
 * Admin class.
 *
 * @since 1.0.0
 */
class Admin {

	/**
	 * The exporter instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Exporter
	 */
	private $exporter;

	/**
	 * The importer instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Importer
	 */
	private $importer;

	/**
	 * The logger instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Whether the current WordPress installation is a multisite network.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	private $is_multisite;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Initialize the admin functionality.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		$this->is_multisite = is_multisite();

		require_once CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_DIR . 'includes/class-logger.php';

		$this->logger = new Logger();

		if ( $this->is_multisite ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'network_admin_menu', array( $this, 'add_network_admin_menu' ) );
			add_action( 'network_admin_edit_custom_fields_snapshots_update_network_settings', array( $this, 'update_network_settings' ) );
		} else {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		}

		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_custom_fields_snapshots_import', array( $this, 'ajax_import' ) );
		add_action( 'admin_post_custom_fields_snapshots_export', array( $this, 'handle_export' ) );
	}

	/**
	 * Add admin menu for a single site.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Custom Fields Snapshots', 'custom-fields-snapshots' ),
			__( 'Field Snapshots', 'custom-fields-snapshots' ),
			'manage_options',
			'custom-fields-snapshots',
			array( $this, 'render_export_page' ),
			'dashicons-database-import'
		);

		$this->add_submenu_pages( 'custom-fields-snapshots' );
	}

	/**
	 * Add a submenu page to the Network Admin Settings menu.
	 *
	 * This function adds a submenu page for Custom Fields Snapshots settings
	 * in the Network Admin area. It's only accessible to users with the
	 * 'manage_network_options' capability.
	 *
	 * @since 1.0.0
	 */
	public function add_network_admin_menu() {
		add_submenu_page(
			'settings.php',
			__( 'Custom Fields Snapshots', 'custom-fields-snapshots' ),
			__( 'Field Snapshots', 'custom-fields-snapshots' ),
			'manage_network_options',
			'custom-fields-snapshots-network-settings',
			array( $this, 'render_network_settings_page' )
		);
	}

	/**
	 * Render the network settings page for Custom Fields Snapshots.
	 *
	 * This function displays the network-wide settings page for the Custom Fields Snapshots plugin.
	 * It checks for proper user capabilities and renders the settings form.
	 *
	 * @since 1.0.0
	 */
	public function render_network_settings_page() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'custom-fields-snapshots' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form action="<?php echo esc_url( network_admin_url( 'edit.php?action=custom_fields_snapshots_update_network_settings' ) ); ?>" method="post">
				<?php
				settings_fields( 'custom_fields_snapshots_network_settings' );
				do_settings_sections( 'custom_fields_snapshots_network_settings' );
				submit_button( __( 'Save Network Settings', 'custom-fields-snapshots' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the description for the network settings section.
	 *
	 * This function outputs a description for the network-wide settings section
	 * of the Custom Fields Snapshots plugin.
	 *
	 * @since 1.0.0
	 */
	public function render_network_settings_section() {
		echo '<p>' . esc_html__( 'Configure network-wide settings for Custom Fields Snapshots.', 'custom-fields-snapshots' ) . '</p>';
	}

	/**
	 * Update network settings for Custom Fields Snapshots.
	 *
	 * This function handles the form submission for updating network-wide settings.
	 * It checks for proper permissions, validates the nonce, and updates the relevant options.
	 *
	 * @since 1.0.0
	 */
	public function update_network_settings() {
		check_admin_referer( 'custom_fields_snapshots_network_settings-options' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'custom-fields-snapshots' ) );
		}

		$options = array(
			'custom_fields_snapshots_delete_plugin_data',
		);

		foreach ( $options as $option ) {
			$option = sanitize_key( $option );
			$value  = filter_input( INPUT_POST, $option, FILTER_VALIDATE_BOOLEAN );

			if ( null !== $value ) {
				update_site_option( $option, $value );
			} else {
				update_site_option( $option, false );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'custom-fields-snapshots-network-settings',
					'updated' => 'true',
				),
				network_admin_url( 'settings.php' )
			)
		);
		exit;
	}

	/**
	 * Add submenu pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $parent_slug The slug of the parent menu.
	 */
	private function add_submenu_pages( $parent_slug ) {
		add_submenu_page(
			$parent_slug,
			__( 'Export', 'custom-fields-snapshots' ),
			__( 'Export', 'custom-fields-snapshots' ),
			'manage_options',
			$parent_slug,
			array( $this, 'render_export_page' )
		);

		add_submenu_page(
			$parent_slug,
			__( 'Import', 'custom-fields-snapshots' ),
			__( 'Import', 'custom-fields-snapshots' ),
			'manage_options',
			'custom-fields-snapshots-import',
			array( $this, 'render_import_page' )
		);

		add_submenu_page(
			$parent_slug,
			__( 'Settings', 'custom-fields-snapshots' ),
			__( 'Settings', 'custom-fields-snapshots' ),
			'manage_options',
			'custom-fields-snapshots-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		if ( $this->is_multisite && ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'custom-fields-snapshots' ) );
		} elseif ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'custom-fields-snapshots' ) );
		}

		$is_network_admin = $this->is_multisite && is_network_admin();
		$admin_url        = $is_network_admin ? network_admin_url() : admin_url();
		$option_group     = $is_network_admin ? 'custom_fields_snapshots_network_settings' : 'custom-fields-snapshots-settings';
		$form_action      = $is_network_admin ? 'edit.php?action=custom_fields_snapshots_update_network_settings' : 'options.php';

		?>
		<div class="custom-fields-snapshots wrap">
			<h1><?php esc_html_e( 'Custom Fields Snapshots', 'custom-fields-snapshots' ); ?></h1>

			<?php settings_errors( 'custom_fields_snapshots_messages' ); ?>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( trailingslashit( $admin_url ) . 'admin.php?page=custom-fields-snapshots' ); ?>" class="nav-tab"><?php esc_html_e( 'Export', 'custom-fields-snapshots' ); ?></a>
				<a href="<?php echo esc_url( trailingslashit( $admin_url ) . 'admin.php?page=custom-fields-snapshots-import' ); ?>" class="nav-tab"><?php esc_html_e( 'Import', 'custom-fields-snapshots' ); ?></a>
				<a href="<?php echo esc_url( trailingslashit( $admin_url ) . 'admin.php?page=custom-fields-snapshots-settings' ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Settings', 'custom-fields-snapshots' ); ?></a>
			</h2>
		
			<div class="settings-tab">
				<form class="settings-form" method="post" action="<?php echo esc_url( trailingslashit( $admin_url ) . $form_action ); ?>">
					<?php
					settings_fields( $option_group );
					do_settings_sections( $option_group );
					submit_button();
					?>
				</form>

				<div class="info-box">
					<h3><?php esc_html_e( 'About', 'custom-fields-snapshots' ); ?></h3>
					<p><?php esc_html_e( 'Custom Fields Snapshots allow you to easily create backups of your Advanced Custom Fields (ACF) data by exporting selected field groups, post types, users, and options. These snapshots enable version control, make it easier to share setups with team members, assist with migrations between WordPress environments, and enable quick restoration of previous configurations.', 'custom-fields-snapshots' ); ?></p>

					<h3><?php esc_html_e( 'Contribute', 'custom-fields-snapshots' ); ?></h3>
					<p><?php esc_html_e( 'Support the development of this plugin:', 'custom-fields-snapshots' ); ?></p>

					<div class="contribute-box">
						<a class="button button-primary" href="<?php echo esc_url( 'https://wordpress.org/support/plugin/custom-fields-snapshots/reviews/#new-post' ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Rate it on WordPress.org', 'custom-fields-snapshots' ); ?>
						</a>

						<a class="button button-primary" href="<?php echo esc_url( 'https://translate.wordpress.org/projects/wp-plugins/custom-fields-snapshots/' ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Help translate it', 'custom-fields-snapshots' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>
		<?php

		$this->enqueue_settings_assets();
	}

	/**
	 * Render the settings section description.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_section() {
		echo '<p>' . esc_html__( 'Configure settings for Custom Fields Snapshots.', 'custom-fields-snapshots' ) . '</p>';
	}

	/**
	 * Enqueue the settings assets.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_settings_assets() {
		wp_enqueue_style(
			'custom-fields-snapshots-settings',
			CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_URL . 'assets/css/admin-settings.css',
			array(),
			CUSTOM_FIELDS_SNAPSHOTS_VERSION
		);
	}

	/**
	 * Render the import page.
	 *
	 * @since 1.0.0
	 */
	public function render_import_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="custom-fields-snapshots wrap">
			<h1><?php esc_html_e( 'Import Snapshot', 'custom-fields-snapshots' ); ?></h1>
			
			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-fields-snapshots' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Export', 'custom-fields-snapshots' ); ?></a>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-fields-snapshots-import' ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Import', 'custom-fields-snapshots' ); ?></a>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-fields-snapshots-settings' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Settings', 'custom-fields-snapshots' ); ?></a>
			</h2>
	
			<div class="import-tab">
				<div class="import-box">
					<h3><?php esc_html_e( 'Import Snapshot', 'custom-fields-snapshots' ); ?></h3>

					<div class="import-container">
						<form class="import-form" method="post" enctype="multipart/form-data">
							<?php wp_nonce_field( 'custom-fields-snapshots-import', 'custom-fields-snapshots-import-nonce' ); ?>
		
							<div class="upload-area">
								<div class="upload-icon dashicons dashicons-upload"></div>
								<p style="margin-top:2.5em"><?php esc_html_e( 'Drag & Drop your JSON file here or click to select', 'custom-fields-snapshots' ); ?></p>
								<input type="file" name="import_file" class="import-file" accept=".json" style="display:none">
							</div>
							
							<div class="file-info" style="display:none">
								<span class="file-name"></span>
								<button type="button" class="remove-file button"><?php esc_html_e( 'Remove', 'custom-fields-snapshots' ); ?></button>
							</div>
		
							<div class="import-options">
								<label class="rollback-changes" for="rollback-changes-input">
									<input type="checkbox" name="rollback_changes" id="rollback-changes-input" value="1" checked>
									<?php esc_html_e( 'Rollback changes on failure', 'custom-fields-snapshots' ); ?>
									<span class="rollback-info"><?php esc_html_e( 'In case the import process fails, all changes will be automatically reverted.', 'custom-fields-snapshots' ); ?></span>
								</label>
							</div>
		
							<p><input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Import Snapshot', 'custom-fields-snapshots' ); ?>"></p>
						</form>
		
						<div class="import-validation-message"></div>
						<div class="import-result" style="display:none"></div>
						<div class="event-log" style="display:none"></div>
					</div>
				</div>

				<div class="info-box">
					<h3><?php esc_html_e( 'How To Use', 'custom-fields-snapshots' ); ?></h3>

					<ol class="info-box-list">
						<li><?php esc_html_e( 'Back up your WordPress database', 'custom-fields-snapshots' ); ?></li>
						<li><?php esc_html_e( 'Upload the snapshot JSON file (drag or select)', 'custom-fields-snapshots' ); ?></li>
						<li><?php esc_html_e( 'Enable "Rollback changes on failure" (recommended)', 'custom-fields-snapshots' ); ?></li>
						<li><?php esc_html_e( 'Click "Import Snapshot"', 'custom-fields-snapshots' ); ?></li>
						<li><?php esc_html_e( 'Enable event logs in Settings if issues occur', 'custom-fields-snapshots' ); ?></li>
					</ol>

					<p><strong><?php esc_html_e( "While the import process is safe when using the rollback feature, it's always best to have a backup as an extra precaution.", 'custom-fields-snapshots' ); ?></strong></p>
				</div>
			</div>
		</div>
		<?php

		$this->enqueue_import_assets();
	}

	/**
	 * Enqueue the import assets.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_import_assets() {
		wp_enqueue_style(
			'custom-fields-snapshots-import',
			CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_URL . 'assets/css/admin-import.css',
			array(),
			CUSTOM_FIELDS_SNAPSHOTS_VERSION
		);

		wp_add_inline_style(
			'custom-fields-snapshots-import',
			sprintf(
				'
			.custom-fields-snapshots .info-box-list li::before {
				--custom-fields-snapshots-primary-color: %s;
			}',
				esc_html( $this->get_admin_primary_color() )
			)
		);

		$event_logging = get_option( 'custom_fields_snapshots_event_logging', false );

		wp_enqueue_script(
			'custom-fields-snapshots-import',
			CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_URL . 'assets/js/admin-import.js',
			array( 'jquery' ),
			CUSTOM_FIELDS_SNAPSHOTS_VERSION,
			true
		);

		wp_add_inline_script(
			'custom-fields-snapshots-import',
			'var customFieldsSnapshotsSettings = { event_logging: ' . ( $event_logging ? 'true' : 'false' ) . ' };',
			'before'
		);

		wp_localize_script(
			'custom-fields-snapshots-import',
			'customFieldsSnapshots',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'custom-fields-snapshots-nonce' ),
				'L10n'    => array(
					'importText'                   => __( 'Import Snapshot', 'custom-fields-snapshots' ),
					'importingText'                => __( 'Importing...', 'custom-fields-snapshots' ),
					'eventLogText'                 => __( 'Event Log', 'custom-fields-snapshots' ),
					'noFileSelected'               => __( 'Please select a file to proceed.', 'custom-fields-snapshots' ),
					'invalidFileType'              => __( 'Invalid file type selected. Please select a JSON file.', 'custom-fields-snapshots' ),
					'rollbackDisabledConfirmation' => __( '"Rollback changes on failure" is turned off. Are you sure you want to proceed?', 'custom-fields-snapshots' ),
					'ajaxError'                    => __( 'An unknown error occurred. Please try again.', 'custom-fields-snapshots' ),
				),
			)
		);
	}

	/**
	 * Render the export page.
	 *
	 * @since 1.0.0
	 */
	public function render_export_page() {

		?>
		<div class="custom-fields-snapshots wrap">
			<h1><?php esc_html_e( 'Export Snapshot', 'custom-fields-snapshots' ); ?></h1>

			<div class="export-validation-message"></div>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-fields-snapshots' ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Export', 'custom-fields-snapshots' ); ?></a>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-fields-snapshots-import' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Import', 'custom-fields-snapshots' ); ?></a>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-fields-snapshots-settings' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Settings', 'custom-fields-snapshots' ); ?></a>
			</h2>

			<div class="export-tab">
				<form class="export-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'custom-fields-snapshots-export', 'custom-fields-snapshots-export-nonce' ); ?>
					<input type="hidden" name="action" value="custom_fields_snapshots_export">
					
					<div class="export-step">
						<span class="step-number">1</span>
						<h3><?php esc_html_e( 'Field Groups', 'custom-fields-snapshots' ); ?></h3>
						<br /><br />
						<?php $this->render_field_groups_selection(); ?>
					</div>
		
					<div class="export-step">
						<span class="step-number">2</span>
						<h3><?php esc_html_e( 'Post Types, Users and Options', 'custom-fields-snapshots' ); ?></h3>
						<div class="post-types-container">
							<div class="post-type-section">
								<h4><?php esc_html_e( 'Public Post Types', 'custom-fields-snapshots' ); ?></h4>

								<div class="scrollable-content">
									<?php $this->render_post_types_selection( 'public' ); ?>
								</div>
							</div>
							<div class="post-type-section">
								<h4><?php esc_html_e( 'Private Post Types', 'custom-fields-snapshots' ); ?></h4>
								
								<div class="scrollable-content">
									<?php $this->render_post_types_selection( 'private' ); ?>
								</div>
							</div>
							<div class="side-wide-section">
								<h4><?php esc_html_e( 'Side-wide Data', 'custom-fields-snapshots' ); ?></h4>

								<div class="scrollable-content">
									<div class="select-all-site-wide-data-container post-type-selection">
										<label>
											<input type="checkbox" class="select-all-site-wide-data">
											<?php
											/* translators: %s: Post type label (e.g., "Public" or "Private") */
											esc_html_e( 'All Side-wide Data', 'custom-fields-snapshots' );
											?>
										</label>
									</div>
									<div class="post-type-selection">
										<?php
										$is_acf_pro_active = Plugin::is_acf_pro_active();
										?>
										<label <?php echo ! $is_acf_pro_active ? 'class="disabled"' : ''; ?>>
											<input type="checkbox" 
												name="options" 
												value="1" 
												class="post-type-checkbox options-checkbox"
												<?php echo ! $is_acf_pro_active ? 'disabled' : ''; ?>>
											<?php esc_html_e( 'Options', 'custom-fields-snapshots' ); ?>
											<?php if ( ! $is_acf_pro_active ) : ?>
												<span class="acf-pro-required"><?php esc_html_e( '(ACF Pro required)', 'custom-fields-snapshots' ); ?></span>
											<?php endif; ?>
										</label>
									</div>

									<?php $this->render_users_selection(); ?>
									<?php $this->render_user_roles_selection(); ?>
								</div>
							</div>
						</div>
					</div>
		
					<p><input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Export Snapshot', 'custom-fields-snapshots' ); ?>"></p>
				</form>
			</div>
		</div>
		<?php

		$this->enqueue_export_assets();
	}

	/**
	 * Enqueue the export assets.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_export_assets() {
		wp_enqueue_style(
			'custom-fields-snapshots-export',
			CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_URL . 'assets/css/admin-export.css',
			array(),
			CUSTOM_FIELDS_SNAPSHOTS_VERSION
		);

		wp_add_inline_style(
			'custom-fields-snapshots-export',
			sprintf(
				'
			.custom-fields-snapshots .step-number,
			.custom-fields-snapshots .info-box-list li::before {
				--custom-fields-snapshots-primary-color: %s;
			}',
				esc_html( $this->get_admin_primary_color() )
			)
		);

		wp_enqueue_script(
			'custom-fields-snapshots-export',
			CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_URL . 'assets/js/admin-export.js',
			array( 'jquery' ),
			CUSTOM_FIELDS_SNAPSHOTS_VERSION,
			true
		);

		wp_localize_script(
			'custom-fields-snapshots-export',
			'customFieldsSnapshots',
			array(
				'L10n' => array(
					'selectFieldGroup' => __( 'Please select at least one Field Group.', 'custom-fields-snapshots' ),
					'selectDataTypes'  => __( 'Please select at least one Post Type, User, User Role, or Options.', 'custom-fields-snapshots' ),
					/* translators: %s: Post type name */
					'selectPostId'     => __( 'Please select at least one post ID for post type: %s', 'custom-fields-snapshots' ),
				),
			)
		);
	}

	/**
	 * Render the field groups selection.
	 *
	 * @since 1.0.0
	 */
	private function render_field_groups_selection() {
		$field_groups = acf_get_field_groups();

		if ( empty( $field_groups ) ) {
			esc_html_e( 'No field groups found.', 'custom-fields-snapshots' );
			return;
		}

		?>
		<div class="select-all-field-groups-container">
			<label>
				<input type="checkbox" class="select-all-field-groups" name="select_all_field_groups">
				<?php esc_html_e( 'All Field Groups', 'custom-fields-snapshots' ); ?>
			</label>
		</div>

		<div class="export-field-groups">
		<?php

		foreach ( $field_groups as $field_group ) :
			?>
			<label>
				<input class="field-group-checkbox" type="checkbox" name="field_groups[]" value="<?php echo esc_attr( $field_group['key'] ); ?>">
				<?php echo esc_html( $field_group['title'] ); ?>
			</label>
			<?php
		endforeach;

		?>
		</div>
		<?php
	}

	/**
	 * Render the selection of post types for export.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The type of post types to render ('public' or 'private').
	 */
	private function render_post_types_selection( $type = 'public' ) {
		// Get all post types.
		$post_types = get_post_types( array(), 'objects' );

		// Filter post types based on public/private status.
		$post_types = array_filter(
			$post_types,
			function ( $post_type ) use ( $type ) {
				return ( 'public' === $type ) ? $post_type->public : ! $post_type->public;
			}
		);

		/**
		 * Filters the post types to be exported.
		 *
		 * @since 1.0.0
		 *
		 * @param array $post_types The post types to be exported.
		 * @param string $type The type of post types to render ('public' or 'private').
		 */
		$post_types = apply_filters( sprintf( 'custom_fields_snapshots_export_%s_post_types', sanitize_key( $type ) ), $post_types );

		/**
		 * Filters the post types to be excluded from export.
		 *
		 * @since 1.0.0
		 *
		 * @param array $excluded_post_types The post types to be excluded from export.
		 * @param string $type The type of post types to render ('public' or 'private').
		 */
		$excluded_post_types = apply_filters(
			sprintf( 'custom_fields_snapshots_export_%s_excluded_post_types', sanitize_key( $type ) ),
			( 'public' === $type )
				? array(
					'attachment',
				)
				: array(
					'acf-field',
					'acf-field-group',
					'acf-post-type',
					'acf-taxonomy',
					'acf-ui-options-page',
					'custom_css',
					'customize_changeset',
					'nav_menu_item',
					'oembed_cache',
					'user_request',
					'wp_block',
					'wp_font_face',
					'wp_font_family',
					'wp_global_styles',
					'wp_navigation',
					'wp_pattern',
					'wp_template',
					'wp_template_part',
				)
		);

		// Remove excluded post types.
		$post_types = array_diff_key( $post_types, array_flip( $excluded_post_types ) );

		if ( empty( $post_types ) ) {
			/* translators: %s: Type of post types (e.g., 'public' or 'private') */
			printf( esc_html__( 'No %s post types found.', 'custom-fields-snapshots' ), esc_html( $type ) );
			return;
		}

		$type_label = ( 'public' === $type ) ? __( 'Public', 'custom-fields-snapshots' ) : __( 'Private', 'custom-fields-snapshots' );

		?>
		<div class="select-all-post-types-container post-type-selection">
			<label>
				<input type="checkbox" class="select-all-<?php echo esc_attr( $type ); ?>-post-types" data-type="<?php echo esc_attr( $type ); ?>">
				<?php
				/* translators: %s: Post type label (e.g., "Public" or "Private") */
				printf( esc_html__( 'All %s Post Types', 'custom-fields-snapshots' ), esc_html( $type_label ) );
				?>
			</label>
		</div>
		<?php
		foreach ( $post_types as $post_type ) :
			?>
			<div class="post-type-selection">
				<label>
					<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" class="post-type-checkbox <?php echo esc_attr( $type ); ?>-post-type-checkbox">
					<?php echo esc_html( $post_type->label ); ?>
				</label>
				<div class="post-ids-selection" style="margin-left:20px;display:none">
					<label style="font-weight:bold">
						<input type="checkbox" class="select-all-posts" data-post-type="<?php echo esc_attr( $post_type->name ); ?>">
						<?php esc_html_e( 'All', 'custom-fields-snapshots' ); ?>
					</label>
					
					<?php
					// Use custom sorting for revisions for better visibility.
					$orderby = 'revision' === $post_type->name
						? array(
							'post_title' => 'ASC',
							'post_date'  => 'DESC',
						)
						: array(
							'post_date' => 'DESC',
						);

					// Get all posts for the current post type.
					$posts = get_posts(
						array(
							'post_type'      => $post_type->name,
							'posts_per_page' => -1,
							'post_status'    => 'any',
							'orderby'        => $orderby,
						)
					);

					foreach ( $posts as $post ) :
						?>
						<label>
							<input type="checkbox" name="post_ids[<?php echo esc_attr( $post_type->name ); ?>][]" value="<?php echo esc_attr( $post->ID ); ?>" class="post-id-checkbox">
							<?php
							$post_title = get_the_title( $post );
							$post_id    = absint( $post->ID );

							/* translators: %d: Item ID (could be Post ID or User ID) */
							$id_string = sprintf( __( 'ID: %d', 'custom-fields-snapshots' ), $post_id );

							$date_string = '';
							if ( 'revision' === $post_type->name ) {
								$date_format    = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
								$formatted_date = get_the_date( $date_format, $post );

								/* translators: %s: Formatted date and time */
								$date_string = sprintf(
									'<span class="published-label">%1$s</span> %2$s',
									esc_html__( 'Published:', 'custom-fields-snapshots' ),
									esc_html( $formatted_date )
								);
							}

							$tooltip_content = $date_string ? $id_string . "\n" . $date_string : $id_string;

							/* translators: 1: Post title, 2: Tooltip content with post information */
							$output = sprintf(
								'<div class="custom-fields-snapshot-post-item">
									<span class="post-title">%1$s</span>
									<span class="tooltip">
										<span class="dashicons dashicons-info"></span>
										<span class="tooltiptext">%2$s</span>
									</span>
								</div>',
								esc_html( $post_title ),
								$tooltip_content
							);

							echo wp_kses(
								$output,
								array(
									'div'  => array( 'class' => array() ),
									'span' => array(
										'class' => array(),
									),
								)
							);
							?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
		endforeach;
	}

	/**
	 * Render the selection of user roles for export.
	 *
	 * @since 1.1.0
	 */
	private function render_user_roles_selection() {
		/**
		 * Filters the user roles to be exported.
		 *
		 * @since 1.1.0
		 *
		 * @param array $roles The user roles to be exported.
		 */
		$roles = apply_filters( 'custom_fields_snapshots_export_user_roles', wp_roles()->get_names() );

		if ( empty( $roles ) ) {
			esc_html_e( 'No user roles found.', 'custom-fields-snapshots' );
			return;
		}

		?>
		<div class="user-selection">
			<label>
				<input type="checkbox" class="post-type-checkbox user-roles-checkbox" data-type="user-roles">
				<?php esc_html_e( 'User Roles', 'custom-fields-snapshots' ); ?>
			</label>
			<div class="user-roles-selection" style="margin-left: 20px; display: none;">
				<label style="font-weight: bold;">
					<input type="checkbox" class="select-all-users" data-type="roles">
					<?php esc_html_e( 'All', 'custom-fields-snapshots' ); ?>
				</label>
				<?php foreach ( $roles as $role_value => $role_name ) : ?>
					<label>
						<input type="checkbox" name="user_roles[]" value="<?php echo esc_attr( $role_value ); ?>" class="post-id-checkbox">
						<?php echo esc_html( translate_user_role( $role_name ) ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the selection of users for export.
	 *
	 * @since 1.1.0
	 */
	private function render_users_selection() {
		/**
		 * Filters the users to be exported.
		 *
		 * @since 1.1.0
		 *
		 * @param array $users The users to be exported.
		 */
		$users = apply_filters( 'custom_fields_snapshots_export_users', get_users( array( 'fields' => array( 'ID', 'user_login', 'display_name' ) ) ) );

		if ( empty( $users ) ) {
			esc_html_e( 'No users found.', 'custom-fields-snapshots' );
			return;
		}

		?>
		<div class="user-selection">
			<label>
				<input type="checkbox" class="post-type-checkbox users-checkbox" data-type="users">
				<?php esc_html_e( 'Users', 'custom-fields-snapshots' ); ?>
			</label>
			<div class="user-ids-selection" style="margin-left: 20px; display: none;">
				<label style="font-weight: bold;">
					<input type="checkbox" class="select-all-users" data-type="users">
					<?php esc_html_e( 'All', 'custom-fields-snapshots' ); ?>
				</label>
				<?php foreach ( $users as $user ) : ?>
					<label>
						<input type="checkbox" name="users[]" value="<?php echo esc_attr( $user->ID ); ?>" class="post-id-checkbox">
						<?php
						$post_title = $user->display_name;
						$post_id    = absint( $user->ID );

						/* translators: %d: Item ID (could be Post ID or User ID) */
						$id_string = sprintf( __( 'ID: %d', 'custom-fields-snapshots' ), $post_id );

						/* translators: %s: Username */
						$tooltip_content = sprintf( __( 'Username: %s', 'custom-fields-snapshots' ), $user->user_login );

						/* translators: 1: User display name, 2: Tooltip content with user information */
						$output = sprintf(
							'<div class="custom-fields-snapshot-post-item">
								<span class="post-title">%1$s</span>
								<span class="tooltip">
									<span class="dashicons dashicons-info"></span>
									<span class="tooltiptext">%2$s</span>
								</span>
							</div>',
							esc_html( $post_title ),
							esc_html( $id_string . "\n" . $tooltip_content )
						);

						echo wp_kses(
							$output,
							array(
								'div'  => array( 'class' => array() ),
								'span' => array( 'class' => array() ),
							)
						);
						?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX import.
	 *
	 * @since 1.0.0
	 */
	public function ajax_import() {
		global $wp_filesystem;

		check_ajax_referer( 'custom-fields-snapshots-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions', 'custom-fields-snapshots' ),
				)
			);
		}

		if ( ! isset( $_FILES['import_file'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No file uploaded', 'custom-fields-snapshots' ),
				)
			);
		}

		$file = null;

		if ( isset( $_FILES['import_file'] ) ) {
			$file = array(
				'name'     => isset( $_FILES['import_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['import_file']['name'] ) ) : '',
				'type'     => isset( $_FILES['import_file']['type'] ) ? sanitize_text_field( wp_unslash( $_FILES['import_file']['type'] ) ) : '',
				'tmp_name' => isset( $_FILES['import_file']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['import_file']['tmp_name'] ) ) : '',
				'error'    => isset( $_FILES['import_file']['error'] ) ? (int) $_FILES['import_file']['error'] : 0,
				'size'     => isset( $_FILES['import_file']['size'] ) ? (int) $_FILES['import_file']['size'] : 0,
			);
		}

		if ( $file && UPLOAD_ERR_OK !== $file['error'] ) {
			$error_message = __( 'File upload failed. Please try again.', 'custom-fields-snapshots' );

			if ( $file && isset( $file['error'] ) ) {
				switch ( $file['error'] ) {
					case UPLOAD_ERR_INI_SIZE:
					case UPLOAD_ERR_FORM_SIZE:
						$error_message = __( 'The uploaded file exceeds the maximum file size limit.', 'custom-fields-snapshots' );
						break;
					case UPLOAD_ERR_PARTIAL:
						$error_message = __( 'The file was only partially uploaded. Please try again.', 'custom-fields-snapshots' );
						break;
					case UPLOAD_ERR_NO_FILE:
						$error_message = __( 'No file was uploaded. Please select a file and try again.', 'custom-fields-snapshots' );
						break;
					case UPLOAD_ERR_NO_TMP_DIR:
					case UPLOAD_ERR_CANT_WRITE:
						$error_message = __( 'Server error. Unable to save the uploaded file.', 'custom-fields-snapshots' );
						break;
					case UPLOAD_ERR_EXTENSION:
						$error_message = __( 'File upload stopped by extension.', 'custom-fields-snapshots' );
						break;
				}
			}

			wp_send_json_error(
				array(
					'message' => $error_message,
				)
			);
		}

		// Check if it's a JSON file.
		$file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( 'json' !== $file_ext ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please upload a JSON file', 'custom-fields-snapshots' ),
				)
			);
		}

		WP_Filesystem();
		$json_data = $wp_filesystem->get_contents( $file['tmp_name'] );

		// Validate JSON.
		$data = json_decode( $json_data, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error(
				array(
					'message' => __( 'The uploaded file is not a valid JSON.', 'custom-fields-snapshots' ),
				)
			);
		}

		// Check the structure of the JSON.
		if ( ! $this->validate_import_structure( $data ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'The uploaded file has an invalid JSON structure.', 'custom-fields-snapshots' ),
				)
			);
		}

		// Load the importer class only when needed.
		require_once CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_DIR . 'includes/class-importer.php';

		$this->importer = new Importer( $this->logger );

		$rollback = isset( $_POST['rollback_changes'] ) ? (bool) $_POST['rollback_changes'] : false;

		/**
		 * Fires before importing the field data.
		 *
		 * @since 1.0.0
		 *
		 * @param array $json_data The JSON data to import.
		 */
		do_action( 'custom_fields_snapshots_import_pre', $json_data );

		$import_result = $this->importer->import_field_data( $json_data, $rollback );

		if ( $import_result ) {
			/**
			 * Fires after importing the field data.
			 *
			 * @since 1.0.0
			 */
			do_action( 'custom_fields_snapshots_import_complete' );
		} else {
			/**
			 * Fires when the import process fails.
			 *
			 * @since 1.0.0
			 */
			do_action( 'custom_fields_snapshots_import_failed' );
		}

		$event_logging = get_option( 'custom_fields_snapshots_event_logging', false );
		$log           = $event_logging ? implode( "\n", $this->logger->get_log() ) : '';

		if ( $import_result ) {
			wp_send_json_success(
				array(
					'message' => __( 'Import completed successfully', 'custom-fields-snapshots' ),
					'log'     => $log,
				)
			);
		}

		wp_send_json_error(
			array(
				'message' => $event_logging ? __( 'Import failed. View the event log for more details.', 'custom-fields-snapshots' ) : __( 'Import failed. Enable the event log from the Settings tab for more details.', 'custom-fields-snapshots' ),
				'log'     => $log,
			)
		);
	}

	/**
	 * Validate the structure of the imported data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data The data to validate.
	 * @return bool True if the structure is valid, false otherwise.
	 */
	private function validate_import_structure( $data ) {
		if ( ! is_array( $data ) ) {
			return false;
		}

		foreach ( $data as $group_key => $fields ) {
			if ( ! $this->is_valid_group( $group_key, $fields ) ) {
				return false;
			}

			foreach ( $fields as $field_name => $field_data ) {
				if ( ! $this->is_valid_field( $field_name, $field_data ) ) {
					return false;
				}

				if ( isset( $field_data['options'] ) && ! $this->validate_option_value( $field_data['options'] ) ) {
					return false;
				}

				if ( isset( $field_data['users'] ) && ! $this->is_valid_user_data( $field_data['users'] ) ) {
					return false;
				}

				if ( isset( $field_data['post_types'] ) && ! $this->is_valid_post_types_data( $field_data['post_types'] ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Validate a group key and its fields.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $group_key The group key to validate.
	 * @param mixed $fields    The fields to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_group( $group_key, $fields ) {
		return is_string( $group_key ) && is_array( $fields );
	}

	/**
	 * Validate a field name and its data.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $field_name The field name to validate.
	 * @param mixed $field_data The field data to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_field( $field_name, $field_data ) {
		return is_string( $field_name ) && is_array( $field_data );
	}

	/**
	 * Validate an option value.
	 *
	 * This function recursively validates option values, ensuring they are of
	 * acceptable types (array, string, integer, boolean, null, or object).
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value to validate.
	 * @return bool True if the value is valid, false otherwise.
	 */
	private function validate_option_value( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( ! $this->validate_option_value( $item ) ) {
					return false;
				}
			}
			return true;
		}

		if ( is_string( $value ) || is_int( $value ) || is_bool( $value ) || is_null( $value ) || is_object( $value ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Validate post type data.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_post_type_data( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $post_id => $post_value ) {
			if ( ! is_int( $post_id ) || ! $this->validate_option_value( $post_value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate post types data structure.
	 *
	 * @since 1.0.0
	 *
	 * @param array $post_types_data The post types data to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_post_types_data( $post_types_data ) {
		if ( ! is_array( $post_types_data ) ) {
			return false;
		}

		foreach ( $post_types_data as $post_type => $posts ) {
			if ( ! is_string( $post_type ) || ! is_array( $posts ) ) {
				return false;
			}

			foreach ( $posts as $post_id => $value ) {
				if ( ! is_int( $post_id ) || ! $this->validate_option_value( $value ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Validate user data structure.
	 *
	 * @since 1.1.0
	 *
	 * @param array $user_data The user data to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_user_data( $user_data ) {
		if ( ! is_array( $user_data ) ) {
			return false;
		}

		foreach ( $user_data as $user_id => $value ) {
			if ( ! is_int( $user_id ) || ! $this->validate_option_value( $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Handle export action.
	 *
	 * @since 1.0.0
	 */
	public function handle_export() {
		$nonce = isset( $_POST['custom-fields-snapshots-export-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['custom-fields-snapshots-export-nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'custom-fields-snapshots-export' ) ) {
			wp_die( esc_html__( 'Invalid nonce', 'custom-fields-snapshots' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'custom-fields-snapshots' ) );
		}

		$exports = array(
			'post_types' => array(),
			'post_ids'   => array(),
			'options'    => false,
			'users'      => array(
				'roles' => array(),
				'ids'   => array(),
			),
		);

		$field_groups = isset( $_POST['field_groups'] ) && is_array( $_POST['field_groups'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['field_groups'] ) )
			: array();

		$exports['options'] = filter_input( INPUT_POST, 'options', FILTER_VALIDATE_BOOLEAN );

		$exports['post_types'] = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) )
			: array();

		$exports['post_ids'] = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] )
			? $this->sanitize_post_ids( wp_unslash( $_POST['post_ids'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		$exports['users']['roles'] = isset( $_POST['user_roles'] ) && is_array( $_POST['user_roles'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['user_roles'] ) )
			: array();

		$exports['users']['ids'] = isset( $_POST['users'] ) && is_array( $_POST['users'] )
			? array_map( 'absint', wp_unslash( $_POST['users'] ) )
			: array();

		$errors = array();

		if ( empty( $field_groups ) ) {
			$errors[] = __( 'Please select at least one Field Group.', 'custom-fields-snapshots' );
		}

		if ( ! $exports['options'] &&
			empty( $exports['post_types'] ) &&
			empty( $exports['users']['roles'] ) &&
			empty( $exports['users']['ids'] ) ) {
			$errors[] = __( 'Please select at least one Post Type, User, User Role, or Options.', 'custom-fields-snapshots' );
		}

		if ( ! $exports['options'] &&
			empty( $exports['post_ids'] ) &&
			empty( $exports['users']['roles'] ) &&
			empty( $exports['users']['ids'] ) ) {
			$errors[] = __( 'Please select at least one Post ID, User ID, or User Role.', 'custom-fields-snapshots' );
		}

		if ( ! empty( $errors ) ) {
			$error_message = implode( '<br>', $errors );
			wp_die( esc_html( $error_message ), esc_html( __( 'Export Validation Error', 'custom-fields-snapshots' ) ), array( 'back_link' => true ) );
		}

		/**
		 * Fires before exporting the field data.
		 *
		 * @since 1.0.0
		 *
		 * @param array $field_groups The field groups to export.
		 * @param array $exports      The data types to export.
		 */
		do_action( 'custom_fields_snapshots_export_pre', $field_groups, $exports );

		require_once CUSTOM_FIELDS_SNAPSHOTS_PLUGIN_DIR . 'includes/class-exporter.php';

		$this->exporter = new Exporter();

		$export_data = $this->exporter->export_field_groups( $field_groups, $exports );

		// Check if there's any data to export.
		if ( ! $this->exporter->has_data( $export_data ) ) {
			wp_die( esc_html( __( 'No data to export for the selected field groups and post types, users, or options.', 'custom-fields-snapshots' ) ), esc_html( __( 'Export Error', 'custom-fields-snapshots' ) ), array( 'back_link' => true ) );
		}

		/**
		 * Fires after exporting the field data.
		 *
		 * @since 1.0.0
		 *
		 * @param array $field_groups The field groups exported.
		 * @param array $exports      The data types exported.
		 */
		do_action( 'custom_fields_snapshots_export_post', $field_groups, $exports );

		$json_data = wp_json_encode( $export_data, JSON_PRETTY_PRINT );
		$filename  = sanitize_file_name( 'data-export-' . gmdate( 'Y-m-d' ) ) . '.json';

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo $json_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		exit;
	}

	/**
	 * Sanitize an array of post IDs grouped by post type.
	 *
	 * @since 1.0.0
	 *
	 * @param array $post_ids An array of post IDs grouped by post type.
	 * @return array Sanitized array of post IDs.
	 */
	private function sanitize_post_ids( $post_ids ) {
		if ( ! is_array( $post_ids ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $post_ids as $post_type => $ids ) {
			$sanitized_post_type = sanitize_key( $post_type );
			$sanitized_ids       = array();

			if ( ! is_array( $ids ) ) {
				$ids = array( $ids );
			}

			foreach ( $ids as $id ) {
				$sanitized_ids[] = absint( $id );
			}

			$sanitized[ $sanitized_post_type ] = array_filter( $sanitized_ids );
		}

		return $sanitized;
	}

	/**
	 * Register plugin settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		if ( $this->is_multisite ) {
			$this->register_network_settings();
			if ( ! is_network_admin() ) {
				$this->register_site_specific_settings();
			}
		} else {
			$this->register_site_specific_settings();
			$this->register_single_site_delete_data_setting();
		}
	}

	/**
	 * Register network-wide settings.
	 *
	 * @since 1.0.0
	 */
	private function register_network_settings() {
		add_site_option( 'custom_fields_snapshots_delete_plugin_data', false );

		register_setting(
			'custom_fields_snapshots_network_settings',
			'custom_fields_snapshots_delete_plugin_data',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
			)
		);

		add_settings_section(
			'custom_fields_snapshots_network_general_settings',
			esc_html__( 'Network Settings', 'custom-fields-snapshots' ),
			array( $this, 'render_network_settings_section' ),
			'custom_fields_snapshots_network_settings'
		);

		add_settings_field(
			'custom_fields_snapshots_delete_plugin_data',
			esc_html__( 'Delete plugin data on uninstall', 'custom-fields-snapshots' ),
			array( $this, 'render_delete_data_field' ),
			'custom_fields_snapshots_network_settings',
			'custom_fields_snapshots_network_general_settings'
		);
	}

	/**
	 * Register site-specific settings.
	 *
	 * @since 1.0.0
	 */
	private function register_site_specific_settings() {
		register_setting(
			'custom-fields-snapshots-settings',
			'custom_fields_snapshots_event_logging',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( $this, 'sanitize_site_settings' ),
				'show_in_rest'      => true,
			)
		);

		add_settings_section(
			'custom_fields_snapshots_general_settings',
			esc_html__( 'Settings', 'custom-fields-snapshots' ),
			array( $this, 'render_settings_section' ),
			'custom-fields-snapshots-settings'
		);

		add_settings_field(
			'custom_fields_snapshots_event_logging',
			esc_html__( 'Event Logging', 'custom-fields-snapshots' ),
			array( $this, 'render_enable_logging_field' ),
			'custom-fields-snapshots-settings',
			'custom_fields_snapshots_general_settings'
		);
	}

	/**
	 * Register single-site delete data setting.
	 *
	 * @since 1.0.0
	 */
	private function register_single_site_delete_data_setting() {
		register_setting(
			'custom-fields-snapshots-settings',
			'custom_fields_snapshots_delete_plugin_data',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( $this, 'sanitize_site_settings' ),
				'show_in_rest'      => true,
			)
		);

		add_settings_field(
			'custom_fields_snapshots_delete_plugin_data',
			esc_html__( 'Delete plugin data on uninstall', 'custom-fields-snapshots' ),
			array( $this, 'render_delete_data_field' ),
			'custom-fields-snapshots-settings',
			'custom_fields_snapshots_general_settings'
		);
	}

	/**
	 * Sanitizes and validates site-specific settings.
	 *
	 * This function sanitizes the input value to a boolean and adds a settings
	 * error message if any setting has changed. It ensures the message is only
	 * added once per settings update.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $value The value to sanitize.
	 * @return bool The sanitized boolean value.
	 */
	public function sanitize_site_settings( $value ) {
		static $updated = false;

		$sanitized_value = rest_sanitize_boolean( $value );
		$current_filter  = current_filter();
		$prefix          = 'sanitize_option_';
		$option_name     = ( strpos( $current_filter, $prefix ) === 0 ) ? substr( $current_filter, strlen( $prefix ) ) : $current_filter;
		$old_value       = rest_sanitize_boolean( get_option( $option_name ) );

		if ( ! $updated && $old_value !== $sanitized_value ) {
			add_settings_error(
				'custom_fields_snapshots_messages',
				'settings_updated',
				__( 'Settings updated.', 'custom-fields-snapshots' ),
				'updated'
			);

			$updated = true;
		}

		return $sanitized_value;
	}


	/**
	 * Render the enable logging field.
	 *
	 * @since 1.0.0
	 */
	public function render_enable_logging_field() {
		$event_logging = get_option( 'custom_fields_snapshots_event_logging', false );

		?>
		<label>
			<input type="checkbox" name="custom_fields_snapshots_event_logging" value="1" <?php checked( $event_logging, true ); ?>>
			<?php esc_html_e( 'Show detailed log after the import process', 'custom-fields-snapshots' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, a detailed log will be displayed after the import process.', 'custom-fields-snapshots' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the delete plugin data on uninstall field.
	 *
	 * @since 1.0.0
	 */
	public function render_delete_data_field() {
		$delete_data = $this->is_multisite
			? get_site_option( 'custom_fields_snapshots_delete_plugin_data', false )
			: get_option( 'custom_fields_snapshots_delete_plugin_data', false );

		?>
		<label>
			<input type="checkbox" name="custom_fields_snapshots_delete_plugin_data" value="1" <?php checked( $delete_data, true ); ?>>
			<?php esc_html_e( 'Delete all plugin data when uninstalling', 'custom-fields-snapshots' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( "If enabled, all plugin-specific settings will be removed upon uninstallation. This will not affect your ACF field data or content created using the plugin - only the plugin's internal settings will be deleted.", 'custom-fields-snapshots' ); ?>
		</p>
		<?php
	}

	/**
	 * Get the primary color of the current admin color scheme.
	 *
	 * This function retrieves the user's admin color preference and returns
	 * the corresponding primary color. If the color scheme is not found or
	 * is invalid, it returns a default color.
	 *
	 * @since 1.0.0
	 *
	 * @return string The hex color code for the admin primary color.
	 */
	private function get_admin_primary_color() {
		$admin_color = get_user_option( 'admin_color' );

		$colors = array(
			'fresh'     => '#0073aa',
			'light'     => '#04a4cc',
			'modern'    => '#3858e9',
			'blue'      => '#e1a948',
			'coffee'    => '#c7a589',
			'ectoplasm' => '#a3b745',
			'midnight'  => '#e14d43',
			'ocean'     => '#9ebaa0',
			'sunrise'   => '#dd823b',
		);

		$color = isset( $colors[ $admin_color ] ) ? $colors[ $admin_color ] : '#2271b1';

		return sanitize_hex_color( $color );
	}
}
