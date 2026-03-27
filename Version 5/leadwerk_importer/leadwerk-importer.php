<?php
/**
 * Plugin Name: Leadwerk Importer
 * Description: Import statische U-like-it-Inhalte in WordPress Seiten, Medien und Leadwerk-Felder.
 * Version: 1.1.0
 * Author: Leadwerk
 * Text Domain: leadwerk-importer
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Leadwerk_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LEADWERK_IMPORTER_VERSION', '1.1.0' );
define( 'LEADWERK_IMPORTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'LEADWERK_IMPORTER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Ensure the lightweight Leadwerk Fields API is available to the importer.
 *
 * @return void
 */
function leadwerk_importer_bootstrap_fields_api() {
	if ( function_exists( 'get_field' ) && function_exists( 'update_field' ) && class_exists( 'Leadwerk_Content_Schema' ) ) {
		return;
	}

	$fields_includes = dirname( LEADWERK_IMPORTER_PATH ) . '/leadwerk_fields/includes/';
	$schema_file     = $fields_includes . 'class-leadwerk-content-schema.php';
	$api_file        = $fields_includes . 'class-leadwerk-fields-api.php';
	$functions_file  = $fields_includes . 'leadwerk-fields-functions.php';

	if ( ! class_exists( 'Leadwerk_Content_Schema' ) && is_file( $schema_file ) ) {
		require_once $schema_file;
	}

	if ( ! class_exists( 'Leadwerk_Fields_API' ) && is_file( $api_file ) ) {
		require_once $api_file;
	}

	if ( class_exists( 'Leadwerk_Fields_API' ) ) {
		Leadwerk_Fields_API::init();
	}

	if ( ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) && is_file( $functions_file ) ) {
		require_once $functions_file;
	}
}

leadwerk_importer_bootstrap_fields_api();

require_once LEADWERK_IMPORTER_PATH . 'includes/class-leadwerk-importer.php';
require_once LEADWERK_IMPORTER_PATH . 'includes/class-leadwerk-media-importer.php';
require_once LEADWERK_IMPORTER_PATH . 'includes/class-leadwerk-logger.php';
require_once LEADWERK_IMPORTER_PATH . 'includes/class-leadwerk-acf-filler.php';

/**
 * Admin menu entry.
 *
 * @return void
 */
function leadwerk_importer_menu() {
	add_management_page(
		__( 'Leadwerk Import', 'leadwerk-importer' ),
		__( 'Leadwerk Import', 'leadwerk-importer' ),
		'manage_options',
		'leadwerk-import',
		'leadwerk_importer_admin_page'
	);
}
add_action( 'admin_menu', 'leadwerk_importer_menu' );

/**
 * Enqueue importer admin assets.
 *
 * @param string $hook Current admin hook.
 * @return void
 */
