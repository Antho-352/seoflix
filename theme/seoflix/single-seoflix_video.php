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
	$pub      = (string) get_post_meta( $video_id, '_seoflix_published_at', true );
	$source_embed_url = seoflix_source_embed_url( $yid );
	$timestamps       = seoflix_video_timestamps( $video_id );
	$key_concepts     = seoflix_video_key_concepts( $video_id );
	$editorial_url    = seoflix_video_editorial_embed_url( $video_id );
	?>
	<article class="sx-container sx-page sx-video-page">

		<header class="sx-video-page__heading">
			<h1 class="sx-video-page__title"><?php the_title(); ?></h1>

			<div class="sx-video-page__meta">
				<?php if ( $channel ) :
					$avatar = seoflix_channel_thumbnail_url( $channel->ID ); ?>
					<a href="<?php echo esc_url( get_permalink( $channel ) ); ?>" class="sx-video-page__channel-link">
						<?php if ( $avatar ) : ?>
							<img src="<?php echo esc_url( $avatar ); ?>" alt="" width="32" height="32" class="sx-video-page__channel-avatar" loading="lazy">
						<?php endif; ?>
						<?php echo esc_html( $channel->post_title ); ?>
					</a>
					<span class="sx-sep">·</span>
				<?php endif; ?>
				<?php if ( $duration ) : ?>
					<span><?php echo esc_html( $duration ); ?></span>
				<?php endif; ?>
				<?php if ( $duration && $pub ) : ?>
					<span class="sx-sep">·</span>
				<?php endif; ?>
				<?php if ( $pub ) : ?>
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

		</header>

		<div class="sx-video-page__top">
			<div class="sx-video-page__player-col">
				<section class="sx-video-source" data-sx-player="source" aria-labelledby="sx-video-source-title">
					<h2 id="sx-video-source-title" class="sx-video-source__label">Vidéo source</h2>
				<?php
				$lock_videos      = (bool) get_option( 'seoflix_lock_videos_to_users', false );
				$accounts_enabled = class_exists( '\Seoflix\FeatureFlags' ) && \Seoflix\FeatureFlags::user_accounts_enabled();
				$show_locked      = $lock_videos && $accounts_enabled && ! is_user_logged_in();
				?>
				<?php if ( $show_locked ) : ?>
					<div class="sx-player-locked">
						<div class="sx-player-locked__inner">
							<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
								<rect x="4" y="11" width="16" height="10" rx="2"/>
								<path d="M8 11V7a4 4 0 1 1 8 0v4"/>
							</svg>
							<h3>Connecte-toi pour regarder</h3>
							<p>Crée un compte gratuit pour accéder à toutes les vidéos et suivre ta progression sur les parcours d'apprentissage.</p>
							<div class="sx-player-locked__cta">
								<a class="sx-btn" href="<?php echo esc_url( wp_registration_url() ); ?>">Créer un compte gratuit</a>
								<a class="sx-btn sx-btn--ghost" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Se connecter</a>
							</div>
						</div>
					</div>
				<?php elseif ( $source_embed_url ) : ?>
					<div class="sx-player">
						<iframe
							id="sx-source-player"
							name="sx-source-player"
							src="<?php echo esc_url( $source_embed_url ); ?>"
							title="<?php echo esc_attr( sprintf( 'Vidéo source : %s', get_the_title() ) ); ?>"
							loading="lazy"
							referrerpolicy="strict-origin-when-cross-origin"
							allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
							allowfullscreen></iframe>
					</div>
				<?php else : ?>
					<div class="sx-empty">Vidéo non disponible.</div>
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

				<?php if ( $timestamps && $source_embed_url && ! $show_locked ) : ?>
					<section class="sx-video-passages" aria-labelledby="sx-video-passages-title">
						<h3 id="sx-video-passages-title">Passages clés</h3>
						<ol class="sx-video-passages__list">
							<?php foreach ( $timestamps as $passage ) :
								$seconds   = (int) $passage['seconds'];
								$time_label = seoflix_video_timestamp_label( $seconds );
								$seek_url   = seoflix_source_embed_url( $yid, $seconds, true );
								?>
								<li class="sx-video-passages__item">
									<a
										class="sx-video-passages__link"
										href="<?php echo esc_url( $seek_url ); ?>"
										target="sx-source-player"
										aria-label="<?php echo esc_attr( sprintf( 'Lire « %1$s » à %2$s dans la vidéo source', $passage['label'], $time_label ) ); ?>">
										<time datetime="<?php echo esc_attr( 'PT' . $seconds . 'S' ); ?>"><?php echo esc_html( $time_label ); ?></time>
										<span><?php echo esc_html( $passage['label'] ); ?></span>
									</a>
									<?php if ( $passage['takeaway'] !== '' ) : ?>
										<p><?php echo esc_html( $passage['takeaway'] ); ?></p>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ol>
					</section>
				<?php endif; ?>
				</section>
			</div>

			<aside class="sx-video-page__sidebar">
				<?php if ( $products ) : ?>
					<div class="sx-video-page__products">
						<span class="sx-affiliate-notice">Liens affiliés</span>
						<h2>Produits &amp; services mentionnés</h2>
						<div class="sx-product-list">
							<?php foreach ( $products as $p ) {
								seoflix_render_product_card( $p, [ 'show_pricing' => false, 'compact' => true, 'affiliate_link' => true ] );
							} ?>
						</div>
					</div>
				<?php else :
					$fallback_ids = array_map( 'intval', (array) get_option( 'seoflix_default_fallback_products', [] ) );
					$fallback_ids = array_slice( array_filter( $fallback_ids ), 0, 3 );
					$fallback_products = $fallback_ids ? get_posts( [
						'post_type'      => 'seoflix_product',
						'post_status'    => 'publish',
						'post__in'       => $fallback_ids,
						'orderby'        => 'post__in',
						'posts_per_page' => 3,
					] ) : [];
					?>
					<?php if ( $fallback_products ) : ?>
						<div class="sx-video-page__products sx-video-page__products--fallback">
							<span class="sx-affiliate-notice">Liens affiliés</span>
							<h2>Mais ces outils pourraient t'intéresser</h2>
							<div class="sx-product-list">
								<?php foreach ( $fallback_products as $p ) {
									seoflix_render_product_card( $p, [ 'show_pricing' => false, 'compact' => true, 'affiliate_link' => true ] );
								} ?>
							</div>
							<p style="margin-top: var(--sx-space-3);"><a class="sx-btn sx-btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_product' ) ); ?>">Voir tous les outils →</a></p>
						</div>
					<?php else : ?>
						<div class="sx-video-page__products sx-video-page__products--empty">
							<h2>Aucun produit référencé pour cette vidéo</h2>
							<p>Découvre tous les outils SEO recommandés sur WEAS.</p>
							<p><a class="sx-btn sx-btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_product' ) ); ?>">Voir le catalogue</a></p>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</aside>
		</div>

		<div class="sx-video-page__description">
			<h2>Ce que couvre cette vidéo</h2>
			<?php the_content(); ?>
		</div>

		<?php if ( $key_concepts ) : ?>
			<section class="sx-video-concepts" aria-labelledby="sx-video-concepts-title">
				<h2 id="sx-video-concepts-title">Points à retenir</h2>
				<ul class="sx-video-concepts__list">
					<?php foreach ( $key_concepts as $concept ) : ?>
						<li><?php echo esc_html( $concept['text'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( ! $show_locked && $source_embed_url ) : ?>
			<?php if ( $editorial_url ) : ?>
				<section class="sx-madias-capsule" data-sx-player="madias" aria-labelledby="sx-madias-capsule-title">
					<div class="sx-madias-capsule__heading">
						<p class="sx-madias-capsule__eyebrow">Éclairage éditorial</p>
						<h2 id="sx-madias-capsule-title">L’essentiel par WEAS</h2>
					</div>
					<div class="sx-player sx-madias-capsule__player">
						<iframe
							src="<?php echo esc_url( $editorial_url ); ?>"
							title="<?php echo esc_attr( sprintf( 'L’essentiel par WEAS : %s', get_the_title() ) ); ?>"
							loading="lazy"
							referrerpolicy="strict-origin-when-cross-origin"
							allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
							allowfullscreen></iframe>
					</div>
				</section>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( class_exists( '\Seoflix\FeatureFlags' ) && \Seoflix\FeatureFlags::video_discussions_enabled() ) : ?>
			<?php get_template_part( 'comments-video' ); ?>
		<?php endif; ?>

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
