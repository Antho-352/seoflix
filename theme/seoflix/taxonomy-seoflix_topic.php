<?php
/**
 * Archive taxonomie : Sujet (topic).
 */
get_header();

$term  = get_queried_object();
global $wp_query;
$total = (int) $wp_query->found_posts;
?>

<div class="sx-container sx-page">

	<header class="sx-archive-header">
		<div class="sx-archive-header__kicker">Sujet</div>
		<h1 class="sx-archive-header__title"><?php echo esc_html( $term->name ); ?></h1>
		<p class="sx-archive-header__count"><?php echo esc_html( number_format_i18n( $total ) ); ?> vidéo<?php echo $total > 1 ? 's' : ''; ?></p>
		<?php if ( $term->description ) : ?>
			<p style="margin-top: var(--sx-space-3); max-width: 60ch; color: var(--sx-color-text-muted);"><?php echo esc_html( $term->description ); ?></p>
		<?php endif; ?>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="sx-grid sx-grid--videos">
			<?php while ( have_posts() ) : the_post();
				seoflix_render_video_card( get_post() );
			endwhile; ?>
		</div>

		<nav class="sx-pagination" aria-label="Pagination">
			<?php echo paginate_links( [ 'prev_text' => '←', 'next_text' => '→' ] ); ?>
		</nav>
	<?php else : ?>
		<div class="sx-empty">Aucune vidéo dans ce sujet pour le moment.</div>
	<?php endif; ?>

</div>

<?php get_footer();
