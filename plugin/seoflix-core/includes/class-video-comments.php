<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discussions privées des vidéos, stockées dans les commentaires natifs WordPress.
 */
final class Video_Comments {

	public const COMMENT_TYPE = 'seoflix_video_discussion';
	public const MIN_LENGTH   = 3;
	public const MAX_LENGTH   = 1500;
	public const RATE_SECONDS = 45;
	public const ERASER_BATCH = 100;

	private const ACTION          = 'seoflix_video_comment';
	private const TOMBSTONE_NAME  = 'Utilisateur supprimé';
	private const TOMBSTONE_BODY  = 'Message supprimé à la demande de son auteur.';
	private const ERROR_CODES     = [
		'invalid', 'nonce', 'login', 'forbidden', 'disabled', 'closed', 'video',
		'files', 'content_short', 'content_long', 'content_unsafe', 'content_link',
		'parent', 'rate', 'duplicate', 'failed',
	];

	private static bool $inside_validated_handler = false;
	private static bool $inside_private_operation = false;

	public static function init(): void {
		add_action( 'admin_post_seoflix_video_comment', [ self::class, 'handle_submission' ] );

		// Ferme wp-comments-post et les insertions directes pour les vidéos.
		add_filter( 'preprocess_comment', [ self::class, 'guard_direct_submission' ], 1 );
		add_filter( 'wp_insert_comment_data', [ self::class, 'guard_final_insertion' ], 1, 2 );

		// La surface REST ne peut ni écrire ni exposer les discussions privées.
		add_filter( 'rest_pre_insert_comment', [ self::class, 'guard_rest_insertion' ], 10, 2 );
		add_filter( 'rest_pre_dispatch', [ self::class, 'guard_rest_mutation' ], 10, 3 );
		add_filter( 'rest_comment_query', [ self::class, 'guard_rest_query' ], 10, 2 );
		add_filter( 'comments_clauses', [ self::class, 'guard_private_reads' ], 10, 2 );

		add_filter( 'wp_privacy_personal_data_erasers', [ self::class, 'register_privacy_eraser' ] );
	}

	public static function handle_submission(): void {
		$post_id   = self::posted_integer( 'post_id' );
		$parent_id = self::posted_integer( 'parent_id' );
		$redirect  = $post_id > 0 ? get_permalink( $post_id ) : home_url( '/' );

		if ( ! is_user_logged_in() ) {
			self::redirect_result( $redirect, 'login' );
		}
		if ( ! current_user_can( 'read' ) ) {
			self::redirect_result( $redirect, 'forbidden' );
		}
		if ( ! FeatureFlags::video_discussions_enabled() ) {
			self::redirect_result( $redirect, 'disabled' );
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== CPT::VIDEO || $post->post_status !== 'publish' ) {
			self::redirect_result( $redirect, 'video' );
		}
		$redirect = get_permalink( $post_id );

		$nonce = isset( $_POST['seoflix_video_comment_nonce'] ) && is_scalar( $_POST['seoflix_video_comment_nonce'] )
			? (string) wp_unslash( $_POST['seoflix_video_comment_nonce'] )
			: '';
		if ( ! wp_verify_nonce( $nonce, 'seoflix_video_comment_' . $post_id ) ) {
			self::redirect_result( $redirect, 'nonce' );
		}
		if ( ! comments_open( $post_id ) ) {
			self::redirect_result( $redirect, 'closed' );
		}
		if ( ! empty( $_FILES ) ) {
			self::redirect_result( $redirect, 'files' );
		}
		if ( ! isset( $_POST['content'] ) || ! is_scalar( $_POST['content'] ) ) {
			self::redirect_result( $redirect, 'invalid' );
		}

		$content = self::validate_plain_text( (string) wp_unslash( $_POST['content'] ) );
		if ( is_wp_error( $content ) ) {
			self::redirect_result( $redirect, $content->get_error_code() );
		}
		if ( $parent_id < 0 || ! self::valid_parent( $parent_id, $post_id ) ) {
			self::redirect_result( $redirect, 'parent' );
		}

		$user     = wp_get_current_user();
		$rate_key = 'seoflix_video_discussion_rate_' . wp_hash( (int) $user->ID . '|' . $post_id );
		if ( get_transient( $rate_key ) ) {
			self::redirect_result( $redirect, 'rate' );
		}

		$comment_data = [
			'comment_post_ID'      => $post_id,
			'comment_parent'       => $parent_id,
			'comment_type'         => self::COMMENT_TYPE,
			'comment_content'      => $content,
			'comment_author'       => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_author_url'   => '',
			'comment_author_IP'    => '',
			'comment_agent'        => '',
			'user_id'              => (int) $user->ID,
		];

		self::$inside_validated_handler = true;
		try {
			$comment_id = wp_new_comment( $comment_data, true );
		} finally {
			self::$inside_validated_handler = false;
		}

		if ( is_wp_error( $comment_id ) ) {
			$core_code = $comment_id->get_error_code();
			$code      = 'comment_duplicate' === $core_code ? 'duplicate' : 'failed';
			$code      = false !== strpos( (string) $core_code, 'flood' ) ? 'rate' : $code;
			self::redirect_result( $redirect, $code );
		}
		if ( ! $comment_id ) {
			self::redirect_result( $redirect, 'failed' );
		}

		// Le marqueur n'est posé qu'après une insertion réussie.
		set_transient( $rate_key, 1, self::RATE_SECONDS );
		$status = 'approved' === wp_get_comment_status( (int) $comment_id ) ? 'submitted' : 'pending';
		self::redirect_result( $redirect, $status );
	}

