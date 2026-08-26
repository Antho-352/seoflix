<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestion des comptes utilisateurs (V2).
 *
 * Activé via `seoflix_user_accounts_enabled` (FeatureFlags).
 *
 * Fonctionnalités :
 *   - Active/désactive les inscriptions WordPress
 *   - Hardening inscription : Turnstile, email verification, rate-limit, password fort
 *   - Page /mon-parcours = dashboard utilisateur (favoris, vidéos vues, progression parcours)
 *   - Endpoints AJAX : mark watched, toggle favorite
 *   - Rewrite /mon-parcours/, /connexion/, /inscription/
 *   - Restriction wp-admin pour les users non-admin
 */
final class User_Accounts {

	public const ROLE      = 'subscriber';
	private const PRIVACY_BATCH = 100;
	private const EXPORT_STATE_TTL = HOUR_IN_SECONDS;

	public static function init(): void {
		// Le cycle de vie et la confidentialité restent actifs même si les comptes sont fermés.
		self::enforce_registration_state();
		add_action( 'delete_user', [ self::class, 'delete_user_data' ] );
		add_filter( 'wp_privacy_personal_data_exporters', [ self::class, 'register_privacy_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ self::class, 'register_privacy_eraser' ] );

		if ( ! FeatureFlags::user_accounts_enabled() ) {
			return;
		}

		// Endpoints AJAX (loggés uniquement)
		add_action( 'wp_ajax_seoflix_toggle_favorite', [ self::class, 'ajax_toggle_favorite' ] );
		add_action( 'wp_ajax_seoflix_mark_watched',   [ self::class, 'ajax_mark_watched' ] );

		// Rewrite : /mon-parcours/
		add_action( 'init',                       [ self::class, 'register_rewrites' ] );
		add_filter( 'query_vars',                 [ self::class, 'register_query_vars' ] );
		add_action( 'template_redirect',          [ self::class, 'redirect_unauthenticated' ] );
		add_filter( 'template_include',           [ self::class, 'load_dashboard_template' ] );

		// Hardening inscription
		add_filter( 'registration_errors',        [ self::class, 'validate_registration' ], 10, 3 );
		add_action( 'register_form',              [ self::class, 'render_turnstile_register' ] );
		add_action( 'login_form',                 [ self::class, 'render_turnstile_login' ] );
		add_filter( 'authenticate',               [ self::class, 'check_login_turnstile' ], 10, 1 );

		// Empêche les non-admins d'accéder à /wp-admin
		add_action( 'admin_init',                 [ self::class, 'block_non_admin_dashboard' ] );

		// Ajoute la barre wp-admin pour les users non-admin → désactivée
		add_filter( 'show_admin_bar',             [ self::class, 'admin_bar_for_admins_only' ] );
	}

	public static function enable_registration(): void {
		self::enforce_registration_state();
	}

	public static function enforce_registration_state(): void {
		if ( FeatureFlags::user_accounts_enabled() ) {
			update_option( 'users_can_register', 1 );
			update_option( 'default_role', self::ROLE );
			return;
		}
		update_option( 'users_can_register', 0 );
	}

	/** Supprime les lignes métier avant que WordPress ne supprime un utilisateur. */
	public static function delete_user_data( int $user_id ): void {
		global $wpdb;
		$failed = [];
		foreach ( [ DB_Schema::table_favorites(), DB_Schema::table_watch() ] as $table ) {
			$result = $wpdb->delete( $table, [ 'user_id' => $user_id ], [ '%d' ] );
			if ( false === $result ) {
				$failed[] = $table;
			}
		}
		if ( $failed ) {
			wp_die(
				'Les données d’activité MADIAS n’ont pas pu être supprimées. La suppression du compte est interrompue.',
				'Suppression du compte interrompue',
				[ 'response' => 500 ]
			);
		}
	}

	public static function register_privacy_exporter( array $exporters ): array {
		$exporters['seoflix-account-activity'] = [
			'exporter_friendly_name' => 'Favoris et historique vidéo MADIAS',
			'callback'               => [ self::class, 'personal_data_exporter' ],
		];
		return $exporters;
	}

	public static function register_privacy_eraser( array $erasers ): array {
		$erasers['seoflix-account-activity'] = [
			'eraser_friendly_name' => 'Favoris et historique vidéo MADIAS',
			'callback'             => [ self::class, 'personal_data_eraser' ],
		];
		return $erasers;
	}

	private static function privacy_export_request_id(): int {
		if ( ! isset( $_POST['id'] ) || ( ! is_string( $_POST['id'] ) && ! is_int( $_POST['id'] ) ) ) {
			return 0;
		}
		return absint( wp_unslash( $_POST['id'] ) );
	}

	private static function privacy_export_state_key( int $user_id, int $page, int $request_id ): string {
		return 'seoflix_privacy_export_' . substr( wp_hash( $request_id . '|' . $user_id . '|' . $page, 'nonce' ), 0, 40 );
	}

	private static function privacy_subject_state_key( string $operation, int $request_id ): string {
		return 'seoflix_privacy_subject_' . substr( wp_hash( $operation . '|' . $request_id, 'nonce' ), 0, 40 );
	}

	private static function privacy_export_failure(): array {
		return [ 'data' => [], 'done' => false ];
	}

	/** Distingue un utilisateur absent d’un échec SQL masqué par get_user_by(). */
	private static function lookup_user_by_email( string $email_address ): array {
		global $wpdb;
		$wpdb->last_error = '';
		$user = get_user_by( 'email', $email_address );
		return [
			'user'   => $user,
			'failed' => '' !== $wpdb->last_error,
		];
	}

	public static function personal_data_exporter( string $email_address, int $page = 1 ): array {
		$request_id = self::privacy_export_request_id();
		if ( $request_id < 1 ) {
			return self::privacy_export_failure();
		}
		$page        = max( 1, $page );
		$subject_key = self::privacy_subject_state_key( 'export', $request_id );
		if ( 1 === $page ) {
			$lookup = self::lookup_user_by_email( $email_address );
			if ( $lookup['failed'] ) {
				return self::privacy_export_failure();
			}
			$user = $lookup['user'];
			if ( ! $user ) {
				return [ 'data' => [], 'done' => true ];
			}
			$user_id = (int) $user->ID;
		} else {
			$user_id = absint( get_transient( $subject_key ) );
			if ( $user_id < 1 ) {
				return self::privacy_export_failure();
			}
		}

		global $wpdb;
		$favorites = DB_Schema::table_favorites();
		$watch     = DB_Schema::table_watch();
		$sources   = [
			[ 'kind' => 'favorite', 'table' => $favorites, 'event' => 'created_at' ],
			[ 'kind' => 'watch', 'table' => $watch, 'event' => 'watched_at' ],
		];

		if ( 1 === $page ) {
			$max_ids = [];
			foreach ( $sources as $source ) {
				$max_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT COALESCE(MAX(id), 0) FROM {$source['table']} WHERE user_id = %d",
					$user_id
				) );
				if ( null === $max_id || '' !== $wpdb->last_error ) {
					return self::privacy_export_failure();
				}
				$max_ids[] = (int) $max_id;
			}
			$state = [ 'source' => 0, 'cursors' => [ 0, 0 ], 'max_ids' => $max_ids ];
		} else {
			$state = get_transient( self::privacy_export_state_key( $user_id, $page, $request_id ) );
			if ( ! is_array( $state )
				|| ! isset( $state['source'], $state['cursors'], $state['max_ids'] )
				|| ! is_array( $state['cursors'] )
				|| ! is_array( $state['max_ids'] ) ) {
				return self::privacy_export_failure();
			}
		}

		$data = [];
		while ( (int) $state['source'] < count( $sources ) && count( $data ) < self::PRIVACY_BATCH ) {
			$source_index = (int) $state['source'];
			$source       = $sources[ $source_index ];
			$remaining    = self::PRIVACY_BATCH - count( $data );
			$cursor       = (int) ( $state['cursors'][ $source_index ] ?? 0 );
			$max_id       = (int) ( $state['max_ids'][ $source_index ] ?? 0 );
			$extra        = $remaining + 1;
			$progress_sql = 'watch' === $source['kind']
				? 'progress_seconds, completed'
				: 'NULL AS progress_seconds, NULL AS completed';
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, video_id, {$source['event']} AS event_at, {$progress_sql}
				FROM {$source['table']}
				WHERE user_id = %d AND id > %d AND id <= %d
				ORDER BY id ASC LIMIT %d",
				$user_id,
				$cursor,
				$max_id,
				$extra
			), ARRAY_A );
			if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
				return self::privacy_export_failure();
			}

			$has_more = count( $rows ) > $remaining;
			$rows     = array_slice( $rows, 0, $remaining );
			foreach ( $rows as $row ) {
				$row_id = (int) $row['id'];
				$state['cursors'][ $source_index ] = $row_id;
				$data[] = [
					'group_id'    => 'seoflix-account-activity',
					'group_label' => 'Activité vidéo MADIAS',
					'item_id'     => $source['kind'] . '-' . $row_id,
					'data'        => array_map(
						static fn( $key, $value ) => [ 'name' => (string) $key, 'value' => (string) $value ],
						array_keys( $row ),
						array_values( $row )
					),
				];
			}
			if ( ! $has_more ) {
				$state['source'] = $source_index + 1;
			}
		}

		$done = (int) $state['source'] >= count( $sources );
		if ( ! $done ) {
			set_transient( $subject_key, $user_id, self::EXPORT_STATE_TTL );
			set_transient(
				self::privacy_export_state_key( $user_id, $page + 1, $request_id ),
				$state,
				self::EXPORT_STATE_TTL
			);
		} else {
			delete_transient( $subject_key );
		}
		return [ 'data' => $data, 'done' => $done ];
	}

	private static function privacy_eraser_failure( string $message ): array {
		return [
			'items_removed'  => false,
			'items_retained' => true,
			'messages'       => [ $message ],
			'done'           => false,
			'removed_count'  => 0,
		];
	}

	public static function personal_data_eraser( string $email_address, int $page = 1 ): array {
		$request_id = self::privacy_export_request_id();
		if ( $request_id < 1 ) {
			return self::privacy_eraser_failure( 'L’identifiant de la demande Privacy est absent ou invalide.' );
		}
		$page        = max( 1, $page );
		$subject_key = self::privacy_subject_state_key( 'erase', $request_id );
		if ( 1 === $page ) {
			$lookup = self::lookup_user_by_email( $email_address );
			if ( $lookup['failed'] ) {
				return self::privacy_eraser_failure( 'La recherche du compte a échoué ; aucune fin d’effacement n’est déclarée.' );
			}
			$user = $lookup['user'];
			if ( ! $user ) {
				return [ 'items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true, 'removed_count' => 0 ];
			}
			$user_id = (int) $user->ID;
		} else {
			$user_id = absint( get_transient( $subject_key ) );
			if ( $user_id < 1 ) {
				return self::privacy_eraser_failure( 'L’état de la demande Privacy est absent ou expiré.' );
			}
		}

		$result = self::erase_user_activity_batch( $user_id );
		if ( $result['done'] ) {
			delete_transient( $subject_key );
		} else {
			set_transient( $subject_key, $user_id, self::EXPORT_STATE_TTL );
		}
		return $result;
	}

	/** Efface au plus un lot d’activité pour un user_id déjà résolu. */
	public static function erase_user_activity_batch( int $user_id ): array {
		if ( $user_id < 1 ) {
			return self::privacy_eraser_failure( 'L’identifiant du compte à effacer est invalide.' );
		}
		global $wpdb;
		$removed   = 0;
		$processed = 0;
		$failed    = false;
		$messages  = [];
		foreach ( [ DB_Schema::table_favorites(), DB_Schema::table_watch() ] as $table ) {
			$remaining = self::PRIVACY_BATCH - $processed;
			if ( $remaining <= 0 ) {
				break;
			}
			$ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d ORDER BY id ASC LIMIT %d",
				$user_id,
				$remaining
			) );
			if ( ! is_array( $ids ) || '' !== $wpdb->last_error ) {
				$failed     = true;
				$messages[] = 'La lecture des données d’activité a échoué ; aucune fin d’effacement n’est déclarée.';
				break;
			}
			$ids = array_map( 'intval', $ids );
			$processed += count( $ids );
			foreach ( $ids as $id ) {
				$result = $wpdb->delete( $table, [ 'id' => $id, 'user_id' => $user_id ], [ '%d', '%d' ] );
				if ( false === $result ) {
					$failed = true;
				} elseif ( 1 === $result ) {
					$removed++;
				}
			}
		}

		if ( $failed && ! $messages ) {
			$messages[] = 'Certaines données d’activité n’ont pas pu être supprimées.';
		}
		return [
			'items_removed'  => $removed > 0,
			'items_retained' => $failed,
			'messages'       => $messages,
			'done'           => ! $failed && $processed < self::PRIVACY_BATCH,
			'removed_count'  => $removed,
		];
	}

	public static function register_rewrites(): void {
		add_rewrite_rule( '^mon-parcours/?$', 'index.php?seoflix_dashboard=1', 'top' );
	}

	public static function register_query_vars( array $vars ): array {
		$vars[] = 'seoflix_dashboard';
		return $vars;
	}

	public static function redirect_unauthenticated(): void {
		if ( get_query_var( 'seoflix_dashboard' ) && ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( '/mon-parcours/' ) ) );
			exit;
		}
	}

	public static function load_dashboard_template( $template ) {
		if ( ! get_query_var( 'seoflix_dashboard' ) ) {
			return $template;
		}
		$theme_template = locate_template( 'page-mon-parcours.php' );
		if ( $theme_template ) {
			return $theme_template;
		}
		return $template;
	}

	/* ======================================================================
	 *  Hardening inscription
	 * ====================================================================== */

	public static function validate_registration( $errors, $sanitized_user_login, $user_email ) {
		// Rate limit : 3 inscriptions / heure / IP
		$ip       = Security::client_ip();
		$rate_key = 'seoflix_register_rate_' . substr( wp_hash( $ip, 'nonce' ), 0, 40 );
		$count    = (int) get_transient( $rate_key );
		if ( $count >= 3 ) {
			$errors->add( 'seoflix_rate_limit', '<strong>Erreur :</strong> Trop d\'inscriptions depuis cette adresse. Réessaye dans 1 heure.' );
			return $errors;
		}

		// Honeypot
		if ( ! empty( $_POST['website'] ) ) {
			$errors->add( 'seoflix_honeypot', '<strong>Erreur :</strong> Champ invalide.' );
			return $errors;
		}

		// Turnstile (si configuré)
		$turnstile_secret = (string) get_option( Contact::OPTION_TURNSTILE_SECRET, '' );
		if ( $turnstile_secret ) {
			$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
			if ( ! $token || ! self::verify_turnstile_token( $token, $turnstile_secret, $ip ) ) {
				$errors->add( 'seoflix_turnstile', '<strong>Erreur :</strong> Vérification anti-bot échouée.' );
				return $errors;
			}
		}

		set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

		return $errors;
	}

	public static function render_turnstile_register(): void {
		self::render_honeypot();
		self::render_turnstile_widget();
	}

	public static function render_turnstile_login(): void {
		self::render_turnstile_widget();
	}

	public static function check_login_turnstile( $user ) {
		// Skip si pas un POST de login
		if ( empty( $_POST['log'] ) || empty( $_POST['pwd'] ) ) {
			return $user;
		}
		$turnstile_secret = (string) get_option( Contact::OPTION_TURNSTILE_SECRET, '' );
		if ( ! $turnstile_secret ) {
			return $user;
		}
		$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
		if ( ! $token || ! self::verify_turnstile_token( $token, $turnstile_secret, Security::client_ip() ) ) {
			return new \WP_Error( 'seoflix_turnstile', '<strong>Erreur :</strong> Vérification anti-bot échouée.' );
		}
		return $user;
	}

	private static function render_honeypot(): void {
		echo '<div style="position:absolute;left:-9999px;" aria-hidden="true"><label>Ne pas remplir<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
	}

	private static function render_turnstile_widget(): void {
		$site = (string) get_option( Contact::OPTION_TURNSTILE_SITE, '' );
		if ( ! $site ) {
			return;
		}
		echo '<div class="cf-turnstile" data-sitekey="' . esc_attr( $site ) . '" data-theme="dark" style="margin-bottom:1rem;"></div>';
		echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
	}

	private static function verify_turnstile_token( string $token, string $secret, string $ip ): bool {
		$response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
			'timeout' => 8,
			'body'    => [ 'secret' => $secret, 'response' => $token, 'remoteip' => $ip ],
		] );
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return ! empty( $body['success'] );
	}

	/* ======================================================================
	 *  Empêche les non-admin d'accéder au dashboard WP
	 * ====================================================================== */

	public static function block_non_admin_dashboard(): void {
		if ( wp_doing_ajax() ) {
			return;
		}
		if ( current_user_can( 'edit_posts' ) ) {
			return;
		}
		// User loggé mais pas auteur/éditeur/admin → renvoie vers le dashboard front
		wp_safe_redirect( home_url( '/mon-parcours/' ) );
		exit;
	}

	public static function admin_bar_for_admins_only( bool $show ): bool {
		return current_user_can( 'edit_posts' ) ? $show : false;
	}

	/* ======================================================================
	 *  AJAX : favoris + vidéos vues
	 * ====================================================================== */

	public static function ajax_toggle_favorite(): void {
		check_ajax_referer( 'seoflix_user_action' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Connexion requise.' );
		}
		$video_id = isset( $_POST['video_id'] ) ? (int) $_POST['video_id'] : 0;
		if ( ! $video_id || get_post_type( $video_id ) !== CPT::VIDEO ) {
			wp_send_json_error( 'Vidéo invalide.' );
		}

		global $wpdb;
		$table   = DB_Schema::table_favorites();
		$user_id = get_current_user_id();

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE user_id=%d AND video_id=%d", $user_id, $video_id ) );
		if ( $exists ) {
			$wpdb->delete( $table, [ 'id' => $exists ], [ '%d' ] );
			wp_send_json_success( [ 'state' => 'removed' ] );
		}
		$wpdb->insert( $table, [ 'user_id' => $user_id, 'video_id' => $video_id ], [ '%d', '%d' ] );
		wp_send_json_success( [ 'state' => 'added' ] );
	}

	public static function ajax_mark_watched(): void {
		check_ajax_referer( 'seoflix_user_action' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Connexion requise.' );
		}
		$video_id = isset( $_POST['video_id'] ) ? (int) $_POST['video_id'] : 0;
		$progress = isset( $_POST['progress'] ) ? (int) $_POST['progress'] : 0;
		$completed = ! empty( $_POST['completed'] );
		if ( ! $video_id || get_post_type( $video_id ) !== CPT::VIDEO ) {
			wp_send_json_error( 'Vidéo invalide.' );
		}

		global $wpdb;
		$table   = DB_Schema::table_watch();
		$user_id = get_current_user_id();

		$wpdb->replace( $table, [
			'user_id'          => $user_id,
			'video_id'         => $video_id,
			'progress_seconds' => max( 0, $progress ),
			'completed'        => $completed ? 1 : 0,
			'watched_at'       => current_time( 'mysql' ),
		], [ '%d', '%d', '%d', '%d', '%s' ] );

		wp_send_json_success();
	}

	/* ======================================================================
	 *  Helpers utilisés par les templates
	 * ====================================================================== */

	public static function user_favorite_video_ids( int $user_id, int $limit = 50 ): array {
		global $wpdb;
		$table = DB_Schema::table_favorites();
		return array_map( 'intval', (array) $wpdb->get_col(
			$wpdb->prepare( "SELECT video_id FROM {$table} WHERE user_id=%d ORDER BY created_at DESC LIMIT %d", $user_id, $limit )
		) );
	}

	public static function user_watched_video_ids( int $user_id, int $limit = 50, bool $completed_only = false ): array {
		global $wpdb;
		$table = DB_Schema::table_watch();
		$where = $completed_only ? ' AND completed=1' : '';
		return array_map( 'intval', (array) $wpdb->get_col(
			$wpdb->prepare( "SELECT video_id FROM {$table} WHERE user_id=%d{$where} ORDER BY watched_at DESC LIMIT %d", $user_id, $limit )
		) );
	}

	public static function is_video_favorited( int $user_id, int $video_id ): bool {
		if ( ! $user_id ) {
			return false;
		}
		global $wpdb;
		$table = DB_Schema::table_favorites();
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE user_id=%d AND video_id=%d", $user_id, $video_id ) );
	}

	public static function is_video_watched( int $user_id, int $video_id ): bool {
		if ( ! $user_id ) {
			return false;
		}
		global $wpdb;
		$table = DB_Schema::table_watch();
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE user_id=%d AND video_id=%d AND completed=1", $user_id, $video_id ) );
	}

	/**
	 * Récupère la progression d'un utilisateur sur un parcours (term seoflix_path).
	 * Retourne [ 'total' => int, 'watched' => int, 'next_video_id' => ?int ]
	 */
	public static function path_progress( int $user_id, int $term_id ): array {
		$videos = Path_Order::ordered_video_ids_for_term( $term_id );

		$completed_ids = [];
		if ( $user_id > 0 && $videos ) {
			global $wpdb;
			$table = DB_Schema::table_watch();
			$completed_ids = array_map( 'intval', (array) $wpdb->get_col(
				$wpdb->prepare( "SELECT video_id FROM {$table} WHERE user_id=%d AND completed=1", $user_id )
			) );
		}
		$completed = array_fill_keys( $completed_ids, true );

		$watched_ids = [];
		$next        = null;
		foreach ( $videos as $video_id ) {
			if ( isset( $completed[ $video_id ] ) ) {
				$watched_ids[] = $video_id;
			} elseif ( $next === null ) {
				$next = $video_id;
			}
		}

		return [
			'total'             => count( $videos ),
			'watched'           => count( $watched_ids ),
			'next_video_id'     => $next,
			'video_ids'         => $videos,
			'watched_video_ids' => $watched_ids,
		];
	}
}
