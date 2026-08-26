<?php
/**
 * Index éditorial des six parcours MADIAS.
 * Route dédiée : /parcours/ (aucune page WordPress requise).
 */
get_header();

$path_catalog = \Seoflix\Homepage::path_definitions();
$accounts_on  = function_exists( '\Seoflix\seoflix_user_accounts_enabled' ) && \Seoflix\seoflix_user_accounts_enabled();
$show_progress = $accounts_on && is_user_logged_in();
$user_id       = $show_progress ? get_current_user_id() : 0;
?>

<div class="sx-container sx-page sx-paths-index">
	<nav class="sx-breadcrumbs" aria-label="Fil d’Ariane">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil</a>
		<span aria-hidden="true">/</span>
		<span aria-current="page">Parcours</span>
	</nav>

	<header class="sx-paths-index__header">
		<p class="sx-home-section__kicker">Apprendre sans s'éparpiller</p>
		<h1>Les parcours MADIAS</h1>
		<p>Six chemins éditoriaux construits à partir de vidéos réellement publiées. Choisis un sujet, puis avance dans l'ordre.</p>
	</header>

	<div class="sx-paths-index__grid">
		<?php foreach ( $path_catalog as $definition ) :
			$term = get_term_by( 'slug', $definition['slug'], 'seoflix_path' );
			if ( ! $term || is_wp_error( $term ) ) : ?>
				<article class="sx-path-card sx-path-card--index sx-path-card--unavailable">
					<span class="sx-path-card__icon" aria-hidden="true"><?php echo esc_html( $definition['icon'] ); ?></span>
					<div class="sx-path-card__body">
						<h2 class="sx-path-card__title"><?php echo esc_html( $definition['name'] ); ?></h2>
						<p class="sx-path-card__description">Parcours indisponible : le terme n'existe pas encore.</p>
						<p class="sx-path-card__meta">Aucune vidéo publiée.</p>
					</div>
				</article>
				<?php continue; ?>
			<?php endif;

			$ordered_ids = \Seoflix\Path_Order::ordered_video_ids_for_term( (int) $term->term_id );
			$videos = $ordered_ids ? get_posts( [
				'post_type'      => 'seoflix_video',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'post__in'       => $ordered_ids,
				'orderby'        => 'post__in',
			] ) : [];
			$count       = count( $videos );
			$description = trim( wp_strip_all_tags( $term->description ) );
			$total_seconds = 0;
			$all_durations_known = $count > 0;
			foreach ( $videos as $video ) {
				$seconds = (int) get_post_meta( $video->ID, \Seoflix\Meta_Keys::VIDEO_DURATION, true );
				if ( $seconds <= 0 ) {
					$all_durations_known = false;
					break;
				}
				$total_seconds += $seconds;
			}
			$duration_label = '';
			if ( $all_durations_known && $total_seconds > 0 ) {
				$hours   = intdiv( $total_seconds, HOUR_IN_SECONDS );
				$minutes = (int) ceil( ( $total_seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
				$duration_label = $hours > 0
					? sprintf( '%dh %02dmin', $hours, $minutes )
					: sprintf( '%d min', max( 1, $minutes ) );
			}
			$progress = $show_progress ? \Seoflix\User_Accounts::path_progress( $user_id, (int) $term->term_id ) : null;
			$watched  = $progress ? min( $count, max( 0, (int) $progress['watched'] ) ) : 0;
			$start_url = '';
			$start_label = 'Commencer';
			if ( $count > 0 && isset( $videos[0]->ID ) ) {
				$first_url = get_permalink( (int) $videos[0]->ID );
				$start_url = is_string( $first_url ) ? $first_url : '';
			}
			if ( $progress && ! empty( $progress['next_video_id'] ) && 'publish' === get_post_status( (int) $progress['next_video_id'] ) ) {
				$next_url = get_permalink( (int) $progress['next_video_id'] );
				if ( is_string( $next_url ) ) {
					$start_url   = $next_url;
					$start_label = $watched > 0 ? 'Continuer' : 'Commencer';
				}
			}
			?>
			<article class="sx-path-card sx-path-card--index">
				<span class="sx-path-card__icon" aria-hidden="true"><?php echo esc_html( $definition['icon'] ); ?></span>
				<div class="sx-path-card__body">
					<h2 class="sx-path-card__title"><a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $definition['name'] ); ?></a></h2>
					<p class="sx-path-card__description"><?php echo esc_html( $description ?: 'Description indisponible.' ); ?></p>
					<p class="sx-path-card__meta">
						<?php echo esc_html( sprintf( _n( '%d vidéo publiée', '%d vidéos publiées', $count, 'seoflix' ), $count ) ); ?>
						<?php if ( $duration_label ) : ?>
							<span aria-hidden="true">·</span> <?php echo esc_html( 'Durée totale : ' . $duration_label ); ?>
						<?php endif; ?>
					</p>

					<?php if ( $progress && $count > 0 ) : ?>
						<div class="sx-path-card__account-progress">
							<label for="path-progress-<?php echo (int) $term->term_id; ?>"><?php echo esc_html( sprintf( '%d sur %d vidéos vues', $watched, $count ) ); ?></label>
							<progress id="path-progress-<?php echo (int) $term->term_id; ?>" value="<?php echo (int) $watched; ?>" max="<?php echo (int) $count; ?>"><?php echo (int) $watched; ?> / <?php echo (int) $count; ?></progress>
						</div>
					<?php endif; ?>
					<?php if ( $count > 0 && $start_url ) : ?>
						<a class="sx-path-card__continue" href="<?php echo esc_url( $start_url ); ?>"><?php echo esc_html( $start_label ); ?></a>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</div>

<?php get_footer();
