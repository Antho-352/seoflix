<?php
namespace Seoflix\Admin;

use Seoflix\CPT;
use Seoflix\Meta_Keys;
use Seoflix\Video_Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Colonnes custom dans les listes admin des CPT seoflix_video, seoflix_product et seoflix_channel.
 *
 * Sur l'écran "Toutes les vidéos" :
 *   - Ajoute une colonne « Chaîne » avec le nom de la chaîne (cliquable vers son édition)
 *   - Ajoute une colonne « Durée »
 *   - Ajoute une colonne « Vues »
 */
final class Admin_Columns {

	public static function init(): void {
		// Vidéos
		add_filter( 'manage_seoflix_video_posts_columns',         [ self::class, 'video_columns' ] );
		add_action( 'manage_seoflix_video_posts_custom_column',   [ self::class, 'video_column_content' ], 10, 2 );
		add_filter( 'manage_edit-seoflix_video_sortable_columns', [ self::class, 'video_sortable_columns' ] );
		add_action( 'pre_get_posts',                              [ self::class, 'video_orderby' ] );

		// Chaînes
		add_filter( 'manage_seoflix_channel_posts_columns',         [ self::class, 'channel_columns' ] );
		add_action( 'manage_seoflix_channel_posts_custom_column',   [ self::class, 'channel_column_content' ], 10, 2 );

		// Produits
		add_filter( 'manage_seoflix_product_posts_columns',         [ self::class, 'product_columns' ] );
		add_action( 'manage_seoflix_product_posts_custom_column',   [ self::class, 'product_column_content' ], 10, 2 );

		// Bulk action : auto-détection produits sur les vidéos sélectionnées
		add_filter( 'bulk_actions-edit-seoflix_video',         [ self::class, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-seoflix_video',  [ self::class, 'handle_bulk_detect_products' ], 10, 3 );
		add_action( 'admin_notices',                           [ self::class, 'bulk_detect_notice' ] );

		// Recherche par URL / ID YouTube sur la liste des vidéos
		add_action( 'pre_get_posts',                           [ self::class, 'video_search_by_youtube' ] );
	}

	/**
	 * Hijacke la recherche admin de la liste des vidéos pour matcher aussi
	 * sur le meta `_seoflix_youtube_id`. Accepte :
	 *   - URL YouTube complète : https://www.youtube.com/watch?v=XXXXXXXXXXX
	 *   - URL courte : https://youtu.be/XXXXXXXXXXX
	 *   - ID nu : XXXXXXXXXXX (11 chars)
	 *   - Sinon : recherche normale par titre/contenu
	 */
	public static function video_search_by_youtube( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'edit-seoflix_video' ) {
			return;
		}
		$s = trim( (string) $query->get( 's' ) );
		if ( ! $s ) {
			return;
		}

		$yt_id = self::extract_youtube_id( $s );
		if ( ! $yt_id ) {
			return; // recherche normale par titre
		}

		// Switch vers une meta_query exacte sur _seoflix_youtube_id
		$query->set( 's', '' );
		$query->set( 'meta_query', [
			[
				'key'     => Meta_Keys::VIDEO_YOUTUBE_ID,
				'value'   => $yt_id,
				'compare' => '=',
			],
		] );
	}

	private static function extract_youtube_id( string $input ): string {
		$input = trim( $input );
		// URL longue
		if ( preg_match( '~[?&]v=([a-zA-Z0-9_\-]{11})~', $input, $m ) ) {
			return $m[1];
		}
		// URL courte youtu.be
		if ( preg_match( '~youtu\.be/([a-zA-Z0-9_\-]{11})~', $input, $m ) ) {
			return $m[1];
		}
		// URL embed
		if ( preg_match( '~youtube(?:-nocookie)?\.com/embed/([a-zA-Z0-9_\-]{11})~', $input, $m ) ) {
			return $m[1];
		}
		// ID brut
		if ( preg_match( '~^[a-zA-Z0-9_\-]{11}$~', $input ) ) {
			return $input;
		}
		return '';
	}

	/* ======================================================================
	 *  Bulk action : auto-détection produits sur sélection
	 * ====================================================================== */

	public static function register_bulk_actions( array $actions ): array {
		$actions['seoflix_detect_products'] = '🔍 Auto-détecter les produits';
		return $actions;
	}

	public static function handle_bulk_detect_products( string $redirect_url, string $action, array $post_ids ): string {
		if ( $action !== 'seoflix_detect_products' ) {
			return $redirect_url;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $redirect_url;
		}

		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-video-meta.php';

		$updated     = 0;
		$total_links = 0;
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || $post->post_type !== CPT::VIDEO ) {
				continue;
			}
			$text     = $post->post_title . "\n" . $post->post_content;
			$products = Video_Meta::detect_products_in_text( $text );
			update_post_meta( $post_id, Meta_Keys::VIDEO_PRODUCTS, wp_json_encode( $products ) );
			$updated++;
			$total_links += count( $products );
		}

