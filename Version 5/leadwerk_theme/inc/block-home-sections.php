<?php
/**
 * Flexible Content "home_sections" ausgeben.
 * Erwartet Variable $sections (Array der Layouts).
 *
 * @package Leadwerk_Theme
 */

if ( ! isset( $sections ) || ! is_array( $sections ) ) {
	return;
}

// Scroll Progress + Custom Cursor
echo '<div class="scroll-progress" id="scroll-progress" aria-hidden="true"></div>';
echo '<div class="custom-cursor" id="custom-cursor" aria-hidden="true"></div>';
echo '<div class="custom-cursor-shadow" id="custom-cursor-shadow" aria-hidden="true"></div>';

foreach ( $sections as $section ) {
	$layout = isset( $section['acf_fc_layout'] ) ? $section['acf_fc_layout'] : '';
	switch ( $layout ) {
		case 'hero':
			leadwerk_render_hero( $section );
			break;
		case 'why':
			leadwerk_render_why( $section );
			// Ticker after Why
			leadwerk_render_ticker();
			break;
		case 'app_steps':
			leadwerk_render_app_steps( $section );
			break;
		case 'pakete':
			leadwerk_render_pakete( $section );
			break;
		case 'solutions':
			leadwerk_render_solutions( $section );
			break;
		case 'faq':
			leadwerk_render_faq( $section );
			break;
		case 'cta':
			leadwerk_render_cta( $section );
			break;
	}
}

/* ══════════════════════════════════════════════════════════════════════
 * HERO
 * ══════════════════════════════════════════════════════════════════════ */
function leadwerk_render_hero( $f ) {
	$title_grad  = isset( $f['title_gradient'] ) ? $f['title_gradient'] : 'Rette&nbsp;deine Innenstadt.';
	$typewriter  = isset( $f['typewriter_words'] ) ? $f['typewriter_words'] : 'App herunterladen. Schnäppchen sichern.|Lokale Angebote in deiner Nähe.|Vor Ort einlösen. Direkt sparen.';
	$cta_text    = isset( $f['cta_text'] ) ? $f['cta_text'] : 'App herunterladen';
	if ( empty( $f['typewriter_words'] ) ) {
		$typewriter = 'App herunterladen. Schnäppchen sichern.|Lokale Angebote in deiner Nähe.|Vor Ort einlösen. Direkt sparen.';
	}
	$cta_url     = isset( $f['cta_url'] ) ? $f['cta_url'] : '/#download';
	$cta_url     = function_exists( 'leadwerk_theme_normalize_home_download_url' ) ? leadwerk_theme_normalize_home_download_url( $cta_url ) : $cta_url;
	$store_badges = function_exists( 'leadwerk_theme_get_store_badge_data' ) ? leadwerk_theme_get_store_badge_data() : array();
	$is_home_download_swap = '/#download' === $cta_url;
	$hero_cta_href = $is_home_download_swap ? '#' : $cta_url;
	$hero_img_id = isset( $f['hero_image'] ) ? (int) $f['hero_image'] : 0;
	$hero_img_url = $hero_img_id ? wp_get_attachment_image_url( $hero_img_id, 'full' ) : '';
	// Fallback: use theme asset if no attachment
	if ( ! $hero_img_url ) {
		$hero_img_url = LEADWERK_THEME_URI . '/assets/images/frau-freigestellt.png';
	}
	?>
	<div class="hero-why-bg">
		<div class="hero-bg-parallax" data-parallax="0.08" aria-hidden="true"></div>
		<div class="hero-frau-layer" data-parallax="0.24" aria-hidden="true">
			<img src="<?php echo esc_url( $hero_img_url ); ?>" alt="" class="hero-frau-img">
		</div>
	<section class="hero" id="hero">
		<div class="hero-bg-wrap" aria-hidden="true"></div>
		<div class="container hero-inner" id="hero-inner">
			<div class="hero-content reveal reveal-up">
				<h1 class="hero-title">
					<span class="text-gradient"><?php echo wp_kses_post( $title_grad ); ?></span>
					<span class="hero-title-solid hero-typewriter-wrap hero-outline-text" aria-live="polite" data-words="<?php echo esc_attr( $typewriter ); ?>">
						<span id="hero-typewriter-text"></span>
						<span class="hero-typewriter-cursor" id="hero-typewriter-cursor" aria-hidden="true">|</span>
					</span>
				</h1>
				<div class="hero-cta"<?php if ( ! empty( $store_badges ) ) : ?> data-home-download-swap-root="true" data-home-store-apple-url="<?php echo esc_url( $store_badges['apple_url'] ?? '' ); ?>" data-home-store-apple-badge="<?php echo esc_url( $store_badges['apple_badge'] ?? '' ); ?>" data-home-store-google-url="<?php echo esc_url( $store_badges['google_url'] ?? '' ); ?>" data-home-store-google-badge="<?php echo esc_url( $store_badges['google_badge'] ?? '' ); ?>"<?php endif; ?>>
					<a href="<?php echo esc_url( $hero_cta_href ); ?>" class="btn btn-primary btn-accent btn-lg"<?php if ( $is_home_download_swap ) : ?> data-home-download-swap-trigger="true"<?php endif; ?>><?php echo esc_html( $cta_text ); ?></a>
				</div>
			</div>
		</div>
	</section>
		<div class="hero-arrow-wrap" aria-hidden="true">
			<div class="hero-arrow-parallax">
				<img src="<?php echo esc_url( LEADWERK_THEME_URI . '/assets/images/pfeil-3.png' ); ?>" alt="" class="hero-arrow-img">
			</div>
		</div>
	</div><!-- /.hero-why-bg -->
	<?php
}

