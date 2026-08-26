<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Moteur déterministe du questionnaire d'orientation MADIAS.
 *
 * Les égalités suivent toujours TIE_BREAK_ORDER. La table est versionnée pour
 * rendre toute évolution explicite et auditable.
 */
final class Business_Finder {

	public const SCORING_VERSION = '1.0';

	public const ANSWER_ALLOWLISTS = [
		'model'     => [ 'asset', 'service', 'open' ],
		'horizon'   => [ 'rapid', 'months', 'no_urgency' ],
		'budget'    => [ 'zero', 'recurring', 'invest' ],
		'clients'   => [ 'willing', 'limited', 'no' ],
		'exposure'  => [ 'face', 'voice', 'discreet' ],
		'time'      => [ 'low', 'medium', 'high' ],
		'technical' => [ 'low', 'tools', 'continuous' ],
		'potential' => [ 'stable', 'high_uncertain' ],
	];

	public const PATHS = [
		'affiliation-seo' => [
			'name'        => 'Affiliation SEO',
			'slug'        => 'apprendre-l-affiliation',
			'constraints' => [
				'Les contenus et positions organiques demandent du temps avant de produire des résultats.',
				'Les revenus dépendent des plateformes, des offres et des mises à jour des moteurs de recherche.',
			],
		],
		'youtube' => [
			'name'        => 'Youtube',
			'slug'        => 'apprendre-youtube',
			'constraints' => [
				'La publication régulière et l’apprentissage du format demandent un effort durable.',
				'L’audience et la distribution restent variables, même avec un travail constant.',
			],
		],
		'vente-de-liens' => [
			'name'        => 'Vente de liens',
			'slug'        => 'apprendre-la-vente-de-liens',
			'constraints' => [
				'Il faut disposer de sites crédibles, les entretenir et préserver leur qualité éditoriale.',
				'La demande et la valeur des sites peuvent évoluer rapidement.',
			],
		],
		'ia-automatisation' => [
			'name'        => 'IA et automatisation',
			'slug'        => 'apprendre-ia-automatisation',
			'constraints' => [
				'Les outils changent vite et imposent des tests ainsi qu’une veille continue.',
				'Une automatisation doit être contrôlée et maintenue pour rester fiable.',
			],
		],
		'vente-de-leads' => [
			'name'        => 'Vente de leads',
			'slug'        => 'apprendre-la-vente-de-leads',
			'constraints' => [
				'La qualification, le suivi des partenaires et la conformité demandent une gestion rigoureuse.',
				'L’acquisition peut nécessiter du temps ou un budget avant de trouver un canal viable.',
			],
		],
		'freelancing' => [
			'name'        => 'Freelancing',
			'slug'        => 'apprendre-le-freelancing',
			'constraints' => [
				'Le chiffre d’affaires dépend du temps disponible, du tarif et du flux de missions.',
				'La prospection, le cadrage et la relation client font partie du travail.',
			],
		],
	];

	/** Ordre stable utilisé seulement quand les scores sont égaux. */
	public const TIE_BREAK_ORDER = [
		'affiliation-seo',
		'youtube',
		'vente-de-liens',
		'ia-automatisation',
		'vente-de-leads',
		'freelancing',
	];

