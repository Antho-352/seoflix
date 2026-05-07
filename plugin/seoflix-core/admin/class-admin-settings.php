<?php
namespace Seoflix\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page admin Seoflix → Réglages.
 */
final class Admin_Settings {

	private const OPTION_GROUP = 'seoflix_settings';
	private const PAGE_SLUG    = 'seoflix-settings';

	public static function init(): void {
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
	}

	public static function register_settings(): void {
		register_setting( self::OPTION_GROUP, 'seoflix_youtube_api_key', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );
		register_setting( self::OPTION_GROUP, 'seoflix_user_accounts_enabled', [
			'type'              => 'boolean',
			'sanitize_callback' => static fn( $v ) => (bool) $v,
			'default'           => false,
		] );
		register_setting( self::OPTION_GROUP, 'seoflix_auto_publish_ai', [
			'type'              => 'boolean',
			'sanitize_callback' => static fn( $v ) => (bool) $v,
			'default'           => false,
		] );
		register_setting( self::OPTION_GROUP, 'seoflix_ingestion_cron_enabled', [
			'type'              => 'boolean',
			'sanitize_callback' => static fn( $v ) => (bool) $v,
			'default'           => true,
		] );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}

		$api_key      = (string) get_option( 'seoflix_youtube_api_key', '' );
		$accounts_on  = (bool) get_option( 'seoflix_user_accounts_enabled', false );
		$auto_publish = (bool) get_option( 'seoflix_auto_publish_ai', false );
		$cron_on      = (bool) get_option( 'seoflix_ingestion_cron_enabled', true );

		?>
		<div class="wrap seoflix-wrap">
			<h1>Seoflix — Réglages</h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<div class="seoflix-card">
					<h2>YouTube Data API</h2>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="seoflix_youtube_api_key">Clé API YouTube Data v3</label></th>
							<td>
								<input type="text" id="seoflix_youtube_api_key" name="seoflix_youtube_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off">
								<p class="description">Pour activer l'ingestion automatique. Créer la clé sur <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console.cloud.google.com</a> (activer « YouTube Data API v3 »). Restreindre à l'IP du serveur.</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="seoflix-card">
					<h2>Comportement</h2>
					<table class="form-table">
						<tr>
							<th scope="row">Comptes utilisateurs (V2)</th>
							<td>
								<label>
									<input type="checkbox" name="seoflix_user_accounts_enabled" value="1" <?php checked( $accounts_on ); ?>>
									Activer les comptes utilisateurs (favoris, historique)
								</label>
								<p class="description">V1 = site 100 % public, aucun compte requis. Activer cette option ne déploie pas de fonctionnalité supplémentaire en V1 (les pages favoris/historique restent désactivées tant que les templates V2 ne sont pas livrés).</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Auto-publication IA</th>
							<td>
								<label>
									<input type="checkbox" name="seoflix_auto_publish_ai" value="1" <?php checked( $auto_publish ); ?>>
									Publier automatiquement les vidéos importées sans validation manuelle
								</label>
								<p class="description">Désactivé par défaut. Si activé, les vidéos importées passent directement en statut « publié » sans review.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Cron d'ingestion</th>
							<td>
								<label>
									<input type="checkbox" name="seoflix_ingestion_cron_enabled" value="1" <?php checked( $cron_on ); ?>>
									Activer le scan quotidien des chaînes
								</label>
								<p class="description">Scan quotidien à 4h du matin (heure serveur). Nécessite la clé YouTube Data API.</p>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
