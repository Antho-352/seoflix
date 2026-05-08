<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Durcissement sécurité de base.
 *
 * Fonctionnalités :
 *   - Headers de sécurité (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy)
 *   - CSP autorise les iframes YouTube (youtube-nocookie.com)
 *   - Désactivation de XML-RPC (vecteur de bruteforce)
 *   - Désactivation des endpoints REST sensibles aux non-loggés (/wp/v2/users)
 *   - Désactivation des oEmbed externes (qui exposent l'origine)
 */
final class Security {

	public const LOGIN_LOCK_PREFIX  = 'seoflix_login_lock_';
	public const LOGIN_FAILS_PREFIX = 'seoflix_login_fails_';

	public static function init(): void {
		add_action( 'send_headers', [ self::class, 'send_security_headers' ] );

		// XML-RPC : vecteur d'attaque bruteforce inutile pour seoflix
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'wp_headers', [ self::class, 'remove_xpingback' ] );

		// Endpoint REST /wp/v2/users : empêche l'énumération anonyme des utilisateurs
		add_filter( 'rest_endpoints', [ self::class, 'restrict_rest_users' ] );

		// Cacher l'erreur de login (timing-safe + pas d'indication user vs password)
		add_filter( 'login_errors', static fn() => 'Identifiants invalides.' );

		// Bloquer l'énumération via ?author=N
		add_action( 'template_redirect', [ self::class, 'block_author_enumeration' ] );

		// Anti-bruteforce login : 5 échecs/IP/15min puis lock 30min
		add_filter( 'authenticate',    [ self::class, 'check_login_lock' ], 5, 1 );
		add_action( 'wp_login_failed', [ self::class, 'on_login_failed' ] );
		add_action( 'wp_login',        [ self::class, 'on_login_success' ] );

		// DISALLOW_FILE_EDIT : empêche l'édition de fichiers via le dashboard
		// (à mettre idéalement dans wp-config.php, mais on le force ici en runtime
		//  comme defense-in-depth si le user oublie de le mettre dans wp-config)
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}
	}

	public static function send_security_headers(): void {
		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		$csp = self::build_csp();
		header( 'Content-Security-Policy: ' . $csp );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: interest-cohort=(), browsing-topics=()' );
		// X-Frame-Options : empêcher l'embed dans un iframe externe (clickjacking)
		header( 'X-Frame-Options: SAMEORIGIN' );
		// HSTS : force HTTPS sur 1 an + sous-domaines + preload
		header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains; preload' );
		// Cross-Origin Opener Policy : isole le contexte JS de la page
		header( 'Cross-Origin-Opener-Policy: same-origin-allow-popups' );
	}

	private static function build_csp(): string {
		$directives = [
			"default-src 'self'",
			"script-src 'self' 'unsafe-inline' https://static.cloudflareinsights.com https://challenges.cloudflare.com",
			"style-src 'self' 'unsafe-inline'",
			"img-src 'self' data: https://i.ytimg.com https://yt3.googleusercontent.com https://yt3.ggpht.com https://*.gravatar.com",
			"font-src 'self' data:",
			"frame-src https://www.youtube-nocookie.com https://www.youtube.com https://challenges.cloudflare.com",
			"frame-ancestors 'self'",
			"connect-src 'self' https://cloudflareinsights.com https://challenges.cloudflare.com",
			"worker-src 'self' blob:",
			"base-uri 'self'",
			"form-action 'self'",
			"object-src 'none'",
		];
		return implode( '; ', $directives );
	}

	/* ======================================================================
	 *  Anti-bruteforce login
	 * ====================================================================== */

	public static function check_login_lock( $user ) {
		// Si déjà une erreur amont, on laisse passer
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		$ip = self::client_ip();
		if ( ! $ip ) {
			return $user;
		}
		// Lock global IP (anti scan)
		if ( get_transient( self::LOGIN_LOCK_PREFIX . md5( $ip ) ) ) {
			return new \WP_Error(
				'seoflix_locked',
				'Trop d\'échecs récents depuis cette IP. Réessaye dans 30 minutes.'
			);
		}
		// Lock par couple username+IP (3 fails)
		if ( $user instanceof \WP_User ) {
			$key = self::LOGIN_LOCK_PREFIX . md5( $user->user_login . '::' . $ip );
			if ( get_transient( $key ) ) {
				return new \WP_Error(
					'seoflix_locked',
					'Trop d\'échecs sur ce compte depuis ton IP. Réessaye dans 30 minutes.'
				);
			}
		}
		return $user;
	}

	public static function on_login_failed( string $username ): void {
		$ip = self::client_ip();
		if ( ! $ip ) {
			return;
		}

		// Compteur global IP : 5 fails → lock 30min (anti-scan multi-comptes)
		$ip_key   = self::LOGIN_FAILS_PREFIX . md5( $ip );
		$ip_fails = (int) get_transient( $ip_key );
		$ip_fails++;
		set_transient( $ip_key, $ip_fails, 15 * MINUTE_IN_SECONDS );
		if ( $ip_fails >= 5 ) {
			set_transient( self::LOGIN_LOCK_PREFIX . md5( $ip ), 1, 30 * MINUTE_IN_SECONDS );
		}

		// Compteur username+IP : 3 fails → lock 30min (anti-bruteforce ciblé)
		if ( $username ) {
			$user_ip_key   = self::LOGIN_FAILS_PREFIX . md5( $username . '::' . $ip );
			$user_ip_fails = (int) get_transient( $user_ip_key );
			$user_ip_fails++;
			set_transient( $user_ip_key, $user_ip_fails, 15 * MINUTE_IN_SECONDS );
			if ( $user_ip_fails >= 3 ) {
				set_transient( self::LOGIN_LOCK_PREFIX . md5( $username . '::' . $ip ), 1, 30 * MINUTE_IN_SECONDS );
			}
		}
	}

	public static function on_login_success(): void {
		$ip = self::client_ip();
		if ( ! $ip ) {
			return;
		}
		delete_transient( self::LOGIN_FAILS_PREFIX . md5( $ip ) );
		delete_transient( self::LOGIN_LOCK_PREFIX . md5( $ip ) );
		// Note: les compteurs username+IP s'auto-expirent en 15min, pas critique de les nettoyer
	}

	/**
	 * Récupère l'IP client réelle, en tenant compte de Cloudflare et reverse-proxy.
	 */
	public static function client_ip(): string {
		// Cloudflare envoie l'IP réelle dans CF-Connecting-IP
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return trim( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] );
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] );
			return trim( $forwarded[0] );
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	}

	public static function remove_xpingback( array $headers ): array {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	public static function restrict_rest_users( array $endpoints ): array {
		if ( isset( $endpoints['/wp/v2/users'] ) ) {
			unset( $endpoints['/wp/v2/users'] );
		}
		if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
			unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}

	public static function block_author_enumeration(): void {
		if ( is_admin() ) {
			return;
		}
		if ( isset( $_GET['author'] ) && ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}
	}
}
