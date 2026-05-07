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
		register_setting( self::OPTION_GROUP, 'seoflix_yt_daily_limit', [
			'type'              => 'integer',
			'sanitize_callback' => static fn( $v ) => max( 0, (int) $v ),
			'default'           => 1000,
		] );
		register_setting( self::OPTION_GROUP, 'seoflix_yt_hourly_limit', [
			'type'              => 'integer',
			'sanitize_callback' => static fn( $v ) => max( 0, (int) $v ),
			'default'           => 200,
		] );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}

		$api_key      = (string) get_option( 'seoflix_youtube_api_key', '' );
		$daily_limit  = (int) get_option( 'seoflix_yt_daily_limit', 1000 );
		$hourly_limit = (int) get_option( 'seoflix_yt_hourly_limit', 200 );
		$accounts_on  = (bool) get_option( 'seoflix_user_accounts_enabled', false );
		$auto_publish = (bool) get_option( 'seoflix_auto_publish_ai', false );
		$cron_on      = (bool) get_option( 'seoflix_ingestion_cron_enabled', true );

		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-youtube-api.php';
		$today_usage = \Seoflix\YouTube_API::get_today_usage();
		$hour_usage  = \Seoflix\YouTube_API::get_current_hour_usage();

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
								<input type="password" id="seoflix_youtube_api_key" name="seoflix_youtube_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off">
								<p class="description">Créer la clé sur <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console.cloud.google.com</a> (activer « YouTube Data API v3 »). Restreindre à l'IP du serveur Kimsufi. La clé reste affichée masquée par sécurité.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="seoflix_yt_hourly_limit">Limite horaire (anti-runaway)</label></th>
							<td>
								<input type="number" id="seoflix_yt_hourly_limit" name="seoflix_yt_hourly_limit" value="<?php echo esc_attr( (string) $hourly_limit ); ?>" min="0" step="10" class="small-text">
								<p class="description">Cap par heure pour bloquer un éventuel bug de boucle. Défaut 200 unités/heure = ~67 syncs maximum/heure. <strong>Cette heure : <?php echo esc_html( number_format_i18n( $hour_usage ) ); ?>/<?php echo esc_html( number_format_i18n( $hourly_limit ?: 0 ) ); ?>.</strong> Mettre 0 = pas de cap horaire.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="seoflix_yt_daily_limit">Limite quotidienne</label></th>
							<td>
								<input type="number" id="seoflix_yt_daily_limit" name="seoflix_yt_daily_limit" value="<?php echo esc_attr( (string) $daily_limit ); ?>" min="0" step="100" class="small-text">
								<p class="description">Plafond local quotidien (sur 10 000/j chez Google). Reset à minuit Pacific Time (≈ 9h heure française). <strong>Aujourd'hui : <?php echo esc_html( number_format_i18n( $today_usage ) ); ?>/<?php echo esc_html( number_format_i18n( $daily_limit ?: 0 ) ); ?>.</strong> Mettre 0 = pas de cap quotidien (seul Google bloque à 10k).<br>
								<em>Coûts indicatifs : Récupérer une chaîne = 1 unité, Sync de 50 vidéos = 3 unités.</em></p>
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
								<p class="description">Scan quotidien à 4h du matin (heure serveur). 50 dernières vidéos par chaîne, dédup auto par YouTube ID. Nécessite la clé YouTube Data API.</p>
								<?php
								$last_run = get_option( \Seoflix\Cron::LAST_RUN_OPTION, [] );
								$next     = wp_next_scheduled( \Seoflix\Cron::HOOK );
								if ( $next ) {
									echo '<p class="description"><strong>Prochaine exécution :</strong> ' . esc_html( wp_date( 'd/m/Y H:i', $next ) ) . '</p>';
								}
								if ( ! empty( $last_run['timestamp'] ) ) {
									$ago   = human_time_diff( $last_run['timestamp'], time() );
									$stats = $last_run['stats'] ?? [];
									if ( ! empty( $stats['error'] ) ) {
										printf(
											'<p class="description" style="color:#dc3545;"><strong>Dernière exécution :</strong> il y a %s — erreur : %s</p>',
											esc_html( $ago ), esc_html( $stats['error'] )
										);
									} else {
										printf(
											'<p class="description"><strong>Dernière exécution :</strong> il y a %s — %d vidéo(s) créée(s), %d ignorée(s) (déjà en base), %d chaîne(s) scannée(s).</p>',
											esc_html( $ago ),
											(int) ( $stats['created'] ?? 0 ),
											(int) ( $stats['skipped_existing'] ?? 0 ),
											(int) ( $stats['channels_done'] ?? 0 )
										);
									}
								}
								?>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button(); ?>
			</form>

			<div class="seoflix-card">
				<h2>Pages légales</h2>
				<p>Génère les 3 pages légales référencées dans le footer (Mentions légales, Politique de confidentialité, Politique d'affiliation).</p>
				<?php Legal_Pages::render_button(); ?>
			</div>
		</div>
		<?php
	}
}
