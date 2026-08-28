<?php
/**
 * Page d'accueil WEAS : assemblage éditorial fixe.
 */
get_header();

$cfg          = class_exists( '\Seoflix\Homepage' ) ? \Seoflix\Homepage::get_config() : \Seoflix\Homepage::defaults();
$hero         = $cfg['hero'];
$blocks       = $cfg['fixed_blocks'];
$path_catalog = \Seoflix\Homepage::path_definitions();
$path_names   = array_column( $path_catalog, 'name', 'slug' );
$default_title = \Seoflix\Homepage::defaults()['hero']['title'] ?: 'Apprends l’affiliation sans perdre des heures sur YouTube.';
$rotate_hero   = in_array( $hero['title'], [ $default_title, \Seoflix\Homepage::LEGACY_HERO_TITLE ], true );
$hero_labels   = array_values( array_filter( array_column( $path_catalog, 'hero_label' ) ) );

$get_path_videos = static function ( int $term_id, int $limit = -1 ): array {
	$ordered_ids = \Seoflix\Path_Order::ordered_video_ids_for_term( $term_id );
	if ( ! $ordered_ids ) {
		return [];
	}
	return get_posts( [
		'post_type'      => 'seoflix_video',
		'post_status'    => 'publish',
		'posts_per_page'     => $limit,
		'seoflix_focus_apply' => 1,
		'post__in'            => $ordered_ids,
		'orderby'        => 'post__in',
	] );
};

$render_home_newsletter = static function (): void {
	seoflix_render_newsletter( 'homepage', [ 'compact' => true ] );
};
?>