	public const SCORING_TABLE = [
		'model' => [
			'asset'   => [ 'affiliation-seo' => 4, 'youtube' => 4, 'vente-de-liens' => 4, 'ia-automatisation' => 0, 'vente-de-leads' => 3, 'freelancing' => 0 ],
			'service' => [ 'affiliation-seo' => 0, 'youtube' => 0, 'vente-de-liens' => 0, 'ia-automatisation' => 3, 'vente-de-leads' => 1, 'freelancing' => 5 ],
			'open'    => [ 'affiliation-seo' => 2, 'youtube' => 2, 'vente-de-liens' => 2, 'ia-automatisation' => 2, 'vente-de-leads' => 2, 'freelancing' => 2 ],
		],
		'horizon' => [
			'rapid'      => [ 'affiliation-seo' => 0, 'youtube' => 0, 'vente-de-liens' => 3, 'ia-automatisation' => 4, 'vente-de-leads' => 1, 'freelancing' => 5 ],
			'months'     => [ 'affiliation-seo' => 4, 'youtube' => 2, 'vente-de-liens' => 2, 'ia-automatisation' => 2, 'vente-de-leads' => 4, 'freelancing' => 1 ],
			'no_urgency' => [ 'affiliation-seo' => 3, 'youtube' => 5, 'vente-de-liens' => 1, 'ia-automatisation' => 2, 'vente-de-leads' => 3, 'freelancing' => 0 ],
		],
		'budget' => [
			'zero'      => [ 'affiliation-seo' => 2, 'youtube' => 3, 'vente-de-liens' => 0, 'ia-automatisation' => 2, 'vente-de-leads' => 1, 'freelancing' => 4 ],
			'recurring' => [ 'affiliation-seo' => 3, 'youtube' => 2, 'vente-de-liens' => 2, 'ia-automatisation' => 3, 'vente-de-leads' => 2, 'freelancing' => 1 ],
			'invest'    => [ 'affiliation-seo' => 2, 'youtube' => 1, 'vente-de-liens' => 5, 'ia-automatisation' => 3, 'vente-de-leads' => 5, 'freelancing' => 0 ],
		],
		'clients' => [
			'willing' => [ 'affiliation-seo' => 0, 'youtube' => 0, 'vente-de-liens' => 1, 'ia-automatisation' => 4, 'vente-de-leads' => 3, 'freelancing' => 5 ],
			'limited' => [ 'affiliation-seo' => 2, 'youtube' => 1, 'vente-de-liens' => 4, 'ia-automatisation' => 2, 'vente-de-leads' => 4, 'freelancing' => 2 ],
			'no'      => [ 'affiliation-seo' => 4, 'youtube' => 4, 'vente-de-liens' => 2, 'ia-automatisation' => 0, 'vente-de-leads' => 1, 'freelancing' => 0 ],
		],
		'exposure' => [
			'face'     => [ 'affiliation-seo' => 0, 'youtube' => 6, 'vente-de-liens' => 0, 'ia-automatisation' => 0, 'vente-de-leads' => 0, 'freelancing' => 2 ],
			'voice'    => [ 'affiliation-seo' => 1, 'youtube' => 4, 'vente-de-liens' => 1, 'ia-automatisation' => 1, 'vente-de-leads' => 1, 'freelancing' => 3 ],
			'discreet' => [ 'affiliation-seo' => 4, 'youtube' => 0, 'vente-de-liens' => 4, 'ia-automatisation' => 3, 'vente-de-leads' => 4, 'freelancing' => 0 ],
		],
		'time' => [
			'low'    => [ 'affiliation-seo' => 1, 'youtube' => 0, 'vente-de-liens' => 1, 'ia-automatisation' => 0, 'vente-de-leads' => 0, 'freelancing' => 3 ],
			'medium' => [ 'affiliation-seo' => 3, 'youtube' => 2, 'vente-de-liens' => 3, 'ia-automatisation' => 2, 'vente-de-leads' => 2, 'freelancing' => 3 ],
			'high'   => [ 'affiliation-seo' => 3, 'youtube' => 5, 'vente-de-liens' => 2, 'ia-automatisation' => 5, 'vente-de-leads' => 5, 'freelancing' => 2 ],
		],
		'technical' => [
			'low'        => [ 'affiliation-seo' => 1, 'youtube' => 1, 'vente-de-liens' => 1, 'ia-automatisation' => 0, 'vente-de-leads' => 1, 'freelancing' => 4 ],
			'tools'      => [ 'affiliation-seo' => 4, 'youtube' => 2, 'vente-de-liens' => 3, 'ia-automatisation' => 3, 'vente-de-leads' => 4, 'freelancing' => 2 ],
			'continuous' => [ 'affiliation-seo' => 3, 'youtube' => 3, 'vente-de-liens' => 2, 'ia-automatisation' => 7, 'vente-de-leads' => 4, 'freelancing' => 1 ],
		],
		'potential' => [
			'stable'         => [ 'affiliation-seo' => 3, 'youtube' => 0, 'vente-de-liens' => 5, 'ia-automatisation' => 1, 'vente-de-leads' => 1, 'freelancing' => 4 ],
			'high_uncertain' => [ 'affiliation-seo' => 2, 'youtube' => 4, 'vente-de-liens' => 1, 'ia-automatisation' => 4, 'vente-de-leads' => 5, 'freelancing' => 1 ],
		],
	];

