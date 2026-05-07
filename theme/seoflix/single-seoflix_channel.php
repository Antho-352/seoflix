<?php
/**
 * Page chaîne — hero + grille des vidéos.
 */
get_header();

while ( have_posts() ) :
	the_post();
	$channel_id  = get_the_ID();
	$thumb       = seoflix_channel_thumbnail_url( $channel_id );
	$real_name   = seoflix_channel_real_name( $channel_id );
	$subscribers = seoflix_channel_subscriber_count( $channel_id );
	$yt_url      = seoflix_channel_youtube_url( $channel_id );
	$videos      = seoflix_channel_videos( $channel_id, -1 );
	?>
	<article class="sx-container sx-page">

		<header class="sx-channel-hero">
			<?php if ( $thumb ) : ?>
				<img src="<?php echo esc_url( $thumb ); ?>" alt="" class="sx-channel-hero__avatar" loading="eager">
			<?php endif; ?>
			<div class="sx-channel-hero__info">
				<h1 class="sx-channel-hero__name"><?php the_title(); ?></h1>
				<?php if ( $real_name && $real_name !== get_the_title() ) : ?>
					<div class="sx-channel-hero__real-name"><?php echo esc_html( $real_name ); ?></div>
				<?php endif; ?>
				<?php if ( $subscribers > 0 ) : ?>
					<div class="sx-channel-hero__subs"><?php echo esc_html( seoflix_format_count( $subscribers ) ); ?> abonnés YouTube</div>
				<?php endif; ?>
				<?php if ( $post = get_post( $channel_id ) ) {
					$content = wpautop( $post->post_content );
					if ( $content ) {
						echo '<div class="sx-channel-hero__description">' . wp_kses_post( $content ) . '</div>';
					}
				} ?>
				<?php if ( $yt_url ) : ?>
					<div class="sx-channel-hero__actions">
						<a class="sx-btn sx-btn--ghost" href="<?php echo esc_url( $yt_url ); ?>" target="_blank" rel="noopener noreferrer">Voir sur YouTube ↗</a>
					</div>
				<?php endif; ?>
			</div>
		</header>

		<?php if ( $videos ) : ?>
			<h2 class="sx-row__title" style="margin-bottom: var(--sx-space-6);"><?php echo count( $videos ); ?> vidéo<?php echo count( $videos ) > 1 ? 's' : ''; ?></h2>
			<div class="sx-grid sx-grid--videos">
				<?php foreach ( $videos as $v ) {
					seoflix_render_video_card( $v );
				} ?>
			</div>
		<?php else : ?>
			<div class="sx-empty">Aucune vidéo publiée pour cette chaîne.</div>
		<?php endif; ?>

	</article>
<?php endwhile; ?>

<?php get_footer();
