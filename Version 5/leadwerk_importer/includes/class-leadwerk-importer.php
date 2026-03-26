<?php
/**
 * Haupt-Importer: Manifest einlesen, Pages anlegen/aktualisieren, Medien importieren.
 *
 * @package Leadwerk_Importer
 */
class Leadwerk_Importer {

	protected $dry_run = true;
	protected $manifest = array();
	protected $manifest_dir = '';
	protected $source_root = '';
	protected $media_importer = null;

	public function __construct( $apply = false ) {
		$this->dry_run      = ! $apply;
		$this->manifest_dir = LEADWERK_IMPORTER_PATH . 'manifest/';
		$this->load_manifest();
		$this->source_root = $this->manifest['source_root'] ?? '';
		if ( $this->source_root === '' && defined( 'LEADWERK_IMPORT_SOURCE_ROOT' ) ) {
			$this->source_root = LEADWERK_IMPORT_SOURCE_ROOT;
		}
		if ( $this->source_root === '' || ! is_dir( $this->source_root ) ) {
			$bundled = LEADWERK_IMPORTER_PATH . 'source_assets';
			if ( is_dir( $bundled ) ) {
				$this->source_root = $bundled;
				Leadwerk_Logger::log( 'Quellordner: Plugin-eigene source_assets.' );
			}
		}
		$this->source_root = (string) apply_filters( 'leadwerk_import_source_root', $this->source_root );
		if ( $this->source_root !== '' && is_dir( $this->source_root ) ) {
			$this->media_importer = new Leadwerk_Media_Importer( $this->source_root, $this->dry_run );
		}
	}

	protected function load_manifest() {
		$path = $this->manifest_dir . 'mapping.json';
		if ( ! is_file( $path ) ) {
			Leadwerk_Logger::log( 'Manifest nicht gefunden: ' . $path );
			$this->manifest = array( 'pages' => array() );
			return;
		}
		$json = file_get_contents( $path );
		$data = json_decode( $json, true );
		$this->manifest = is_array( $data ) ? $data : array( 'pages' => array() );
		Leadwerk_Logger::log( 'Manifest geladen: ' . count( $this->manifest['pages'] ?? array() ) . ' Seiten' );
	}

