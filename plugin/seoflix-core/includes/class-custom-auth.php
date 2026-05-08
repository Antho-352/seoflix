<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handlers d'authentification 100 % custom — ne passent JAMAIS par wp-login.php.
 *
 * Pourquoi : si un plugin (WPS Hide Login, Limit Login Attempts) renomme wp-login.php,
 * les forms qui posteraient dessus exposent l'URL secrète. Avec admin-post.php on est
 * sûr que l'URL ne change jamais et que la route admin est inchangée.
 *
 * Endpoints :
 *   - admin-post.php?action=seoflix_register     (handle_register)
 *   - admin-post.php?action=seoflix_login        (handle_login)
 *   - admin-post.php?action=seoflix_lostpass     (handle_lostpass)
 *   - /activer/{token}/                          (handle_activate via rewrite)
 *
 * Flow d'inscription :
 *   1. POST /inscription → admin-post.php?action=seoflix_register
 *   2. Validation + create user (rôle subscriber, meta `pending_activation` = token)
 *   3. Envoi e-mail avec lien /activer/{token}/
 *   4. Redirect → /inscription/?check=email (« vérifie ta boîte »)
 *   5. User clique le lien → /activer/{token}/ → handle_activate → enlève la meta
 *      → connecte automatiquement → redirige /mon-parcours/?welcome=1
 *
 * Flow login :
 *   1. POST /connexion → admin-post.php?action=seoflix_login
 *   2. Validation Turnstile + lock IP + rate-limit
 *   3. Si user a pending_activation → refus avec message + lien renvoi e-mail
 *   4. Sinon wp_signon + redirect /mon-parcours/
 */
final class Custom_Auth {

	public const META_PENDING       = '_seoflix_pending_activation';
	public const META_RESEND_LOCK   = '_seoflix_resend_lock';
	public const QV_ACTIVATE        = 'seoflix_activate';
	public const QV_SETPWD          = 'seoflix_setpwd';
	public const QV_AUTH_ACTION     = 'seoflix_auth_action';

	public static function init(): void {
		if ( ! FeatureFlags::user_accounts_enabled() ) {
			return;
		}

		// Handlers via admin-post.php (ancienne route, gardée pour compat)
		add_action( 'admin_post_nopriv_seoflix_register', [ self::class, 'handle_register' ] );
		add_action( 'admin_post_seoflix_register',         [ self::class, 'handle_register' ] );
		add_action( 'admin_post_nopriv_seoflix_login',     [ self::class, 'handle_login' ] );
		add_action( 'admin_post_seoflix_login',            [ self::class, 'handle_login' ] );
		add_action( 'admin_post_nopriv_seoflix_lostpass',  [ self::class, 'handle_lostpass' ] );
		add_action( 'admin_post_seoflix_lostpass',         [ self::class, 'handle_lostpass' ] );
		add_action( 'admin_post_nopriv_seoflix_resend',    [ self::class, 'handle_resend_activation' ] );
		add_action( 'admin_post_nopriv_seoflix_setpwd',    [ self::class, 'handle_setpwd' ] );

		// NOUVEAU : route frontend /sx-auth/{action}/ qui contourne WPS Hide Login
		// (les plugins type WPS Hide Login interceptent /wp-admin/* y compris admin-post.php)
		add_action( 'parse_request',     [ self::class, 'route_frontend_action' ], 1 );

		// Rewrite /activer/{token}/ + /definir-mot-de-passe/{token}/ + /sx-auth/{action}/
		add_action( 'init',              [ self::class, 'register_rewrites' ] );
		add_filter( 'query_vars',        [ self::class, 'register_query_vars' ] );
		add_action( 'template_redirect', [ self::class, 'handle_activate' ] );
		add_filter( 'template_include',  [ self::class, 'load_setpwd_template' ] );

		// Bloque le login natif WP pour les comptes en pending_activation
		add_filter( 'wp_authenticate_user', [ self::class, 'block_pending_login' ], 10, 2 );
	}

