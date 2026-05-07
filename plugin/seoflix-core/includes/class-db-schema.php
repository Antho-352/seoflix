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

	public static function install(): void {
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

		dbDelta( $sql_favorites );
		dbDelta( $sql_watch );
		dbDelta( $sql_clicks );

		update_option( 'seoflix_db_version', SEOFLIX_DB_VERSION );
	}
}
