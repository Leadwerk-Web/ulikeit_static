<?php
/**
 * Leadwerk Theme – U-like-it
 * Minimale PHP-Integration: Asset-Enqueue, Block für Startseiten-Sektionen.
 *
 * @package Leadwerk_Theme
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LEADWERK_THEME_VERSION', '1.0.4' );
define( 'LEADWERK_THEME_DIR', get_template_directory() );
define( 'LEADWERK_THEME_URI', get_template_directory_uri() );

/* ──────────────────────────────────────────────────────────────────────
 * 1. Assets
 * ────────────────────────────────────────────────────────────────────── */

function leadwerk_theme_enqueue_assets() {
	wp_enqueue_style(
		'leadwerk-theme-style',
		get_stylesheet_uri(),
		array(),
		LEADWERK_THEME_VERSION
	);

	if ( is_page( 'fuer-nutzer' ) ) {
		wp_enqueue_style(
			'leadwerk-theme-subpages',
			LEADWERK_THEME_URI . '/assets/css/subpages-shared.css',
			array( 'leadwerk-theme-style' ),
			LEADWERK_THEME_VERSION
		);
		wp_enqueue_script(
			'leadwerk-theme-subpages',
			LEADWERK_THEME_URI . '/assets/js/subpages-shared.js',
			array(),
			LEADWERK_THEME_VERSION,
			true
		);
		return;
	}

	if ( is_page( 'fuer-haendler' ) ) {
		wp_enqueue_style(
			'leadwerk-theme-subpages',
			LEADWERK_THEME_URI . '/assets/css/subpages-shared.css',
			array( 'leadwerk-theme-style' ),
			LEADWERK_THEME_VERSION
		);
		wp_enqueue_script(
			'leadwerk-theme-subpages',
			LEADWERK_THEME_URI . '/assets/js/subpages-shared.js',
			array(),
			LEADWERK_THEME_VERSION,
			true
		);
		return;
	}

	wp_enqueue_script(
		'leadwerk-theme-main',
		LEADWERK_THEME_URI . '/assets/js/main.js',
		array(),
		LEADWERK_THEME_VERSION,
		true
	);
	wp_localize_script(
		'leadwerk-theme-main',
		'ulikeitTheme',
		array(
			'themeUri' => esc_url( LEADWERK_THEME_URI ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'leadwerk_theme_enqueue_assets' );

/**
 * Truncate a human-readable SEO title for Yoast pixel/width hints (character-based heuristic).
 *
 * @param string $title      Raw title.
 * @param int    $max_chars  Maximum characters before ellipsis.
 * @return string
 */
function leadwerk_theme_truncate_seo_title_for_yoast( $title, $max_chars = 58 ) {
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
 * Build rendered HTML for Yoast (U-like-it: structured section fields + legal groups).
 *
 * @param int $post_id Post ID.
 * @return string
 */
function leadwerk_theme_get_yoast_analysis_content( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || ! function_exists( 'get_field' ) ) {
		return '';
	}

	$source_key = (string) get_post_meta( $post_id, 'leadwerk_source_key', true );

	$post_before = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
	$GLOBALS['post'] = get_post( $post_id );
	if ( $GLOBALS['post'] instanceof WP_Post ) {
		setup_postdata( $GLOBALS['post'] );
	}

	$html = '';
	$section_map = array(
		'ulikeit-home-v1'     => array(
			'field'    => 'home_sections',
			'template' => LEADWERK_THEME_DIR . '/inc/block-home-sections.php',
			'label'    => 'Home',
		),
		'ulikeit-haendler-v1' => array(
			'field'    => 'haendler_sections',
			'template' => LEADWERK_THEME_DIR . '/inc/block-haendler-sections.php',
			'label'    => 'Händler',
		),
		'ulikeit-user-v1'     => array(
			'field'    => 'user_sections',
			'template' => LEADWERK_THEME_DIR . '/inc/block-user-sections.php',
			'label'    => 'Nutzer',
		),
	);

	if ( isset( $section_map[ $source_key ] ) ) {
		$row = $section_map[ $source_key ];
		$html = leadwerk_theme_render_structured_sections_template(
			$row['field'],
			$row['label'],
			$row['template'],
			null
		);
	} else {
		foreach ( array( 'impressum_page', 'datenschutz_page', 'download_page' ) as $legal_field ) {
			$data = get_field( $legal_field, $post_id );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$headline = trim( (string) ( $data['headline'] ?? '' ) );
			$body     = (string) ( $data['content'] ?? '' );
			if ( '' !== $headline || '' !== trim( wp_strip_all_tags( $body ) ) ) {
				$html = ( '' !== $headline ? '<h1>' . esc_html( $headline ) . '</h1>' : '' ) . $body;
				break;
			}
		}
	}

	wp_reset_postdata();
	$GLOBALS['post'] = $post_before;

	if ( '' === trim( wp_strip_all_tags( $html ) ) && false === strpos( $html, '<img' ) ) {
		return '';
	}

	$html = (string) preg_replace( '#<script[^>]*>.*?</script>#is', '', $html );
	$html = (string) preg_replace( '#<style[^>]*>.*?</style>#is', '', $html );

	$clean = wp_kses_post( $html );
	$clean = (string) str_replace( array( "\r", "\n", "\t" ), ' ', $clean );
	$clean = (string) preg_replace( '/\s+/', ' ', $clean );

	return trim( $clean );
}

/**
 * Rebuild Yoast SEO Indexable for one post (admin list dots, admin bar) after meta-only changes.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function leadwerk_theme_rebuild_yoast_post_indexable( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 || ! function_exists( 'YoastSEO' ) ) {
		return;
	}
	if ( ! class_exists( '\Yoast\WP\SEO\Integrations\Watchers\Indexable_Post_Watcher', false ) ) {
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
 * After saving a Leadwerk-managed page, refresh Yoast indexables.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @param bool    $update  Whether this is an existing post.
 * @return void
 */
function leadwerk_theme_leadwerk_page_yoast_indexable_touch( $post_id, $post, $update ) {
	unset( $update );
	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( '' === (string) get_post_meta( $post_id, 'leadwerk_source_key', true ) ) {
		return;
	}

	leadwerk_theme_rebuild_yoast_post_indexable( $post_id );
}

add_action( 'save_post', 'leadwerk_theme_leadwerk_page_yoast_indexable_touch', 99, 3 );

/**
 * Feed rendered page content into Yoast's content analysis (admin).
 *
 * @param string $hook_suffix Current admin hook.
 * @return void
 */
function leadwerk_theme_enqueue_admin_yoast_analysis( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! class_exists( 'WPSEO_Options' ) || ! function_exists( 'get_current_screen' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->base ) {
		return;
	}

	$post_id = 0;
	if ( isset( $_GET['post'] ) ) {
		$post_id = (int) $_GET['post'];
	} elseif ( isset( $_POST['post_ID'] ) ) {
		$post_id = (int) $_POST['post_ID'];
	}

	if ( $post_id <= 0 ) {
		return;
	}

	$analysis_content = leadwerk_theme_get_yoast_analysis_content( $post_id );
	if ( '' === $analysis_content ) {
		return;
	}

	$max_bytes = (int) apply_filters( 'leadwerk_yoast_analysis_inline_max_bytes', 350000 );
	if ( $max_bytes > 0 && strlen( $analysis_content ) > $max_bytes ) {
		$analysis_content = substr( $analysis_content, 0, $max_bytes );
	}

	$payload = array(
		'postId'          => $post_id,
		'renderedContent' => $analysis_content,
	);
	$json    = wp_json_encode(
		$payload,
		JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
	);
	if ( false === $json ) {
		$payload['renderedContent'] = substr( wp_strip_all_tags( $analysis_content ), 0, 60000 );
		$json                       = wp_json_encode(
			$payload,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
	}
	if ( false === $json ) {
		return;
	}

	wp_enqueue_script(
		'leadwerk-admin-yoast-analysis',
		LEADWERK_THEME_URI . '/js/admin-yoast-analysis.js',
		array(),
		LEADWERK_THEME_VERSION,
		true
	);

	wp_add_inline_script(
		'leadwerk-admin-yoast-analysis',
		'window.leadwerkYoastAnalysis = ' . $json . ';',
		'before'
	);
}
add_action( 'admin_enqueue_scripts', 'leadwerk_theme_enqueue_admin_yoast_analysis', 100 );

function leadwerk_theme_dequeue_block_styles() {
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
}
add_action( 'wp_enqueue_scripts', 'leadwerk_theme_dequeue_block_styles', 100 );

/* ──────────────────────────────────────────────────────────────────────
 * 2. Theme Setup
 * ────────────────────────────────────────────────────────────────────── */

function leadwerk_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'editor-styles' );
}
add_action( 'after_setup_theme', 'leadwerk_theme_setup' );

/* ──────────────────────────────────────────────────────────────────────
 * 3. Body Classes
 * ────────────────────────────────────────────────────────────────────── */

function leadwerk_theme_body_class_subpages( $classes ) {
	if ( ! is_front_page() ) {
		$classes[] = 'is-subpage';
	}
	if ( is_404() || is_page( '404' ) ) {
		$classes[] = 'page-404';
		$classes[] = 'header-scrolled';
	}
	if ( is_page( 'download' ) ) {
		$classes[] = 'download-page-template';
	}
	return $classes;
}
add_filter( 'body_class', 'leadwerk_theme_body_class_subpages' );

/* ──────────────────────────────────────────────────────────────────────
 * 4. Favicon
 * ────────────────────────────────────────────────────────────────────── */

function leadwerk_theme_favicon() {
	if ( get_option( 'site_icon' ) ) {
		return;
	}
	$favicon_url = LEADWERK_THEME_URI . '/assets/images/favicon.png';
	echo '<link rel="icon" type="image/png" href="' . esc_url( $favicon_url ) . '">' . "\n";
}
add_action( 'wp_head', 'leadwerk_theme_favicon', 1 );

/* ──────────────────────────────────────────────────────────────────────
 * 5. ACF-Block „Home-Sektionen" registrieren
 * ────────────────────────────────────────────────────────────────────── */

function leadwerk_theme_get_dynamic_blocks() {
	return array(
		array(
			'name'            => 'ulikeit-home-sections',
			'title'           => __( 'U-like-it Startseiten-Sektionen', 'leadwerk-theme' ),
			'description'     => __( 'Hero, Why, App Steps, Pakete, Solutions, FAQ, CTA', 'leadwerk-theme' ),
			'render_callback' => 'leadwerk_theme_render_home_sections',
		),
		array(
			'name'            => 'ulikeit-user-sections',
			'title'           => __( 'U-like-it Nutzer-Seite', 'leadwerk-theme' ),
			'description'     => __( 'Hero, How It Works, App Preview, Location, Categories, Exclusive, CTA', 'leadwerk-theme' ),
			'render_callback' => 'leadwerk_theme_render_user_sections',
		),
		array(
			'name'            => 'ulikeit-haendler-sections',
			'title'           => __( 'U-like-it Händler-Seite', 'leadwerk-theme' ),
			'description'     => __( 'Hero, Vorteile, So geht’s, Dashboard, Praxisbeispiele, Konditionen, Loslegen, FAQ, CTA', 'leadwerk-theme' ),
			'render_callback' => 'leadwerk_theme_render_haendler_sections',
		),
		array(
			'name'            => 'ulikeit-download-page',
			'title'           => __( 'U-like-it Download-Seite', 'leadwerk-theme' ),
			'description'     => __( 'Download-Unterseite mit Store-Badges und App-Screenshots', 'leadwerk-theme' ),
			'render_callback' => 'leadwerk_theme_render_download_page',
		),
	);
}

function leadwerk_theme_is_block_registered( $block_name ) {
	if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
		return false;
	}

	return WP_Block_Type_Registry::get_instance()->is_registered( $block_name );
}

function leadwerk_theme_register_blocks() {
	foreach ( leadwerk_theme_get_dynamic_blocks() as $block ) {
		$wp_block_name = 'acf/' . $block['name'];

		if ( leadwerk_theme_is_block_registered( $wp_block_name ) ) {
			continue;
		}

		if ( function_exists( 'acf_register_block_type' ) ) {
			acf_register_block_type(
				array(
					'name'            => $block['name'],
					'title'           => $block['title'],
					'description'     => $block['description'],
					'render_callback' => $block['render_callback'],
					'category'        => 'theme',
					'icon'            => 'store',
					'supports'        => array( 'align' => false ),
				)
			);
		}

		if ( ! leadwerk_theme_is_block_registered( $wp_block_name ) && function_exists( 'register_block_type' ) ) {
			register_block_type(
				$wp_block_name,
				array(
					'title'           => $block['title'],
					'description'     => $block['description'],
					'category'        => 'theme',
					'icon'            => 'store',
					'supports'        => array( 'align' => false ),
					'render_callback' => $block['render_callback'],
				)
			);
		}
	}
}
add_action( 'acf/init', 'leadwerk_theme_register_blocks' );
add_action( 'init', 'leadwerk_theme_register_blocks' );

/* ──────────────────────────────────────────────────────────────────────
 * 6. ACF-Optionsseite (Stub)
 * ────────────────────────────────────────────────────────────────────── */

function leadwerk_theme_acf_options_page() {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}
	acf_add_options_page( array(
		'page_title' => __( 'Leadwerk Optionen', 'leadwerk-theme' ),
		'menu_title' => __( 'Leadwerk Optionen', 'leadwerk-theme' ),
		'menu_slug'  => 'acf-options',
		'capability' => 'edit_posts',
	) );
}
add_action( 'init', 'leadwerk_theme_acf_options_page' );

