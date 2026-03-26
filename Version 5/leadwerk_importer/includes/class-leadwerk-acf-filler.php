<?php
/**
 * ACF-Befüllung der Startseite aus index.html
 * (Hero, Why, App Steps, Pakete, Solutions, FAQ, CTA).
 *
 * @package Leadwerk_Importer
 */
class Leadwerk_ACF_Filler {

	protected $source_root = '';
	protected $attachment_cache = array();

	/**
	 * Attachment-ID anhand des Quellpfads ermitteln.
	 */
	public function get_attachment_id_by_source( $path ) {
		$norm = $this->normalize_path( $path );
		if ( isset( $this->attachment_cache[ $norm ] ) ) {
			return $this->attachment_cache[ $norm ];
		}
		$id = 0;
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
		} else {
			$basename = wp_basename( $path );
			$q2 = new WP_Query( array(
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'fields'      => 'ids',
				'posts_per_page' => 1,
				'meta_query'  => array(
					array( 'key' => '_wp_attached_file', 'value' => $basename, 'compare' => 'LIKE' ),
				),
			) );
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

	/**
	 * Startseite mit home_sections befüllen.
	 */
	public function fill_front_page( $post_id, $source_root ) {
		if ( ! function_exists( 'update_field' ) || ! $post_id ) {
			Leadwerk_Logger::log( 'Fields-Befüllung übersprungen (API nicht aktiv oder keine Post-ID).' );
			return false;
		}
		$this->source_root = rtrim( $source_root, '/\\' );
		$index_path        = $this->source_root . DIRECTORY_SEPARATOR . 'index.html';
		if ( ! is_file( $index_path ) ) {
			Leadwerk_Logger::log( 'index.html nicht gefunden: ' . $index_path );
			return false;
		}
		$html     = file_get_contents( $index_path );
		$sections = $this->build_home_sections_from_html( $html );
		if ( empty( $sections ) ) {
			Leadwerk_Logger::log( 'Keine Sektionen aus index.html extrahiert.' );
			return false;
		}
		update_field( 'home_sections', $sections, $post_id );
		Leadwerk_Logger::log( 'home_sections befüllt: ' . count( $sections ) . ' Layout(s) für Startseite (ID ' . $post_id . ').' );
		return true;
	}

	/**
	 * HTML parsen und Flexible-Content-Array bauen.
	 */
	protected function build_home_sections_from_html( $html ) {
		$sections = array();
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		$xpath = new DOMXPath( $dom );

		// ── Hero ──
		$hero = $this->xpath_section( $xpath, 'hero' );
		if ( $hero ) {
			$hero_img = $this->attr( $xpath, './/div[contains(@class,"hero-frau-layer")]//img', 'src', $hero );
			$sections[] = array(
				'acf_fc_layout'    => 'hero',
				'title_gradient'   => $this->text( $xpath, './/h1[contains(@class,"hero-title")]//span[contains(@class,"text-gradient")]', $hero ),
				'typewriter_words' => 'Shoppe lokal.|Finde Deals.|Entdecke Mode.|Stärke deine Stadt.',
				'cta_text'         => $this->text( $xpath, './/div[contains(@class,"hero-cta")]//a', $hero ) ?: 'App herunterladen',
				'cta_url'          => $this->attr( $xpath, './/div[contains(@class,"hero-cta")]//a', 'href', $hero ) ?: '#download',
				'hero_image'       => $hero_img ? $this->get_attachment_id_by_source( $hero_img ) : 0,
			);
		}

		// ── Why ──
		$why = $this->xpath_section( $xpath, 'why' );
		if ( $why ) {
			$cards = array();
			$card_nodes = $xpath->query( './/div[contains(@class,"why-float-card")]', $why );
			foreach ( $card_nodes as $card ) {
				$text   = $this->text( $xpath, './/p[contains(@class,"why-item-text")]', $card );
				$detail = $this->text( $xpath, './/p[contains(@class,"why-item-detail")]', $card );
				$svg    = '';
				$svg_el = $xpath->query( './/span[contains(@class,"why-item-icon")]//svg', $card )->item( 0 );
				if ( $svg_el ) {
					$svg = $dom->saveHTML( $svg_el );
				}
				$cards[] = array(
					'icon_svg' => $svg,
					'text'     => $text,
					'detail'   => $detail,
				);
			}
			$bag_img = $this->attr( $xpath, './/div[contains(@class,"why-bag")]//img', 'src', $why );
			$sections[] = array(
				'acf_fc_layout'  => 'why',
				'label'          => $this->text( $xpath, './/span[contains(@class,"why-label")]', $why ),
				'title'          => $this->text( $xpath, './/h2[contains(@class,"section-title")]', $why ),
				'cards'          => $cards,
				'bag_image'      => $bag_img ? $this->get_attachment_id_by_source( $bag_img ) : 0,
			);
		}

		// ── App Steps ──
		$steps = $this->xpath_section( $xpath, 'app-steps' );
		if ( $steps ) {
			$step_items = array();
			$slide_nodes = $xpath->query( './/div[contains(@class,"app-step-slide")]', $steps );
			foreach ( $slide_nodes as $slide ) {
				$img_top = $slide instanceof DOMElement ? $slide->getAttribute( 'data-img-top' ) : '';
				$img_bot = $slide instanceof DOMElement ? $slide->getAttribute( 'data-img-bottom' ) : '';
				$step_items[] = array(
					'number' => $this->text( $xpath, './/span[contains(@class,"app-step-number")]', $slide ),
					'text'   => $this->text( $xpath, './/p[contains(@class,"app-step-text")]', $slide ),
					'image_top'    => $img_top ? $this->get_attachment_id_by_source( $img_top ) : 0,
					'image_bottom' => $img_bot ? $this->get_attachment_id_by_source( $img_bot ) : 0,
				);
			}
			$sections[] = array(
				'acf_fc_layout' => 'app_steps',
				'headline'      => $this->text( $xpath, './/h2[contains(@class,"app-steps-headline")]', $steps ),
				'steps'         => $step_items,
			);
		}

		// ── Pakete ──
		$pakete = $this->xpath_section( $xpath, 'pakete' );
		if ( $pakete ) {
			$pkg_img = $this->attr( $xpath, './/div[contains(@class,"pakete-image")]//img', 'src', $pakete );
			$content = '';
			$ps = $xpath->query( './/div[contains(@class,"pakete-text")]//p', $pakete );
			foreach ( $ps as $p ) {
				$content .= $dom->saveHTML( $p );
			}
			$sections[] = array(
				'acf_fc_layout' => 'pakete',
				'label'         => $this->text( $xpath, './/span[contains(@class,"pakete-label")]', $pakete ),
				'title'         => $this->text( $xpath, './/h2[contains(@class,"pakete-title")]', $pakete ),
				'content'       => $content,
				'cta_text'      => $this->text( $xpath, './/a[contains(@class,"pakete-cta")]', $pakete ) ?: 'Mehr erfahren',
				'cta_url'       => $this->attr( $xpath, './/a[contains(@class,"pakete-cta")]', 'href', $pakete ) ?: '#download',
				'image'         => $pkg_img ? $this->get_attachment_id_by_source( $pkg_img ) : 0,
			);
		}

		// ── Solutions ──
		$sol = $this->xpath_section( $xpath, 'solution-2' );
		if ( $sol ) {
			$sol_cards = array();
			$card_nodes = $xpath->query( './/article[contains(@class,"solution-card")]', $sol );
			foreach ( $card_nodes as $card ) {
				$svg = '';
				$svg_el = $xpath->query( './/div[contains(@class,"solution-card-icon")]//svg', $card )->item( 0 );
				if ( $svg_el ) {
					$svg = $dom->saveHTML( $svg_el );
				}
				$sol_cards[] = array(
					'icon_svg'    => $svg,
					'title'       => $this->text( $xpath, './/h3', $card ),
					'description' => $this->text( $xpath, './/p[contains(@class,"solution-card-desc")]', $card ),
					'link_text'   => $this->text( $xpath, './/a[contains(@class,"solution-card-link")]', $card ),
					'link_url'    => $this->attr( $xpath, './/a[contains(@class,"solution-card-link")]', 'href', $card ),
				);
			}
			$content = '';
			$ps = $xpath->query( './/div[contains(@class,"solution-2-text")]//p', $sol );
			foreach ( $ps as $p ) {
				$content .= $dom->saveHTML( $p );
			}
			$sections[] = array(
				'acf_fc_layout'    => 'solutions',
				'label'            => $this->text( $xpath, './/span[contains(@class,"solution-2-label")]', $sol ),
				'title'            => $this->text( $xpath, './/h2[contains(@class,"solution-title")]', $sol ),
				'intro_text'       => $content,
				'register_btn_text' => $this->text( $xpath, './/div[contains(@class,"solution-intro")]//a[contains(@class,"btn")]', $sol ) ?: 'Unternehmen registrieren',
				'register_btn_url'  => $this->attr( $xpath, './/div[contains(@class,"solution-intro")]//a[contains(@class,"btn")]', 'href', $sol ) ?: '#download',
				'cards'            => $sol_cards,
			);
		}

		// ── FAQ ──
		$faq = $this->xpath_section( $xpath, 'solution-faq' );
		if ( $faq ) {
			$faq_items = array();
			$detail_nodes = $xpath->query( './/details[contains(@class,"faq-item")]', $faq );
			foreach ( $detail_nodes as $detail ) {
				$question = $this->text( $xpath, './/summary//span', $detail );
				$answer   = $this->text( $xpath, './/div[contains(@class,"faq-answer")]//p', $detail );
				$faq_items[] = array(
					'question' => $question,
					'answer'   => $answer,
				);
			}
			$faq_img = $this->attr( $xpath, './/div[contains(@class,"solution-faq-image-wrap")]//img', 'src', $faq );
			$sections[] = array(
				'acf_fc_layout'  => 'faq',
				'label'          => $this->text( $xpath, './/span[contains(@class,"solution-2-label")]', $faq ),
				'title'          => $this->text( $xpath, './/h2[contains(@class,"section-title")]', $faq ),
				'items'          => $faq_items,
				'image'          => $faq_img ? $this->get_attachment_id_by_source( $faq_img ) : 0,
			);
		}

		// ── CTA ──
		$cta = $this->xpath_section( $xpath, 'download' );
		if ( $cta ) {
			// Extract the raw HTML of h2 to preserve <br>
			$cta_title_el = $xpath->query( './/h2[contains(@class,"cta-title")]', $cta )->item( 0 );
			$cta_title = $cta_title_el ? trim( strip_tags( $dom->saveHTML( $cta_title_el ), '<br>' ) ) : '';
			$cta_title = str_replace( '<br>', "\n", $cta_title );
			$sections[] = array(
				'acf_fc_layout' => 'cta',
				'title'         => $cta_title,
				'button_1_text' => $this->text( $xpath, './/div[contains(@class,"cta-buttons-row")]//a[1]', $cta ) ?: 'Unternehmen registrieren',
				'button_1_url'  => $this->attr( $xpath, './/div[contains(@class,"cta-buttons-row")]//a[1]', 'href', $cta ) ?: '#',
				'button_2_text' => $this->text( $xpath, './/div[contains(@class,"cta-buttons-row")]//a[2]', $cta ) ?: 'App herunterladen',
				'button_2_url'  => $this->attr( $xpath, './/div[contains(@class,"cta-buttons-row")]//a[2]', 'href', $cta ) ?: '#',
			);
		}

		return $sections;
	}

	/* ── Helper methods ── */

	protected function xpath_section( DOMXPath $xpath, $id ) {
		$nodes = $xpath->query( "//section[@id='" . $id . "']" );
		if ( $nodes->length > 0 ) {
			return $nodes->item( 0 );
		}
		// Fallback: any element with matching id (div, etc.)
		$nodes = $xpath->query( "//*[@id='" . $id . "']" );
		return $nodes->length > 0 ? $nodes->item( 0 ) : null;
	}

	protected function text( DOMXPath $xpath, $expr, $context ) {
		$nodes = $xpath->query( $expr, $context );
		if ( $nodes->length === 0 ) return '';
		return trim( $nodes->item( 0 )->textContent );
	}

	protected function attr( DOMXPath $xpath, $expr, $attr, $context ) {
		$nodes = $xpath->query( $expr, $context );
		if ( $nodes->length === 0 ) return '';
		return $this->attr_node( $nodes->item( 0 ), $attr );
	}

	protected function attr_node( DOMNode $node, $attr ) {
		if ( ! $node instanceof DOMElement || ! $node->hasAttribute( $attr ) ) return '';
		return trim( $node->getAttribute( $attr ) );
	}
}
