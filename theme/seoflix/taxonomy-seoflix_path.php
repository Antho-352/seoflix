<?php
/**
 * Archive taxonomie : Parcours d'apprentissage.
 *
 * Mode "expérience d'apprentissage" : si l'utilisateur est connecté (V2),
 * affiche la progression + le bouton "Continuer". Sinon affiche la liste ordonnée.
 */
get_header();

$term       = get_queried_object();
$user_id    = get_current_user_id();
$accounts_on = class_exists( '\Seoflix\FeatureFlags' ) && \Seoflix\FeatureFlags::user_accounts_enabled();

// Récupère TOUTES les vidéos du parcours, ordonnées
$videos_query = new WP_Query( [
	'post_type'      => 'seoflix_video',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'tax_query'      => [
		[ 'taxonomy' => 'seoflix_path', 'field' => 'term_id', 'terms' => $term->term_id ],
	],
	'meta_key'       => '_seoflix_path_order',
	'orderby'        => [ 'meta_value_num' => 'ASC', 'date' => 'ASC' ],
] );

$total = (int) $videos_query->found_posts;

$progress = null;
if ( $accounts_on && $user_id ) {
	$progress = \Seoflix\User_Accounts::path_progress( $user_id, $term->term_id );
}
?>

<div class="sx-container sx-page sx-path-archive">

	<header class="sx-archive-header sx-path-header">
		<div class="sx-archive-header__kicker">Parcours d'apprentissage</div>
		<h1 class="sx-archive-header__title"><?php echo esc_html( $term->name ); ?></h1>
		<?php if ( $term->description ) : ?>
			<p class="sx-path-header__desc"><?php echo esc_html( $term->description ); ?></p>
		<?php endif; ?>
		<p class="sx-archive-header__count"><?php echo esc_html( number_format_i18n( $total ) ); ?> vidéo<?php echo $total > 1 ? 's' : ''; ?> dans ce parcours</p>

		<?php if ( $progress && $total > 0 ) :
			$percent = (int) round( ( $progress['watched'] / $total ) * 100 ); ?>
			<div class="sx-path-progress">
				<div class="sx-progress sx-progress--lg">
					<div class="sx-progress__bar" style="width: <?php echo (int) $percent; ?>%"></div>
				</div>
				<div class="sx-path-progress__meta">
					<strong><?php echo (int) $progress['watched']; ?> / <?php echo (int) $total; ?></strong> vidéos vues — <?php echo (int) $percent; ?>%
					<?php if ( $progress['next_video_id'] ) : ?>
						<a class="sx-btn" href="<?php echo esc_url( get_permalink( $progress['next_video_id'] ) ); ?>">
							<?php echo $progress['watched'] > 0 ? 'Continuer →' : 'Commencer →'; ?>
						</a>
					<?php elseif ( $progress['watched'] === $total ) : ?>
						<span class="sx-path-done">✓ Parcours terminé !</span>
					<?php endif; ?>
				</div>
			</div>
		<?php elseif ( $accounts_on && ! $user_id ) : ?>
			<div class="sx-path-cta">
				<p>Crée un compte pour suivre ta progression sur ce parcours.</p>
				<a class="sx-btn" href="<?php echo esc_url( wp_registration_url() ); ?>">Créer un compte gratuit</a>
				<a class="sx-btn sx-btn--ghost" href="<?php echo esc_url( wp_login_url( get_term_link( $term ) ) ); ?>">Se connecter</a>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( $videos_query->have_posts() ) : ?>
		<ol class="sx-path-list">
			<?php $idx = 0;
			while ( $videos_query->have_posts() ) : $videos_query->the_post(); $idx++;
				$vid       = get_the_ID();
				$is_done   = $user_id ? \Seoflix\User_Accounts::is_video_watched( $user_id, $vid ) : false;
				$thumb     = seoflix_video_thumbnail_url( $vid );
				$duration  = seoflix_video_duration_formatted( $vid );
				?>
				<li class="sx-path-item <?php echo $is_done ? 'is-done' : ''; ?>">
					<div class="sx-path-item__num"><?php echo $is_done ? '✓' : (string) $idx; ?></div>
					<a class="sx-path-item__thumb" href="<?php echo esc_url( get_permalink() ); ?>">
						<?php if ( $thumb ) : ?>
							<img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy">
						<?php endif; ?>
						<?php if ( $duration ) : ?>
							<span class="sx-path-item__duration"><?php echo esc_html( $duration ); ?></span>
						<?php endif; ?>
					</a>
					<div class="sx-path-item__body">
						<h3><a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_title(); ?></a></h3>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt() ?: get_the_content(), 25 ) ); ?></p>
					</div>
				</li>
			<?php endwhile;
			wp_reset_postdata(); ?>
		</ol>
	<?php else : ?>
		<div class="sx-empty">Aucune vidéo dans ce parcours pour le moment.</div>
	<?php endif; ?>

</div>

<?php get_footer();
