<?php
/**
 * Archive — catalogue des outils & ressources affiliés.
 */
get_header();

$categories = get_terms( [
	'taxonomy'   => 'seoflix_product_category',
	'hide_empty' => true,
] );
?>

<div class="sx-container sx-page">

	<header class="sx-archive-header">
		<div class="sx-archive-header__kicker">Outils &amp; ressources</div>
		<h1 class="sx-archive-header__title">Tous les outils SEO recommandés</h1>
		<p class="sx-archive-header__count">Outils, plateformes, formations et services mentionnés dans les vidéos. Liens affiliés sur certains outils — <a href="<?php echo esc_url( home_url( '/affiliation/' ) ); ?>">en savoir plus</a>.</p>
	</header>

	<?php if ( ! is_wp_error( $categories ) && $categories ) : ?>
		<div class="sx-badges" style="margin-bottom: var(--sx-space-8);">
			<a class="sx-badge sx-badge--accent" href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_product' ) ); ?>">Tout</a>
			<?php foreach ( $categories as $cat ) : ?>
				<a class="sx-badge" href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?> (<?php echo (int) $cat->count; ?>)</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<div class="sx-grid sx-grid--products">
			<?php while ( have_posts() ) : the_post();
				seoflix_render_product_card( get_post() );
			endwhile; ?>
		</div>
	<?php else : ?>
		<div class="sx-empty">Aucun outil référencé.</div>
	<?php endif; ?>

</div>

<?php get_footer();