	/**
	 * Valide puis nettoie un message en texte brut. La méthode reste testable sans WordPress.
	 *
	 * @return string|\WP_Error
	 */
	public static function validate_plain_text( string $content ) {
		$content = str_replace( [ "\r\n", "\r" ], "\n", $content );
		$content = trim( $content );

		if ( 1 !== preg_match( '//u', $content ) ) {
			return new \WP_Error( 'content_unsafe' );
		}
		$length = function_exists( 'mb_strlen' )
			? mb_strlen( $content, 'UTF-8' )
			: preg_match_all( '/./us', $content, $characters );
		if ( $length < self::MIN_LENGTH ) {
			return new \WP_Error( 'content_short' );
		}
		if ( $length > self::MAX_LENGTH ) {
			return new \WP_Error( 'content_long' );
		}

		$decoded = $content;
		for ( $pass = 0; $pass < 3; $pass++ ) {
			$next = html_entity_decode( $decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( $next === $decoded ) {
				break;
			}
			$decoded = $next;
		}
		$stripped = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $decoded, true ) : strip_tags( $decoded );
		// Rejette HTML, commentaires Gutenberg, shortcodes et balises actives/code-like.
		if (
			$decoded !== $stripped
			|| false !== strpos( $decoded, '<!--' )
			|| preg_match( '/[<>]/u', $decoded )
			|| preg_match( '/\[\/?[a-z][^\]\r\n]*\]/iu', $decoded ) // shortcode
			|| preg_match( '/\b(?:iframe|script|style|object|embed|svg|math|form|input|code|pre)\b\s*[:={]/iu', $decoded )
		) {
			return new \WP_Error( 'content_unsafe' );
		}

		if ( self::contains_link_signal( $decoded ) ) {
			return new \WP_Error( 'content_link' );
		}

		// La sanitation intervient uniquement après les rejets fail-closed.
		$sanitized = sanitize_textarea_field( $decoded );
		$sanitized = str_replace( [ "\r\n", "\r" ], "\n", $sanitized );
		$sanitized_length = function_exists( 'mb_strlen' )
			? mb_strlen( $sanitized, 'UTF-8' )
			: preg_match_all( '/./us', $sanitized, $characters );
		if ( $sanitized_length < self::MIN_LENGTH ) {
			return new \WP_Error( 'content_short' );
		}
		if ( $sanitized_length > self::MAX_LENGTH ) {
			return new \WP_Error( 'content_long' );
		}
		return $sanitized;
	}

