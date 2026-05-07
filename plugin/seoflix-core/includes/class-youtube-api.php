<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wrapper minimaliste autour de YouTube Data API v3.
 *
 * Récupère les infos d'une chaîne par handle (@xxx) ou par channel ID (UCxxx).
 * Coût API : 1 unité par requête (sur un quota gratuit de 10 000/jour).
 */
final class YouTube_API {

	private const ENDPOINT_CHANNELS       = 'https://www.googleapis.com/youtube/v3/channels';
	private const ENDPOINT_PLAYLIST_ITEMS = 'https://www.googleapis.com/youtube/v3/playlistItems';
	private const ENDPOINT_VIDEOS         = 'https://www.googleapis.com/youtube/v3/videos';

	/**
	 * Initialise le hook qui force IPv4 pour tous les appels googleapis.com.
	 *
	 * Pourquoi : sur certains serveurs (notamment Kimsufi/OVH), PHP utilise IPv6 par
	 * défaut pour ses requêtes sortantes vers Google. Comme la restriction d'IP de la
	 * clé API est généralement configurée avec l'IPv4 du serveur uniquement, les
	 * appels échouent avec "IP address restriction violated".
	 *
	 * En forçant IPv4 via curl, on s'assure que toutes les requêtes partent depuis
	 * l'IPv4 du serveur, qui matche la whitelist Google.
	 */
	public static function init(): void {
		add_action( 'http_api_curl', [ self::class, 'force_ipv4_for_google' ], 10, 3 );
	}

	public static function force_ipv4_for_google( $handle, $args, $url ): void {
		if ( strpos( (string) $url, 'googleapis.com' ) !== false ) {
			curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
		}
	}

	public static function get_api_key(): string {
		return (string) get_option( 'seoflix_youtube_api_key', '' );
	}

	public static function is_configured(): bool {
		return self::get_api_key() !== '';
	}

	/* ======================================================================
	 *  Quota soft-cap (côté plugin, par-dessus le hard cap Google de 10k/jour)
	 * ====================================================================== */

	private const USAGE_DAY_PREFIX  = 'seoflix_yt_api_usage_d_';
	private const USAGE_HOUR_PREFIX = 'seoflix_yt_api_usage_h_';

	public static function get_daily_limit(): int {
		return max( 0, (int) get_option( 'seoflix_yt_daily_limit', 1000 ) );
	}

	public static function get_hourly_limit(): int {
		return max( 0, (int) get_option( 'seoflix_yt_hourly_limit', 200 ) );
	}

	public static function get_today_usage(): int {
		$key = self::USAGE_DAY_PREFIX . gmdate( 'Y-m-d' );
		return (int) get_transient( $key );
	}

	public static function get_current_hour_usage(): int {
		$key = self::USAGE_HOUR_PREFIX . gmdate( 'Y-m-d-H' );
		return (int) get_transient( $key );
	}

	public static function has_quota( int $needed ): bool {
		// Cap horaire : ne pas brûler tout le budget en une heure (ceinture anti-runaway)
		$hourly_limit = self::get_hourly_limit();
		if ( $hourly_limit > 0 && self::get_current_hour_usage() + $needed > $hourly_limit ) {
			return false;
		}

		// Cap quotidien
		$daily_limit = self::get_daily_limit();
		if ( $daily_limit > 0 && self::get_today_usage() + $needed > $daily_limit ) {
			return false;
		}

		return true;
	}

	public static function quota_blocker_message(): string {
		$hourly_limit = self::get_hourly_limit();
		$daily_limit  = self::get_daily_limit();
		$hour_used    = self::get_current_hour_usage();
		$day_used     = self::get_today_usage();

		if ( $hourly_limit > 0 && $hour_used >= $hourly_limit ) {
			return sprintf(
				'Cap horaire atteint (%d unités utilisées cette heure sur %d). Réessaye dans l\'heure suivante. Ce cap protège contre les boucles accidentelles.',
				$hour_used, $hourly_limit
			);
		}
		if ( $daily_limit > 0 && $day_used >= $daily_limit ) {
			return sprintf(
				'Cap quotidien atteint (%d/%d). Reset à minuit Pacific Time (≈ 9h heure française).',
				$day_used, $daily_limit
			);
		}
		return 'Quota dépassé.';
	}

	public static function increment_usage( int $units ): void {
		$day_key  = self::USAGE_DAY_PREFIX . gmdate( 'Y-m-d' );
		$hour_key = self::USAGE_HOUR_PREFIX . gmdate( 'Y-m-d-H' );

		set_transient( $day_key,  ( (int) get_transient( $day_key ) ) + $units, DAY_IN_SECONDS );
		set_transient( $hour_key, ( (int) get_transient( $hour_key ) ) + $units, HOUR_IN_SECONDS );
	}

