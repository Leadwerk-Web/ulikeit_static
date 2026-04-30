<?php
/**
 * ACF-like field filling from static HTML sources.
 *
 * @package Leadwerk_Importer
 */

class Leadwerk_ACF_Filler {

	protected $source_root = '';
	protected $attachment_cache = array();
	protected $last_parser_diagnostics = array();

	/**
	 * Attachment-ID anhand des Quellpfads ermitteln.
	 *
	 * @param string $path Relative source path.
	 * @return int
	 */
	public function get_attachment_id_by_source( $path ) {
		$norm = $this->normalize_path( $path );
		if ( isset( $this->attachment_cache[ $norm ] ) ) {
			return $this->attachment_cache[ $norm ];
		}

		$id = 0;
		$q  = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'meta_key'       => 'leadwerk_source_path',
				'meta_value'     => $norm,
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		$ids = $q->get_posts();

		if ( ! empty( $ids ) ) {
			$id = (int) $ids[0];
		} else {
			$basename = wp_basename( $path );
			$q2       = new WP_Query(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => 1,
					'meta_query'     => array(
						array(
							'key'     => '_wp_attached_file',
							'value'   => $basename,
							'compare' => 'LIKE',
						),
					),
				)
			);
			$ids2 = $q2->get_posts();
			if ( ! empty( $ids2 ) ) {
				$id = (int) $ids2[0];
				update_post_meta( $id, 'leadwerk_source_path', $norm );
			}
		}

