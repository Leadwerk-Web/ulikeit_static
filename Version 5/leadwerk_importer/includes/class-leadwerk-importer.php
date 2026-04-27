<?php
/**
 * Main importer for U-like-it pages, media and shared options.
 *
 * @package Leadwerk_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leadwerk_Importer {

	/**
	 * Dry-run mode.
	 *
	 * @var bool
	 */
	protected $dry_run = true;

	/**
	 * Manifest data.
	 *
	 * @var array<string,mixed>
	 */
	protected $manifest = array();

	/**
	 * Manifest directory path.
	 *
	 * @var string
	 */
	protected $manifest_dir = '';

	/**
	 * Source root path.
	 *
	 * @var string
	 */
	protected $source_root = '';

	/**
	 * Media importer.
	 *
	 * @var Leadwerk_Media_Importer|null
	 */
	protected $media_importer = null;

	/**
	 * Filler instance.
	 *
	 * @var Leadwerk_ACF_Filler
	 */
	protected $filler;

	/**
	 * Runtime lookup for canonical pages.
	 *
	 * @var array<string,int>
	 */
	protected $page_lookup = array();

	/**
	 * Constructor.
	 *
	 * @param bool $apply Whether changes should be applied.
	 */
	public function __construct( $apply = false ) {
		$this->dry_run      = ! $apply;
		$this->manifest_dir = LEADWERK_IMPORTER_PATH . 'manifest/';
		$this->filler       = new Leadwerk_ACF_Filler();
		$this->load_manifest();

		$this->source_root = $this->manifest['source_root'] ?? '';
		if ( '' === $this->source_root && defined( 'LEADWERK_IMPORT_SOURCE_ROOT' ) ) {
			$this->source_root = LEADWERK_IMPORT_SOURCE_ROOT;
		}

		if ( '' === $this->source_root || ! is_dir( $this->source_root ) ) {
			$bundled = LEADWERK_IMPORTER_PATH . 'source_assets';
			if ( is_dir( $bundled ) ) {
				$this->source_root = $bundled;
			}
		}

		$this->source_root = (string) apply_filters( 'leadwerk_import_source_root', $this->source_root );
		if ( '' !== $this->source_root && is_dir( $this->source_root ) ) {
			$this->media_importer = new Leadwerk_Media_Importer( $this->source_root, $this->dry_run );
		}

		$this->filler->set_source_root( $this->source_root );
	}

	/**
	 * Load manifest.
	 *
	 * @return void
	 */
	protected function load_manifest() {
		$path = $this->manifest_dir . 'mapping.json';
		if ( ! is_file( $path ) ) {
			Leadwerk_Logger::log( 'Manifest nicht gefunden: ' . $path, 'error' );
			$this->manifest = array( 'pages' => array() );
			return;
		}

		$json = file_get_contents( $path );
		$data = json_decode( (string) $json, true );
		$this->manifest = is_array( $data ) ? $data : array( 'pages' => array() );
	}

	/**
	 * Run the importer synchronously.
	 *
	 * @return void
	 */
	public function run() {
		if ( function_exists( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
			@set_time_limit( 300 );
		}

		$job            = $this->build_initial_job_state();
		$max_iterations = 1200;
		$iterations     = 0;

		while ( $iterations < $max_iterations && ! in_array( (string) ( $job['status'] ?? '' ), array( 'completed', 'failed' ), true ) ) {
			$job = $this->run_next_batch(
				$job,
				array(
					'page_upsert' => 1,
					'media_import'=> 10,
					'page_fill'   => 1,
				)
			);
			++$iterations;
		}

		if ( $iterations >= $max_iterations && ! in_array( (string) ( $job['status'] ?? '' ), array( 'completed', 'failed' ), true ) ) {
			Leadwerk_Logger::record_result( 'error', 'Importer abgebrochen: zu viele Verarbeitungsschritte ohne Abschluss.', 'import-loop' );
			Leadwerk_Logger::finish_job(
				'failed',
				array(
					'current_item' => 'Importer aborted after reaching the safety iteration limit.',
				)
			);
		}

		Leadwerk_Logger::save();
	}

	/**
	 * Create or resume one import job state.
	 *
	 * @return array<string,mixed>
	 */
	public function build_initial_job_state() {
		Leadwerk_Logger::force_reset_stale_job();

		$steps = $this->get_step_definitions();
		$job   = Leadwerk_Logger::start_job(
			array(
				'dry_run' => $this->dry_run,
				'status'  => 'running',
			)
		);

		if ( ! empty( $job['steps'] ) && ! empty( $job['job_id'] ) && in_array( (string) ( $job['status'] ?? '' ), array( 'running', 'booting' ), true ) ) {
			return $job;
		}

		$job = Leadwerk_Logger::set_state(
			array(
				'job_id'          => sanitize_text_field( wp_generate_uuid4() ),
				'dry_run'         => $this->dry_run,
				'status'          => 'running',
				'current_step'    => 'preflight',
				'current_item'    => '',
				'started_at'      => current_time( 'mysql', true ),
				'finished_at'     => '',
				'processed'       => 0,
				'success_count'   => 0,
				'warning_count'   => 0,
				'error_count'     => 0,
				'steps'           => $steps,
				'queues'          => array(
					'pages' => array_values( (array) ( $this->manifest['pages'] ?? array() ) ),
					'media' => array(),
				),
				'cursor'          => array(
					'page_upsert' => 0,
					'media_import'=> 0,
					'page_fill'   => 0,
				),
				'page_lookup'     => array(),
				'results'         => array(
					'pages'    => array(),
					'media'    => array(),
					'summary'  => array(),
					'blocking' => array(),
				),
				'log_tail'        => array(),
				'overall_percent' => 0,
				'step_percent'    => 0,
			)
		);

		Leadwerk_Logger::log( $this->dry_run ? '--- Dry-Run ---' : '--- Import (Apply) ---' );
		return $job;
	}

	/**
	 * Run the next importer batch.
	 *
	 * @param array<string,mixed> $job_state   Current state.
	 * @param array<string,int>   $batch_sizes Step-specific batch sizes.
	 * @return array<string,mixed>
	 */
	public function run_next_batch( $job_state, $batch_sizes = array() ) {
		$job_state = is_array( $job_state ) ? $job_state : $this->build_initial_job_state();
		if ( in_array( (string) ( $job_state['status'] ?? '' ), array( 'completed', 'failed' ), true ) ) {
			return $job_state;
		}

		$this->restore_runtime_from_state( $job_state );

		$step_key = $this->get_next_step_key( $job_state );
		if ( '' === $step_key ) {
			return $this->complete_job( $job_state );
		}

		switch ( $step_key ) {
			case 'preflight':
				$job_state = $this->perform_preflight_step( $job_state );
				break;
			case 'page_upsert':
				$job_state = $this->perform_page_upsert_step( $job_state, max( 1, (int) ( $batch_sizes['page_upsert'] ?? 1 ) ) );
				break;
			case 'media_scan':
				$job_state = $this->perform_media_scan_step( $job_state );
				break;
			case 'media_import':
				$job_state = $this->perform_media_import_step( $job_state, max( 1, (int) ( $batch_sizes['media_import'] ?? 6 ) ) );
				break;
			case 'page_fill':
				$job_state = $this->perform_page_fill_step( $job_state, max( 1, (int) ( $batch_sizes['page_fill'] ?? 1 ) ) );
				break;
			case 'options':
				$job_state = $this->perform_options_step( $job_state );
				break;
			case 'finalize':
				$job_state = $this->perform_finalize_step( $job_state );
				break;
		}

		$this->persist_runtime_into_state( $job_state );
		$job_state = $this->refresh_processed_count( $job_state );
		$job_state = $this->merge_runtime_job_state( $job_state );
		Leadwerk_Logger::set_state( $job_state );

		if ( 'failed' === ( $job_state['status'] ?? '' ) ) {
			return Leadwerk_Logger::get_state();
		}

		if ( '' === $this->get_next_step_key( $job_state ) ) {
			return $this->complete_job( $job_state );
		}

		return Leadwerk_Logger::get_state();
	}

	/**
	 * Return step definitions for the progress bar.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function get_step_definitions() {
		$page_total = count( (array) ( $this->manifest['pages'] ?? array() ) );

		return array(
			'preflight' => array(
				'label'     => 'Preflight',
				'total'     => 1,
				'processed' => 0,
				'status'    => 'pending',
			),
			'page_upsert' => array(
				'label'     => 'Page Upsert',
				'total'     => $page_total,
				'processed' => 0,
				'status'    => 'pending',
			),
			'media_scan' => array(
				'label'     => 'Media Scan',
				'total'     => 1,
				'processed' => 0,
				'status'    => 'pending',
			),
			'media_import' => array(
				'label'     => 'Media Import',
				'total'     => 0,
				'processed' => 0,
				'status'    => 'pending',
			),
			'page_fill' => array(
				'label'     => 'Fill Structured Pages',
				'total'     => $page_total,
				'processed' => 0,
				'status'    => 'pending',
			),
			'options' => array(
				'label'     => 'Options',
				'total'     => 1,
				'processed' => 0,
				'status'    => 'pending',
			),
			'finalize' => array(
				'label'     => 'Finalize',
				'total'     => 1,
				'processed' => 0,
				'status'    => 'pending',
			),
		);
	}

	/**
	 * Preflight step.
	 *
	 * @param array<string,mixed> $job_state State.
	 * @return array<string,mixed>
	 */
	protected function perform_preflight_step( $job_state ) {
		$step_key  = 'preflight';
		$job_state = $this->mark_step_running( $job_state, $step_key, 'Checking importer prerequisites' );
		$blocking  = array();

		if ( empty( $this->manifest['pages'] ) || ! is_array( $this->manifest['pages'] ) ) {
			$blocking[] = 'Das Manifest enthaelt keine importierbaren Seiten.';
		}

		if ( '' === $this->source_root || ! is_dir( $this->source_root ) ) {
			$blocking[] = 'source_assets wurde nicht gefunden.';
		}

		if ( ! class_exists( 'Leadwerk_Content_Schema' ) ) {
			$blocking[] = 'Leadwerk_Content_Schema ist nicht geladen.';
		}

		if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
			$blocking[] = 'Leadwerk Fields API ist nicht aktiv.';
		}

		$missing_blocks = $this->get_missing_dynamic_blocks();
		if ( ! empty( $missing_blocks ) ) {
			$blocking[] = 'Dynamische U-like-it Bloecke sind nicht registriert: ' . implode( ', ', $missing_blocks ) . '.';
		}

		foreach ( (array) ( $this->manifest['pages'] ?? array() ) as $page_config ) {
			$source_key = (string) ( $page_config['source_key'] ?? '' );
			$field_name = (string) ( $page_config['field_name'] ?? '' );
			if ( '' === $source_key ) {
				$blocking[] = 'Eine Seite im Manifest hat keinen source_key.';
				continue;
			}

			if ( '' === $field_name || ! Leadwerk_Content_Schema::get_group( $field_name ) ) {
				$blocking[] = 'Schema fehlt fuer ' . $source_key . '.';
			}

			$source_file = (string) ( $page_config['source_file'] ?? '' );
			if ( '' === $source_file || ! is_file( $this->resolve_source_path( $source_file ) ) ) {
				$blocking[] = 'HTML-Datei fehlt fuer ' . $source_key . '.';
			}
		}

		if ( ! empty( $blocking ) ) {
			foreach ( $blocking as $message ) {
				Leadwerk_Logger::record_result( 'error', $message, 'preflight' );
			}

			$job_state['results']['blocking'] = array_values( $blocking );
			$job_state['status']              = 'failed';
			$job_state                        = $this->mark_step_finished( $job_state, $step_key, 'failed', 'Preflight failed' );

			return Leadwerk_Logger::finish_job(
				'failed',
				array(
					'results'      => $job_state['results'],
					'steps'        => $job_state['steps'],
					'current_step' => $step_key,
					'current_item' => 'Preflight failed.',
				)
			);
		}

		Leadwerk_Logger::record_result( 'success', 'Preflight complete.', 'preflight' );
		return $this->mark_step_finished( $job_state, $step_key, 'completed', 'Preflight complete' );
	}

	/**
	 * Page upsert step.
	 *
	 * @param array<string,mixed> $job_state  State.
	 * @param int                 $batch_size Batch size.
	 * @return array<string,mixed>
	 */
	protected function perform_page_upsert_step( $job_state, $batch_size ) {
		$step_key = 'page_upsert';
		$queue    = array_values( (array) ( $job_state['queues']['pages'] ?? array() ) );
		$total    = count( $queue );
		$cursor   = (int) ( $job_state['cursor'][ $step_key ] ?? 0 );

		if ( 0 === $total ) {
			return $this->mark_step_finished( $job_state, $step_key, 'skipped', 'No pages to upsert' );
		}

		$job_state['steps'][ $step_key ]['total'] = $total;
		$job_state = $this->mark_step_running( $job_state, $step_key, 'Creating or updating pages' );

		for ( $count = 0; $count < $batch_size && $cursor < $total; $count++ ) {
			$page_config = $queue[ $cursor ];
			$source_key  = sanitize_key( (string) ( $page_config['source_key'] ?? '' ) );
			$title       = (string) ( $page_config['title'] ?? ( $page_config['source_key'] ?? 'page' ) );

			$job_state = $this->mark_step_running( $job_state, $step_key, $title );
			$page_result = $this->process_page( $page_config );

			if ( ! empty( $page_result['post_id'] ) ) {
				$this->page_lookup[ $source_key ] = (int) $page_result['post_id'];
			}

			$job_state['results']['pages'][ $source_key ] = array_merge(
				(array) ( $job_state['results']['pages'][ $source_key ] ?? array() ),
				$page_result,
				array(
					'source_key' => $source_key,
					'title'      => $title,
					'field_name' => (string) ( $page_config['field_name'] ?? '' ),
					'source_file'=> (string) ( $page_config['source_file'] ?? '' ),
				)
			);

			$cursor++;
			$job_state['cursor'][ $step_key ]             = $cursor;
			$job_state['steps'][ $step_key ]['processed'] = $cursor;
		}

		if ( $cursor >= $total ) {
			return $this->mark_step_finished( $job_state, $step_key, 'completed', 'Page upsert complete' );
		}

		return $job_state;
	}

	/**
	 * Media scan step.
	 *
	 * @param array<string,mixed> $job_state State.
	 * @return array<string,mixed>
	 */
	protected function perform_media_scan_step( $job_state ) {
		$step_key  = 'media_scan';
		$job_state = $this->mark_step_running( $job_state, $step_key, 'Scanning bundled assets' );
		$files     = $this->collect_media_files( $this->source_root, '' );

		$job_state['queues']['media']                    = array_values( $files );
		$job_state['steps']['media_import']['total']     = count( $files );
		$job_state['steps']['media_import']['processed'] = 0;
		$job_state['steps']['media_import']['status']    = empty( $files ) ? 'skipped' : 'pending';

		Leadwerk_Logger::log( 'Medien in source_assets: ' . count( $files ) . ' Datei(en)' );
		return $this->mark_step_finished( $job_state, $step_key, 'completed', 'Media scan complete' );
	}

	/**
	 * Media import step.
	 *
	 * @param array<string,mixed> $job_state  State.
	 * @param int                 $batch_size Batch size.
	 * @return array<string,mixed>
	 */
	protected function perform_media_import_step( $job_state, $batch_size ) {
		$step_key = 'media_import';
		$queue    = array_values( (array) ( $job_state['queues']['media'] ?? array() ) );
		$total    = count( $queue );

		if ( 0 === $total ) {
			return $this->mark_step_finished( $job_state, $step_key, 'skipped', 'No media files to import' );
		}

		$cursor = (int) ( $job_state['cursor'][ $step_key ] ?? 0 );
		$job_state['steps'][ $step_key ]['total'] = $total;
		$job_state = $this->mark_step_running( $job_state, $step_key, 'Importing media assets' );

		for ( $count = 0; $count < $batch_size && $cursor < $total; $count++ ) {
			$relative_path = (string) $queue[ $cursor ];
			$job_state     = $this->mark_step_running( $job_state, $step_key, $relative_path );

			if ( $this->media_importer ) {
				$id = $this->media_importer->import_file( $relative_path );
				if ( $this->dry_run || $id ) {
					Leadwerk_Logger::increment( 'success_count' );
				} else {
					Leadwerk_Logger::increment( 'error_count' );
				}
			} else {
				Leadwerk_Logger::log( 'Media importer nicht verfuegbar: ' . $relative_path, 'warning' );
				Leadwerk_Logger::increment( 'warning_count' );
			}

			$cursor++;
			$job_state['cursor'][ $step_key ]             = $cursor;
			$job_state['steps'][ $step_key ]['processed'] = $cursor;
		}

		if ( $cursor >= $total ) {
			return $this->mark_step_finished( $job_state, $step_key, 'completed', 'Media import complete' );
		}

		return $job_state;
	}

	/**
	 * Fill structured page fields.
	 *
	 * @param array<string,mixed> $job_state  State.
	 * @param int                 $batch_size Batch size.
	 * @return array<string,mixed>
	 */
	protected function perform_page_fill_step( $job_state, $batch_size ) {
		$step_key = 'page_fill';
		$queue    = array_values( (array) ( $job_state['queues']['pages'] ?? array() ) );
		$total    = count( $queue );

		if ( 0 === $total ) {
			return $this->mark_step_finished( $job_state, $step_key, 'skipped', 'No structured pages to fill' );
		}

		$cursor = (int) ( $job_state['cursor'][ $step_key ] ?? 0 );
		$job_state['steps'][ $step_key ]['total'] = $total;
		$job_state = $this->mark_step_running( $job_state, $step_key, 'Filling structured content' );

		for ( $count = 0; $count < $batch_size && $cursor < $total; $count++ ) {
			$page_config   = (array) $queue[ $cursor ];
			$source_key    = sanitize_key( (string) ( $page_config['source_key'] ?? '' ) );
			$field_name    = (string) ( $page_config['field_name'] ?? '' );
			$title         = (string) ( $page_config['title'] ?? $source_key );
			$resolution    = $this->resolve_page_target( $page_config );
			$post_id       = (int) ( $resolution['id'] ?? 0 );
			$page_result   = (array) ( $job_state['results']['pages'][ $source_key ] ?? array() );
			$job_state     = $this->mark_step_running( $job_state, $step_key, $title );
			$previous      = $post_id && function_exists( 'get_field' ) ? get_field( $field_name, $post_id ) : null;
			$previous_val  = $this->filler->validate_group_value( $field_name, $previous );
			$current_state = array(
				'field_status'            => 'error',
				'field_message'           => 'Structured content konnte nicht geschrieben werden.',
				'failure_reason'          => 'write_failed',
				'payload_validation'      => array(),
				'readback_validation'     => array(),
				'layout_diagnostics'      => array(),
				'parser_diagnostics'      => array(),
				'render_ready'            => false,
				'used_last_good_fallback' => false,
			);

			if ( ! $post_id && ! $this->dry_run ) {
				$current_state['field_message'] = 'Seite fuer Structured Fill nicht gefunden.';
				$page_result                    = array_merge( $page_result, $current_state );
				$job_state['results']['pages'][ $source_key ] = $page_result;
				Leadwerk_Logger::record_result( 'error', $title . ': ' . $current_state['field_message'], 'page_fill:' . $source_key );

				++$cursor;
				$job_state['cursor'][ $step_key ]             = $cursor;
				$job_state['steps'][ $step_key ]['processed'] = $cursor;
				continue;
			}

			$payload            = $this->filler->build_page_payload( $page_config );
			$payload_validation = (array) ( $payload['validation'] ?? array() );
			$layout_diag        = $this->compact_layout_diagnostics( (array) ( $payload['layout_diagnostics'] ?? array() ) );
			$parser_diag        = (array) ( $payload['parser_diagnostics'] ?? array() );
			$readback           = $previous;
			$readback_val       = $previous_val;

			$current_state['payload_validation'] = $this->compact_validation( $payload_validation );
			$current_state['layout_diagnostics'] = $layout_diag;
			$current_state['parser_diagnostics'] = $parser_diag;

			if ( empty( $payload_validation['has_visible_content'] ) ) {
				$current_state['field_status']  = ! empty( $previous_val['has_visible_content'] ) ? 'warning' : 'error';
				$current_state['field_message'] = 'Importer payload ist leer.';
				$current_state['failure_reason'] = $this->has_selector_miss_in_diagnostics( $layout_diag ) ? 'selector_miss' : 'parser_empty';
				$current_state['render_ready']  = ! empty( $previous_val['has_visible_content'] );
				Leadwerk_Logger::log(
					'Keine sichtbaren Inhalte aus ' . (string) ( $page_config['source_file'] ?? $source_key ) . ' extrahiert. Field=' . $field_name . '. ' . $this->format_parser_diagnostics( $parser_diag ),
					'warning'
				);

				if ( ! $this->dry_run && empty( $previous_val['has_visible_content'] ) ) {
					$this->force_draft( $post_id );
				}
			} else {
				if ( $this->dry_run ) {
					$readback     = $payload['value'] ?? array();
					$readback_val = $payload_validation;
				} else {
					update_field( $field_name, $payload['value'], $post_id );
					$this->sync_post_content_if_needed( $post_id, $page_config, $payload['value'] );
					$readback     = get_field( $field_name, $post_id );
					$readback_val = $this->filler->validate_group_value( $field_name, $readback );
				}

				if ( ! empty( $readback_val['has_visible_content'] ) ) {
					$current_state['field_status']       = 'success';
					$current_state['field_message']      = $this->dry_run ? 'Structured content wuerde verifiziert geschrieben.' : 'Structured content geschrieben und verifiziert.';
					$current_state['failure_reason']     = '';
					$current_state['render_ready']       = true;
					$current_state['readback_validation'] = $this->compact_validation( $readback_val );

					if ( ! $this->dry_run ) {
						$this->save_last_good_snapshot( $post_id, $field_name, $readback, $readback_val, 'readback' );
						$this->maybe_update_post_status( $post_id, (string) ( $page_config['post_status'] ?? 'publish' ) );
					}
				} else {
					$current_state['field_status']   = ! empty( $previous_val['has_visible_content'] ) ? 'warning' : 'error';
					$current_state['field_message']  = 'Strukturierte Felddaten konnten nicht verifiziert werden.';
					$current_state['failure_reason'] = 'readback_empty';

					if ( ! $this->dry_run && ! empty( $previous_val['has_visible_content'] ) ) {
						update_field( $field_name, $previous, $post_id );
						$this->sync_post_content_if_needed( $post_id, $page_config, $previous );
						$readback     = get_field( $field_name, $post_id );
						$readback_val = $this->filler->validate_group_value( $field_name, $readback );

						if ( ! empty( $readback_val['has_visible_content'] ) ) {
							$this->save_last_good_snapshot( $post_id, $field_name, $readback, $readback_val, 'restored_previous' );
							$current_state['field_message']  = 'Schreiben fehlgeschlagen, vorheriger Inhalt wurde wiederhergestellt.';
							$current_state['failure_reason'] = 'restored_previous';
							$current_state['render_ready']   = true;
						}
					}

					if ( ! $this->dry_run && empty( $previous_val['has_visible_content'] ) ) {
						$this->force_draft( $post_id );
					}
				}
			}

			$current_state['readback_validation'] = $this->compact_validation( $readback_val );
			$page_result                          = array_merge(
				$page_result,
				$current_state,
				array(
					'post_id'    => $post_id,
					'matched_by' => (string) ( $resolution['matched_by'] ?? '' ),
				)
			);
			$job_state['results']['pages'][ $source_key ] = $page_result;

			Leadwerk_Logger::record_result(
				(string) ( $current_state['field_status'] ?? 'error' ),
				$title . ': ' . (string) ( $current_state['field_message'] ?? '' ),
				'page_fill:' . $source_key
			);

			++$cursor;
			$job_state['cursor'][ $step_key ]             = $cursor;
			$job_state['steps'][ $step_key ]['processed'] = $cursor;
		}

		if ( $cursor >= $total ) {
			return $this->mark_step_finished( $job_state, $step_key, 'completed', 'Structured page fill complete' );
		}

		return $job_state;
	}

	/**
	 * Apply shared options.
	 *
	 * @param array<string,mixed> $job_state State.
	 * @return array<string,mixed>
	 */
	protected function perform_options_step( $job_state ) {
		$step_key  = 'options';
		$job_state = $this->mark_step_running( $job_state, $step_key, 'Applying shared options' );

		$this->apply_site_identity();
		$this->fill_options();
		$this->set_site_icon();

		Leadwerk_Logger::increment( 'success_count' );
		return $this->mark_step_finished( $job_state, $step_key, 'completed', 'Options complete' );
	}

	/**
	 * Final verification step.
	 *
	 * @param array<string,mixed> $job_state State.
	 * @return array<string,mixed>
	 */
	protected function perform_finalize_step( $job_state ) {
		$step_key  = 'finalize';
		$job_state = $this->mark_step_running( $job_state, $step_key, 'Verifying render readiness' );

		$issues = array();
		foreach ( (array) ( $this->manifest['pages'] ?? array() ) as $page_config ) {
			$source_key = (string) ( $page_config['source_key'] ?? '' );
			if ( '' === $source_key || $this->dry_run ) {
				continue;
			}

			$resolution = $this->resolve_page_target( $page_config );
			$post_id    = (int) ( $resolution['id'] ?? 0 );
			$slug       = (string) ( $page_config['slug'] ?? '' );
			$field_name = (string) ( $page_config['field_name'] ?? '' );

			if ( ! $post_id ) {
				$issues[] = 'Finalize fehlgeschlagen. Zielseite nicht gefunden: ' . $source_key . '.';
				continue;
			}

			if ( '' !== $slug && $slug !== (string) get_post_field( 'post_name', $post_id ) ) {
				$issues[] = 'Slug stimmt nicht fuer ' . $source_key . ': erwartet ' . $slug . ', gefunden ' . (string) get_post_field( 'post_name', $post_id ) . '.';
			}

			if ( ! empty( $page_config['is_front_page'] ) && (int) get_option( 'page_on_front' ) !== $post_id ) {
				$issues[] = 'Die Startseite ist nicht der erwarteten Seite fuer ' . $source_key . ' zugewiesen.';
			}

			$assigned_pages = $this->get_pages_by_source_key( $source_key );
			if ( count( $assigned_pages ) > 1 ) {
				$issues[] = 'Mehrere Seiten tragen denselben source_key ' . $source_key . ': ' . implode( ', ', array_map( 'intval', $assigned_pages ) ) . '.';
			}

			$expected_block_content = $this->get_default_block_content( $source_key );
			if ( '' !== $expected_block_content && ! $this->page_has_expected_block_content( $post_id, $source_key ) ) {
				$issues[] = 'Der dynamische Block-Inhalt fehlt fuer ' . $source_key . ' auf Post-ID ' . $post_id . '.';
			}

			if ( '' !== $field_name ) {
				$effective_state = $this->get_effective_field_state( $post_id, $field_name, $this->filler );
				$validation      = (array) ( $effective_state['validation'] ?? array() );
				$render_ready    = ! empty( $validation['has_visible_content'] );

				$job_state['results']['pages'][ $source_key ] = array_merge(
					(array) ( $job_state['results']['pages'][ $source_key ] ?? array() ),
					array(
						'post_id'                 => $post_id,
						'render_ready'            => $render_ready,
						'used_last_good_fallback' => ! empty( $effective_state['used_last_good_fallback'] ),
						'readback_validation'     => $this->compact_validation( $validation ),
					)
				);

				if ( ! $render_ready ) {
					$issues[] = 'Strukturierte Felddaten fehlen fuer ' . $source_key . ' (' . $field_name . ') auf Post-ID ' . $post_id . '.';
					$this->force_draft( $post_id );
				}
			}
		}

		if ( ! empty( $issues ) ) {
			foreach ( $issues as $issue ) {
				Leadwerk_Logger::record_result( 'error', $issue, 'finalize' );
			}

			$job_state['status'] = 'failed';
			$job_state           = $this->mark_step_finished( $job_state, $step_key, 'failed', 'Finalize failed' );

			return Leadwerk_Logger::finish_job(
				'failed',
				array(
					'results'      => $job_state['results'],
					'steps'        => $job_state['steps'],
					'current_step' => $step_key,
					'current_item' => 'Finalize failed.',
				)
			);
		}

		Leadwerk_Logger::record_result( 'success', 'Finalize complete.', 'finalize' );
		return $this->mark_step_finished( $job_state, $step_key, 'completed', 'Finalize complete' );
	}

	/**
	 * Complete the current job.
	 *
	 * @param array<string,mixed> $job_state State.
	 * @return array<string,mixed>
	 */
	protected function complete_job( $job_state ) {
		$job_state = $this->refresh_processed_count( $job_state );

		return Leadwerk_Logger::finish_job(
			'completed',
			array(
				'steps'        => $job_state['steps'],
				'processed'    => $job_state['processed'],
				'current_step' => 'finalize',
				'current_item' => 'Import complete.',
			)
		);
	}

	/**
	 * Return the next unfinished step key.
	 *
	 * @param array<string,mixed> $job_state State.
	 * @return string
	 */
	protected function get_next_step_key( $job_state ) {
		foreach ( (array) ( $job_state['steps'] ?? array() ) as $step_key => $step ) {
			$status    = sanitize_key( (string) ( $step['status'] ?? 'pending' ) );
			$total     = max( 1, (int) ( $step['total'] ?? 1 ) );
			$processed = (int) ( $step['processed'] ?? 0 );

			if ( in_array( $status, array( 'completed', 'failed', 'skipped' ), true ) ) {
				continue;
			}

			// Auto-promote a running step whose items are all processed.
			if ( 'running' === $status && $processed >= $total ) {
				$job_state['steps'][ $step_key ]['status'] = 'completed';
				Leadwerk_Logger::set_state( $job_state );
				continue;
			}

			if ( $processed < $total ) {
				return (string) $step_key;
			}
		}

		return '';
	}

	/**
	 * Mark one step as running.
	 *
	 * @param array<string,mixed> $job_state State.
	 * @param string              $step_key  Step key.
	 * @param string              $item      Active item.
	 * @return array<string,mixed>
	 */
	protected function mark_step_running( $job_state, $step_key, $item = '' ) {
		$job_state['status']                         = 'running';
		$job_state['current_step']                   = $step_key;
		$job_state['current_item']                   = $item;
		$job_state['steps'][ $step_key ]['status']   = 'running';

		Leadwerk_Logger::set_current( $step_key, $item );
		return $job_state;
	}

	/**
	 * Mark one step as finished.
	 *
	 * @param array<string,mixed> $job_state State.
	 * @param string              $step_key  Step key.
	 * @param string              $status    completed|failed|skipped.
	 * @param string              $item      Active item.
	 * @return array<string,mixed>
	 */
	protected function mark_step_finished( $job_state, $step_key, $status, $item = '' ) {
		$total = max( 0, (int) ( $job_state['steps'][ $step_key ]['total'] ?? 0 ) );

		$job_state['steps'][ $step_key ]['status'] = $status;
		if ( 'completed' === $status ) {
			$job_state['steps'][ $step_key ]['processed'] = $total;
		} elseif ( 'skipped' === $status && 0 === $total ) {
			$job_state['steps'][ $step_key ]['processed'] = 0;
		}

		$job_state['current_step'] = $step_key;
		$job_state['current_item'] = $item;

		return $job_state;
	}

	/**
	 * Refresh aggregate processed count.
	 *
	 * @param array<string,mixed> $job_state State.
	 * @return array<string,mixed>
	 */
	protected function refresh_processed_count( $job_state ) {
		$processed = 0;

		foreach ( (array) ( $job_state['steps'] ?? array() ) as $step ) {
			$processed += (int) ( $step['processed'] ?? 0 );
		}

		$job_state['processed'] = $processed;
		return $job_state;
	}

	/**
	 * Restore importer runtime state.
	 *
	 * @param array<string,mixed> $job_state State.
	 * @return void
	 */
	protected function restore_runtime_from_state( $job_state ) {
		$this->page_lookup = isset( $job_state['page_lookup'] ) && is_array( $job_state['page_lookup'] )
			? array_map( 'intval', $job_state['page_lookup'] )
			: array();
	}

	/**
	 * Persist importer runtime state.
	 *
	 * @param array<string,mixed> $job_state State.
	 * @return void
	 */
	protected function persist_runtime_into_state( &$job_state ) {
		$job_state['page_lookup'] = $this->page_lookup;
	}

	/**
	 * Merge logger-owned runtime state with importer-owned state.
	 *
	 * @param array<string,mixed> $job_state State.
	 * @return array<string,mixed>
	 */
	protected function merge_runtime_job_state( $job_state ) {
		$persisted_state = Leadwerk_Logger::get_state();
		if ( empty( $persisted_state ) || ! is_array( $persisted_state ) ) {
			return $job_state;
		}

		foreach ( array( 'success_count', 'warning_count', 'error_count', 'log_tail' ) as $key ) {
			if ( array_key_exists( $key, $persisted_state ) ) {
				$job_state[ $key ] = $persisted_state[ $key ];
			}
		}

		$persisted_results = isset( $persisted_state['results'] ) && is_array( $persisted_state['results'] ) ? $persisted_state['results'] : array();
		$local_results     = isset( $job_state['results'] ) && is_array( $job_state['results'] ) ? $job_state['results'] : array();
		$job_state['results'] = array_replace_recursive( $persisted_results, $local_results );

		return $job_state;
	}

	/**
	 * Resolve the source HTML file for one source key.
	 *
	 * @param string $source_key Source key.
	 * @return string
	 */
	protected function get_source_file_for_source_key( $source_key ) {
		$page_config = $this->get_page_config_by_source_key( $source_key );
		return (string) ( $page_config['source_file'] ?? '' );
	}

	/**
	 * Resolve one manifest page config by source key.
	 *
	 * @param string $source_key Source key.
	 * @return array<string,mixed>
	 */
	protected function get_page_config_by_source_key( $source_key ) {
		foreach ( (array) ( $this->manifest['pages'] ?? array() ) as $page_config ) {
			$page_config = (array) $page_config;
			if ( (string) ( $page_config['source_key'] ?? '' ) === (string) $source_key ) {
				return $page_config;
			}
		}

		return array();
	}

	/**
	 * Resolve one source file path.
	 *
	 * @param string $relative_path Relative path.
	 * @return string
	 */
	protected function resolve_source_path( $relative_path ) {
		return rtrim( $this->source_root, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $relative_path );
	}

	/**
	 * Collect all importable media files.
	 *
	 * @param string $dir  Directory.
	 * @param string $base Relative base.
	 * @return array<int,string>
	 */
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
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$full = $dir . DIRECTORY_SEPARATOR . $item;
			$rel  = '' === $base ? $item : $base . '/' . $item;

			if ( is_dir( $full ) ) {
				$out = array_merge( $out, $this->collect_media_files( $full, $rel ) );
			} elseif ( is_file( $full ) ) {
				$ext = strtolower( pathinfo( $item, PATHINFO_EXTENSION ) );
				if ( in_array( $ext, $allowed, true ) ) {
					$out[] = $rel;
				}
			}
		}

		sort( $out );
		return $out;
	}

	/**
	 * Create or update one page.
	 *
	 * @param array<string,mixed> $config Page config.
	 * @return array<string,mixed>
	 */
	protected function process_page( $config ) {
		$source_key = (string) ( $config['source_key'] ?? '' );
		if ( '' === $source_key ) {
			Leadwerk_Logger::record_result( 'error', 'Page ohne source_key uebersprungen.', 'page_upsert:missing' );
			return array(
				'post_id'      => 0,
				'page_status'  => 'error',
				'page_message' => 'Page ohne source_key uebersprungen.',
				'matched_by'   => 'missing',
			);
		}

		$resolution = $this->resolve_page_target( $config );
		$existing   = (int) ( $resolution['id'] ?? 0 );
		$matched_by = (string) ( $resolution['matched_by'] ?? 'new' );
		$title      = (string) ( $config['title'] ?? 'Untitled' );
		$slug     = (string) ( $config['slug'] ?? sanitize_title( $title ) );
		$status   = (string) ( $config['post_status'] ?? 'publish' );
		$content  = '';

		if ( ! empty( $config['content_file'] ) ) {
			$content_path = $this->manifest_dir . $config['content_file'];
			if ( is_file( $content_path ) ) {
				$content = (string) file_get_contents( $content_path );
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
			if ( $this->dry_run ) {
				Leadwerk_Logger::record_result( 'success', 'Page wuerde aktualisiert: ' . $title . ' (ID ' . $existing . ', Treffer ueber ' . $matched_by . ')', 'page_upsert:' . $source_key );
			} else {
				wp_update_post( $post_data );
				$this->sync_source_key_assignment( $source_key, $existing );
				Leadwerk_Logger::record_result( 'success', 'Page aktualisiert: ' . $title . ' (ID ' . $existing . ', Treffer ueber ' . $matched_by . ')', 'page_upsert:' . $source_key );
			}
		} else {
			if ( $this->dry_run ) {
				Leadwerk_Logger::record_result( 'success', 'Page wuerde angelegt: ' . $title . ' (Slug ' . $slug . ')', 'page_upsert:' . $source_key );
				return array(
					'post_id'      => 0,
					'page_status'  => 'success',
					'page_message' => 'Page wuerde angelegt.',
					'matched_by'   => 'new',
					'created_new'  => true,
					'post_name'    => $slug,
				);
			}

			$id = wp_insert_post( $post_data );
			if ( $id && ! is_wp_error( $id ) ) {
				$existing = (int) $id;
				$this->sync_source_key_assignment( $source_key, $existing );
				Leadwerk_Logger::record_result( 'success', 'Page angelegt: ' . $title . ' (ID ' . $existing . ')', 'page_upsert:' . $source_key );
			} else {
				$message = is_wp_error( $id ) ? $id->get_error_message() : 'Unbekannter Fehler';
				Leadwerk_Logger::record_result( 'error', 'Fehler beim Anlegen von ' . $title . ': ' . $message, 'page_upsert:' . $source_key );
				return array(
					'post_id'      => 0,
					'page_status'  => 'error',
					'page_message' => $message,
					'matched_by'   => 'new',
				);
			}
		}

		if ( ! empty( $config['is_front_page'] ) && $existing ) {
			if ( $this->dry_run ) {
				Leadwerk_Logger::log( 'Startseite wuerde gesetzt: ID ' . $existing );
			} else {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', (int) $existing );
				Leadwerk_Logger::log( 'Startseite gesetzt: ID ' . $existing );
			}
		}

		if ( $existing && ! empty( $config['seo'] ) ) {
			$this->apply_seo_meta( (int) $existing, (array) $config['seo'] );
		}

		return array(
			'post_id'      => (int) $existing,
			'page_status'  => 'success',
			'page_message' => $existing ? 'Page bereit.' : 'Page fehlt.',
			'matched_by'   => $matched_by,
			'created_new'  => 'new' === $matched_by,
			'post_name'    => $slug,
		);
	}

	/**
	 * Truncate SEO title for Yoast width hints (uses theme helper when active).
	 *
	 * @param string $title      Title.
	 * @param int    $max_chars  Max length.
	 * @return string
	 */
	protected function truncate_seo_title_for_yoast( $title, $max_chars = 58 ) {
		if ( function_exists( 'leadwerk_theme_truncate_seo_title_for_yoast' ) ) {
			return leadwerk_theme_truncate_seo_title_for_yoast( $title, $max_chars );
		}

		$title = trim( (string) $title );
		if ( '' === $title ) {
			return '';
		}
		if ( $max_chars < 8 ) {
			$max_chars = 8;
		}
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strlen( $title ) > $max_chars ) {
			return rtrim( mb_substr( $title, 0, $max_chars - 1 ) ) . '…';
		}
		if ( strlen( $title ) > $max_chars ) {
			return rtrim( substr( $title, 0, $max_chars - 1 ) ) . '…';
		}

		return $title;
	}

	/**
	 * Refresh Yoast indexables after SEO meta import.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	protected function maybe_rebuild_yoast_indexable( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || $this->dry_run ) {
			return;
		}

		if ( function_exists( 'leadwerk_theme_rebuild_yoast_post_indexable' ) ) {
			leadwerk_theme_rebuild_yoast_post_indexable( $post_id );
			return;
		}

		if ( ! function_exists( 'YoastSEO' ) || ! class_exists( '\Yoast\WP\SEO\Integrations\Watchers\Indexable_Post_Watcher', false ) ) {
			return;
		}

		try {
			$yoast = YoastSEO();
			if ( ! is_object( $yoast ) || ! isset( $yoast->classes ) || ! is_object( $yoast->classes ) || ! method_exists( $yoast->classes, 'get' ) ) {
				return;
			}
			$watcher = $yoast->classes->get( \Yoast\WP\SEO\Integrations\Watchers\Indexable_Post_Watcher::class );
			if ( is_object( $watcher ) && method_exists( $watcher, 'build_indexable' ) ) {
				$watcher->build_indexable( $post_id );
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			return;
		}
	}

	/**
	 * Resolve focus keyphrase from manifest-style keys (primary + English fallback).
	 *
	 * @param array<string,mixed> $seo SEO config.
	 * @return string
	 */
	protected function resolve_seo_focus_keyphrase( array $seo ) {
		$kw = isset( $seo['focus_keyphrase'] ) ? trim( (string) $seo['focus_keyphrase'] ) : '';
		if ( '' !== $kw ) {
			return $kw;
		}
		if ( ! empty( $seo['focus_keyphrase_en'] ) ) {
			return trim( (string) $seo['focus_keyphrase_en'] );
		}

		return '';
	}

	/**
	 * Apply SEO meta values.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $seo     SEO config.
	 * @return void
	 */
	protected function apply_seo_meta( $post_id, $seo ) {
		$fields_written = array();

		if ( ! empty( $seo['title'] ) ) {
			if ( ! $this->dry_run ) {
				$seo_title = $this->truncate_seo_title_for_yoast( (string) $seo['title'] );
				update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $seo_title ) );
			}
			$fields_written[] = 'title';
		}

		if ( ! empty( $seo['meta_description'] ) ) {
			if ( ! $this->dry_run ) {
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $seo['meta_description'] ) );
			}
			$fields_written[] = 'metadesc';
		}

		$focus_kw = $this->resolve_seo_focus_keyphrase( $seo );
		if ( '' !== $focus_kw ) {
			if ( ! $this->dry_run ) {
				update_post_meta( $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( $focus_kw ) );
			}
			$fields_written[] = 'focuskw';
		}

		if ( ! empty( $seo['meta_robots'] ) ) {
			$robots = sanitize_text_field( $seo['meta_robots'] );
			if ( false !== strpos( $robots, 'noindex' ) ) {
				if ( ! $this->dry_run ) {
					update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', '1' );
				}
				$fields_written[] = 'noindex';
			}
			if ( false !== strpos( $robots, 'nofollow' ) ) {
				if ( ! $this->dry_run ) {
					update_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', '1' );
				}
				$fields_written[] = 'nofollow';
			}
		}

		if ( ! empty( $seo['og_title'] ) ) {
			if ( ! $this->dry_run ) {
				update_post_meta( $post_id, '_yoast_wpseo_opengraph-title', sanitize_text_field( $seo['og_title'] ) );
			}
			$fields_written[] = 'og:title';
		}

		if ( ! empty( $seo['og_description'] ) ) {
			if ( ! $this->dry_run ) {
				update_post_meta( $post_id, '_yoast_wpseo_opengraph-description', sanitize_text_field( $seo['og_description'] ) );
			}
			$fields_written[] = 'og:description';
		}

		if ( ! empty( $fields_written ) ) {
			Leadwerk_Logger::log( 'SEO-Meta ' . ( $this->dry_run ? 'wuerde gesetzt' : 'gesetzt' ) . ' fuer ID ' . $post_id . ': ' . implode( ', ', $fields_written ) );
		}

		$this->maybe_rebuild_yoast_indexable( (int) $post_id );
	}

	/**
	 * Return default block content.
	 *
	 * @param string $source_key Source key.
	 * @return string
	 */
	protected function get_default_block_content( $source_key ) {
		if ( class_exists( 'Leadwerk_Content_Schema' ) && method_exists( 'Leadwerk_Content_Schema', 'get_default_post_content_for_source_key' ) ) {
			$content = (string) Leadwerk_Content_Schema::get_default_post_content_for_source_key( $source_key );
			if ( '' !== $content ) {
				return $content;
			}
		}

		$map = array(
			'ulikeit-home-v1'     => '<!-- wp:acf/ulikeit-home-sections /-->',
			'ulikeit-user-v1'     => '<!-- wp:acf/ulikeit-user-sections /-->',
			'ulikeit-haendler-v1' => '<!-- wp:acf/ulikeit-haendler-sections /-->',
		);

		return isset( $map[ $source_key ] ) ? $map[ $source_key ] : '';
	}

	/**
	 * Fill shared options from imported assets.
	 *
	 * @return void
	 */
	protected function fill_options() {
		if ( ! function_exists( 'update_field' ) ) {
			Leadwerk_Logger::log( 'Options uebersprungen (update_field nicht verfuegbar).', 'warning' );
			return;
		}

		$fields_set = array();

		$logo_id = $this->resolve_attachment( 'images/logo.png' );
		if ( $logo_id ) {
			if ( ! $this->dry_run ) {
				update_field( 'logo', $logo_id, 'option' );
			}
			$fields_set[] = 'logo=' . $logo_id;
		}

		$footer_logo_id = $this->resolve_attachment( 'images/logo-weiss.png' );
		if ( $footer_logo_id ) {
			if ( ! $this->dry_run ) {
				update_field( 'footer_logo', $footer_logo_id, 'option' );
			}
			$fields_set[] = 'footer_logo=' . $footer_logo_id;
		}

		if ( ! $this->dry_run ) {
			update_field( 'footer_text', 'U-like-it verbindet lokale Haendler und Gastronomie mit Kund:innen in der Naehe. Zeitlich begrenzte Angebote lassen sich in der App entdecken und vor Ort per QR-Code einloesen - so belebt U-like-it deine Innenstadt.', 'option' );
			update_field( 'copyright_text', '&copy; ' . gmdate( 'Y' ) . ' U-like-it. Alle Rechte vorbehalten.', 'option' );
			update_field( 'app_store_url', 'https://apps.apple.com/de/app/u-like-it/id1593884667', 'option' );
			update_field( 'google_play_url', 'https://play.google.com/store/apps/details?id=de.u_like_it', 'option' );
		}

		$fields_set[] = 'footer_text';
		$fields_set[] = 'copyright_text';
		$fields_set[] = 'store_urls';

		$apple_badge_id = $this->resolve_attachment( 'images/apple_app_store_badge.png' );
		if ( $apple_badge_id ) {
			if ( ! $this->dry_run ) {
				update_field( 'app_store_badge', $apple_badge_id, 'option' );
			}
			$fields_set[] = 'app_store_badge=' . $apple_badge_id;
		}

		$google_badge_id = $this->resolve_attachment( 'images/google-play-badge.png' );
		if ( $google_badge_id ) {
			if ( ! $this->dry_run ) {
				update_field( 'google_play_badge', $google_badge_id, 'option' );
			}
			$fields_set[] = 'google_play_badge=' . $google_badge_id;
		}

		Leadwerk_Logger::log( 'Options ' . ( $this->dry_run ? 'wuerden befuellt' : 'befuellt' ) . ': ' . implode( ', ', $fields_set ) );
	}

	/**
	 * Resolve an attachment from the imported source path.
	 *
	 * @param string $source_path Relative source path.
	 * @return int
	 */
	protected function resolve_attachment( $source_path ) {
		if ( $this->media_importer ) {
			$id = $this->media_importer->get_attachment_id_by_source( $source_path );
			if ( $id ) {
				return $id;
			}
		}

		return $this->filler->get_attachment_id_by_source( $source_path );
	}

	/**
	 * Apply site title and tagline.
	 *
	 * @return void
	 */
	protected function apply_site_identity() {
		$site_title   = (string) ( $this->manifest['site_title'] ?? '' );
		$site_tagline = (string) ( $this->manifest['site_tagline'] ?? '' );

		if ( '' !== $site_title ) {
			if ( ! $this->dry_run ) {
				update_option( 'blogname', $site_title );
			}
			Leadwerk_Logger::log( 'Site-Titel ' . ( $this->dry_run ? 'wuerde gesetzt' : 'gesetzt' ) . ': ' . $site_title );
		}

		if ( '' !== $site_tagline ) {
			if ( ! $this->dry_run ) {
				update_option( 'blogdescription', $site_tagline );
			}
			Leadwerk_Logger::log( 'Site-Tagline ' . ( $this->dry_run ? 'wuerde gesetzt' : 'gesetzt' ) . ': ' . $site_tagline );
		}
	}

	/**
	 * Set the WordPress site icon.
	 *
	 * @return void
	 */
	protected function set_site_icon() {
		if ( ! $this->media_importer ) {
			return;
		}

		$id = $this->media_importer->get_attachment_id_by_source( 'images/favicon.png' );
		if ( ! $id ) {
			$q = new WP_Query(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'any',
					'meta_key'       => 'leadwerk_source_path',
					'meta_value'     => 'images/favicon.png',
					'fields'         => 'ids',
					'posts_per_page' => 1,
				)
			);
			$ids = $q->get_posts();
			if ( ! empty( $ids ) ) {
				$id = (int) $ids[0];
			}
		}

		if ( ! $id ) {
			Leadwerk_Logger::log( 'Favicon: Attachment nicht gefunden.', 'warning' );
			return;
		}

		if ( ! $this->dry_run ) {
			update_option( 'site_icon', $id );
		}

		Leadwerk_Logger::log( 'Favicon (site_icon) ' . ( $this->dry_run ? 'wuerde gesetzt' : 'gesetzt' ) . ': Attachment-ID ' . $id );
	}

	/**
	 * Find one imported page by source key.
	 *
	 * @param string $source_key Source key.
	 * @return int
	 */
	protected function find_page_by_source_key( $source_key ) {
		$ids = $this->get_pages_by_source_key( $source_key );
		return ! empty( $ids ) ? (int) $ids[0] : 0;
	}

	/**
	 * Find all imported pages by source key.
	 *
	 * @param string $source_key Source key.
	 * @return array<int,int>
	 */
	protected function get_pages_by_source_key( $source_key ) {
		$q = new WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => 'any',
				'meta_key'       => 'leadwerk_source_key',
				'meta_value'     => $source_key,
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		return array_map( 'intval', (array) $q->get_posts() );
	}

	/**
	 * Find one page by canonical slug.
	 *
	 * @param string $slug Expected slug.
	 * @return int
	 */
	protected function find_page_by_slug( $slug ) {
		if ( '' === $slug ) {
			return 0;
		}

		$q = new WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => 'any',
				'name'           => $slug,
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$ids = $q->get_posts();
		return ! empty( $ids ) ? (int) $ids[0] : 0;
	}

	/**
	 * Resolve the canonical target page for one manifest config.
	 *
	 * Prefers the expected slug over stale source-key links so live URLs are repaired in place.
	 *
	 * @param array<string,mixed> $config Page config.
	 * @return array<string,mixed>
	 */
	protected function resolve_page_target( $config ) {
		$slug        = (string) ( $config['slug'] ?? '' );
		$source_key  = (string) ( $config['source_key'] ?? '' );
		$candidates  = array();
		$slug_page   = $this->find_page_by_slug( $slug );

		if ( $slug_page ) {
			$candidates[ $slug_page ] = 'slug';
		}

		if ( ! empty( $config['is_front_page'] ) ) {
			$front_page_id = (int) get_option( 'page_on_front' );
			if ( $front_page_id > 0 && ! isset( $candidates[ $front_page_id ] ) ) {
				$candidates[ $front_page_id ] = 'front_page';
			}
		}

		foreach ( $this->get_pages_by_source_key( $source_key ) as $linked_id ) {
			if ( ! isset( $candidates[ $linked_id ] ) ) {
				$candidates[ $linked_id ] = 'source_key';
			}
		}

		foreach ( $candidates as $id => $matched_by ) {
			return array(
				'id'         => (int) $id,
				'matched_by' => $matched_by,
			);
		}

		return array(
			'id'         => 0,
			'matched_by' => 'new',
		);
	}

	/**
	 * Keep leadwerk_source_key bound to one canonical page only.
	 *
	 * @param string $source_key Source key.
	 * @param int    $post_id    Canonical post ID.
	 * @return void
	 */
	protected function sync_source_key_assignment( $source_key, $post_id ) {
		if ( '' === $source_key || $post_id <= 0 ) {
			return;
		}

		$linked_ids = $this->get_pages_by_source_key( $source_key );
		foreach ( $linked_ids as $linked_id ) {
			if ( (int) $linked_id !== (int) $post_id ) {
				delete_post_meta( (int) $linked_id, 'leadwerk_source_key', $source_key );
				Leadwerk_Logger::log( 'Source key ' . $source_key . ' von Post-ID ' . (int) $linked_id . ' geloest.' );
			}
		}

		update_post_meta( $post_id, 'leadwerk_source_key', $source_key );
	}

	/**
	 * Return registered dynamic block names that are missing.
	 *
	 * @return array<int,string>
	 */
	protected function get_missing_dynamic_blocks() {
		if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
			return array( 'acf/ulikeit-home-sections', 'acf/ulikeit-user-sections', 'acf/ulikeit-haendler-sections' );
		}

		$registry = WP_Block_Type_Registry::get_instance();
		$required = array(
			'acf/ulikeit-home-sections',
			'acf/ulikeit-user-sections',
			'acf/ulikeit-haendler-sections',
		);
		$missing  = array();

		foreach ( $required as $block_name ) {
			if ( ! $registry->is_registered( $block_name ) ) {
				$missing[] = $block_name;
			}
		}

		return $missing;
	}

	/**
	 * Map one source key to its structured field group.
	 *
	 * @param string $source_key Source key.
	 * @return string
	 */
	protected function get_structured_field_name_for_source_key( $source_key ) {
		$page_config = $this->get_page_config_by_source_key( $source_key );
		return (string) ( $page_config['field_name'] ?? '' );
	}

	/**
	 * Compact verbose validation payloads for job storage.
	 *
	 * @param array<string,mixed> $validation Validation data.
	 * @return array<string,mixed>
	 */
	protected function compact_validation( $validation ) {
		return array(
			'has_visible_content'    => ! empty( $validation['has_visible_content'] ),
			'visible_content_score'  => (int) ( $validation['visible_content_score'] ?? 0 ),
			'expected_layout_count'  => (int) ( $validation['expected_layout_count'] ?? 0 ),
			'parsed_layout_count'    => (int) ( $validation['parsed_layout_count'] ?? 0 ),
			'parsed_section_count'   => (int) ( $validation['parsed_section_count'] ?? 0 ),
			'non_empty_layout_count' => (int) ( $validation['non_empty_layout_count'] ?? 0 ),
			'missing_sections'       => (int) ( $validation['missing_sections'] ?? 0 ),
			'empty_layouts'          => array_slice( array_values( array_unique( (array) ( $validation['empty_layouts'] ?? array() ) ) ), 0, 10 ),
			'empty_fields'           => array_slice( array_values( array_unique( (array) ( $validation['empty_fields'] ?? array() ) ) ), 0, 12 ),
		);
	}

	/**
	 * Compact layout diagnostics for state/log storage.
	 *
	 * @param array<int,array<string,mixed>> $diagnostics Layout diagnostics.
	 * @return array<int,array<string,mixed>>
	 */
	protected function compact_layout_diagnostics( $diagnostics ) {
		$out = array();

		foreach ( (array) $diagnostics as $diagnostic ) {
			$out[] = array(
				'layout'              => (string) ( $diagnostic['layout'] ?? '' ),
				'index'               => (int) ( $diagnostic['index'] ?? 0 ),
				'present'             => ! empty( $diagnostic['present'] ),
				'stored_layout'       => (string) ( $diagnostic['stored_layout'] ?? '' ),
				'selector_miss'       => ! empty( $diagnostic['selector_miss'] ),
				'has_visible_content' => ! empty( $diagnostic['has_visible_content'] ),
				'empty_fields'        => array_slice( array_values( (array) ( $diagnostic['empty_fields'] ?? array() ) ), 0, 8 ),
			);
		}

		return $out;
	}

	/**
	 * Format parser diagnostics for human-readable logs.
	 *
	 * @param array<string,mixed> $diagnostics Parser diagnostics.
	 * @return string
	 */
	protected function format_parser_diagnostics( $diagnostics ) {
		$mode        = (string) ( $diagnostics['mode'] ?? 'unknown' );
		$error_count = (int) ( $diagnostics['error_count'] ?? 0 );
		$summary     = trim( (string) ( $diagnostics['error_summary'] ?? '' ) );

		$message = 'Parser mode=' . $mode . ', errors=' . $error_count . '.';
		if ( '' !== $summary ) {
			$message .= ' ' . $summary;
		}

		return $message;
	}

	/**
	 * Whether diagnostics indicate a selector miss or parser mismatch.
	 *
	 * @param array<int,array<string,mixed>> $diagnostics Layout diagnostics.
	 * @return bool
	 */
	protected function has_selector_miss_in_diagnostics( $diagnostics ) {
		foreach ( (array) $diagnostics as $diagnostic ) {
			if ( ! empty( $diagnostic['selector_miss'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return the snapshot meta key for one structured field.
	 *
	 * @param string $field_name Field name.
	 * @return string
	 */
	protected function get_last_good_meta_key( $field_name ) {
		return '_leadwerk_last_good_' . sanitize_key( (string) $field_name );
	}

	/**
	 * Persist the last known good payload for one field.
	 *
	 * @param int                 $post_id     Post ID.
	 * @param string              $field_name  Field name.
	 * @param mixed               $value       Stored value.
	 * @param array<string,mixed> $validation  Validation data.
	 * @param string              $source      Snapshot source.
	 * @return void
	 */
	protected function save_last_good_snapshot( $post_id, $field_name, $value, $validation, $source = 'readback' ) {
		update_post_meta(
			$post_id,
			$this->get_last_good_meta_key( $field_name ),
			array(
				'value'      => $value,
				'source'     => sanitize_key( (string) $source ),
				'saved_at'   => current_time( 'mysql', true ),
				'validation' => $this->compact_validation( (array) $validation ),
			)
		);
	}

	/**
	 * Read the stored last-good snapshot for one field.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $field_name Field name.
	 * @return array<string,mixed>
	 */
	protected function get_last_good_snapshot( $post_id, $field_name ) {
		$snapshot = get_post_meta( (int) $post_id, $this->get_last_good_meta_key( $field_name ), true );
		return is_array( $snapshot ) ? $snapshot : array();
	}

	/**
	 * Resolve the effective field state, optionally using the last-good snapshot.
	 *
	 * @param int                  $post_id    Post ID.
	 * @param string               $field_name Field name.
	 * @param Leadwerk_ACF_Filler  $filler     Filler instance.
	 * @return array<string,mixed>
	 */
	protected function get_effective_field_state( $post_id, $field_name, $filler ) {
		$value      = function_exists( 'get_field' ) ? get_field( $field_name, $post_id ) : null;
		$validation = $filler->validate_group_value( $field_name, $value );

		if ( ! empty( $validation['has_visible_content'] ) ) {
			return array(
				'value'                   => $value,
				'validation'              => $validation,
				'used_last_good_fallback' => false,
				'snapshot_source'         => '',
			);
		}

		$snapshot = $this->get_last_good_snapshot( $post_id, $field_name );
		if ( empty( $snapshot['value'] ) ) {
			return array(
				'value'                   => $value,
				'validation'              => $validation,
				'used_last_good_fallback' => false,
				'snapshot_source'         => '',
			);
		}

		$snapshot_validation = $filler->validate_group_value( $field_name, $snapshot['value'] );
		if ( empty( $snapshot_validation['has_visible_content'] ) ) {
			return array(
				'value'                   => $value,
				'validation'              => $validation,
				'used_last_good_fallback' => false,
				'snapshot_source'         => '',
			);
		}

		return array(
			'value'                   => $snapshot['value'],
			'validation'              => $snapshot_validation,
			'used_last_good_fallback' => true,
			'snapshot_source'         => (string) ( $snapshot['source'] ?? '' ),
		);
	}

	/**
	 * Sync post_content for legal pages or dynamic block pages.
	 *
	 * @param int                 $post_id     Post ID.
	 * @param array<string,mixed> $page_config Manifest config.
	 * @param mixed               $value       Stored field value.
	 * @return void
	 */
	protected function sync_post_content_if_needed( $post_id, $page_config, $value ) {
		if ( ! class_exists( 'Leadwerk_Content_Schema' ) ) {
			return;
		}

		$field_name = (string) ( $page_config['field_name'] ?? '' );
		$group      = Leadwerk_Content_Schema::get_group( $field_name );
		if ( ! $group || ! is_array( $group ) ) {
			return;
		}

		$post_content = '';
		if ( ! empty( $group['sync_post_content'] ) && is_array( $value ) ) {
			$headline     = trim( (string) ( $value['headline'] ?? '' ) );
			$content      = (string) ( $value['content'] ?? '' );
			$post_content = sprintf(
				'<section class="section legal-section"><div class="container legal-container"><h1 class="section-title legal-title">%1$s</h1><div class="legal-content">%2$s</div></div></section>',
				esc_html( $headline ),
				wp_kses_post( $content )
			);
		} elseif ( ! empty( $group['block_content'] ) ) {
			$post_content = (string) $group['block_content'];
		}

		if ( '' === $post_content ) {
			return;
		}

		if ( trim( (string) get_post_field( 'post_content', $post_id ) ) === trim( $post_content ) ) {
			return;
		}

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $post_content,
			)
		);
	}

	/**
	 * Update one post to its desired status after a successful import.
	 *
	 * @param int    $post_id         Post ID.
	 * @param string $desired_status  Status.
	 * @return void
	 */
	protected function maybe_update_post_status( $post_id, $desired_status ) {
		$desired_status = sanitize_key( (string) $desired_status );
		if ( '' === $desired_status ) {
			return;
		}

		if ( $desired_status === (string) get_post_status( $post_id ) ) {
			return;
		}

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $desired_status,
			)
		);
	}

	/**
	 * Force one page back to draft for safety.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	protected function force_draft( $post_id ) {
		if ( $post_id <= 0 ) {
			return;
		}

		if ( 'draft' === (string) get_post_status( $post_id ) ) {
			return;
		}

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);
	}

	/**
	 * Check whether one page still contains its expected dynamic block markup.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $source_key Source key.
	 * @return bool
	 */
	protected function page_has_expected_block_content( $post_id, $source_key ) {
		$expected_content = trim( $this->get_default_block_content( $source_key ) );
		if ( '' === $expected_content ) {
			return true;
		}

		$post_content = trim( (string) get_post_field( 'post_content', $post_id ) );
		if ( '' === $post_content ) {
			return false;
		}

		return false !== strpos( $post_content, $expected_content );
	}

	/**
	 * Check whether one structured ACF field group contains data.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $field_name  Structured field name.
	 * @return bool
	 */
	protected function page_has_structured_field_data( $post_id, $field_name ) {
		if ( '' === $field_name || ! function_exists( 'get_field' ) ) {
			return false;
		}

		$value = get_field( $field_name, $post_id );
		if ( ! is_array( $value ) ) {
			return ! empty( $value );
		}

		return ! empty( $value );
	}
}
