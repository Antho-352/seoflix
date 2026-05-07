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

	private const ENDPOINT = 'https://www.googleapis.com/youtube/v3/channels';

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

		$url = self::ENDPOINT . '?' . http_build_query( $params );

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
}
