<?php
/**
 * Home page — Netflix-style.
 * Configurable via Seoflix → Page d'accueil (option seoflix_homepage_config).
 */
get_header();

$cfg            = class_exists( '\Seoflix\Homepage' ) ? \Seoflix\Homepage::get_config() : [];
$hero           = $cfg['hero'] ?? [];
$sections       = class_exists( '\Seoflix\Homepage' ) ? \Seoflix\Homepage::visible_sections() : [];
$total_videos   = wp_count_posts( 'seoflix_video' )->publish ?? 0;
$total_channels = wp_count_posts( 'seoflix_channel' )->publish ?? 0;
$total_products = wp_count_posts( 'seoflix_product' )->publish ?? 0;

$hero_title    = (string) ( $hero['title'] ?? 'Maîtrise [rotate] avec les meilleurs.' );
$hero_subtitle = (string) ( $hero['subtitle'] ?? '' );
$rotating      = (array)  ( $hero['rotating_words'] ?? [] );
$show_stats    = ! empty( $hero['show_stats'] );

// Découpage du titre autour de [rotate]
$has_rotate    = strpos( $hero_title, '[rotate]' ) !== false;
[$title_before, $title_after] = $has_rotate
	? array_pad( explode( '[rotate]', $hero_title, 2 ), 2, '' )
	: [ $hero_title, '' ];

// Compteur de rangées rendues (pour insérer la newsletter au bon endroit)
// Le bloc newsletter apparaît APRÈS la 2e rangée vidéo (ex: après Nouveautés + SEO)
$rows_rendered     = 0;
$newsletter_done   = false;
$newsletter_after  = 2; // après la 2e rangée
$render_newsletter = static function () use ( &$rows_rendered, &$newsletter_done, $newsletter_after ) {
	if ( $newsletter_done ) {
		return;
	}
	if ( $rows_rendered >= $newsletter_after && function_exists( 'seoflix_render_newsletter' ) ) {
		seoflix_render_newsletter( 'homepage', [ 'compact' => true ] );
		$newsletter_done = true;
	}
};
?>

<section class="sx-hero">
	<div class="sx-container">
		<h1 class="sx-hero__title">
			<?php echo esc_html( $title_before ); ?>
			<?php if ( $has_rotate ) : ?>
				<span id="sx-rotate" class="sx-rotate" aria-live="polite"><?php echo esc_html( $rotating[0] ?? '' ); ?></span>
			<?php endif; ?>
			<?php echo esc_html( $title_after ); ?>
		</h1>
		<?php if ( $hero_subtitle ) : ?>
			<p class="sx-hero__subtitle"><?php echo esc_html( $hero_subtitle ); ?></p>
		<?php endif; ?>
		<?php if ( $show_stats ) : ?>
			<div class="sx-hero__stats">
				<div class="sx-hero__stat">
					<strong><?php echo esc_html( number_format_i18n( $total_videos ) ); ?></strong>
					<span>vidéos</span>
				</div>
				<div class="sx-hero__stat">
					<strong><?php echo esc_html( number_format_i18n( $total_channels ) ); ?></strong>
					<span>chaînes</span>
				</div>
				<div class="sx-hero__stat">
					<strong><?php echo esc_html( number_format_i18n( $total_products ) ); ?></strong>
					<span>outils référencés</span>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>

