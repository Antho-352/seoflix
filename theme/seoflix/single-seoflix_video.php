<?php
/**
 * Page vidéo — lecteur YouTube + sidebar produits affiliés (toujours visible côté player en desktop).
 */
get_header();

while ( have_posts() ) :
	the_post();
	$video_id = get_the_ID();
	$yid      = seoflix_video_youtube_id( $video_id );
	$channel  = seoflix_video_channel( $video_id );
	$products = seoflix_video_products( $video_id );
	$topics   = seoflix_video_topics( $video_id );
	$formats  = wp_get_object_terms( $video_id, 'seoflix_format' );
	$duration = seoflix_video_duration_formatted( $video_id );
	$views    = seoflix_format_count( seoflix_video_view_count( $video_id ) );
	$pub      = (string) get_post_meta( $video_id, '_seoflix_published_at', true );
	?>
	<article class="sx-container sx-page sx-video-page">

		<header class="sx-video-page__heading">
			<h1 class="sx-video-page__title"><?php the_title(); ?></h1>

			<div class="sx-video-page__meta">
				<?php if ( $channel ) :
					$avatar = seoflix_channel_thumbnail_url( $channel->ID ); ?>
					<a href="<?php echo esc_url( get_permalink( $channel ) ); ?>" class="sx-video-page__channel-link">
						<?php if ( $avatar ) : ?>
							<img src="<?php echo esc_url( $avatar ); ?>" alt="" class="sx-video-page__channel-avatar" loading="lazy">
						<?php endif; ?>
						<?php echo esc_html( $channel->post_title ); ?>
					</a>
					<span class="sx-sep">·</span>
				<?php endif; ?>
				<?php if ( $duration ) : ?>
					<span><?php echo esc_html( $duration ); ?></span>
					<span class="sx-sep">·</span>
				<?php endif; ?>
				<span><?php echo esc_html( $views ); ?> vues</span>
				<?php if ( $pub ) : ?>
					<span class="sx-sep">·</span>
					<span><?php echo esc_html( wp_date( 'j F Y', strtotime( $pub ) ) ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( ( ! is_wp_error( $topics ) && $topics ) || ( ! is_wp_error( $formats ) && $formats ) ) : ?>
				<div class="sx-badges sx-video-page__badges">
					<?php
					if ( ! is_wp_error( $topics ) ) {
						foreach ( $topics as $t ) {
							echo '<a class="sx-badge sx-badge--accent" href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a>';
						}
					}
					if ( ! is_wp_error( $formats ) ) {
						foreach ( $formats as $f ) {
							echo '<a class="sx-badge" href="' . esc_url( get_term_link( $f ) ) . '">' . esc_html( $f->name ) . '</a>';
						}
					}
					?>
				</div>
			<?php endif; ?>

			<?php
			if ( class_exists( '\Seoflix\FeatureFlags' ) && \Seoflix\FeatureFlags::user_accounts_enabled() ) :
				$current_uid = get_current_user_id();
				if ( $current_uid ) :
					$is_fav     = \Seoflix\User_Accounts::is_video_favorited( $current_uid, $video_id );
					$is_watched = \Seoflix\User_Accounts::is_video_watched( $current_uid, $video_id );
					$nonce      = wp_create_nonce( 'seoflix_user_action' );
					?>
					<div class="sx-video-page__actions" data-video-id="<?php echo (int) $video_id; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
						<button type="button" class="sx-action sx-action--fav <?php echo $is_fav ? 'is-on' : ''; ?>" data-action="favorite" aria-pressed="<?php echo $is_fav ? 'true' : 'false'; ?>">
							<span class="sx-action__icon">♥</span>
							<span class="sx-action__label"><?php echo $is_fav ? 'Retirer des favoris' : 'Ajouter aux favoris'; ?></span>
						</button>
						<button type="button" class="sx-action sx-action--watched <?php echo $is_watched ? 'is-on' : ''; ?>" data-action="watched" aria-pressed="<?php echo $is_watched ? 'true' : 'false'; ?>">
							<span class="sx-action__icon">✓</span>
							<span class="sx-action__label"><?php echo $is_watched ? 'Vue' : 'Marquer comme vue'; ?></span>
						</button>
					</div>
				<?php else : ?>
					<p class="sx-video-page__signup-cta">
						<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Connecte-toi</a> pour suivre ta progression et ajouter aux favoris.
					</p>
				<?php endif;
			endif;
			?>
		</header>

		<div class="sx-video-page__top">
			<div class="sx-video-page__player-col">
				<?php if ( $yid ) : ?>
					<div class="sx-player">
						<iframe
							src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr( $yid ); ?>?rel=0&modestbranding=1"
							title="<?php echo esc_attr( get_the_title() ); ?>"
							loading="lazy"
							referrerpolicy="strict-origin-when-cross-origin"
							allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
							allowfullscreen></iframe>
					</div>
				<?php else : ?>
					<div class="sx-empty">Vidéo non disponible.</div>
				<?php endif; ?>
			</div>

			<aside class="sx-video-page__sidebar">
				<?php if ( $products ) : ?>
					<div class="sx-video-page__products">
						<span class="sx-affiliate-notice">Liens affiliés</span>
						<h2>Produits &amp; services mentionnés</h2>
						<div class="sx-product-list">
							<?php foreach ( $products as $p ) {
								seoflix_render_product_card( $p, [ 'show_pricing' => false, 'compact' => true ] );
							} ?>
						</div>
					</div>
				<?php else : ?>
					<div class="sx-video-page__products sx-video-page__products--empty">
						<h2>Aucun produit référencé pour cette vidéo</h2>
						<p>Découvre tous les outils SEO recommandés sur Seoflix.</p>
						<p><a class="sx-btn sx-btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_product' ) ); ?>">Voir le catalogue</a></p>
					</div>
				<?php endif; ?>
			</aside>
		</div>

		<div class="sx-video-page__description">
			<h2>Ce que couvre cette vidéo</h2>
			<?php the_content(); ?>
		</div>

		<?php
		// Suggestions : autres vidéos de la chaîne + même sujet
		if ( $channel ) {
			$suggestions = get_posts( [
				'post_type'      => 'seoflix_video',
				'post_status'    => 'publish',
				'posts_per_page' => 8,
				'post__not_in'   => [ $video_id ],
				'meta_query'     => [
					[ 'key' => '_seoflix_channel_id', 'value' => $channel->ID ],
				],
				'orderby'        => 'rand',
			] );
			if ( $suggestions ) {
				seoflix_render_video_row( 'Autres vidéos de ' . $channel->post_title, $suggestions, get_permalink( $channel ) );
			}
		}

		if ( ! is_wp_error( $topics ) && $topics ) {
			$first_topic = $topics[0];
			$similar = get_posts( [
				'post_type'      => 'seoflix_video',
				'post_status'    => 'publish',
				'posts_per_page' => 8,
				'post__not_in'   => [ $video_id ],
				'tax_query'      => [
					[ 'taxonomy' => 'seoflix_topic', 'field' => 'term_id', 'terms' => $first_topic->term_id ],
				],
				'orderby'        => 'rand',
			] );
			if ( $similar ) {
				seoflix_render_video_row( 'Sur le même sujet : ' . $first_topic->name, $similar, get_term_link( $first_topic ) );
			}
		}
		?>

	</article>
<?php endwhile; ?>

<?php get_footer();
