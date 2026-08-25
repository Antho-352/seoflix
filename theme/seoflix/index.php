<?php
/**
 * Archive des articles natifs et fallback de contenu.
 */
get_header();

if ( is_home() ) {
	$posts_page          = (int) get_option( 'page_for_posts' );
	$archive_title       = single_post_title( '', false ) ?: 'Le blog';
	$archive_description = $posts_page ? get_post_field( 'post_excerpt', $posts_page ) : '';
} elseif ( is_category() ) {
	$archive_title       = sprintf( 'Articles : %s', single_cat_title( '', false ) );
	$archive_description = category_description();
} elseif ( is_date() ) {
	$archive_title       = get_the_archive_title();
	$archive_description = get_the_archive_description();
} else {
	$archive_title       = get_the_archive_title() ?: 'Tous les contenus';
	$archive_description = get_the_archive_description();
}
$archive_title = wp_strip_all_tags( $archive_title );
?>

<div class="sx-container sx-page sx-blog-archive">
	<header class="sx-blog-archive__header">
		<p class="sx-blog-archive__kicker">Éditorial</p>
		<h1 class="sx-blog-archive__title"><?php echo esc_html( $archive_title ); ?></h1>
		<?php if ( $archive_description ) : ?>
			<div class="sx-blog-archive__description"><?php echo wp_kses_post( $archive_description ); ?></div>
		<?php endif; ?>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="sx-blog-grid">
			<?php while ( have_posts() ) : the_post(); ?>
				<?php if ( 'seoflix_video' === get_post_type() ) : ?>
					<?php seoflix_render_video_card( get_post() ); ?>
				<?php else : ?>
					<?php seoflix_render_post_card( get_post() ); ?>
				<?php endif; ?>
			<?php endwhile; ?>
		</div>

		<?php the_posts_pagination( [
			'mid_size'           => 1,
			'prev_text'          => 'Précédent',
			'next_text'          => 'Suivant',
			'screen_reader_text' => 'Pagination des articles',
		] ); ?>
	<?php else : ?>
		<div class="sx-blog-empty" role="status">
			<h2>Aucun article trouvé</h2>
			<p>Les prochains articles apparaîtront ici dès leur publication.</p>
		</div>
	<?php endif; ?>
</div>

<?php get_footer();
