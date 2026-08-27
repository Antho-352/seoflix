<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php if ( ! has_site_icon() ) : ?>
		<link rel="icon" href="<?php echo esc_url( get_theme_file_uri( 'assets/images/favicon.ico' ) ); ?>" sizes="any">
		<link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url( get_theme_file_uri( 'assets/images/favicon-32.png' ) ); ?>">
		<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( get_theme_file_uri( 'assets/images/favicon-180.png' ) ); ?>">
	<?php endif; ?>
	<meta name="theme-color" content="#0B0B0F">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="sx-site-header">
	<div class="sx-container sx-site-header__inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="sx-logo" aria-label="WEAS — accueil">
			<img class="sx-logo__image" src="<?php echo esc_url( get_theme_file_uri( 'assets/images/logo-weas.png' ) ); ?>" width="942" height="168" alt="" aria-hidden="true">
		</a>

		<nav class="sx-nav sx-nav--desktop" aria-label="Navigation principale">
			<?php seoflix_render_primary_menu(); ?>
		</nav>

		<a class="sx-header-arsenal" href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_product' ) ?: home_url( '/outils/' ) ); ?>">L’ARSENAL</a>

		<form role="search" method="get" class="sx-search-form sx-search-form--desktop" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="search" name="s" placeholder="Rechercher…" value="<?php echo esc_attr( get_search_query() ); ?>" aria-label="Rechercher">
		</form>

		<?php if ( function_exists( '\\Seoflix\\seoflix_user_accounts_enabled' ) && \Seoflix\seoflix_user_accounts_enabled() ) : ?>
			<div class="sx-user-cta sx-user-cta--desktop">
				<?php if ( is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( home_url( '/mon-parcours/' ) ); ?>" class="sx-user-cta__link sx-user-cta__link--ghost">Mon parcours</a>
				<?php else : ?>
					<a href="<?php echo esc_url( wp_login_url() ); ?>" class="sx-user-cta__link sx-user-cta__link--ghost">Connexion</a>
					<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="sx-user-cta__link sx-user-cta__link--accent">Inscription</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<button type="button" class="sx-burger" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="sx-drawer">
			<span></span><span></span><span></span>
		</button>
	</div>
</header>

<div class="sx-drawer" id="sx-drawer" aria-hidden="true">
	<div class="sx-drawer__overlay" data-sx-close></div>
	<aside class="sx-drawer__panel" role="dialog" aria-modal="true" aria-label="Menu de navigation">
		<div class="sx-drawer__head">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="sx-logo" aria-label="WEAS — accueil" data-sx-close>
				<img class="sx-logo__image" src="<?php echo esc_url( get_theme_file_uri( 'assets/images/logo-weas.png' ) ); ?>" width="942" height="168" alt="" aria-hidden="true">
			</a>
			<button type="button" class="sx-drawer__close" aria-label="Fermer le menu" data-sx-close>×</button>
		</div>

		<form role="search" method="get" class="sx-search-form sx-search-form--drawer" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="search" name="s" placeholder="Rechercher…" value="<?php echo esc_attr( get_search_query() ); ?>" aria-label="Rechercher">
		</form>

		<nav class="sx-nav sx-nav--drawer" aria-label="Navigation mobile">
			<?php seoflix_render_primary_menu(); ?>
		</nav>
		<a class="sx-btn sx-drawer__arsenal" href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_product' ) ?: home_url( '/outils/' ) ); ?>" data-sx-close>L’ARSENAL</a>

		<?php if ( is_user_logged_in() ) : ?>
			<div class="sx-drawer__account">
				<a href="<?php echo esc_url( home_url( '/mon-parcours/' ) ); ?>" class="sx-btn sx-btn--ghost" data-sx-close>Mon parcours</a>
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" class="sx-drawer__small">Se déconnecter</a>
			</div>
		<?php elseif ( get_option( 'seoflix_user_accounts_enabled' ) ) : ?>
			<div class="sx-drawer__account">
				<a href="<?php echo esc_url( wp_login_url() ); ?>" class="sx-btn sx-btn--ghost" data-sx-close>Se connecter</a>
				<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="sx-btn" data-sx-close>Créer un compte</a>
			</div>
		<?php endif; ?>
	</aside>
</div>
<script>
(function(){
	const burger = document.querySelector('.sx-burger');
	const drawer = document.getElementById('sx-drawer');
	if (!burger || !drawer) return;
	const closeEls = drawer.querySelectorAll('[data-sx-close]');
	function open(){ drawer.classList.add('is-open'); burger.setAttribute('aria-expanded','true'); drawer.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; }
	function close(){ drawer.classList.remove('is-open'); burger.setAttribute('aria-expanded','false'); drawer.setAttribute('aria-hidden','true'); document.body.style.overflow=''; }
	burger.addEventListener('click', () => drawer.classList.contains('is-open') ? close() : open());
	closeEls.forEach(el => el.addEventListener('click', close));
	document.addEventListener('keydown', e => { if (e.key === 'Escape' && drawer.classList.contains('is-open')) close(); });
})();
</script>

<main class="sx-main">
