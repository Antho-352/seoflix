<?php
get_header();
?>

<div class="sx-container">
	<div class="sx-404">
		<div class="sx-404__code">404</div>
		<h1 class="sx-404__title">Cette vidéo n'existe pas.</h1>
		<p class="sx-404__subtitle">Le contenu que tu cherches a peut-être été supprimé ou déplacé.</p>
		<div class="sx-404__cta">
			<a class="sx-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Retour à l'accueil</a>
		</div>
	</div>
</div>

<?php get_footer();