	public static function register_rewrites(): void {
		add_rewrite_rule( '^activer/([a-f0-9]{32,64})/?$',                'index.php?' . self::QV_ACTIVATE . '=$matches[1]', 'top' );
		add_rewrite_rule( '^definir-mot-de-passe/([a-f0-9]{32,64})/?$',   'index.php?' . self::QV_SETPWD . '=$matches[1]',   'top' );
		// Endpoint frontend qui ne passe PAS par /wp-admin/ → bypass WPS Hide Login
		add_rewrite_rule( '^sx-auth/([a-z_]+)/?$',                        'index.php?' . self::QV_AUTH_ACTION . '=$matches[1]', 'top' );
	}

	public static function register_query_vars( array $vars ): array {
		$vars[] = self::QV_ACTIVATE;
		$vars[] = self::QV_SETPWD;
		$vars[] = self::QV_AUTH_ACTION;
		return $vars;
	}

	/**
	 * Route les requêtes /sx-auth/{action}/ vers les bons handlers.
	 * Tourne sur parse_request priorité 1 (avant que WP fasse le main query).
	 */
	public static function route_frontend_action( \WP $wp ): void {
		$action = $wp->query_vars[ self::QV_AUTH_ACTION ] ?? '';
		if ( ! $action || $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
			return;
		}
		$handlers = [
			'register'   => [ self::class, 'handle_register' ],
			'login'      => [ self::class, 'handle_login' ],
			'lostpass'   => [ self::class, 'handle_lostpass' ],
			'resend'     => [ self::class, 'handle_resend_activation' ],
			'setpwd'     => [ self::class, 'handle_setpwd' ],
			'newsletter' => [ Newsletter::class, 'handle_subscribe' ],
		];
		if ( isset( $handlers[ $action ] ) ) {
			call_user_func( $handlers[ $action ] );
			exit;
		}
	}

	public static function frontend_action_url( string $action ): string {
		return home_url( '/sx-auth/' . $action . '/' );
	}

	public static function load_setpwd_template( $template ) {
		if ( get_query_var( self::QV_SETPWD ) ) {
			$t = locate_template( 'page-definir-mot-de-passe.php' );
			if ( $t ) {
				return $t;
			}
		}
		return $template;
	}

	public static function handle_setpwd(): void {
		$back_token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$back       = $back_token ? home_url( '/definir-mot-de-passe/' . $back_token . '/' ) : home_url( '/connexion/' );

		if ( ! isset( $_POST['_seoflix_setpwd_nonce'] ) || ! wp_verify_nonce( $_POST['_seoflix_setpwd_nonce'], 'seoflix_setpwd' ) ) {
			self::redirect_error( $back, 'session_expired', 'setpwd' );
		}

		if ( ! preg_match( '/^[a-f0-9]{32,64}$/', $back_token ) ) {
			self::redirect_error( home_url( '/connexion/' ), 'invalid', 'activated' );
		}

		$users = get_users( [
			'meta_key'    => '_seoflix_setpwd_token',
			'meta_value'  => $back_token,
			'number'      => 1,
			'fields'      => [ 'ID' ],
		] );
		if ( ! $users ) {
			self::redirect_error( home_url( '/connexion/' ), 'invalid', 'activated' );
		}
		$user_id = (int) $users[0]->ID;

		$expires = (int) get_user_meta( $user_id, '_seoflix_setpwd_expires', true );
		if ( $expires && time() > $expires ) {
			delete_user_meta( $user_id, '_seoflix_setpwd_token' );
			self::redirect_error( home_url( '/connexion/' ), 'expired', 'activated' );
		}

		$pwd  = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';
		$pwd2 = isset( $_POST['pwd2'] ) ? (string) wp_unslash( $_POST['pwd2'] ) : '';

		if ( strlen( $pwd ) < 12 ) {
			self::redirect_error( $back, 'too_short', 'setpwd' );
		}
		if ( $pwd !== $pwd2 ) {
			self::redirect_error( $back, 'mismatch', 'setpwd' );
		}

		wp_set_password( $pwd, $user_id );
		delete_user_meta( $user_id, '_seoflix_setpwd_token' );
		delete_user_meta( $user_id, '_seoflix_setpwd_expires' );

		// Auto-login
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );

