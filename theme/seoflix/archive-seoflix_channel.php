<?php
/**
 * Archive — toutes les chaînes.
 */
get_header();
?>

<div class="sx-container sx-page">

	<header class="sx-archive-header">
		<div class="sx-archive-header__kicker">Chaînes</div>
		<h1 class="sx-archive-header__title">Les chaînes YouTube référencées</h1>
		<p class="sx-archive-header__count">Sélection des meilleures chaînes francophones SEO et édition de sites.</p>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="sx-grid sx-grid--channels">
			<?php while ( have_posts() ) : the_post();
				seoflix_render_channel_card( get_post() );
			endwhile; ?>
		</div>

		<nav class="sx-pagination" aria-label="Pagination">
			<?php echo paginate_links( [ 'prev_text' => '←', 'next_text' => '→' ] ); ?>
		</nav>
	<?php else : ?>
		<div class="sx-empty">Aucune chaîne référencée.</div>
	<?php endif; ?>

</div>

<?php get_footer();
