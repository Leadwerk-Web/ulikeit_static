<?php
/**
 * Einfaches Logging für den Import.
 *
 * @package Leadwerk_Importer
 */
class Leadwerk_Logger {

	protected static $log = '';

	public static function log( $message ) {
		self::$log .= '[' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $message . "\n";
	}

	public static function get_log() {
		return self::$log;
	}

	public static function save() {
		update_option( 'leadwerk_import_log', self::$log, false );
	}
}