/* ──────────────────────────────────────────────────────────────────────
 * 7. Render-Callback: home_sections
 * ────────────────────────────────────────────────────────────────────── */

function leadwerk_theme_resolve_render_post_id( $block = null ) {
	if ( is_object( $block ) && ! empty( $block->context['postId'] ) ) {
		return (int) $block->context['postId'];
	}

	$post_id = get_the_ID();
	if ( $post_id ) {
		return (int) $post_id;
	}

	$post_id = get_queried_object_id();
	if ( $post_id ) {
		return (int) $post_id;
	}

	global $post;
	if ( $post instanceof WP_Post ) {
		return (int) $post->ID;
	}

	return 0;
}

function leadwerk_theme_get_last_good_field_value( $field_name, $post_id ) {
	$snapshot = get_post_meta( $post_id, '_leadwerk_last_good_' . sanitize_key( (string) $field_name ), true );
	if ( ! is_array( $snapshot ) || ! array_key_exists( 'value', $snapshot ) ) {
		return null;
	}

	return $snapshot['value'];
}

function leadwerk_theme_render_missing_content_notice( $label, $post_id = 0 ) {
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return '';
	}

	return '<section style="padding:32px 0;"><div class="container"><div style="padding:18px 20px;border:1px solid #fdba74;border-radius:18px;background:#fff7ed;color:#9a3412;">Leadwerk content is empty for &quot;' . esc_html( (string) $label ) . '&quot;. Run the importer or fill the Leadwerk Fields metabox.</div></div></section>';
}