	private static function contains_link_signal( string $content ): bool {
		$normalized = $content;
		// zero-width: suppression des séparateurs invisibles utilisés pour obfusquer une URL.
		$normalized = preg_replace( '/[\x{00AD}\x{034F}\x{061C}\x{180E}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}]/u', '', $normalized );
		// unicode-dot: conversion des points visuellement équivalents.
		$normalized = str_replace( [ '。', '．', '｡', '․', '‧', '∙', '⋅' ], '.', $normalized );
		$normalized = preg_replace( '/\s*(?:\[\s*\.\s*\]|\(\s*dot\s*\))\s*/iu', '.', $normalized ); // [.] / (dot)
		$normalized = preg_replace( '/\s+dot\s+/iu', '.', $normalized ); // dot
		$normalized = preg_replace( '/(?<=[\p{L}\p{N}])\s*\.\s*(?=[\p{L}\p{N}])/u', '.', $normalized ); // espaces autour du point
		$normalized = html_entity_decode( (string) $normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		if ( false !== strpos( $normalized, '@' ) ) {
			return true;
		}
		if ( preg_match( '/\b(?:https?|ftps?|mailto|data|javascript|file|hxxps?)\s*(?::|：|\/\/)/iu', $normalized ) ) {
			return true;
		}
		if ( preg_match( '/\bwww\s*\./iu', $normalized ) || preg_match( '/\bxn--[a-z0-9-]+/iu', $normalized ) ) {
			return true;
		}

		// Domaines ASCII, IDN et punycode, y compris sans schéma.
		return 1 === preg_match(
			'/(?<![\p{L}\p{N}_-])(?:[\p{L}\p{N}](?:[\p{L}\p{N}-]{0,61}[\p{L}\p{N}])?\.)+(?:[\p{L}]{2,63}|xn--[a-z0-9-]{2,59})(?![\p{L}\p{N}_-])/iu',
			$normalized
		);
	}

	private static function valid_parent( int $parent_id, int $post_id ): bool {
		if ( 0 === $parent_id ) {
			return true;
		}
		$parent = get_comment( $parent_id );
		if ( ! $parent
			|| (int) $parent->comment_post_ID !== $post_id
			|| $parent->comment_type !== self::COMMENT_TYPE
			|| $parent->comment_approved !== '1'
			|| (int) $parent->comment_parent !== 0
		) {
			return false;
		}
		return true;
	}

	private static function posted_integer( string $key ): int {
		if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
			return 0;
		}
		$value = (string) wp_unslash( $_POST[ $key ] );
		return preg_match( '/^\d+$/', $value ) ? (int) $value : -1;
	}

	private static function redirect_result( string $redirect, string $code ): void {
		$code = in_array( $code, array_merge( self::ERROR_CODES, [ 'submitted', 'pending' ] ), true ) ? $code : 'invalid';
		$url  = add_query_arg( 'discussion_status', $code, $redirect ) . '#discussion-video';
		wp_safe_redirect( $url, 303 );
		exit;
	}

	public static function guard_direct_submission( array $comment_data ): array {
		$post_id = isset( $comment_data['comment_post_ID'] ) ? (int) $comment_data['comment_post_ID'] : 0;
		if ( self::$inside_validated_handler || ! $post_id || get_post_type( $post_id ) !== CPT::VIDEO ) {
			return $comment_data;
		}
		wp_die( esc_html__( 'Cette discussion utilise le formulaire privé WEAS.', 'seoflix' ), '', [ 'response' => 403 ] );
	}