	private const REASONS = [
		'model' => [
			'asset' => 'Tu préfères construire un actif sur la durée.',
			'service' => 'Tu privilégies une activité de service directement commercialisable.',
			'open' => 'Tu souhaites garder ouverts les modèles d’actif et de service.',
		],
		'horizon' => [
			'rapid' => 'Tu recherches une voie qui peut être commercialisée rapidement.',
			'months' => 'Tu acceptes une phase de construction de plusieurs mois.',
			'no_urgency' => 'Tu peux investir du temps sans urgence de monétisation.',
		],
		'budget' => [
			'zero' => 'Tu souhaites commencer avec très peu de budget.',
			'recurring' => 'Tu peux consacrer un petit budget récurrent aux outils ou contenus.',
			'invest' => 'Tu peux financer des actifs, des outils ou de l’acquisition.',
		],
		'clients' => [
			'willing' => 'La prospection et la gestion régulière de clients te conviennent.',
			'limited' => 'Tu acceptes une relation commerciale limitée avec des partenaires.',
			'no' => 'Tu veux limiter fortement la prospection et la gestion de clients.',
		],
		'exposure' => [
			'face' => 'Tu es à l’aise avec une présence visible face caméra.',
			'voice' => 'Tu peux utiliser ta voix sans nécessairement montrer ton visage.',
			'discreet' => 'Tu privilégies un modèle exploitable avec une présence discrète.',
		],
		'time' => [
			'low' => 'Ton temps hebdomadaire est limité.',
			'medium' => 'Tu peux maintenir un rythme hebdomadaire régulier.',
			'high' => 'Tu peux consacrer un volume de travail hebdomadaire important.',
		],
		'technical' => [
			'low' => 'Tu préfères limiter la veille et les changements d’outils.',
			'tools' => 'Tu es prêt à prendre en main des outils et à apprendre régulièrement.',
			'continuous' => 'Tu apprécies les tests d’outils et la veille technique continue.',
		],
		'potential' => [
			'stable' => 'Tu privilégies un modèle plutôt stable, même si son potentiel est borné.',
			'high_uncertain' => 'Tu acceptes davantage d’incertitude pour viser un potentiel plus élevé.',
		],
	];

	/**
	 * Valide exactement les huit réponses, sans conversion permissive.
	 *
	 * @return array<string, string>|null
	 */
	public static function validate_answers( array $answers ): ?array {
		if ( count( $answers ) !== count( self::ANSWER_ALLOWLISTS ) ) {
			return null;
		}

		$validated = [];
		foreach ( self::ANSWER_ALLOWLISTS as $question => $allowed ) {
			if ( ! array_key_exists( $question, $answers ) || ! is_string( $answers[ $question ] ) ) {
				return null;
			}
			if ( ! in_array( $answers[ $question ], $allowed, true ) ) {
				return null;
			}
			$validated[ $question ] = $answers[ $question ];
		}

		return $validated;
	}

	/** @return string[] */
	public static function hard_exclusions( array $answers ): array {
		$excluded = [];
		if ( ( $answers['clients'] ?? null ) === 'no' ) {
			$excluded[] = 'freelancing';
		}
		if ( ( $answers['technical'] ?? null ) === 'low' ) {
			$excluded[] = 'ia-automatisation';
		}
		return $excluded;
	}

	/**
	 * Trie les scores par valeur décroissante puis selon TIE_BREAK_ORDER.
	 *
	 * @param array<string, int> $scores
	 * @return array<string, int>
	 */
	public static function sort_scores( array $scores ): array {
		$order = array_flip( self::TIE_BREAK_ORDER );
		uksort(
			$scores,
			static function ( string $left, string $right ) use ( $scores, $order ): int {
				if ( $scores[ $left ] !== $scores[ $right ] ) {
					return $scores[ $right ] <=> $scores[ $left ];
				}
				return ( $order[ $left ] ?? PHP_INT_MAX ) <=> ( $order[ $right ] ?? PHP_INT_MAX );
			}
		);
		return $scores;
	}

