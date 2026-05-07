<?php
/**
 * Fallback générique (utilisé pour le blog WP standard si jamais activé en V2).
 */
get_header();
?>

<div class="sx-container sx-page">
	<?php if ( have_posts() ) : ?>
		<div class="sx-grid sx-grid--videos">
			<?php while ( have_posts() ) : the_post();
				if ( get_post_type() === 'seoflix_video' ) {
					seoflix_render_video_card( get_post() );
				} else {
					?>
					<article style="background: var(--sx-color-surface); border: 1px solid var(--sx-color-border); border-radius: var(--sx-radius-md); padding: var(--sx-space-6);">
						<a href="<?php the_permalink(); ?>" style="color: var(--sx-color-text);">
							<h3><?php the_title(); ?></h3>
							<p style="color: var(--sx-color-text-muted); margin-top: 0.5rem;"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 25 ) ); ?></p>
						</a>
					</article>
					<?php
				}
			endwhile; ?>
		</div>

		<nav class="sx-pagination" aria-label="Pagination">
			<?php echo paginate_links( [ 'prev_text' => '←', 'next_text' => '→' ] ); ?>
		</nav>
	<?php else : ?>
		<div class="sx-empty">Aucun contenu trouvé.</div>
	<?php endif; ?>
</div>

<?php get_footer();
