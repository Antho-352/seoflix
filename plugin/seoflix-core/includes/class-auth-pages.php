<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pages d'authentification custom (V2).
 *
 * Substitue à /wp-login.php des pages thématisées :
 *   - /connexion/                → page-connexion.php
 *   - /inscription/              → page-inscription.php
 *   - /mot-de-passe-oublie/      → page-mot-de-passe-oublie.php
 *
 * Note : wp-login.php reste fonctionnel pour les admins (pas de blocage).
 * Mais wp_login_url() / wp_registration_url() / wp_lostpassword_url() pointent
 * vers les pages custom pour tout le code WP qui les utilise (theme, plugins).
 */
final class Auth_Pages {

	public const QV_LOGIN     = 'seoflix_login';
	public const QV_REGISTER  = 'seoflix_register';
	public const QV_LOSTPASS  = 'seoflix_lostpass';

	public static function init(): void {
		if ( ! FeatureFlags::user_accounts_enabled() ) {
			return;
		}

		add_action( 'init',           [ self::class, 'register_rewrites' ] );
		add_filter( 'query_vars',     [ self::class, 'register_query_vars' ] );
		add_filter( 'template_include', [ self::class, 'load_template' ] );

		// Override les URLs de login/register/lostpass dans tout WP
		add_filter( 'login_url',         [ self::class, 'override_login_url' ], 10, 3 );
		add_filter( 'register_url',      [ self::class, 'override_register_url' ] );
		add_filter( 'lostpassword_url',  [ self::class, 'override_lostpass_url' ], 10, 2 );

		// Empêche les utilisateurs déjà connectés d'aller sur /connexion ou /inscription
		add_action( 'template_redirect', [ self::class, 'redirect_authenticated' ] );

		// Redirect après login pour les non-admins → /mon-parcours
		add_filter( 'login_redirect',    [ self::class, 'login_redirect' ], 10, 3 );

		// Après inscription, redirige vers /connexion/?registered=1 au lieu de wp-login.php
		add_filter( 'registration_redirect', [ self::class, 'register_redirect' ] );
	}

	public static function register_rewrites(): void {
		add_rewrite_rule( '^connexion/?$',                'index.php?' . self::QV_LOGIN . '=1',     'top' );
		add_rewrite_rule( '^inscription/?$',              'index.php?' . self::QV_REGISTER . '=1',  'top' );
		add_rewrite_rule( '^mot-de-passe-oublie/?$',      'index.php?' . self::QV_LOSTPASS . '=1',  'top' );
	}

	public static function register_query_vars( array $vars ): array {
		$vars[] = self::QV_LOGIN;
		$vars[] = self::QV_REGISTER;
		$vars[] = self::QV_LOSTPASS;
		return $vars;
	}

	public static function load_template( $template ) {
		if ( get_query_var( self::QV_LOGIN ) ) {
			$t = locate_template( 'page-connexion.php' );
			if ( $t ) {
				return $t;
			}
		}
		if ( get_query_var( self::QV_REGISTER ) ) {
			$t = locate_template( 'page-inscription.php' );
			if ( $t ) {
				return $t;
			}
		}
		if ( get_query_var( self::QV_LOSTPASS ) ) {
			$t = locate_template( 'page-mot-de-passe-oublie.php' );
			if ( $t ) {
				return $t;
			}
		}
		return $template;
	}

	public static function override_login_url( string $login_url, string $redirect = '', bool $force_reauth = false ): string {
		// Pour les pages d'admin (force_reauth) on garde wp-login.php (cas extrême)
		if ( $force_reauth ) {
			return $login_url;
		}
		$url = home_url( '/connexion/' );
		if ( $redirect ) {
			$url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
		}
		return $url;
	}

	public static function override_register_url( string $register_url ): string {
		return home_url( '/inscription/' );
	}

	public static function override_lostpass_url( string $lostpass_url, string $redirect = '' ): string {
		$url = home_url( '/mot-de-passe-oublie/' );
		if ( $redirect ) {
			$url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
		}
		return $url;
	}

	public static function redirect_authenticated(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		if ( get_query_var( self::QV_LOGIN ) || get_query_var( self::QV_REGISTER ) ) {
			wp_safe_redirect( home_url( '/mon-parcours/' ) );
			exit;
		}
	}

	public static function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( is_wp_error( $user ) || ! ( $user instanceof \WP_User ) ) {
			return $redirect_to;
		}
		// Si admin/éditeur, garde le redirect par défaut (wp-admin)
		if ( user_can( $user, 'edit_posts' ) ) {
			return $redirect_to;
		}
		// Si redirect explicite demandé (ex: depuis une page vidéo), respecte-le
		if ( $requested_redirect_to && strpos( $requested_redirect_to, home_url() ) === 0 ) {
			return $requested_redirect_to;
		}
		return home_url( '/mon-parcours/' );
	}

	public static function register_redirect( $redirect ) {
		return home_url( '/connexion/?registered=1' );
	}

	/**
	 * URL de soumission des formulaires custom (toujours wp-login.php).
	 */
	public static function login_post_url(): string {
		return site_url( 'wp-login.php', 'login_post' );
	}
}
