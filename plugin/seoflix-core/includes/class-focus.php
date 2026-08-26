<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Préférence fonctionnelle FOCUS appliquée exclusivement aux requêtes vidéo.
 *
 * Les requêtes secondaires restent intactes par défaut. Elles doivent définir
 * explicitement `seoflix_focus_apply` à `1` pour accepter le filtrage FOCUS.
 */
final class Focus {

	public const META_KEY         = '_seoflix_focus_path';
	public const COOKIE_NAME      = 'seoflix_focus_path';
	public const NONCE_ACTION     = 'seoflix_focus_update';
	public const NONCE_FIELD      = 'seoflix_focus_nonce';
	public const QUERY_VAR_APPLY  = 'seoflix_focus_apply';
	public const QUERY_VAR_BYPASS = 'seoflix_focus_bypass';
	public const QUERY_VAR_ACTIVE = 'seoflix_focus_active';
	public const COOKIE_TTL       = 7776000; // 90 jours.
	public const PATH_SLUGS       = [
		'apprendre-l-affiliation',
		'apprendre-youtube',
		'apprendre-la-vente-de-liens',
		'apprendre-ia-automatisation',
		'apprendre-la-vente-de-leads',
		'apprendre-le-freelancing',
	];

	/** @var array<string, \WP_Term|null> */
	private static array $validated_terms = [];

	public static function init(): void {
		add_action( 'admin_post_seoflix_focus_set', [ self::class, 'handle_set' ] );
		add_action( 'admin_post_nopriv_seoflix_focus_set', [ self::class, 'handle_set' ] );
		add_action( 'admin_post_seoflix_focus_reset', [ self::class, 'handle_reset' ] );
		add_action( 'admin_post_nopriv_seoflix_focus_reset', [ self::class, 'handle_reset' ] );
		add_action( 'pre_get_posts', [ self::class, 'filter_video_query' ], 20 );
		add_action( 'template_redirect', [ self::class, 'prevent_personalized_cache' ], 0 );
		add_action( 'wp_login', [ self::class, 'promote_cookie_on_login' ], 10, 2 );
		add_filter( 'query_vars', [ self::class, 'register_query_vars' ] );
	}

	/**
	 * @param array<int, string> $query_vars Variables publiques WordPress.
	 * @return array<int, string>
	 */
	public static function register_query_vars( array $query_vars ): array {
		$query_vars[] = self::QUERY_VAR_APPLY;
		$query_vars[] = self::QUERY_VAR_BYPASS;
		return $query_vars;
	}