		wp_safe_redirect( home_url( '/mon-parcours/?welcome=1' ) );
		exit;
	}

	/* ======================================================================
	 *  Inscription
	 * ====================================================================== */

	public static function handle_register(): void {
		$back = home_url( '/inscription/' );

		if ( ! isset( $_POST['_seoflix_register_nonce'] ) || ! wp_verify_nonce( $_POST['_seoflix_register_nonce'], 'seoflix_register' ) ) {
			self::redirect_error( $back, 'session_expired' );
		}

		// Honeypot champ caché
		if ( ! empty( $_POST['website'] ) ) {
			// On simule le succès — pas la peine d'avertir le bot
			wp_safe_redirect( add_query_arg( 'check', 'email', $back ) );
			exit;
		}

		// Honeypot temporel
		$ts = isset( $_POST['_t'] ) ? (int) $_POST['_t'] : 0;
		if ( $ts && ( time() - $ts ) < 3 ) {
			wp_safe_redirect( add_query_arg( 'check', 'email', $back ) );
			exit;
		}

		$ip          = Security::client_ip();
		$rate_key    = 'seoflix_register_rate_' . md5( $ip );
		$rate_count  = (int) get_transient( $rate_key );
		if ( $rate_count >= 3 ) {
			self::redirect_error( $back, 'rate_limit' );
		}

		// Validation des champs
		$user_login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ), true ) : '';
		$user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		$rgpd_ok    = ! empty( $_POST['rgpd_ok'] );

		if ( ! $user_login || strlen( $user_login ) < 3 || strlen( $user_login ) > 30 ) {
			self::redirect_error( $back, 'invalid_username' );
		}
		if ( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $user_login ) ) {
			self::redirect_error( $back, 'invalid_username' );
		}
		if ( ! is_email( $user_email ) ) {
			self::redirect_error( $back, 'invalid_email' );
		}
		if ( ! $rgpd_ok ) {
			self::redirect_error( $back, 'rgpd_required' );
		}
		// Anti-énumération : message générique si username OU email déjà pris
		if ( username_exists( $user_login ) || email_exists( $user_email ) ) {
			self::redirect_error( $back, 'taken' );
		}

		// Validation Turnstile
		$turnstile_secret = (string) get_option( Contact::OPTION_TURNSTILE_SECRET, '' );
		if ( $turnstile_secret ) {
			$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
			if ( ! $token || ! self::verify_turnstile( $token, $turnstile_secret, $ip ) ) {
				self::redirect_error( $back, 'turnstile' );
			}
		}

		// Mot de passe choisi par l'utilisateur sur le form
		$password         = isset( $_POST['pwd'] )  ? (string) wp_unslash( $_POST['pwd'] )  : '';
		$password_confirm = isset( $_POST['pwd2'] ) ? (string) wp_unslash( $_POST['pwd2'] ) : '';
		if ( strlen( $password ) < 12 ) {
			self::redirect_error( $back, 'pwd_too_short' );
		}
		if ( $password !== $password_confirm ) {
			self::redirect_error( $back, 'pwd_mismatch' );
		}

		// Désactive l'envoi automatique des e-mails WP (on envoie nos propres custom)
		add_filter( 'wp_new_user_notification_email_admin', '__return_false', 99 );
		add_filter( 'wp_new_user_notification_email',       '__return_false', 99 );
		add_filter( 'send_password_change_email',           '__return_false', 99 );
		add_filter( 'send_email_change_email',              '__return_false', 99 );

		// Création du compte avec le mot de passe choisi, MAIS marqué pending_activation
		$user_id = wp_create_user( $user_login, $password, $user_email );
		if ( is_wp_error( $user_id ) ) {
			self::redirect_error( $back, 'create_failed' );
		}

		$user = new \WP_User( $user_id );
		$user->set_role( User_Accounts::ROLE );

		// Token d'activation : 7 jours pour valider l'e-mail
		$activation_token = bin2hex( random_bytes( 32 ) );
		update_user_meta( $user_id, self::META_PENDING, $activation_token );
		update_user_meta( $user_id, self::META_PENDING . '_expires', time() + ( 7 * DAY_IN_SECONDS ) );

		// Opt-in newsletter (case cochée sur le form)
		if ( ! empty( $_POST['newsletter_optin'] ) ) {
			Newsletter::subscribe( $user_email, Newsletter::SOURCE_REGISTRATION );
		}

		set_transient( $rate_key, $rate_count + 1, HOUR_IN_SECONDS );

		// Redirige vers la page "vérifie ta boîte mail"
		wp_safe_redirect( home_url( '/inscription/?check=email' ) );

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}

		// E-mail d'activation (en background)
		$activate_url = home_url( '/activer/' . $activation_token . '/' );
		$site_name    = get_bloginfo( 'name' );
		$user_subject = sprintf( '[%s] Confirme ton inscription', $site_name );
		$user_body    = "Salut,\n\n"
			. "Merci de t'être inscrit sur {$site_name}.\n\n"
			. "Pour activer ton compte et pouvoir te connecter, clique sur le lien ci-dessous (valable 7 jours) :\n\n"
			. $activate_url . "\n\n"
			. "Si tu n'es pas à l'origine de cette inscription, ignore simplement cet e-mail — ton compte ne sera pas activé.\n\n"
			. "À bientôt,\n"
			. $site_name . "\n"
			. home_url();
		wp_mail( $user_email, $user_subject, $user_body );

		// Notification admin
		$strip_crlf    = static fn( $v ) => preg_replace( '/[\r\n\t\0]/', '', (string) $v );
		$admin_to      = (string) get_option( Contact::OPTION_RECIPIENT, '' ) ?: get_option( 'admin_email' );
		$admin_subject = sprintf( '[%s] Nouvelle inscription : %s', $strip_crlf( $site_name ), $strip_crlf( $user_email ) );
		$admin_body    = "Nouvel utilisateur inscrit sur " . $strip_crlf( $site_name ) . ".\n\n"
			. "Login : " . $strip_crlf( $user_login ) . "\n"
			. "E-mail : " . $strip_crlf( $user_email ) . "\n"
			. "IP : " . $strip_crlf( $ip ) . "\n"
			. "Statut : en attente d'activation par e-mail";
		wp_mail( $admin_to, $admin_subject, $admin_body );

		// Notification admin (anti email-header injection : strip CRLF dans tout user input)
		$strip_crlf    = static fn( $v ) => preg_replace( '/[\r\n\t\0]/', '', (string) $v );
		$admin_to      = (string) get_option( Contact::OPTION_RECIPIENT, '' ) ?: get_option( 'admin_email' );
		$admin_subject = sprintf( '[%s] Nouvelle inscription : %s', $strip_crlf( $site_name ), $strip_crlf( $user_email ) );
		$admin_body    = "Nouvel utilisateur inscrit sur " . $strip_crlf( $site_name ) . ".\n\n"
			. "Login : " . $strip_crlf( $user_login ) . "\n"
			. "E-mail : " . $strip_crlf( $user_email ) . "\n"
			. "Date : " . current_time( 'd/m/Y H:i' ) . "\n"
			. "IP : " . $strip_crlf( $ip ) . "\n"
			. "Statut : en attente d'activation par e-mail";
		wp_mail( $admin_to, $admin_subject, $admin_body );

		exit;
	}

	/* ======================================================================
	 *  Activation
	 * ====================================================================== */

	public static function handle_activate(): void {
		$token = (string) get_query_var( self::QV_ACTIVATE );
		if ( ! $token ) {
			return;
		}

		$users = get_users( [
			'meta_key'    => self::META_PENDING,
			'meta_value'  => $token,
			'number'      => 1,
			'fields'      => [ 'ID' ],
		] );

		if ( ! $users ) {
			wp_safe_redirect( home_url( '/connexion/?activated=invalid' ) );
			exit;
		}

		$user_id = (int) $users[0]->ID;
		$expires = (int) get_user_meta( $user_id, self::META_PENDING . '_expires', true );
		if ( $expires && time() > $expires ) {
			wp_safe_redirect( home_url( '/connexion/?activated=expired' ) );
			exit;
		}

		// Activation : suppression de la meta pending → user peut maintenant se connecter
		delete_user_meta( $user_id, self::META_PENDING );
		delete_user_meta( $user_id, self::META_PENDING . '_expires' );

		// Auto-login (le user a déjà set son password à l'inscription)
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );

		wp_safe_redirect( home_url( '/mon-parcours/?welcome=1' ) );
		exit;
	}

	/* ======================================================================
	 *  Connexion
	 * ====================================================================== */

	public static function handle_login(): void {
		$back = home_url( '/connexion/' );

		if ( ! isset( $_POST['_seoflix_login_nonce'] ) || ! wp_verify_nonce( $_POST['_seoflix_login_nonce'], 'seoflix_login' ) ) {
			self::redirect_error( $back, 'session_expired', 'login' );
		}

		$ip = Security::client_ip();

		// Vérifie le lock IP (5 échecs/15min → 30min de lock)
		if ( get_transient( Security::LOGIN_LOCK_PREFIX . md5( $ip ) ) ) {
			self::redirect_error( $back, 'seoflix_locked', 'login' );
		}

		// Turnstile
		$turnstile_secret = (string) get_option( Contact::OPTION_TURNSTILE_SECRET, '' );
		if ( $turnstile_secret ) {
			$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
			if ( ! $token || ! self::verify_turnstile( $token, $turnstile_secret, $ip ) ) {
				self::redirect_error( $back, 'turnstile', 'login' );
			}
		}

		$user_login    = isset( $_POST['log'] ) ? trim( wp_unslash( $_POST['log'] ) ) : '';
		$user_password = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';
		$remember      = ! empty( $_POST['rememberme'] );

		if ( ! $user_login || ! $user_password ) {
			self::redirect_error( $back, 'empty_fields', 'login' );
		}

		// Si log = e-mail, résout le user_login
		if ( is_email( $user_login ) ) {
			$u = get_user_by( 'email', $user_login );
			if ( $u ) {
				$user_login = $u->user_login;
			}
		}

		// Tentative de signon
		$user = wp_signon( [
			'user_login'    => $user_login,
			'user_password' => $user_password,
			'remember'      => $remember,
		], is_ssl() );

		if ( is_wp_error( $user ) ) {
			// Incrémente compteur d'échecs (Security gère le lock)
			Security::on_login_failed( $user_login );
			self::redirect_error( $back, 'failed', 'login' );
		}

		// Reset compteur d'échecs au succès
		Security::on_login_success();

		// Redirect : admin → wp-admin / éditeur → wp-admin / autres → /mon-parcours
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
		if ( ! $redirect_to || strpos( $redirect_to, home_url() ) !== 0 ) {
			$redirect_to = user_can( $user, 'edit_posts' ) ? admin_url() : home_url( '/mon-parcours/' );
		}
		wp_safe_redirect( $redirect_to );
		exit;
	}

	public static function block_pending_login( $user, string $password ) {
		if ( is_wp_error( $user ) || ! ( $user instanceof \WP_User ) ) {
			return $user;
		}
		$pending = get_user_meta( $user->ID, self::META_PENDING, true );
		if ( $pending ) {
			return new \WP_Error(
				'seoflix_pending',
				'Compte non activé. Vérifie ta boîte mail (et tes spams) — un e-mail de confirmation t\'a été envoyé.'
			);
		}
		return $user;
	}

	/* ======================================================================
	 *  Mot de passe oublié
	 * ====================================================================== */

	public static function handle_lostpass(): void {
		$back = home_url( '/mot-de-passe-oublie/' );

		if ( ! isset( $_POST['_seoflix_lostpass_nonce'] ) || ! wp_verify_nonce( $_POST['_seoflix_lostpass_nonce'], 'seoflix_lostpass' ) ) {
			self::redirect_error( $back, 'session_expired', 'login' );
		}

		$user_login = isset( $_POST['user_login'] ) ? trim( wp_unslash( $_POST['user_login'] ) ) : '';
		if ( ! $user_login ) {
			self::redirect_error( $back, 'empty_username', 'login' );
		}

		// On utilise retrieve_password() de WP — ça envoie un e-mail avec un lien
		// vers wp-login.php?action=rp ce qui POSE PROBLÈME avec WPS Hide Login.
		// Donc on génère notre propre token + on envoie notre propre mail.
		$user = is_email( $user_login ) ? get_user_by( 'email', $user_login ) : get_user_by( 'login', $user_login );

		// On simule TOUJOURS un succès (anti enum)
		$success_url = add_query_arg( 'checkemail', '1', $back );

		if ( $user instanceof \WP_User ) {
			$reset_token = bin2hex( random_bytes( 32 ) );
			update_user_meta( $user->ID, '_seoflix_setpwd_token', $reset_token );
			update_user_meta( $user->ID, '_seoflix_setpwd_expires', time() + HOUR_IN_SECONDS );

			$reset_url = home_url( '/definir-mot-de-passe/' . $reset_token . '/' );
			$site_name = get_bloginfo( 'name' );
			$body = "Salut,\n\n"
				. "Tu as demandé à réinitialiser ton mot de passe sur {$site_name}.\n\n"
				. "Clique sur le lien suivant (valable 1 heure) :\n\n"
				. $reset_url . "\n\n"
				. "Si tu n'es pas à l'origine de cette demande, ignore cet e-mail.\n\n"
				. $site_name;

			wp_safe_redirect( $success_url );
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			}
			wp_mail( $user->user_email, sprintf( '[%s] Réinitialisation de mot de passe', $site_name ), $body );
			exit;
		}

		// User inconnu : on simule quand même le succès
		wp_safe_redirect( $success_url );
		exit;
	}

	public static function handle_resend_activation(): void {
		$back        = home_url( '/connexion/' );
		$success_url = add_query_arg( 'check', 'email', home_url( '/inscription/' ) );

		// CSRF
		if ( ! isset( $_POST['_seoflix_resend_nonce'] ) || ! wp_verify_nonce( $_POST['_seoflix_resend_nonce'], 'seoflix_resend' ) ) {
			self::redirect_error( $back, 'session_expired', 'login' );
		}

		// Rate-limit IP : 5 renvois / heure / IP, pour bloquer un attaquant
		// qui parcourrait une liste d'e-mails pour spam-bomber les utilisateurs
		$ip          = Security::client_ip();
		$ip_rate_key = 'seoflix_resend_ip_' . md5( $ip );
		$ip_count    = (int) get_transient( $ip_rate_key );
		if ( $ip_count >= 5 ) {
			// On simule le succès (pas la peine d'avertir l'attaquant)
			wp_safe_redirect( $success_url );
			exit;
		}
		set_transient( $ip_rate_key, $ip_count + 1, HOUR_IN_SECONDS );

		$email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			// Toujours simule succès (anti enum)
			wp_safe_redirect( $success_url );
			exit;
		}

		$user = get_user_by( 'email', $email );

		if ( $user instanceof \WP_User ) {
			$pending = get_user_meta( $user->ID, self::META_PENDING, true );
			if ( $pending ) {
				$resend_lock = (int) get_user_meta( $user->ID, self::META_RESEND_LOCK, true );
				if ( $resend_lock && time() < $resend_lock ) {
					wp_safe_redirect( $success_url );
					exit;
				}
				update_user_meta( $user->ID, self::META_RESEND_LOCK, time() + ( 5 * MINUTE_IN_SECONDS ) );

				$activate_url = home_url( '/activer/' . $pending . '/' );
				$site_name    = get_bloginfo( 'name' );
				$body = "Lien d'activation pour ton compte sur {$site_name} :\n\n" . $activate_url;
				wp_safe_redirect( $success_url );
				if ( function_exists( 'fastcgi_finish_request' ) ) {
					fastcgi_finish_request();
				}
				wp_mail( $user->user_email, sprintf( '[%s] Lien d\'activation', $site_name ), $body );
				exit;
			}
		}
		wp_safe_redirect( $success_url );
		exit;
	}

	/* ======================================================================
	 *  Helpers
	 * ====================================================================== */

	private static function redirect_error( string $url, string $code, string $param = 'registration' ): void {
		wp_safe_redirect( add_query_arg( $param, $code, $url ) );
		exit;
	}

	/**
	 * Vérifie un token Turnstile côté serveur. Fail-closed : si l'API Cloudflare est
	 * inaccessible, on refuse la soumission (better safe than sorry).
	 */
	private static function verify_turnstile( string $token, string $secret, string $ip ): bool {
		$response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
			'timeout' => 8,
			'body'    => [ 'secret' => $secret, 'response' => $token, 'remoteip' => $ip ],
		] );
		if ( is_wp_error( $response ) ) {
			error_log( 'Seoflix Turnstile: HTTP error - ' . $response->get_error_message() );
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			error_log( 'Seoflix Turnstile: HTTP ' . $code );
			return false;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			error_log( 'Seoflix Turnstile: invalid JSON response' );
			return false;
		}
		return ! empty( $body['success'] );
	}
}
