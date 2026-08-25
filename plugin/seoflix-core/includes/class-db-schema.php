<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tables custom :
 *  - {$prefix}seoflix_favorites      (V2 — feature flag dormant en V1, table créée pour préparer)
 *  - {$prefix}seoflix_watch          (V2 — idem)
 *  - {$prefix}seoflix_affiliate_clicks (V1 — tracking clics affiliés)
 */
final class DB_Schema {

	public static function table_favorites(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoflix_favorites';
	}

	public static function table_watch(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoflix_watch';
	}

	public static function table_affiliate_clicks(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoflix_affiliate_clicks';
	}

	public static function install(): bool {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$favorites = self::table_favorites();
		$watch     = self::table_watch();
		$clicks    = self::table_affiliate_clicks();

		$sql_favorites = "CREATE TABLE {$favorites} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			video_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uq_user_video (user_id, video_id),
			KEY k_user (user_id),
			KEY k_video (video_id)
		) {$charset_collate};";

		$sql_watch = "CREATE TABLE {$watch} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			video_id BIGINT UNSIGNED NOT NULL,
			watched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			progress_seconds INT UNSIGNED NOT NULL DEFAULT 0,
			completed TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY uq_user_video (user_id, video_id),
			KEY k_user (user_id),
			KEY k_video (video_id),
			KEY k_watched_at (watched_at)
		) {$charset_collate};";

		$sql_clicks = "CREATE TABLE {$clicks} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			source_video_id BIGINT UNSIGNED NULL,
			source_page VARCHAR(255) NULL,
			ip_hash CHAR(64) NULL,
			user_agent VARCHAR(255) NULL,
			referer VARCHAR(255) NULL,
			clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY k_product (product_id),
			KEY k_source_video (source_video_id),
			KEY k_clicked_at (clicked_at)
		) {$charset_collate};";

		$wpdb->last_error = '';
		dbDelta( $sql_favorites );
		dbDelta( $sql_watch );
		dbDelta( $sql_clicks );

		return $wpdb->last_error === '';
	}

	/**
	 * Copie chaque ordre global positif dans les parcours associés qui n'ont
	 * pas encore d'ordre propre. Les entrées récentes ne sont jamais écrasées.
	 */
	public static function migrate_legacy_path_orders(): bool {
		$video_ids = get_posts( [
			'post_type'      => CPT::VIDEO,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => Path_Order::META_ORDER_KEY,
			'meta_value'     => 0,
			'meta_compare'   => '>',
			'meta_type'      => 'NUMERIC',
		] );

		foreach ( array_map( 'intval', $video_ids ) as $video_id ) {
			$legacy_order = (int) get_post_meta( $video_id, Path_Order::META_ORDER_KEY, true );
			if ( $legacy_order <= 0 ) {
				continue;
			}

			$term_ids = wp_get_object_terms( $video_id, Taxonomies::PATH, [ 'fields' => 'ids' ] );
			if ( is_wp_error( $term_ids ) ) {
				return false;
			}

			$orders  = Path_Order::get_order_map( $video_id );
			$changed = false;
			foreach ( array_map( 'intval', $term_ids ) as $term_id ) {
				if ( $term_id > 0 && ! array_key_exists( $term_id, $orders ) ) {
					$orders[ $term_id ] = $legacy_order;
					$changed = true;
				}
			}

			if ( $changed ) {
				ksort( $orders, SORT_NUMERIC );
				$encoded = wp_json_encode( $orders );
				if ( false === update_post_meta( $video_id, Meta_Keys::VIDEO_PATH_ORDERS, $encoded )
					&& get_post_meta( $video_id, Meta_Keys::VIDEO_PATH_ORDERS, true ) !== $encoded ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Exécute les mises à niveau après un remplacement ZIP du plugin.
	 */
	public static function maybe_upgrade(): bool {
		if ( (int) get_option( 'seoflix_db_version', 0 ) >= (int) SEOFLIX_DB_VERSION ) {
			return true;
		}
		if ( ! self::install() ) {
			return false;
		}
		if ( ! self::migrate_legacy_path_orders() ) {
			return false;
		}

		update_option( 'seoflix_db_version', SEOFLIX_DB_VERSION );
		return (string) get_option( 'seoflix_db_version', '' ) === (string) SEOFLIX_DB_VERSION;
	}
}