	/**
	 * Récupère les infos d'une chaîne YouTube.
	 *
	 * @param string $handle_or_id  Format @xxx (handle) ou UCxxx (channel ID).
	 * @return array|\WP_Error  Tableau normalisé ou WP_Error en cas d'échec.
	 */
	public static function fetch_channel( string $handle_or_id ) {
		$api_key = self::get_api_key();
		if ( ! $api_key ) {
			return new \WP_Error( 'no_api_key', 'Clé YouTube Data API non configurée. Va dans Seoflix → Réglages.' );
		}
		if ( ! self::has_quota( 1 ) ) {
			return new \WP_Error( 'quota_exceeded', self::quota_blocker_message() );
		}

		$handle_or_id = trim( $handle_or_id );
		if ( ! $handle_or_id ) {
			return new \WP_Error( 'empty_input', 'Handle ou channel ID requis.' );
		}

		$params = [
			'key'  => $api_key,
			'part' => 'snippet,statistics,brandingSettings',
		];

		if ( strpos( $handle_or_id, '@' ) === 0 ) {
			$params['forHandle'] = $handle_or_id;
		} elseif ( strpos( $handle_or_id, 'UC' ) === 0 ) {
			$params['id'] = $handle_or_id;
		} else {
			// On suppose un handle sans @
			$params['forHandle'] = '@' . $handle_or_id;
		}

		$url = self::ENDPOINT_CHANNELS . '?' . http_build_query( $params );

		$response = wp_remote_get( $url, [
			'timeout'    => 15,
			'user-agent' => 'Seoflix/1.0 (+https://seoflix.fr)',
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$msg = $body['error']['message'] ?? "HTTP $code";
			return new \WP_Error( 'youtube_api_error', $msg );
		}

		if ( empty( $body['items'][0] ) ) {
			return new \WP_Error( 'channel_not_found', 'Aucune chaîne trouvée pour : ' . $handle_or_id );
		}

		self::increment_usage( 1 );

		$item    = $body['items'][0];
		$snippet = $item['snippet'] ?? [];
		$stats   = $item['statistics'] ?? [];

		// Choisir la meilleure miniature dispo
		$thumbnails = $snippet['thumbnails'] ?? [];
		$thumb_url  = $thumbnails['high']['url']
			?? $thumbnails['medium']['url']
			?? $thumbnails['default']['url']
			?? '';

		$custom_url = $snippet['customUrl'] ?? '';
		$handle     = $custom_url ? '@' . ltrim( $custom_url, '@' ) : ( strpos( $handle_or_id, '@' ) === 0 ? $handle_or_id : '' );

		return [
			'youtube_channel_id' => $item['id'] ?? '',
			'handle'             => $handle,
			'title'              => $snippet['title'] ?? '',
			'description'        => $snippet['description'] ?? '',
			'thumbnail_url'      => $thumb_url,
			'subscriber_count'   => (int) ( $stats['subscriberCount'] ?? 0 ),
			'video_count'        => (int) ( $stats['videoCount'] ?? 0 ),
			'view_count'         => (int) ( $stats['viewCount'] ?? 0 ),
			'channel_url'        => $handle ? 'https://www.youtube.com/' . $handle : ( 'https://www.youtube.com/channel/' . ( $item['id'] ?? '' ) ),
			'published_at'       => $snippet['publishedAt'] ?? '',
		];
	}

	/**
	 * Récupère les N dernières vidéos d'une chaîne YouTube, filtrées par durée min.
	 *
	 * Utilise l'astuce Uploads Playlist : la playlist `UU<id>` contient automatiquement
	 * tous les uploads d'une chaîne. Coût API : ~3 unités (1 pour le playlist + 1 par chunk de 50 vidéos).
	 *
	 * @param string $channel_id    Format `UCxxx...` (24 caractères)
	 * @param int    $max_videos    Nombre max de vidéos à récupérer (défaut 50)
	 * @param int    $min_duration  Durée minimum en secondes (défaut 420 = 7 min)
	 * @return array|\WP_Error
	 */
	public static function fetch_channel_videos( string $channel_id, int $max_videos = 50, int $min_duration = 420 ) {
		if ( ! self::is_configured() ) {
			return new \WP_Error( 'no_api_key', 'Clé YouTube Data API non configurée.' );
		}
		if ( strpos( $channel_id, 'UC' ) !== 0 || strlen( $channel_id ) !== 24 ) {
			return new \WP_Error( 'invalid_channel_id', "ID de chaîne invalide ($channel_id). Format attendu : UC + 22 caractères." );
		}

		// Estimation du coût : 1 unit pour playlist + 1 unit par chunk de 50 videos = max ~3 units pour 50 videos
		$estimated_cost = 1 + max( 1, (int) ceil( $max_videos / 50 ) );
		if ( ! self::has_quota( $estimated_cost ) ) {
			return new \WP_Error( 'quota_exceeded', self::quota_blocker_message() );
		}

		// Uploads playlist = UU + suffix du channel_id (sans le préfixe UC)
		$uploads_playlist_id = 'UU' . substr( $channel_id, 2 );

		$api_key = self::get_api_key();

		$units_used = 0;

		// Étape 1 : récupérer les playlistItems (juste les video IDs)
		$video_ids = [];
		$page_token = '';
		while ( count( $video_ids ) < $max_videos ) {
			$params = [
				'key'        => $api_key,
				'playlistId' => $uploads_playlist_id,
				'part'       => 'contentDetails',
				'maxResults' => min( 50, $max_videos - count( $video_ids ) ),
			];
			if ( $page_token ) {
				$params['pageToken'] = $page_token;
			}

			$resp = wp_remote_get(
				self::ENDPOINT_PLAYLIST_ITEMS . '?' . http_build_query( $params ),
				[ 'timeout' => 15, 'user-agent' => 'Seoflix/1.0' ]
			);
			$units_used += 1;
			if ( is_wp_error( $resp ) ) {
				self::increment_usage( $units_used );
				return $resp;
			}
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );
			if ( wp_remote_retrieve_response_code( $resp ) !== 200 ) {
				self::increment_usage( $units_used );
				return new \WP_Error( 'youtube_api_error', $body['error']['message'] ?? 'Erreur API' );
			}

			foreach ( ( $body['items'] ?? [] ) as $item ) {
				if ( ! empty( $item['contentDetails']['videoId'] ) ) {
					$video_ids[] = $item['contentDetails']['videoId'];
				}
			}

			$page_token = $body['nextPageToken'] ?? '';
			if ( ! $page_token ) {
				break;
			}
		}

		if ( ! $video_ids ) {
			self::increment_usage( $units_used );
			return [];
		}

		// Étape 2 : récupérer les détails complets (durée, vues, snippet) par chunks de 50
		$results = [];
		foreach ( array_chunk( $video_ids, 50 ) as $chunk ) {
			$params = [
				'key'  => $api_key,
				'id'   => implode( ',', $chunk ),
				'part' => 'snippet,contentDetails,statistics',
			];

			$resp = wp_remote_get(
				self::ENDPOINT_VIDEOS . '?' . http_build_query( $params ),
				[ 'timeout' => 20, 'user-agent' => 'Seoflix/1.0' ]
			);
			$units_used += 1;
			if ( is_wp_error( $resp ) ) {
				self::increment_usage( $units_used );
				return $resp;
			}
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );
			if ( wp_remote_retrieve_response_code( $resp ) !== 200 ) {
				self::increment_usage( $units_used );
				return new \WP_Error( 'youtube_api_error', $body['error']['message'] ?? 'Erreur API' );
			}

			foreach ( ( $body['items'] ?? [] ) as $item ) {
				$duration = self::iso_duration_to_seconds( $item['contentDetails']['duration'] ?? 'PT0S' );
				if ( $duration < $min_duration ) {
					continue;
				}
				$snippet    = $item['snippet'] ?? [];
				$thumbnails = $snippet['thumbnails'] ?? [];
				$thumb_url  = $thumbnails['maxres']['url']
					?? $thumbnails['high']['url']
					?? $thumbnails['medium']['url']
					?? $thumbnails['default']['url']
					?? '';

				$results[] = [
					'youtube_id'       => $item['id'],
					'title'            => $snippet['title'] ?? '',
					'description'      => substr( $snippet['description'] ?? '', 0, 5000 ),
					'duration_seconds' => $duration,
					'view_count'       => (int) ( $item['statistics']['viewCount'] ?? 0 ),
					'published_at'     => isset( $snippet['publishedAt'] ) ? gmdate( 'Y-m-d', strtotime( $snippet['publishedAt'] ) ) : '',
					'thumbnail_url'    => $thumb_url,
					'tags'             => array_slice( (array) ( $snippet['tags'] ?? [] ), 0, 20 ),
					'youtube_url'      => 'https://www.youtube.com/watch?v=' . $item['id'],
				];
			}
		}

		self::increment_usage( $units_used );
		return $results;
	}

	/**
	 * Convertit une durée ISO 8601 (PT1H30M45S) en secondes.
	 */
	private static function iso_duration_to_seconds( string $iso ): int {
		if ( ! preg_match( '/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $m ) ) {
			return 0;
		}
		return ( (int) ( $m[1] ?? 0 ) ) * 3600
			+ ( (int) ( $m[2] ?? 0 ) ) * 60
			+ ( (int) ( $m[3] ?? 0 ) );
	}
}
