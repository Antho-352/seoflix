<?php
/**
 * Template Name: Mon parcours (V2 dashboard utilisateur)
 *
 * Utilisé soit via la page "Mon parcours" créée à la main et assignée à ce template,
 * soit via la rewrite rule /mon-parcours/ qui set query_var seoflix_dashboard.
 */

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( wp_login_url( home_url( '/mon-parcours/' ) ) );
	exit;
}

if ( ! class_exists( '\Seoflix\User_Accounts' ) || ! \Seoflix\FeatureFlags::user_accounts_enabled() ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

get_header();

$user      = wp_get_current_user();
$user_id   = (int) $user->ID;
$favorites = \Seoflix\User_Accounts::user_favorite_video_ids( $user_id, 24 );
$watched   = \Seoflix\User_Accounts::user_watched_video_ids( $user_id, 24 );
$paths     = get_terms( [ 'taxonomy' => 'seoflix_path', 'hide_empty' => false ] );
?>

<div class="sx-container sx-page sx-dashboard">

	<header class="sx-dashboard__header">
		<h1>Salut <?php echo esc_html( $user->display_name ); ?> 👋</h1>
		<p class="sx-dashboard__lead">Voici ton tableau de bord d'apprentissage. Reprends là où tu t'étais arrêté ou explore un nouveau parcours.</p>
	</header>

	<?php
	// Cartes des parcours avec progression
	if ( ! is_wp_error( $paths ) && $paths ) : ?>
		<section class="sx-row">
			<div class="sx-row__header">
				<h2 class="sx-row__title">Tes parcours d'apprentissage</h2>
			</div>
			<div class="sx-grid sx-grid--paths">
				<?php foreach ( $paths as $path ) :
					$prog    = \Seoflix\User_Accounts::path_progress( $user_id, $path->term_id );
					$total   = $prog['total'];
					$done    = $prog['watched'];
					$percent = $total > 0 ? (int) round( ( $done / $total ) * 100 ) : 0;
					$next_id = $prog['next_video_id'];
					?>
					<article class="sx-card-path">
						<header class="sx-card-path__head">
							<h3><a href="<?php echo esc_url( get_term_link( $path ) ); ?>"><?php echo esc_html( $path->name ); ?></a></h3>
							<span class="sx-card-path__count"><?php echo esc_html( $done . ' / ' . $total ); ?></span>
						</header>
						<div class="sx-progress">
							<div class="sx-progress__bar" style="width: <?php echo (int) $percent; ?>%"></div>
						</div>
						<footer class="sx-card-path__foot">
							<?php if ( $next_id && $total > 0 ) : ?>
								<a class="sx-btn sx-btn--ghost" href="<?php echo esc_url( get_permalink( $next_id ) ); ?>">
									<?php echo $done > 0 ? 'Continuer →' : 'Commencer →'; ?>
								</a>
							<?php elseif ( $total > 0 && $done === $total ) : ?>
								<span class="sx-card-path__done">✓ Parcours terminé</span>
							<?php else : ?>
								<a class="sx-btn sx-btn--ghost" href="<?php echo esc_url( get_term_link( $path ) ); ?>">Voir →</a>
							<?php endif; ?>
						</footer>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	// Vidéos vues récemment
	if ( $watched ) :
		$watched_videos = get_posts( [
			'post_type'      => 'seoflix_video',
			'post__in'       => $watched,
			'orderby'        => 'post__in',
			'posts_per_page' => -1,
		] );
		seoflix_render_video_row( 'Vidéos vues récemment', $watched_videos );
	endif;

	// Favoris
	if ( $favorites ) :
		$fav_videos = get_posts( [
			'post_type'      => 'seoflix_video',
			'post__in'       => $favorites,
			'orderby'        => 'post__in',
			'posts_per_page' => -1,
		] );
		seoflix_render_video_row( 'Tes favoris', $fav_videos );
	else : ?>
		<section class="sx-card" style="margin-top: 2rem;">
			<h2>Aucun favori pour le moment</h2>
			<p>Clique sur le ❤ d'une vidéo pour l'ajouter à tes favoris et la retrouver ici.</p>
			<p><a class="sx-btn" href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_video' ) ); ?>">Explorer les vidéos →</a></p>
		</section>
	<?php endif; ?>

	<footer class="sx-dashboard__foot">
		<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" class="sx-link-muted">Se déconnecter</a>
	</footer>

</div>

<?php get_footer();
