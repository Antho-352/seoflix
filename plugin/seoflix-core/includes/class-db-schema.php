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
	private const MIGRATION_FAILED = -1;
	private const MIGRATION_PENDING = 0;
	private const MIGRATION_COMPLETE = 1;
	private const MIGRATION_BATCH_SIZE = 200;
	private const MIGRATION_CURSOR_OPTION = 'seoflix_path_order_migration_cursor';

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

		if ( ! self::apply_schema_statement(
			$sql_favorites,
			$favorites,
			[ 'id', 'user_id', 'video_id', 'created_at' ],
			[ 'PRIMARY', 'uq_user_video', 'k_user', 'k_video' ]
		) ) {
			return false;
		}
		if ( ! self::apply_schema_statement(
			$sql_watch,
			$watch,
			[ 'id', 'user_id', 'video_id', 'watched_at', 'progress_seconds', 'completed' ],
			[ 'PRIMARY', 'uq_user_video', 'k_user', 'k_video', 'k_watched_at' ]
		) ) {
			return false;
		}
		if ( ! self::apply_schema_statement(
			$sql_clicks,
			$clicks,
			[ 'id', 'product_id', 'source_video_id', 'source_page', 'ip_hash', 'user_agent', 'referer', 'clicked_at' ],
			[ 'PRIMARY', 'k_product', 'k_source_video', 'k_clicked_at' ]
		) ) {
			return false;
		}

		return true;
	}

	/** Exécute et vérifie immédiatement une instruction dbDelta. */
	private static function apply_schema_statement(
		string $sql,
		string $table,
		array $required_columns,
		array $required_indexes
	): bool {
		global $wpdb;
		$wpdb->last_error = '';
		dbDelta( $sql );
		if ( $wpdb->last_error !== '' ) {
			return false;
		}

		$found = $wpdb->get_var( $wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$wpdb->esc_like( $table )
		) );
		if ( $found !== $table ) {
			return false;
		}

		$identifier = '`' . str_replace( '`', '``', $table ) . '`';
		$wpdb->last_error = '';
		$columns = array_map( 'strval', $wpdb->get_col( "SHOW COLUMNS FROM {$identifier}", 0 ) );
		if ( $wpdb->last_error !== '' || array_diff( $required_columns, $columns ) ) {
			return false;
		}

		$wpdb->last_error = '';
		$indexes = array_unique( array_map( 'strval', $wpdb->get_col( "SHOW INDEX FROM {$identifier}", 2 ) ) );
		return $wpdb->last_error === '' && ! array_diff( $required_indexes, $indexes );
	}

	/** Sérialise migration et sauvegardes éditoriales sur la connexion MySQL. */
	public static function acquire_path_order_lock( int $timeout = 0 ): bool {
		global $wpdb;
		$lock_name = 'seoflix_path_order_' . md5( DB_NAME . '|' . $wpdb->prefix );
		return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, max( 0, $timeout ) ) );
	}

	public static function release_path_order_lock(): void {
		global $wpdb;
		$lock_name = 'seoflix_path_order_' . md5( DB_NAME . '|' . $wpdb->prefix );
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
	}

	/**
	 * Copie chaque ordre global positif dans les parcours associés qui n'ont
	 * pas encore d'ordre propre. Les entrées récentes ne sont jamais écrasées.
	 */
	public static function migrate_legacy_path_orders(): int {
		global $wpdb;
		if ( ! self::acquire_path_order_lock() ) {
			return self::MIGRATION_PENDING;
		}

		try {
		$cursor = max( 0, (int) get_option( self::MIGRATION_CURSOR_OPTION, 0 ) );
		$wpdb->last_error = '';
		$video_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			WHERE p.post_type = %s
				AND p.ID > %d
				AND pm.meta_key = %s
				AND CAST(pm.meta_value AS UNSIGNED) > 0
			ORDER BY p.ID ASC
			LIMIT %d",
			CPT::VIDEO,
			$cursor,
			Path_Order::META_ORDER_KEY,
			self::MIGRATION_BATCH_SIZE
		) ) );
		if ( $wpdb->last_error !== '' ) {
			return self::MIGRATION_FAILED;
		}
		if ( ! $video_ids ) {
			delete_option( self::MIGRATION_CURSOR_OPTION );
			return self::MIGRATION_COMPLETE;
		}

		_prime_post_caches( $video_ids, false, false );
		update_meta_cache( 'post', $video_ids );
		$terms = wp_get_object_terms( $video_ids, Taxonomies::PATH, [ 'fields' => 'all_with_object_id' ] );
		if ( is_wp_error( $terms ) ) {
			return self::MIGRATION_FAILED;
		}
		$terms_by_video = array_fill_keys( $video_ids, [] );
		foreach ( $terms as $term ) {
			$object_id = isset( $term->object_id ) ? (int) $term->object_id : 0;
			if ( isset( $terms_by_video[ $object_id ] ) ) {
				$terms_by_video[ $object_id ][] = (int) $term->term_id;
			}
		}

		foreach ( $video_ids as $video_id ) {
			$legacy_order = (int) get_post_meta( $video_id, Path_Order::META_ORDER_KEY, true );
			if ( $legacy_order <= 0 ) {
				continue;
			}

			$orders  = Path_Order::get_order_map( $video_id );
			$changed = false;
			foreach ( $terms_by_video[ $video_id ] as $term_id ) {
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
					return self::MIGRATION_FAILED;
				}
			}
		}

		$last_video_id = max( $video_ids );
		if ( count( $video_ids ) < self::MIGRATION_BATCH_SIZE ) {
			delete_option( self::MIGRATION_CURSOR_OPTION );
			return self::MIGRATION_COMPLETE;
		}
		if ( false === update_option( self::MIGRATION_CURSOR_OPTION, $last_video_id, false )
			&& (int) get_option( self::MIGRATION_CURSOR_OPTION, 0 ) !== $last_video_id ) {
			return self::MIGRATION_FAILED;
		}
		return self::MIGRATION_PENDING;
		} finally {
			self::release_path_order_lock();
		}
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
		$migration_status = self::migrate_legacy_path_orders();
		if ( $migration_status !== self::MIGRATION_COMPLETE ) {
			return false;
		}

		update_option( 'seoflix_db_version', SEOFLIX_DB_VERSION );
		return (string) get_option( 'seoflix_db_version', '' ) === (string) SEOFLIX_DB_VERSION;
	}
}
