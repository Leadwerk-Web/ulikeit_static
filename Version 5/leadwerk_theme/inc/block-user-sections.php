<?php
/**
 * Render user page sections.
 *
 * @package Leadwerk_Theme
 */

if ( ! isset( $sections ) || ! is_array( $sections ) ) {
	return;
}

$leadwerk_store_badges = leadwerk_theme_get_store_badge_data();

echo '<div class="scroll-progress" id="scroll-progress" aria-hidden="true"></div>';
echo '<div class="custom-cursor" id="custom-cursor" aria-hidden="true"></div>';
echo '<div class="custom-cursor-shadow" id="custom-cursor-shadow" aria-hidden="true"></div>';

if ( ! function_exists( 'leadwerk_theme_render_store_badges' ) ) {
	function leadwerk_theme_render_store_badges( $store, $width = 155, $height = 46, $class = 'store-badge' ) {
		?>
		<a href="<?php echo esc_url( $store['apple_url'] ); ?>" class="<?php echo esc_attr( $class ); ?>" aria-label="Im App Store herunterladen">
			<img src="<?php echo esc_url( $store['apple_badge'] ); ?>" alt="Download on the App Store" width="<?php echo (int) $width; ?>" height="<?php echo (int) $height; ?>">
		</a>
		<a href="<?php echo esc_url( $store['google_url'] ); ?>" class="<?php echo esc_attr( $class ); ?>" aria-label="Bei Google Play herunterladen">
			<img src="<?php echo esc_url( $store['google_badge'] ); ?>" alt="Bei Google Play herunterladen" width="<?php echo (int) $width; ?>" height="<?php echo (int) $height; ?>">
		</a>
		<?php
	}
}