/* ══════════════════════════════════════════════════════════════════════
 * WHY
 * ══════════════════════════════════════════════════════════════════════ */
function leadwerk_render_why( $f ) {
	$label   = isset( $f['label'] ) ? $f['label'] : 'Warum U-like-it?';
	$title   = isset( $f['title'] ) ? $f['title'] : 'Weil lokal einfach besser ist.';
	$cards   = isset( $f['cards'] ) && is_array( $f['cards'] ) ? $f['cards'] : array();
	$bag_id  = isset( $f['bag_image'] ) ? (int) $f['bag_image'] : 0;
	$bag_url = $bag_id ? wp_get_attachment_image_url( $bag_id, 'full' ) : '';
	if ( ! $bag_url ) {
		$bag_url = LEADWERK_THEME_URI . '/assets/images/hand.png';
	}
	?>
	<section class="section why-section" id="why">
		<div class="container">
			<header class="section-header why-header reveal reveal-scale">
				<span class="why-label"><?php echo esc_html( $label ); ?></span>
				<h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
			</header>
			<div class="why-content reveal reveal-scale">
				<div class="why-floating-items">
					<?php foreach ( $cards as $i => $card ) :
						$n = $i + 1;
						$icon_svg = isset( $card['icon_svg'] ) ? $card['icon_svg'] : '';
						$text     = isset( $card['text'] ) ? $card['text'] : '';
						$detail   = isset( $card['detail'] ) ? $card['detail'] : '';
					?>
					<div class="why-float-card why-float-<?php echo (int) $n; ?> glass-card">
						<span class="why-item-icon" aria-hidden="true"><?php echo $icon_svg; ?></span>
						<div class="why-item-body">
							<p class="why-item-text"><?php echo esc_html( $text ); ?></p>
							<?php if ( $detail ) : ?>
							<details class="why-item-details">
								<summary class="why-item-more">Mehr erfahren</summary>
								<p class="why-item-detail"><?php echo esc_html( $detail ); ?></p>
							</details>
							<?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
				<div class="why-bag-wrap">
					<div class="why-bag">
							<img src="<?php echo esc_url( $bag_url ); ?>" alt="" class="why-bag-img" width="240" height="280">
					</div>
				</div>
			</div>
		</div>
	</section>
	<?php
}