function leadwerk_theme_resolve_structured_sections( $field_name, $post_id ) {
	$sections = function_exists( 'get_field' ) ? get_field( $field_name, $post_id ) : null;
	if ( is_array( $sections ) && ! empty( $sections ) ) {
		return array(
			'sections'                 => $sections,
			'used_last_good_fallback'  => false,
		);
	}

	$snapshot = leadwerk_theme_get_last_good_field_value( $field_name, $post_id );
	if ( is_array( $snapshot ) && ! empty( $snapshot ) ) {
		return array(
			'sections'                 => $snapshot,
			'used_last_good_fallback'  => true,
		);
	}

	return array(
		'sections'                 => array(),
		'used_last_good_fallback'  => false,
	);
}

function leadwerk_theme_render_structured_sections_template( $field_name, $label, $template_file, $block = null ) {
	$post_id = leadwerk_theme_resolve_render_post_id( $block );
	if ( ! $post_id || ! is_file( $template_file ) ) {
		return '';
	}

	$resolved = leadwerk_theme_resolve_structured_sections( $field_name, $post_id );
	$sections = leadwerk_theme_fix_mojibake_deep( $resolved['sections'] );

	if ( ! is_array( $sections ) || empty( $sections ) ) {
		return leadwerk_theme_render_missing_content_notice( $label, $post_id );
	}

	ob_start();
	include $template_file;
	return (string) ob_get_clean();
}