<div class="sx-home">
	<section class="sx-home-hero" aria-labelledby="madias-home-title">
		<div class="sx-home-hero__media" aria-hidden="true">
			<video class="sx-home-hero__video" autoplay muted loop playsinline preload="metadata" aria-hidden="true">
				<source src="<?php echo esc_url( get_theme_file_uri( 'assets/video/weas-cerveau-neural-loop.mp4' ) ); ?>" type="video/mp4">
			</video>
		</div>
		<div class="sx-container sx-home-hero__inner">
			<h1 id="madias-home-title" class="sx-home-hero__title">
				<?php if ( $rotate_hero && $hero_labels ) : ?>
					<span class="screen-reader-text">Apprends gratuitement l’affiliation SEO, YouTube, la vente de liens, l’IA et l’automatisation, la vente de leads et le freelancing.</span>
					<span class="sx-home-hero__lead" aria-hidden="true">Apprends</span>
					<span class="sx-home-hero__rotator" aria-hidden="true">
						<?php foreach ( $hero_labels as $hero_label_index => $hero_label ) :
							$hero_term_class = 'sx-home-hero__term';
							if ( 0 === $hero_label_index ) {
								$hero_term_class .= ' is-active';
							}
							if ( 3 === $hero_label_index ) {
								$hero_term_class .= ' sx-home-hero__term--long';
							} elseif ( in_array( $hero_label_index, [ 1, 2, 4, 5 ], true ) ) {
								$hero_term_class .= ' sx-home-hero__term--medium';
							}
							?>
							<span class="<?php echo esc_attr( $hero_term_class ); ?>"><?php echo esc_html( $hero_label ); ?></span>
						<?php endforeach; ?>
					</span>
					<span class="sx-home-hero__tail" aria-hidden="true">gratuitement.</span>
				<?php else : ?>
					<?php echo esc_html( $hero['title'] ?: $default_title ); ?>
				<?php endif; ?>
			</h1>
			<?php if ( $hero['subtitle'] ) : ?>
				<p class="sx-home-hero__subtitle"><?php echo esc_html( $hero['subtitle'] ); ?></p>
			<?php endif; ?>
			<a class="sx-home-hero__cta" href="<?php echo esc_url( home_url( '/commencer/' ) ); ?>"><?php echo esc_html( $hero['cta_text'] ?: 'Commencer à apprendre' ); ?></a>
		</div>
		<button class="sx-home-hero__motion" type="button" aria-pressed="false">Pause animation</button>
	</section>

	<div class="sx-container sx-page sx-home__content">
		<?php if ( ! empty( $blocks['paths'] ) ) : ?>
			<section id="parcours" class="sx-home-section sx-home-paths" aria-labelledby="home-paths-title">
				<header class="sx-home-section__header">
					<h2 id="home-paths-title">Six business en ligne à apprendre gratuitement</h2>
				</header>
				<div class="sx-home-paths__grid">
					<?php foreach ( $path_catalog as $definition ) :
						$term = get_term_by( 'slug', $definition['slug'], 'seoflix_path' );
						if ( $term && ! is_wp_error( $term ) ) :
							$path_videos = $get_path_videos( (int) $term->term_id );
							$count       = count( $path_videos );
							$description = trim( wp_strip_all_tags( $term->description ) ) ?: $definition['description'];
							?>
							<a class="sx-path-card" href="<?php echo esc_url( get_term_link( $term ) ); ?>" aria-label="<?php echo esc_attr( 'Ouvrir le parcours ' . $definition['name'] ); ?>">
								<span class="sx-path-card__icon" aria-hidden="true"><?php echo esc_html( $definition['icon'] ); ?></span>
								<span class="sx-path-card__body">
									<strong class="sx-path-card__title"><?php echo esc_html( $definition['name'] ); ?></strong>
									<span class="sx-path-card__description"><?php echo esc_html( $description ); ?></span>
									<span class="sx-path-card__meta"><?php echo esc_html( sprintf( _n( '%d vidéo publiée', '%d vidéos publiées', $count, 'seoflix' ), $count ) ); ?></span>
									<span class="sx-path-card__progress" aria-hidden="true"><span></span><span></span><span></span></span>
								</span>
							</a>
						<?php else : ?>
							<article class="sx-path-card sx-path-card--unavailable" aria-label="<?php echo esc_attr( $definition['name'] . ' indisponible' ); ?>">
								<span class="sx-path-card__icon" aria-hidden="true"><?php echo esc_html( $definition['icon'] ); ?></span>
								<span class="sx-path-card__body">
									<strong class="sx-path-card__title"><?php echo esc_html( $definition['name'] ); ?></strong>
									<span class="sx-path-card__description">Parcours indisponible : le terme n'existe pas encore.</span>
									<span class="sx-path-card__meta">Aucune vidéo publiée.</span>
									<span class="sx-path-card__progress" aria-hidden="true"><span></span><span></span><span></span></span>
								</span>
							</article>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $blocks['new'] ) ) :
			$new_videos = get_posts( [
				'post_type'      => 'seoflix_video',
				'post_status'    => 'publish',
				'posts_per_page'     => 12,
				'seoflix_focus_apply' => 1,
				'orderby'            => 'date',
				'order'          => 'DESC',
			] ); ?>
			<section id="nouveautes" class="sx-home-section" aria-labelledby="home-new-title">
				<h2 id="home-new-title" class="screen-reader-text">Nouveautés</h2>
				<?php if ( $new_videos ) : ?>
					<?php seoflix_render_video_row( 'Nouveautés', $new_videos, get_post_type_archive_link( 'seoflix_video' ) ); ?>
				<?php else : ?>
					<div class="sx-home-empty"><strong>Nouveautés</strong><p>Aucune vidéo publiée pour le moment.</p></div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $blocks['tools'] ) ) :
			$best_tool_ids = \Seoflix\Homepage::normalize_tool_ids( $cfg['best_tool_ids'] );
			$best_tools = $best_tool_ids ? get_posts( [
				'post_type'      => 'seoflix_product',
				'post_status'    => 'publish',
				'posts_per_page' => count( $best_tool_ids ),
				'post__in'       => $best_tool_ids,
				'orderby'        => 'post__in',
			] ) : []; ?>
			<section id="meilleurs-outils" class="sx-home-section" aria-labelledby="home-tools-title">
				<header class="sx-home-section__header sx-home-section__header--row">
					<h2 id="home-tools-title">Meilleurs outils</h2>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_product' ) ?: home_url( '/outils/' ) ); ?>">Voir tous les outils</a>
				</header>
				<?php if ( $best_tools ) : ?>
					<div class="sx-grid sx-grid--products">
						<?php foreach ( $best_tools as $tool ) : ?>
							<?php seoflix_render_product_card( $tool, [ 'show_pricing' => true ] ); ?>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="sx-home-empty"><p>Aucun outil publié n'est sélectionné pour le moment.</p></div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $blocks['promise'] ) ) : ?>
			<section id="promesse" class="sx-home-promise" aria-labelledby="home-promise-title">
				<h2 id="home-promise-title">Développe le business en ligne qui te correspond</h2>
				<p>Apprends l’édition de sites sans y laisser 500€.</p>
				<p>Crée ta compétence et développe ton business à partir d'une sélection complète des meilleures vidéos. Affiliation, YouTube, vente de liens, IA &amp; automatisation, vente de leads, freelancing.</p>
				<p>Suis le parcours qui te permettra de générer tes premiers euros en ligne.</p>
				<p>Des ressources vidéos sélectionnées et organisées pour se former à son rythme et gratuitement.</p>
			</section>
		<?php endif; ?>

		<?php
		$newsletter_rendered = false;
		if ( ! empty( $blocks['featured_paths'] ) ) :
			$featured_rows = [];
			foreach ( $cfg['featured_path_slugs'] as $featured_slug ) {
				$featured_term = get_term_by( 'slug', $featured_slug, 'seoflix_path' );
				if ( ! $featured_term instanceof WP_Term ) {
					continue;
				}
				$featured_videos = $get_path_videos( (int) $featured_term->term_id, 12 );
				$featured_rows[] = [
					'name'   => $path_names[ $featured_slug ] ?? $featured_slug,
					'term'   => $featured_term,
					'videos' => $featured_videos,
				];
			}
			if ( $featured_rows ) : ?>
			<section id="parcours-selectionnes" class="sx-home-section" aria-labelledby="home-featured-paths-title">
				<header class="sx-home-section__header">
					<h2 id="home-featured-paths-title">Suis le parcours de ton choix</h2>
				</header>
				<?php foreach ( $featured_rows as $featured_row_index => $featured_row ) : ?>
					<?php seoflix_render_video_row( $featured_row['name'], $featured_row['videos'], get_term_link( $featured_row['term'] ), 'Tout voir', true ); ?>
					<?php if ( $featured_row_index === 1 && ! empty( $blocks['newsletter'] ) && function_exists( 'seoflix_render_newsletter' ) ) : ?>
						<?php $render_home_newsletter(); ?>
						<?php $newsletter_rendered = true; ?>
					<?php endif; ?>
				<?php endforeach; ?>
			</section>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( ! $newsletter_rendered && ! empty( $blocks['newsletter'] ) && function_exists( 'seoflix_render_newsletter' ) ) : ?>
			<?php $render_home_newsletter(); ?>
		<?php endif; ?>

		<?php if ( ! empty( $blocks['about'] ) ) : ?>
			<section id="a-propos" class="sx-home-about" aria-labelledby="home-about-title">
				<p class="sx-home-section__kicker">Pourquoi WEAS</p>
				<h2 id="home-about-title">À propos</h2>
				<p>Cela fait plus de 5 ans que je fais du freelancing et de l'édition de sites (SEO, Affiliation, Youtube) et que je regarde tous les contenus sur ces sujets. Je voulais permettre aux débutants et aux initiés de perdre le moins de temps possible en sélectionnant les vidéos qui apportent de la valeur.</p>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $blocks['blog'] ) ) :
			$latest_posts = get_posts( [
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 4,
				'orderby'        => 'date',
				'order'          => 'DESC',
			] ); ?>
			<section id="derniers-articles" class="sx-home-section" aria-labelledby="home-posts-title">
				<header class="sx-home-section__header sx-home-section__header--row">
					<h2 id="home-posts-title">Derniers articles</h2>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'post' ) ?: home_url( '/blog/' ) ); ?>">Voir le blog</a>
				</header>
				<?php if ( $latest_posts ) : ?>
					<div class="sx-blog-grid">
						<?php foreach ( $latest_posts as $latest_post ) : ?>
							<?php seoflix_render_post_card( $latest_post, [ 'heading_level' => 3 ] ); ?>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="sx-home-empty"><p>Aucun article publié pour le moment.</p></div>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</div>
