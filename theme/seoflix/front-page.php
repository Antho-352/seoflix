<?php
/**
 * Home page — Netflix-style.
 */
get_header();

$total_videos   = wp_count_posts( 'seoflix_video' )->publish ?? 0;
$total_channels = wp_count_posts( 'seoflix_channel' )->publish ?? 0;
$total_products = wp_count_posts( 'seoflix_product' )->publish ?? 0;
?>

<section class="sx-hero">
	<div class="sx-container">
		<h1 class="sx-hero__title">
			Maîtrise <span id="sx-rotate" class="sx-rotate" aria-live="polite">SEO</span><br>
			avec les meilleurs.
		</h1>
		<p class="sx-hero__subtitle">Les meilleures interviews, podcasts et tutos sur le SEO, le netlinking, l'affiliation, la vente de liens et le business web — agrégés en un seul endroit.</p>
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
	</div>
</section>

<div class="sx-container sx-page">

	<?php
	// Rangée : Nouveautés
	$new_videos = get_posts( [
		'post_type'      => 'seoflix_video',
		'post_status'    => 'publish',
		'posts_per_page' => 12,
		'orderby'        => 'date',
		'order'          => 'DESC',
	] );
	seoflix_render_video_row( 'Nouveautés', $new_videos, get_post_type_archive_link( 'seoflix_video' ) );

	// Rangées par sujet (uniquement sujets ayant des vidéos)
	$topics = get_terms( [
		'taxonomy'   => 'seoflix_topic',
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 6,
	] );
	if ( ! is_wp_error( $topics ) ) {
		foreach ( $topics as $topic ) {
			$videos = get_posts( [
				'post_type'      => 'seoflix_video',
				'post_status'    => 'publish',
				'posts_per_page' => 12,
				'tax_query'      => [
					[ 'taxonomy' => 'seoflix_topic', 'field' => 'term_id', 'terms' => $topic->term_id ],
				],
				'orderby'        => 'rand',
			] );
			seoflix_render_video_row( $topic->name, $videos, get_term_link( $topic ) );
		}
	}

	// Rangée chaînes
	$channels = get_posts( [
		'post_type'      => 'seoflix_channel',
		'post_status'    => 'publish',
		'posts_per_page' => 12,
		'meta_key'       => '_seoflix_subscriber_count',
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',
	] );
	seoflix_render_channel_row( 'Chaînes', $channels, get_post_type_archive_link( 'seoflix_channel' ) );

	// Rangée parcours d'apprentissage
	$paths = get_terms( [
		'taxonomy'   => 'seoflix_path',
		'hide_empty' => true,
	] );
	if ( ! is_wp_error( $paths ) && $paths ) :
	?>
		<section class="sx-row">
			<div class="sx-row__header">
				<h2 class="sx-row__title">Parcours d'apprentissage</h2>
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
	<?php endif; ?>

</div>

<script>
(function() {
	const el = document.getElementById('sx-rotate');
	if (!el) return;
	const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const words = ["l'Edition de Sites", "le SEO", "l'Affiliation", "Youtube", "la Vente de Liens", "l'IA", "le GEO", "la Vente de Leads", "le Black Hat", "le Business en ligne"];
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

<?php get_footer();
