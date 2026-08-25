<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Importer un fichier JSON conforme à docs/IMPORT_FORMAT.md.
 *
 * Idempotent : ré-importer le même fichier ne crée pas de doublons.
 * Clés d'unicité :
 *   - channel : `handle` (post_meta)
 *   - product : `slug`   (post_name)
 *   - video   : `youtube_id` (post_meta)
 *
 * Vidéos importées en statut `pending` (sauf si déjà publiées en prod).
 */
final class Importer {

	public static function import_from_file( string $path ): array {
		if ( ! is_readable( $path ) ) {
			return self::error_report( 'Fichier illisible : ' . $path );
		}
		$contents = file_get_contents( $path );
		if ( $contents === false ) {
			return self::error_report( 'Impossible de lire le fichier.' );
		}
		$raw_data = json_decode( $contents );
		$data     = json_decode( $contents, true );
		if ( ! is_object( $raw_data ) || ! is_array( $data ) ) {
			return self::error_report( 'JSON invalide : ' . json_last_error_msg() );
		}
		$data = self::preserve_editorial_container_shapes( $data, $raw_data );
		return self::import_from_data( $data );
	}

	/**
	 * json_decode(..., true) confond {} et []. On retire les collections
	 * éditoriales encodées comme objets afin qu'elles soient ignorées, jamais
	 * interprétées comme une demande d'effacement.
	 */
	private static function preserve_editorial_container_shapes( array $data, object $raw_data ): array {
		if ( ! isset( $raw_data->videos ) || ! is_array( $raw_data->videos ) ) {
			return $data;
		}
		foreach ( $raw_data->videos as $index => $raw_video ) {
			if ( ! is_object( $raw_video ) || ! isset( $data['videos'][ $index ] ) || ! is_array( $data['videos'][ $index ] ) ) {
				continue;
			}
			foreach ( [ 'timestamps', 'key_concepts' ] as $field ) {
				if ( property_exists( $raw_video, $field ) && is_object( $raw_video->{$field} ) ) {
					unset( $data['videos'][ $index ][ $field ] );
				}
			}
		}
		return $data;
	}

	public static function import_from_data( array $data ): array {
		$report = [
			'ok'                 => true,
			'channels_created'   => 0,
			'channels_updated'   => 0,
			'videos_created'     => 0,
			'videos_updated'     => 0,
			'products_created'   => 0,
			'products_updated'   => 0,
			'errors'             => [],
		];

		// Étape 1 : chaînes
		$channel_map = []; // handle => post_id
		foreach ( $data['channels'] ?? [] as $ch ) {
			try {
				$result = self::upsert_channel( $ch );
				$channel_map[ $ch['handle'] ] = $result['id'];
				$result['created'] ? $report['channels_created']++ : $report['channels_updated']++;
			} catch ( \Throwable $e ) {
				$report['errors'][] = 'Chaîne ' . ( $ch['handle'] ?? '?' ) . ' : ' . $e->getMessage();
			}
		}

		// Étape 2 : produits
		$product_map = []; // slug => post_id
		foreach ( $data['products_detected'] ?? [] as $p ) {
			try {
				$result = self::upsert_product( $p );
				$product_map[ $p['slug'] ] = $result['id'];
				$result['created'] ? $report['products_created']++ : $report['products_updated']++;
			} catch ( \Throwable $e ) {
				$report['errors'][] = 'Produit ' . ( $p['slug'] ?? '?' ) . ' : ' . $e->getMessage();
			}
		}

		// Étape 3 : vidéos (dépendent des channel_map et product_map)
		foreach ( $data['videos'] ?? [] as $v ) {
			try {
				$result = self::upsert_video( $v, $channel_map, $product_map );
				$result['created'] ? $report['videos_created']++ : $report['videos_updated']++;
			} catch ( \Throwable $e ) {
				$report['errors'][] = 'Vidéo ' . ( $v['youtube_id'] ?? '?' ) . ' : ' . $e->getMessage();
			}
		}

		return $report;
	}

