<?php
/**
 * Download page block.
 *
 * @package Leadwerk_Theme
 */

if ( ! isset( $download_page ) || ! is_array( $download_page ) ) {
	return;
}

$store_badges = function_exists( 'leadwerk_theme_get_store_badge_data' ) ? leadwerk_theme_get_store_badge_data() : array();

$eyebrow  = trim( (string) ( $download_page['eyebrow'] ?? '' ) );
$headline = trim( (string) ( $download_page['headline'] ?? '' ) );
$subtitle = trim( (string) ( $download_page['subtitle'] ?? '' ) );
$content  = (string) ( $download_page['content'] ?? '' );

if ( '' === $eyebrow ) {
	$eyebrow = 'U-like-it App';
}
if ( '' === $headline ) {
	$headline = 'Herunterladen';
}
if ( '' === $subtitle ) {
	$subtitle = 'Wo bekomme ich U-like-it f&uuml;r mein Smartphone?';
}
if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
	$content = '<p>U-like-it gibt es f&uuml;r Android und iOS. Einfach den passenden Store ausw&auml;hlen und los geht&apos;s. Bei der ersten Registrierung entscheidest du, ob du als Ladenbesitzer:in oder K&auml;ufer:in die App verwenden willst.</p>';
}

$apple_badge = ! empty( $download_page['apple_badge'] ) && function_exists( 'leadwerk_theme_resolve_acf_image_url' )
	? leadwerk_theme_resolve_acf_image_url( $download_page['apple_badge'], 'full' )
	: '';
$google_badge = ! empty( $download_page['google_badge'] ) && function_exists( 'leadwerk_theme_resolve_acf_image_url' )
	? leadwerk_theme_resolve_acf_image_url( $download_page['google_badge'], 'full' )
	: '';

$apple_url    = trim( (string) ( $download_page['apple_url'] ?? '' ) ) ?: ( $store_badges['apple_url'] ?? 'https://apps.apple.com/de/app/u-like-it/id1593884667' );
$google_url   = trim( (string) ( $download_page['google_url'] ?? '' ) ) ?: ( $store_badges['google_url'] ?? 'https://play.google.com/store/apps/details?id=de.u_like_it' );
$apple_badge  = $apple_badge ?: ( $store_badges['apple_badge'] ?? LEADWERK_THEME_URI . '/assets/images/apple_app_store_badge.png' );
$google_badge = $google_badge ?: ( $store_badges['google_badge'] ?? LEADWERK_THEME_URI . '/assets/images/google-play-badge.png' );

$platforms = isset( $download_page['platforms'] ) && is_array( $download_page['platforms'] ) ? $download_page['platforms'] : array();
if ( empty( $platforms ) ) {
	$platforms = array(
		array( 'text' => 'iPhone und Android werden unterst&uuml;tzt.' ),
		array( 'text' => 'Die App ist kostenlos f&uuml;r Nutzer:innen.' ),
		array( 'text' => 'Ein Konto reicht f&uuml;r K&auml;ufer:innen und Ladenbesitzer:innen.' ),
	);
}

$fallback_slides = array(
	array( 'src' => LEADWERK_THEME_URI . '/assets/images/gallery/iPhone 11 Pro Max-00BuyerLogin_framed.png', 'alt' => 'U-like-it Login Screen' ),
	array( 'src' => LEADWERK_THEME_URI . '/assets/images/gallery/iPhone 11 Pro Max-01BuyerDashboard_framed.png', 'alt' => 'U-like-it Dashboard' ),
	array( 'src' => LEADWERK_THEME_URI . '/assets/images/gallery/iPhone 11 Pro Max-02BuyerShopList_framed.png', 'alt' => 'U-like-it Shopliste' ),
	array( 'src' => LEADWERK_THEME_URI . '/assets/images/gallery/iPhone 11 Pro Max-03BuyerFirstShop_framed.png', 'alt' => 'U-like-it Shop Detail' ),
	array( 'src' => LEADWERK_THEME_URI . '/assets/images/gallery/iPhone 11 Pro Max-04BuyerOfferList_framed.png', 'alt' => 'U-like-it Angebote' ),
	array( 'src' => LEADWERK_THEME_URI . '/assets/images/gallery/iPhone 11 Pro Max-05BuyerFirstOffer_framed.png', 'alt' => 'U-like-it Angebotsdetail' ),
	array( 'src' => LEADWERK_THEME_URI . '/assets/images/gallery/iPhone 11 Pro Max-06BuyerSettings_framed.png', 'alt' => 'U-like-it Einstellungen' ),
	array( 'src' => LEADWERK_THEME_URI . '/assets/images/gallery/iPhone 11 Pro Max-07BuyerCategories_framed.png', 'alt' => 'U-like-it Kategorien' ),
	array( 'src' => LEADWERK_THEME_URI . '/assets/images/gallery/iPhone 11 Pro Max-10OwnerOfferList_framed.png', 'alt' => 'U-like-it Anbieter Angebote' ),
	array( 'src' => LEADWERK_THEME_URI . '/assets/images/gallery/iPhone 11 Pro Max-11OwnerEditOffer_framed.png', 'alt' => 'U-like-it Angebot bearbeiten' ),
	array( 'src' => LEADWERK_THEME_URI . '/assets/images/gallery/iPhone 11 Pro Max-12OwnerEditDescription_framed.png', 'alt' => 'U-like-it Beschreibung bearbeiten' ),
	array( 'src' => LEADWERK_THEME_URI . '/assets/images/gallery/iPhone 11 Pro Max-13OwnerPublish_framed.png', 'alt' => 'U-like-it Angebot ver&ouml;ffentlichen' ),
	array( 'src' => LEADWERK_THEME_URI . '/assets/images/gallery/iPhone 11 Pro Max-14OwnerStatYear_framed.png', 'alt' => 'U-like-it Statistik' ),
);

