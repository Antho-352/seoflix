</main>

<footer class="sx-site-footer">
	<div class="sx-container">
		<div class="sx-site-footer__cols">
			<div>
				<h3>Seoflix</h3>
				<p style="color: var(--sx-color-text-muted); font-size: 0.9rem; line-height: 1.5;">L'agrégation des meilleures vidéos YouTube SEO francophones. SEO, netlinking, affiliation, vente de liens, business web.</p>
			</div>

			<div>
				<h3>Explorer</h3>
				<ul>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_video' ) ?: home_url( '/videos/' ) ); ?>">Toutes les vidéos</a></li>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_channel' ) ?: home_url( '/chaines/' ) ); ?>">Chaînes</a></li>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_product' ) ?: home_url( '/outils/' ) ); ?>">Outils & ressources</a></li>
				</ul>
			</div>

			<div>
				<h3>Sujets</h3>
				<ul>
					<?php
					$topics = get_terms( [
						'taxonomy'   => 'seoflix_topic',
						'hide_empty' => true,
						'number'     => 8,
					] );
					if ( ! is_wp_error( $topics ) ) {
						foreach ( $topics as $t ) {
							echo '<li><a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a></li>';
						}
					}
					?>
				</ul>
			</div>

			<div>
				<h3>Légal</h3>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/affiliation/' ) ); ?>">Politique d'affiliation</a></li>
					<li><a href="<?php echo esc_url( home_url( '/mentions-legales/' ) ); ?>">Mentions légales</a></li>
					<li><a href="<?php echo esc_url( home_url( '/confidentialite/' ) ); ?>">Confidentialité</a></li>
				</ul>
			</div>
		</div>

		<div class="sx-site-footer__bottom">
			<span>© <?php echo esc_html( date( 'Y' ) ); ?> Seoflix.</span>
			<span>Certains liens sont des liens d'affiliation. <a href="<?php echo esc_url( home_url( '/affiliation/' ) ); ?>">En savoir plus</a>.</span>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
