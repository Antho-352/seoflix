<?php
/**
 * Résultats de recherche.
 */
get_header();

global $wp_query;
$total = (int) $wp_query->found_posts;
$q     = get_search_query();
?>

<div class="sx-container sx-page">

	<header class="sx-archive-header">
		<div class="sx-archive-header__kicker">Recherche</div>
		<h1 class="sx-archive-header__title">« <?php echo esc_html( $q ); ?> »</h1>
		<p class="sx-archive-header__count"><?php echo esc_html( number_format_i18n( $total ) ); ?> résultat<?php echo $total > 1 ? 's' : ''; ?></p>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="sx-grid sx-grid--videos">
			<?php while ( have_posts() ) : the_post();
				if ( get_post_type() === 'seoflix_video' ) {
					seoflix_render_video_card( get_post() );
				} elseif ( get_post_type() === 'seoflix_channel' ) {
					seoflix_render_channel_card( get_post() );
				} elseif ( get_post_type() === 'seoflix_product' ) {
					seoflix_render_product_card( get_post() );
				}
			endwhile; ?>
		</div>

		<nav class="sx-pagination" aria-label="Pagination">
			<?php echo paginate_links( [ 'prev_text' => '←', 'next_text' => '→' ] ); ?>
		</nav>
	<?php else : ?>
		<div class="sx-empty">
			<p>Aucun résultat pour <strong>« <?php echo esc_html( $q ); ?> »</strong>.</p>
			<p style="margin-top: var(--sx-space-3);"><a class="sx-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Retour à l'accueil</a></p>
		</div>
	<?php endif; ?>

</div>

<?php get_footer();
