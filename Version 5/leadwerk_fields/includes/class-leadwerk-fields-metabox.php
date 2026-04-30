<?php
/**
 * Leadwerk Fields metabox UI.
 *
 * @package Leadwerk_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leadwerk_Fields_Metabox {

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
		'haendler_wpforms_id' => array(
			'label'       => 'Händler WPForms ID',
			'type'        => 'text',
			'description' => 'ID oder Shortcode eingeben, z. B. 1420 oder [wpforms id="1420"].',
		),
	);

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_metaboxes' ), 10, 2 );
		add_action( 'save_post_page', array( __CLASS__, 'save_sections' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'register_options_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_filter( 'use_block_editor_for_post', array( __CLASS__, 'maybe_disable_block_editor' ), 10, 2 );
	}

	public static function maybe_disable_block_editor( $use_block_editor, $post ) {
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return $use_block_editor;
		}

		$group = Leadwerk_Content_Schema::get_group_for_post( $post );
		if ( $group ) {
			return false;
		}

		return $use_block_editor;
	}

	public static function enqueue_admin_assets( $hook ) {
		$screens = array( 'post.php', 'post-new.php', 'toplevel_page_leadwerk-options' );
		$found   = false;

		foreach ( $screens as $screen ) {
			if ( false !== strpos( $hook, $screen ) || $hook === $screen ) {
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

	public static function register_metaboxes( $post_type, $post ) {
		if ( 'page' !== $post_type || ! $post ) {
			return;
		}

		$group = Leadwerk_Content_Schema::get_group_for_post( $post );
		if ( ! $group ) {
			return;
		}

		add_meta_box(
			'leadwerk_page_sections',
			esc_html( $group['label'] ),
			array( __CLASS__, 'render_sections_metabox' ),
			'page',
			'normal',
			'high'
		);
	}

	public static function render_sections_metabox( $post ) {
		$group = Leadwerk_Content_Schema::get_group_for_post( $post );
		if ( ! $group ) {
			return;
		}

		$field_name = $group['field_name'];

		wp_nonce_field( 'leadwerk_save_sections', 'leadwerk_sections_nonce' );

		echo '<input type="hidden" name="leadwerk_sections_field_name" value="' . esc_attr( $field_name ) . '">';
		echo '<div class="leadwerk-metabox">';
		echo '<p class="description">' . esc_html( $group['description'] ) . '</p>';

		if ( empty( $group['layouts'] ) ) {
			$values = Leadwerk_Fields_API::get_field( $field_name, $post->ID );
			$values = is_array( $values ) ? $values : array();

			echo '<div class="leadwerk-section-box">';
			echo '<div class="leadwerk-section-fields" style="display:block;">';

			foreach ( $group['fields'] as $field_key => $definition ) {
				$value = $values[ $field_key ] ?? Leadwerk_Content_Schema::get_default_value( $definition );
				self::render_field( "leadwerk_group[{$field_key}]", $definition, $value );
			}

			echo '</div>';
			echo '</div>';
			echo '</div>';
			return;
		}

		$sections = Leadwerk_Fields_API::get_field( $field_name, $post->ID );
		$sections = is_array( $sections ) ? array_values( $sections ) : array();

		if ( empty( $sections ) ) {
			echo '<p><em>Keine Sektionen vorhanden. Bitte zuerst den Leadwerk-Import ausfuehren.</em></p>';
			echo '</div>';
			return;
		}

		foreach ( $sections as $index => $section ) {
			$layout = isset( $section['acf_fc_layout'] ) ? sanitize_key( $section['acf_fc_layout'] ) : '';
			$schema = Leadwerk_Content_Schema::get_layout( $field_name, $layout );
			$label  = $schema['label'] ?? ucfirst( $layout );

			echo '<div class="leadwerk-section-box">';
			echo '<h3 class="leadwerk-section-title">';
			echo '<span class="leadwerk-section-number">' . (int) ( $index + 1 ) . '</span> ';
			echo esc_html( $label ) . ' <code>[' . esc_html( $layout ) . ']</code>';
			echo '</h3>';
			echo '<div class="leadwerk-section-fields">';
			echo '<input type="hidden" name="leadwerk_sections[' . esc_attr( (string) $index ) . '][acf_fc_layout]" value="' . esc_attr( $layout ) . '">';

			if ( $schema ) {
				foreach ( $schema['fields'] as $field_key => $definition ) {
					$value = $section[ $field_key ] ?? Leadwerk_Content_Schema::get_default_value( $definition );
					self::render_field( "leadwerk_sections[{$index}][{$field_key}]", $definition, $value );
				}
			}

			echo '</div>';
			echo '</div>';
		}

		echo '</div>';
	}

	public static function save_sections( $post_id, $post ) {
		if ( ! isset( $_POST['leadwerk_sections_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['leadwerk_sections_nonce'] ), 'leadwerk_save_sections' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$field_name = isset( $_POST['leadwerk_sections_field_name'] ) ? sanitize_key( wp_unslash( $_POST['leadwerk_sections_field_name'] ) ) : '';
		$group      = Leadwerk_Content_Schema::get_group( $field_name );
		if ( ! $group ) {
			return;
		}

		if ( empty( $group['layouts'] ) ) {
			$raw = $_POST['leadwerk_group'] ?? null;
			if ( ! is_array( $raw ) ) {
				return;
			}

			$values = array();
			foreach ( $group['fields'] as $field_key => $definition ) {
				$values[ $field_key ] = self::sanitize_field_value( $raw[ $field_key ] ?? null, $definition );
			}

			Leadwerk_Fields_API::update_field( $field_name, $values, $post_id );
			self::sync_post_content_if_needed( $post_id, $group, $values );
			self::sync_last_good_snapshot( $post_id, $field_name, $group, $values );
			return;
		}

		$raw = $_POST['leadwerk_sections'] ?? null;

		if ( ! is_array( $raw ) ) {
			return;
		}

		$sections = array();

		foreach ( $raw as $section_raw ) {
			if ( ! is_array( $section_raw ) ) {
				continue;
			}

			$layout = isset( $section_raw['acf_fc_layout'] ) ? sanitize_key( wp_unslash( $section_raw['acf_fc_layout'] ) ) : '';
			$schema = Leadwerk_Content_Schema::get_layout( $field_name, $layout );

			if ( ! $schema ) {
				continue;
			}

			$section                  = array();
			$section['acf_fc_layout'] = $layout;

			foreach ( $schema['fields'] as $field_key => $definition ) {
				$section[ $field_key ] = self::sanitize_field_value( $section_raw[ $field_key ] ?? null, $definition );
			}

			$sections[] = $section;
		}

		Leadwerk_Fields_API::update_field( $field_name, $sections, $post_id );
		self::sync_post_content_if_needed( $post_id, $group, $sections );
		self::sync_last_good_snapshot( $post_id, $field_name, $group, $sections );
	}

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
		if ( isset( $_POST['leadwerk_options_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['leadwerk_options_nonce'] ), 'leadwerk_save_options' ) ) {
			self::save_options();
			echo '<div class="notice notice-success"><p>Optionen gespeichert.</p></div>';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Leadwerk Optionen', 'leadwerk-fields' ); ?></h1>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'leadwerk_save_options', 'leadwerk_options_nonce' ); ?>
				<table class="form-table leadwerk-options-table">
					<?php foreach ( self::$options_fields as $key => $definition ) : ?>
					<tr>
						<th scope="row"><label for="leadwerk_opt_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $definition['label'] ); ?></label></th>
						<td><?php
						$value = Leadwerk_Fields_API::get_field( $key, 'option' );
						self::render_field( 'leadwerk_opt_' . $key, $definition, $value, 'leadwerk_opt_' . $key );
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
		foreach ( self::$options_fields as $key => $definition ) {
			$form_key = 'leadwerk_opt_' . $key;
			if ( array_key_exists( $form_key, $_POST ) ) {
				Leadwerk_Fields_API::update_field( $key, self::sanitize_option_value( $key, $_POST[ $form_key ], $definition ), 'option' );
			}
		}
	}

	private static function render_field( $name, $definition, $value, $id = '' ) {
		$type  = $definition['type'] ?? 'text';
		$label = $definition['label'] ?? $name;
		$id    = $id ?: sanitize_title( $name );

		echo '<div class="leadwerk-field leadwerk-field-' . esc_attr( $type ) . '">';

		if ( 'checkbox' !== $type ) {
			echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		}

		switch ( $type ) {
			case 'text':
			case 'url':
				$input_type = 'url' === $type ? 'url' : 'text';
				echo '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" class="regular-text">';
				break;

			case 'textarea':
			case 'wysiwyg':
			case 'svg_code':
				$rows = 'svg_code' === $type ? 8 : 4;
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="' . esc_attr( (string) $rows ) . '" class="large-text' . ( 'svg_code' === $type ? ' code' : '' ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
				break;

			case 'classic_editor':
				wp_editor(
					(string) $value,
					$id,
					array(
						'textarea_name' => $name,
						'textarea_rows' => 18,
						'media_buttons' => false,
						'teeny'         => false,
						'quicktags'     => true,
						'tinymce'       => true,
					)
				);
				break;

			case 'select_options':
				$text_value = '';
				if ( is_array( $value ) ) {
					$text_value = implode( "\n", array_map( 'strval', $value ) );
				}
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="5" class="large-text code">' . esc_textarea( $text_value ) . '</textarea>';
				echo '<p class="description">Eine Option pro Zeile.</p>';
				break;

			case 'checkbox':
				echo '<label class="leadwerk-checkbox">';
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
				echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $value ), true, false ) . '>';
				echo '<span>' . esc_html( $label ) . '</span>';
				echo '</label>';
				break;

			case 'image':
				$img_id  = is_numeric( $value ) ? (int) $value : 0;
				$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
				echo '<div class="leadwerk-image-field" data-target="' . esc_attr( $id ) . '">';
				echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $img_id ) . '">';
				echo '<div class="leadwerk-image-preview">';
				if ( $img_url ) {
					echo '<img src="' . esc_url( $img_url ) . '" alt="" style="max-width:150px;height:auto;">';
				}
				echo '</div>';
				echo '<button type="button" class="button leadwerk-image-select">Bild waehlen</button> ';
				echo '<button type="button" class="button leadwerk-image-remove">Entfernen</button>';
				echo '</div>';
				break;

			case 'repeater':
				self::render_repeater_field( $name, $definition, $value, $id );
				break;
		}

		if ( ! empty( $definition['description'] ) ) {
			echo '<p class="description">' . esc_html( $definition['description'] ) . '</p>';
		}

		echo '</div>';
	}

	private static function sanitize_option_value( $key, $value, $definition ) {
		if ( 'haendler_wpforms_id' === $key ) {
			return self::normalize_wpforms_option_value( $value );
		}

		return self::sanitize_field_value( $value, $definition );
	}

	private static function normalize_wpforms_option_value( $value ) {
		$value = trim( (string) wp_unslash( is_null( $value ) ? '' : $value ) );
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/\bid\s*=\s*(["\']?)(\d+)\1/i', $value, $matches ) ) {
			return $matches[2];
		}

		if ( preg_match( '/^\d+$/', $value ) ) {
			return $value;
		}

		if ( preg_match( '/\d+/', $value, $matches ) ) {
			return $matches[0];
		}

		return sanitize_text_field( $value );
	}

	private static function render_repeater_field( $name, $definition, $value, $id ) {
		$items = is_array( $value ) ? array_values( $value ) : array();

		echo '<div class="leadwerk-repeater" id="' . esc_attr( $id ) . '" data-next-index="' . esc_attr( (string) count( $items ) ) . '">';
		echo '<div class="leadwerk-repeater-items">';

		foreach ( $items as $index => $item ) {
			echo self::get_repeater_item_markup( $name, $definition, (int) $index, is_array( $item ) ? $item : array() );
		}

		echo '</div>';
		echo '<button type="button" class="button leadwerk-repeater-add">' . esc_html( $definition['add_button_label'] ?? 'Eintrag hinzufuegen' ) . '</button>';
		echo '<template class="leadwerk-repeater-template">' . self::get_repeater_item_markup( $name, $definition, '__INDEX__', array() ) . '</template>';
		echo '</div>';
	}

	private static function get_repeater_item_markup( $name, $definition, $index, $item ) {
		ob_start();
		?>
		<div class="leadwerk-repeater-item">
			<div class="leadwerk-repeater-item-header">
				<strong class="leadwerk-repeater-item-title">Eintrag</strong>
				<div class="leadwerk-repeater-item-actions">
					<button type="button" class="button button-small leadwerk-repeater-move-up">Nach oben</button>
					<button type="button" class="button button-small leadwerk-repeater-move-down">Nach unten</button>
					<button type="button" class="button button-small leadwerk-repeater-remove">Entfernen</button>
				</div>
			</div>
			<div class="leadwerk-repeater-item-fields">
				<?php
				foreach ( $definition['fields'] as $sub_key => $sub_definition ) {
					$sub_value = $item[ $sub_key ] ?? Leadwerk_Content_Schema::get_default_value( $sub_definition );
					self::render_field( $name . '[' . $index . '][' . $sub_key . ']', $sub_definition, $sub_value );
				}
				?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	private static function sanitize_field_value( $value, $definition ) {
		$type = $definition['type'] ?? 'text';

		switch ( $type ) {
			case 'text':
				return sanitize_text_field( is_null( $value ) ? '' : wp_unslash( $value ) );

			case 'url':
				return esc_url_raw( is_null( $value ) ? '' : wp_unslash( $value ) );

			case 'textarea':
				return sanitize_textarea_field( is_null( $value ) ? '' : wp_unslash( $value ) );

			case 'wysiwyg':
			case 'classic_editor':
				return wp_kses_post( is_null( $value ) ? '' : wp_unslash( $value ) );

			case 'svg_code':
				return trim( (string) wp_unslash( is_null( $value ) ? '' : $value ) );

			case 'image':
				return absint( $value );

			case 'checkbox':
				return ! empty( $value );

			case 'select_options':
				$raw = is_null( $value ) ? '' : wp_unslash( $value );
				$raw = is_array( $raw ) ? $raw : preg_split( '/\r\n|\r|\n/', (string) $raw );
				$raw = is_array( $raw ) ? $raw : array();
				$out = array();

				foreach ( $raw as $line ) {
					$line = sanitize_text_field( (string) $line );
					if ( '' !== $line ) {
						$out[] = $line;
					}
				}

				return $out;

			case 'repeater':
				$rows = is_array( $value ) ? $value : array();
				$out  = array();

				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}

					$item = array();
					foreach ( $definition['fields'] as $sub_key => $sub_definition ) {
						$item[ $sub_key ] = self::sanitize_field_value( $row[ $sub_key ] ?? null, $sub_definition );
					}
					$out[] = $item;
				}

				return $out;
		}

		return sanitize_text_field( is_null( $value ) ? '' : wp_unslash( $value ) );
	}

	private static function sync_post_content_if_needed( $post_id, $group, $value ) {
		$post_content = '';

		if ( ! empty( $group['sync_post_content'] ) && is_array( $value ) ) {
			$post_content = self::build_legal_page_content( $value );
		} elseif ( ! empty( $group['block_content'] ) ) {
			$post_content = (string) $group['block_content'];
		}

		if ( '' === $post_content ) {
			return;
		}

		$current_post_content = (string) get_post_field( 'post_content', $post_id );
		if ( trim( $current_post_content ) === trim( $post_content ) ) {
			return;
		}

		remove_action( 'save_post_page', array( __CLASS__, 'save_sections' ), 10 );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $post_content,
			)
		);
		add_action( 'save_post_page', array( __CLASS__, 'save_sections' ), 10, 2 );
	}

	private static function sync_last_good_snapshot( $post_id, $field_name, $group, $value ) {
		$meta_key = '_leadwerk_last_good_' . sanitize_key( (string) $field_name );

		if ( self::group_has_visible_content( $group, $value ) ) {
			update_post_meta(
				$post_id,
				$meta_key,
				array(
					'value'      => $value,
					'source'     => 'manual_save',
					'saved_at'   => current_time( 'mysql', true ),
					'field_name' => $field_name,
				)
			);
			return;
		}

		delete_post_meta( $post_id, $meta_key );
	}

	private static function group_has_visible_content( $group, $value ) {
		if ( empty( $group['layouts'] ) ) {
			$headline = is_array( $value ) ? (string) ( $value['headline'] ?? '' ) : '';
			$content  = is_array( $value ) ? (string) ( $value['content'] ?? '' ) : '';
			return '' !== trim( wp_strip_all_tags( $headline . ' ' . $content ) );
		}

		if ( ! is_array( $value ) || empty( $value ) ) {
			return false;
		}

		foreach ( array_values( $value ) as $section ) {
			if ( is_array( $section ) && self::section_has_visible_content( $section ) ) {
				return true;
			}
		}

		return false;
	}

	private static function section_has_visible_content( $value ) {
		foreach ( (array) $value as $field_key => $field_value ) {
			if ( 'acf_fc_layout' === (string) $field_key ) {
				continue;
			}

			if ( is_array( $field_value ) ) {
				if ( self::section_has_visible_content( $field_value ) ) {
					return true;
				}
				continue;
			}

			if ( is_bool( $field_value ) ) {
				if ( $field_value ) {
					return true;
				}
				continue;
			}

			if ( is_numeric( $field_value ) ) {
				if ( (int) $field_value > 0 ) {
					return true;
				}
				continue;
			}

			if ( '' !== trim( wp_strip_all_tags( (string) $field_value ) ) ) {
				return true;
			}
		}

		return false;
	}

	private static function build_legal_page_content( $value ) {
		$headline = trim( (string) ( $value['headline'] ?? '' ) );
		$content  = (string) ( $value['content'] ?? '' );

		return sprintf(
			'<section class="section legal-section"><div class="container legal-container"><h1 class="section-title legal-title">%1$s</h1><div class="legal-content">%2$s</div></div></section>',
			esc_html( $headline ),
			wp_kses_post( $content )
		);
	}

	private static function get_inline_css() {
		return '
.leadwerk-metabox { max-width: 100%; }
.leadwerk-section-box { background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px; margin: 12px 0; overflow: hidden; }
.leadwerk-section-title { margin: 0; padding: 12px 16px; background: #e9e9e9; border-bottom: 1px solid #ddd; font-size: 14px; font-weight: 600; cursor: pointer; }
.leadwerk-section-title:hover { background: #ddd; }
.leadwerk-section-number { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; background: #0073aa; color: #fff; border-radius: 50%; font-size: 12px; margin-right: 6px; }
.leadwerk-section-fields { padding: 12px 16px; }
.leadwerk-field { margin-bottom: 14px; }
.leadwerk-field > label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px; }
.leadwerk-checkbox { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; }
.leadwerk-image-preview { margin: 6px 0; }
.leadwerk-image-preview img { border: 1px solid #ddd; border-radius: 4px; }
.leadwerk-repeater { border: 1px solid #d0d7de; background: #fff; border-radius: 6px; padding: 10px; }
.leadwerk-repeater-items { display: grid; gap: 12px; margin-bottom: 10px; }
.leadwerk-repeater-item { border: 1px solid #d0d7de; border-radius: 6px; background: #fafafa; }
.leadwerk-repeater-item-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 12px; border-bottom: 1px solid #e5e7eb; background: #f3f4f6; }
.leadwerk-repeater-item-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.leadwerk-repeater-item-fields { padding: 12px; }
.leadwerk-field-classic_editor .wp-editor-wrap { max-width: 100%; }
.leadwerk-field-classic_editor .wp-editor-area { min-height: 320px; }
.leadwerk-options-table .leadwerk-field { margin: 0; }
.leadwerk-options-table .leadwerk-field > label { display: none; }
';
	}

	private static function get_inline_js() {
		return "
jQuery(function($){
	function updateRepeaterTitles(container){
		container.find('.leadwerk-repeater-item').each(function(index){
			$(this).find('.leadwerk-repeater-item-title').text('Eintrag ' + (index + 1));
		});
	}

	$(document).on('click','.leadwerk-image-select',function(e){
		e.preventDefault();
		var wrap = $(this).closest('.leadwerk-image-field');
		var frame = wp.media({title:'Bild waehlen',button:{text:'Auswaehlen'},multiple:false});
		frame.on('select',function(){
			var att = frame.state().get('selection').first().toJSON();
			wrap.find('input[type=hidden]').val(att.id);
			var thumb = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
			wrap.find('.leadwerk-image-preview').html('<img src=\"' + thumb + '\" alt=\"\" style=\"max-width:150px;height:auto;\">');
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

	$(document).on('click','.leadwerk-repeater-add',function(e){
		e.preventDefault();
		var repeater = $(this).closest('.leadwerk-repeater');
		var nextIndex = parseInt(repeater.attr('data-next-index'), 10) || 0;
		var template = repeater.find('.leadwerk-repeater-template').html() || '';
		repeater.attr('data-next-index', nextIndex + 1);
		template = template.replace(/__INDEX__/g, nextIndex);
		repeater.find('.leadwerk-repeater-items').append(template);
		updateRepeaterTitles(repeater);
	});

	$(document).on('click','.leadwerk-repeater-remove',function(e){
		e.preventDefault();
		var repeater = $(this).closest('.leadwerk-repeater');
		$(this).closest('.leadwerk-repeater-item').remove();
		updateRepeaterTitles(repeater);
	});

	$(document).on('click','.leadwerk-repeater-move-up',function(e){
		e.preventDefault();
		var item = $(this).closest('.leadwerk-repeater-item');
		var prev = item.prev('.leadwerk-repeater-item');
		if (prev.length) {
			item.insertBefore(prev);
			updateRepeaterTitles(item.closest('.leadwerk-repeater'));
		}
	});

	$(document).on('click','.leadwerk-repeater-move-down',function(e){
		e.preventDefault();
		var item = $(this).closest('.leadwerk-repeater-item');
		var next = item.next('.leadwerk-repeater-item');
		if (next.length) {
			item.insertAfter(next);
			updateRepeaterTitles(item.closest('.leadwerk-repeater'));
		}
	});

	$('.leadwerk-repeater').each(function(){
		updateRepeaterTitles($(this));
	});
});
";
	}
}
