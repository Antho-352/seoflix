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
 *  Assets
 * ============================================================ */

add_action( 'wp_enqueue_scripts', static function () {
	$ver = wp_get_theme()->get( 'Version' );
	wp_enqueue_style( 'seoflix-tokens',     get_theme_file_uri( 'assets/css/tokens.css' ),     [], $ver );
	wp_enqueue_style( 'seoflix-reset',      get_theme_file_uri( 'assets/css/reset.css' ),      [ 'seoflix-tokens' ], $ver );
	wp_enqueue_style( 'seoflix-layout',     get_theme_file_uri( 'assets/css/layout.css' ),     [ 'seoflix-reset' ], $ver );
	wp_enqueue_style( 'seoflix-components', get_theme_file_uri( 'assets/css/components.css' ), [ 'seoflix-layout' ], $ver );
	wp_enqueue_style( 'seoflix-pages',      get_theme_file_uri( 'assets/css/pages.css' ),      [ 'seoflix-components' ], $ver );
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

	// Toutes les chaînes sur une seule page, triées par abonnés desc
	if ( $query->is_post_type_archive( 'seoflix_channel' ) ) {
		$query->set( 'posts_per_page', -1 );
		$query->set( 'meta_key', '_seoflix_subscriber_count' );
		$query->set( 'orderby', 'meta_value_num' );
		$query->set( 'order', 'DESC' );
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

function seoflix_render_product_card( WP_Post $product ): void {
	$category = wp_get_object_terms( $product->ID, 'seoflix_product_category', [ 'number' => 1 ] );
	$cat_name = ( ! is_wp_error( $category ) && $category ) ? $category[0]->name : '';
	$pricing  = seoflix_product_pricing( $product->ID );
	$pricing_label = match ( $pricing ) {
		'free'     => 'Gratuit',
		'freemium' => 'Freemium',
		'paid'     => 'Payant',
		default    => '',
	};
	?>
	<article class="sx-card-product">
		<a href="<?php echo esc_url( get_permalink( $product ) ); ?>" class="sx-card-product__link">
			<h3 class="sx-card-product__name"><?php echo esc_html( get_the_title( $product ) ); ?></h3>
			<?php if ( $cat_name ) : ?>
				<div class="sx-card-product__category"><?php echo esc_html( $cat_name ); ?></div>
			<?php endif; ?>
			<p class="sx-card-product__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt( $product ) ?: $product->post_content, 20 ) ); ?></p>
			<?php if ( $pricing_label ) : ?>
				<span class="sx-card-product__pricing sx-card-product__pricing--<?php echo esc_attr( $pricing ); ?>"><?php echo esc_html( $pricing_label ); ?></span>
			<?php endif; ?>
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