	public static function guard_final_insertion( array $data, array $comment_data ): array {
		$post_id = isset( $data['comment_post_ID'] ) ? (int) $data['comment_post_ID'] : 0;
		$type    = isset( $data['comment_type'] ) ? (string) $data['comment_type'] : '';
		$is_video_surface = self::COMMENT_TYPE === $type || ( $post_id && get_post_type( $post_id ) === CPT::VIDEO );
		if ( $is_video_surface && ! self::$inside_validated_handler ) {
			wp_die( esc_html__( 'Écriture de discussion refusée.', 'seoflix' ), '', [ 'response' => 403 ] );
		}
		if ( self::$inside_validated_handler ) {
			$data['comment_type']      = self::COMMENT_TYPE;
			$data['comment_author_IP'] = '';
			$data['comment_agent']     = '';
		}
		return $data;
	}

	public static function guard_rest_insertion( $prepared_comment, \WP_REST_Request $request ) {
		if ( self::rest_request_targets_video_discussion( $request ) ) {
			return new \WP_Error( 'rest_forbidden', 'Discussion privée non modifiable via REST.', [ 'status' => 403 ] );
		}
		return $prepared_comment;
	}

	public static function guard_rest_mutation( $result, \WP_REST_Server $server, \WP_REST_Request $request ) {
		$method = strtoupper( $request->get_method() );
		$targets_private_discussion = self::rest_request_targets_video_discussion( $request );
		if ( 'GET' === $method && $targets_private_discussion && ! self::can_view_discussions() ) {
			return new \WP_Error( 'rest_forbidden', 'Discussion privée non consultable via REST.', [ 'status' => 403 ] );
		}
		if ( in_array( $method, [ 'POST', 'PUT', 'PATCH', 'DELETE' ], true ) && $targets_private_discussion ) {
			return new \WP_Error( 'rest_forbidden', 'Discussion privée non modifiable via REST.', [ 'status' => 403 ] );
		}
		return $result;
	}

	private static function rest_request_targets_video_discussion( \WP_REST_Request $request ): bool {
		$route = $request->get_route();
		if ( ! preg_match( '#^/wp/v2/comments(?:/(\d+))?$#', $route, $match ) ) {
			return false;
		}
		$comment_id = ! empty( $match[1] ) ? (int) $match[1] : (int) $request->get_param( 'id' );
		if ( $comment_id ) {
			$comment = get_comment( $comment_id );
			return $comment && ( $comment->comment_type === self::COMMENT_TYPE || get_post_type( (int) $comment->comment_post_ID ) === CPT::VIDEO );
		}
		$post_id = (int) $request->get_param( 'post' );
		$type    = (string) $request->get_param( 'type' );
		return self::COMMENT_TYPE === $type || ( $post_id && get_post_type( $post_id ) === CPT::VIDEO );
	}

	public static function guard_rest_query( array $args, \WP_REST_Request $request ): array {
		if ( ! self::can_view_discussions() ) {
			$excluded            = isset( $args['type__not_in'] ) ? (array) $args['type__not_in'] : [];
			$excluded[]          = self::COMMENT_TYPE;
			$args['type__not_in'] = array_values( array_unique( $excluded ) );
		}
		return $args;
	}

	public static function guard_private_reads( array $clauses, \WP_Comment_Query $query ): array {
		// Les modérateurs doivent pouvoir traiter les fils dans wp-admin même si le flag public est coupé.
		if ( self::$inside_private_operation || ( is_admin() && current_user_can( 'moderate_comments' ) ) || self::can_view_discussions() ) {
			return $clauses;
		}
		global $wpdb;
		$clauses['where'] .= $wpdb->prepare( " AND {$wpdb->comments}.comment_type <> %s", self::COMMENT_TYPE );
		return $clauses;
	}

	private static function can_view_discussions(): bool {
		return FeatureFlags::video_discussions_enabled() && is_user_logged_in() && current_user_can( 'read' );
	}

	public static function register_privacy_eraser( array $erasers ): array {
		// Notre politique doit s'exécuter avant l'effaceur natif, qui viderait sinon l'e-mail de correspondance.
		return [
			'seoflix-video-discussions' => [
				'eraser_friendly_name' => 'Discussions privées des vidéos WEAS',
				'callback'             => [ self::class, 'personal_data_eraser' ],
			],
		] + $erasers;
	}

