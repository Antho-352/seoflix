<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {

	public static function activate(): void {
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-meta-keys.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-cpt.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-taxonomies.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-path-order.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-db-schema.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-affiliate.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-frontend.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-homepage.php';

		// L'activation arrive après `init` : enregistrer immédiatement, sans hook tardif.
		CPT::register_video();
		CPT::register_channel();
		CPT::register_product();
		Taxonomies::register_topic();
		Taxonomies::register_format();
		Taxonomies::register_path();
		Taxonomies::register_product_category();
		DB_Schema::maybe_upgrade();
		Affiliate::register_rewrite();
		Frontend::register_rewrite();
		update_option( 'seoflix_frontend_rewrite_version', Frontend::REWRITE_SCHEMA_VERSION, false );

		self::seed_default_terms();
		self::seed_default_options();
		self::set_seed_version_verified();
		self::ensure_product_catalog_seeded();

		flush_rewrite_rules();
	}

	public static function seed_default_terms(): void {
		$topics = [
			'seo-technique'    => 'SEO technique',
			'netlinking'       => 'Netlinking',
			'black-hat'        => 'Black Hat',
			'vente-de-liens'   => 'Vente de liens',
			'affiliation'      => 'Affiliation',
			'vente-de-leads'   => 'Vente de leads',
			'dropshipping'     => 'Dropshipping',
			'youtube'          => 'YouTube',
			'mindset-business' => 'Mindset business',
			'organisation'     => 'Organisation',
			'infrastructure'   => 'Infrastructure',
			'business-general' => 'Business',
			'e-commerce'       => 'E-commerce',
			'ia-redaction'     => 'IA & Rédaction',
			'analytics'        => 'Analytics',
		];
		self::insert_terms( 'seoflix_topic', $topics );

		$formats = [
			'podcast'         => 'Podcast',
			'interview'       => 'Interview',
			'build-in-public' => 'Build in Public',
			'tuto'            => 'Tutoriel',
			'cas-pratique'    => 'Cas pratique',
			'conference'      => 'Conférence',
			'vlog'            => 'Vlog',
		];
		self::insert_terms( 'seoflix_format', $formats );

		self::normalize_path_terms();

		$product_categories = [
			'outils-seo'                  => 'Outils SEO',
			'crawlers'                    => 'Crawlers',
			'plateformes-vente-de-liens'  => 'Plateformes vente de liens',
			'plateformes-affiliation'     => "Plateformes d'affiliation",
			'hebergement'                 => 'Hébergement',
			'vps'                         => 'VPS / Serveurs dédiés',
			'domaines'                    => 'Domaines',
			'wordpress-plugins'           => 'Plugins WordPress',
			'wordpress-themes'            => 'Thèmes WordPress',
			'formations'                  => 'Formations',
			'ia-redaction'                => 'IA / Rédaction',
			'trackers-analytics'          => 'Analytics',
			'email-marketing'             => 'Email marketing',
			'automatisation'              => 'Automatisation',
			'autres'                      => 'Autres',
		];
		self::insert_terms( 'seoflix_product_category', $product_categories );
	}

	private static function insert_terms( string $taxonomy, array $terms ): void {
		foreach ( $terms as $slug => $name ) {
			if ( ! term_exists( $slug, $taxonomy ) ) {
				wp_insert_term( $name, $taxonomy, [ 'slug' => $slug ] );
			}
		}
	}

	private static function set_term_meta_verified( int $term_id, string $meta_key, $value ): void {
		update_term_meta( $term_id, $meta_key, $value );
		if ( (string) get_term_meta( $term_id, $meta_key, true ) !== (string) $value ) {
			throw new \RuntimeException( 'Impossible d’enregistrer la métadonnée de parcours ' . $meta_key . '.' );
		}
	}

	private static function delete_term_meta_verified( int $term_id, string $meta_key ): void {
		delete_term_meta( $term_id, $meta_key );
		if ( metadata_exists( 'term', $term_id, $meta_key ) ) {
			throw new \RuntimeException( 'Impossible de supprimer la métadonnée de parcours ' . $meta_key . '.' );
		}
	}

	private static function set_post_meta_verified( int $post_id, string $meta_key, $value ): void {
		update_post_meta( $post_id, $meta_key, $value );
		if ( (string) get_post_meta( $post_id, $meta_key, true ) !== (string) $value ) {
			throw new \RuntimeException( 'Impossible d’enregistrer la métadonnée vidéo ' . $meta_key . '.' );
		}
	}

	private static function set_product_catalog_seed_version_verified(): void {
		update_option( 'seoflix_product_catalog_seed_version', self::PRODUCT_CATALOG_SEED_VERSION, false );
		if ( (int) get_option( 'seoflix_product_catalog_seed_version', 0 ) !== self::PRODUCT_CATALOG_SEED_VERSION ) {
			throw new \RuntimeException( 'Impossible de confirmer la version de migration du catalogue produits.' );
		}
	}

	private static function set_seed_version_verified(): void {
		update_option( 'seoflix_terms_seed_version', self::TERMS_SEED_VERSION );
		if ( (int) get_option( 'seoflix_terms_seed_version', 0 ) !== self::TERMS_SEED_VERSION ) {
			throw new \RuntimeException( 'Impossible de confirmer la version de migration des parcours.' );
		}
	}

	/**
	 * Ramène le catalogue historique aux six parcours WEAS sans perdre les
	 * relations ni les ordres éditoriaux attachés aux vidéos.
	 */
	private static function normalize_path_terms(): void {
		$canonical_ids = [];
		foreach ( Homepage::path_definitions() as $position => $definition ) {
			$existing = term_exists( $definition['slug'], Taxonomies::PATH );
			if ( ! $existing ) {
				$existing = wp_insert_term( $definition['name'], Taxonomies::PATH, [
					'slug'        => $definition['slug'],
					'description' => $definition['description'],
				] );
			}
			if ( is_wp_error( $existing ) ) {
				throw new \RuntimeException( 'Impossible de créer le parcours ' . $definition['slug'] . '.' );
			}

			$term_id = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
			$term    = get_term( $term_id, Taxonomies::PATH );
			if ( ! $term instanceof \WP_Term ) {
				throw new \RuntimeException( 'Parcours introuvable après création : ' . $definition['slug'] . '.' );
			}
			$update = [ 'name' => $definition['name'] ];
			if ( '' === trim( (string) $term->description ) ) {
				$update['description'] = $definition['description'];
			}
			$result = wp_update_term( $term_id, Taxonomies::PATH, $update );
			if ( is_wp_error( $result ) ) {
				throw new \RuntimeException( 'Impossible de normaliser le parcours ' . $definition['slug'] . '.' );
			}

			self::set_term_meta_verified( $term_id, Homepage::PATH_ORDER_META, $position + 1 );
			self::set_term_meta_verified( $term_id, Homepage::FOCUS_ENABLED_META, $definition['focus_label'] !== '' ? '1' : '0' );
			if ( $definition['focus_label'] !== '' ) {
				self::set_term_meta_verified( $term_id, Homepage::FOCUS_LABEL_META, $definition['focus_label'] );
			} else {
				self::delete_term_meta_verified( $term_id, Homepage::FOCUS_LABEL_META );
			}
			$canonical_ids[ $definition['slug'] ] = $term_id;
		}

		$legacy_map = [
			'apprendre-le-seo'                 => 'apprendre-l-affiliation',
			'apprendre-le-netlinking'          => 'apprendre-la-vente-de-liens',
			'apprendre-le-business'            => 'apprendre-l-affiliation',
			'apprendre-lia-et-lautomatisation' => 'apprendre-ia-automatisation',
		];
		foreach ( $legacy_map as $legacy_slug => $target_slug ) {
			$legacy = get_term_by( 'slug', $legacy_slug, Taxonomies::PATH );
			if ( ! $legacy instanceof \WP_Term ) {
				continue;
			}
			$target_id  = $canonical_ids[ $target_slug ];
			$object_ids = get_objects_in_term( (int) $legacy->term_id, Taxonomies::PATH );
			if ( is_wp_error( $object_ids ) ) {
				throw new \RuntimeException( 'Impossible de lire les relations du parcours ' . $legacy_slug . '.' );
			}
			foreach ( array_map( 'intval', $object_ids ) as $object_id ) {
				$assigned = wp_set_object_terms( $object_id, [ $target_id ], Taxonomies::PATH, true );
				if ( is_wp_error( $assigned ) ) {
					throw new \RuntimeException( 'Impossible de transférer une vidéo depuis ' . $legacy_slug . '.' );
				}
				$orders = Path_Order::sanitize_order_map( get_post_meta( $object_id, Meta_Keys::VIDEO_PATH_ORDERS, true ) );
				if ( isset( $orders[ (int) $legacy->term_id ] ) ) {
					$orders[ $target_id ] = $orders[ $target_id ] ?? $orders[ (int) $legacy->term_id ];
					unset( $orders[ (int) $legacy->term_id ] );
					$encoded = wp_json_encode( (object) $orders );
					if ( false === $encoded ) {
						throw new \RuntimeException( 'Impossible d’encoder l’ordre vidéo de ' . $legacy_slug . '.' );
					}
					self::set_post_meta_verified( $object_id, Meta_Keys::VIDEO_PATH_ORDERS, $encoded );
				}
			}
			$deleted = wp_delete_term( (int) $legacy->term_id, Taxonomies::PATH );
			if ( is_wp_error( $deleted ) || false === $deleted ) {
				throw new \RuntimeException( 'Impossible de retirer le parcours historique ' . $legacy_slug . '.' );
			}
		}
	}

	private static function seed_product_catalog_metadata(): bool {
		$linkquiver = get_page_by_path( 'linkquiver', OBJECT, CPT::PRODUCT );
		$cuik       = get_page_by_path( 'cuik', OBJECT, CPT::PRODUCT );
		if ( ! ( $linkquiver instanceof \WP_Post ) || ! ( $cuik instanceof \WP_Post ) ) {
			return false;
		}

		if ( trim( (string) get_post_meta( $linkquiver->ID, Meta_Keys::PRODUCT_LOGO_URL, true ) ) === '' ) {
			$canonical_scheme = wp_parse_url( home_url( '/' ), PHP_URL_SCHEME );
			if ( ! in_array( $canonical_scheme, [ 'http', 'https' ], true ) ) {
				throw new \RuntimeException( 'Schéma canonique invalide pour le logo LinkQuiver.' );
			}
			self::set_post_meta_verified(
				$linkquiver->ID,
				Meta_Keys::PRODUCT_LOGO_URL,
				set_url_scheme( SEOFLIX_PLUGIN_URL . 'assets/images/linkquiver-icon.svg', $canonical_scheme )
			);
		}

		if ( trim( (string) get_post_meta( $cuik->ID, Meta_Keys::PRODUCT_PRICING, true ) ) === '' ) {
			self::set_post_meta_verified( $cuik->ID, Meta_Keys::PRODUCT_PRICING, 'paid' );
		}

		return true;
	}

	private static function seed_default_options(): void {
		add_option( 'seoflix_terms_seed_version', self::TERMS_SEED_VERSION );
		add_option( 'seoflix_product_catalog_seed_version', 0 );
		add_option( 'seoflix_user_accounts_enabled', false );
		add_option( 'seoflix_auto_publish_ai', false );
		add_option( 'seoflix_youtube_api_key', '' );
		add_option( 'seoflix_ingestion_cron_enabled', true );
	}

	/**
	 * Bumper cette constante quand on ajoute / renomme des termes par défaut.
	 * Le re-seed se fait automatiquement à chaque page chargée (tant que la version stockée < courante).
	 */
	public const TERMS_SEED_VERSION = 4;
	public const PRODUCT_CATALOG_SEED_VERSION = 1;

	/**
	 * Re-seed idempotent (safe à exécuter à chaque page chargée).
	 */
	public static function ensure_terms_seeded(): void {
		if ( (int) get_option( 'seoflix_terms_seed_version', 0 ) >= self::TERMS_SEED_VERSION ) {
			return;
		}
		if ( ! DB_Schema::acquire_path_order_lock( 10 ) ) {
			return;
		}
		try {
			self::seed_default_terms();
			self::set_seed_version_verified();
		} catch ( \Throwable $error ) {
			error_log( 'WEAS path migration failed: ' . $error->getMessage() );
		} finally {
			DB_Schema::release_path_order_lock();
		}
	}

	public static function ensure_product_catalog_seeded(): void {
		if ( (int) get_option( 'seoflix_product_catalog_seed_version', 0 ) >= self::PRODUCT_CATALOG_SEED_VERSION ) {
			return;
		}
		if ( ! DB_Schema::acquire_path_order_lock( 10 ) ) {
			return;
		}
		try {
			if ( self::seed_product_catalog_metadata() ) {
				self::set_product_catalog_seed_version_verified();
			}
		} catch ( \Throwable $error ) {
			error_log( 'WEAS product catalog migration failed: ' . $error->getMessage() );
		} finally {
			DB_Schema::release_path_order_lock();
		}
	}
}
