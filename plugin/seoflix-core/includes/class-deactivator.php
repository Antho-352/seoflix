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
		flush_rewrite_rules();
	}
}