	public static function personal_data_eraser( string $email_address, int $page = 1 ): array {
		$email_address = sanitize_email( $email_address );
		if ( ! $email_address ) {
			return [ 'items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true ];
		}

		self::$inside_private_operation = true;
		try {
			// Toujours le premier lot restant : les lignes sont supprimées/anonymisées pendant la boucle.
			$comments = get_comments( [
				'type'                 => self::COMMENT_TYPE,
				'status'               => 'all',
				'author_email'         => $email_address,
				'number'               => self::ERASER_BATCH,
				'orderby'              => 'comment_ID',
				'order'                => 'ASC',
				'no_found_rows'        => true,
				'update_comment_meta_cache' => false,
			] );

			$removed = false;
			$retained = false;
			$failed = false;
			foreach ( $comments as $comment ) {
				$result   = self::erase_comment( $comment );
				if ( ! $result ) {
					$failed = true;
					continue;
				}
				$removed  = true;
				$updated  = $result ? get_comment( (int) $comment->comment_ID ) : null;
				$retained = $retained || ( $updated && self::TOMBSTONE_BODY === $updated->comment_content );
			}
		} finally {
			self::$inside_private_operation = false;
		}

		$messages = [];
		if ( $retained ) {
			$messages[] = 'Un message parent a été anonymisé pour préserver les réponses d’autres utilisateurs.';
		}
		if ( $failed ) {
			$messages[] = 'Au moins une discussion n’a pas pu être effacée ; les données de correspondance sont conservées pour permettre une reprise.';
		}

		return [
			'items_removed'  => $removed,
			'items_retained' => $retained || $failed,
			'messages'       => $messages,
			'done'           => ! $failed && count( $comments ) < self::ERASER_BATCH,
		];
	}

	/**
	 * Supprime une feuille ou transforme un parent avec réponses en tombstone sans PII.
	 */
	public static function erase_comment( $comment ): bool {
		$comment = is_object( $comment ) ? $comment : get_comment( (int) $comment );
		if ( ! $comment || $comment->comment_type !== self::COMMENT_TYPE ) {
			return false;
		}

		self::$inside_private_operation = true;
		try {
			$children = get_comments( [
				'parent'  => (int) $comment->comment_ID,
				'type'    => self::COMMENT_TYPE,
				'status'  => 'all',
				'number'  => 1,
				'count'   => true,
			] );
			if ( $children ) {
				// Purger d'abord : en cas d'échec, l'e-mail reste disponible pour une reprise RGPD.
				if ( ! self::purge_comment_metadata( (int) $comment->comment_ID ) ) {
					return false;
				}
				$updated = wp_update_comment( [
					'comment_ID'           => (int) $comment->comment_ID,
					'comment_author'       => self::TOMBSTONE_NAME,
					'comment_author_email' => '',
					'comment_author_url'   => '',
					'comment_author_IP'    => '',
					'comment_agent'        => '',
					'comment_content'      => self::TOMBSTONE_BODY,
					'user_id'              => 0,
				] );
				return (bool) $updated;
			}
			return (bool) wp_delete_comment( (int) $comment->comment_ID, true );
		} finally {
			self::$inside_private_operation = false;
		}
	}

	/**
	 * Supprime toutes les métadonnées d'un tombstone et vérifie les échecs d'écriture.
	 */
	private static function purge_comment_metadata( int $comment_id ): bool {
		$metadata = get_comment_meta( $comment_id );
		foreach ( array_keys( $metadata ) as $meta_key ) {
			if ( delete_comment_meta( $comment_id, (string) $meta_key ) ) {
				continue;
			}
			if ( get_comment_meta( $comment_id, (string) $meta_key, false ) ) {
				return false;
			}
		}
		return true;
	}
}
