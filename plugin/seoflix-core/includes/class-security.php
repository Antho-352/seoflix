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
	}

	private static function build_csp(): string {
		$directives = [
			"default-src 'self'",
			"script-src 'self' 'unsafe-inline'",
			"style-src 'self' 'unsafe-inline'",
			"img-src 'self' data: https://i.ytimg.com https://yt3.googleusercontent.com https://yt3.ggpht.com",
			"font-src 'self' data:",
			"frame-src https://www.youtube-nocookie.com https://www.youtube.com",
			"frame-ancestors 'self'",
			"connect-src 'self'",
			"base-uri 'self'",
			"form-action 'self'",
			"object-src 'none'",
		];
		return implode( '; ', $directives );
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