$slides = array();
foreach ( (array) ( $download_page['slides'] ?? array() ) as $slide ) {
	if ( ! is_array( $slide ) || empty( $slide['image'] ) ) {
		continue;
	}
	$image_url = function_exists( 'leadwerk_theme_resolve_acf_image_url' ) ? leadwerk_theme_resolve_acf_image_url( $slide['image'], 'full' ) : '';
	if ( ! $image_url ) {
		continue;
	}
	$slides[] = array(
		'src' => $image_url,
		'alt' => trim( (string) ( $slide['alt'] ?? '' ) ),
	);
}
if ( empty( $slides ) ) {
	$slides = $fallback_slides;
}

echo '<div class="scroll-progress" id="scroll-progress" aria-hidden="true"></div>';
echo '<div class="custom-cursor" id="custom-cursor" aria-hidden="true"></div>';
echo '<div class="custom-cursor-shadow" id="custom-cursor-shadow" aria-hidden="true"></div>';
?>
<section class="section download-page-section" id="download-page">
	<div class="download-page-bg" aria-hidden="true"></div>
	<div class="container download-page-inner">
		<div class="download-page-copy reveal reveal-up">
			<span class="download-eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
			<h1 class="download-title"><?php echo esc_html( $headline ); ?></h1>
			<p class="download-subtitle"><?php echo wp_kses_post( $subtitle ); ?></p>
			<div class="download-copy-text">
				<?php echo wp_kses_post( $content ); ?>
			</div>
			<div class="download-store-row" aria-label="U-like-it App Stores">
				<a href="<?php echo esc_url( $apple_url ); ?>" class="download-store-badge" target="_blank" rel="noopener noreferrer" aria-label="Im App Store herunterladen">
					<img src="<?php echo esc_url( $apple_badge ); ?>" alt="Im App Store herunterladen" width="155" height="46">
				</a>
				<a href="<?php echo esc_url( $google_url ); ?>" class="download-store-badge" target="_blank" rel="noopener noreferrer" aria-label="Bei Google Play herunterladen">
					<img src="<?php echo esc_url( $google_badge ); ?>" alt="Bei Google Play herunterladen" width="155" height="46">
				</a>
			</div>
			<ul class="download-platform-list">
				<?php foreach ( $platforms as $platform ) : ?>
					<?php $platform_text = is_array( $platform ) ? trim( (string) ( $platform['text'] ?? '' ) ) : trim( (string) $platform ); ?>
					<?php if ( '' !== $platform_text ) : ?>
						<li><?php echo wp_kses_post( $platform_text ); ?></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="download-slider-shell reveal reveal-scale">
			<div class="download-phone-frame" data-download-slider aria-label="U-like-it App Screenshots">
				<div class="download-slider-viewport">
					<div class="download-slider-track">
						<?php foreach ( $slides as $index => $slide ) : ?>
							<div class="download-slider-slide">
								<img src="<?php echo esc_url( $slide['src'] ); ?>" alt="<?php echo esc_attr( html_entity_decode( $slide['alt'] ?: 'U-like-it App Screenshot', ENT_QUOTES, 'UTF-8' ) ); ?>" class="download-slider-img" width="300" height="600"<?php echo $index > 0 ? ' loading="lazy"' : ''; ?>>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<button type="button" class="download-slider-btn download-slider-prev" data-download-slider-prev aria-label="Vorheriger Screenshot">&lsaquo;</button>
				<button type="button" class="download-slider-btn download-slider-next" data-download-slider-next aria-label="N&auml;chster Screenshot">&rsaquo;</button>
			</div>
		</div>
	</div>
</section>
