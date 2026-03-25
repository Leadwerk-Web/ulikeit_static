<?php
/**
 * Leadwerk Fields – Classic Editor Metabox UI
 * Provides editable metaboxes for home_sections (Flexible Content)
 * and Options fields in the WordPress admin.
 *
 * @package Leadwerk_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leadwerk_Fields_Metabox {

	/** @var array Field schema for the home sections. */
	private static $home_section_layouts = array(
		'hero' => array(
			'label'  => 'Hero',
			'fields' => array(
				'title_gradient'   => array( 'label' => 'Titel (Gradient)', 'type' => 'text' ),
				'typewriter_words' => array( 'label' => 'Typewriter-Wörter (|)', 'type' => 'text' ),
				'cta_text'         => array( 'label' => 'CTA Button Text', 'type' => 'text' ),
				'cta_url'          => array( 'label' => 'CTA Button URL', 'type' => 'url' ),
				'hero_image'       => array( 'label' => 'Hero-Bild', 'type' => 'image' ),
			),
		),
		'why' => array(
			'label'  => 'Why / Warum',
			'fields' => array(
				'label' => array( 'label' => 'Label', 'type' => 'text' ),
				'title' => array( 'label' => 'Titel', 'type' => 'text' ),
				'bag_image' => array( 'label' => 'Bag-Bild', 'type' => 'image' ),
			),
		),
		'app_steps' => array(
			'label'  => 'App Steps',
			'fields' => array(
				'headline' => array( 'label' => 'Überschrift', 'type' => 'text' ),
			),
		),
		'pakete' => array(
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
			'label'  => 'Unsere Lösungen',
			'fields' => array(
				'label'             => array( 'label' => 'Label', 'type' => 'text' ),
				'title'             => array( 'label' => 'Titel', 'type' => 'text' ),
				'intro_text'        => array( 'label' => 'Intro-Text', 'type' => 'wysiwyg' ),
				'register_btn_text' => array( 'label' => 'Button Text', 'type' => 'text' ),
				'register_btn_url'  => array( 'label' => 'Button URL', 'type' => 'url' ),
			),
		),
		'faq' => array(
			'label'  => 'FAQ',
			'fields' => array(
				'label' => array( 'label' => 'Label', 'type' => 'text' ),
				'title' => array( 'label' => 'Titel', 'type' => 'text' ),
				'image' => array( 'label' => 'FAQ-Bild', 'type' => 'image' ),
			),
		),
		'cta' => array(
			'label'  => 'Final CTA',
			'fields' => array(
				'title'         => array( 'label' => 'Titel', 'type' => 'textarea' ),
				'button_1_text' => array( 'label' => 'Button 1 Text', 'type' => 'text' ),
				'button_1_url'  => array( 'label' => 'Button 1 URL', 'type' => 'url' ),
				'button_2_text' => array( 'label' => 'Button 2 Text', 'type' => 'text' ),
				'button_2_url'  => array( 'label' => 'Button 2 URL', 'type' => 'url' ),
			),
		),
	);

	/** @var array Options fields. */
	private static $options_fields = array(
		'logo'              => array( 'label' => 'Logo (Header)', 'type' => 'image' ),
		'footer_logo'       => array( 'label' => 'Footer-Logo', 'type' => 'image' ),
		'footer_text'       => array( 'label' => 'Footer-Beschreibung', 'type' => 'textarea' ),
		'copyright_text'    => array( 'label' => 'Copyright-Text', 'type' => 'text' ),
		'app_store_url'     => array( 'label' => 'App Store URL', 'type' => 'url' ),
		'google_play_url'   => array( 'label' => 'Google Play URL', 'type' => 'url' ),
		'app_store_badge'   => array( 'label' => 'App Store Badge', 'type' => 'image' ),
		'google_play_badge' => array( 'label' => 'Google Play Badge', 'type' => 'image' ),
	);

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_metaboxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_home_sections' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'register_options_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/* ══════════════════════════════════════════════════════════════════
	 * Admin Assets (media uploader)
	 * ══════════════════════════════════════════════════════════════════ */

	public static function enqueue_admin_assets( $hook ) {
		$screens = array( 'post.php', 'post-new.php', 'toplevel_page_leadwerk-options' );
		$found = false;
		foreach ( $screens as $s ) {
			if ( strpos( $hook, $s ) !== false || $hook === $s ) {
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			return;
		}
		wp_enqueue_media();
		wp_add_inline_script( 'media-editor', self::get_inline_js() );
		wp_add_inline_style( 'wp-admin', self::get_inline_css() );
	}

	/* ══════════════════════════════════════════════════════════════════
	 * Home Sections Metabox (Front Page)
	 * ══════════════════════════════════════════════════════════════════ */

	public static function register_metaboxes() {
		add_meta_box(
			'leadwerk_home_sections',
			__( 'U-like-it Startseiten-Sektionen', 'leadwerk-fields' ),
			array( __CLASS__, 'render_home_sections_metabox' ),
			'page',
			'normal',
			'high'
		);
	}

	public static function render_home_sections_metabox( $post ) {
		wp_nonce_field( 'leadwerk_save_sections', 'leadwerk_sections_nonce' );
		$sections = Leadwerk_Fields_API::get_field( 'home_sections', $post->ID );
		if ( ! is_array( $sections ) ) {
			$sections = array();
		}

		echo '<div class="leadwerk-metabox">';
		echo '<p class="description">' . esc_html__( 'Sektionen der Startseite bearbeiten. Reihenfolge und Anzahl bleiben erhalten.', 'leadwerk-fields' ) . '</p>';

		if ( empty( $sections ) ) {
			echo '<p><em>Keine Sektionen vorhanden. Bitte zuerst den Leadwerk-Import ausführen.</em></p>';
			echo '</div>';
			return;
		}

		foreach ( $sections as $idx => $section ) {
			$layout = isset( $section['acf_fc_layout'] ) ? $section['acf_fc_layout'] : 'unknown';
			$schema = isset( self::$home_section_layouts[ $layout ] ) ? self::$home_section_layouts[ $layout ] : null;
			$label  = $schema ? $schema['label'] : ucfirst( $layout );

			echo '<div class="leadwerk-section-box">';
			echo '<h3 class="leadwerk-section-title">';
			echo '<span class="leadwerk-section-number">' . ( $idx + 1 ) . '</span> ';
			echo esc_html( $label ) . ' <code>[' . esc_html( $layout ) . ']</code>';
			echo '</h3>';
			echo '<input type="hidden" name="leadwerk_sections[' . $idx . '][acf_fc_layout]" value="' . esc_attr( $layout ) . '">';

			echo '<div class="leadwerk-section-fields">';
			if ( $schema ) {
				foreach ( $schema['fields'] as $field_key => $field_def ) {
					$value = isset( $section[ $field_key ] ) ? $section[ $field_key ] : '';
					self::render_field( "leadwerk_sections[{$idx}][{$field_key}]", $field_def, $value );
				}
			}
			// Render sub-items (cards, steps, faq items) as JSON textarea
			$complex_keys = array( 'cards', 'steps', 'items' );
			foreach ( $complex_keys as $ck ) {
				if ( isset( $section[ $ck ] ) && is_array( $section[ $ck ] ) ) {
					echo '<div class="leadwerk-field">';
					echo '<label>' . esc_html( ucfirst( $ck ) ) . ' (JSON)</label>';
					echo '<textarea name="leadwerk_sections[' . $idx . '][' . $ck . ']" rows="6" class="large-text code">';
					echo esc_textarea( wp_json_encode( $section[ $ck ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
					echo '</textarea>';
					echo '<p class="description">JSON-Format. Vorsicht beim Bearbeiten.</p>';
					echo '</div>';
				}
			}
			echo '</div></div>';
		}
		echo '</div>';
	}

	public static function save_home_sections( $post_id, $post ) {
		if ( ! isset( $_POST['leadwerk_sections_nonce'] ) || ! wp_verify_nonce( $_POST['leadwerk_sections_nonce'], 'leadwerk_save_sections' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['leadwerk_sections'] ) || ! is_array( $_POST['leadwerk_sections'] ) ) {
			return;
		}

		$raw_sections = $_POST['leadwerk_sections'];
		$sections = array();
		foreach ( $raw_sections as $idx => $raw ) {
			$section = array();
			$layout  = isset( $raw['acf_fc_layout'] ) ? sanitize_text_field( $raw['acf_fc_layout'] ) : '';
			$section['acf_fc_layout'] = $layout;

			$schema = isset( self::$home_section_layouts[ $layout ] ) ? self::$home_section_layouts[ $layout ] : null;
			if ( $schema ) {
				foreach ( $schema['fields'] as $field_key => $field_def ) {
					if ( isset( $raw[ $field_key ] ) ) {
						$section[ $field_key ] = self::sanitize_value( $raw[ $field_key ], $field_def['type'] );
					}
				}
			}

			// Complex sub-items (cards, steps, items)
			$complex_keys = array( 'cards', 'steps', 'items' );
			foreach ( $complex_keys as $ck ) {
				if ( isset( $raw[ $ck ] ) ) {
					$decoded = json_decode( wp_unslash( $raw[ $ck ] ), true );
					if ( is_array( $decoded ) ) {
						$section[ $ck ] = $decoded;
					}
				}
			}

			$sections[] = $section;
		}

		Leadwerk_Fields_API::update_field( 'home_sections', $sections, $post_id );
	}

	/* ══════════════════════════════════════════════════════════════════
	 * Options Page
	 * ══════════════════════════════════════════════════════════════════ */

	public static function register_options_page() {
		add_menu_page(
			__( 'Leadwerk Optionen', 'leadwerk-fields' ),
			__( 'Leadwerk Optionen', 'leadwerk-fields' ),
			'manage_options',
			'leadwerk-options',
			array( __CLASS__, 'render_options_page' ),
			'dashicons-store',
			80
		);
	}

	public static function render_options_page() {
		if ( isset( $_POST['leadwerk_options_nonce'] ) && wp_verify_nonce( $_POST['leadwerk_options_nonce'], 'leadwerk_save_options' ) ) {
			self::save_options();
			echo '<div class="notice notice-success"><p>Optionen gespeichert.</p></div>';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Leadwerk Optionen', 'leadwerk-fields' ); ?></h1>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'leadwerk_save_options', 'leadwerk_options_nonce' ); ?>
				<table class="form-table leadwerk-options-table">
					<?php foreach ( self::$options_fields as $key => $def ) : ?>
					<tr>
						<th scope="row"><label for="leadwerk_opt_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def['label'] ); ?></label></th>
						<td><?php
							$value = Leadwerk_Fields_API::get_field( $key, 'option' );
							self::render_field( 'leadwerk_opt_' . $key, $def, $value, 'leadwerk_opt_' . $key );
						?></td>
					</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button( __( 'Optionen speichern', 'leadwerk-fields' ) ); ?>
			</form>
		</div>
		<?php
	}

	private static function save_options() {
		foreach ( self::$options_fields as $key => $def ) {
			$form_key = 'leadwerk_opt_' . $key;
			if ( isset( $_POST[ $form_key ] ) ) {
				Leadwerk_Fields_API::update_field( $key, self::sanitize_value( $_POST[ $form_key ], $def['type'] ), 'option' );
			}
		}
	}

	/* ══════════════════════════════════════════════════════════════════
	 * Field Renderer
	 * ══════════════════════════════════════════════════════════════════ */

	private static function render_field( $name, $def, $value, $id = '' ) {
		$type  = $def['type'];
		$label = $def['label'];
		$id    = $id ?: sanitize_title( $name );
		echo '<div class="leadwerk-field">';
		echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		switch ( $type ) {
			case 'text':
			case 'url':
				$input_type = $type === 'url' ? 'url' : 'text';
				echo '<input type="' . $input_type . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" class="regular-text">';
				break;
			case 'textarea':
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3" class="large-text">' . esc_textarea( (string) $value ) . '</textarea>';
				break;
			case 'wysiwyg':
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="5" class="large-text">' . esc_textarea( (string) $value ) . '</textarea>';
				break;
			case 'image':
				$img_id  = is_numeric( $value ) ? (int) $value : 0;
				$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
				echo '<div class="leadwerk-image-field" data-target="' . esc_attr( $id ) . '">';
				echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $img_id ) . '">';
				echo '<div class="leadwerk-image-preview">';
				if ( $img_url ) {
					echo '<img src="' . esc_url( $img_url ) . '" alt="" style="max-width:150px;height:auto;">';
				}
				echo '</div>';
				echo '<button type="button" class="button leadwerk-image-select">' . __( 'Bild wählen', 'leadwerk-fields' ) . '</button> ';
				echo '<button type="button" class="button leadwerk-image-remove">' . __( 'Entfernen', 'leadwerk-fields' ) . '</button>';
				echo '</div>';
				break;
		}
		echo '</div>';
	}

	private static function sanitize_value( $value, $type ) {
		switch ( $type ) {
			case 'text':
				return sanitize_text_field( wp_unslash( $value ) );
			case 'url':
				return esc_url_raw( wp_unslash( $value ) );
			case 'textarea':
				return sanitize_textarea_field( wp_unslash( $value ) );
			case 'wysiwyg':
				return wp_kses_post( wp_unslash( $value ) );
			case 'image':
				return absint( $value );
			default:
				return sanitize_text_field( wp_unslash( $value ) );
		}
	}

	/* ══════════════════════════════════════════════════════════════════
	 * Inline CSS / JS
	 * ══════════════════════════════════════════════════════════════════ */

	private static function get_inline_css() {
		return '
.leadwerk-metabox { max-width: 100%; }
.leadwerk-section-box {
	background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px; margin: 12px 0; padding: 0;
}
.leadwerk-section-title {
	margin: 0; padding: 12px 16px; background: #e9e9e9; border-bottom: 1px solid #ddd;
	font-size: 14px; font-weight: 600; cursor: pointer; border-radius: 5px 5px 0 0;
}
.leadwerk-section-title:hover { background: #ddd; }
.leadwerk-section-number {
	display: inline-flex; align-items: center; justify-content: center;
	width: 22px; height: 22px; background: #0073aa; color: #fff; border-radius: 50%;
	font-size: 12px; margin-right: 6px;
}
.leadwerk-section-fields { padding: 12px 16px; }
.leadwerk-field { margin-bottom: 12px; }
.leadwerk-field > label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px; }
.leadwerk-image-preview { margin: 6px 0; }
.leadwerk-image-preview img { border: 1px solid #ddd; border-radius: 4px; }
.leadwerk-options-table .leadwerk-field { margin: 0; }
.leadwerk-options-table .leadwerk-field > label { display: none; }
';
	}

	private static function get_inline_js() {
		return "
jQuery(function($){
	$(document).on('click','.leadwerk-image-select',function(e){
		e.preventDefault();
		var wrap = $(this).closest('.leadwerk-image-field');
		var targetId = wrap.data('target');
		var frame = wp.media({title:'Bild wählen',button:{text:'Auswählen'},multiple:false});
		frame.on('select',function(){
			var att = frame.state().get('selection').first().toJSON();
			wrap.find('input[type=hidden]').val(att.id);
			var thumb = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
			wrap.find('.leadwerk-image-preview').html('<img src=\"'+thumb+'\" alt=\"\" style=\"max-width:150px;height:auto;\">');
		});
		frame.open();
	});
	$(document).on('click','.leadwerk-image-remove',function(e){
		e.preventDefault();
		var wrap = $(this).closest('.leadwerk-image-field');
		wrap.find('input[type=hidden]').val('0');
		wrap.find('.leadwerk-image-preview').html('');
	});
	$(document).on('click','.leadwerk-section-title',function(){
		$(this).next('.leadwerk-section-fields').slideToggle(200);
	});
});
";
	}
}
