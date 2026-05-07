<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes & vues custom côté frontend (hors CPT/taxonomies natifs).
 *
 * Routes :
 *   /categories/  →  index de tous les sujets (theme: page-topics-index.php)
 */
final class Frontend {

	private const QUERY_VAR = 'seoflix_view';

	public static function init(): void {
		add_action( 'init',              [ self::class, 'register_rewrite' ] );
		add_filter( 'query_vars',        [ self::class, 'add_query_var' ] );
		add_filter( 'template_include',  [ self::class, 'load_template' ] );
		add_action( 'pre_get_posts',     [ self::class, 'fix_404' ] );
	}

	public static function register_rewrite(): void {
		add_rewrite_rule( '^categories/?$', 'index.php?' . self::QUERY_VAR . '=topics', 'top' );

		// Alias raccourcis vers les catégories de produits les plus utilisées
		// (le slug technique reste `categorie-outil/<slug>` mais ces URLs sont plus propres)
		$category_aliases = [
			'formations'      => 'formations',
			'outils-seo'      => 'outils-seo',
			'plateformes'     => 'plateformes-vente-de-liens',
			'hebergement'     => 'hebergement',
		];
		foreach ( $category_aliases as $public_slug => $term_slug ) {
			add_rewrite_rule(
				'^' . preg_quote( $public_slug, '#' ) . '/?$',
				'index.php?seoflix_product_category=' . $term_slug,
				'top'
			);
		}
	}

	public static function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function fix_404( \WP_Query $query ): void {
		if ( ! $query->is_main_query() || is_admin() ) {
			return;
		}
		if ( get_query_var( self::QUERY_VAR ) ) {
			$query->is_404 = false;
			$query->is_home = false;
		}
	}

	public static function load_template( string $template ): string {
		$view = get_query_var( self::QUERY_VAR );
		if ( ! $view ) {
			return $template;
		}

		switch ( $view ) {
			case 'topics':
				$candidate = locate_template( 'page-topics-index.php' );
				if ( $candidate ) {
					status_header( 200 );
					return $candidate;
				}
				break;
		}

		return $template;
	}
}