	private static function error_report( string $msg ): array {
		return [
			'ok'               => false,
			'channels_created' => 0, 'channels_updated' => 0,
			'videos_created'   => 0, 'videos_updated'   => 0,
			'products_created' => 0, 'products_updated' => 0,
			'errors'           => [ $msg ],
		];
	}

	/* ======================================================================
	 *  Channel
	 * ====================================================================== */

	private static function upsert_channel( array $ch ): array {
		$handle = sanitize_text_field( $ch['handle'] ?? '' );
		if ( ! $handle ) {
			throw new \InvalidArgumentException( 'Handle manquant' );
		}

		$existing_id = self::find_channel_id_by_handle( $handle );

		$args = [
			'post_type'    => CPT::CHANNEL,
			'post_title'   => sanitize_text_field( $ch['display_name'] ?? $handle ),
			'post_content' => wp_kses_post( $ch['description'] ?? '' ),
			'post_status'  => 'publish',
		];

		if ( $existing_id ) {
			$args['ID'] = $existing_id;
			$id         = wp_update_post( $args, true );
			$created    = false;
		} else {
			$id      = wp_insert_post( $args, true );
			$created = true;
		}
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( $id->get_error_message() );
		}

		update_post_meta( $id, Meta_Keys::CHANNEL_HANDLE, $handle );
		if ( ! empty( $ch['youtube_channel_id'] ) ) {
			update_post_meta( $id, Meta_Keys::CHANNEL_YOUTUBE_ID, sanitize_text_field( $ch['youtube_channel_id'] ) );
		}
		if ( ! empty( $ch['real_name'] ) ) {
			update_post_meta( $id, Meta_Keys::CHANNEL_REAL_NAME, sanitize_text_field( $ch['real_name'] ) );
		}
		if ( isset( $ch['subscriber_count'] ) && is_numeric( $ch['subscriber_count'] ) ) {
			update_post_meta( $id, Meta_Keys::CHANNEL_SUBSCRIBER_COUNT, (int) $ch['subscriber_count'] );
		}
		if ( ! empty( $ch['thumbnail_url'] ) ) {
			update_post_meta( $id, Meta_Keys::CHANNEL_THUMBNAIL_URL, esc_url_raw( $ch['thumbnail_url'] ) );
		}
		if ( ! empty( $ch['url'] ) ) {
			update_post_meta( $id, Meta_Keys::CHANNEL_YOUTUBE_URL, esc_url_raw( $ch['url'] ) );
		}

