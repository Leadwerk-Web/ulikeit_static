<?php
/**
 * Shared schema for importer, fields metaboxes and theme renderers.
 *
 * @package Leadwerk_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leadwerk_Content_Schema {

	/**
	 * Return all section field groups.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_groups() {
		static $groups = null;

		if ( null !== $groups ) {
			return $groups;
		}

		$groups = array(
			'home_sections'     => array(
				'label'       => 'U-like-it Startseiten-Sektionen',
				'description' => 'Sektionen der Startseite bearbeiten. Reihenfolge und Anzahl bleiben erhalten.',
				'source_keys' => array( 'ulikeit-home-v1' ),
				'post_match'  => array(
					'is_front_page' => true,
					'slugs'         => array( 'home' ),
				),
				'block_content' => '<!-- wp:acf/ulikeit-home-sections /-->',
				'layouts'     => array(
					'hero'      => array(
						'label'  => 'Hero',
						'fields' => array(
							'title_gradient'   => array( 'label' => 'Titel (Gradient)', 'type' => 'text' ),
							'typewriter_words' => array( 'label' => 'Typewriter-Woerter (|)', 'type' => 'text' ),
							'cta_text'         => array( 'label' => 'CTA Button Text', 'type' => 'text' ),
							'cta_url'          => array( 'label' => 'CTA Button URL', 'type' => 'url' ),
							'hero_image'       => array( 'label' => 'Hero-Bild', 'type' => 'image' ),
						),
					),
					'why'       => array(
						'label'  => 'Why / Warum',
						'fields' => array(
							'label'     => array( 'label' => 'Label', 'type' => 'text' ),
							'title'     => array( 'label' => 'Titel', 'type' => 'text' ),
							'bag_image' => array( 'label' => 'Bag-Bild', 'type' => 'image' ),
							'cards'     => array(
								'label'            => 'Cards',
								'type'             => 'repeater',
								'add_button_label' => 'Card hinzufuegen',
								'fields'           => array(
									'icon_svg' => array( 'label' => 'Icon SVG', 'type' => 'svg_code' ),
									'text'     => array( 'label' => 'Text', 'type' => 'text' ),
									'detail'   => array( 'label' => 'Detail', 'type' => 'textarea' ),
								),
							),
						),
					),
					'app_steps' => array(
						'label'  => 'App Steps',
						'fields' => array(
							'headline' => array( 'label' => 'Ueberschrift', 'type' => 'text' ),
							'steps'    => array(
								'label'            => 'Schritte',
								'type'             => 'repeater',
								'add_button_label' => 'Schritt hinzufuegen',
								'fields'           => array(
									'number'       => array( 'label' => 'Nummer', 'type' => 'text' ),
									'text'         => array( 'label' => 'Text', 'type' => 'textarea' ),
									'image_top'    => array( 'label' => 'Bild oben', 'type' => 'image' ),
									'image_bottom' => array( 'label' => 'Bild unten', 'type' => 'image' ),
								),
							),
						),
					),
					'pakete'    => array(
						'label'  => 'Pakete',
						'fields' => array(
							'label'    => array( 'label' => 'Label', 'type' => 'text' ),
							'title'    => array( 'label' => 'Titel', 'type' => 'text' ),
							'content'  => array( 'label' => 'Inhalt', 'type' => 'wysiwyg' ),
							'cta_text' => array( 'label' => 'CTA Text', 'type' => 'text' ),
							'cta_url'  => array( 'label' => 'CTA URL', 'type' => 'url' ),
							'image'    => array( 'label' => 'Bild', 'type' => 'image' ),
						),
					),
					'solutions' => array(
						'label'  => 'Unsere Loesungen',
						'fields' => array(
							'label'             => array( 'label' => 'Label', 'type' => 'text' ),
							'title'             => array( 'label' => 'Titel', 'type' => 'text' ),
							'intro_text'        => array( 'label' => 'Intro-Text', 'type' => 'wysiwyg' ),
							'register_btn_text' => array( 'label' => 'Button Text', 'type' => 'text' ),
							'register_btn_url'  => array( 'label' => 'Button URL', 'type' => 'url' ),
							'cards'             => array(
								'label'            => 'Cards',
								'type'             => 'repeater',
								'add_button_label' => 'Card hinzufuegen',
								'fields'           => array(
									'icon_svg'    => array( 'label' => 'Icon SVG', 'type' => 'svg_code' ),
									'title'       => array( 'label' => 'Titel', 'type' => 'text' ),
									'description' => array( 'label' => 'Beschreibung', 'type' => 'textarea' ),
									'link_text'   => array( 'label' => 'Link Text', 'type' => 'text' ),
									'link_url'    => array( 'label' => 'Link URL', 'type' => 'url' ),
								),
							),
						),
					),
					'faq'       => array(
						'label'  => 'FAQ',
						'fields' => array(
							'label' => array( 'label' => 'Label', 'type' => 'text' ),
							'title' => array( 'label' => 'Titel', 'type' => 'text' ),
							'image' => array( 'label' => 'FAQ-Bild', 'type' => 'image' ),
							'items' => array(
								'label'            => 'FAQ-Eintraege',
								'type'             => 'repeater',
								'add_button_label' => 'FAQ-Eintrag hinzufuegen',
								'fields'           => array(
									'question' => array( 'label' => 'Frage', 'type' => 'text' ),
									'answer'   => array( 'label' => 'Antwort', 'type' => 'textarea' ),
								),
							),
						),
					),
					'cta'       => array(
						'label'  => 'Final CTA',
						'fields' => array(
							'title'         => array( 'label' => 'Titel', 'type' => 'textarea' ),
							'button_1_text' => array( 'label' => 'Button 1 Text', 'type' => 'text' ),
							'button_1_url'  => array( 'label' => 'Button 1 URL', 'type' => 'url' ),
							'button_2_text' => array( 'label' => 'Button 2 Text', 'type' => 'text' ),
							'button_2_url'  => array( 'label' => 'Button 2 URL', 'type' => 'url' ),
						),
					),
				),
			),
			'user_sections'     => array(
				'label'       => 'U-like-it Nutzer-Seite',
				'description' => 'Sektionen der Nutzer-Seite bearbeiten. Reihenfolge und Anzahl bleiben erhalten.',
				'source_keys' => array( 'ulikeit-user-v1' ),
				'post_match'  => array(
					'slugs' => array( 'fuer-nutzer' ),
				),
				'block_content' => '<!-- wp:acf/ulikeit-user-sections /-->',
				'layouts'     => array(
					'hero'         => array(
						'label'  => 'Hero',
						'fields' => array(
							'title_lines' => array(
								'label'            => 'Titelzeilen',
								'type'             => 'repeater',
								'add_button_label' => 'Titelzeile hinzufuegen',
								'fields'           => array(
									'text'   => array( 'label' => 'Text', 'type' => 'text' ),
									'accent' => array( 'label' => 'Akzent', 'type' => 'checkbox' ),
								),
							),
							'subtitle'    => array( 'label' => 'Subtitle', 'type' => 'textarea' ),
							'micro_text'  => array( 'label' => 'Micro-Text', 'type' => 'text' ),
							'hero_image'  => array( 'label' => 'Hero-Bild', 'type' => 'image' ),
						),
					),
					'how_it_works' => array(
						'label'  => 'How It Works',
						'fields' => array(
							'label' => array( 'label' => 'Label', 'type' => 'text' ),
							'title' => array( 'label' => 'Titel', 'type' => 'text' ),
							'steps' => array(
								'label'            => 'Schritte',
								'type'             => 'repeater',
								'add_button_label' => 'Schritt hinzufuegen',
								'fields'           => array(
									'number'   => array( 'label' => 'Nummer', 'type' => 'text' ),
									'icon_svg' => array( 'label' => 'Icon SVG', 'type' => 'svg_code' ),
									'title'    => array( 'label' => 'Titel', 'type' => 'text' ),
									'text'     => array( 'label' => 'Text', 'type' => 'textarea' ),
								),
							),
						),
					),
					'app_preview'  => array(
						'label'  => 'App Preview',
						'fields' => array(
							'label'           => array( 'label' => 'Label', 'type' => 'text' ),
							'title'           => array( 'label' => 'Titel', 'type' => 'text' ),
							'swipe_hint_text' => array( 'label' => 'Swipe-Hinweis', 'type' => 'text' ),
							'slides'          => array(
								'label'            => 'Slides',
								'type'             => 'repeater',
								'add_button_label' => 'Slide hinzufuegen',
								'fields'           => array(
									'tab_label'    => array( 'label' => 'Tab Label', 'type' => 'text' ),
									'tab_icon_svg' => array( 'label' => 'Tab Icon SVG', 'type' => 'svg_code' ),
									'info_title'   => array( 'label' => 'Info Titel', 'type' => 'text' ),
									'info_text'    => array( 'label' => 'Info Text', 'type' => 'textarea' ),
									'image'        => array( 'label' => 'Bild', 'type' => 'image' ),
									'image_alt'    => array( 'label' => 'Bild Alt', 'type' => 'text' ),
								),
							),
						),
					),
					'location'     => array(
						'label'  => 'Location',
						'fields' => array(
							'label'               => array( 'label' => 'Label', 'type' => 'text' ),
							'title'               => array( 'label' => 'Titel', 'type' => 'text' ),
							'body_text'           => array( 'label' => 'Text', 'type' => 'textarea' ),
							'input_placeholder'   => array( 'label' => 'Input Placeholder', 'type' => 'text' ),
							'image'               => array( 'label' => 'Bild', 'type' => 'image' ),
							'image_alt'           => array( 'label' => 'Bild Alt', 'type' => 'text' ),
							'city_tags'           => array(
								'label'            => 'City Tags',
								'type'             => 'repeater',
								'add_button_label' => 'City Tag hinzufuegen',
								'fields'           => array(
									'label' => array( 'label' => 'Button Label', 'type' => 'text' ),
									'value' => array( 'label' => 'City Value', 'type' => 'text' ),
								),
							),
							'modal_title_prefix'  => array( 'label' => 'Modal Titel Prefix', 'type' => 'text' ),
							'modal_fallback_city' => array( 'label' => 'Modal Fallback City', 'type' => 'text' ),
							'modal_text'          => array( 'label' => 'Modal Text', 'type' => 'textarea' ),
							'modal_micro'         => array( 'label' => 'Modal Micro', 'type' => 'text' ),
						),
					),
					'categories'   => array(
						'label'  => 'Categories',
						'fields' => array(
							'label'      => array( 'label' => 'Label', 'type' => 'text' ),
							'title'      => array( 'label' => 'Titel', 'type' => 'text' ),
							'categories' => array(
								'label'            => 'Kategorien',
								'type'             => 'repeater',
								'add_button_label' => 'Kategorie hinzufuegen',
								'fields'           => array(
									'image'     => array( 'label' => 'Bild', 'type' => 'image' ),
									'image_alt' => array( 'label' => 'Bild Alt', 'type' => 'text' ),
									'icon_svg'  => array( 'label' => 'Icon SVG', 'type' => 'svg_code' ),
									'title'     => array( 'label' => 'Titel', 'type' => 'text' ),
									'text'      => array( 'label' => 'Text', 'type' => 'textarea' ),
								),
							),
						),
					),
					'exclusive'    => array(
						'label'  => 'Exclusive',
						'fields' => array(
							'label'    => array( 'label' => 'Label', 'type' => 'text' ),
							'title'    => array( 'label' => 'Titel', 'type' => 'textarea' ),
							'lead'     => array( 'label' => 'Lead', 'type' => 'textarea' ),
							'features' => array(
								'label'            => 'Features',
								'type'             => 'repeater',
								'add_button_label' => 'Feature hinzufuegen',
								'fields'           => array(
									'icon_svg' => array( 'label' => 'Icon SVG', 'type' => 'svg_code' ),
									'accent'   => array( 'label' => 'Akzent-Icon', 'type' => 'checkbox' ),
									'title'    => array( 'label' => 'Titel', 'type' => 'text' ),
									'text'     => array( 'label' => 'Text', 'type' => 'textarea' ),
								),
							),
						),
					),
					'cta'          => array(
						'label'  => 'CTA',
						'fields' => array(
							'title'      => array( 'label' => 'Titel', 'type' => 'textarea' ),
							'subtitle'   => array( 'label' => 'Subtitle', 'type' => 'textarea' ),
							'micro_text' => array( 'label' => 'Micro-Text', 'type' => 'text' ),
						),
					),
				),
			),
			'haendler_sections' => array(
				'label'       => 'U-like-it Haendler-Seite',
				'description' => 'Sektionen der Haendler-Seite bearbeiten. Reihenfolge und Anzahl bleiben erhalten.',
				'source_keys' => array( 'ulikeit-haendler-v1' ),
				'post_match'  => array(
					'slugs' => array( 'fuer-haendler' ),
				),
				'block_content' => '<!-- wp:acf/ulikeit-haendler-sections /-->',
				'layouts'     => array(
					'hero'              => array(
						'label'  => 'Hero',
						'fields' => array(
							'title_lines'           => array(
								'label'            => 'Titelzeilen',
								'type'             => 'repeater',
								'add_button_label' => 'Titelzeile hinzufuegen',
								'fields'           => array(
									'text'   => array( 'label' => 'Text', 'type' => 'text' ),
									'accent' => array( 'label' => 'Akzent', 'type' => 'checkbox' ),
								),
							),
							'subtitle'              => array( 'label' => 'Subtitle', 'type' => 'textarea' ),
							'primary_button_text'   => array( 'label' => 'Primary Button Text', 'type' => 'text' ),
							'primary_button_url'    => array( 'label' => 'Primary Button URL', 'type' => 'url' ),
							'secondary_button_text' => array( 'label' => 'Secondary Button Text', 'type' => 'text' ),
							'secondary_button_url'  => array( 'label' => 'Secondary Button URL', 'type' => 'url' ),
							'micro_text'            => array( 'label' => 'Micro-Text', 'type' => 'text' ),
							'hero_image'            => array( 'label' => 'Hero-Bild', 'type' => 'image' ),
						),
					),
					'benefits'          => array(
						'label'  => 'Benefits',
						'fields' => array(
							'label'    => array( 'label' => 'Label', 'type' => 'text' ),
							'title'    => array( 'label' => 'Titel', 'type' => 'textarea' ),
							'lead'     => array( 'label' => 'Lead', 'type' => 'textarea' ),
							'features' => array(
								'label'            => 'Features',
								'type'             => 'repeater',
								'add_button_label' => 'Feature hinzufuegen',
								'fields'           => array(
									'icon_svg' => array( 'label' => 'Icon SVG', 'type' => 'svg_code' ),
									'accent'   => array( 'label' => 'Akzent-Icon', 'type' => 'checkbox' ),
									'title'    => array( 'label' => 'Titel', 'type' => 'text' ),
									'text'     => array( 'label' => 'Text', 'type' => 'textarea' ),
								),
							),
						),
					),
					'how_it_works'      => array(
						'label'  => 'How It Works',
						'fields' => array(
							'label' => array( 'label' => 'Label', 'type' => 'text' ),
							'title' => array( 'label' => 'Titel', 'type' => 'text' ),
							'steps' => array(
								'label'            => 'Schritte',
								'type'             => 'repeater',
								'add_button_label' => 'Schritt hinzufuegen',
								'fields'           => array(
									'number'   => array( 'label' => 'Nummer', 'type' => 'text' ),
									'icon_svg' => array( 'label' => 'Icon SVG', 'type' => 'svg_code' ),
									'title'    => array( 'label' => 'Titel', 'type' => 'text' ),
									'text'     => array( 'label' => 'Text', 'type' => 'textarea' ),
								),
							),
						),
					),
					'dashboard_preview' => array(
						'label'  => 'Dashboard Preview',
						'fields' => array(
							'label'           => array( 'label' => 'Label', 'type' => 'text' ),
							'title'           => array( 'label' => 'Titel', 'type' => 'text' ),
							'swipe_hint_text' => array( 'label' => 'Swipe-Hinweis', 'type' => 'text' ),
							'slides'          => array(
								'label'            => 'Slides',
								'type'             => 'repeater',
								'add_button_label' => 'Slide hinzufuegen',
								'fields'           => array(
									'tab_label'    => array( 'label' => 'Tab Label', 'type' => 'text' ),
									'tab_icon_svg' => array( 'label' => 'Tab Icon SVG', 'type' => 'svg_code' ),
									'info_title'   => array( 'label' => 'Info Titel', 'type' => 'text' ),
									'info_text'    => array( 'label' => 'Info Text', 'type' => 'textarea' ),
									'image'        => array( 'label' => 'Bild', 'type' => 'image' ),
									'image_alt'    => array( 'label' => 'Bild Alt', 'type' => 'text' ),
								),
							),
						),
					),
					'use_cases'         => array(
						'label'  => 'Use Cases',
						'fields' => array(
							'label'     => array( 'label' => 'Label', 'type' => 'text' ),
							'title'     => array( 'label' => 'Titel', 'type' => 'text' ),
							'image'     => array( 'label' => 'Bild', 'type' => 'image' ),
							'image_alt' => array( 'label' => 'Bild Alt', 'type' => 'text' ),
							'cases'     => array(
								'label'            => 'Cases',
								'type'             => 'repeater',
								'add_button_label' => 'Case hinzufuegen',
								'fields'           => array(
									'icon_svg' => array( 'label' => 'Icon SVG', 'type' => 'svg_code' ),
									'label'    => array( 'label' => 'Label', 'type' => 'text' ),
									'quote'    => array( 'label' => 'Zitat', 'type' => 'text' ),
									'result'   => array( 'label' => 'Ergebnis', 'type' => 'textarea' ),
								),
							),
						),
					),
					'conditions'        => array(
						'label'  => 'Conditions',
						'fields' => array(
							'label'      => array( 'label' => 'Label', 'type' => 'text' ),
							'title'      => array( 'label' => 'Titel', 'type' => 'text' ),
							'content'    => array( 'label' => 'Inhalt', 'type' => 'wysiwyg' ),
							'conditions' => array(
								'label'            => 'Konditionen',
								'type'             => 'repeater',
								'add_button_label' => 'Kondition hinzufuegen',
								'fields'           => array(
									'text' => array( 'label' => 'Text', 'type' => 'text' ),
								),
							),
							'cta_text'   => array( 'label' => 'CTA Text', 'type' => 'text' ),
							'cta_url'    => array( 'label' => 'CTA URL', 'type' => 'url' ),
						),
					),
					'onboarding'        => array(
						'label'  => 'Onboarding',
						'fields' => array(
							'label'       => array( 'label' => 'Label', 'type' => 'text' ),
							'title'       => array( 'label' => 'Titel', 'type' => 'text' ),
							'body_text'   => array( 'label' => 'Text', 'type' => 'textarea' ),
							'pos_title'   => array( 'label' => 'POS Titel', 'type' => 'text' ),
							'pos_text'    => array( 'label' => 'POS Text', 'type' => 'textarea' ),
							'micro_text'  => array( 'label' => 'Micro-Text', 'type' => 'text' ),
						),
					),
					'faq'               => array(
						'label'  => 'FAQ',
						'fields' => array(
							'label'     => array( 'label' => 'Label', 'type' => 'text' ),
							'title'     => array( 'label' => 'Titel', 'type' => 'text' ),
							'image'     => array( 'label' => 'Bild', 'type' => 'image' ),
							'image_alt' => array( 'label' => 'Bild Alt', 'type' => 'text' ),
							'faq_items' => array(
								'label'            => 'FAQ-Eintraege',
								'type'             => 'repeater',
								'add_button_label' => 'FAQ-Eintrag hinzufuegen',
								'fields'           => array(
									'question' => array( 'label' => 'Frage', 'type' => 'text' ),
									'answer'   => array( 'label' => 'Antwort', 'type' => 'textarea' ),
								),
							),
						),
					),
					'cta'               => array(
						'label'  => 'CTA',
						'fields' => array(
							'title'         => array( 'label' => 'Titel', 'type' => 'textarea' ),
							'subtitle'      => array( 'label' => 'Subtitle', 'type' => 'textarea' ),
							'button_1_text' => array( 'label' => 'Button 1 Text', 'type' => 'text' ),
							'button_1_url'  => array( 'label' => 'Button 1 URL', 'type' => 'url' ),
							'button_2_text' => array( 'label' => 'Button 2 Text', 'type' => 'text' ),
							'button_2_url'  => array( 'label' => 'Button 2 URL', 'type' => 'url' ),
						),
					),
				),
			),
			'impressum_page'    => array(
				'label'             => 'U-like-it Impressum',
				'description'       => 'Impressum ueber Leadwerk Fields bearbeiten. Der Inhalt wird in das Seiten-HTML synchronisiert.',
				'source_keys'       => array( 'ulikeit-impressum-v1' ),
				'post_match'        => array(
					'slugs' => array( 'impressum' ),
				),
				'sync_post_content' => true,
				'fields'            => array(
					'headline' => array( 'label' => 'Seitenueberschrift', 'type' => 'text' ),
					'content'  => array( 'label' => 'Inhalt', 'type' => 'classic_editor' ),
				),
			),
			'datenschutz_page'  => array(
				'label'             => 'U-like-it Datenschutz',
				'description'       => 'Datenschutzerklaerung ueber Leadwerk Fields bearbeiten. Der Inhalt wird in das Seiten-HTML synchronisiert.',
				'source_keys'       => array( 'ulikeit-datenschutz-v1' ),
				'post_match'        => array(
					'slugs' => array( 'datenschutz' ),
				),
				'sync_post_content' => true,
				'fields'            => array(
					'headline' => array( 'label' => 'Seitenueberschrift', 'type' => 'text' ),
					'content'  => array( 'label' => 'Inhalt', 'type' => 'classic_editor' ),
				),
			),
		);

		return $groups;
	}

	/**
	 * Return one field group schema.
	 *
	 * @param string $field_name Field name.
	 * @return array<string,mixed>|null
	 */
	public static function get_group( $field_name ) {
		$groups = self::get_groups();
		return $groups[ $field_name ] ?? null;
	}

	/**
	 * Resolve a field group by source key.
	 *
	 * @param string $source_key Source key.
	 * @return array<string,mixed>|null
	 */
	public static function get_group_for_source_key( $source_key ) {
		foreach ( self::get_groups() as $field_name => $group ) {
			if ( in_array( $source_key, $group['source_keys'], true ) ) {
				$group['field_name'] = $field_name;
				return $group;
			}
		}

		return null;
	}

	/**
	 * Resolve a field group by post.
	 *
	 * @param int|WP_Post $post Post object or ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_group_for_post( $post ) {
		$post_id = is_object( $post ) ? (int) $post->ID : (int) $post;
		if ( ! $post_id ) {
			return null;
		}

		$source_key = (string) get_post_meta( $post_id, 'leadwerk_source_key', true );
		if ( '' !== $source_key ) {
			$group = self::get_group_for_source_key( $source_key );
			if ( $group ) {
				return $group;
			}
		}

		foreach ( self::get_groups() as $field_name => $group ) {
			if ( self::group_matches_post( $group, $post_id ) ) {
				$group['field_name'] = $field_name;
				return $group;
			}
		}

		return null;
	}

	/**
	 * Return the expected default post_content for one source key.
	 *
	 * @param string $source_key Source key.
	 * @return string
	 */
	public static function get_default_post_content_for_source_key( $source_key ) {
		$group = self::get_group_for_source_key( $source_key );
		if ( ! $group ) {
			return '';
		}

		return isset( $group['block_content'] ) ? (string) $group['block_content'] : '';
	}

	/**
	 * Return the expected default post_content for one field group.
	 *
	 * @param string $field_name Field group name.
	 * @return string
	 */
	public static function get_default_post_content_for_group( $field_name ) {
		$group = self::get_group( $field_name );
		if ( ! $group ) {
			return '';
		}

		return isset( $group['block_content'] ) ? (string) $group['block_content'] : '';
	}

	/**
	 * Check whether a schema group belongs to one post.
	 *
	 * @param array<string,mixed> $group   Group schema.
	 * @param int                 $post_id Post ID.
	 * @return bool
	 */
	private static function group_matches_post( $group, $post_id ) {
		$post_match = isset( $group['post_match'] ) && is_array( $group['post_match'] ) ? $group['post_match'] : array();
		$post_slug  = (string) get_post_field( 'post_name', $post_id );

		if ( ! empty( $post_match['slugs'] ) && is_array( $post_match['slugs'] ) ) {
			foreach ( $post_match['slugs'] as $slug ) {
				if ( (string) $slug === $post_slug ) {
					return true;
				}
			}
		}

		if ( ! empty( $post_match['is_front_page'] ) && (int) get_option( 'page_on_front' ) === (int) $post_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Resolve a layout schema.
	 *
	 * @param string $field_name Field group name.
	 * @param string $layout     Layout name.
	 * @return array<string,mixed>|null
	 */
	public static function get_layout( $field_name, $layout ) {
		$group = self::get_group( $field_name );
		if ( ! $group ) {
			return null;
		}

		return $group['layouts'][ $layout ] ?? null;
	}

	/**
	 * Default value for a field definition.
	 *
	 * @param array<string,mixed> $definition Field definition.
	 * @return mixed
	 */
	public static function get_default_value( $definition ) {
		$type = $definition['type'] ?? 'text';

		switch ( $type ) {
			case 'checkbox':
				return false;
			case 'image':
				return 0;
			case 'repeater':
			case 'select_options':
				return array();
			default:
				return '';
		}
	}
}
