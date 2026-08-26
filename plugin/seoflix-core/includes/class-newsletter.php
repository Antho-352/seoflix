<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module newsletter (e-mail uniquement, pas de provider externe en V1).
 *
 * - CPT `seoflix_subscriber` (privé) : un post par inscrit, l'e-mail dans le titre
 * - Endpoint frontend POST /sx-auth/newsletter/ (bypass WPS Hide Login comme l'auth)
 * - Bloc rendu via fonction `seoflix_render_newsletter_form()` utilisée dans la home + footer + form inscription
 * - Notification admin à chaque nouveau subscriber
 * - Page admin WEAS → Newsletter avec liste + export CSV
 *
 * Plus tard : intégration Brevo/Mailerlite via API si besoin.
 */
final class Newsletter {

	public const CPT          = 'seoflix_subscriber';
	public const META_SOURCE  = '_seoflix_subscriber_source';
	public const META_IP_HASH = '_seoflix_subscriber_ip_hash';

	public const SOURCE_HOMEPAGE    = 'homepage';
	public const SOURCE_FOOTER      = 'footer';
	public const SOURCE_REGISTRATION = 'registration';

	public static function init(): void {
		add_action( 'init',                   [ self::class, 'register_cpt' ] );
		add_action( 'admin_post_nopriv_seoflix_newsletter', [ self::class, 'handle_subscribe' ] );
		add_action( 'admin_post_seoflix_newsletter',         [ self::class, 'handle_subscribe' ] );
	}

	public static function register_cpt(): void {
		register_post_type( self::CPT, [
			'labels'              => [
				'name'          => 'Newsletter',
				'singular_name' => 'Inscrit Newsletter',
				'menu_name'     => 'Newsletter',
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'seoflix',
			'show_in_rest'        => false,
			'supports'            => [ 'title', 'custom-fields' ],
			'capabilities'        => [
				'create_posts' => 'do_not_allow', // pas de création manuelle (que via le form)
			],
			'map_meta_cap'        => true,
		] );
	}

	/**
	 * Souscrit un e-mail. Source = origine du clic (homepage/footer/registration).
	 * Idempotent : si l'e-mail existe déjà, ne crée pas de doublon.
	 *
	 * @return true|\WP_Error
	 */
	public static function subscribe( string $email, string $source = self::SOURCE_HOMEPAGE ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', 'E-mail invalide.' );
		}

		// Vérification doublon (titre = email)
		$existing = get_posts( [
			'post_type'      => self::CPT,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'title'          => $email,
		] );
		if ( $existing ) {
			return true; // déjà inscrit, on simule succès
		}

		$ip      = Security::client_ip();
		$post_id = wp_insert_post( [
			'post_type'   => self::CPT,
			'post_title'  => $email,
			'post_status' => 'publish',
			'meta_input'  => [
				self::META_SOURCE  => $source,
				self::META_IP_HASH => $ip ? hash( 'sha256', $ip . wp_salt() ) : '',
			],
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Notification admin (pas d'e-mail à l'inscrit pour l'instant — V1)
		$strip_crlf    = static fn( $v ) => preg_replace( '/[\r\n\t\0]/', '', (string) $v );
		$site_name     = get_bloginfo( 'name' );
		$admin_to      = (string) get_option( Contact::OPTION_RECIPIENT, '' ) ?: get_option( 'admin_email' );
		$admin_subject = sprintf( '[%s] Nouvel inscrit Newsletter : %s', $strip_crlf( $site_name ), $strip_crlf( $email ) );
		$admin_body    = "Nouvel abonné à la newsletter.\n\n"
			. "E-mail : " . $strip_crlf( $email ) . "\n"
			. "Source : " . $strip_crlf( $source ) . "\n"
			. "Date : " . current_time( 'd/m/Y H:i' ) . "\n"
			. "IP : " . $strip_crlf( $ip ) . "\n\n"
			. "Liste : " . admin_url( 'edit.php?post_type=' . self::CPT );
		wp_mail( $admin_to, $admin_subject, $admin_body );

		return true;
	}

	/**
	 * Handler POST /sx-auth/newsletter/ — appelé via Custom_Auth::route_frontend_action.
	 * Ou via admin-post.php?action=seoflix_newsletter en fallback.
	 */
	public static function handle_subscribe(): void {
		$back = isset( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : home_url( '/' );

		if ( ! isset( $_POST['_seoflix_newsletter_nonce'] ) || ! wp_verify_nonce( $_POST['_seoflix_newsletter_nonce'], 'seoflix_newsletter' ) ) {
			wp_safe_redirect( add_query_arg( 'newsletter', 'session_expired', $back ) );
			exit;
		}

		// Honeypot
		if ( ! empty( $_POST['website'] ) ) {
			wp_safe_redirect( add_query_arg( 'newsletter', 'ok', $back ) );
			exit;
		}

		// Rate-limit IP : 5/h
		$ip       = Security::client_ip();
		$rate_key = 'seoflix_news_rate_' . md5( $ip );
		$count    = (int) get_transient( $rate_key );
		if ( $count >= 5 ) {
			wp_safe_redirect( add_query_arg( 'newsletter', 'ok', $back ) ); // simule succès
			exit;
		}
		set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

		$email  = isset( $_POST['email'] )  ? sanitize_email( wp_unslash( $_POST['email'] ) )  : '';
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) )   : self::SOURCE_HOMEPAGE;

		$result = self::subscribe( $email, $source );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'newsletter', 'invalid', $back ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'newsletter', 'ok', $back ) );
		exit;
	}
}
