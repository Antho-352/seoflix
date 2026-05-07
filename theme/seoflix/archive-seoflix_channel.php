<?php
/**
 * Archive — toutes les chaînes.
 */
get_header();
?>

<div class="sx-container sx-page">

	<?php
	global $wp_query;
	$total = (int) $wp_query->found_posts;
	?>

	<header class="sx-archive-header">
		<div class="sx-archive-header__kicker">Chaînes</div>
		<h1 class="sx-archive-header__title">Les chaînes YouTube référencées</h1>
		<p class="sx-archive-header__count"><?php echo esc_html( number_format_i18n( $total ) ); ?> chaîne<?php echo $total > 1 ? 's' : ''; ?> francophone<?php echo $total > 1 ? 's' : ''; ?> sur le SEO et l'édition de sites — triées par audience YouTube.</p>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="sx-grid sx-grid--channels">
			<?php while ( have_posts() ) : the_post();
				seoflix_render_channel_card( get_post() );
			endwhile; ?>
		</div>
	<?php else : ?>
		<div class="sx-empty">Aucune chaîne référencée.</div>
	<?php endif; ?>

</div>

<?php get_footer();
