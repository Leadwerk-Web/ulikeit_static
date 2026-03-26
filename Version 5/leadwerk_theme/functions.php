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

define( 'LEADWERK_THEME_VERSION', '1.0.0' );
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

function leadwerk_theme_register_blocks() {
	$blocks = array(
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
			'title'           => __( 'U-like-it Haendler-Seite', 'leadwerk-theme' ),
			'description'     => __( 'Hero, Benefits, How It Works, Dashboard, Cases, Conditions, Onboarding, FAQ, CTA', 'leadwerk-theme' ),
			'render_callback' => 'leadwerk_theme_render_haendler_sections',
		),
	);

	foreach ( $blocks as $block ) {
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
		} else {
			register_block_type(
				'acf/' . $block['name'],
				array(
					'render_callback' => $block['render_callback'],
				)
			);
		}
	}
}
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

function leadwerk_theme_render_home_sections() {
	$post_id = get_the_ID();
	if ( ! $post_id || ! function_exists( 'get_field' ) ) {
		return;
	}
	$sections = get_field( 'home_sections', $post_id );
	if ( ! is_array( $sections ) || empty( $sections ) ) {
		return;
	}
	include LEADWERK_THEME_DIR . '/inc/block-home-sections.php';
}

function leadwerk_theme_render_user_sections() {
	$post_id = get_the_ID();
	if ( ! $post_id || ! function_exists( 'get_field' ) ) {
		return;
	}
	$sections = get_field( 'user_sections', $post_id );
	if ( ! is_array( $sections ) || empty( $sections ) ) {
		return;
	}
	include LEADWERK_THEME_DIR . '/inc/block-user-sections.php';
}

function leadwerk_theme_render_haendler_sections() {
	$post_id = get_the_ID();
	if ( ! $post_id || ! function_exists( 'get_field' ) ) {
		return;
	}
	$sections = get_field( 'haendler_sections', $post_id );
	if ( ! is_array( $sections ) || empty( $sections ) ) {
		return;
	}
	include LEADWERK_THEME_DIR . '/inc/block-haendler-sections.php';
}

function leadwerk_theme_get_option_url( $field_name, $default = '#' ) {
	if ( ! function_exists( 'get_field' ) ) {
		return $default;
	}

	$url = get_field( $field_name, 'option' );
	return ! empty( $url ) ? $url : $default;
}

function leadwerk_theme_get_store_badge_data() {
	$apple_badge = function_exists( 'get_field' ) ? get_field( 'app_store_badge', 'option' ) : null;
	$google_badge = function_exists( 'get_field' ) ? get_field( 'google_play_badge', 'option' ) : null;

	return array(
		'apple_url'      => leadwerk_theme_get_option_url( 'app_store_url', '#' ),
		'google_url'     => leadwerk_theme_get_option_url( 'google_play_url', '#' ),
		'apple_badge'    => leadwerk_theme_resolve_acf_image_url( $apple_badge, 'full' ) ?: LEADWERK_THEME_URI . '/assets/images/apple_app_store_badge.png',
		'google_badge'   => leadwerk_theme_resolve_acf_image_url( $google_badge, 'full' ) ?: LEADWERK_THEME_URI . '/assets/images/google-play-badge.png',
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
	$logo_url = LEADWERK_THEME_URI . '/assets/images/logo.png';
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
	$footer_logo_url = LEADWERK_THEME_URI . '/assets/images/logo-weiss.png';
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

	if ( ! $has_fields ) {
		return $content;
	}

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

	// App Store badges
	$apple_badge_id = get_field( 'app_store_badge', 'option' );
	if ( $apple_badge_id && is_numeric( $apple_badge_id ) ) {
		$u = wp_get_attachment_image_url( (int) $apple_badge_id, 'full' );
		if ( $u ) {
			$content = preg_replace(
				'/<img[^>]*data-badge="apple"[^>]*>/s',
				'<img src="' . esc_url( $u ) . '" alt="Download on the App Store" width="135" height="40">',
				$content
			);
		}
	}
	$google_badge_id = get_field( 'google_play_badge', 'option' );
	if ( $google_badge_id && is_numeric( $google_badge_id ) ) {
		$u = wp_get_attachment_image_url( (int) $google_badge_id, 'full' );
		if ( $u ) {
			$content = preg_replace(
				'/<img[^>]*data-badge="google"[^>]*>/s',
				'<img src="' . esc_url( $u ) . '" alt="Bei Google Play herunterladen" width="135" height="40">',
				$content
			);
		}
	}

	$apple_store_url  = leadwerk_theme_get_option_url( 'app_store_url', '#' );
	$google_store_url = leadwerk_theme_get_option_url( 'google_play_url', '#' );
	$content          = str_replace( 'href="#" data-store-link="apple"', 'href="' . esc_url( $apple_store_url ) . '" data-store-link="apple"', $content );
	$content          = str_replace( 'href="#" data-store-link="google"', 'href="' . esc_url( $google_store_url ) . '" data-store-link="google"', $content );

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
