<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Deactivator {

	public static function deactivate(): void {
		// Préserver les données : ne PAS supprimer les CPT, taxonomies, tables custom.
		// Seul l'utilitaire `uninstall.php` supprimerait les données (à implémenter si besoin).
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-cron.php';
		Cron::unschedule();
		wp_clear_scheduled_hook( 'seoflix_purge_affiliate_clicks' );
		wp_clear_scheduled_hook( 'seoflix_purge_affiliate_clicks', [ 'catchup' ] );
		flush_rewrite_rules();
	}
}
