<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {

	public static function activate(): void {
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-cpt.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-taxonomies.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-db-schema.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-affiliate.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-frontend.php';

		CPT::register();
		Taxonomies::register();
		DB_Schema::install();
		Affiliate::register_rewrite();
		Frontend::register_rewrite();

		self::seed_default_terms();
		self::seed_default_options();
		update_option( 'seoflix_terms_seed_version', self::TERMS_SEED_VERSION );

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

		$paths = [
			'apprendre-le-seo'             => 'Apprendre le SEO',
			'apprendre-le-netlinking'      => 'Apprendre le netlinking',
			'apprendre-la-vente-de-liens'  => 'Apprendre la vente de liens',
			'apprendre-l-affiliation'      => "Apprendre l'affiliation",
			'apprendre-la-vente-de-leads'  => 'Apprendre la vente de leads',
			'apprendre-le-business'        => 'Apprendre le business',
		];
		self::insert_terms( 'seoflix_path', $paths );

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

	private static function seed_default_options(): void {
		add_option( 'seoflix_db_version', SEOFLIX_DB_VERSION );
		add_option( 'seoflix_terms_seed_version', self::TERMS_SEED_VERSION );
		add_option( 'seoflix_user_accounts_enabled', false );
		add_option( 'seoflix_auto_publish_ai', false );
		add_option( 'seoflix_youtube_api_key', '' );
		add_option( 'seoflix_ingestion_cron_enabled', true );
	}

	/**
	 * Bumper cette constante quand on ajoute / renomme des termes par défaut.
	 * Le re-seed se fait automatiquement à chaque page chargée (tant que la version stockée < courante).
	 */
	public const TERMS_SEED_VERSION = 2;

	/**
	 * Re-seed idempotent (safe à exécuter à chaque page chargée).
	 */
	public static function ensure_terms_seeded(): void {
		if ( (int) get_option( 'seoflix_terms_seed_version', 0 ) >= self::TERMS_SEED_VERSION ) {
			return;
		}
		self::seed_default_terms();
		update_option( 'seoflix_terms_seed_version', self::TERMS_SEED_VERSION );
	}
}
