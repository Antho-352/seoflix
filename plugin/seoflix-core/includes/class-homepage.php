<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configuration ciblée de la page d'accueil WEAS.
 *
 * L'option historique `seoflix_homepage_config` est conservée. Le rendu public
 * utilise désormais un assemblage fixe : seuls les textes du hero, les outils,
 * les trois parcours mis en avant et la visibilité des blocs sont réglables.
 */
final class Homepage {

	public const OPTION = 'seoflix_homepage_config';
	public const PATH_ORDER_META    = 'seoflix_path_public_order';
	public const FOCUS_ENABLED_META = 'seoflix_focus_enabled';
	public const FOCUS_LABEL_META   = 'seoflix_focus_label';

	/** Constantes historiques conservées pour les intégrations existantes. */
	public const TYPE_NEW          = 'new_videos';
	public const TYPE_MOST_VIEWED  = 'most_viewed';
	public const TYPE_TOPICS       = 'topics';
	public const TYPE_CHANNELS     = 'channels';
	public const TYPE_PATHS        = 'paths';
	public const TYPE_TOPIC        = 'topic';
	public const MAX_BEST_TOOLS    = 8;
	public const MAX_FEATURED_ROWS = 3;

	/**
	 * Catalogue public intentionnel. L'ordre est un contrat éditorial.
	 *
	 * @return array<int,array{slug:string,name:string,hero_label:string,focus_label:string,icon:string,description:string}>
	 */
	public static function path_definitions(): array {
		return [
			[ 'slug' => 'apprendre-l-affiliation', 'name' => 'Affiliation SEO', 'hero_label' => 'Affiliation SEO', 'focus_label' => "Apprendre l'affiliation", 'icon' => '◎', 'description' => 'Construire des actifs éditoriaux et monétiser une audience qualifiée.' ],
			[ 'slug' => 'apprendre-youtube', 'name' => 'Youtube', 'hero_label' => 'YouTube', 'focus_label' => '', 'icon' => '▶', 'description' => 'Choisir un angle, publier régulièrement et développer une audience vidéo.' ],
			[ 'slug' => 'apprendre-la-vente-de-liens', 'name' => 'Vente de liens', 'hero_label' => 'Vente de liens', 'focus_label' => 'Apprendre la vente de liens', 'icon' => '↗', 'description' => 'Créer et exploiter un portefeuille de sites avec méthode.' ],
			[ 'slug' => 'apprendre-ia-automatisation', 'name' => 'IA et automatisation', 'hero_label' => 'IA et automatisation', 'focus_label' => '', 'icon' => '◇', 'description' => 'Utiliser les outils IA pour accélérer des tâches réellement utiles.' ],
			[ 'slug' => 'apprendre-la-vente-de-leads', 'name' => 'Vente de leads', 'hero_label' => 'Vente de leads', 'focus_label' => 'Apprendre la vente de leads', 'icon' => '＋', 'description' => 'Générer, qualifier et transmettre des contacts à des partenaires.' ],
			[ 'slug' => 'apprendre-le-freelancing', 'name' => 'Freelancing', 'hero_label' => 'Freelancing', 'focus_label' => '', 'icon' => '◆', 'description' => 'Vendre une compétence, cadrer ses missions et construire une activité durable.' ],
		];
	}

	/** @return array<int,\WP_Term> */
	public static function public_path_terms(): array {
		$terms = get_terms( [
			'taxonomy'   => Taxonomies::PATH,
			'hide_empty' => false,
			'meta_key'   => self::PATH_ORDER_META,
			'orderby'    => 'meta_value_num',
			'order'      => 'ASC',
		] );
		return is_wp_error( $terms ) ? [] : array_values( array_filter( $terms, static fn( $term ) => $term instanceof \WP_Term ) );
	}

	/** @return string[] */
	public static function public_path_slugs(): array {
		return array_values( array_map( static fn( \WP_Term $term ): string => $term->slug, self::public_path_terms() ) );
	}

	/** @return array<string,mixed> */
	public static function defaults(): array {
		return [
			'hero' => [
				'title'     => 'Apprends le business web sans perdre des heures sur YouTube.',
				'subtitle'  => 'Des vidéos utiles, sélectionnées et organisées pour avancer sans bruit inutile.',
				'cta_text'  => 'Commencer à apprendre',
			],
			'best_tool_ids'       => [],
			'featured_path_slugs' => [ 'apprendre-ia-automatisation', 'apprendre-youtube', 'apprendre-la-vente-de-liens' ],
			'fixed_blocks'        => [
				'paths'          => true,
				'new'            => true,
				'tools'          => true,
				'promise'        => true,
				'featured_paths' => true,
				'paths_cta'      => true,
				'about'          => true,
				'newsletter'     => true,
				'blog'           => true,
			],
		];
	}

