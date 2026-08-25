<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CPT {

	public const VIDEO   = 'seoflix_video';
	public const CHANNEL = 'seoflix_channel';
	public const PRODUCT = 'seoflix_product';

	public static function register(): void {
		add_action( 'init', [ self::class, 'register_video' ] );
		add_action( 'init', [ self::class, 'register_channel' ] );
		add_action( 'init', [ self::class, 'register_product' ] );
	}

	public static function register_video(): void {
		register_post_type( self::VIDEO, [
			'labels'              => [
				'name'               => 'Vidéos',
				'singular_name'      => 'Vidéo',
				'add_new'            => 'Ajouter',
				'add_new_item'       => 'Ajouter une vidéo',
				'edit_item'          => 'Modifier la vidéo',
				'new_item'           => 'Nouvelle vidéo',
				'view_item'          => 'Voir la vidéo',
				'search_items'       => 'Rechercher des vidéos',
				'not_found'          => 'Aucune vidéo',
				'not_found_in_trash' => 'Aucune vidéo dans la corbeille',
				'menu_name'          => 'Vidéos',
			],
			'public'              => true,
			'show_in_rest'        => true,
			'has_archive'         => 'videos',
			'rewrite'             => [ 'slug' => 'video', 'with_front' => false ],
			'show_in_menu'        => 'seoflix',
			'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions', 'comments' ],
			'taxonomies'          => [ 'seoflix_topic', 'seoflix_format', 'seoflix_path' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		] );
	}

	public static function register_channel(): void {
		register_post_type( self::CHANNEL, [
			'labels'              => [
				'name'               => 'Chaînes',
				'singular_name'      => 'Chaîne',
				'add_new'            => 'Ajouter',
				'add_new_item'       => 'Ajouter une chaîne',
				'edit_item'          => 'Modifier la chaîne',
				'new_item'           => 'Nouvelle chaîne',
				'view_item'          => 'Voir la chaîne',
				'search_items'       => 'Rechercher des chaînes',
				'not_found'          => 'Aucune chaîne',
				'not_found_in_trash' => 'Aucune chaîne dans la corbeille',
				'menu_name'          => 'Chaînes',
			],
			'public'              => true,
			'show_in_rest'        => true,
			'has_archive'         => 'chaines',
			'rewrite'             => [ 'slug' => 'chaine', 'with_front' => false ],
			'show_in_menu'        => 'seoflix',
			'supports'            => [ 'title', 'editor', 'thumbnail', 'custom-fields', 'revisions' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		] );
	}

	public static function register_product(): void {
		register_post_type( self::PRODUCT, [
			'labels'              => [
				'name'               => 'Produits',
				'singular_name'      => 'Produit',
				'add_new'            => 'Ajouter',
				'add_new_item'       => 'Ajouter un produit',
				'edit_item'          => 'Modifier le produit',
				'new_item'           => 'Nouveau produit',
				'view_item'          => 'Voir le produit',
				'search_items'       => 'Rechercher des produits',
				'not_found'          => 'Aucun produit',
				'not_found_in_trash' => 'Aucun produit dans la corbeille',
				'menu_name'          => 'Produits',
			],
			'public'              => true,
			'show_in_rest'        => true,
			'has_archive'         => 'outils',
			'rewrite'             => [ 'slug' => 'outils', 'with_front' => false ],
			'show_in_menu'        => 'seoflix',
			'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ],
			'taxonomies'          => [ 'seoflix_product_category' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		] );
	}
}
