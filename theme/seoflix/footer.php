</main>

<?php
// Newsletter dans le footer (sauf sur les pages auth pour ne pas confondre)
if ( ! is_front_page()
	&& ! is_page( [ 'connexion', 'inscription', 'mot-de-passe-oublie' ] )
	&& ! ( function_exists( 'get_query_var' ) && ( get_query_var( 'seoflix_setpwd' ) || get_query_var( 'seoflix_activate' ) || get_query_var( 'seoflix_dashboard' ) ) )
	&& function_exists( 'seoflix_render_newsletter' ) ) {
	echo '<div class="sx-container sx-newsletter-footer-wrap">';
	seoflix_render_newsletter( \seoflix\Newsletter::SOURCE_FOOTER, [ 'compact' => true, 'title' => 'Newsletter WEAS' ] );
	echo '</div>';
}
?>

<footer class="sx-site-footer">
	<div class="sx-container">
		<div class="sx-site-footer__cols">

			<div class="sx-footer-col">
				<?php if ( is_active_sidebar( 'sx-footer-1' ) ) : ?>
					<?php dynamic_sidebar( 'sx-footer-1' ); ?>
				<?php else : ?>
					<h3>WEAS</h3>
					<p style="color: var(--sx-color-text-muted); font-size: 0.9rem; line-height: 1.5;">Les meilleures vidéos business web, sélectionnées et organisées pour apprendre sans perdre de temps.</p>
				<?php endif; ?>
			</div>

			<div class="sx-footer-col">
				<?php if ( is_active_sidebar( 'sx-footer-2' ) ) : ?>
					<?php dynamic_sidebar( 'sx-footer-2' ); ?>
				<?php else : ?>
					<h3>Explorer</h3>
					<ul>
						<li><a href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_video' ) ?: home_url( '/videos/' ) ); ?>">Toutes les vidéos</a></li>
						<li><a href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_channel' ) ?: home_url( '/chaines/' ) ); ?>">Chaînes</a></li>
						<li><a href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_product' ) ?: home_url( '/outils/' ) ); ?>">Outils &amp; ressources</a></li>
					</ul>
				<?php endif; ?>
			</div>

			<div class="sx-footer-col">
				<?php if ( is_active_sidebar( 'sx-footer-3' ) ) : ?>
					<?php dynamic_sidebar( 'sx-footer-3' ); ?>
				<?php else : ?>
					<h3>Sujets</h3>
					<ul>
						<?php
						$topics = get_terms( [
							'taxonomy'   => 'seoflix_topic',
							'hide_empty' => true,
							'number'     => 8,
							'slug__in'   => [ 'seo-technique', 'netlinking', 'affiliation', 'vente-de-liens', 'youtube', 'business-general', 'black-hat', 'mindset-business' ],
						] );
						if ( ! is_wp_error( $topics ) ) {
							foreach ( $topics as $t ) {
								echo '<li><a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a></li>';
							}
						}
						?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="sx-footer-col">
				<?php if ( is_active_sidebar( 'sx-footer-4' ) ) : ?>
					<?php dynamic_sidebar( 'sx-footer-4' ); ?>
				<?php else : ?>
					<h3>Légal &amp; contact</h3>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a></li>
						<li><a href="<?php echo esc_url( home_url( '/affiliation/' ) ); ?>">Politique d'affiliation</a></li>
						<li><a href="<?php echo esc_url( home_url( '/mentions-legales/' ) ); ?>">Mentions légales</a></li>
						<li><a href="<?php echo esc_url( home_url( '/confidentialite/' ) ); ?>">Confidentialité</a></li>
					</ul>
				<?php endif; ?>
			</div>

		</div>

		<div class="sx-site-footer__bottom">
			<span>© <?php echo esc_html( date( 'Y' ) ); ?> WEAS.</span>
			<span>Certains liens sont des liens d'affiliation. <a href="<?php echo esc_url( home_url( '/affiliation/' ) ); ?>">En savoir plus</a>.</span>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
