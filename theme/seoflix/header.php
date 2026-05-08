<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php if ( ! has_site_icon() ) : ?>
		<link rel="icon" type="image/svg+xml" href="<?php echo esc_url( get_theme_file_uri( 'assets/images/favicon.svg' ) ); ?>">
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
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="sx-logo" aria-label="Seoflix — accueil">
			<svg class="sx-logo__mark" viewBox="0 0 100 100" width="28" height="28" aria-hidden="true">
				<rect width="100" height="100" rx="22" fill="#16161D"/>
				<path d="M50 28 L74 72 L26 72 Z" fill="#FF2D3F"/>
			</svg>
			<span class="sx-logo__text">seo<em>flix</em></span>
		</a>

		<nav class="sx-nav sx-nav--desktop" aria-label="Navigation principale">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( [
					'theme_location' => 'primary',
					'container'      => false,
					'items_wrap'     => '%3$s',
					'walker'         => new Seoflix_Nav_Walker(),
					'depth'          => 1,
					'fallback_cb'    => false,
				] );
			} else {
				seoflix_default_primary_menu();
			}
			?>
		</nav>

		<form role="search" method="get" class="sx-search-form sx-search-form--desktop" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="search" name="s" placeholder="Rechercher…" value="<?php echo esc_attr( get_search_query() ); ?>" aria-label="Rechercher">
		</form>

		<?php if ( function_exists( '\\Seoflix\\seoflix_user_accounts_enabled' ) && \Seoflix\seoflix_user_accounts_enabled() ) : ?>
			<div class="sx-user-cta sx-user-cta--desktop">
				<?php if ( is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( home_url( '/mon-parcours/' ) ); ?>" class="sx-user-cta__link">Mon parcours</a>
				<?php else : ?>
					<a href="<?php echo esc_url( wp_login_url() ); ?>" class="sx-user-cta__link">Connexion</a>
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
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="sx-logo" data-sx-close>
				<svg class="sx-logo__mark" viewBox="0 0 100 100" width="28" height="28" aria-hidden="true">
					<rect width="100" height="100" rx="22" fill="#16161D"/>
					<path d="M50 28 L74 72 L26 72 Z" fill="#FF2D3F"/>
				</svg>
				<span class="sx-logo__text">seo<em>flix</em></span>
			</a>
			<button type="button" class="sx-drawer__close" aria-label="Fermer le menu" data-sx-close>×</button>
		</div>

		<form role="search" method="get" class="sx-search-form sx-search-form--drawer" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="search" name="s" placeholder="Rechercher…" value="<?php echo esc_attr( get_search_query() ); ?>" aria-label="Rechercher">
		</form>

		<nav class="sx-nav sx-nav--drawer" aria-label="Navigation mobile">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( [
					'theme_location' => 'primary',
					'container'      => false,
					'items_wrap'     => '%3$s',
					'walker'         => new Seoflix_Nav_Walker(),
					'depth'          => 1,
					'fallback_cb'    => false,
				] );
			} else {
				seoflix_default_primary_menu();
			}
			?>
		</nav>

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