/* ══════════════════════════════════════════════════════════════════════
 * TICKER
 * ══════════════════════════════════════════════════════════════════════ */
function leadwerk_render_ticker() {
	$items = array( 'MODE', 'GASTRO', 'CAFÉS', 'BOUTIQUEN', 'RESTAURANTS', 'BARS', 'KONZEPTLÄDEN', 'LOKALE ANGEBOTE', 'MITTAGSANGEBOTE', 'FEIERABEND', 'EINKAUFEN', 'STADTLEBEN' );
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

/* ══════════════════════════════════════════════════════════════════════
 * APP STEPS
 * ══════════════════════════════════════════════════════════════════════ */
function leadwerk_render_app_steps( $f ) {
	$headline = isset( $f['headline'] ) ? $f['headline'] : 'So funktioniert die App';
	$steps    = isset( $f['steps'] ) && is_array( $f['steps'] ) ? $f['steps'] : array();
	$first_img_url = '';
	if ( ! empty( $steps ) ) {
		$first_top = isset( $steps[0]['image_top'] ) ? (int) $steps[0]['image_top'] : 0;
		if ( $first_top ) {
			$first_img_url = wp_get_attachment_image_url( $first_top, 'full' );
		}
	}
	?>
	<div class="app-pakete-wrap">
		<div class="app-pakete-blob-bg" aria-hidden="true">
			<div class="light-blob-parallax" data-parallax="0.12"><div class="light-blob light-blob-blue"></div></div>
			<div class="light-blob-parallax" data-parallax="0.18"><div class="light-blob light-blob-orange"></div></div>
			<div class="light-blob-parallax" data-parallax="0.08"><div class="light-blob light-blob-blue-2"></div></div>
			<div class="light-blob-parallax" data-parallax="0.15"><div class="light-blob light-blob-blue-3"></div></div>
			<div class="light-blob-parallax" data-parallax="0.2"><div class="light-blob light-blob-orange-2"></div></div>
			<div class="light-blob-parallax" data-parallax="0.1"><div class="light-blob light-blob-orange-3"></div></div>
			<div class="light-blob-parallax" data-parallax="0.14"><div class="light-blob light-blob-blue-4"></div></div>
		</div>
	<section class="section app-steps-section" id="app-steps">
		<div class="container app-steps-container">
			<div class="app-steps-layout">
				<div class="app-steps-content">
					<h2 class="app-steps-headline"><?php echo esc_html( $headline ); ?></h2>
					<div class="app-steps-slider">
						<div class="app-steps-slides">
							<?php foreach ( $steps as $i => $step ) :
								$num  = isset( $step['number'] ) ? $step['number'] : 'Schritt ' . ( $i + 1 );
								$text = isset( $step['text'] ) ? $step['text'] : '';
								$img_top_id = isset( $step['image_top'] ) ? (int) $step['image_top'] : 0;
								$img_bot_id = isset( $step['image_bottom'] ) ? (int) $step['image_bottom'] : 0;
								$img_top_url = $img_top_id ? wp_get_attachment_image_url( $img_top_id, 'full' ) : '';
								$img_bot_url = $img_bot_id ? wp_get_attachment_image_url( $img_bot_id, 'full' ) : '';
							?>
							<div class="app-step-slide <?php echo $i === 0 ? 'active' : ''; ?>" data-step="<?php echo (int) $i; ?>" data-img-top="<?php echo esc_url( $img_top_url ); ?>" data-img-bottom="<?php echo esc_url( $img_bot_url ); ?>">
								<span class="app-step-number"><?php echo esc_html( $num ); ?></span>
								<p class="app-step-text"><?php echo esc_html( $text ); ?></p>
							</div>
							<?php endforeach; ?>
						</div>
						<div class="app-steps-nav" role="tablist" aria-label="Schritte">
							<button type="button" class="app-steps-btn app-steps-prev" aria-label="Vorheriger Schritt" id="app-steps-prev">
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
							</button>
							<div class="app-steps-dots" id="app-steps-dots">
								<?php foreach ( $steps as $i => $s ) : ?>
									<button type="button" class="app-steps-dot <?php echo $i === 0 ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>" aria-label="Schritt <?php echo (int) $i + 1; ?>" data-index="<?php echo (int) $i; ?>"></button>
								<?php endforeach; ?>
							</div>
							<button type="button" class="app-steps-btn app-steps-next" aria-label="Nächster Schritt" id="app-steps-next">
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
							</button>
						</div>
					</div>
				</div>
				<div class="app-steps-images">
					<div class="app-steps-image-wrap">
						<?php if ( $first_img_url ) : ?>
							<img src="<?php echo esc_url( $first_img_url ); ?>" alt="" class="app-step-img" id="app-step-img-1" width="280" height="280">
						<?php endif; ?>
					</div>
					<div class="app-steps-badge" id="app-steps-badge" aria-hidden="true">
						<svg class="app-steps-badge-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
					</div>
				</div>
			</div>
		</div>
	</section>
	<?php
}

/* ══════════════════════════════════════════════════════════════════════
 * PAKETE
 * ══════════════════════════════════════════════════════════════════════ */
function leadwerk_render_pakete( $f ) {
	$label    = isset( $f['label'] ) ? $f['label'] : 'Das sind';
	$title    = isset( $f['title'] ) ? $f['title'] : 'U-like-it Pakete';
	$content  = isset( $f['content'] ) ? $f['content'] : '';
	$cta_text = isset( $f['cta_text'] ) ? $f['cta_text'] : 'Mehr erfahren';
	$cta_url  = isset( $f['cta_url'] ) ? $f['cta_url'] : '/#download';
	$cta_url  = function_exists( 'leadwerk_theme_normalize_home_download_url' ) ? leadwerk_theme_normalize_home_download_url( $cta_url ) : $cta_url;
	$img_id   = isset( $f['image'] ) ? (int) $f['image'] : 0;
	$img_url  = $img_id ? wp_get_attachment_image_url( $img_id, 'full' ) : '';
	if ( ! $img_url ) {
		$img_url = LEADWERK_THEME_URI . '/assets/images/bag.png';
	}
	?>
	<div class="app-pakete-arrow-wrap" aria-hidden="true">
		<div class="app-pakete-arrow-parallax">
			<img src="<?php echo esc_url( LEADWERK_THEME_URI . '/assets/images/pfeil-1.png' ); ?>" alt="" class="app-pakete-arrow-img">
		</div>
	</div>
	<section class="section pakete-section" id="pakete">
		<div class="container pakete-container">
			<div class="pakete-layout">
				<div class="pakete-image reveal">
					<img src="<?php echo esc_url( $img_url ); ?>" alt="U-like-it Tasche" class="pakete-img">
				</div>
				<div class="pakete-content reveal reveal-scale">
					<span class="pakete-label"><?php echo esc_html( $label ); ?></span>
					<h2 class="pakete-title"><?php echo esc_html( $title ); ?></h2>
					<div class="pakete-text"><?php echo wp_kses_post( $content ); ?></div>
					<a href="<?php echo esc_url( $cta_url ); ?>" class="solution-card-link pakete-cta"><?php echo esc_html( $cta_text ); ?></a>
				</div>
			</div>
		</div>
	</section>
	</div><!-- /.app-pakete-wrap -->

	<!-- Parallax Image Section -->
	<section class="parallax-image-section" aria-hidden="true">
		<div class="parallax-image-wrap"></div>
	</section>

	<!-- Ticker -->
	<?php leadwerk_render_ticker(); ?>
	<?php
}

/* ══════════════════════════════════════════════════════════════════════
 * SOLUTIONS
 * ══════════════════════════════════════════════════════════════════════ */
function leadwerk_render_solutions( $f ) {
	$label           = isset( $f['label'] ) ? $f['label'] : 'Für Händler & Gastronomie';
	$title           = isset( $f['title'] ) ? $f['title'] : 'Unsere Lösungen für Unternehmen';
	$intro           = isset( $f['intro_text'] ) ? $f['intro_text'] : '';
	$reg_btn_text    = isset( $f['register_btn_text'] ) ? $f['register_btn_text'] : 'Unternehmen registrieren';
	$reg_btn_url     = isset( $f['register_btn_url'] ) ? $f['register_btn_url'] : '/fuer-haendler/#onboarding';
	$reg_btn_url     = function_exists( 'leadwerk_theme_normalize_home_registration_url' ) ? leadwerk_theme_normalize_home_registration_url( $reg_btn_url ) : $reg_btn_url;
	$cards           = isset( $f['cards'] ) && is_array( $f['cards'] ) ? $f['cards'] : array();
	?>
	<div class="light-sections-wrap">
		<div class="solution-arrow-between-wrap" aria-hidden="true">
			<div class="solution-arrow-between-parallax">
				<img src="<?php echo esc_url( LEADWERK_THEME_URI . '/assets/images/pfeil-2.png' ); ?>" alt="" class="solution-arrow-between-img">
			</div>
		</div>
		<div class="light-sections-blob-bg" aria-hidden="true">
			<div class="light-blob-parallax" data-parallax="0.12"><div class="light-blob light-blob-blue"></div></div>
			<div class="light-blob-parallax" data-parallax="0.18"><div class="light-blob light-blob-orange"></div></div>
			<div class="light-blob-parallax" data-parallax="0.08"><div class="light-blob light-blob-blue-2"></div></div>
			<div class="light-blob-parallax" data-parallax="0.15"><div class="light-blob light-blob-blue-3"></div></div>
			<div class="light-blob-parallax" data-parallax="0.2"><div class="light-blob light-blob-orange-2"></div></div>
			<div class="light-blob-parallax" data-parallax="0.1"><div class="light-blob light-blob-orange-3"></div></div>
			<div class="light-blob-parallax" data-parallax="0.14"><div class="light-blob light-blob-blue-4"></div></div>
		</div>
	<section class="section solution-section" id="solution-2">
		<div class="container">
			<div class="solution-layout">
				<div class="solution-intro reveal">
					<span class="solution-2-label"><?php echo esc_html( $label ); ?></span>
					<h2 class="solution-title solution-title-black"><?php echo esc_html( $title ); ?></h2>
					<div class="solution-2-text"><?php echo wp_kses_post( $intro ); ?></div>
					<a href="<?php echo esc_url( $reg_btn_url ); ?>" class="btn btn-primary"><?php echo esc_html( $reg_btn_text ); ?></a>
				</div>
				<div class="solution-cards">
					<?php foreach ( $cards as $i => $card ) :
						$icon  = isset( $card['icon_svg'] ) ? $card['icon_svg'] : '';
						$ctit  = isset( $card['title'] ) ? $card['title'] : '';
						$cdesc = isset( $card['description'] ) ? $card['description'] : '';
						$clink = isset( $card['link_text'] ) ? $card['link_text'] : 'Mehr erfahren';
						$curl  = isset( $card['link_url'] ) ? $card['link_url'] : '#app-steps';
						$delay = $i > 0 ? ' reveal-delay-' . $i : '';
					?>
					<article class="solution-card reveal<?php echo esc_attr( $delay ); ?>">
						<div class="solution-card-header">
							<div class="solution-card-icon solution-icon-blue"><?php echo $icon; ?></div>
							<h3><?php echo esc_html( $ctit ); ?></h3>
						</div>
						<p class="solution-card-desc"><?php echo esc_html( $cdesc ); ?></p>
						<a href="<?php echo esc_url( $curl ); ?>" class="solution-card-link"><?php echo esc_html( $clink ); ?></a>
					</article>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>
	<?php
}

/* ══════════════════════════════════════════════════════════════════════
 * FAQ
 * ══════════════════════════════════════════════════════════════════════ */
function leadwerk_render_faq( $f ) {
	$label   = isset( $f['label'] ) ? $f['label'] : 'FAQ';
	$title   = isset( $f['title'] ) ? $f['title'] : 'Alles, was du wissen musst';
	$items   = isset( $f['items'] ) && is_array( $f['items'] ) ? $f['items'] : array();
	$img_id  = isset( $f['image'] ) ? (int) $f['image'] : 0;
	$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'full' ) : '';
	if ( ! $img_url ) {
		$img_url = LEADWERK_THEME_URI . '/assets/images/shop.jpg';
	}
	?>
	<section class="section solution-faq-section" id="solution-faq">
		<div class="container">
			<header class="section-header solution-faq-header reveal">
				<span class="solution-2-label"><?php echo esc_html( $label ); ?></span>
				<h2 class="section-title solution-title-black"><?php echo esc_html( $title ); ?></h2>
			</header>
			<div class="solution-faq-layout">
				<div class="solution-faq-list-wrap">
					<div class="faq-list solution-faq-list">
						<?php foreach ( $items as $faq ) :
							$q = isset( $faq['question'] ) ? $faq['question'] : '';
							$a = isset( $faq['answer'] ) ? $faq['answer'] : '';
						?>
						<details class="faq-item glass-card reveal">
							<summary class="faq-question">
								<span><?php echo esc_html( $q ); ?></span>
								<svg class="faq-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
							</summary>
							<div class="faq-answer">
								<p><?php echo esc_html( $a ); ?></p>
							</div>
						</details>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="solution-faq-visual">
					<div class="solution-faq-image-wrap">
						<img src="<?php echo esc_url( $img_url ); ?>" alt="" class="solution-faq-img" width="400" height="400">
					</div>
					<div class="solution-faq-badge" aria-hidden="true">
						<svg class="solution-faq-badge-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
					</div>
				</div>
			</div>
		</div>
	</section>
	</div><!-- /.light-sections-wrap -->
	<?php
}

