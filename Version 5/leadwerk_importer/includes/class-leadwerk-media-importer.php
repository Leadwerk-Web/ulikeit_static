<?php
/**
 * Medienimport: Dateien als Attachments anlegen, Deduplizierung über Pfad/Meta.
 *
 * @package Leadwerk_Importer
 */
class Leadwerk_Media_Importer {

	protected $source_root = '';
	protected $attachment_map = array();
	protected $dry_run = false;

	public function __construct( $source_root, $dry_run = false ) {
		$this->source_root = rtrim( $source_root, '/\\' );
		$this->dry_run     = $dry_run;
		add_filter( 'upload_mimes', array( $this, 'allow_extra_mimes' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_mime_detection' ), 10, 5 );
	}

	public function allow_extra_mimes( $mimes ) {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';
		$mimes['ico']  = 'image/x-icon';
		return $mimes;
	}

	public function fix_mime_detection( $data, $file, $filename, $mimes, $real_mime = '' ) {
		if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
			return $data;
		}
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$map = array(
			'svg'  => 'image/svg+xml',
			'svgz' => 'image/svg+xml',
			'ico'  => 'image/x-icon',
		);
		if ( isset( $map[ $ext ] ) ) {
			$data['ext']             = $ext;
			$data['type']            = $map[ $ext ];
			$data['proper_filename'] = false;
		}
		return $data;
	}

	/**
	 * Importiert eine Datei und gibt Attachment-ID zurück.
	 *
	 * @param string $relative_path Pfad relativ zu source_root.
	 * @return int 0 bei Fehler oder Dry-Run.
	 */
	public function import_file( $relative_path ) {
		$full_path = $this->source_root . DIRECTORY_SEPARATOR . str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $relative_path );
		if ( ! is_file( $full_path ) ) {
			Leadwerk_Logger::log( "Media skip (missing): $relative_path" );
			return 0;
		}
		$norm = $this->normalize_path( $relative_path );
		if ( isset( $this->attachment_map[ $norm ] ) ) {
			return (int) $this->attachment_map[ $norm ];
		}
		$existing = $this->find_attachment_by_source_path( $norm );
		if ( $existing ) {
			$this->attachment_map[ $norm ] = $existing;
			$this->ensure_attachment_file_exists( (int) $existing, $full_path );
			Leadwerk_Logger::log( "Media bereits vorhanden: $relative_path => $existing" );
			return (int) $existing;
		}
		if ( $this->dry_run ) {
			Leadwerk_Logger::log( "Media would import: $relative_path" );
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( wp_basename( $full_path ) );
		copy( $full_path, $tmp );
		$file_array = array(
			'name'     => wp_basename( $full_path ),
			'tmp_name' => $tmp,
		);
		$id = media_handle_sideload( $file_array, 0, null );
		if ( file_exists( $tmp ) ) {
			@unlink( $tmp );
		}
		if ( is_wp_error( $id ) ) {
			Leadwerk_Logger::log( "Media error $relative_path: " . $id->get_error_message() );
			return 0;
		}
		update_post_meta( $id, 'leadwerk_source_path', $norm );
		$this->ensure_attachment_file_exists( (int) $id, $full_path );
		$this->attachment_map[ $norm ] = $id;
		Leadwerk_Logger::log( "Media imported: $relative_path => $id" );
		return (int) $id;
	}

	public function get_attachment_id_by_source( $relative_path ) {
		$norm = $this->normalize_path( $relative_path );
		if ( isset( $this->attachment_map[ $norm ] ) ) {
			return (int) $this->attachment_map[ $norm ];
		}
		$id = $this->find_attachment_by_source_path( $norm );
		if ( $id ) {
			$this->attachment_map[ $norm ] = $id;
		}
		return $id;
	}

	protected function normalize_path( $path ) {
		$path = str_replace( array( '\\', '//' ), array( '/', '/' ), $path );
		$path = str_replace( array( "\xE2\x80\x93", "\xE2\x80\x94" ), '-', $path );
		return trim( $path, '/' );
	}

	protected function find_attachment_by_source_path( $norm ) {
		$q = new WP_Query( array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'meta_key'       => 'leadwerk_source_path',
			'meta_value'     => $norm,
			'fields'         => 'ids',
			'posts_per_page' => 1,
		) );
		$ids = $q->get_posts();
		return ! empty( $ids ) ? (int) $ids[0] : 0;
	}

	protected function ensure_attachment_file_exists( $attachment_id, $source_full_path ) {
		if ( $this->dry_run || $attachment_id <= 0 || ! is_file( $source_full_path ) ) {
			return;
		}

		$attached_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( '' === $attached_file ) {
			return;
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			Leadwerk_Logger::log( 'Media repair skipped: uploads directory unavailable for attachment ' . $attachment_id, 'warning' );
			return;
		}

		$relative_path = ltrim( str_replace( array( '\\', '/' ), DIRECTORY_SEPARATOR, $attached_file ), DIRECTORY_SEPARATOR );
		$target_path   = trailingslashit( $uploads['basedir'] ) . $relative_path;

		if ( is_file( $target_path ) ) {
			return;
		}

		if ( ! wp_mkdir_p( dirname( $target_path ) ) ) {
			Leadwerk_Logger::log( 'Media repair failed: target directory missing for attachment ' . $attachment_id . ' (' . dirname( $target_path ) . ')', 'warning' );
			return;
		}

		if ( ! copy( $source_full_path, $target_path ) ) {
			Leadwerk_Logger::log( 'Media repair failed: could not copy ' . $source_full_path . ' to ' . $target_path, 'warning' );
			return;
		}

		if ( wp_attachment_is_image( $attachment_id ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$metadata = wp_generate_attachment_metadata( $attachment_id, $target_path );
			if ( is_array( $metadata ) && ! is_wp_error( $metadata ) ) {
				wp_update_attachment_metadata( $attachment_id, $metadata );
			}
		}

		Leadwerk_Logger::log( 'Media file repaired: attachment ' . $attachment_id . ' => ' . $attached_file );
	}
}
