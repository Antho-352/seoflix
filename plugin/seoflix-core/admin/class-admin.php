<?php
namespace Seoflix\Admin;

use Seoflix\CPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loader des modules d'administration.
 */
final class Admin {

	public static function init(): void {
		require_once SEOFLIX_PLUGIN_DIR . 'admin/class-admin-menu.php';
		require_once SEOFLIX_PLUGIN_DIR . 'admin/class-admin-dashboard.php';
		require_once SEOFLIX_PLUGIN_DIR . 'admin/class-admin-ingestion.php';
		require_once SEOFLIX_PLUGIN_DIR . 'admin/class-admin-pending.php';
		require_once SEOFLIX_PLUGIN_DIR . 'admin/class-admin-settings.php';
		require_once SEOFLIX_PLUGIN_DIR . 'admin/class-admin-affiliate-stats.php';
		require_once SEOFLIX_PLUGIN_DIR . 'admin/class-admin-columns.php';
		require_once SEOFLIX_PLUGIN_DIR . 'admin/class-product-logo.php';
		require_once SEOFLIX_PLUGIN_DIR . 'admin/class-legal-pages.php';
		require_once SEOFLIX_PLUGIN_DIR . 'admin/class-admin-rgpd.php';
		require_once SEOFLIX_PLUGIN_DIR . 'admin/class-admin-seo-tools.php';
		require_once SEOFLIX_PLUGIN_DIR . 'admin/class-admin-homepage.php';
		require_once SEOFLIX_PLUGIN_DIR . 'admin/class-admin-newsletter.php';

		Admin_Menu::init();
		Admin_Ingestion::init();
		Admin_Pending::init();
		Admin_Settings::init();
		Admin_Dashboard::init();
		Admin_Affiliate_Stats::init();
		Admin_Columns::init();
		Product_Logo::init();
		Legal_Pages::init();
		Admin_Rgpd::init();
		Admin_SEO_Tools::init();
		Admin_Homepage::init();
		Admin_Newsletter::init();

		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	public static function enqueue_assets( string $hook ): void {
		// Charger CSS uniquement sur les pages Seoflix.
		if ( strpos( $hook, 'seoflix' ) === false ) {
			return;
		}
		$css = '
			.seoflix-wrap { max-width: 1100px; }
			.seoflix-card { background: #fff; padding: 1.25rem 1.5rem; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 1rem; }
			.seoflix-card h2 { margin-top: 0; }
			.seoflix-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin: 1rem 0 2rem; }
			.seoflix-stat { background: #fff; padding: 1rem 1.25rem; border: 1px solid #ccd0d4; border-radius: 4px; }
			.seoflix-stat .num { font-size: 2rem; font-weight: 700; line-height: 1; color: #FF2D3F; }
			.seoflix-stat .label { color: #666; font-size: 0.85rem; margin-top: 0.25rem; }
			.seoflix-report { padding: 1rem; border-radius: 4px; margin: 1rem 0; }
			.seoflix-report.ok { background: #d4edda; border-left: 4px solid #28a745; }
			.seoflix-report.err { background: #f8d7da; border-left: 4px solid #dc3545; }
			.seoflix-pending-row { display: grid; grid-template-columns: 160px 1fr auto; gap: 1rem; padding: 1rem; border-bottom: 1px solid #eee; align-items: start; }
			.seoflix-pending-row img { width: 160px; height: 90px; object-fit: cover; border-radius: 4px; }
			.seoflix-pending-row .meta { color: #666; font-size: 0.85rem; margin-top: 0.5rem; }
			.seoflix-pending-row .actions { display: flex; gap: 0.5rem; flex-direction: column; }
		';
		wp_register_style( 'seoflix-admin-inline', false );
		wp_enqueue_style( 'seoflix-admin-inline' );
		wp_add_inline_style( 'seoflix-admin-inline', $css );
	}
}