		return [ 'id' => (int) $id, 'created' => $created ];
	}

	private static function find_channel_id_by_handle( string $handle ): int {
		$ids = get_posts( [
			'post_type'      => CPT::CHANNEL,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'   => Meta_Keys::CHANNEL_HANDLE,
					'value' => $handle,
				],
			],
		] );
		return $ids ? (int) $ids[0] : 0;
	}

	/* ======================================================================
	 *  Product
	 * ====================================================================== */

	private static function upsert_product( array $p ): array {
		$slug = sanitize_title( $p['slug'] ?? '' );
		if ( ! $slug ) {
			throw new \InvalidArgumentException( 'Slug produit manquant' );
		}

		$existing = get_page_by_path( $slug, OBJECT, CPT::PRODUCT );

		$args = [
			'post_type'    => CPT::PRODUCT,
			'post_title'   => sanitize_text_field( $p['name'] ?? $slug ),
			'post_name'    => $slug,
			'post_content' => wp_kses_post( $p['description'] ?? '' ),
			'post_status'  => 'publish',
		];

		if ( $existing ) {
			$args['ID'] = $existing->ID;
			$id         = wp_update_post( $args, true );
			$created    = false;
		} else {
			$id      = wp_insert_post( $args, true );
			$created = true;
		}
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( $id->get_error_message() );
		}

		if ( ! empty( $p['official_url'] ) ) {
			update_post_meta( $id, Meta_Keys::PRODUCT_OFFICIAL_URL, esc_url_raw( $p['official_url'] ) );
		}
		if ( ! empty( $p['pricing'] ) ) {
			update_post_meta( $id, Meta_Keys::PRODUCT_PRICING, sanitize_text_field( $p['pricing'] ) );
		}

		// Catégorie produit (taxonomie hiérarchique)
		if ( ! empty( $p['category'] ) ) {
			$term = get_term_by( 'slug', sanitize_title( $p['category'] ), Taxonomies::PRODUCT_CATEGORY );
			if ( $term ) {
				wp_set_object_terms( $id, [ (int) $term->term_id ], Taxonomies::PRODUCT_CATEGORY, false );
			}
		}

		return [ 'id' => (int) $id, 'created' => $created ];
	}

	/* ======================================================================
	 *  Video
	 * ====================================================================== */

	private static function upsert_video( array $v, array $channel_map, array $product_map ): array {
		$youtube_id = sanitize_text_field( $v['youtube_id'] ?? '' );
		if ( ! $youtube_id ) {
			throw new \InvalidArgumentException( 'youtube_id manquant' );
		}

		$channel_handle = sanitize_text_field( $v['channel_handle'] ?? '' );
		if ( ! isset( $channel_map[ $channel_handle ] ) ) {
			throw new \InvalidArgumentException( "Chaîne inconnue : $channel_handle" );
		}
		$channel_id = (int) $channel_map[ $channel_handle ];

		$existing_id = self::find_video_id_by_youtube_id( $youtube_id );

		$published_at = sanitize_text_field( $v['published_at'] ?? '' );
		$post_date    = $published_at && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $published_at )
			? $published_at . ' 00:00:00'
			: current_time( 'mysql' );

		$args = [
			'post_type'    => CPT::VIDEO,
			'post_title'   => sanitize_text_field( $v['title'] ?? $youtube_id ),
			'post_content' => wp_kses_post( $v['description_ai'] ?? '' ),
			'post_date'    => $post_date,
			'post_status'  => 'pending', // par défaut : en attente de validation admin
		];

		if ( $existing_id ) {
			$args['ID'] = $existing_id;
			// Ne PAS rétrograder une vidéo déjà publiée vers pending
			$current_status = get_post_status( $existing_id );
			if ( $current_status === 'publish' ) {
				unset( $args['post_status'] );
			}
			$id      = wp_update_post( $args, true );
			$created = false;
		} else {
			$id      = wp_insert_post( $args, true );
			$created = true;
		}
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( $id->get_error_message() );
		}

		update_post_meta( $id, Meta_Keys::VIDEO_YOUTUBE_ID, $youtube_id );
		update_post_meta( $id, Meta_Keys::VIDEO_CHANNEL_ID, $channel_id );

		if ( isset( $v['duration_seconds'] ) && is_numeric( $v['duration_seconds'] ) ) {
			update_post_meta( $id, Meta_Keys::VIDEO_DURATION, max( 0, (int) $v['duration_seconds'] ) );
		}
		if ( $published_at ) {
			update_post_meta( $id, Meta_Keys::VIDEO_PUBLISHED_AT, $published_at );
		}
		if ( isset( $v['view_count'] ) && is_numeric( $v['view_count'] ) ) {
			update_post_meta( $id, Meta_Keys::VIDEO_VIEW_COUNT, (int) $v['view_count'] );
		}
		if ( ! empty( $v['thumbnail_url'] ) ) {
			update_post_meta( $id, Meta_Keys::VIDEO_THUMBNAIL_URL, esc_url_raw( $v['thumbnail_url'] ) );
		}
		if ( ! empty( $v['youtube_url'] ) ) {
			update_post_meta( $id, Meta_Keys::VIDEO_YOUTUBE_URL, esc_url_raw( $v['youtube_url'] ) );
		}
		if ( array_key_exists( 'editorial_video_url', $v ) ) {
			$editorial_url = Video_Meta::normalize_editorial_youtube_url( $v['editorial_video_url'] );
			if ( $editorial_url === '' ) {
				delete_post_meta( $id, Meta_Keys::VIDEO_EDITORIAL_URL );
			} elseif ( $editorial_url !== null ) {
				update_post_meta( $id, Meta_Keys::VIDEO_EDITORIAL_URL, $editorial_url );
			}
		}
		if ( array_key_exists( 'timestamps', $v ) && is_array( $v['timestamps'] ) ) {
			$duration   = isset( $v['duration_seconds'] ) && is_numeric( $v['duration_seconds'] )
				? max( 0, (int) $v['duration_seconds'] )
				: (int) get_post_meta( $id, Meta_Keys::VIDEO_DURATION, true );
			$timestamp_rows = self::prepare_timestamp_import_rows( $v['timestamps'], $youtube_id );
			$timestamps     = Video_Meta::sanitize_timestamps( $timestamp_rows, $duration );
			if ( self::should_persist_editorial_rows( $v['timestamps'], $timestamps ) ) {
				update_post_meta( $id, Meta_Keys::VIDEO_TIMESTAMPS, wp_json_encode( $timestamps ) );
			}
		}
		if ( array_key_exists( 'key_concepts', $v ) && is_array( $v['key_concepts'] ) ) {
			$key_concept_rows = self::prepare_key_concept_import_rows( $v['key_concepts'], $youtube_id );
			$key_concepts     = Video_Meta::sanitize_key_concepts( $key_concept_rows );
			if ( self::should_persist_editorial_rows( $v['key_concepts'], $key_concepts ) ) {
				update_post_meta( $id, Meta_Keys::VIDEO_KEY_CONCEPTS, wp_json_encode( $key_concepts ) );
			}
		}
		if ( isset( $v['transcript_available'] ) ) {
			update_post_meta( $id, Meta_Keys::VIDEO_TRANSCRIPT_AVAILABLE, $v['transcript_available'] ? '1' : '0' );
		}

		// Produits mentionnés : résolution slug → post ID, stockage en JSON
		$product_ids = [];
		foreach ( ( $v['products_mentioned'] ?? [] ) as $slug ) {
			$slug = sanitize_title( $slug );
			if ( isset( $product_map[ $slug ] ) ) {
				$product_ids[] = (int) $product_map[ $slug ];
			}
		}
		update_post_meta( $id, Meta_Keys::VIDEO_PRODUCTS, wp_json_encode( $product_ids ) );

		// Taxonomies (résolution par slug, on ignore les slugs invalides silencieusement)
		self::set_video_terms( $id, $v['topics']  ?? [], Taxonomies::TOPIC );
		self::set_video_terms( $id, $v['formats'] ?? [], Taxonomies::FORMAT );
		self::set_video_terms( $id, $v['paths']   ?? [], Taxonomies::PATH );

		return [ 'id' => (int) $id, 'created' => $created ];
	}

	/**
	 * Un tableau vide explicite efface le champ. Un tableau non vide dont aucune
	 * ligne ne survit à la validation est malformé et ne doit jamais effacer la
	 * valeur éditoriale déjà enregistrée.
	 */
	private static function should_persist_editorial_rows( array $raw, array $sanitized ): bool {
		return $raw === [] || $sanitized !== [];
	}

	/** Ajoute des UUID déterministes aux passages importés qui n'en ont pas. */
	private static function prepare_timestamp_import_rows( array $rows, string $youtube_id ): array {
		$occurrences = [];
		$seen_ids    = [];
		$reserved_ids = [];
		foreach ( $rows as $row ) {
			$id = is_array( $row ) && is_scalar( $row['id'] ?? null ) ? strtolower( (string) $row['id'] ) : '';
			if ( wp_is_uuid( $id ) ) {
				$reserved_ids[ $id ] = true;
			}
		}
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = is_scalar( $row['id'] ?? null ) ? strtolower( (string) $row['id'] ) : '';
			$fingerprint = hash( 'sha256', wp_json_encode( [
				$row['seconds'] ?? null,
				$row['label'] ?? null,
				$row['takeaway'] ?? null,
			] ) );
			$occurrence = $occurrences[ $fingerprint ] ?? 0;
			$occurrences[ $fingerprint ] = $occurrence + 1;
			if ( wp_is_uuid( $id ) && ! isset( $seen_ids[ $id ] ) ) {
				$seen_ids[ $id ] = true;
				continue;
			}
			do {
				$id = self::stable_import_uuid( $youtube_id . '|timestamp|' . $fingerprint . '|' . $occurrence );
				$occurrence++;
			} while ( isset( $seen_ids[ $id ] ) || isset( $reserved_ids[ $id ] ) );
			$rows[ $index ]['id'] = $id;
			$seen_ids[ $id ] = true;
		}
		return $rows;
	}

	/** Ajoute des UUID déterministes aux points importés, y compris l'ancien format string[]. */
	private static function prepare_key_concept_import_rows( array $rows, string $youtube_id ): array {
		$occurrences = [];
		$seen_ids    = [];
		$reserved_ids = [];
		foreach ( $rows as $row ) {
			$id = is_array( $row ) && is_scalar( $row['id'] ?? null ) ? strtolower( (string) $row['id'] ) : '';
			if ( wp_is_uuid( $id ) ) {
				$reserved_ids[ $id ] = true;
			}
		}
		foreach ( $rows as $index => $row ) {
			$text = is_string( $row )
				? $row
				: ( is_array( $row ) && is_scalar( $row['text'] ?? null ) ? (string) $row['text'] : '' );
			$id = is_array( $row ) && is_scalar( $row['id'] ?? null ) ? strtolower( (string) $row['id'] ) : '';
			$fingerprint = hash( 'sha256', sanitize_text_field( $text ) );
			$occurrence = $occurrences[ $fingerprint ] ?? 0;
			$occurrences[ $fingerprint ] = $occurrence + 1;
			if ( wp_is_uuid( $id ) && ! isset( $seen_ids[ $id ] ) ) {
				$seen_ids[ $id ] = true;
				continue;
			}
			do {
				$id = self::stable_import_uuid( $youtube_id . '|key-concept|' . $fingerprint . '|' . $occurrence );
				$occurrence++;
			} while ( isset( $seen_ids[ $id ] ) || isset( $reserved_ids[ $id ] ) );
			$rows[ $index ] = [
				'id'   => $id,
				'text' => $text,
			];
			$seen_ids[ $id ] = true;
		}
		return $rows;
	}

	/** Construit un UUID v5-compatible déterministe sans dépendance externe. */
	private static function stable_import_uuid( string $seed ): string {
		$hash = hash( 'sha256', $seed );
		return substr( $hash, 0, 8 ) . '-'
			. substr( $hash, 8, 4 ) . '-5'
			. substr( $hash, 13, 3 ) . '-a'
			. substr( $hash, 17, 3 ) . '-'
			. substr( $hash, 20, 12 );
	}

	private static function find_video_id_by_youtube_id( string $youtube_id ): int {
		$ids = get_posts( [
			'post_type'      => CPT::VIDEO,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'   => Meta_Keys::VIDEO_YOUTUBE_ID,
					'value' => $youtube_id,
				],
			],
		] );
		return $ids ? (int) $ids[0] : 0;
	}

	private static function set_video_terms( int $post_id, array $slugs, string $taxonomy ): void {
		if ( ! $slugs ) {
			return;
		}
		$term_ids = [];
		foreach ( $slugs as $slug ) {
			$slug = sanitize_title( $slug );
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term ) {
				$term_ids[] = (int) $term->term_id;
			}
		}
		if ( $term_ids ) {
			wp_set_object_terms( $post_id, $term_ids, $taxonomy, false );
		}
	}
}