/* ══════════════════════════════════════════════════════════════════════
 * CTA
 * ══════════════════════════════════════════════════════════════════════ */
function leadwerk_render_cta( $f ) {
	$title   = isset( $f['title'] ) ? $f['title'] : "App herunterladen\nund Schnäppchen sichern.";
	$btn1_t  = isset( $f['button_1_text'] ) ? $f['button_1_text'] : 'Unternehmen registrieren';
	$btn1_u  = isset( $f['button_1_url'] ) ? $f['button_1_url'] : '/fuer-haendler/#onboarding';
	$btn2_t  = isset( $f['button_2_text'] ) ? $f['button_2_text'] : 'App herunterladen';
	$btn2_u  = isset( $f['button_2_url'] ) ? $f['button_2_url'] : '/#download';
	$btn1_u  = function_exists( 'leadwerk_theme_normalize_home_registration_url' ) ? leadwerk_theme_normalize_home_registration_url( $btn1_u ) : $btn1_u;
	$btn2_u  = function_exists( 'leadwerk_theme_normalize_home_download_url' ) ? leadwerk_theme_normalize_home_download_url( $btn2_u ) : $btn2_u;
	?>
	<section class="section cta-section" id="download">
		<div class="cta-bg-wrap" aria-hidden="true">
			<div class="cta-bg-gradient"></div>
			<div class="cta-floating cta-floating-1"></div>
			<div class="cta-floating cta-floating-2"></div>
		</div>
		<div class="container cta-inner">
			<h2 class="cta-title reveal"><?php echo wp_kses_post( nl2br( esc_html( $title ) ) ); ?></h2>
			<div class="cta-buttons-row reveal">
				<a href="<?php echo esc_url( $btn1_u ); ?>" class="btn btn-white btn-lg"><?php echo esc_html( $btn1_t ); ?></a>
				<a href="<?php echo esc_url( $btn2_u ); ?>" class="btn btn-accent-solid btn-lg"><?php echo esc_html( $btn2_t ); ?></a>
			</div>
		</div>
	</section>
	<?php
}