		return add_query_arg( [
			'seoflix_detected' => $updated,
			'seoflix_links'    => $total_links,
		], $redirect_url );
	}

	public static function bulk_detect_notice(): void {
		if ( ! isset( $_GET['seoflix_detected'] ) ) {
			return;
		}
		$count = (int) $_GET['seoflix_detected'];
		$links = (int) ( $_GET['seoflix_links'] ?? 0 );
		printf(
			'<div class="notice notice-success is-dismissible"><p><strong>%s</strong> vidéo(s) re-scannée(s), <strong>%s</strong> liaison(s) produit attribuée(s).</p></div>',
			esc_html( number_format_i18n( $count ) ),
			esc_html( number_format_i18n( $links ) )
		);
	}

	/* ======================================================================
	 *  Vidéos
	 * ====================================================================== */

	public static function video_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( $key === 'title' ) {
				$new['sx_channel']  = 'Chaîne';
				$new['sx_duration'] = 'Durée';
				$new['sx_views']    = 'Vues';
			}
		}
		return $new;
	}

	public static function video_column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'sx_channel':
				$channel_id = (int) get_post_meta( $post_id, Meta_Keys::VIDEO_CHANNEL_ID, true );
				if ( $channel_id ) {
					$channel = get_post( $channel_id );
					if ( $channel ) {
						echo '<a href="' . esc_url( get_edit_post_link( $channel->ID ) ) . '">' . esc_html( $channel->post_title ) . '</a>';
					} else {
						echo '<em style="color: #999;">Chaîne introuvable (ID ' . (int) $channel_id . ')</em>';
					}
				} else {
					echo '<em style="color: #999;">—</em>';
				}
				break;

			case 'sx_duration':
				$sec = (int) get_post_meta( $post_id, Meta_Keys::VIDEO_DURATION, true );
				if ( $sec > 0 ) {
					$h = (int) floor( $sec / 3600 );
					$m = (int) floor( ( $sec % 3600 ) / 60 );
					echo $h > 0 ? sprintf( '%dh%02d', $h, $m ) : sprintf( '%d:%02d', $m, $sec % 60 );
				} else {
					echo '—';
				}
				break;

			case 'sx_views':
				$n = (int) get_post_meta( $post_id, Meta_Keys::VIDEO_VIEW_COUNT, true );
				echo $n > 0 ? esc_html( number_format_i18n( $n ) ) : '—';
				break;
		}
	}

	public static function video_sortable_columns( array $columns ): array {
		$columns['sx_duration'] = 'sx_duration';
		$columns['sx_views']    = 'sx_views';
		return $columns;
	}

	public static function video_orderby( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$orderby = $query->get( 'orderby' );
		if ( $orderby === 'sx_duration' ) {
			$query->set( 'meta_key', Meta_Keys::VIDEO_DURATION );
			$query->set( 'orderby', 'meta_value_num' );
		} elseif ( $orderby === 'sx_views' ) {
			$query->set( 'meta_key', Meta_Keys::VIDEO_VIEW_COUNT );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/* ======================================================================
	 *  Chaînes
	 * ====================================================================== */

	public static function channel_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( $key === 'title' ) {
				$new['sx_handle'] = 'Handle';
				$new['sx_subs']   = 'Abonnés';
			}
		}
		return $new;
	}

	public static function channel_column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'sx_handle':
				$handle = (string) get_post_meta( $post_id, Meta_Keys::CHANNEL_HANDLE, true );
				echo $handle ? '<code>' . esc_html( $handle ) . '</code>' : '—';
				break;
			case 'sx_subs':
				$n = (int) get_post_meta( $post_id, Meta_Keys::CHANNEL_SUBSCRIBER_COUNT, true );
				echo $n > 0 ? esc_html( number_format_i18n( $n ) ) : '—';
				break;
		}
	}

	/* ======================================================================
	 *  Produits
	 * ====================================================================== */

	public static function product_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( $key === 'title' ) {
				$new['sx_pricing']  = 'Tarif';
				$new['sx_aff_url']  = 'URL affiliée';
			}
		}
		return $new;
	}

	public static function product_column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'sx_pricing':
				$pricing = (string) get_post_meta( $post_id, Meta_Keys::PRODUCT_PRICING, true );
				$labels  = [ 'free' => 'Gratuit', 'freemium' => 'Freemium', 'paid' => 'Payant' ];
				echo $pricing && isset( $labels[ $pricing ] ) ? esc_html( $labels[ $pricing ] ) : '—';
				break;
			case 'sx_aff_url':
				$aff = (string) get_post_meta( $post_id, Meta_Keys::PRODUCT_AFFILIATE_URL, true );
				if ( $aff ) {
					echo '<span style="color: #28a745;" title="' . esc_attr( $aff ) . '">✓ saisie</span>';
				} else {
					echo '<span style="color: #dc3545;">✗ manquante</span>';
				}
				break;
		}
	}
}
