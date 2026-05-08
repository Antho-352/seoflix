<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 *  Setup
 * ============================================================ */

add_action( 'after_setup_theme', static function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );

	register_nav_menus( [
		'primary' => 'Menu principal',
		'footer'  => 'Menu pied de page',
	] );
} );

/* ============================================================
 *  Sidebars (zones de widgets) — 4 colonnes du footer
 *  Configurables depuis Apparence → Widgets
 * ============================================================ */

add_action( 'widgets_init', static function () {
	$columns = [
		[ 'id' => 'sx-footer-1', 'name' => 'Pied de page — Colonne 1 (Branding)' ],
		[ 'id' => 'sx-footer-2', 'name' => 'Pied de page — Colonne 2 (Explorer)' ],
		[ 'id' => 'sx-footer-3', 'name' => 'Pied de page — Colonne 3 (Sujets)' ],
		[ 'id' => 'sx-footer-4', 'name' => 'Pied de page — Colonne 4 (Légal)' ],
	];
	foreach ( $columns as $col ) {
		register_sidebar( [
			'id'            => $col['id'],
			'name'          => $col['name'],
			'description'   => 'Glisse-dépose ici un widget « Menu de navigation », « HTML personnalisé » ou « Liste personnalisée ». Si vide, le contenu par défaut s\'affiche.',
			'before_widget' => '<div class="sx-footer-widget %2$s" id="%1$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3>',
			'after_title'   => '</h3>',
		] );
	}
} );

/* ============================================================
 *  Assets
 * ============================================================ */