	/** @return int[] */
	public static function normalize_tool_ids( $ids ): array {
		if ( is_string( $ids ) ) {
			$ids = preg_split( '/[\s,]+/', $ids, -1, PREG_SPLIT_NO_EMPTY );
		}
		if ( ! is_array( $ids ) ) {
			return [];
		}
		$normalized = [];
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 && ! in_array( $id, $normalized, true ) ) {
				$normalized[] = $id;
			}
		}
		return array_slice( $normalized, 0, self::MAX_BEST_TOOLS );
	}

	/** @return string[] */
	public static function normalize_path_slugs( $slugs ): array {
		if ( ! is_array( $slugs ) ) {
			$slugs = [];
		}
		$legacy_aliases = [
			'ia-et-automatisation' => 'apprendre-ia-automatisation',
			'youtube'              => 'apprendre-youtube',
			'vente-de-liens'       => 'apprendre-la-vente-de-liens',
		];
		$normalized = [];
		foreach ( $slugs as $slug ) {
			$slug = sanitize_key( $slug );
			$slug = $legacy_aliases[ $slug ] ?? $slug;
			if ( $slug && ! in_array( $slug, $normalized, true ) ) {
				$normalized[] = $slug;
			}
		}
		return array_slice( $normalized, 0, 3 );
	}

	/** @return array<string,mixed> */
	public static function get_config(): array {
		$defaults = self::defaults();
		$saved    = get_option( self::OPTION, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		$config = $defaults;
		if ( isset( $saved['hero'] ) && is_array( $saved['hero'] ) ) {
			foreach ( [ 'title', 'subtitle', 'cta_text' ] as $field ) {
				if ( isset( $saved['hero'][ $field ] ) && is_string( $saved['hero'][ $field ] ) ) {
					$config['hero'][ $field ] = $saved['hero'][ $field ];
				}
			}
		}

		$config['best_tool_ids'] = self::normalize_tool_ids( $saved['best_tool_ids'] ?? [] );
		$featured = self::normalize_path_slugs( $saved['featured_path_slugs'] ?? [] );
		foreach ( $defaults['featured_path_slugs'] as $fallback ) {
			if ( count( $featured ) >= self::MAX_FEATURED_ROWS ) {
				break;
			}
			if ( ! in_array( $fallback, $featured, true ) ) {
				$featured[] = $fallback;
			}
		}
		$config['featured_path_slugs'] = array_slice( $featured, 0, self::MAX_FEATURED_ROWS );

		if ( isset( $saved['fixed_blocks'] ) && is_array( $saved['fixed_blocks'] ) ) {
			foreach ( array_keys( $defaults['fixed_blocks'] ) as $block ) {
				if ( array_key_exists( $block, $saved['fixed_blocks'] ) ) {
					$config['fixed_blocks'][ $block ] = (bool) $saved['fixed_blocks'][ $block ];
				}
			}
		} elseif ( isset( $saved['sections'] ) && is_array( $saved['sections'] ) ) {
			// Compatibilité sûre : seules les anciennes visibilités équivalentes sont reprises.
			foreach ( $saved['sections'] as $legacy_section ) {
				if ( ! is_array( $legacy_section ) ) {
					continue;
				}
				$type = $legacy_section['type'] ?? '';
				if ( self::TYPE_NEW === $type ) {
					$config['fixed_blocks']['new'] = ! empty( $legacy_section['visible'] );
				} elseif ( self::TYPE_PATHS === $type ) {
					$config['fixed_blocks']['paths'] = ! empty( $legacy_section['visible'] );
				}
			}
		}

		return $config;
	}

	/** @param array<string,mixed> $config */
	public static function save_config( array $config ): void {
		$defaults = self::defaults();
		$hero_in  = isset( $config['hero'] ) && is_array( $config['hero'] ) ? $config['hero'] : [];
		$blocks   = [];
		foreach ( array_keys( $defaults['fixed_blocks'] ) as $block ) {
			$blocks[ $block ] = ! empty( $config['fixed_blocks'][ $block ] );
		}

		update_option(
			self::OPTION,
			[
				'hero' => [
					'title'    => sanitize_text_field( $hero_in['title'] ?? $defaults['hero']['title'] ),
					'subtitle' => sanitize_textarea_field( $hero_in['subtitle'] ?? $defaults['hero']['subtitle'] ),
					'cta_text' => sanitize_text_field( $hero_in['cta_text'] ?? $defaults['hero']['cta_text'] ),
				],
				'best_tool_ids'       => self::normalize_tool_ids( $config['best_tool_ids'] ?? [] ),
				'featured_path_slugs' => self::normalize_path_slugs( $config['featured_path_slugs'] ?? [] ),
				'fixed_blocks'        => $blocks,
			],
			false
		);
	}

	public static function reset(): void {
		delete_option( self::OPTION );
	}
}