		$this->attachment_cache[ $norm ] = $id;
		return $id;
	}

	protected function normalize_path( $path ) {
		$path = str_replace( array( '\\', '//' ), array( '/', '/' ), $path );
		$path = str_replace( array( "\xE2\x80\x93", "\xE2\x80\x94" ), '-', $path );
		return trim( $path, '/' );
	}

	protected function normalize_wp_internal_url( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		$map = array(
			'#download'                 => '/download/',
			'index.html#download'       => '/download/',
			'/index.html#download'      => '/download/',
			'http://index.html#download'=> '/download/',
			'https://index.html#download'=> '/download/',
			'#onboarding'               => '/fuer-haendler/#onboarding',
			'haendler.html#onboarding'  => '/fuer-haendler/#onboarding',
			'/haendler.html#onboarding' => '/fuer-haendler/#onboarding',
			'user.html#download'        => '/download/',
			'/user.html#download'       => '/download/',
			'download.html'             => '/download/',
			'/download.html'            => '/download/',
			'index.html'                => '/',
			'/index.html'               => '/',
			'haendler.html'             => '/fuer-haendler/',
			'/haendler.html'            => '/fuer-haendler/',
			'user.html'                 => '/fuer-nutzer/',
			'/user.html'                => '/fuer-nutzer/',
		);

		return isset( $map[ $url ] ) ? $map[ $url ] : $url;
	}

	public function set_source_root( $source_root ) {
		$this->source_root = rtrim( (string) $source_root, '/\\' );
	}

	public function fill_front_page( $post_id, $source_root ) {
		return $this->fill_group_from_file( $post_id, $source_root, 'index.html', 'home_sections', 'build_home_sections_from_html' );
	}

	public function fill_user_page( $post_id, $source_root ) {
		return $this->fill_group_from_file( $post_id, $source_root, 'user.html', 'user_sections', 'build_user_sections_from_html' );
	}

	public function fill_haendler_page( $post_id, $source_root ) {
		return $this->fill_group_from_file( $post_id, $source_root, 'haendler.html', 'haendler_sections', 'build_haendler_sections_from_html' );
	}

	public function fill_impressum_page( $post_id, $source_root ) {
		return $this->fill_scalar_group_from_file( $post_id, $source_root, 'impressum.html', 'impressum_page', 'build_legal_page_from_html' );
	}

	public function fill_datenschutz_page( $post_id, $source_root ) {
		return $this->fill_scalar_group_from_file( $post_id, $source_root, 'datenschutz.html', 'datenschutz_page', 'build_legal_page_from_html' );
	}

	protected function fill_group_from_file( $post_id, $source_root, $file_name, $field_name, $builder_method ) {
		if ( ! function_exists( 'update_field' ) || ! $post_id ) {
			Leadwerk_Logger::log( 'Fields-Befuellung uebersprungen (API nicht aktiv oder keine Post-ID).' );
			return false;
		}

		$this->source_root = rtrim( $source_root, '/\\' );
		$file_path         = $this->source_root . DIRECTORY_SEPARATOR . $file_name;

		if ( ! is_file( $file_path ) ) {
			Leadwerk_Logger::log( $file_name . ' nicht gefunden: ' . $file_path );
			return false;
		}

		if ( ! method_exists( $this, $builder_method ) ) {
			Leadwerk_Logger::log( 'Builder nicht gefunden: ' . $builder_method );
			return false;
		}

		$html     = file_get_contents( $file_path );
		$sections = call_user_func( array( $this, $builder_method ), $html );
		$sections = $this->normalize_sections_for_field( $field_name, $sections );

		if ( empty( $sections ) ) {
			Leadwerk_Logger::log( 'Keine Sektionen aus ' . $file_name . ' extrahiert. Field=' . $field_name . '. ' . $this->get_last_parser_diagnostics_message() );
			return false;
		}

		update_field( $field_name, $sections, $post_id );
		Leadwerk_Logger::log( $field_name . ' befuellt: ' . count( $sections ) . ' Layout(s) fuer Post-ID ' . $post_id . '.' );

		return true;
	}

	protected function fill_scalar_group_from_file( $post_id, $source_root, $file_name, $field_name, $builder_method ) {
		if ( ! function_exists( 'update_field' ) || ! $post_id ) {
			Leadwerk_Logger::log( 'Fields-Befuellung uebersprungen (API nicht aktiv oder keine Post-ID).' );
			return false;
		}

		$this->source_root = rtrim( $source_root, '/\\' );
		$file_path         = $this->source_root . DIRECTORY_SEPARATOR . $file_name;

		if ( ! is_file( $file_path ) ) {
			Leadwerk_Logger::log( $file_name . ' nicht gefunden: ' . $file_path );
			return false;
		}

		if ( ! method_exists( $this, $builder_method ) ) {
			Leadwerk_Logger::log( 'Builder nicht gefunden: ' . $builder_method );
			return false;
		}

		$html  = file_get_contents( $file_path );
		$value = call_user_func( array( $this, $builder_method ), $html );
		$value = $this->normalize_scalar_group_for_field( $field_name, $value );

		if ( empty( $value['headline'] ) && empty( $value['content'] ) ) {
			Leadwerk_Logger::log( 'Keine Inhalte aus ' . $file_name . ' extrahiert.' );
			return false;
		}

		update_field( $field_name, $value, $post_id );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $this->build_legal_post_content( $value ),
			)
		);
		Leadwerk_Logger::log( $field_name . ' befuellt und post_content synchronisiert fuer Post-ID ' . $post_id . '.' );

		return true;
	}

	protected function normalize_sections_for_field( $field_name, $sections ) {
		if ( ! is_array( $sections ) ) {
			return array();
		}

		if ( ! class_exists( 'Leadwerk_Content_Schema' ) ) {
			return array_values( $sections );
		}

		$group = Leadwerk_Content_Schema::get_group( $field_name );
		if ( ! $group || empty( $group['layouts'] ) || ! is_array( $group['layouts'] ) ) {
			return array_values( $sections );
		}

		$layouts = array();
		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			$layout = isset( $section['acf_fc_layout'] ) ? sanitize_key( $section['acf_fc_layout'] ) : '';
			if ( '' === $layout || isset( $layouts[ $layout ] ) ) {
				continue;
			}

			$layouts[ $layout ] = $section;
		}

		$normalized = array();
		foreach ( $group['layouts'] as $layout => $layout_schema ) {
			if ( ! isset( $layouts[ $layout ] ) ) {
				continue;
			}

			$normalized[] = $this->normalize_section_by_schema( $layout, $layouts[ $layout ], $layout_schema );
		}

		return $normalized;
	}

	protected function normalize_section_by_schema( $layout, $section, $schema ) {
		$normalized                  = array();
		$normalized['acf_fc_layout'] = $layout;

		foreach ( $schema['fields'] ?? array() as $field_key => $definition ) {
			$value = $section[ $field_key ] ?? null;
			if ( null === $value && class_exists( 'Leadwerk_Content_Schema' ) ) {
				$value = Leadwerk_Content_Schema::get_default_value( $definition );
			}

			$normalized[ $field_key ] = $this->normalize_value_by_definition( $value, $definition );
		}

		return $normalized;
	}

	protected function normalize_value_by_definition( $value, $definition ) {
		$type = $definition['type'] ?? 'text';

		switch ( $type ) {
			case 'text':
				return sanitize_text_field( (string) $value );

			case 'url':
				return esc_url_raw( (string) $value );

			case 'textarea':
				return sanitize_textarea_field( (string) $value );

			case 'wysiwyg':
			case 'classic_editor':
				return wp_kses_post( (string) $value );

			case 'svg_code':
				return trim( (string) $value );

			case 'image':
				if ( is_array( $value ) && isset( $value['ID'] ) ) {
					return absint( $value['ID'] );
				}
				return absint( $value );

			case 'checkbox':
				return ! empty( $value );

			case 'select_options':
				$options = is_array( $value ) ? $value : array();
				$options = array_map(
					static function ( $option ) {
						return sanitize_text_field( (string) $option );
					},
					$options
				);
				return array_values( array_filter( $options, 'strlen' ) );

			case 'repeater':
				$rows       = is_array( $value ) ? array_values( $value ) : array();
				$normalized = array();

				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}

					$item = array();
					foreach ( $definition['fields'] ?? array() as $sub_key => $sub_definition ) {
						$sub_value = $row[ $sub_key ] ?? null;
						if ( null === $sub_value && class_exists( 'Leadwerk_Content_Schema' ) ) {
							$sub_value = Leadwerk_Content_Schema::get_default_value( $sub_definition );
						}

						$item[ $sub_key ] = $this->normalize_value_by_definition( $sub_value, $sub_definition );
					}

					$normalized[] = $item;
				}

				return $normalized;
		}

		return sanitize_text_field( (string) $value );
	}

	protected function normalize_scalar_group_for_field( $field_name, $value ) {
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		if ( ! class_exists( 'Leadwerk_Content_Schema' ) ) {
			return $value;
		}

		$group = Leadwerk_Content_Schema::get_group( $field_name );
		if ( ! $group || empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
			return $value;
		}

		$normalized = array();
		foreach ( $group['fields'] as $field_key => $definition ) {
			$current = $value[ $field_key ] ?? Leadwerk_Content_Schema::get_default_value( $definition );
			$normalized[ $field_key ] = $this->normalize_value_by_definition( $current, $definition );
		}

		return $normalized;
	}

	protected function build_legal_post_content( $value ) {
		$headline = trim( (string) ( $value['headline'] ?? '' ) );
		$content  = (string) ( $value['content'] ?? '' );

		return sprintf(
			'<section class="section legal-section"><div class="container legal-container"><h1 class="section-title legal-title">%1$s</h1><div class="legal-content">%2$s</div></div></section>',
			esc_html( $headline ),
			wp_kses_post( $content )
		);
	}

	public function build_page_payload( $page_config, $lang = 'de', $override_relative_file = '' ) {
		$field_name = (string) ( $page_config['field_name'] ?? '' );
		$source_key = (string) ( $page_config['source_key'] ?? '' );
		$source_file = (string) ( $override_relative_file ?: ( $page_config['source_file'] ?? '' ) );
		$group      = class_exists( 'Leadwerk_Content_Schema' ) ? Leadwerk_Content_Schema::get_group( $field_name ) : null;
		$payload    = array(
			'field_name'         => $field_name,
			'source_key'         => $source_key,
			'source_file'        => $source_file,
			'value'              => array(),
			'validation'         => array(
				'field_name'             => $field_name,
				'has_visible_content'    => false,
				'visible_content_score'  => 0,
				'expected_layout_count'  => 0,
				'parsed_layout_count'    => 0,
				'parsed_section_count'   => 0,
				'non_empty_layout_count' => 0,
				'missing_sections'       => 0,
				'empty_layouts'          => array(),
				'empty_fields'           => array(),
			),
			'layout_diagnostics' => array(),
			'parser_diagnostics' => $this->last_parser_diagnostics,
		);

		if ( ! $group || ! is_array( $group ) ) {
			$payload['validation']['empty_fields'][] = 'schema_missing';
			return $payload;
		}

		if ( '' === $source_file ) {
			$payload['validation']['empty_fields'][] = 'source_file_missing';
			return $payload;
		}

		$file_path = $this->resolve_source_file_path( $source_file );
		if ( '' === $file_path || ! is_file( $file_path ) ) {
			$payload['validation']['empty_fields'][] = 'source_file_missing';
			return $payload;
		}

		$html = (string) file_get_contents( $file_path );
		if ( empty( $group['layouts'] ) ) {
			$builder_method = $this->get_builder_method_for_field( $field_name );
			if ( '' !== $builder_method && method_exists( $this, $builder_method ) ) {
				$value = $this->normalize_scalar_group_for_field( $field_name, call_user_func( array( $this, $builder_method ), $html ) );
			} else {
				$value = $this->normalize_scalar_group_for_field( $field_name, $this->build_legal_page_from_html( $html ) );
			}
			$payload['value']              = $value;
			$payload['parser_diagnostics'] = $this->last_parser_diagnostics;
			$payload['validation'] = $this->build_payload_validation( $field_name, $group, $value, $this->group_has_visible_content( $group, $value ) ? 1 : 0 );
			return $payload;
		}

		$builder_method = $this->get_builder_method_for_field( $field_name );
		if ( '' === $builder_method || ! method_exists( $this, $builder_method ) ) {
			$payload['validation']['empty_fields'][] = 'builder_missing';
			return $payload;
		}

		$sections = call_user_func( array( $this, $builder_method ), $html );
		$sections = $this->normalize_sections_for_field( $field_name, $sections );

		$payload['value']              = $sections;
		$payload['parser_diagnostics'] = $this->last_parser_diagnostics;
		$payload['validation']         = $this->build_payload_validation( $field_name, $group, $sections, is_array( $sections ) ? count( $sections ) : 0 );
		$payload['layout_diagnostics'] = $this->build_layout_diagnostics( $field_name, $group, $sections );

		return $payload;
	}

	public function validate_group_value( $field_name, $value ) {
		$group = class_exists( 'Leadwerk_Content_Schema' ) ? Leadwerk_Content_Schema::get_group( $field_name ) : null;
		if ( ! $group || ! is_array( $group ) ) {
			return array(
				'field_name'             => $field_name,
				'has_visible_content'    => false,
				'visible_content_score'  => 0,
				'expected_layout_count'  => 0,
				'parsed_layout_count'    => 0,
				'parsed_section_count'   => 0,
				'non_empty_layout_count' => 0,
				'missing_sections'       => 0,
				'empty_layouts'          => array(),
				'empty_fields'           => array( 'schema_missing' ),
			);
		}

		$parsed_count = is_array( $value ) ? count( $value ) : ( $this->group_has_visible_content( $group, $value ) ? 1 : 0 );
		return $this->build_payload_validation( $field_name, $group, $value, $parsed_count );
	}

	protected function build_payload_validation( $field_name, $group, $value, $parsed_section_count = 0 ) {
		$validation = array(
			'field_name'             => (string) $field_name,
			'has_visible_content'    => false,
			'visible_content_score'  => 0,
			'expected_layout_count'  => 0,
			'parsed_layout_count'    => 0,
			'parsed_section_count'   => (int) $parsed_section_count,
			'non_empty_layout_count' => 0,
			'missing_sections'       => 0,
			'empty_layouts'          => array(),
			'empty_fields'           => array(),
		);

		if ( empty( $group['layouts'] ) ) {
			$validation['has_visible_content'] = $this->group_has_visible_content( $group, $value );
			$validation['visible_content_score'] = $validation['has_visible_content'] ? $this->count_visible_fields( $value ) : 0;
			if ( ! $validation['has_visible_content'] ) {
				foreach ( array_keys( (array) ( $group['fields'] ?? array() ) ) as $field_key ) {
					$validation['empty_fields'][] = $field_key;
				}
			}

			return $validation;
		}

		$sections          = is_array( $value ) ? array_values( $value ) : array();
		$expected_layouts  = array_keys( (array) ( $group['layouts'] ?? array() ) );
		$visible_score     = 0;

		$validation['expected_layout_count'] = count( $expected_layouts );
		$validation['parsed_layout_count']   = count( $sections );
		$validation['parsed_section_count']  = (int) $parsed_section_count;
		$validation['missing_sections']      = max( 0, $validation['expected_layout_count'] - $validation['parsed_layout_count'] );

		foreach ( $expected_layouts as $index => $layout_key ) {
			$layout_schema = $group['layouts'][ $layout_key ] ?? array();
			$section       = isset( $sections[ $index ] ) && is_array( $sections[ $index ] ) ? $sections[ $index ] : array( 'acf_fc_layout' => $layout_key );
			$is_present    = isset( $sections[ $index ] ) && is_array( $sections[ $index ] );

			if ( ! $is_present ) {
				$validation['empty_layouts'][] = $layout_key;
			}

			$layout_visible = false;
			foreach ( (array) ( $layout_schema['fields'] ?? array() ) as $field_key => $definition ) {
				$field_value = $section[ $field_key ] ?? null;
				if ( $this->field_value_has_visible_content( $field_value, $definition ) ) {
					$layout_visible = true;
					$visible_score += $this->count_visible_fields( $field_value, $definition );
				} else {
					$validation['empty_fields'][] = $layout_key . '.' . $field_key;
				}
			}

			if ( $layout_visible ) {
				++$validation['non_empty_layout_count'];
			} elseif ( ! in_array( $layout_key, $validation['empty_layouts'], true ) ) {
				$validation['empty_layouts'][] = $layout_key;
			}
		}

		$validation['has_visible_content']   = $validation['non_empty_layout_count'] > 0;
		$validation['visible_content_score'] = $visible_score;
		$validation['empty_layouts']         = array_values( array_unique( $validation['empty_layouts'] ) );
		$validation['empty_fields']          = array_slice( array_values( array_unique( $validation['empty_fields'] ) ), 0, 20 );

		return $validation;
	}

	protected function build_layout_diagnostics( $field_name, $group, $value ) {
		if ( empty( $group['layouts'] ) ) {
			return array();
		}

		$sections     = is_array( $value ) ? array_values( $value ) : array();
		$diagnostics  = array();

		foreach ( (array) ( $group['layouts'] ?? array() ) as $layout_key => $layout_schema ) {
			$index      = count( $diagnostics );
			$is_present = isset( $sections[ $index ] ) && is_array( $sections[ $index ] );
			$section    = $is_present ? $sections[ $index ] : array( 'acf_fc_layout' => $layout_key );
			$empty      = array();

			foreach ( (array) ( $layout_schema['fields'] ?? array() ) as $field_key => $definition ) {
				if ( ! $this->field_value_has_visible_content( $section[ $field_key ] ?? null, $definition ) ) {
					$empty[] = $field_key;
				}
			}

			$diagnostics[] = array(
				'layout'              => $layout_key,
				'index'               => $index,
				'present'             => $is_present,
				'stored_layout'       => sanitize_key( (string) ( $section['acf_fc_layout'] ?? '' ) ),
				'selector_miss'       => ! $is_present,
				'has_visible_content' => $this->section_has_visible_content( $section ),
				'empty_fields'        => $empty,
			);
		}

		return $diagnostics;
	}

	protected function group_has_visible_content( $group, $value ) {
		if ( empty( $group['layouts'] ) ) {
			foreach ( (array) ( $group['fields'] ?? array() ) as $field_key => $definition ) {
				if ( $this->field_value_has_visible_content( $value[ $field_key ] ?? null, $definition ) ) {
					return true;
				}
			}

			return false;
		}

		if ( ! is_array( $value ) || empty( $value ) ) {
			return false;
		}

		foreach ( array_values( $value ) as $section ) {
			if ( is_array( $section ) && $this->section_has_visible_content( $section ) ) {
				return true;
			}
		}

		return false;
	}

	protected function section_has_visible_content( $section ) {
		foreach ( (array) $section as $field_key => $field_value ) {
			if ( 'acf_fc_layout' === (string) $field_key ) {
				continue;
			}

			if ( $this->field_value_has_visible_content( $field_value ) ) {
				return true;
			}
		}

		return false;
	}

	protected function field_value_has_visible_content( $value, $definition = array() ) {
		$type = isset( $definition['type'] ) ? (string) $definition['type'] : '';

		if ( is_array( $value ) ) {
			if ( 'repeater' === $type || isset( $definition['fields'] ) ) {
				foreach ( array_values( $value ) as $row ) {
					if ( is_array( $row ) && $this->section_has_visible_content( $row ) ) {
						return true;
					}
				}

				return false;
			}

			foreach ( $value as $item ) {
				if ( $this->field_value_has_visible_content( $item ) ) {
					return true;
				}
			}

			return false;
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return (int) $value > 0;
		}

		return '' !== trim( wp_strip_all_tags( (string) $value ) );
	}

	protected function count_visible_fields( $value, $definition = array() ) {
		if ( is_array( $value ) ) {
			$count = 0;
			foreach ( $value as $field_key => $field_value ) {
				if ( 'acf_fc_layout' === (string) $field_key ) {
					continue;
				}

				$count += $this->count_visible_fields( $field_value );
			}

			return $count;
		}

		return $this->field_value_has_visible_content( $value, $definition ) ? 1 : 0;
	}

	protected function get_builder_method_for_field( $field_name ) {
		$map = array(
			'home_sections'     => 'build_home_sections_from_html',
			'user_sections'     => 'build_user_sections_from_html',
			'haendler_sections' => 'build_haendler_sections_from_html',
			'impressum_page'    => 'build_legal_page_from_html',
			'datenschutz_page'  => 'build_legal_page_from_html',
			'download_page'     => 'build_download_page_from_html',
		);

		return isset( $map[ $field_name ] ) ? $map[ $field_name ] : '';
	}

	protected function resolve_source_file_path( $relative_path ) {
		if ( '' === $this->source_root ) {
			return '';
		}

		return rtrim( $this->source_root, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, (string) $relative_path );
	}

	protected function build_home_sections_from_html( $html ) {
		list( $dom, $xpath ) = $this->create_dom_xpath( $html );
		$sections            = array();

		$hero = $this->xpath_section( $xpath, 'hero' );
		if ( $hero ) {
			$hero_img         = $this->attr( $xpath, './/div[contains(@class,"hero-frau-layer")]//img', 'src', $hero );
			$typewriter_words = $this->attr( $xpath, './/span[contains(@class,"hero-typewriter-wrap")]', 'data-words', $hero );
			$sections[] = array(
				'acf_fc_layout'    => 'hero',
				'title_gradient'   => $this->text( $xpath, './/h1[contains(@class,"hero-title")]//span[contains(@class,"text-gradient")]', $hero ),
				'typewriter_words' => $typewriter_words ? $typewriter_words : 'App herunterladen. Schnäppchen sichern.|Lokale Angebote in deiner Nähe.|Vor Ort einlösen. Direkt sparen.',
				'cta_text'         => $this->text( $xpath, './/div[contains(@class,"hero-cta")]//a', $hero ) ?: 'App herunterladen',
				'cta_url'          => $this->normalize_wp_internal_url( $this->attr( $xpath, './/div[contains(@class,"hero-cta")]//a', 'href', $hero ) ?: '/#download' ),
				'hero_image'       => $hero_img ? $this->get_attachment_id_by_source( $hero_img ) : 0,
			);
		}

		$why = $this->xpath_section( $xpath, 'why' );
		if ( $why ) {
			$cards      = array();
			$card_nodes = $xpath->query( './/div[contains(@class,"why-float-card")]', $why );
			foreach ( $card_nodes as $card ) {
				$svg_el  = $xpath->query( './/span[contains(@class,"why-item-icon")]//svg', $card )->item( 0 );
				$cards[] = array(
					'icon_svg' => $svg_el ? $dom->saveHTML( $svg_el ) : '',
					'text'     => $this->text( $xpath, './/p[contains(@class,"why-item-text")]', $card ),
					'detail'   => $this->text( $xpath, './/p[contains(@class,"why-item-detail")]', $card ),
				);
			}
			$bag_img    = $this->attr( $xpath, './/div[contains(@class,"why-bag")]//img', 'src', $why );
			$sections[] = array(
				'acf_fc_layout' => 'why',
				'label'         => $this->text( $xpath, './/span[contains(@class,"why-label")]', $why ),
				'title'         => $this->text( $xpath, './/h2[contains(@class,"section-title")]', $why ),
				'cards'         => $cards,
				'bag_image'     => $bag_img ? $this->get_attachment_id_by_source( $bag_img ) : 0,
			);
		}

		$steps = $this->xpath_section( $xpath, 'app-steps' );
		if ( $steps ) {
			$items       = array();
			$slide_nodes = $xpath->query( './/div[contains(@class,"app-step-slide")]', $steps );
			foreach ( $slide_nodes as $slide ) {
				$img_top = $slide instanceof DOMElement ? $slide->getAttribute( 'data-img-top' ) : '';
				$img_bot = $slide instanceof DOMElement ? $slide->getAttribute( 'data-img-bottom' ) : '';
				$items[] = array(
					'number'       => $this->text( $xpath, './/span[contains(@class,"app-step-number")]', $slide ),
					'text'         => $this->text( $xpath, './/p[contains(@class,"app-step-text")]', $slide ),
					'image_top'    => $img_top ? $this->get_attachment_id_by_source( $img_top ) : 0,
					'image_bottom' => $img_bot ? $this->get_attachment_id_by_source( $img_bot ) : 0,
				);
			}
			$sections[] = array(
				'acf_fc_layout' => 'app_steps',
				'headline'      => $this->text( $xpath, './/h2[contains(@class,"app-steps-headline")]', $steps ),
				'steps'         => $items,
			);
		}

		$pakete = $this->xpath_section( $xpath, 'pakete' );
		if ( $pakete ) {
			$pkg_img = $this->attr( $xpath, './/div[contains(@class,"pakete-image")]//img', 'src', $pakete );
			$content = '';
			foreach ( $xpath->query( './/div[contains(@class,"pakete-text")]//p', $pakete ) as $p ) {
				$content .= $dom->saveHTML( $p );
			}
			$sections[] = array(
				'acf_fc_layout' => 'pakete',
				'label'         => $this->text( $xpath, './/span[contains(@class,"pakete-label")]', $pakete ),
				'title'         => $this->text( $xpath, './/h2[contains(@class,"pakete-title")]', $pakete ),
				'content'       => $content,
				'cta_text'      => $this->text( $xpath, './/a[contains(@class,"pakete-cta")]', $pakete ) ?: 'Mehr erfahren',
				'cta_url'       => $this->normalize_wp_internal_url( $this->attr( $xpath, './/a[contains(@class,"pakete-cta")]', 'href', $pakete ) ?: '/#download' ),
				'image'         => $pkg_img ? $this->get_attachment_id_by_source( $pkg_img ) : 0,
			);
		}

		$sol = $this->xpath_section( $xpath, 'solution-2' );
		if ( $sol ) {
			$cards = array();
			foreach ( $xpath->query( './/article[contains(@class,"solution-card")]', $sol ) as $card ) {
				$svg_el  = $xpath->query( './/div[contains(@class,"solution-card-icon")]//svg', $card )->item( 0 );
				$cards[] = array(
					'icon_svg'    => $svg_el ? $dom->saveHTML( $svg_el ) : '',
					'title'       => $this->text( $xpath, './/h3', $card ),
					'description' => $this->text( $xpath, './/p[contains(@class,"solution-card-desc")]', $card ),
					'link_text'   => $this->text( $xpath, './/a[contains(@class,"solution-card-link")]', $card ),
					'link_url'    => $this->normalize_wp_internal_url( $this->attr( $xpath, './/a[contains(@class,"solution-card-link")]', 'href', $card ) ),
				);
			}

			$content = '';
			foreach ( $xpath->query( './/div[contains(@class,"solution-2-text")]//p', $sol ) as $p ) {
				$content .= $dom->saveHTML( $p );
			}

			$sections[] = array(
				'acf_fc_layout'     => 'solutions',
				'label'             => $this->text( $xpath, './/span[contains(@class,"solution-2-label")]', $sol ),
				'title'             => $this->text( $xpath, './/h2[contains(@class,"solution-title")]', $sol ),
				'intro_text'        => $content,
				'register_btn_text' => $this->text( $xpath, './/div[contains(@class,"solution-intro")]//a[contains(@class,"btn")]', $sol ) ?: 'Unternehmen registrieren',
				'register_btn_url'  => $this->normalize_wp_internal_url( $this->attr( $xpath, './/div[contains(@class,"solution-intro")]//a[contains(@class,"btn")]', 'href', $sol ) ?: '/fuer-haendler/#onboarding' ),
				'cards'             => $cards,
			);
		}

		$faq = $this->xpath_section( $xpath, 'solution-faq' );
		if ( $faq ) {
			$items = array();
			foreach ( $xpath->query( './/details[contains(@class,"faq-item")]', $faq ) as $detail ) {
				$items[] = array(
					'question' => $this->text( $xpath, './/summary//span', $detail ),
					'answer'   => $this->text( $xpath, './/div[contains(@class,"faq-answer")]//p', $detail ),
				);
			}
			$faq_img     = $this->attr( $xpath, './/div[contains(@class,"solution-faq-image-wrap")]//img', 'src', $faq );
			$sections[] = array(
				'acf_fc_layout' => 'faq',
				'label'         => $this->text( $xpath, './/span[contains(@class,"solution-2-label")]', $faq ),
				'title'         => $this->text( $xpath, './/h2[contains(@class,"section-title")]', $faq ),
				'items'         => $items,
				'image'         => $faq_img ? $this->get_attachment_id_by_source( $faq_img ) : 0,
			);
		}

		$cta = $this->xpath_section( $xpath, 'download' );
		if ( $cta ) {
			$sections[] = array(
				'acf_fc_layout' => 'cta',
				'title'         => $this->multiline_text( $dom, $xpath, './/h2[contains(@class,"cta-title")]', $cta ),
				'button_1_text' => $this->text( $xpath, './/div[contains(@class,"cta-buttons-row")]//a[1]', $cta ) ?: 'Unternehmen registrieren',
				'button_1_url'  => $this->normalize_wp_internal_url( $this->attr( $xpath, './/div[contains(@class,"cta-buttons-row")]//a[1]', 'href', $cta ) ?: '/fuer-haendler/#onboarding' ),
				'button_2_text' => $this->text( $xpath, './/div[contains(@class,"cta-buttons-row")]//a[2]', $cta ) ?: 'App herunterladen',
				'button_2_url'  => $this->normalize_wp_internal_url( $this->attr( $xpath, './/div[contains(@class,"cta-buttons-row")]//a[2]', 'href', $cta ) ?: '/#download' ),
			);
		}

		return $sections;
	}

	protected function build_user_sections_from_html( $html ) {
		list( $dom, $xpath ) = $this->create_dom_xpath( $html );
		$sections            = array();

		$hero = $this->xpath_section( $xpath, 'hero' );
		if ( $hero ) {
			$sections[] = array(
				'acf_fc_layout' => 'hero',
				'title_lines'   => $this->extract_title_lines( $xpath, './/h1[contains(@class,"user-hero-title")]//span[contains(@class,"hero-title-line")]', $hero ),
				'subtitle'      => $this->text( $xpath, './/p[contains(@class,"user-hero-sub")]', $hero ),
				'micro_text'    => $this->text( $xpath, './/p[contains(@class,"user-hero-micro")]', $hero ),
				'hero_image'    => $this->resolve_image_from_xpath( $xpath, './/div[contains(@class,"hero-frau-layer")]//img', $hero ),
			);
		}

		$how = $this->xpath_section( $xpath, 'how-it-works' );
		if ( $how ) {
			$steps = array();
			foreach ( $xpath->query( './/div[contains(@class,"user-step glass-card")]', $how ) as $step ) {
				$svg_el  = $xpath->query( './/div[contains(@class,"user-step-icon")]//svg', $step )->item( 0 );
				$steps[] = array(
					'number'   => $this->text( $xpath, './/span[contains(@class,"user-step-number")]', $step ),
					'icon_svg' => $svg_el ? $dom->saveHTML( $svg_el ) : '',
					'title'    => $this->text( $xpath, './/h3', $step ),
					'text'     => $this->text( $xpath, './/p', $step ),
				);
			}
			$sections[] = array(
				'acf_fc_layout' => 'how_it_works',
				'label'         => $this->text( $xpath, './/span[contains(@class,"solution-2-label")]', $how ),
				'title'         => $this->text( $xpath, './/h2[contains(@class,"section-title")]', $how ),
				'steps'         => $steps,
			);
		}

		$app_preview = $this->xpath_section( $xpath, 'app-preview' );
		if ( $app_preview ) {
			$sections[] = array(
				'acf_fc_layout'   => 'app_preview',
				'label'           => $this->text( $xpath, './/span[contains(@class,"solution-2-label")]', $app_preview ),
				'title'           => $this->text( $xpath, './/h2[contains(@class,"section-title")]', $app_preview ),
				'swipe_hint_text' => trim( $this->text( $xpath, './/p[contains(@class,"app-showcase-swipe-hint")]', $app_preview ) ),
				'slides'          => $this->extract_showcase_slides( $dom, $xpath, $app_preview ),
			);
		}

		$location = $this->xpath_section( $xpath, 'location' );
		if ( $location ) {
			$city_tags = array();
			foreach ( $xpath->query( './/button[contains(@class,"user-location-tag")]', $location ) as $button ) {
				if ( ! $button instanceof DOMElement ) {
					continue;
				}
				$city_tags[] = array(
					'label' => trim( $button->textContent ),
					'value' => trim( $button->getAttribute( 'data-city' ) ),
				);
			}

			$modal               = $xpath->query( '//*[@id="loc-modal-overlay"]' )->item( 0 );
			$modal_title_prefix  = '';
			$modal_fallback_city = '';
			$modal_text          = '';
			$modal_micro         = '';
			if ( $modal ) {
				$modal_fallback_city = $this->text( $xpath, './/span[@id="loc-modal-city"]', $modal );
				$title_text          = $this->text( $xpath, './/h3[contains(@class,"loc-modal-title")]', $modal );
				$modal_title_prefix  = trim( str_replace( $modal_fallback_city, '', $title_text ) );
				$modal_text          = $this->text( $xpath, './/p[contains(@class,"loc-modal-text")]', $modal );
				$modal_micro         = $this->text( $xpath, './/p[contains(@class,"loc-modal-micro")]', $modal );
			}

			$sections[] = array(
				'acf_fc_layout'       => 'location',
				'label'               => $this->text( $xpath, './/span[contains(@class,"solution-2-label")]', $location ),
				'title'               => $this->text( $xpath, './/h2[contains(@class,"section-title")]', $location ),
				'body_text'           => $this->text( $xpath, './/p[contains(@class,"user-location-text")]', $location ),
				'input_placeholder'   => $this->attr( $xpath, './/input[contains(@class,"user-location-input")]', 'placeholder', $location ),
				'image'               => $this->resolve_image_from_xpath( $xpath, './/img[contains(@class,"user-location-img")]', $location ),
				'image_alt'           => $this->attr( $xpath, './/img[contains(@class,"user-location-img")]', 'alt', $location ),
				'city_tags'           => $city_tags,
				'modal_title_prefix'  => $modal_title_prefix,
				'modal_fallback_city' => $modal_fallback_city,
				'modal_text'          => $modal_text,
				'modal_micro'         => $modal_micro,
			);
		}

		$categories = $this->xpath_section( $xpath, 'categories' );
		if ( $categories ) {
			$items = array();
			foreach ( $xpath->query( './/div[contains(@class,"user-category-card")]', $categories ) as $card ) {
				$svg_el  = $xpath->query( './/div[contains(@class,"user-category-icon-wrap")]//svg', $card )->item( 0 );
				$img_src = $this->attr( $xpath, './/img[contains(@class,"user-category-img")]', 'src', $card );
				$items[] = array(
					'image'     => $img_src ? $this->get_attachment_id_by_source( $img_src ) : 0,
					'image_alt' => $this->attr( $xpath, './/img[contains(@class,"user-category-img")]', 'alt', $card ),
					'icon_svg'  => $svg_el ? $dom->saveHTML( $svg_el ) : '',
					'title'     => $this->text( $xpath, './/h3', $card ),
					'text'      => $this->text( $xpath, './/p', $card ),
				);
			}
			$sections[] = array(
				'acf_fc_layout' => 'categories',
				'label'         => $this->text( $xpath, './/span[contains(@class,"solution-2-label")]', $categories ),
				'title'         => $this->text( $xpath, './/h2[contains(@class,"section-title")]', $categories ),
				'categories'    => $items,
			);
		}

		$exclusive = $this->xpath_section( $xpath, 'exclusive' );
		if ( $exclusive ) {
			$features = array();
			foreach ( $xpath->query( './/div[contains(@class,"user-exclusive-feature glass-card")]', $exclusive ) as $feature ) {
				$icon_wrap = $xpath->query( './/div[contains(@class,"user-exclusive-feature-icon")]', $feature )->item( 0 );
				$svg_el    = $xpath->query( './/div[contains(@class,"user-exclusive-feature-icon")]//svg', $feature )->item( 0 );
				$features[] = array(
					'icon_svg' => $svg_el ? $dom->saveHTML( $svg_el ) : '',
					'accent'   => $this->element_has_class( $icon_wrap, 'user-exclusive-feature-icon-accent' ),
					'title'    => $this->text( $xpath, './/h3', $feature ),
					'text'     => $this->text( $xpath, './/p', $feature ),
				);
			}
			$sections[] = array(
				'acf_fc_layout' => 'exclusive',
				'label'         => $this->text( $xpath, './/span[contains(@class,"solution-2-label")]', $exclusive ),
				'title'         => $this->multiline_text( $dom, $xpath, './/h2[contains(@class,"user-exclusive-title")]', $exclusive ),
				'lead'          => $this->text( $xpath, './/p[contains(@class,"user-exclusive-lead")]', $exclusive ),
				'features'      => $features,
			);
		}

		$cta = $this->xpath_section( $xpath, 'download' );
		if ( $cta ) {
			$sections[] = array(
				'acf_fc_layout' => 'cta',
				'title'         => $this->multiline_text( $dom, $xpath, './/h2[contains(@class,"cta-title")]', $cta ),
				'subtitle'      => $this->text( $xpath, './/p[contains(@class,"cta-subtitle")]', $cta ),
				'micro_text'    => $this->text( $xpath, './/p[contains(@class,"cta-micro")]', $cta ),
			);
		}

		return $sections;
	}

	protected function build_haendler_sections_from_html( $html ) {
		list( $dom, $xpath ) = $this->create_dom_xpath( $html );
		$sections            = array();

		$hero = $this->xpath_section( $xpath, 'hero' );
		if ( $hero ) {
			$sections[] = array(
				'acf_fc_layout'         => 'hero',
				'title_lines'           => $this->extract_title_lines( $xpath, './/h1[contains(@class,"user-hero-title")]//span[contains(@class,"hero-title-line")]', $hero ),
				'subtitle'              => $this->text( $xpath, './/p[contains(@class,"user-hero-sub")]', $hero ),
				'primary_button_text'   => $this->text( $xpath, './/div[contains(@class,"haendler-hero-cta")]//a[1]', $hero ),
				'primary_button_url'    => $this->attr( $xpath, './/div[contains(@class,"haendler-hero-cta")]//a[1]', 'href', $hero ),
				'secondary_button_text' => $this->text( $xpath, './/div[contains(@class,"haendler-hero-cta")]//a[2]', $hero ),
				'secondary_button_url'  => $this->attr( $xpath, './/div[contains(@class,"haendler-hero-cta")]//a[2]', 'href', $hero ),
				'micro_text'            => $this->text( $xpath, './/p[contains(@class,"user-hero-micro")]', $hero ),
				'hero_image'            => $this->resolve_image_from_xpath( $xpath, './/div[contains(@class,"hero-frau-layer")]//img', $hero ),
			);
		}

		$benefits = $this->xpath_section( $xpath, 'vorteile' );
		if ( $benefits ) {
			$sections[] = array(
				'acf_fc_layout' => 'benefits',
				'label'         => $this->text( $xpath, './/span[contains(@class,"solution-2-label")]', $benefits ),
				'title'         => $this->multiline_text( $dom, $xpath, './/h2[contains(@class,"user-exclusive-title")]', $benefits ),
				'lead'          => $this->text( $xpath, './/p[contains(@class,"user-exclusive-lead")]', $benefits ),
				'features'      => $this->extract_feature_cards( $dom, $xpath, $benefits ),
			);
		}

		$how = $this->xpath_section( $xpath, 'so-gehts' );
		if ( $how ) {
			$steps = array();
			foreach ( $xpath->query( './/div[contains(@class,"user-step glass-card")]', $how ) as $step ) {
				$svg_el  = $xpath->query( './/div[contains(@class,"user-step-icon")]//svg', $step )->item( 0 );
				$steps[] = array(
					'number'   => $this->text( $xpath, './/span[contains(@class,"user-step-number")]', $step ),
					'icon_svg' => $svg_el ? $dom->saveHTML( $svg_el ) : '',
					'title'    => $this->text( $xpath, './/h3', $step ),
					'text'     => $this->text( $xpath, './/p', $step ),
				);
			}
			$sections[] = array(
				'acf_fc_layout' => 'how_it_works',
				'label'         => $this->text( $xpath, './/span[contains(@class,"solution-2-label")]', $how ),
				'title'         => $this->text( $xpath, './/h2[contains(@class,"section-title")]', $how ),
				'steps'         => $steps,
			);
		}

		$dashboard = $this->xpath_section( $xpath, 'dashboard' );
		if ( $dashboard ) {
			$sections[] = array(
				'acf_fc_layout'   => 'dashboard_preview',
				'label'           => $this->text( $xpath, './/span[contains(@class,"solution-2-label")]', $dashboard ),
				'title'           => $this->text( $xpath, './/h2[contains(@class,"section-title")]', $dashboard ),
				'swipe_hint_text' => trim( $this->text( $xpath, './/p[contains(@class,"app-showcase-swipe-hint")]', $dashboard ) ),
				'slides'          => $this->extract_showcase_slides( $dom, $xpath, $dashboard ),
			);
		}

		$use_cases = $this->xpath_section( $xpath, 'beispiele' );
		if ( $use_cases ) {
			$cases = array();
			foreach ( $xpath->query( './/div[contains(@class,"haendler-case-item")]', $use_cases ) as $case ) {
				$svg_el  = $xpath->query( './/div[contains(@class,"haendler-case-icon-wrap")]//svg', $case )->item( 0 );
				$cases[] = array(
					'icon_svg' => $svg_el ? $dom->saveHTML( $svg_el ) : '',
					'label'    => $this->text( $xpath, './/span[contains(@class,"haendler-case-label")]', $case ),
					'quote'    => $this->text( $xpath, './/p[contains(@class,"haendler-case-quote")]', $case ),
					'result'   => $this->text( $xpath, './/p[contains(@class,"haendler-case-result")]', $case ),
				);
			}
			$sections[] = array(
				'acf_fc_layout' => 'use_cases',
				'label'         => $this->text( $xpath, './/span[contains(@class,"solution-2-label")]', $use_cases ),
				'title'         => $this->text( $xpath, './/h2[contains(@class,"section-title")]', $use_cases ),
				'image'         => $this->resolve_image_from_xpath( $xpath, './/img[contains(@class,"user-location-img")]', $use_cases ),
				'image_alt'     => $this->attr( $xpath, './/img[contains(@class,"user-location-img")]', 'alt', $use_cases ),
				'cases'         => $cases,
			);
		}

		$conditions = $this->xpath_section( $xpath, 'konditionen' );
		if ( $conditions ) {
			$list = array();
			foreach ( $xpath->query( './/ul[contains(@class,"haendler-conditions-grid")]//li', $conditions ) as $li ) {
				$list[] = array(
					'text' => trim( preg_replace( '/\s+/', ' ', $li->textContent ) ),
				);
			}

			$content = '';
			foreach ( $xpath->query( './/div[contains(@class,"pakete-text")]//p', $conditions ) as $p ) {
				$content .= $dom->saveHTML( $p );
			}

			$sections[] = array(
				'acf_fc_layout' => 'conditions',
				'label'         => $this->text( $xpath, './/span[contains(@class,"pakete-label")]', $conditions ),
				'title'         => $this->text( $xpath, './/h2[contains(@class,"pakete-title")]', $conditions ),
				'content'       => $content,
				'conditions'    => $list,
				'cta_text'      => $this->text( $xpath, './/a[contains(@class,"btn")]', $conditions ),
				'cta_url'       => $this->attr( $xpath, './/a[contains(@class,"btn")]', 'href', $conditions ),
			);
		}

		$onboarding = $this->xpath_section( $xpath, 'onboarding' );
		if ( $onboarding ) {
			$pos_title = '';
			$pos_text  = '';
			$pos_wrap  = $xpath->query( './/p[contains(@class,"haendler-pos-text")]', $onboarding )->item( 0 );
			if ( $pos_wrap instanceof DOMElement ) {
				$full      = trim( preg_replace( '/\s+/', ' ', $pos_wrap->textContent ) );
				$pos_title = $this->text( $xpath, './/strong', $pos_wrap );
				$pos_text  = trim( str_replace( $pos_title, '', $full ) );
				$pos_text  = ltrim( $pos_text, ': ' );
			}

			$sections[] = array(
				'acf_fc_layout' => 'onboarding',
				'label'         => $this->text( $xpath, './/span[contains(@class,"solution-2-label")]', $onboarding ),
				'title'         => $this->text( $xpath, './/h2[contains(@class,"section-title")]', $onboarding ),
				'body_text'     => $this->text( $xpath, './/p[contains(@class,"user-location-text")]', $onboarding ),
				'pos_title'     => $pos_title,
				'pos_text'      => $pos_text,
				'micro_text'    => $this->text( $xpath, './/p[contains(@class,"haendler-form-micro")]', $onboarding ),
			);
		}

		$faq = $this->xpath_section( $xpath, 'faq' );
		if ( $faq ) {
			$items = array();
			foreach ( $xpath->query( './/details[contains(@class,"faq-item")]', $faq ) as $detail ) {
				$items[] = array(
					'question' => $this->text( $xpath, './/summary//span', $detail ),
					'answer'   => $this->text( $xpath, './/div[contains(@class,"faq-answer")]//p', $detail ),
				);
			}
			$sections[] = array(
				'acf_fc_layout' => 'faq',
				'label'         => $this->text( $xpath, './/span[contains(@class,"solution-2-label")]', $faq ),
				'title'         => $this->text( $xpath, './/h2[contains(@class,"section-title")]', $faq ),
				'image'         => $this->resolve_image_from_xpath( $xpath, './/img[contains(@class,"solution-faq-img")]', $faq ),
				'image_alt'     => $this->attr( $xpath, './/img[contains(@class,"solution-faq-img")]', 'alt', $faq ),
				'faq_items'     => $items,
			);
		}

		$cta = $this->xpath_section( $xpath, 'download' );
		if ( $cta ) {
			$sections[] = array(
				'acf_fc_layout' => 'cta',
				'title'         => $this->multiline_text( $dom, $xpath, './/h2[contains(@class,"cta-title")]', $cta ),
				'subtitle'      => $this->text( $xpath, './/p[contains(@class,"cta-subtitle")]', $cta ),
				'button_1_text' => $this->text( $xpath, './/div[contains(@class,"cta-buttons-row")]//a[1]', $cta ),
				'button_1_url'  => $this->attr( $xpath, './/div[contains(@class,"cta-buttons-row")]//a[1]', 'href', $cta ),
				'button_2_text' => $this->text( $xpath, './/div[contains(@class,"cta-buttons-row")]//a[2]', $cta ),
				'button_2_url'  => $this->attr( $xpath, './/div[contains(@class,"cta-buttons-row")]//a[2]', 'href', $cta ),
			);
		}

		return $sections;
	}

	protected function build_legal_page_from_html( $html ) {
		list( $dom, $xpath ) = $this->create_dom_xpath( $html );

		$section = $xpath->query( '//section[contains(@class,"legal-section")]' )->item( 0 );
		if ( ! $section ) {
			return array(
				'headline' => '',
				'content'  => '',
			);
		}

		$content_node = $xpath->query( './/div[contains(@class,"legal-content")]', $section )->item( 0 );

		return array(
			'headline' => $this->text( $xpath, './/h1[contains(@class,"section-title")]', $section ),
			'content'  => $content_node ? $this->inner_html( $dom, $content_node ) : '',
		);
	}

	protected function build_download_page_from_html( $html ) {
		list( $dom, $xpath ) = $this->create_dom_xpath( $html );

		$section = $this->xpath_section( $xpath, 'download-page' );
		if ( ! $section ) {
			$section = $xpath->query( '//section[contains(@class,"download-page-section")]' )->item( 0 );
		}

		if ( ! $section ) {
			return array();
		}

		$content_node = $xpath->query( './/div[contains(@class,"download-copy-text")]', $section )->item( 0 );
		$store_links  = $xpath->query( './/a[contains(@class,"download-store-badge")]', $section );
		$apple_link   = $store_links->item( 0 );
		$google_link  = $store_links->item( 1 );
		$apple_img    = $apple_link ? $xpath->query( './/img', $apple_link )->item( 0 ) : null;
		$google_img   = $google_link ? $xpath->query( './/img', $google_link )->item( 0 ) : null;

		$platforms = array();
		foreach ( $xpath->query( './/ul[contains(@class,"download-platform-list")]//li', $section ) as $item ) {
			$text = trim( preg_replace( '/\s+/', ' ', $item->textContent ) );
			if ( '' !== $text ) {
				$platforms[] = array( 'text' => $text );
			}
		}

		$slides = array();
		foreach ( $xpath->query( './/img[contains(@class,"download-slider-img")]', $section ) as $image ) {
			if ( ! $image instanceof DOMElement ) {
				continue;
			}
			$src = trim( $image->getAttribute( 'src' ) );
			if ( '' === $src ) {
				continue;
			}
			$slides[] = array(
				'image' => $this->get_attachment_id_by_source( $src ),
				'alt'   => trim( $image->getAttribute( 'alt' ) ),
			);
		}

		return array(
			'eyebrow'      => $this->text( $xpath, './/span[contains(@class,"download-eyebrow")]', $section ),
			'headline'     => $this->text( $xpath, './/h1[contains(@class,"download-title")]', $section ),
			'subtitle'     => $this->text( $xpath, './/p[contains(@class,"download-subtitle")]', $section ),
			'content'      => $content_node ? $this->inner_html( $dom, $content_node ) : '',
			'apple_url'    => $apple_link instanceof DOMElement ? trim( $apple_link->getAttribute( 'href' ) ) : '',
			'apple_badge'  => $apple_img instanceof DOMElement ? $this->get_attachment_id_by_source( trim( $apple_img->getAttribute( 'src' ) ) ) : 0,
			'google_url'   => $google_link instanceof DOMElement ? trim( $google_link->getAttribute( 'href' ) ) : '',
			'google_badge' => $google_img instanceof DOMElement ? $this->get_attachment_id_by_source( trim( $google_img->getAttribute( 'src' ) ) ) : 0,
			'platforms'    => $platforms,
			'slides'       => $slides,
		);
	}

	protected function extract_feature_cards( DOMDocument $dom, DOMXPath $xpath, DOMNode $context ) {
		$features = array();
		foreach ( $xpath->query( './/div[contains(@class,"user-exclusive-feature glass-card")]', $context ) as $feature ) {
			$icon_wrap = $xpath->query( './/div[contains(@class,"user-exclusive-feature-icon")]', $feature )->item( 0 );
			$svg_el    = $xpath->query( './/div[contains(@class,"user-exclusive-feature-icon")]//svg', $feature )->item( 0 );
			$features[] = array(
				'icon_svg' => $svg_el ? $dom->saveHTML( $svg_el ) : '',
				'accent'   => $this->element_has_class( $icon_wrap, 'user-exclusive-feature-icon-accent' ),
				'title'    => $this->text( $xpath, './/h3', $feature ),
				'text'     => $this->text( $xpath, './/p', $feature ),
			);
		}

		return $features;
	}

	protected function extract_showcase_slides( DOMDocument $dom, DOMXPath $xpath, DOMNode $context ) {
		$slides      = array();
		$tabs        = $xpath->query( './/button[contains(@class,"app-showcase-tab")]', $context );
		$infos       = $xpath->query( './/div[contains(@class,"app-showcase-slide-info")]', $context );
		$images      = $xpath->query( './/img[contains(@class,"app-showcase-img")]', $context );
		$slide_count = max( $tabs->length, $infos->length, $images->length );

		for ( $i = 0; $i < $slide_count; $i++ ) {
			$tab   = $tabs->item( $i );
			$info  = $infos->item( $i );
			$image = $images->item( $i );
			$svg   = '';

			if ( $tab ) {
				$svg_el = $xpath->query( './/svg', $tab )->item( 0 );
				$svg    = $svg_el ? $dom->saveHTML( $svg_el ) : '';
			}

			$image_src = $image instanceof DOMElement ? trim( $image->getAttribute( 'src' ) ) : '';
			$slides[]  = array(
				'tab_label'    => $tab ? $this->text( $xpath, './/span', $tab ) : '',
				'tab_icon_svg' => $svg,
				'info_title'   => $info ? $this->text( $xpath, './/h3', $info ) : '',
				'info_text'    => $info ? $this->text( $xpath, './/p', $info ) : '',
				'image'        => $image_src ? $this->get_attachment_id_by_source( $image_src ) : 0,
				'image_alt'    => $image instanceof DOMElement ? trim( $image->getAttribute( 'alt' ) ) : '',
			);
		}

		return $slides;
	}

	protected function extract_title_lines( DOMXPath $xpath, $expr, DOMNode $context ) {
		$lines = array();
		foreach ( $xpath->query( $expr, $context ) as $node ) {
			$lines[] = array(
				'text'   => trim( $node->textContent ),
				'accent' => $this->element_has_class( $node, 'haendler-hero-accent' ),
			);
		}

		return $lines;
	}

	protected function resolve_image_from_xpath( DOMXPath $xpath, $expr, DOMNode $context ) {
		$src = $this->attr( $xpath, $expr, 'src', $context );
		return $src ? $this->get_attachment_id_by_source( $src ) : 0;
	}

	protected function create_dom_xpath( $html ) {
		$html = (string) $html;
		$mode = 'raw_html';

		if ( preg_match( '/<body\b[^>]*>(.*)<\/body>/is', $html, $matches ) ) {
			$html = (string) $matches[1];
			$mode = 'body_fragment';
		}

		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		$errors = libxml_get_errors();
		libxml_clear_errors();

		$this->last_parser_diagnostics = array(
			'mode'          => $mode,
			'error_count'   => is_array( $errors ) ? count( $errors ) : 0,
			'error_summary' => $this->summarize_libxml_errors( is_array( $errors ) ? $errors : array() ),
		);

		return array( $dom, new DOMXPath( $dom ) );
	}

	protected function get_last_parser_diagnostics_message() {
		if ( empty( $this->last_parser_diagnostics ) || ! is_array( $this->last_parser_diagnostics ) ) {
			return 'Parser diagnostics unavailable.';
		}

		$mode        = (string) ( $this->last_parser_diagnostics['mode'] ?? 'unknown' );
		$error_count = (int) ( $this->last_parser_diagnostics['error_count'] ?? 0 );
		$summary     = (string) ( $this->last_parser_diagnostics['error_summary'] ?? '' );

		$message = 'Parser mode=' . $mode . ', errors=' . $error_count . '.';
		if ( '' !== $summary ) {
			$message .= ' ' . $summary;
		}

		return $message;
	}

	protected function summarize_libxml_errors( $errors ) {
		if ( empty( $errors ) || ! is_array( $errors ) ) {
			return '';
		}

		$messages = array();
		foreach ( array_slice( $errors, 0, 3 ) as $error ) {
			if ( ! $error instanceof LibXMLError ) {
				continue;
			}

			$message = trim( preg_replace( '/\s+/', ' ', (string) $error->message ) );
			if ( '' === $message ) {
				continue;
			}

			$messages[] = $message;
		}

		return ! empty( $messages ) ? 'LibXML: ' . implode( ' | ', $messages ) : '';
	}

	protected function xpath_section( DOMXPath $xpath, $id ) {
		$nodes = $xpath->query( "//*[@id='" . $id . "']" );
		return $nodes->length > 0 ? $nodes->item( 0 ) : null;
	}

	protected function text( DOMXPath $xpath, $expr, $context ) {
		$nodes = $xpath->query( $expr, $context );
		if ( 0 === $nodes->length ) {
			return '';
		}

		return trim( preg_replace( '/\s+/', ' ', $nodes->item( 0 )->textContent ) );
	}

	protected function attr( DOMXPath $xpath, $expr, $attr, $context ) {
		$nodes = $xpath->query( $expr, $context );
		if ( 0 === $nodes->length ) {
			return '';
		}

		return $this->attr_node( $nodes->item( 0 ), $attr );
	}

	protected function attr_node( DOMNode $node, $attr ) {
		if ( ! $node instanceof DOMElement || ! $node->hasAttribute( $attr ) ) {
			return '';
		}

		return trim( $node->getAttribute( $attr ) );
	}

	protected function multiline_text( DOMDocument $dom, DOMXPath $xpath, $expr, $context ) {
		$node = $xpath->query( $expr, $context )->item( 0 );
		if ( ! $node ) {
			return '';
		}

		$html = trim( $dom->saveHTML( $node ) );
		$html = preg_replace( '#^<[^>]+>#', '', $html );
		$html = preg_replace( '#</[^>]+>$#', '', $html );
		$html = preg_replace( '#<br\s*/?>#i', "\n", $html );
		$html = trim( wp_strip_all_tags( $html ) );
		$lines = array_map( 'trim', preg_split( '/\r\n|\r|\n/', $html ) );
		$lines = array_filter( $lines, 'strlen' );

		return implode( "\n", $lines );
	}

	protected function inner_html( DOMDocument $dom, DOMNode $node ) {
		$html = '';

		foreach ( $node->childNodes as $child ) {
			$html .= $dom->saveHTML( $child );
		}

		return trim( $html );
	}

	protected function element_has_class( $node, $class ) {
		if ( ! $node instanceof DOMElement ) {
			return false;
		}

		$current = ' ' . trim( $node->getAttribute( 'class' ) ) . ' ';
		return false !== strpos( $current, ' ' . $class . ' ' );
	}
}
