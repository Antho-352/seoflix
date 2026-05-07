<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FeatureFlags {

	public static function init(): void {
		// Pas de hook particulier en V1 — la classe expose des méthodes statiques utilitaires.
	}

	public static function user_accounts_enabled(): bool {
		return (bool) get_option( 'seoflix_user_accounts_enabled', false );
	}

	public static function auto_publish_ai_enabled(): bool {
		return (bool) get_option( 'seoflix_auto_publish_ai', false );
	}

	public static function ingestion_cron_enabled(): bool {
		return (bool) get_option( 'seoflix_ingestion_cron_enabled', true );
	}
}

/**
 * Helper global pour usage dans les templates.
 */
function seoflix_user_accounts_enabled(): bool {
	return FeatureFlags::user_accounts_enabled();
}
