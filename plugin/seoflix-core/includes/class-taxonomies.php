<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Taxonomies {

	public const TOPIC            = 'seoflix_topic';
	public const FORMAT           = 'seoflix_format';
	public const PATH             = 'seoflix_path';
	public const PRODUCT_CATEGORY = 'seoflix_product_category';

	public static function register(): void {
		add_action( 'init', [ self::class, 'register_topic' ] );
		add_action( 'init', [ self::class, 'register_format' ] );
		add_action( 'init', [ self::class, 'register_path' ] );
		add_action( 'init', [ self::class, 'register_product_category' ] );
	}

	public static function register_topic(): void {
		register_taxonomy( self::TOPIC, [ CPT::VIDEO ], [
			'labels'            => [
				'name'          => 'Sujets',
				'singular_name' => 'Sujet',
				'menu_name'     => 'Sujets',
			],
			'public'            => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => 'sujet', 'with_front' => false ],
		] );
	}

	public static function register_format(): void {
		register_taxonomy( self::FORMAT, [ CPT::VIDEO ], [
			'labels'            => [
				'name'          => 'Formats',
				'singular_name' => 'Format',
				'menu_name'     => 'Formats',
			],
			'public'            => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => 'format', 'with_front' => false ],
		] );
	}

	public static function register_path(): void {
		register_taxonomy( self::PATH, [ CPT::VIDEO ], [
			'labels'            => [
				'name'          => 'Parcours',
				'singular_name' => 'Parcours',
				'menu_name'     => 'Parcours',
			],
			'public'            => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => 'parcours', 'with_front' => false ],
		] );
	}

	public static function register_product_category(): void {
		register_taxonomy( self::PRODUCT_CATEGORY, [ CPT::PRODUCT ], [
			'labels'            => [
				'name'          => 'Catégories de produits',
				'singular_name' => 'Catégorie',
				'menu_name'     => 'Catégories',
			],
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => 'categorie-outil', 'with_front' => false ],
		] );
	}
}