add_action( 'wp_enqueue_scripts', static function () {
	$ver = wp_get_theme()->get( 'Version' );
	wp_enqueue_style( 'seoflix-tokens',     get_theme_file_uri( 'assets/css/tokens.css' ),     [], $ver );
	wp_enqueue_style( 'seoflix-reset',      get_theme_file_uri( 'assets/css/reset.css' ),      [ 'seoflix-tokens' ], $ver );
	wp_enqueue_style( 'seoflix-layout',     get_theme_file_uri( 'assets/css/layout.css' ),     [ 'seoflix-reset' ], $ver );
	wp_enqueue_style( 'seoflix-components', get_theme_file_uri( 'assets/css/components.css' ), [ 'seoflix-layout' ], $ver );
	wp_enqueue_style( 'seoflix-pages',      get_theme_file_uri( 'assets/css/pages.css' ),      [ 'seoflix-components' ], $ver );

	// Toggle eye sur les champs password (toutes les pages auth)
	$toggle_js = <<<'JS'
	document.addEventListener('click', function(e) {
		const btn = e.target.closest('.sx-input-pwd__toggle');
		if (!btn) return;
		const wrap = btn.closest('.sx-input-pwd');
		const input = wrap && wrap.querySelector('input');
		if (!input) return;
		const isPwd = input.type === 'password';
		input.type = isPwd ? 'text' : 'password';
		const show = btn.querySelector('.sx-eye--show');
		const hide = btn.querySelector('.sx-eye--hide');
		if (show && hide) {
			show.style.display = isPwd ? 'none' : '';
			hide.style.display = isPwd ? '' : 'none';
		}
		btn.setAttribute('aria-label', isPwd ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
	});
	JS;
	wp_register_script( 'seoflix-pwd-toggle', '', [], $ver, true );
	wp_enqueue_script( 'seoflix-pwd-toggle' );
	wp_add_inline_script( 'seoflix-pwd-toggle', $toggle_js );

	// JS user actions (favoris + watched) — uniquement si user loggé et V2 actif
	if ( is_user_logged_in() && function_exists( '\\Seoflix\\seoflix_user_accounts_enabled' ) && \Seoflix\seoflix_user_accounts_enabled() ) {
		$ajax_url = esc_js( admin_url( 'admin-ajax.php' ) );
		$inline_js = "
		(function(){
			const AJAX_URL = '{$ajax_url}';
			document.addEventListener('click', function(e) {
				// Cas 1 : bouton coeur sur card vidéo (homepage, archives)
				const fav = e.target.closest('.sx-card-video__fav');
				if (fav) {
					e.preventDefault();
					e.stopPropagation();
					if (fav.disabled) return;
					fav.disabled = true;
					const fd = new FormData();
					fd.append('action', 'seoflix_toggle_favorite');
					fd.append('_ajax_nonce', fav.dataset.nonce);
					fd.append('video_id', fav.dataset.videoId);
					fetch(AJAX_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
						.then(r => r.json())
						.then(json => {
							if (!json.success) throw new Error(json.data || 'Erreur');
							fav.classList.toggle('is-on');
							const on = fav.classList.contains('is-on');
							fav.setAttribute('aria-pressed', on ? 'true' : 'false');
							const label = on ? 'Retirer des favoris' : 'Ajouter aux favoris';
							fav.setAttribute('aria-label', label);
							fav.setAttribute('title', label);
						})
						.catch(err => console.warn(err))
						.finally(() => { fav.disabled = false; });
					return;
				}
				// Cas 2 : boutons sous le player (single video)
				const btn = e.target.closest('.sx-action');
				if (!btn) return;
				const wrap = btn.closest('.sx-video-page__actions');
				if (!wrap) return;
				const action = btn.dataset.action;
				btn.disabled = true;
				const fd = new FormData();
				fd.append('action', action === 'favorite' ? 'seoflix_toggle_favorite' : 'seoflix_mark_watched');
				fd.append('_ajax_nonce', wrap.dataset.nonce);
				fd.append('video_id', wrap.dataset.videoId);
				if (action === 'watched') { fd.append('completed', '1'); }
				fetch(wrap.dataset.ajax || AJAX_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(r => r.json())
					.then(json => {
						if (!json.success) throw new Error(json.data || 'Erreur');
						btn.classList.toggle('is-on');
						const on = btn.classList.contains('is-on');
						const label = btn.querySelector('.sx-action__label');
						if (action === 'favorite') {
							label.textContent = on ? 'Retirer des favoris' : 'Ajouter aux favoris';
						} else {
							label.textContent = on ? 'Vue' : 'Marquer comme vue';
						}
						btn.setAttribute('aria-pressed', on ? 'true' : 'false');
					})
					.catch(err => alert(err.message))
					.finally(() => { btn.disabled = false; });
			});
		})();
		";
		wp_register_script( 'seoflix-user-actions', '', [], $ver, true );
		wp_enqueue_script( 'seoflix-user-actions' );
		wp_add_inline_script( 'seoflix-user-actions', $inline_js );
	}
} );

/* ============================================================
 *  Nettoyer les traces visibles de WordPress dans le HTML
 * ============================================================ */

add_action( 'init', static function () {
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	remove_action( 'wp_head', 'feed_links_extra', 3 );

	// Désactive le remplacement des emojis natifs par des <img> depuis s.w.org
	// (les navigateurs modernes rendent les emojis nativement, pas besoin d'images
	// + ça causait des broken pictos sur mobile à cause de notre CSP)
	remove_action( 'wp_head',             'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles',     'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles',  'print_emoji_styles' );
	remove_filter( 'the_content_feed',    'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss',    'wp_staticize_emoji' );
	remove_filter( 'wp_mail',             'wp_staticize_emoji_for_email' );
	add_filter( 'tiny_mce_plugins', static function ( $plugins ) {
		return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : [];
	} );
	add_filter( 'emoji_svg_url', '__return_false' );
} );

add_action( 'after_setup_theme', static function () {
	if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
		show_admin_bar( false );
	}
} );

/* ============================================================
 *  Query overrides — archives custom
 * ============================================================ */

add_action( 'pre_get_posts', static function ( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	// Toutes les chaînes sur une seule page, triées alphabétiquement
	// (l'ancien tri par meta_key excluait les chaînes nouvellement créées sans subscriber_count)
	if ( $query->is_post_type_archive( 'seoflix_channel' ) ) {
		$query->set( 'posts_per_page', -1 );
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
	}

	// Vidéos par sujet/format/parcours : 24 par page
	if ( is_tax( [ 'seoflix_topic', 'seoflix_format', 'seoflix_path' ] ) ) {
		$query->set( 'posts_per_page', 24 );
	}

	// Archive des vidéos : 24 par page
	if ( $query->is_post_type_archive( 'seoflix_video' ) ) {
		$query->set( 'posts_per_page', 24 );
	}

	// Archive produits : tous sur une page (max ~50)
	if ( $query->is_post_type_archive( 'seoflix_product' ) || is_tax( 'seoflix_product_category' ) ) {
		$query->set( 'posts_per_page', -1 );
	}
} );

/* ============================================================
 *  Nav menu — walker + auto-création du menu par défaut
 * ============================================================ */

/**
 * Crée automatiquement un menu "Menu principal" pré-rempli avec les 8 items
 * et l'assigne à l'emplacement « primary », au premier chargement après activation.
 *
 * Idempotent : le flag `seoflix_default_menu_installed` empêche les doublons.
 *
 * Tu peux ensuite éditer ce menu librement dans Apparence → Menus
 * (réordonner, ajouter Blog, supprimer des items, etc.).
 *
 * Pour repartir de zéro : supprimer l'option via wp_options
 *   DELETE FROM wp_options WHERE option_name = 'seoflix_default_menu_installed';
 * puis recharger une page côté admin.
 */
add_action( 'init', static function () {
	if ( ! is_admin() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		// On ne tente la création que dans un contexte admin pour éviter les coûts en front
		return;
	}
	if ( get_option( 'seoflix_default_menu_installed' ) ) {
		return;
	}
	seoflix_install_default_menu();
}, 999 );

// Trigger explicite à l'activation du thème (au cas où l'utilisateur switche de thème)
add_action( 'after_switch_theme', 'seoflix_install_default_menu' );

function seoflix_install_default_menu(): void {
	if ( ! function_exists( 'wp_create_nav_menu' ) ) {
		require_once ABSPATH . 'wp-includes/nav-menu.php';
	}

	$menu_name = 'Menu principal';

	// Si le menu existe déjà (créé manuellement par l'utilisateur), on l'assigne juste
	$existing = wp_get_nav_menu_object( $menu_name );
	if ( $existing ) {
		$menu_id = (int) $existing->term_id;
	} else {
		$menu_id = wp_create_nav_menu( $menu_name );
		if ( is_wp_error( $menu_id ) ) {
			return;
		}
		$menu_id = (int) $menu_id;

		// Items par défaut (uniquement si le menu vient d'être créé)
		$items = [
			[ 'title' => 'SEO',                    'url' => home_url( '/sujet/seo-technique/' ) ],
			[ 'title' => 'Affiliation',            'url' => home_url( '/sujet/affiliation/' ) ],
			[ 'title' => 'YouTube',                'url' => home_url( '/sujet/youtube/' ) ],
			[ 'title' => 'Vente de liens',         'url' => home_url( '/sujet/vente-de-liens/' ) ],
			[ 'title' => 'Business',               'url' => home_url( '/sujet/business-general/' ) ],
			[ 'title' => 'Toutes les catégories',  'url' => home_url( '/categories/' ) ],
			[ 'title' => 'Chaînes',                'url' => home_url( '/chaines/' ) ],
			[ 'title' => 'Outils SEO',             'url' => home_url( '/outils/' ) ],
		];

		foreach ( $items as $i => $item ) {
			wp_update_nav_menu_item( $menu_id, 0, [
				'menu-item-title'    => $item['title'],
				'menu-item-url'      => $item['url'],
				'menu-item-type'     => 'custom',
				'menu-item-status'   => 'publish',
				'menu-item-position' => $i + 1,
			] );
		}
	}

	// Assigner ce menu à l'emplacement « primary »
	$locations = get_theme_mod( 'nav_menu_locations', [] );
	if ( empty( $locations['primary'] ) ) {
		$locations['primary'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	update_option( 'seoflix_default_menu_installed', '1' );
}

/**
 * Walker minimaliste : pas de <ul>/<li>, juste des <a> directs.
 * Permet de réutiliser le CSS existant `.sx-nav > a`.
 */

/**
 * Walker minimaliste : pas de <ul>/<li>, juste des <a> directs.
 * Permet de réutiliser le CSS existant `.sx-nav > a`.
 */
class Seoflix_Nav_Walker extends Walker_Nav_Menu {
	public function start_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_lvl( &$output, $depth = 0, $args = null ) {}
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$url   = ! empty( $item->url ) ? $item->url : '#';
		$title = apply_filters( 'the_title', $item->title, $item->ID );
		$output .= '<a href="' . esc_url( $url ) . '"';
		if ( ! empty( $item->target ) ) {
			$output .= ' target="' . esc_attr( $item->target ) . '"';
		}
		if ( ! empty( $item->xfn ) ) {
			$output .= ' rel="' . esc_attr( $item->xfn ) . '"';
		}
		$output .= '>' . esc_html( $title ) . '</a>';
	}
	public function end_el( &$output, $item, $depth = 0, $args = null ) {}
}

/**
 * Fallback : menu hardcodé utilisé tant qu'aucun menu n'est assigné à l'emplacement
 * « Menu principal » dans Apparence → Menus.
 *
 * Dès que tu crées un menu et que tu coches « Menu principal » dans son
 * affectation, ce fallback disparaît et c'est ton menu qui s'affiche.
 */
/**
 * Rend le menu primaire avec fallback robuste :
 *   - Si un menu est assigné à "primary" ET contient des items → l'utilise
 *   - Sinon → fallback hardcodé (pour ne JAMAIS avoir un menu vide)
 */
function seoflix_render_primary_menu(): void {
	$rendered = '';
	if ( has_nav_menu( 'primary' ) ) {
		$rendered = wp_nav_menu( [
			'theme_location' => 'primary',
			'container'      => false,
			'items_wrap'     => '%3$s',
			'walker'         => new Seoflix_Nav_Walker(),
			'depth'          => 1,
			'fallback_cb'    => false,
			'echo'           => false,
		] );
	}
	if ( $rendered && trim( $rendered ) !== '' ) {
		echo $rendered;
		return;
	}
	seoflix_default_primary_menu();
}

function seoflix_default_primary_menu(): void {
	?>
	<a href="<?php echo esc_url( home_url( '/sujet/seo-technique/' ) ); ?>">SEO</a>
	<a href="<?php echo esc_url( home_url( '/sujet/affiliation/' ) ); ?>">Affiliation</a>
	<a href="<?php echo esc_url( home_url( '/sujet/youtube/' ) ); ?>">YouTube</a>
	<a href="<?php echo esc_url( home_url( '/sujet/vente-de-liens/' ) ); ?>">Vente de liens</a>
	<a href="<?php echo esc_url( home_url( '/sujet/business-general/' ) ); ?>">Business</a>
	<a href="<?php echo esc_url( home_url( '/categories/' ) ); ?>">Toutes les catégories</a>
	<a href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_channel' ) ?: home_url( '/chaines/' ) ); ?>">Chaînes</a>
	<a href="<?php echo esc_url( get_post_type_archive_link( 'seoflix_product' ) ?: home_url( '/outils/' ) ); ?>">Outils SEO</a>
	<?php
}

/* ============================================================
 *  Helpers — Vidéo
 * ============================================================ */

function seoflix_video_youtube_id( int $post_id ): string {
	return (string) get_post_meta( $post_id, '_seoflix_youtube_id', true );
}

function seoflix_video_thumbnail_url( int $post_id ): string {
	$url = get_post_meta( $post_id, '_seoflix_thumbnail_url', true );
	if ( $url ) {
		return $url;
	}
	$yid = seoflix_video_youtube_id( $post_id );
	return $yid ? "https://i.ytimg.com/vi/{$yid}/maxresdefault.jpg" : '';
}

function seoflix_video_duration_seconds( int $post_id ): int {
	return (int) get_post_meta( $post_id, '_seoflix_duration', true );
}

function seoflix_video_duration_formatted( int $post_id ): string {
	$sec = seoflix_video_duration_seconds( $post_id );
	if ( $sec <= 0 ) {
		return '';
	}
	$h = (int) floor( $sec / 3600 );
	$m = (int) floor( ( $sec % 3600 ) / 60 );
	$s = $sec % 60;
	return $h > 0 ? sprintf( '%dh%02d', $h, $m ) : sprintf( '%d:%02d', $m, $s );
}

function seoflix_video_view_count( int $post_id ): int {
	return (int) get_post_meta( $post_id, '_seoflix_view_count', true );
}

function seoflix_format_count( int $n ): string {
	if ( $n >= 1_000_000 ) {
		return number_format_i18n( $n / 1_000_000, 1 ) . 'M';
	}
	if ( $n >= 1_000 ) {
		return number_format_i18n( $n / 1_000, 1 ) . 'k';
	}
	return number_format_i18n( $n );
}

function seoflix_video_channel_id( int $post_id ): int {
	return (int) get_post_meta( $post_id, '_seoflix_channel_id', true );
}

function seoflix_video_channel( int $post_id ): ?WP_Post {
	$cid = seoflix_video_channel_id( $post_id );
	return $cid ? get_post( $cid ) : null;
}

function seoflix_video_products( int $post_id ): array {
	$json = (string) get_post_meta( $post_id, '_seoflix_products', true );
	$ids  = $json ? json_decode( $json, true ) : [];
	if ( ! is_array( $ids ) || ! $ids ) {
		return [];
	}
	$ids = array_map( 'intval', $ids );
	return get_posts( [
		'post__in'       => $ids,
		'post_type'      => 'seoflix_product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'post__in',
	] );
}

function seoflix_video_topics( int $post_id ): array {
	$terms = wp_get_object_terms( $post_id, 'seoflix_topic' );
	return is_wp_error( $terms ) ? [] : $terms;
}

/* ============================================================
 *  Helpers — Channel
 * ============================================================ */

function seoflix_channel_thumbnail_url( int $post_id ): string {
	return (string) get_post_meta( $post_id, '_seoflix_channel_thumbnail', true );
}

function seoflix_channel_subscriber_count( int $post_id ): int {
	return (int) get_post_meta( $post_id, '_seoflix_subscriber_count', true );
}

function seoflix_channel_youtube_url( int $post_id ): string {
	return (string) get_post_meta( $post_id, '_seoflix_channel_url', true );
}

function seoflix_channel_real_name( int $post_id ): string {
	return (string) get_post_meta( $post_id, '_seoflix_real_name', true );
}

function seoflix_channel_videos( int $channel_id, int $limit = -1 ): array {
	return get_posts( [
		'post_type'      => 'seoflix_video',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'meta_query'     => [
			[ 'key' => '_seoflix_channel_id', 'value' => $channel_id ],
		],
		'orderby'        => 'date',
		'order'          => 'DESC',
	] );
}

/* ============================================================
 *  Helpers — Product
 * ============================================================ */

function seoflix_product_official_url( int $post_id ): string {
	return (string) get_post_meta( $post_id, '_seoflix_official_url', true );
}

function seoflix_product_affiliate_url( int $post_id ): string {
	return (string) get_post_meta( $post_id, '_seoflix_affiliate_url', true );
}

function seoflix_product_pricing( int $post_id ): string {
	return (string) get_post_meta( $post_id, '_seoflix_pricing', true );
}

function seoflix_product_videos( int $product_id, int $limit = 8 ): array {
	// Cherche les vidéos dont _seoflix_products (JSON) contient ce product_id
	return get_posts( [
		'post_type'      => 'seoflix_video',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'meta_query'     => [
			[
				'key'     => '_seoflix_products',
				'value'   => sprintf( ':%d,', $product_id ),
				'compare' => 'LIKE',
			],
		],
		'orderby' => 'date',
		'order'   => 'DESC',
	] );
}

/* ============================================================
 *  Lien d'affiliation : route /go/{slug}/
 * ============================================================ */

function seoflix_product_go_url( int $product_id ): string {
	$slug = get_post_field( 'post_name', $product_id );
	return home_url( '/go/' . $slug . '/' );
}

/* ============================================================
 *  Renderers — partials
 * ============================================================ */

function seoflix_render_video_card( WP_Post $video ): void {
	$thumb    = seoflix_video_thumbnail_url( $video->ID );
	$duration = seoflix_video_duration_formatted( $video->ID );
	$views    = seoflix_format_count( seoflix_video_view_count( $video->ID ) );
	$channel  = seoflix_video_channel( $video->ID );

	// Bouton favori (V2 + user loggé)
	$show_fav = false;
	$is_fav   = false;
	$fav_nonce = '';
	if ( class_exists( '\\Seoflix\\FeatureFlags' ) && \Seoflix\FeatureFlags::user_accounts_enabled() && is_user_logged_in() ) {
		$show_fav = true;
		$is_fav   = \Seoflix\User_Accounts::is_video_favorited( get_current_user_id(), $video->ID );
		$fav_nonce = wp_create_nonce( 'seoflix_user_action' );
	}
	?>
	<article class="sx-card-video">
		<a href="<?php echo esc_url( get_permalink( $video ) ); ?>" class="sx-card-video__link">
			<div class="sx-card-video__thumb-wrap">
				<?php if ( $thumb ) : ?>
					<img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" class="sx-card-video__thumb">
				<?php else : ?>
					<div class="sx-card-video__thumb sx-card-video__thumb--placeholder"></div>
				<?php endif; ?>
				<?php if ( $duration ) : ?>
					<span class="sx-card-video__duration"><?php echo esc_html( $duration ); ?></span>
				<?php endif; ?>
				<span class="sx-card-video__play" aria-hidden="true">▶</span>
			</div>
			<h3 class="sx-card-video__title"><?php echo esc_html( get_the_title( $video ) ); ?></h3>
			<div class="sx-card-video__meta">
				<?php if ( $channel ) : ?>
					<span class="sx-card-video__channel"><?php echo esc_html( $channel->post_title ); ?></span>
					<span class="sx-sep">·</span>
				<?php endif; ?>
				<span><?php echo esc_html( $views ); ?> vues</span>
			</div>
		</a>
		<?php if ( $show_fav ) : ?>
			<button
				type="button"
				class="sx-card-video__fav <?php echo $is_fav ? 'is-on' : ''; ?>"
				data-action="favorite"
				data-video-id="<?php echo (int) $video->ID; ?>"
				data-nonce="<?php echo esc_attr( $fav_nonce ); ?>"
				aria-label="<?php echo $is_fav ? 'Retirer des favoris' : 'Ajouter aux favoris'; ?>"
				aria-pressed="<?php echo $is_fav ? 'true' : 'false'; ?>"
				title="<?php echo $is_fav ? 'Retirer des favoris' : 'Ajouter aux favoris'; ?>">
				<span aria-hidden="true">♥</span>
			</button>
		<?php endif; ?>
	</article>
	<?php
}

function seoflix_render_channel_card( WP_Post $channel ): void {
	$thumb       = seoflix_channel_thumbnail_url( $channel->ID );
	$subscribers = seoflix_format_count( seoflix_channel_subscriber_count( $channel->ID ) );
	$real_name   = seoflix_channel_real_name( $channel->ID );
	?>
	<a href="<?php echo esc_url( get_permalink( $channel ) ); ?>" class="sx-card-channel">
		<?php if ( $thumb ) : ?>
			<img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" class="sx-card-channel__avatar">
		<?php else : ?>
			<div class="sx-card-channel__avatar sx-card-channel__avatar--placeholder"></div>
		<?php endif; ?>
		<div class="sx-card-channel__info">
			<h3 class="sx-card-channel__name"><?php echo esc_html( get_the_title( $channel ) ); ?></h3>
			<?php if ( $real_name ) : ?>
				<div class="sx-card-channel__real-name"><?php echo esc_html( $real_name ); ?></div>
			<?php endif; ?>
			<div class="sx-card-channel__subs"><?php echo esc_html( $subscribers ); ?> abonnés</div>
		</div>
	</a>
	<?php
}

function seoflix_render_product_card( WP_Post $product, array $opts = [] ): void {
	$opts = wp_parse_args( $opts, [
		'show_pricing' => true,
		'compact'      => false,
	] );

	$thumb_id  = get_post_thumbnail_id( $product->ID );
	$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';

	$category = wp_get_object_terms( $product->ID, 'seoflix_product_category', [ 'number' => 1 ] );
	$cat_name = ( ! is_wp_error( $category ) && $category ) ? $category[0]->name : '';

	$pricing       = seoflix_product_pricing( $product->ID );
	$pricing_label = match ( $pricing ) {
		'free'     => 'Gratuit',
		'freemium' => 'Freemium',
		'paid'     => 'Payant',
		default    => '',
	};

	$classes = [ 'sx-card-product' ];
	if ( $opts['compact'] )  { $classes[] = 'sx-card-product--compact'; }
	if ( $thumb_url )        { $classes[] = 'sx-card-product--has-image'; }
	?>
	<article class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
		<a href="<?php echo esc_url( get_permalink( $product ) ); ?>" class="sx-card-product__link">
			<?php if ( $thumb_url ) : ?>
				<div class="sx-card-product__image">
					<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( get_the_title( $product ) ); ?>" loading="lazy">
				</div>
			<?php endif; ?>
			<div class="sx-card-product__body">
				<h3 class="sx-card-product__name"><?php echo esc_html( get_the_title( $product ) ); ?></h3>
				<?php if ( $cat_name && ! $opts['compact'] ) : ?>
					<div class="sx-card-product__category"><?php echo esc_html( $cat_name ); ?></div>
				<?php endif; ?>
				<p class="sx-card-product__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt( $product ) ?: $product->post_content, $opts['compact'] ? 14 : 20 ) ); ?></p>
				<?php if ( $opts['show_pricing'] && $pricing_label ) : ?>
					<span class="sx-card-product__pricing sx-card-product__pricing--<?php echo esc_attr( $pricing ); ?>"><?php echo esc_html( $pricing_label ); ?></span>
				<?php endif; ?>
			</div>
		</a>
	</article>
	<?php
}

function seoflix_render_video_row( string $title, array $videos, ?string $see_more_url = null, ?string $see_more_label = 'Tout voir' ): void {
	if ( ! $videos ) {
		return;
	}
	?>
	<section class="sx-row">
		<div class="sx-row__header">
			<h2 class="sx-row__title"><?php echo esc_html( $title ); ?></h2>
			<?php if ( $see_more_url ) : ?>
				<a class="sx-row__see-more" href="<?php echo esc_url( $see_more_url ); ?>"><?php echo esc_html( $see_more_label ); ?> →</a>
			<?php endif; ?>
		</div>
		<div class="sx-row__rail">
			<?php foreach ( $videos as $v ) : seoflix_render_video_card( $v ); endforeach; ?>
		</div>
	</section>
	<?php
}

function seoflix_render_channel_row( string $title, array $channels, ?string $see_more_url = null ): void {
	if ( ! $channels ) {
		return;
	}
	?>
	<section class="sx-row sx-row--channels">
		<div class="sx-row__header">
			<h2 class="sx-row__title"><?php echo esc_html( $title ); ?></h2>
			<?php if ( $see_more_url ) : ?>
				<a class="sx-row__see-more" href="<?php echo esc_url( $see_more_url ); ?>">Toutes les chaînes →</a>
			<?php endif; ?>
		</div>
		<div class="sx-row__rail">
			<?php foreach ( $channels as $ch ) : seoflix_render_channel_card( $ch ); endforeach; ?>
		</div>
	</section>
	<?php
}