	public function run() {
		if ( function_exists( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
			@set_time_limit( 300 );
		}
		Leadwerk_Logger::log( $this->dry_run ? '--- Dry-Run ---' : '--- Import (Apply) ---' );
		if ( ! $this->dry_run ) {
			$this->apply_site_identity();
		}
		$pages = $this->manifest['pages'] ?? array();
		foreach ( $pages as $page_config ) {
			$this->process_page( $page_config );
		}
		$this->run_media_import();
		if ( ! $this->dry_run && $this->media_importer ) {
			$this->set_site_icon();
		}
		// Startseite: Custom Fields aus index.html befüllen.
		if ( ! $this->dry_run && $this->source_root !== '' ) {
			$this->fill_structured_pages();
		}
		// Options befüllen.
		if ( ! $this->dry_run ) {
			$this->fill_options();
		}
		Leadwerk_Logger::save();
	}

	protected function fill_structured_pages() {
		$filler   = new Leadwerk_ACF_Filler();
		$page_map = array(
			'ulikeit-home-v1'     => array( $filler, 'fill_front_page' ),
			'ulikeit-user-v1'     => array( $filler, 'fill_user_page' ),
			'ulikeit-haendler-v1' => array( $filler, 'fill_haendler_page' ),
			'ulikeit-impressum-v1' => array( $filler, 'fill_impressum_page' ),
			'ulikeit-datenschutz-v1' => array( $filler, 'fill_datenschutz_page' ),
		);

		foreach ( $page_map as $source_key => $callback ) {
			$post_id = $this->find_page_by_source_key( $source_key );
			if ( ! $post_id || ! is_callable( $callback ) ) {
				continue;
			}

			call_user_func( $callback, $post_id, $this->source_root );
		}
	}

	protected function run_media_import() {
		if ( $this->source_root === '' || ! is_dir( $this->source_root ) ) {
			return;
		}
		$files = $this->collect_media_files( $this->source_root, '' );
		$count = count( $files );
		Leadwerk_Logger::log( "Medien in source_assets: $count Datei(en)" );
		foreach ( $files as $relative_path ) {
			if ( $this->media_importer ) {
				$this->media_importer->import_file( $relative_path );
			} else {
				Leadwerk_Logger::log( "Media would import: $relative_path" );
			}
		}
	}

	protected function collect_media_files( $dir, $base ) {
		$allowed = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'mp4', 'webm', 'mov', 'mp3', 'wav', 'ico', 'woff', 'woff2' );
		$out     = array();
		if ( ! is_dir( $dir ) ) {
			return $out;
		}
		$items = @scandir( $dir );
		if ( ! is_array( $items ) ) {
			return $out;
		}
		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}
			$full = $dir . DIRECTORY_SEPARATOR . $item;
			$rel  = $base === '' ? $item : $base . '/' . $item;
			if ( is_dir( $full ) ) {
				$out = array_merge( $out, $this->collect_media_files( $full, $rel ) );
			} elseif ( is_file( $full ) ) {
				$ext = strtolower( pathinfo( $item, PATHINFO_EXTENSION ) );
				if ( in_array( $ext, $allowed, true ) ) {
					$out[] = $rel;
				}
			}
		}
		return $out;
	}

	protected function process_page( $config ) {
		$source_key = $config['source_key'] ?? '';
		if ( $source_key === '' ) {
			Leadwerk_Logger::log( 'Page ohne source_key übersprungen' );
			return;
		}
		$existing = $this->find_page_by_source_key( $source_key );
		$title    = $config['title'] ?? 'Untitled';
		$slug     = $config['slug'] ?? sanitize_title( $title );
		$status   = $config['post_status'] ?? 'publish';
		$content  = '';
		if ( ! empty( $config['content_file'] ) ) {
			$content_path = $this->manifest_dir . $config['content_file'];
			if ( is_file( $content_path ) ) {
				$content = file_get_contents( $content_path );
			}
		}
		if ( '' === trim( $content ) ) {
			$content = $this->get_default_block_content( $source_key );
		}
		$post_data = array(
			'post_type'    => $config['target_type'] ?? 'page',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_status'  => $status,
			'post_content' => $content,
		);
		if ( $existing ) {
			$post_data['ID'] = $existing;
			if ( ! $this->dry_run ) {
				wp_update_post( $post_data );
				Leadwerk_Logger::log( "Page aktualisiert: $title (ID $existing)" );
			} else {
				Leadwerk_Logger::log( "Page würde aktualisiert: $title (ID $existing)" );
			}
		} else {
			if ( ! $this->dry_run ) {
				$id = wp_insert_post( $post_data );
				if ( $id && ! is_wp_error( $id ) ) {
					update_post_meta( $id, 'leadwerk_source_key', $source_key );
					Leadwerk_Logger::log( "Page angelegt: $title (ID $id)" );
					$existing = $id;
				} else {
					Leadwerk_Logger::log( "Fehler beim Anlegen: $title" );
					return;
				}
			} else {
				Leadwerk_Logger::log( "Page würde angelegt: $title (Slug: $slug)" );
				return;
			}
		}
		if ( ! empty( $config['is_front_page'] ) && $existing && ! $this->dry_run ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', (int) $existing );
			Leadwerk_Logger::log( "Startseite gesetzt: ID $existing" );
		}
		if ( $existing && ! $this->dry_run && ! empty( $config['seo'] ) ) {
			$this->apply_seo_meta( $existing, $config['seo'] );
		}
	}

	protected function apply_seo_meta( $post_id, $seo ) {
		$fields_written = array();
		if ( ! empty( $seo['title'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $seo['title'] ) );
			$fields_written[] = 'title';
		}
		if ( ! empty( $seo['meta_description'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $seo['meta_description'] ) );
			$fields_written[] = 'metadesc';
		}
		if ( ! empty( $seo['focus_keyphrase'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( $seo['focus_keyphrase'] ) );
			$fields_written[] = 'focuskw';
		}
		if ( ! empty( $seo['meta_robots'] ) ) {
			$robots = sanitize_text_field( $seo['meta_robots'] );
			if ( strpos( $robots, 'noindex' ) !== false ) {
				update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', '1' );
				$fields_written[] = 'noindex';
			}
			if ( strpos( $robots, 'nofollow' ) !== false ) {
				update_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', '1' );
				$fields_written[] = 'nofollow';
			}
		}
		if ( ! empty( $seo['og_title'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_opengraph-title', sanitize_text_field( $seo['og_title'] ) );
			$fields_written[] = 'og:title';
		}
		if ( ! empty( $seo['og_description'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_opengraph-description', sanitize_text_field( $seo['og_description'] ) );
			$fields_written[] = 'og:description';
		}
		if ( ! empty( $fields_written ) ) {
			Leadwerk_Logger::log( "SEO-Meta für ID $post_id: " . implode( ', ', $fields_written ) );
		}
	}

	protected function get_default_block_content( $source_key ) {
		$map = array(
			'ulikeit-home-v1'     => '<!-- wp:acf/ulikeit-home-sections /-->',
			'ulikeit-user-v1'     => '<!-- wp:acf/ulikeit-user-sections /-->',
			'ulikeit-haendler-v1' => '<!-- wp:acf/ulikeit-haendler-sections /-->',
		);

		return $map[ $source_key ] ?? '';
	}

	protected function fill_options() {
		if ( ! function_exists( 'update_field' ) ) {
			Leadwerk_Logger::log( 'Options übersprungen (update_field nicht verfügbar).' );
			return;
		}
		$fields_set = array();

		// Logo
		$logo_path = 'images/logo.png';
		$logo_id   = $this->resolve_attachment( $logo_path );
		if ( $logo_id ) {
			update_field( 'logo', $logo_id, 'option' );
			$fields_set[] = 'logo=' . $logo_id;
		}

		// Footer-Logo
		$footer_logo_path = 'images/logo-weiss.png';
		$footer_logo_id   = $this->resolve_attachment( $footer_logo_path );
		if ( $footer_logo_id ) {
			update_field( 'footer_logo', $footer_logo_id, 'option' );
			$fields_set[] = 'footer_logo=' . $footer_logo_id;
		}

		// Footer Text
		update_field( 'footer_text', 'U-like-it verbindet lokale Händler und Gastronomie mit Kund:innen in der Nähe. Zeitgesteuerte Deals reservieren, vor Ort per QR-Code einlösen – so belebt die App deine Innenstadt.', 'option' );
		$fields_set[] = 'footer_text';

		// Copyright
		update_field( 'copyright_text', '© ' . date( 'Y' ) . ' U-like-it. Alle Rechte vorbehalten.', 'option' );
		$fields_set[] = 'copyright_text';

		// App Store URLs
		update_field( 'app_store_url', '#', 'option' );
		update_field( 'google_play_url', '#', 'option' );
		$fields_set[] = 'store_urls';

		// App Store Badge images
		$apple_badge_id = $this->resolve_attachment( 'images/apple_app_store_badge.png' );
		if ( $apple_badge_id ) {
			update_field( 'app_store_badge', $apple_badge_id, 'option' );
		}
		$google_badge_id = $this->resolve_attachment( 'images/google-play-badge.png' );
		if ( $google_badge_id ) {
			update_field( 'google_play_badge', $google_badge_id, 'option' );
		}

		Leadwerk_Logger::log( 'Options befüllt: ' . implode( ', ', $fields_set ) );
	}

	protected function resolve_attachment( $source_path ) {
		if ( $this->media_importer ) {
			$id = $this->media_importer->get_attachment_id_by_source( $source_path );
			if ( $id ) {
				return $id;
			}
		}
		$filler = new Leadwerk_ACF_Filler();
		return $filler->get_attachment_id_by_source( $source_path );
	}

	protected function apply_site_identity() {
		$site_title   = $this->manifest['site_title'] ?? '';
		$site_tagline = $this->manifest['site_tagline'] ?? '';
		if ( $site_title ) {
			update_option( 'blogname', $site_title );
			Leadwerk_Logger::log( "Site-Titel gesetzt: $site_title" );
		}
		if ( $site_tagline ) {
			update_option( 'blogdescription', $site_tagline );
			Leadwerk_Logger::log( "Site-Tagline gesetzt: $site_tagline" );
		}
	}

	protected function set_site_icon() {
		$favicon_path = 'images/favicon.png';
		$id = $this->media_importer->get_attachment_id_by_source( $favicon_path );
		if ( ! $id ) {
			$norm = trim( str_replace( array( '\\', '//' ), '/', $favicon_path ), '/' );
			$q = new WP_Query( array(
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'meta_key'       => 'leadwerk_source_path',
				'meta_value'     => $norm,
				'fields'         => 'ids',
				'posts_per_page' => 1,
			) );
			$ids = $q->get_posts();
			if ( ! empty( $ids ) ) {
				$id = (int) $ids[0];
			}
		}
		if ( $id ) {
			update_option( 'site_icon', $id );
			Leadwerk_Logger::log( "Favicon (site_icon) gesetzt: Attachment-ID $id" );
		} else {
			Leadwerk_Logger::log( 'Favicon: Attachment nicht gefunden.' );
		}
	}

	protected function find_page_by_source_key( $source_key ) {
		$q = new WP_Query( array(
			'post_type'      => 'page',
			'post_status'    => 'any',
			'meta_key'       => 'leadwerk_source_key',
			'meta_value'     => $source_key,
			'fields'         => 'ids',
			'posts_per_page' => 1,
		) );
		$ids = $q->get_posts();
		return ! empty( $ids ) ? (int) $ids[0] : 0;
	}
}