	public static function handle_set(): void {
		self::verify_request_nonce();

		if ( ! isset( $_POST['seoflix_focus_path'] ) || ! is_string( $_POST['seoflix_focus_path'] ) ) {
			self::redirect_after_action( false, 'invalid' );
		}

		$slug = sanitize_title( wp_unslash( $_POST['seoflix_focus_path'] ) );
		$term = self::valid_term_for_slug( $slug );
		if ( ! $term ) {
			self::redirect_after_action( false, 'invalid' );
		}

		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), self::META_KEY, $term->slug );
			self::clear_cookie();
		} else {
			self::write_cookie( $term->slug );
		}

		$destination = isset( $_POST['seoflix_focus_destination'] ) && is_string( $_POST['seoflix_focus_destination'] )
			? sanitize_key( wp_unslash( $_POST['seoflix_focus_destination'] ) )
			: '';
		self::redirect_after_action( false, '', 'path' === $destination ? $term : null );
	}

	public static function handle_reset(): void {
		self::verify_request_nonce();

		if ( is_user_logged_in() ) {
			delete_user_meta( get_current_user_id(), self::META_KEY );
		}
		self::clear_cookie();
		self::redirect_after_action( true );
	}

	private static function verify_request_nonce(): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! is_string( $_POST[ self::NONCE_FIELD ] ) ) {
			wp_die( esc_html__( 'Requête FOCUS invalide.', 'seoflix' ), '', [ 'response' => 403 ] );
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'La session FOCUS a expiré.', 'seoflix' ), '', [ 'response' => 403 ] );
		}
	}

	private static function redirect_after_action( bool $bypass, string $status = '', ?\WP_Term $destination = null ): void {
		$url = self::same_site_return_url();
		if ( $destination ) {
			$term_url = get_term_link( $destination );
			if ( is_string( $term_url ) && '' !== $term_url ) {
				$url = $term_url;
			}
		}
		$url = remove_query_arg(
			[ 'action', self::NONCE_FIELD, self::QUERY_VAR_APPLY, self::QUERY_VAR_BYPASS, 'seoflix_focus_status' ],
			$url
		);
		if ( $bypass ) {
			$url = add_query_arg( self::QUERY_VAR_BYPASS, '1', $url );
		} elseif ( 'invalid' === $status ) {
			$url = add_query_arg( 'seoflix_focus_status', 'invalid', $url );
		}

		wp_safe_redirect( $url, 303, 'WEAS FOCUS' );
		exit;
	}

	private static function same_site_return_url(): string {
		$fallback = get_post_type_archive_link( CPT::VIDEO );
		if ( ! is_string( $fallback ) || '' === $fallback ) {
			$fallback = home_url( '/videos/' );
		}

		$referrer = wp_get_referer();
		if ( ! is_string( $referrer ) || '' === $referrer ) {
			return $fallback;
		}
		if ( str_starts_with( $referrer, '/' ) && ! str_starts_with( $referrer, '//' ) ) {
			$referrer = home_url( $referrer );
		}

		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$home_port = (int) wp_parse_url( home_url( '/' ), PHP_URL_PORT );
		$ref_host  = strtolower( (string) wp_parse_url( $referrer, PHP_URL_HOST ) );
		$ref_port  = (int) wp_parse_url( $referrer, PHP_URL_PORT );
		$scheme    = strtolower( (string) wp_parse_url( $referrer, PHP_URL_SCHEME ) );

		if ( '' === $home_host || $home_host !== $ref_host || $home_port !== $ref_port || ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return $fallback;
		}

		return wp_validate_redirect( $referrer, $fallback );
	}

	private static function cookie_options( int $expires ): array {
		return [
			'expires'  => $expires,
			'path'     => '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		];
	}

	private static function write_cookie( string $slug ): void {
		setcookie( self::COOKIE_NAME, $slug, self::cookie_options( time() + self::COOKIE_TTL ) );
		$_COOKIE[ self::COOKIE_NAME ] = $slug;
	}

	private static function clear_cookie(): void {
		setcookie( self::COOKIE_NAME, '', self::cookie_options( time() - HOUR_IN_SECONDS ) );
		unset( $_COOKIE[ self::COOKIE_NAME ] );
	}

	/**
	 * La préférence du compte prime toujours sur le cookie anonyme.
	 */
	public static function current_slug(): string {
		if ( is_user_logged_in() ) {
			$stored = get_user_meta( get_current_user_id(), self::META_KEY, true );
			if ( ! is_string( $stored ) ) {
				return '';
			}
			$slug = sanitize_title( $stored );
			return self::valid_term_for_slug( $slug ) ? $slug : '';
		}

		$slug = self::cookie_slug();
		return self::valid_term_for_slug( $slug ) ? $slug : '';
	}

	private static function cookie_slug(): string {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) || ! is_string( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return '';
		}
		$raw = wp_unslash( $_COOKIE[ self::COOKIE_NAME ] );
		if ( strlen( $raw ) > 200 ) {
			return '';
		}
		return sanitize_title( $raw );
	}

	public static function promote_cookie_on_login( string $user_login, \WP_User $user ): void {
		unset( $user_login );
		$stored = get_user_meta( $user->ID, self::META_KEY, true );
		if ( '' !== $stored ) {
			self::clear_cookie();
			return;
		}

		$slug = self::cookie_slug();
		$term = self::valid_term_for_slug( $slug );
		if ( $term ) {
			update_user_meta( $user->ID, self::META_KEY, $term->slug );
		}
		self::clear_cookie();
	}

	public static function active_path(): ?\WP_Term {
		return self::valid_term_for_slug( self::current_slug() );
	}

	/** Les seules surfaces publiques qui exposent les contrôles FOCUS. */
	public static function is_focus_surface(): bool {
		return is_front_page()
			|| is_post_type_archive( CPT::VIDEO )
			|| is_tax( [ Taxonomies::TOPIC, Taxonomies::FORMAT ] )
			|| ( is_search() && self::is_exact_video_post_type( get_query_var( 'post_type' ) ) );
	}

	/**
	 * Les surfaces FOCUS et toute réponse effectivement personnalisée ne doivent
	 * jamais entrer dans un cache de page partagé entre visiteurs.
	 */
	public static function prevent_personalized_cache(): void {
		if ( ! self::is_focus_surface() && '' === self::current_slug() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Vary: Cookie', false );
		}
	}

	/**
	 * Retourne au maximum les six parcours réels possédant une vidéo publiée.
	 *
	 * @return array<int, \WP_Term>
	 */
	public static function available_paths(): array {
		$valid = [];
		foreach ( self::PATH_SLUGS as $slug ) {
			$term = self::valid_term_for_slug( $slug );
			if ( $term ) {
				$valid[] = $term;
			}
		}
		return $valid;
	}

	private static function valid_term_for_slug( string $slug ): ?\WP_Term {
		if ( '' === $slug || ! preg_match( '/^[a-z0-9-]{1,200}$/', $slug ) ) {
			return null;
		}
		if ( ! in_array( $slug, self::PATH_SLUGS, true ) ) {
			return null;
		}
		if ( array_key_exists( $slug, self::$validated_terms ) ) {
			return self::$validated_terms[ $slug ];
		}

		$term = get_term_by( 'slug', $slug, Taxonomies::PATH );
		if ( ! $term instanceof \WP_Term || Taxonomies::PATH !== $term->taxonomy ) {
			self::$validated_terms[ $slug ] = null;
			return null;
		}

		$published = get_posts(
			[
				'post_type'        => CPT::VIDEO,
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'tax_query'        => [
					[
						'taxonomy' => Taxonomies::PATH,
						'field'    => 'term_id',
						'terms'    => [ (int) $term->term_id ],
					],
				],
			]
		);
		self::$validated_terms[ $slug ] = $published ? $term : null;
		return self::$validated_terms[ $slug ];
	}

	/**
	 * Fail closed: un tableau contenant la vidéo n'est jamais une requête vidéo pure.
	 *
	 * @param mixed $post_type Valeur de la query var post_type.
	 */
	public static function is_exact_video_post_type( $post_type ): bool {
		return CPT::VIDEO === $post_type;
	}

	public static function filter_video_query( \WP_Query $query ): void {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || $query->is_feed() ) {
			return;
		}
		if ( ! $query->is_main_query() && '1' !== (string) $query->get( self::QUERY_VAR_APPLY ) ) {
			return;
		}
		if ( '1' === (string) $query->get( self::QUERY_VAR_BYPASS ) ) {
			return;
		}
		if ( $query->is_singular( CPT::VIDEO ) || $query->is_tax( Taxonomies::PATH ) ) {
			return;
		}
		$video_taxonomy = $query->is_main_query()
			&& $query->is_tax( [ Taxonomies::TOPIC, Taxonomies::FORMAT ] );
		if ( ! $video_taxonomy && ! self::is_exact_video_post_type( $query->get( 'post_type' ) ) ) {
			return;
		}

		$slug = self::current_slug();
		if ( '' === $slug ) {
			return;
		}

		$tax_query    = $query->get( 'tax_query' );
		$focus_clause = [
			'taxonomy' => Taxonomies::PATH,
			'field'    => 'slug',
			'terms'    => [ $slug ],
		];
		if ( is_array( $tax_query ) && $tax_query ) {
			$tax_query = [
				'relation' => 'AND',
				$tax_query,
				$focus_clause,
			];
		} else {
			$tax_query = [ $focus_clause ];
		}
		$query->set( 'tax_query', $tax_query );
		$query->set( self::QUERY_VAR_ACTIVE, $slug );
	}
}
