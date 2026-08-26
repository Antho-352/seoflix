<?php
/**
 * Page d'accueil MADIAS : assemblage éditorial fixe.
 */
get_header();

$cfg          = class_exists( '\Seoflix\Homepage' ) ? \Seoflix\Homepage::get_config() : \Seoflix\Homepage::defaults();
$hero         = $cfg['hero'];
$blocks       = $cfg['fixed_blocks'];
$path_catalog = \Seoflix\Homepage::path_definitions();
$path_names   = array_column( $path_catalog, 'name', 'slug' );

$get_path_videos = static function ( int $term_id, int $limit = -1 ): array {
	$ordered_ids = \Seoflix\Path_Order::ordered_video_ids_for_term( $term_id );
	if ( ! $ordered_ids ) {
		return [];
	}
	return get_posts( [
		'post_type'      => 'seoflix_video',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'post__in'       => $ordered_ids,
		'orderby'        => 'post__in',
	] );
};
?>

<div class="sx-home">
	<section class="sx-home-hero" aria-labelledby="madias-home-title">
		<div class="sx-container sx-home-hero__inner">
			<p class="sx-home-hero__brand">MADIAS</p>
			<h1 id="madias-home-title" class="sx-home-hero__title"><?php echo esc_html( $hero['title'] ?: 'Apprends le business web sans perdre des heures sur YouTube.' ); ?></h1>
			<?php if ( $hero['subtitle'] ) : ?>
				<p class="sx-home-hero__subtitle"><?php echo esc_html( $hero['subtitle'] ); ?></p>
			<?php endif; ?>
			<a class="sx-home-hero__cta" href="<?php echo esc_url( home_url( '/commencer/' ) ); ?>"><?php echo esc_html( $hero['cta_text'] ?: 'Commencer à apprendre' ); ?></a>
		</div>
	</section>

	<div class="sx-container sx-page sx-home__content">
		<?php if ( ! empty( $blocks['paths'] ) ) : ?>
			<section id="parcours" class="sx-home-section sx-home-paths" aria-labelledby="home-paths-title">
				<header class="sx-home-section__header">
					<p class="sx-home-section__kicker">Choisis ton cap</p>
					<h2 id="home-paths-title">Six parcours pour apprendre dans le bon ordre</h2>
				</header>
				<div class="sx-home-paths__grid">
					<?php foreach ( $path_catalog as $definition ) :
						$term = get_term_by( 'slug', $definition['slug'], 'seoflix_path' );
						if ( $term && ! is_wp_error( $term ) ) :
							$path_videos = $get_path_videos( (int) $term->term_id );
							$count       = count( $path_videos );
							$description = trim( wp_strip_all_tags( $term->description ) );
							?>
							<a class="sx-path-card" href="<?php echo esc_url( get_term_link( $term ) ); ?>" aria-label="<?php echo esc_attr( 'Ouvrir le parcours ' . $definition['name'] ); ?>">
								<span class="sx-path-card__icon" aria-hidden="true"><?php echo esc_html( $definition['icon'] ); ?></span>
								<span class="sx-path-card__body">
									<strong class="sx-path-card__title"><?php echo esc_html( $definition['name'] ); ?></strong>
									<span class="sx-path-card__description"><?php echo esc_html( $description ?: 'Description indisponible.' ); ?></span>
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
				'posts_per_page' => 12,
				'orderby'        => 'date',
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
				<p class="sx-home-section__kicker">Une sélection gratuite</p>
				<h2 id="home-promise-title">Apprends l’édition de sites sans y laisser 500€.</h2>
				<p>La sélection complète des meilleures vidéos SEO, affiliation, vente de liens et YouTube business, déjà triées et organisées en parcours.</p>
				<em>Sans formation à vendre à la fin, sans code obligatoire, sans compte premium caché.</em>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $blocks['featured_paths'] ) ) : ?>
			<section id="parcours-selectionnes" class="sx-home-section" aria-labelledby="home-featured-paths-title">
				<header class="sx-home-section__header">
					<h2 id="home-featured-paths-title">Commence par un parcours</h2>
				</header>
				<?php foreach ( $cfg['featured_path_slugs'] as $featured_slug ) :
					$featured_name = $path_names[ $featured_slug ] ?? $featured_slug;
					$featured_term = get_term_by( 'slug', $featured_slug, 'seoflix_path' );
					if ( ! $featured_term || is_wp_error( $featured_term ) ) : ?>
						<section class="sx-row sx-home-unavailable-row" aria-label="Parcours indisponible">
							<h3>Parcours indisponible</h3>
							<p>Le parcours configuré « <?php echo esc_html( $featured_slug ); ?> » n'existe pas.</p>
						</section>
						<?php continue; ?>
					<?php endif;
					$featured_videos = $get_path_videos( (int) $featured_term->term_id, 12 );
					if ( $featured_videos ) : ?>
						<?php seoflix_render_video_row( $featured_name, $featured_videos, get_term_link( $featured_term ) ); ?>
					<?php else : ?>
						<section class="sx-row sx-home-unavailable-row" aria-labelledby="featured-path-<?php echo (int) $featured_term->term_id; ?>">
							<h3 id="featured-path-<?php echo (int) $featured_term->term_id; ?>"><?php echo esc_html( $featured_name ); ?></h3>
							<p>Aucune vidéo publiée dans ce parcours.</p>
						</section>
					<?php endif; ?>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $blocks['paths_cta'] ) ) : ?>
			<section id="tous-les-parcours" class="sx-home-paths-cta" aria-labelledby="home-paths-cta-title">
				<h2 id="home-paths-cta-title">Tu sais déjà ce que tu veux apprendre ?</h2>
				<a class="sx-home-cta" href="<?php echo esc_url( home_url( '/parcours/' ) ); ?>">Voir tous les parcours</a>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $blocks['about'] ) ) : ?>
			<section id="a-propos" class="sx-home-about" aria-labelledby="home-about-title">
				<p class="sx-home-section__kicker">Pourquoi MADIAS</p>
				<h2 id="home-about-title">À propos</h2>
				<p>Cela fait plus de 5 ans que je fais du freelancing et de l'édition de sites (SEO, Affiliation, Youtube) et que je regarde tous les contenus sur ces sujets. Je voulais permettre aux débutants et aux initiés de perdre le moins de temps possible en sélectionnant les vidéos qui apportent de la valeur.</p>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $blocks['newsletter'] ) && function_exists( 'seoflix_render_newsletter' ) ) : ?>
			<?php seoflix_render_newsletter( 'homepage', [ 'compact' => true ] ); ?>
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

<?php get_footer();
