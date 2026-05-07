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

	public static function get_api_key(): string {
		return (string) get_option( 'seoflix_youtube_api_key', '' );
	}

	public static function is_configured(): bool {
		return self::get_api_key() !== '';
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

		// Uploads playlist = UU + suffix du channel_id (sans le préfixe UC)
		$uploads_playlist_id = 'UU' . substr( $channel_id, 2 );

		$api_key = self::get_api_key();

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
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );
			if ( wp_remote_retrieve_response_code( $resp ) !== 200 ) {
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
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );
			if ( wp_remote_retrieve_response_code( $resp ) !== 200 ) {
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