function leadwerk_theme_render_home_sections( $attributes = array(), $content = '', $block = null ) {
	return leadwerk_theme_render_structured_sections_template(
		'home_sections',
		'U-like-it Startseiten-Sektionen',
		LEADWERK_THEME_DIR . '/inc/block-home-sections.php',
		$block
	);
}

function leadwerk_theme_render_user_sections( $attributes = array(), $content = '', $block = null ) {
	return leadwerk_theme_render_structured_sections_template(
		'user_sections',
		'U-like-it Nutzer-Seite',
		LEADWERK_THEME_DIR . '/inc/block-user-sections.php',
		$block
	);
}

function leadwerk_theme_render_haendler_sections( $attributes = array(), $content = '', $block = null ) {
	return leadwerk_theme_render_structured_sections_template(
		'haendler_sections',
		'U-like-it Händler-Seite',
		LEADWERK_THEME_DIR . '/inc/block-haendler-sections.php',
		$block
	);
}

function leadwerk_theme_resolve_scalar_group( $field_name, $post_id ) {
	$value = function_exists( 'get_field' ) ? get_field( $field_name, $post_id ) : null;
	if ( is_array( $value ) && ! empty( $value ) ) {
		return array(
			'value'                   => $value,
			'used_last_good_fallback' => false,
		);
	}

	$snapshot = leadwerk_theme_get_last_good_field_value( $field_name, $post_id );
	if ( is_array( $snapshot ) && ! empty( $snapshot ) ) {
		return array(
			'value'                   => $snapshot,
			'used_last_good_fallback' => true,
		);
	}

	return array(
		'value'                   => array(),
		'used_last_good_fallback' => false,
	);
}

