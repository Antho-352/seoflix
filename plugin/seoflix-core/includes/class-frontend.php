<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes et vues dédiées côté public.
 *
 * /categories/ : index historique des catégories.
 * /parcours/   : index éditorial fixe des six parcours MADIAS.
 * /commencer/  : questionnaire d’orientation business.
 */
final class Frontend {

	private const QUERY_VAR = 'seoflix_view';
	public const REWRITE_SCHEMA_VERSION = 2;
	private const REWRITE_SCHEMA_OPTION = 'seoflix_frontend_rewrite_version';

	private const TEMPLATES = [
		'topics'          => 'page-topics-index.php',
		'paths'           => 'page-paths-index.php',
		'business-finder' => 'page-business-finder.php',
	];

	public static function init(): void {
		add_action( 'init', [ self::class, 'register_rewrite' ] );
		add_action( 'init', [ self::class, 'maybe_upgrade_rewrites' ], 99 );
		add_filter( 'query_vars', [ self::class, 'add_query_var' ] );
		add_filter( 'template_include', [ self::class, 'load_template' ] );
		add_action( 'pre_get_posts', [ self::class, 'fix_404' ] );
		add_filter( 'pre_handle_404', [ self::class, 'pre_handle_404' ], 10, 2 );
		add_action( 'wp_head', [ self::class, 'render_canonical' ], 2 );
		add_filter( 'document_title_parts', [ self::class, 'filter_title' ] );
	}

	public static function register_rewrite(): void {
		add_rewrite_rule( '^categories/?$', 'index.php?' . self::QUERY_VAR . '=topics', 'top' );
		add_rewrite_rule( '^parcours/?$', 'index.php?' . self::QUERY_VAR . '=paths', 'top' );
		add_rewrite_rule( '^commencer/?$', 'index.php?' . self::QUERY_VAR . '=business-finder', 'top' );

		// Les routes de termes `seoflix_path` restent gérées par WordPress.
		$category_aliases = [
			'formations'  => 'formations',
			'outils-seo'  => 'outils-seo',
			'plateformes' => 'plateformes-vente-de-liens',
			'hebergement' => 'hebergement',
		];
		foreach ( $category_aliases as $public_slug => $term_slug ) {
			add_rewrite_rule(
				'^' . preg_quote( $public_slug, '#' ) . '/?$',
				'index.php?seoflix_product_category=' . $term_slug,
				'top'
			);
		}
	}

	/** Installe une seule fois les nouvelles routes après un remplacement ZIP. */
	public static function maybe_upgrade_rewrites(): void {
		if ( (int) get_option( self::REWRITE_SCHEMA_OPTION, 0 ) >= self::REWRITE_SCHEMA_VERSION ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( self::REWRITE_SCHEMA_OPTION, self::REWRITE_SCHEMA_VERSION, false );
	}

	public static function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function current_view(): string {
		return sanitize_key( (string) get_query_var( self::QUERY_VAR ) );
	}

	public static function is_view( string $view ): bool {
		return sanitize_key( $view ) === self::current_view();
	}

	public static function is_seoflix_view( string $view ): bool {
		return isset( self::TEMPLATES[ $view ] ) && self::is_view( $view );
	}

	public static function fix_404( \WP_Query $query ): void {
		if ( ! $query->is_main_query() || is_admin() ) {
			return;
		}
		if ( ! self::is_view( 'topics' ) && ! self::is_view( 'paths' ) && ! self::is_view( 'business-finder' ) ) {
			return;
		}
		$view = self::current_view();
		if ( ! locate_template( self::TEMPLATES[ $view ] ) ) {
			return;
		}
		$query->is_404 = false;
		$query->is_home = false;
	}

	/** Empêche WP::handle_404() de reclasser les vues virtuelles après la requête. */
	public static function pre_handle_404( $preempt, \WP_Query $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return $preempt;
		}
		if ( ! self::is_view( 'topics' ) && ! self::is_view( 'paths' ) && ! self::is_view( 'business-finder' ) ) {
			return $preempt;
		}
		$view = self::current_view();
		if ( ! isset( self::TEMPLATES[ $view ] ) || ! locate_template( self::TEMPLATES[ $view ] ) ) {
			return $preempt;
		}
		$query->is_404 = false;
		$query->is_home = false;
		status_header( 200 );
		return true;
	}

	public static function load_template( string $template ): string {
		$view = self::current_view();
		switch ( $view ) {
			case 'topics':
				$candidate = locate_template( 'page-topics-index.php' );
				break;
			case 'paths':
				$candidate = locate_template( 'page-paths-index.php' );
				break;
			case 'business-finder':
				$candidate = locate_template( 'page-business-finder.php' );
				break;
			default:
				return $template;
		}
		if ( $candidate ) {
			status_header( 200 );
			return $candidate;
		}
		return $template;
	}

	public static function render_canonical(): void {
		if ( ! self::is_seoflix_view( 'business-finder' ) ) {
			return;
		}
		echo '<link rel="canonical" href="' . esc_url( home_url( '/commencer/' ) ) . '">' . "\n";
	}

	public static function filter_title( array $parts ): array {
		if ( self::is_seoflix_view( 'business-finder' ) ) {
			$parts['title'] = 'Trouver le business à apprendre';
		}
		return $parts;
	}
}