	/**
	 * Décrit les éventuels départages des deux positions affichées.
	 *
	 * @param array<string, int> $scores
	 * @return array{primary_tie:bool,alternative_tie:bool,order:string[]}
	 */
	public static function tie_break_details( array $scores ): array {
		$scores = self::sort_scores( $scores );
		$ids    = array_keys( $scores );
		$order  = [];
		foreach ( self::TIE_BREAK_ORDER as $id ) {
			if ( array_key_exists( $id, $scores ) && isset( self::PATHS[ $id ] ) ) {
				$order[] = self::PATHS[ $id ]['name'];
			}
		}

		return [
			'primary_tie'     => isset( $ids[1] ) && $scores[ $ids[0] ] === $scores[ $ids[1] ],
			'alternative_tie' => isset( $ids[2] ) && $scores[ $ids[1] ] === $scores[ $ids[2] ],
			'order'           => $order,
		];
	}

	/**
	 * @param array<string, string> $answers
	 * @param string[] $eligible_slugs Slugs réellement publiés et non vides.
	 * @return array{primary:array<string,mixed>,alternative:array<string,mixed>,tie_break:array<string,mixed>,version:string}|null
	 */
	public static function recommend( array $answers, array $eligible_slugs ): ?array {
		$answers = self::validate_answers( $answers );
		if ( null === $answers ) {
			return null;
		}

		$eligible_slugs = array_values( array_unique( array_filter( $eligible_slugs, 'is_string' ) ) );
		$excluded       = self::hard_exclusions( $answers );
		$scores         = [];
		$contributions  = [];

		foreach ( self::PATHS as $path_id => $path ) {
			if ( in_array( $path_id, $excluded, true ) || ! in_array( $path['slug'], $eligible_slugs, true ) ) {
				continue;
			}
			$scores[ $path_id ]        = 0;
			$contributions[ $path_id ] = [];
			foreach ( $answers as $question => $answer ) {
				$points = self::SCORING_TABLE[ $question ][ $answer ][ $path_id ];
				$scores[ $path_id ] += $points;
				$contributions[ $path_id ][ $question ] = $points;
			}
		}

		if ( count( $scores ) < 2 ) {
			return null;
		}

		$scores = self::sort_scores( $scores );
		$ids    = array_keys( $scores );

		return [
			'primary'     => self::build_result( $ids[0], $scores[ $ids[0] ], $answers, $contributions[ $ids[0] ] ),
			'alternative' => self::build_result( $ids[1], $scores[ $ids[1] ], $answers, $contributions[ $ids[1] ] ),
			'tie_break'   => self::tie_break_details( $scores ),
			'version'     => self::SCORING_VERSION,
		];
	}

	/** @return array<string, mixed> */
	private static function build_result( string $path_id, int $score, array $answers, array $contributions ): array {
		$question_order = array_flip( array_keys( self::ANSWER_ALLOWLISTS ) );
		uksort(
			$contributions,
			static function ( string $left, string $right ) use ( $contributions, $question_order ): int {
				if ( $contributions[ $left ] !== $contributions[ $right ] ) {
					return $contributions[ $right ] <=> $contributions[ $left ];
				}
				return $question_order[ $left ] <=> $question_order[ $right ];
			}
		);
		$decisive = array_slice( array_keys( $contributions ), 0, 2 );
		$reasons  = [];
		foreach ( $decisive as $question ) {
			$reasons[] = self::REASONS[ $question ][ $answers[ $question ] ];
		}

		return [
			'id'          => $path_id,
			'name'        => self::PATHS[ $path_id ]['name'],
			'slug'        => self::PATHS[ $path_id ]['slug'],
			'score'       => $score,
			'reasons'     => $reasons,
			'constraints' => self::PATHS[ $path_id ]['constraints'],
		];
	}
}