function leadwerk_theme_render_download_page( $attributes = array(), $content = '', $block = null ) {
	$post_id = leadwerk_theme_resolve_render_post_id( $block );
	if ( ! $post_id ) {
		return '';
	}

	$resolved = leadwerk_theme_resolve_scalar_group( 'download_page', $post_id );
	$download_page = leadwerk_theme_fix_mojibake_deep( $resolved['value'] );

	if ( ! is_array( $download_page ) || empty( $download_page ) ) {
		return leadwerk_theme_render_missing_content_notice( 'U-like-it Download-Seite', $post_id );
	}

	ob_start();
	include LEADWERK_THEME_DIR . '/inc/block-download-page.php';
	return (string) ob_get_clean();
}

function leadwerk_theme_get_option_url( $field_name, $default = '#' ) {
	if ( ! function_exists( 'get_field' ) ) {
		return $default;
	}

	$url = get_field( $field_name, 'option' );
	$url = trim( (string) $url );

	if ( '' === $url || '#' === $url || '/#' === $url ) {
		return $default;
	}

	return $url;
}

function leadwerk_theme_fix_mojibake( $value ) {
	if ( ! is_string( $value ) || '' === $value ) {
		return $value;
	}

	if ( false === strpos( $value, 'Ã' ) && false === strpos( $value, 'â' ) && false === strpos( $value, 'Â' ) ) {
		return $value;
	}

	return strtr(
		$value,
		array(
			'Ã„'  => 'Ä',
			'Ã–'  => 'Ö',
			'Ãœ'  => 'Ü',
			'Ã¤'  => 'ä',
			'Ã¶'  => 'ö',
			'Ã¼'  => 'ü',
			'ÃŸ'  => 'ß',
			'â€“' => '–',
			'â€”' => '—',
			'â€¦' => '…',
			'â€ž' => '„',
			'â€œ' => '“',
			'â€�' => '”',
			'â€˜' => "'",
			'â€™' => "'",
			'â†’' => '→',
			'Â'   => '',
		)
	);
}

function leadwerk_theme_fix_mojibake_deep( $value ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			$value[ $key ] = leadwerk_theme_fix_mojibake_deep( $item );
		}

		return $value;
	}

	return leadwerk_theme_fix_mojibake( $value );
}

function leadwerk_theme_normalize_home_download_url( $url ) {
	$url = trim( (string) $url );

	if ( in_array( $url, array( '', '#', '#download', '/#download', 'index.html#download', '/index.html#download', 'http://index.html#download', 'https://index.html#download' ), true ) ) {
		return '/download/';
	}

	if ( in_array( $url, array( 'download.html', '/download.html', '/download' ), true ) ) {
		return '/download/';
	}

	return $url;
}