if ( ! function_exists( 'leadwerk_theme_render_user_ticker' ) ) {
	function leadwerk_theme_render_user_ticker( $items ) {
		?>
		<div class="ticker-banner" aria-hidden="true">
			<div class="ticker-track">
				<?php foreach ( array_merge( $items, $items ) as $item ) : ?>
					<span class="ticker-item"><?php echo esc_html( $item ); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}

foreach ( $sections as $section ) {
	$layout = $section['acf_fc_layout'] ?? '';

	switch ( $layout ) {
		case 'hero':
			$title_lines = is_array( $section['title_lines'] ?? null ) ? $section['title_lines'] : array();
			$hero_image  = leadwerk_theme_resolve_acf_image_url( $section['hero_image'] ?? 0, 'full' );
			if ( ! $hero_image ) {
				$hero_image = LEADWERK_THEME_URI . '/assets/images/frau-freigestellt.png';
			}
			?>
			<div class="hero-why-bg">
				<div class="hero-bg-parallax" data-parallax="0.08" aria-hidden="true"></div>
				<div class="hero-frau-layer" data-parallax="0.24" aria-hidden="true">
					<img src="<?php echo esc_url( $hero_image ); ?>" alt="" class="hero-frau-img">
				</div>
				<section class="hero user-hero" id="hero">
					<div class="hero-bg-wrap" aria-hidden="true"></div>
					<div class="container hero-inner" id="hero-inner">
						<div class="hero-content reveal reveal-up">
							<h1 class="hero-title user-hero-title">
								<?php foreach ( $title_lines as $line ) : ?>
									<span class="hero-title-line<?php echo ! empty( $line['accent'] ) ? ' haendler-hero-accent' : ''; ?>"><?php echo esc_html( $line['text'] ?? '' ); ?></span>
								<?php endforeach; ?>
							</h1>
							<p class="user-hero-sub"><?php echo esc_html( $section['subtitle'] ?? '' ); ?></p>
							<div class="hero-cta user-hero-cta">
								<?php leadwerk_theme_render_store_badges( $leadwerk_store_badges ); ?>
							</div>
							<p class="user-hero-micro"><?php echo esc_html( $section['micro_text'] ?? '' ); ?></p>
						</div>
					</div>
				</section>
				<div class="hero-arrow-wrap" aria-hidden="true">
					<div class="hero-arrow-parallax">
						<img src="<?php echo esc_url( LEADWERK_THEME_URI . '/assets/images/pfeil-3.png' ); ?>" alt="" class="hero-arrow-img">
					</div>
				</div>
			</div>
			<?php
			break;

		case 'how_it_works':
			$steps = is_array( $section['steps'] ?? null ) ? $section['steps'] : array();
			?>
			<section class="section user-how-section" id="how-it-works">
				<div class="user-how-blob-bg" aria-hidden="true">
					<div class="light-blob-parallax" data-parallax="0.12"><div class="light-blob light-blob-blue"></div></div>
					<div class="light-blob-parallax" data-parallax="0.18"><div class="light-blob light-blob-orange"></div></div>
					<div class="light-blob-parallax" data-parallax="0.08"><div class="light-blob light-blob-blue-2"></div></div>
				</div>
				<div class="container">
					<header class="section-header reveal reveal-scale">
						<span class="solution-2-label"><?php echo esc_html( $section['label'] ?? '' ); ?></span>
						<h2 class="section-title"><?php echo esc_html( $section['title'] ?? '' ); ?></h2>
					</header>
					<div class="user-steps-grid">
						<?php foreach ( $steps as $index => $step ) : ?>
							<div class="user-step glass-card reveal<?php echo $index > 0 ? ' reveal-delay-' . esc_attr( (string) $index ) : ''; ?>">
								<span class="user-step-number"><?php echo esc_html( $step['number'] ?? '' ); ?></span>
								<div class="user-step-icon<?php echo 1 === $index ? ' user-step-icon-accent' : ''; ?>">
									<?php echo $step['icon_svg'] ?? ''; ?>
								</div>
								<h3><?php echo esc_html( $step['title'] ?? '' ); ?></h3>
								<p><?php echo esc_html( $step['text'] ?? '' ); ?></p>
							</div>
							<?php if ( $index < count( $steps ) - 1 ) : ?>
								<div class="user-step-arrow reveal" aria-hidden="true">
									<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
			<?php
			leadwerk_theme_render_user_ticker( array( 'FASHION', 'GASTRO', 'CAFES', 'BOUTIQUEN', 'RESTAURANTS', 'BARS', 'CONCEPT STORES', 'LOKALE DEALS' ) );
			break;

		case 'app_preview':
			$slides = is_array( $section['slides'] ?? null ) ? $section['slides'] : array();
			?>
			<section class="section user-preview-section" id="app-preview">
				<div class="user-preview-arrow" aria-hidden="true">
					<img src="<?php echo esc_url( LEADWERK_THEME_URI . '/assets/images/Pfeil4.png' ); ?>" alt="" class="user-preview-arrow-img" data-parallax="0.12">
				</div>
				<div class="container">
					<header class="section-header reveal reveal-scale">
						<span class="solution-2-label"><?php echo esc_html( $section['label'] ?? '' ); ?></span>
						<h2 class="section-title"><?php echo esc_html( $section['title'] ?? '' ); ?></h2>
					</header>
					<div class="app-showcase reveal reveal-scale" id="app-showcase">
						<div class="app-showcase-tabs" role="tablist">
							<?php foreach ( $slides as $index => $slide ) : ?>
								<button type="button" class="app-showcase-tab<?php echo 0 === $index ? ' active' : ''; ?>" role="tab" aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>" data-slide="<?php echo (int) $index; ?>">
									<?php echo $slide['tab_icon_svg'] ?? ''; ?>
									<span><?php echo esc_html( $slide['tab_label'] ?? '' ); ?></span>
								</button>
							<?php endforeach; ?>
						</div>
						<div class="app-showcase-body">
							<div class="app-showcase-info">
								<?php foreach ( $slides as $index => $slide ) : ?>
									<div class="app-showcase-slide-info<?php echo 0 === $index ? ' active' : ''; ?>" data-info="<?php echo (int) $index; ?>">
										<h3><?php echo esc_html( $slide['info_title'] ?? '' ); ?></h3>
										<p><?php echo esc_html( $slide['info_text'] ?? '' ); ?></p>
									</div>
								<?php endforeach; ?>
							</div>
							<div class="app-showcase-phone">
								<div class="app-showcase-phone-frame">
									<div class="app-showcase-screen" id="app-showcase-screen">
										<?php foreach ( $slides as $index => $slide ) : ?>
											<?php $image_url = leadwerk_theme_resolve_acf_image_url( $slide['image'] ?? 0, 'full' ); ?>
											<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $slide['image_alt'] ?? '' ); ?>" class="app-showcase-img<?php echo 0 === $index ? ' active' : ''; ?>" data-screen="<?php echo (int) $index; ?>">
										<?php endforeach; ?>
									</div>
								</div>
							</div>
							<div class="app-showcase-dots" aria-hidden="true">
								<?php foreach ( $slides as $index => $slide ) : ?>
									<span class="app-showcase-dot<?php echo 0 === $index ? ' active' : ''; ?>" data-dot="<?php echo (int) $index; ?>"></span>
								<?php endforeach; ?>
							</div>
						</div>
						<p class="app-showcase-swipe-hint" aria-hidden="true">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
							<?php echo esc_html( $section['swipe_hint_text'] ?? 'Wischen oder klicken' ); ?>
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
						</p>
					</div>
				</div>
			</section>
			<?php
			break;

		case 'location':
			$city_tags = is_array( $section['city_tags'] ?? null ) ? $section['city_tags'] : array();
			$image_url = leadwerk_theme_resolve_acf_image_url( $section['image'] ?? 0, 'full' );
			?>
			<section class="section user-location-section" id="location">
				<div class="user-location-blob-bg" aria-hidden="true">
					<div class="light-blob-parallax" data-parallax="0.1"><div class="light-blob light-blob-blue"></div></div>
					<div class="light-blob-parallax" data-parallax="0.16"><div class="light-blob light-blob-orange"></div></div>
				</div>
				<div class="container">
					<div class="user-location-layout">
						<div class="user-location-content reveal">
							<span class="solution-2-label"><?php echo esc_html( $section['label'] ?? '' ); ?></span>
							<h2 class="section-title" style="text-align:left;"><?php echo esc_html( $section['title'] ?? '' ); ?></h2>
							<p class="user-location-text"><?php echo esc_html( $section['body_text'] ?? '' ); ?></p>
							<div class="user-location-mock glass-card" id="location-input-trigger">
								<div class="user-location-icon">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
								</div>
								<input type="text" class="user-location-input" id="location-city-input" placeholder="<?php echo esc_attr( $section['input_placeholder'] ?? '' ); ?>" autocomplete="off">
							</div>
							<div class="user-location-tags">
								<?php foreach ( $city_tags as $tag ) : ?>
									<?php $value = $tag['value'] ?? ''; ?>
									<button type="button" class="user-location-tag<?php echo '' === $value ? ' user-location-tag-more' : ''; ?>" data-city="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $tag['label'] ?? '' ); ?></button>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="user-location-visual reveal reveal-right">
							<div class="user-location-image-wrap">
								<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $section['image_alt'] ?? '' ); ?>" class="user-location-img">
							</div>
						</div>
					</div>
				</div>
			</section>
			<?php
			leadwerk_theme_render_user_ticker( array( 'FASHION', 'GASTRO', 'CAFES', 'BOUTIQUEN', 'RESTAURANTS', 'BARS', 'CONCEPT STORES', 'LOKALE DEALS' ) );
			?>
			<div class="loc-modal-overlay" id="loc-modal-overlay" aria-hidden="true">
				<div class="loc-modal glass-card" role="dialog" aria-labelledby="loc-modal-title">
					<button type="button" class="loc-modal-close" id="loc-modal-close" aria-label="Schliessen">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
					</button>
					<div class="loc-modal-icon-wrap">
						<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
					</div>
					<h3 class="loc-modal-title" id="loc-modal-title"><?php echo esc_html( $section['modal_title_prefix'] ?? '' ); ?> <span id="loc-modal-city"><?php echo esc_html( $section['modal_fallback_city'] ?? '' ); ?></span></h3>
					<p class="loc-modal-text"><?php echo esc_html( $section['modal_text'] ?? '' ); ?></p>
					<div class="loc-modal-badges">
						<?php leadwerk_theme_render_store_badges( $leadwerk_store_badges ); ?>
					</div>
					<p class="loc-modal-micro"><?php echo esc_html( $section['modal_micro'] ?? '' ); ?></p>
				</div>
			</div>
			<?php
			break;

		case 'categories':
			$items = is_array( $section['categories'] ?? null ) ? $section['categories'] : array();
			?>
			<section class="section user-categories-section" id="categories">
				<div class="container">
					<header class="section-header reveal reveal-scale">
						<span class="solution-2-label"><?php echo esc_html( $section['label'] ?? '' ); ?></span>
						<h2 class="section-title"><?php echo esc_html( $section['title'] ?? '' ); ?></h2>
					</header>
					<div class="user-categories-grid">
						<?php foreach ( $items as $index => $item ) : ?>
							<?php $image_url = leadwerk_theme_resolve_acf_image_url( $item['image'] ?? 0, 'full' ); ?>
							<div class="user-category-card reveal<?php echo $index > 0 ? ' reveal-delay-' . esc_attr( (string) $index ) : ''; ?>">
								<div class="user-category-img-wrap">
									<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $item['image_alt'] ?? '' ); ?>" class="user-category-img">
								</div>
								<div class="user-category-body">
									<div class="user-category-icon-wrap"><?php echo $item['icon_svg'] ?? ''; ?></div>
									<h3><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
									<p><?php echo esc_html( $item['text'] ?? '' ); ?></p>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
			<section class="parallax-image-section" aria-hidden="true">
				<div class="parallax-image-wrap"></div>
			</section>
			<?php
			break;

		case 'exclusive':
			$features = is_array( $section['features'] ?? null ) ? $section['features'] : array();
			?>
			<section class="section user-exclusive-section" id="exclusive">
				<div class="user-exclusive-blob-bg" aria-hidden="true">
					<div class="light-blob-parallax" data-parallax="0.12"><div class="light-blob light-blob-blue"></div></div>
					<div class="light-blob-parallax" data-parallax="0.15"><div class="light-blob light-blob-orange"></div></div>
					<div class="light-blob-parallax" data-parallax="0.1"><div class="light-blob light-blob-blue-2"></div></div>
				</div>
				<div class="container">
					<div class="user-exclusive-layout">
						<div class="user-exclusive-hero reveal">
							<span class="solution-2-label"><?php echo esc_html( $section['label'] ?? '' ); ?></span>
							<h2 class="user-exclusive-title"><?php echo wp_kses_post( nl2br( esc_html( $section['title'] ?? '' ) ) ); ?></h2>
							<p class="user-exclusive-lead"><?php echo esc_html( $section['lead'] ?? '' ); ?></p>
						</div>
						<div class="user-exclusive-features">
							<?php foreach ( $features as $index => $feature ) : ?>
								<div class="user-exclusive-feature glass-card reveal<?php echo $index > 0 ? ' reveal-delay-' . esc_attr( (string) $index ) : ''; ?>">
									<div class="user-exclusive-feature-icon<?php echo ! empty( $feature['accent'] ) ? ' user-exclusive-feature-icon-accent' : ''; ?>">
										<?php echo $feature['icon_svg'] ?? ''; ?>
									</div>
									<div>
										<h3><?php echo esc_html( $feature['title'] ?? '' ); ?></h3>
										<p><?php echo esc_html( $feature['text'] ?? '' ); ?></p>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</section>
			<?php
			break;

		case 'cta':
			?>
			<section class="section cta-section" id="download">
				<div class="cta-bg-wrap" aria-hidden="true">
					<div class="cta-bg-gradient"></div>
					<div class="cta-floating cta-floating-1"></div>
					<div class="cta-floating cta-floating-2"></div>
				</div>
				<div class="container cta-inner">
					<h2 class="cta-title reveal"><?php echo wp_kses_post( nl2br( esc_html( $section['title'] ?? '' ) ) ); ?></h2>
					<p class="cta-subtitle reveal"><?php echo esc_html( $section['subtitle'] ?? '' ); ?></p>
					<div class="user-cta-badges reveal">
						<?php leadwerk_theme_render_store_badges( $leadwerk_store_badges ); ?>
					</div>
					<p class="cta-micro reveal"><?php echo esc_html( $section['micro_text'] ?? '' ); ?></p>
				</div>
			</section>
			<?php
			break;
	}
}
