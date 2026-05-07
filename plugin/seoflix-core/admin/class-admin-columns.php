<?php
namespace Seoflix\Admin;

use Seoflix\CPT;
use Seoflix\Meta_Keys;

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