function leadwerk_theme_normalize_home_registration_url( $url ) {
	$url = trim( (string) $url );

	if ( in_array( $url, array( '', '#', '#download', '/#download', 'index.html#download', '/index.html#download', '#onboarding', 'haendler.html#onboarding', '/haendler.html#onboarding' ), true ) ) {
		return '/fuer-haendler/#onboarding';
	}

	return $url;
}

function leadwerk_theme_normalize_user_download_url( $url ) {
	$url = trim( (string) $url );

	if ( in_array( $url, array( '', '#', '#download', '/#download', 'index.html#download', '/index.html#download', 'http://index.html#download', 'https://index.html#download', 'user.html', '/user.html', 'user.html#download', '/user.html#download', '/fuer-nutzer/', '/fuer-nutzer/#download', 'http://user.html#download' ), true ) ) {
		return '/download/';
	}

	if ( in_array( $url, array( 'download.html', '/download.html', '/download' ), true ) ) {
		return '/download/';
	}

	return $url;
}

function leadwerk_theme_get_default_store_urls() {
	return array(
		'apple'  => 'https://apps.apple.com/de/app/u-like-it/id1593884667',
		'google' => 'https://play.google.com/store/apps/details?id=de.u_like_it',
	);
}

function leadwerk_theme_normalize_wpforms_value( $value ) {
	$raw = trim( wp_unslash( (string) $value ) );
	if ( '' === $raw ) {
		return array(
			'id'        => '',
			'shortcode' => '',
		);
	}

	if ( preg_match( '/\bid\s*=\s*(["\']?)(\d+)\1/i', $raw, $matches ) ) {
		$id = $matches[2];
	} elseif ( preg_match( '/\d+/', $raw, $matches ) ) {
		$id = $matches[0];
	} else {
		$id = '';
	}

	if ( '' === $id ) {
		return array(
			'id'        => '',
			'shortcode' => '',
		);
	}

	return array(
		'id'        => $id,
		'shortcode' => sprintf( '[wpforms id="%s" title="false" description="false"]', $id ),
	);
}

function leadwerk_theme_resolve_wpforms_embed( $value ) {
	$data = leadwerk_theme_normalize_wpforms_value( $value );
	$id   = (string) ( $data['id'] ?? '' );

	$result = array(
		'id'                   => $id,
		'shortcode'            => (string) ( $data['shortcode'] ?? '' ),
		'shortcode_registered' => shortcode_exists( 'wpforms' ),
		'html'                 => '',
		'is_ready'             => false,
		'reason'               => 'missing_id',
	);

	if ( '' === $id ) {
		return $result;
	}

	if ( '' === $result['shortcode'] ) {
		$result['reason'] = 'invalid_shortcode';
		return $result;
	}

	$rendered = do_shortcode( $result['shortcode'] );
	$trimmed  = trim( (string) $rendered );

	if ( '' !== $trimmed && false === stripos( $trimmed, '[wpforms' ) ) {
		$result['html']     = $rendered;
		$result['is_ready'] = true;
		$result['reason']   = 'rendered';
		return $result;
	}

	$result['reason'] = $result['shortcode_registered'] ? 'empty_output' : 'shortcode_unavailable';
	return $result;
}

function leadwerk_theme_get_wpforms_admin_note( $embed_state ) {
	$id                  = esc_html( (string) ( $embed_state['id'] ?? '' ) );
	$shortcode_available = ! empty( $embed_state['shortcode_registered'] ) ? 'ja' : 'nein';
	$reason              = (string) ( $embed_state['reason'] ?? 'missing_id' );

	$diagnostic_map = array(
		'missing_id'           => 'Keine gueltige Formular-ID gespeichert.',
		'invalid_shortcode'    => 'Die gespeicherte Eingabe konnte nicht als WPForms-ID erkannt werden.',
		'shortcode_unavailable'=> 'Der WPForms-Shortcode ist in dieser Anfrage nicht registriert.',
		'empty_output'         => 'WPForms ist registriert, liefert aber fuer diese ID keinen Output.',
		'rendered'             => 'Formular erfolgreich gerendert.',
	);

	$diagnostic = esc_html( $diagnostic_map[ $reason ] ?? $reason );
	$message    = 'Leadwerk Optionen unter <strong>Händler WPForms ID</strong> pflegen und WPForms aktivieren, damit das Formular hier erscheint.';
	$message   .= ' Aktuelle Diagnose: ' . $diagnostic;

	if ( '' !== $id ) {
		$message .= ' Erkannte ID: <strong>' . $id . '</strong>.';
	}

	$message .= ' Shortcode registriert: <strong>' . esc_html( $shortcode_available ) . '</strong>.';

	return '<p class="haendler-form-admin-note">' . wp_kses_post( $message ) . '</p>';
}