<div class="sx-container sx-page">

	<?php
	foreach ( $sections as $section ) {
		$type  = $section['type'] ?? '';
		$title = (string) ( $section['title'] ?? '' );
		$limit = (int) ( $section['limit'] ?? 12 );

		switch ( $type ) {

			case \Seoflix\Homepage::TYPE_NEW:
				$videos = get_posts( [
					'post_type'      => 'seoflix_video',
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'orderby'        => 'date',
					'order'          => 'DESC',
				] );
				seoflix_render_video_row(
					$title ?: 'Nouveautés',
					$videos,
					get_post_type_archive_link( 'seoflix_video' )
				);
				$rows_rendered++;
				$render_newsletter();
				break;

			case \Seoflix\Homepage::TYPE_MOST_VIEWED:
				$videos = get_posts( [
					'post_type'      => 'seoflix_video',
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'meta_key'       => '_seoflix_view_count',
					'orderby'        => 'meta_value_num',
					'order'          => 'DESC',
				] );
				seoflix_render_video_row(
					$title ?: 'Les plus vues',
					$videos,
					get_post_type_archive_link( 'seoflix_video' )
				);
				$rows_rendered++;
				$render_newsletter();
				break;

			case \Seoflix\Homepage::TYPE_TOPICS:
				$manual_slugs = isset( $section['topics_manual'] ) && is_array( $section['topics_manual'] )
					? array_filter( array_map( 'sanitize_key', $section['topics_manual'] ) )
					: [];

				if ( $manual_slugs ) {
					// Mode manuel : ordre exact défini par l'admin
					$topics = [];
					foreach ( $manual_slugs as $slug ) {
						$term = get_term_by( 'slug', $slug, 'seoflix_topic' );
						if ( $term && ! is_wp_error( $term ) ) {
							$topics[] = $term;
						}
					}
				} else {
					// Mode auto : top N par nombre de vidéos
					$topics_count = (int) ( $section['topics_count'] ?? 6 );
					$topics = get_terms( [
						'taxonomy'   => 'seoflix_topic',
						'hide_empty' => true,
						'orderby'    => 'count',
						'order'      => 'DESC',
						'number'     => $topics_count,
					] );
					if ( is_wp_error( $topics ) ) {
						$topics = [];
					}
				}

				foreach ( $topics as $topic ) {
					$videos = get_posts( [
						'post_type'      => 'seoflix_video',
						'post_status'    => 'publish',
						'posts_per_page' => $limit,
						'tax_query'      => [
							[ 'taxonomy' => 'seoflix_topic', 'field' => 'term_id', 'terms' => $topic->term_id ],
						],
						'orderby'        => 'rand',
					] );
					seoflix_render_video_row( $topic->name, $videos, get_term_link( $topic ) );
					$rows_rendered++;
					$render_newsletter();
				}
				break;

			case \Seoflix\Homepage::TYPE_CHANNELS:
				$channels = get_posts( [
					'post_type'      => 'seoflix_channel',
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'meta_key'       => '_seoflix_subscriber_count',
					'orderby'        => 'meta_value_num',
					'order'          => 'DESC',
				] );
				seoflix_render_channel_row(
					$title ?: 'Chaînes',
					$channels,
					get_post_type_archive_link( 'seoflix_channel' )
				);
				$rows_rendered++;
				$render_newsletter();
				break;

			case \Seoflix\Homepage::TYPE_PATHS:
				$paths = get_terms( [
					'taxonomy'   => 'seoflix_path',
					'hide_empty' => true,
				] );
				if ( ! is_wp_error( $paths ) && $paths ) :
					?>
					<section class="sx-row">
						<div class="sx-row__header">
							<h2 class="sx-row__title"><?php echo esc_html( $title ?: "Parcours d'apprentissage" ); ?></h2>
						</div>
						<div class="sx-grid sx-grid--products">
							<?php foreach ( $paths as $path ) :
								$count = (int) $path->count; ?>
								<a class="sx-card-product sx-card-product__link" href="<?php echo esc_url( get_term_link( $path ) ); ?>" style="text-decoration: none;">
									<h3 class="sx-card-product__name"><?php echo esc_html( $path->name ); ?></h3>
									<p class="sx-card-product__excerpt"><?php echo esc_html( $count ); ?> vidéo<?php echo $count > 1 ? 's' : ''; ?></p>
								</a>
							<?php endforeach; ?>
						</div>
					</section>
					<?php
					$rows_rendered++;
					$render_newsletter();
				endif;
				break;
		}
	}

	// Si pour une raison ou une autre on n'a pas atteint le seuil (ex: 1 seule rangée),
	// on injecte quand même la newsletter en bas de la home.
	if ( ! $newsletter_done && function_exists( 'seoflix_render_newsletter' ) ) {
		seoflix_render_newsletter( 'homepage', [ 'compact' => true ] );
	}
	?>

</div>

<?php if ( $has_rotate && count( $rotating ) > 1 ) : ?>
<script>
(function() {
	const el = document.getElementById('sx-rotate');
	if (!el) return;
	const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const words = <?php echo wp_json_encode( array_values( $rotating ) ); ?>;
	if (!words.length) return;
	if (reduced) { el.textContent = words[0]; return; }
	let i = 0;
	el.textContent = words[0];
	setInterval(function() {
		el.classList.add('is-out');
		setTimeout(function() {
			i = (i + 1) % words.length;
			el.textContent = words[i];
			el.classList.remove('is-out');
		}, 280);
	}, 2200);
})();
</script>
<?php endif; ?>

<?php get_footer();
