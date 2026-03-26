<?php
/**
 * Render haendler page sections.
 *
 * @package Leadwerk_Theme
 */

if ( ! isset( $sections ) || ! is_array( $sections ) ) {
	return;
}

echo '<div class="scroll-progress" id="scroll-progress" aria-hidden="true"></div>';
echo '<div class="custom-cursor" id="custom-cursor" aria-hidden="true"></div>';
echo '<div class="custom-cursor-shadow" id="custom-cursor-shadow" aria-hidden="true"></div>';

if ( ! function_exists( 'leadwerk_theme_render_generic_ticker' ) ) {
	function leadwerk_theme_render_generic_ticker( $items ) {
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

$haendler_bundle_open    = false;
$haendler_bundle_layouts = array( 'use_cases', 'conditions', 'onboarding' );

$section_count = count( $sections );

foreach ( $sections as $index => $section ) {
	$layout = $section['acf_fc_layout'] ?? '';

	if ( in_array( $layout, $haendler_bundle_layouts, true ) && ! $haendler_bundle_open ) {
		$haendler_bundle_open = true;
		?>
		<div class="app-pakete-wrap">
			<div class="app-pakete-blob-bg" aria-hidden="true">
				<div class="light-blob-parallax" data-parallax="0.1"><div class="light-blob light-blob-blue"></div></div>
				<div class="light-blob-parallax" data-parallax="0.16"><div class="light-blob light-blob-orange"></div></div>
				<div class="light-blob-parallax" data-parallax="0.08"><div class="light-blob light-blob-blue-2"></div></div>
			</div>
		<?php
	}

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
							<div class="hero-cta haendler-hero-cta">
								<a href="<?php echo esc_url( $section['primary_button_url'] ?? '#onboarding' ); ?>" class="btn btn-accent-solid btn-lg"><?php echo esc_html( $section['primary_button_text'] ?? '' ); ?></a>
								<a href="<?php echo esc_url( $section['secondary_button_url'] ?? '#vorteile' ); ?>" class="btn btn-white btn-lg"><?php echo esc_html( $section['secondary_button_text'] ?? '' ); ?></a>
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

		case 'benefits':
			$features = is_array( $section['features'] ?? null ) ? $section['features'] : array();
			?>
			<section class="section user-exclusive-section" id="vorteile">
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
			leadwerk_theme_render_generic_ticker( array( 'BOUTIQUEN', 'FASHION', 'CONCEPT STORES', 'ACCESSOIRES', 'LOKALE HAENDLER', 'SCHUHE', 'SCHMUCK', 'INTERIOR' ) );
			break;

		case 'how_it_works':
			$steps = is_array( $section['steps'] ?? null ) ? $section['steps'] : array();
			?>
			<section class="section user-how-section" id="so-gehts">
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
								<div class="user-step-icon<?php echo 1 === $index ? ' user-step-icon-accent' : ''; ?>"><?php echo $step['icon_svg'] ?? ''; ?></div>
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
			break;

		case 'dashboard_preview':
			$slides = is_array( $section['slides'] ?? null ) ? $section['slides'] : array();
			?>
			<section class="section user-preview-section" id="dashboard">
				<div class="user-preview-arrow" aria-hidden="true">
					<img src="<?php echo esc_url( LEADWERK_THEME_URI . '/assets/images/Pfeil4.png' ); ?>" alt="" class="user-preview-arrow-img" data-parallax="0.12">
				</div>
				<div class="container">
					<header class="section-header reveal reveal-scale">
						<span class="solution-2-label"><?php echo esc_html( $section['label'] ?? '' ); ?></span>
						<h2 class="section-title"><?php echo esc_html( $section['title'] ?? '' ); ?></h2>
					</header>
					<div class="app-showcase reveal reveal-scale" id="haendler-showcase">
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
									<div class="app-showcase-screen" id="haendler-showcase-screen">
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
			<section class="parallax-image-section" aria-hidden="true">
				<div class="parallax-image-wrap"></div>
			</section>
			<?php
			leadwerk_theme_render_generic_ticker( array( 'MEHR FREQUENZ', 'LOKALE DEALS', 'MESSBAR', 'KEIN STREUVERLUST', 'QR-EINLOESUNG', 'PLANBAR', 'EINFACH', 'LOKAL' ) );
			break;

		case 'use_cases':
			$cases     = is_array( $section['cases'] ?? null ) ? $section['cases'] : array();
			$image_url = leadwerk_theme_resolve_acf_image_url( $section['image'] ?? 0, 'full' );
			?>
			<section class="section user-location-section" id="beispiele">
				<div class="container">
					<div class="user-location-layout">
						<div class="user-location-content reveal">
							<span class="solution-2-label"><?php echo esc_html( $section['label'] ?? '' ); ?></span>
							<h2 class="section-title" style="text-align:left;"><?php echo esc_html( $section['title'] ?? '' ); ?></h2>
							<div class="haendler-cases-list">
								<?php foreach ( $cases as $case ) : ?>
									<div class="haendler-case-item">
										<div class="haendler-case-icon-wrap"><?php echo $case['icon_svg'] ?? ''; ?></div>
										<div>
											<span class="haendler-case-label"><?php echo esc_html( $case['label'] ?? '' ); ?></span>
											<p class="haendler-case-quote"><?php echo esc_html( $case['quote'] ?? '' ); ?></p>
											<p class="haendler-case-result"><?php echo esc_html( $case['result'] ?? '' ); ?></p>
										</div>
									</div>
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
			break;

		case 'conditions':
			$list = is_array( $section['conditions'] ?? null ) ? $section['conditions'] : array();
			?>
			<section class="section pakete-section haendler-pakete-centered" id="konditionen">
				<div class="container">
					<div class="haendler-pakete-center-wrap reveal reveal-scale">
						<span class="pakete-label"><?php echo esc_html( $section['label'] ?? '' ); ?></span>
						<h2 class="pakete-title"><?php echo esc_html( $section['title'] ?? '' ); ?></h2>
						<div class="pakete-text"><?php echo wp_kses_post( $section['content'] ?? '' ); ?></div>
						<ul class="haendler-conditions-grid">
							<?php foreach ( $list as $item ) : ?>
								<li>
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
									<?php echo esc_html( $item['text'] ?? '' ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
						<a href="<?php echo esc_url( $section['cta_url'] ?? '#onboarding' ); ?>" class="btn btn-primary" style="margin-top:32px;"><?php echo esc_html( $section['cta_text'] ?? '' ); ?></a>
					</div>
				</div>
			</section>
			<?php
			break;

		case 'onboarding':
			$form_fields = is_array( $section['form_fields'] ?? null ) ? $section['form_fields'] : array();
			?>
			<section class="section haendler-onboarding-section" id="onboarding">
				<div class="haendler-onboarding-arrow" aria-hidden="true">
					<img src="<?php echo esc_url( LEADWERK_THEME_URI . '/assets/images/pfeil-1.png' ); ?>" alt="" class="haendler-onboarding-arrow-img">
				</div>
				<div class="container">
					<div class="user-location-layout">
						<div class="user-location-content reveal">
							<span class="solution-2-label"><?php echo esc_html( $section['label'] ?? '' ); ?></span>
							<h2 class="section-title" style="text-align:left;"><?php echo esc_html( $section['title'] ?? '' ); ?></h2>
							<p class="user-location-text"><?php echo esc_html( $section['body_text'] ?? '' ); ?></p>
							<div class="haendler-pos-banner glass-card">
								<div class="haendler-pos-icon-wrap">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><rect x="7" y="7" width="3" height="9"></rect><rect x="14" y="7" width="3" height="5"></rect></svg>
								</div>
								<div>
									<p class="haendler-pos-text"><strong><?php echo esc_html( $section['pos_title'] ?? '' ); ?></strong> <?php echo esc_html( $section['pos_text'] ?? '' ); ?></p>
								</div>
							</div>
						</div>
						<div class="haendler-form-wrap reveal reveal-right">
							<form class="haendler-form glass-card" onsubmit="return false;" id="haendler-form">
								<?php foreach ( $form_fields as $field ) : ?>
									<div class="haendler-form-group">
										<label for="<?php echo esc_attr( $field['field_id'] ?? '' ); ?>"><?php echo esc_html( $field['label'] ?? '' ); ?></label>
										<?php if ( 'select' === ( $field['field_type'] ?? '' ) ) : ?>
											<select id="<?php echo esc_attr( $field['field_id'] ?? '' ); ?>"<?php echo ! empty( $field['required'] ) ? ' required' : ''; ?>>
												<?php if ( ! empty( $field['placeholder'] ) ) : ?>
													<option value="" disabled selected><?php echo esc_html( $field['placeholder'] ); ?></option>
												<?php endif; ?>
												<?php foreach ( $field['options'] ?? array() as $option ) : ?>
													<option value="<?php echo esc_attr( sanitize_title( $option ) ); ?>"><?php echo esc_html( $option ); ?></option>
												<?php endforeach; ?>
											</select>
										<?php else : ?>
											<input type="<?php echo esc_attr( $field['field_type'] ?? 'text' ); ?>" id="<?php echo esc_attr( $field['field_id'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"<?php echo ! empty( $field['required'] ) ? ' required' : ''; ?>>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
								<button type="submit" class="btn btn-accent-solid btn-lg haendler-form-submit"><?php echo esc_html( $section['submit_text'] ?? '' ); ?></button>
								<p class="haendler-form-micro"><?php echo esc_html( $section['micro_text'] ?? '' ); ?></p>
							</form>
						</div>
					</div>
				</div>
			</section>
			<?php
			break;

		case 'faq':
			$items     = is_array( $section['faq_items'] ?? null ) ? $section['faq_items'] : array();
			$image_url = leadwerk_theme_resolve_acf_image_url( $section['image'] ?? 0, 'full' );
			?>
			<div class="light-sections-wrap haendler-faq-wrap">
				<div class="light-sections-blob-bg" aria-hidden="true">
					<div class="light-blob-parallax" data-parallax="0.12"><div class="light-blob light-blob-blue"></div></div>
					<div class="light-blob-parallax" data-parallax="0.18"><div class="light-blob light-blob-orange"></div></div>
					<div class="light-blob-parallax" data-parallax="0.08"><div class="light-blob light-blob-blue-2"></div></div>
				</div>
				<section class="section solution-faq-section" id="faq">
					<div class="container">
						<header class="section-header solution-faq-header reveal">
							<span class="solution-2-label"><?php echo esc_html( $section['label'] ?? '' ); ?></span>
							<h2 class="section-title solution-title-black"><?php echo esc_html( $section['title'] ?? '' ); ?></h2>
						</header>
						<div class="solution-faq-layout">
							<div class="solution-faq-list-wrap">
								<div class="faq-list solution-faq-list">
									<?php foreach ( $items as $item ) : ?>
										<details class="faq-item glass-card reveal">
											<summary class="faq-question">
												<span><?php echo esc_html( $item['question'] ?? '' ); ?></span>
												<svg class="faq-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
											</summary>
											<div class="faq-answer">
												<p><?php echo esc_html( $item['answer'] ?? '' ); ?></p>
											</div>
										</details>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="solution-faq-visual">
								<div class="solution-faq-image-wrap">
									<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $section['image_alt'] ?? '' ); ?>" class="solution-faq-img" width="400" height="400">
								</div>
								<div class="solution-faq-badge" aria-hidden="true">
									<svg class="solution-faq-badge-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
								</div>
							</div>
						</div>
					</div>
				</section>
			</div>
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
					<div class="cta-buttons-row reveal">
						<a href="<?php echo esc_url( $section['button_1_url'] ?? '#onboarding' ); ?>" class="btn btn-white btn-lg"><?php echo esc_html( $section['button_1_text'] ?? '' ); ?></a>
						<a href="<?php echo esc_url( $section['button_2_url'] ?? '/fuer-nutzer/' ); ?>" class="btn btn-accent-solid btn-lg"><?php echo esc_html( $section['button_2_text'] ?? '' ); ?></a>
					</div>
				</div>
			</section>
			<?php
			break;
	}

	$next_layout = '';
	if ( $index + 1 < $section_count && ! empty( $sections[ $index + 1 ]['acf_fc_layout'] ) ) {
		$next_layout = $sections[ $index + 1 ]['acf_fc_layout'];
	}

	if ( $haendler_bundle_open && in_array( $layout, $haendler_bundle_layouts, true ) && ! in_array( $next_layout, $haendler_bundle_layouts, true ) ) {
		echo '</div>';
		$haendler_bundle_open = false;
	}
}

if ( $haendler_bundle_open ) {
	echo '</div>';
}
