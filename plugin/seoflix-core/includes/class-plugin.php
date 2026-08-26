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
		// Priorité 30 : les CPT/taxonomies (10) et le re-seed (20) sont prêts.
		add_action( 'init', [ DB_Schema::class, 'maybe_upgrade' ], 30 );
		FeatureFlags::init();
		Video_Comments::init();
		Focus::init();

		Affiliate::init();
		Security::init();
		Frontend::init();
		Channel_Meta::init();
		Video_Meta::init();
		YouTube_API::init();
		Cron::init();
		Contact::init();
		SEO::init();
		Path_Order::init();
		User_Accounts::init();
		Auth_Pages::init();
		Custom_Auth::init();
		Newsletter::init();

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
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-video-comments.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-focus.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-importer.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-affiliate.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-security.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-business-finder.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-frontend.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-youtube-api.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-channel-meta.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-video-meta.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-cron.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-contact.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-seo.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-homepage.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-path-order.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-user-accounts.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-auth-pages.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-custom-auth.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-newsletter.php';
	}
}