function leadwerk_theme_get_store_badge_data() {
	$default_store_urls = leadwerk_theme_get_default_store_urls();
	$apple_badge = function_exists( 'get_field' ) ? get_field( 'app_store_badge', 'option' ) : null;
	$google_badge = function_exists( 'get_field' ) ? get_field( 'google_play_badge', 'option' ) : null;

	return array(
		'apple_url'      => leadwerk_theme_get_option_url( 'app_store_url', $default_store_urls['apple'] ),
		'google_url'     => leadwerk_theme_get_option_url( 'google_play_url', $default_store_urls['google'] ),
		'apple_badge'    => leadwerk_theme_resolve_acf_image_url( $apple_badge, 'full' ) ?: LEADWERK_THEME_URI . '/assets/images/apple_app_store_badge.webp',
		'google_badge'   => leadwerk_theme_resolve_acf_image_url( $google_badge, 'full' ) ?: LEADWERK_THEME_URI . '/assets/images/google-play-badge.webp',
		'apple_qr'       => LEADWERK_THEME_URI . '/assets/images/qr-app-store.svg',
		'google_qr'      => LEADWERK_THEME_URI . '/assets/images/qr-google-play.svg',
	);
}

/* ──────────────────────────────────────────────────────────────────────
 * 8. Dynamic Footer Data
 * ────────────────────────────────────────────────────────────────────── */

function leadwerk_theme_dynamic_footer( $content ) {
	if ( strpos( $content, 'data-logo-field=' ) === false && strpos( $content, 'data-field=' ) === false && strpos( $content, 'data-badge=' ) === false && strpos( $content, 'data-store-link=' ) === false ) {
		return $content;
	}
	$has_fields = function_exists( 'get_field' );

	// Logo (header)
	$logo_url = LEADWERK_THEME_URI . '/assets/images/logo.webp';
	if ( $has_fields ) {
		$logo_id = get_field( 'logo', 'option' );
		if ( $logo_id && is_numeric( $logo_id ) ) {
			$u = wp_get_attachment_image_url( (int) $logo_id, 'full' );
			if ( $u ) $logo_url = $u;
		}
	}
	$content = preg_replace(
		'/<img[^>]*data-logo-field="true"[^>]*>/s',
		'<img src="' . esc_url( $logo_url ) . '" alt="U-like-it" class="logo-img">',
		$content
	);

	// Footer logo
	$footer_logo_url = LEADWERK_THEME_URI . '/assets/images/logo-weiss.webp';
	if ( $has_fields ) {
		$fl_id = get_field( 'footer_logo', 'option' );
		if ( ! $fl_id ) $fl_id = get_field( 'logo', 'option' );
		if ( $fl_id && is_numeric( $fl_id ) ) {
			$u = wp_get_attachment_image_url( (int) $fl_id, 'full' );
			if ( $u ) $footer_logo_url = $u;
		}
	}
	$content = preg_replace(
		'/<img[^>]*data-logo-field="footer"[^>]*>/s',
		'<img src="' . esc_url( $footer_logo_url ) . '" alt="U-like-it" class="footer-logo-img" width="140" height="40">',
		$content
	);

	if ( $has_fields ) {
		// Footer text
		$footer_text = get_field( 'footer_text', 'option' );
		if ( $footer_text ) {
			$content = preg_replace(
				'/<p[^>]*data-field="footer_text"[^>]*>.*?<\/p>/s',
				'<p class="footer-seo-text">' . esc_html( $footer_text ) . '</p>',
				$content
			);
		}

		// Copyright
		$copy = get_field( 'copyright_text', 'option' );
		if ( $copy ) {
			$content = preg_replace(
				'/<p[^>]*data-field="copyright"[^>]*>.*?<\/p>/s',
				'<p class="footer-copy">' . wp_kses_post( $copy ) . '</p>',
				$content
			);
		}
	}

	$store_badges = leadwerk_theme_get_store_badge_data();

	$content = preg_replace(
		'/<img[^>]*data-badge="apple"[^>]*>/s',
		'<img src="' . esc_url( $store_badges['apple_badge'] ) . '" alt="Im App Store herunterladen" width="135" height="40" data-badge="apple">',
		$content
	);
	$content = preg_replace(
		'/<img[^>]*data-badge="google"[^>]*>/s',
		'<img src="' . esc_url( $store_badges['google_badge'] ) . '" alt="Bei Google Play herunterladen" width="135" height="40" data-badge="google">',
		$content
	);
	$content = preg_replace(
		'/<img[^>]*data-qr-badge="apple"[^>]*>/s',
		'<img src="' . esc_url( $store_badges['apple_qr'] ) . '" alt="QR-Code f&uuml;r App Store" width="96" height="96" data-qr-badge="apple">',
		$content
	);
	$content = preg_replace(
		'/<img[^>]*data-qr-badge="google"[^>]*>/s',
		'<img src="' . esc_url( $store_badges['google_qr'] ) . '" alt="QR-Code f&uuml;r Google Play" width="96" height="96" data-qr-badge="google">',
		$content
	);
	$content = preg_replace(
		'/href="[^"]*" data-store-link="apple"/',
		'href="' . esc_url( $store_badges['apple_url'] ) . '" data-store-link="apple"',
		$content
	);
	$content = preg_replace(
		'/href="[^"]*" data-store-link="google"/',
		'href="' . esc_url( $store_badges['google_url'] ) . '" data-store-link="google"',
		$content
	);

	return $content;
}
add_filter( 'render_block', 'leadwerk_theme_dynamic_footer' );

