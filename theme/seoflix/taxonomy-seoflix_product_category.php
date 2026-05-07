<?php
/**
 * Archive taxonomie : Catégorie de produits affiliés.
 */
get_header();

$term  = get_queried_object();
global $wp_query;
$total = (int) $wp_query->found_posts;
?>

<div class="sx-container sx-page">

	<header class="sx-archive-header">
		<div class="sx-archive-header__kicker">Catégorie</div>
		<h1 class="sx-archive-header__title"><?php echo esc_html( $term->name ); ?></h1>
		<p class="sx-archive-header__count"><?php echo esc_html( number_format_i18n( $total ) ); ?> outil<?php echo $total > 1 ? 's' : ''; ?>. Liens affiliés sur certains produits — <a href="<?php echo esc_url( home_url( '/affiliation/' ) ); ?>">en savoir plus</a>.</p>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="sx-grid sx-grid--products">
			<?php while ( have_posts() ) : the_post();
				seoflix_render_product_card( get_post() );
			endwhile; ?>
		</div>

		<nav class="sx-pagination" aria-label="Pagination">
			<?php echo paginate_links( [ 'prev_text' => '←', 'next_text' => '→' ] ); ?>
		</nav>
	<?php else : ?>
		<div class="sx-empty">Aucun outil dans cette catégorie.</div>
	<?php endif; ?>

</div>

<?php get_footer();
