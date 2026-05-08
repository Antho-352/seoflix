<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configuration de la page d'accueil.
 *
 * Stocke en option `seoflix_homepage_config` un tableau structuré :
 *   - hero : title, subtitle, rotating_words[], show_stats
 *   - sections : liste ordonnée de blocs avec type/title/visible/extra
 *
 * Le thème (front-page.php) consomme ces options pour rendre la home.
 */
final class Homepage {

	public const OPTION = 'seoflix_homepage_config';

	public const TYPE_NEW         = 'new_videos';
	public const TYPE_MOST_VIEWED = 'most_viewed';
	public const TYPE_TOPICS      = 'topics';
	public const TYPE_CHANNELS    = 'channels';
	public const TYPE_PATHS       = 'paths';
	public const TYPE_TOPIC       = 'topic'; // un topic spécifique

	public static function defaults(): array {
		return [
			'hero' => [
				'title'          => 'Maîtrise [rotate] avec les meilleurs.',
				'subtitle'       => "Les meilleures interviews, podcasts et tutos sur le SEO, le netlinking, l'affiliation, la vente de liens et le business web — agrégés en un seul endroit.",
				'rotating_words' => [
					"l'Edition de Sites",
					"le SEO",
					"l'Affiliation",
					"Youtube",
					"la Vente de Liens",
					"l'IA",
					"le GEO",
					"la Vente de Leads",
					"le Black Hat",
					"le Business en ligne",
				],
				'show_stats'     => true,
			],
			'sections' => [
				[ 'type' => self::TYPE_NEW,         'title' => 'Nouveautés',                'visible' => true,  'order' => 1, 'limit' => 12 ],
				[ 'type' => self::TYPE_TOPICS,      'title' => '',                          'visible' => true,  'order' => 2, 'limit' => 12, 'topics_count' => 6 ],
				[ 'type' => self::TYPE_CHANNELS,    'title' => 'Chaînes',                   'visible' => true,  'order' => 3, 'limit' => 12 ],
				[ 'type' => self::TYPE_PATHS,      'title' => "Parcours d'apprentissage",   'visible' => true,  'order' => 4 ],
				[ 'type' => self::TYPE_MOST_VIEWED, 'title' => 'Les plus vues',             'visible' => false, 'order' => 5, 'limit' => 12 ],
			],
		];
	}

	public static function get_config(): array {
		$saved = get_option( self::OPTION, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		// Merge récursif avec les défauts pour ne pas casser si on ajoute un champ
		$config = self::defaults();
		if ( isset( $saved['hero'] ) && is_array( $saved['hero'] ) ) {
			$config['hero'] = array_merge( $config['hero'], $saved['hero'] );
		}
		if ( isset( $saved['sections'] ) && is_array( $saved['sections'] ) ) {
			$config['sections'] = $saved['sections'];
		}
		return $config;
	}

	public static function save_config( array $config ): void {
		update_option( self::OPTION, $config, false );
	}

	public static function reset(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Retourne les sections triées par ordre, visibles seulement.
	 */
	public static function visible_sections(): array {
		$cfg      = self::get_config();
		$sections = $cfg['sections'] ?? [];
		$sections = array_values( array_filter( $sections, static fn( $s ) => ! empty( $s['visible'] ) ) );
		usort( $sections, static fn( $a, $b ) => ( (int) ( $a['order'] ?? 99 ) ) <=> ( (int) ( $b['order'] ?? 99 ) ) );
		return $sections;
	}

	public static function section_labels(): array {
		return [
			self::TYPE_NEW         => 'Nouveautés (dernières vidéos)',
			self::TYPE_MOST_VIEWED => 'Les plus vues',
			self::TYPE_TOPICS      => 'Topics (auto, top N par nb vidéos)',
			self::TYPE_CHANNELS    => 'Chaînes',
			self::TYPE_PATHS       => "Parcours d'apprentissage",
			self::TYPE_TOPIC       => 'Topic spécifique',
		];
	}
}