</div>

<script>
(function() {
	const hero = document.querySelector('.sx-home-hero');
	const video = hero && hero.querySelector('.sx-home-hero__video');
	const button = hero && hero.querySelector('.sx-home-hero__motion');
	const terms = hero ? Array.from(hero.querySelectorAll('.sx-home-hero__term')) : [];
	if (!hero || !video || !button) return;
	let termIndex = 0;
	let rotationId = null;
	const showTerm = function(index) {
		if (!terms.length) return;
		termIndex = index % terms.length;
		terms.forEach(function(term, current) {
			term.classList.toggle('is-active', current === termIndex);
		});
	};
	const stopRotation = function() {
		if (rotationId !== null) {
			window.clearInterval(rotationId);
			rotationId = null;
		}
	};
	const startRotation = function() {
		stopRotation();
		if (terms.length > 1) {
			rotationId = window.setInterval(function() {
				showTerm(termIndex + 1);
			}, 3000);
		}
	};
	const setPaused = function(paused) {
		hero.classList.toggle('is-paused', paused);
		button.textContent = paused ? 'Relancer l’animation' : 'Pause animation';
		button.setAttribute('aria-pressed', paused ? 'true' : 'false');
	};
	const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	if (reduced) {
		stopRotation();
		showTerm(0);
		video.pause();
		return;
	}
	video.play().then(function() {
		setPaused(false);
		startRotation();
	}).catch(function() {
		stopRotation();
		video.pause();
		setPaused(true);
	});
	button.addEventListener('click', function() {
		if ( ! hero.classList.contains('is-paused') ) {
			stopRotation();
			video.pause();
			setPaused(true);
			return;
		}
		video.play().then(function() {
			setPaused(false);
			startRotation();
		}).catch(function() {
			stopRotation();
			video.pause();
			setPaused(true);
		});
	});
})();
</script>

<?php get_footer();
