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

		<nav class="sx-nav" aria-label="Navigation principale">
			<a href="<?php echo esc_url( home_url( '/sujet/seo-technique/' ) ); ?>">SEO</a>
			<a href="<?php echo esc_url( home_url( '/sujet/affiliation/' ) ); ?>">Affiliation</a>
			<a href="<?php echo esc_url( home_url( '/sujet/youtube/' ) ); ?>">YouTube</a>
			<a href="<?php echo esc_url( home_url( '/sujet/vente-de-liens/' ) ); ?>">Vente de liens</a>
			<a href="<?php echo esc_url( home_url( '/sujet/business-general/' ) ); ?>">Business</a>
			<a href="<?php echo esc_url( home_url( '/categories/' ) ); ?>">Toutes les catégories</a>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_channel' ) ?: home_url( '/chaines/' ) ); ?>">Chaînes</a>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_product' ) ?: home_url( '/outils/' ) ); ?>">Outils SEO</a>
		</nav>

		<form role="search" method="get" class="sx-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="search" name="s" placeholder="Rechercher…" value="<?php echo esc_attr( get_search_query() ); ?>" aria-label="Rechercher">
		</form>
	</div>
</header>

<main class="sx-main">
