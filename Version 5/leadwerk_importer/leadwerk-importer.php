<?php
/**
 * Plugin Name: Leadwerk Importer
 * Description: Import statischer U-like-it-Inhalte in WordPress (Pages, Medien, Custom Fields). Dry-Run, Re-Import, Logging.
 * Version: 1.0.0
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

define( 'LEADWERK_IMPORTER_VERSION', '1.0.0' );
define( 'LEADWERK_IMPORTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'LEADWERK_IMPORTER_URL', plugin_dir_url( __FILE__ ) );

$leadwerk_schema_file = dirname( LEADWERK_IMPORTER_PATH ) . '/leadwerk_fields/includes/class-leadwerk-content-schema.php';
if ( ! class_exists( 'Leadwerk_Content_Schema' ) && is_file( $leadwerk_schema_file ) ) {
	require_once $leadwerk_schema_file;
}

require_once LEADWERK_IMPORTER_PATH . 'includes/class-leadwerk-importer.php';
require_once LEADWERK_IMPORTER_PATH . 'includes/class-leadwerk-media-importer.php';
require_once LEADWERK_IMPORTER_PATH . 'includes/class-leadwerk-logger.php';
require_once LEADWERK_IMPORTER_PATH . 'includes/class-leadwerk-acf-filler.php';

/**
 * Admin-Menü und Ausführung.
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

function leadwerk_importer_admin_page() {
	$dry_run = isset( $_GET['dry_run'] ) && $_GET['dry_run'] === '1';
	$run     = isset( $_GET['run'] ) && $_GET['run'] === '1' && current_user_can( 'manage_options' );
	if ( $run && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'leadwerk_import_run' ) ) {
		if ( function_exists( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
			@set_time_limit( 300 );
		}
		$importer = new Leadwerk_Importer( ! $dry_run );
		$importer->run();
		echo '<div class="notice notice-success"><p>Import ausgeführt. Siehe <a href="' . esc_url( admin_url( 'admin.php?page=leadwerk-import&log=1' ) ) . '">Log</a>.</p></div>';
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Leadwerk Import', 'leadwerk-importer' ); ?></h1>
		<p>Statische U-like-it-Inhalte (Pages, Medien, Custom Fields) importieren. Quelle: Manifest + angegebener Quellordner.</p>
		<p>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'run' => '1', 'dry_run' => '1' ), admin_url( 'admin.php?page=leadwerk-import' ) ), 'leadwerk_import_run' ) ); ?>" class="button">Dry-Run (keine Änderungen)</a>
			&nbsp;
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'run' => '1' ), admin_url( 'admin.php?page=leadwerk-import' ) ), 'leadwerk_import_run' ) ); ?>" class="button button-primary">Import ausführen</a>
		</p>
		<?php if ( isset( $_GET['log'] ) ) : ?>
			<pre style="background:#f5f5f5;padding:1em;max-height:400px;overflow:auto;"><?php echo esc_html( get_option( 'leadwerk_import_log', '' ) ); ?></pre>
		<?php endif; ?>
	</div>
	<?php
}