/* ──────────────────────────────────────────────────────────────────────
 * 9. SEO Fallback (wenn Yoast nicht aktiv)
 * ────────────────────────────────────────────────────────────────────── */

function leadwerk_theme_seo_fallback() {
	if ( defined( 'WPSEO_VERSION' ) ) {
		return;
	}
	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}
	$meta_desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
	if ( $meta_desc ) {
		echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '">' . "\n";
	}
	echo '<link rel="canonical" href="' . esc_url( get_permalink( $post_id ) ) . '">' . "\n";
	$og_title = get_post_meta( $post_id, '_yoast_wpseo_opengraph-title', true );
	$og_desc  = get_post_meta( $post_id, '_yoast_wpseo_opengraph-description', true );
	if ( $og_title || $og_desc ) {
		echo '<meta property="og:type" content="website">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( get_permalink( $post_id ) ) . '">' . "\n";
		if ( $og_title ) {
			echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '">' . "\n";
		}
		if ( $og_desc ) {
			echo '<meta property="og:description" content="' . esc_attr( $og_desc ) . '">' . "\n";
		}
		echo '<meta property="og:locale" content="de_DE">' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'leadwerk_theme_seo_fallback', 2 );

function leadwerk_theme_document_title( $title ) {
	if ( defined( 'WPSEO_VERSION' ) ) {
		return $title;
	}
	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return $title;
	}
	$seo_title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
	if ( $seo_title ) {
		return $seo_title;
	}
	return $title;
}
add_filter( 'pre_get_document_title', 'leadwerk_theme_document_title', 20 );

/* ──────────────────────────────────────────────────────────────────────
 * 10. ACF Helper Functions
 * ────────────────────────────────────────────────────────────────────── */

function leadwerk_theme_resolve_acf_image_url( $img, $size = 'full' ) {
	if ( $img === null || $img === '' || ( is_array( $img ) && $img === array() ) ) {
		return '';
	}
	if ( is_array( $img ) ) {
		$id = isset( $img['ID'] ) ? (int) $img['ID'] : 0;
		if ( $id ) {
			$u = wp_get_attachment_image_url( $id, $size );
			return $u ? $u : '';
		}
		if ( ! empty( $img['url'] ) ) {
			return esc_url_raw( (string) $img['url'] );
		}
		return '';
	}
	if ( is_numeric( $img ) ) {
		$u = wp_get_attachment_image_url( (int) $img, $size );
		return $u ? $u : '';
	}
	return '';
}
