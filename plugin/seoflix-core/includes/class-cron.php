<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron quotidien : scan toutes les chaînes pour récupérer les nouvelles vidéos.
 *
 * Schedule conditionnel (option `seoflix_ingestion_cron_enabled`).
 * Tourne à 04:00 heure du serveur.
 * Fetch 50 vidéos / chaîne, dédup par youtube_id, statut "pending"
 * (ou "publish" si seoflix_auto_publish_ai = true).
 */
final class Cron {

	public const HOOK             = 'seoflix_daily_sync';
	public const LAST_RUN_OPTION  = 'seoflix_cron_last_run';

	public static function init(): void {
		add_action( self::HOOK, [ self::class, 'run_daily_sync' ] );
		add_action( 'init',                          [ self::class, 'maybe_schedule' ], 30 );
		add_action( 'update_option_seoflix_ingestion_cron_enabled', [ self::class, 'maybe_schedule' ], 10, 0 );
	}

	public static function maybe_schedule(): void {
		$enabled = (bool) get_option( 'seoflix_ingestion_cron_enabled', true );
		$next    = wp_next_scheduled( self::HOOK );

		if ( $enabled && ! $next ) {
			// Premier déclenchement : prochain 04:00 heure WP
			$now       = current_time( 'timestamp' );
			$tomorrow4 = strtotime( 'tomorrow 04:00', $now );
			wp_schedule_event( $tomorrow4 - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ), 'daily', self::HOOK );
		} elseif ( ! $enabled && $next ) {
			wp_unschedule_event( $next, self::HOOK );
		}
	}

	public static function run_daily_sync(): void {
		if ( ! YouTube_API::is_configured() ) {
			self::log_run( [ 'error' => 'API key not configured' ] );
			return;
		}

		$channels = get_posts( [
			'post_type'      => CPT::CHANNEL,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[ 'key' => Meta_Keys::CHANNEL_YOUTUBE_ID, 'compare' => 'EXISTS' ],
			],
		] );

		$auto_publish    = (bool) get_option( 'seoflix_auto_publish_ai', false );
		$total_created   = 0;
		$total_skipped   = 0;
		$channels_done   = 0;
		$channels_skipped = 0;

		foreach ( $channels as $channel_post_id ) {
			$yt_id = (string) get_post_meta( $channel_post_id, Meta_Keys::CHANNEL_YOUTUBE_ID, true );
			if ( ! $yt_id ) {
				$channels_skipped++;
				continue;
			}

			// Stop si plus de quota (évite de tourner pour rien)
			if ( ! YouTube_API::has_quota( 4 ) ) {
				$channels_skipped++;
				continue;
			}

			$videos = YouTube_API::fetch_channel_videos( $yt_id, 50, 420 );
			if ( is_wp_error( $videos ) ) {
				$channels_skipped++;
				continue;
			}

			foreach ( $videos as $v ) {
				$existing = get_posts( [
					'post_type'      => CPT::VIDEO,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => [
						[ 'key' => Meta_Keys::VIDEO_YOUTUBE_ID, 'value' => $v['youtube_id'] ],
					],
				] );
				if ( $existing ) {
					$total_skipped++;
					continue;
				}

				$post_date = $v['published_at'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v['published_at'] )
					? $v['published_at'] . ' 00:00:00'
					: current_time( 'mysql' );

				$post_id = wp_insert_post( [
					'post_type'    => CPT::VIDEO,
					'post_title'   => sanitize_text_field( $v['title'] ),
					'post_content' => wp_kses_post( $v['description'] ),
					'post_status'  => $auto_publish ? 'publish' : 'pending',
					'post_date'    => $post_date,
				], true );

				if ( is_wp_error( $post_id ) ) {
					continue;
				}

				update_post_meta( $post_id, Meta_Keys::VIDEO_YOUTUBE_ID, $v['youtube_id'] );
				update_post_meta( $post_id, Meta_Keys::VIDEO_CHANNEL_ID, $channel_post_id );
				update_post_meta( $post_id, Meta_Keys::VIDEO_DURATION, (int) $v['duration_seconds'] );
				update_post_meta( $post_id, Meta_Keys::VIDEO_VIEW_COUNT, (int) $v['view_count'] );
				update_post_meta( $post_id, Meta_Keys::VIDEO_PUBLISHED_AT, $v['published_at'] );
				update_post_meta( $post_id, Meta_Keys::VIDEO_THUMBNAIL_URL, esc_url_raw( $v['thumbnail_url'] ) );
				update_post_meta( $post_id, Meta_Keys::VIDEO_YOUTUBE_URL, esc_url_raw( $v['youtube_url'] ) );

				$detected = Video_Meta::detect_products_in_text( $v['title'] . "\n" . $v['description'] );
				if ( $detected ) {
					update_post_meta( $post_id, Meta_Keys::VIDEO_PRODUCTS, wp_json_encode( $detected ) );
				}

				$total_created++;
			}

			$channels_done++;
		}

		self::log_run( [
			'created'          => $total_created,
			'skipped_existing' => $total_skipped,
			'channels_done'    => $channels_done,
			'channels_skipped' => $channels_skipped,
		] );
	}

	private static function log_run( array $stats ): void {
		update_option( self::LAST_RUN_OPTION, [
			'timestamp' => time(),
			'stats'     => $stats,
		], false );
	}

	public static function unschedule(): void {
		$next = wp_next_scheduled( self::HOOK );
		if ( $next ) {
			wp_unschedule_event( $next, self::HOOK );
		}
	}
}
