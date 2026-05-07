<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		$this->load_dependencies();

		CPT::register();
		Taxonomies::register();
		FeatureFlags::init();

		Affiliate::init();
		Security::init();
		Frontend::init();
		Channel_Meta::init();
		Video_Meta::init();
		YouTube_API::init();

		// Re-seed des termes si la version a bumpé (ajout du topic « youtube » par exemple).
		add_action( 'init', [ Activator::class, 'ensure_terms_seeded' ], 20 );

		if ( is_admin() ) {
			require_once SEOFLIX_PLUGIN_DIR . 'admin/class-admin.php';
			Admin\Admin::init();
		}
	}

	private function load_dependencies(): void {
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-meta-keys.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-cpt.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-taxonomies.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-db-schema.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-feature-flags.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-importer.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-affiliate.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-security.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-frontend.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-youtube-api.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-channel-meta.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-video-meta.php';
	}
}