function leadwerk_importer_admin_assets( $hook ) {
	if ( 'tools_page_leadwerk-import' !== $hook ) {
		return;
	}

	$script_path = LEADWERK_IMPORTER_PATH . 'assets/admin-import.js';
	$style_path  = LEADWERK_IMPORTER_PATH . 'assets/admin-import.css';

	if ( is_file( $style_path ) ) {
		wp_enqueue_style(
			'leadwerk-importer-admin',
			LEADWERK_IMPORTER_URL . 'assets/admin-import.css',
			array(),
			(string) filemtime( $style_path )
		);
	}

	if ( is_file( $script_path ) ) {
		wp_enqueue_script(
			'leadwerk-importer-admin',
			LEADWERK_IMPORTER_URL . 'assets/admin-import.js',
			array(),
			(string) filemtime( $script_path ),
			true
		);
	}

	wp_localize_script(
		'leadwerk-importer-admin',
		'leadwerkImporter',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'leadwerk_import_ajax' ),
			'state'   => Leadwerk_Logger::get_state(),
			'strings' => array(
				'startDryRun' => 'Dry-Run starten',
				'startImport' => 'Import starten',
				'idle'        => 'Noch kein Import gestartet.',
				'running'     => 'Import laeuft...',
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'leadwerk_importer_admin_assets' );

/**
 * Importer admin page.
 *
 * @return void
 */
function leadwerk_importer_admin_page() {
	$run     = isset( $_GET['run'] ) && '1' === $_GET['run'] && current_user_can( 'manage_options' );
	$dry_run = isset( $_GET['dry_run'] ) && '1' === $_GET['dry_run'];
	$state   = Leadwerk_Logger::get_state();

	if ( $run && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'leadwerk_import_run' ) ) {
		$importer = new Leadwerk_Importer( ! $dry_run );
		$importer->run();
		$state = Leadwerk_Logger::get_state();
		echo '<div class="notice notice-success"><p>Synchroner Import ausgefuehrt. Fuer kuenftige Laeufe bitte die Live-Progress-Oberflaeche unten verwenden.</p></div>';
	}
	?>
	<div class="wrap leadwerk-importer-admin">
		<h1><?php esc_html_e( 'Leadwerk Import', 'leadwerk-importer' ); ?></h1>
		<p>U-like-it Inhalte, Medien und Leadwerk-Felder importieren. Der Live-Import laeuft schrittweise, speichert seinen Status und zeigt den Fortschritt in Echtzeit an.</p>

		<div class="leadwerk-importer-toolbar">
			<button type="button" class="button" data-leadwerk-start-import="dry-run">Dry-Run starten</button>
			<button type="button" class="button button-primary" data-leadwerk-start-import="apply">Import mit Live-Progress starten</button>
			<button type="button" class="button" data-leadwerk-reset-progress>Ansicht zuruecksetzen</button>
		</div>

		<div class="leadwerk-importer-progress" data-leadwerk-importer-app>
			<div class="leadwerk-importer-progress__summary">
				<div>
					<strong>Status</strong>
					<div data-import-status><?php echo esc_html( ! empty( $state['status'] ) ? ucfirst( (string) $state['status'] ) : 'Idle' ); ?></div>
				</div>
				<div>
					<strong>Aktiver Schritt</strong>
					<div data-import-step><?php echo esc_html( (string) ( $state['current_step'] ?? 'preflight' ) ); ?></div>
				</div>
				<div>
					<strong>Aktives Element</strong>
					<div data-import-item><?php echo esc_html( (string) ( $state['current_item'] ?? '' ) ); ?></div>
				</div>
			</div>

			<div class="leadwerk-importer-progress__bar">
				<div class="leadwerk-importer-progress__bar-fill" data-import-overall-fill style="width:<?php echo esc_attr( (string) (int) ( $state['overall_percent'] ?? 0 ) ); ?>%"></div>
			</div>
			<div class="leadwerk-importer-progress__percent"><span data-import-overall-percent><?php echo esc_html( (string) (int) ( $state['overall_percent'] ?? 0 ) ); ?></span>%</div>

			<div class="leadwerk-importer-steps" data-import-steps></div>

			<div class="leadwerk-importer-counters">
				<div class="leadwerk-importer-counter"><span>Success</span><strong data-import-success><?php echo esc_html( (string) (int) ( $state['success_count'] ?? 0 ) ); ?></strong></div>
				<div class="leadwerk-importer-counter"><span>Warnings</span><strong data-import-warnings><?php echo esc_html( (string) (int) ( $state['warning_count'] ?? 0 ) ); ?></strong></div>
				<div class="leadwerk-importer-counter"><span>Errors</span><strong data-import-errors><?php echo esc_html( (string) (int) ( $state['error_count'] ?? 0 ) ); ?></strong></div>
			</div>

			<div class="leadwerk-importer-log">
				<h2>Live Log</h2>
				<div class="leadwerk-importer-log__list" data-import-log></div>
			</div>
		</div>

		<details class="leadwerk-importer-fallback">
			<summary>Fallback: synchronen Legacy-Import ausfuehren</summary>
			<p>Nur verwenden, wenn die Live-Progress-Oberflaeche in deinem Setup blockiert wird.</p>
			<p>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'run' => '1', 'dry_run' => '1' ), admin_url( 'admin.php?page=leadwerk-import' ) ), 'leadwerk_import_run' ) ); ?>" class="button">Legacy Dry-Run</a>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'run' => '1' ), admin_url( 'admin.php?page=leadwerk-import' ) ), 'leadwerk_import_run' ) ); ?>" class="button">Legacy Import</a>
			</p>
		</details>

		<?php if ( isset( $_GET['log'] ) ) : ?>
			<pre style="background:#f5f5f5;padding:1em;max-height:420px;overflow:auto;"><?php echo esc_html( Leadwerk_Logger::get_log() ); ?></pre>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Check AJAX permissions.
 *
 * @return void
 */
function leadwerk_importer_verify_ajax_request() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
	}

	check_ajax_referer( 'leadwerk_import_ajax', 'nonce' );
}

/**
 * Start one live import job.
 *
 * @return void
 */
function leadwerk_importer_ajax_start() {
	leadwerk_importer_verify_ajax_request();

	$dry_run = ! empty( $_POST['dry_run'] );
	$state   = Leadwerk_Logger::get_state();

	if ( Leadwerk_Logger::has_active_job() ) {
		wp_send_json_success( array( 'state' => $state ) );
	}

	$importer = new Leadwerk_Importer( ! $dry_run );
	$state    = $importer->build_initial_job_state();
	wp_send_json_success( array( 'state' => $state ) );
}
add_action( 'wp_ajax_leadwerk_import_start', 'leadwerk_importer_ajax_start' );

/**
 * Run the next batch for the active import job.
 *
 * @return void
 */
function leadwerk_importer_ajax_step() {
	leadwerk_importer_verify_ajax_request();

	$state = Leadwerk_Logger::get_state();
	if ( empty( $state['job_id'] ) ) {
		wp_send_json_error( array( 'message' => 'No import job found.' ), 404 );
	}

	$importer = new Leadwerk_Importer( empty( $state['dry_run'] ) );
	$state    = $importer->run_next_batch( $state );
	wp_send_json_success( array( 'state' => $state ) );
}
add_action( 'wp_ajax_leadwerk_import_step', 'leadwerk_importer_ajax_step' );

/**
 * Read current import state.
 *
 * @return void
 */
function leadwerk_importer_ajax_state() {
	leadwerk_importer_verify_ajax_request();
	wp_send_json_success( array( 'state' => Leadwerk_Logger::get_state() ) );
}
add_action( 'wp_ajax_leadwerk_import_state', 'leadwerk_importer_ajax_state' );

/**
 * Reset the stored job state after completion.
 *
 * @return void
 */
function leadwerk_importer_ajax_reset() {
	leadwerk_importer_verify_ajax_request();

	$state = Leadwerk_Logger::get_state();
	if ( ! empty( $state['job_id'] ) && in_array( (string) ( $state['status'] ?? '' ), array( 'running', 'booting' ), true ) ) {
		wp_send_json_error( array( 'message' => 'The import is still running.' ), 409 );
	}

	Leadwerk_Logger::reset_job();
	wp_send_json_success( array( 'state' => array() ) );
}
add_action( 'wp_ajax_leadwerk_import_reset', 'leadwerk_importer_ajax_reset' );
