<?php
namespace Seoflix\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page admin Seoflix → Réglages.
 */
final class Admin_Settings {

	// Un option_group par formulaire pour éviter que la soumission d'un formulaire
	// efface les options gérées par un autre formulaire (limitation Settings API).
	private const GROUP_YOUTUBE  = 'seoflix_settings_youtube';
	private const GROUP_BEHAVIOR = 'seoflix_settings_behavior';
	private const GROUP_CONTACT  = 'seoflix_settings_contact';
	private const PAGE_SLUG      = 'seoflix-settings';

	public static function init(): void {
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
	}

	public static function register_settings(): void {
		// YouTube Data API
		register_setting( self::GROUP_YOUTUBE, 'seoflix_youtube_api_key', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );
		register_setting( self::GROUP_YOUTUBE, 'seoflix_yt_daily_limit', [
			'type'              => 'integer',
			'sanitize_callback' => static fn( $v ) => max( 0, (int) $v ),
			'default'           => 1000,
		] );
		register_setting( self::GROUP_YOUTUBE, 'seoflix_yt_hourly_limit', [
			'type'              => 'integer',
			'sanitize_callback' => static fn( $v ) => max( 0, (int) $v ),
			'default'           => 200,
		] );

		// Comportement
		register_setting( self::GROUP_BEHAVIOR, 'seoflix_user_accounts_enabled', [
			'type'              => 'boolean',
			'sanitize_callback' => static fn( $v ) => (bool) $v,
			'default'           => false,
		] );
		register_setting( self::GROUP_BEHAVIOR, 'seoflix_auto_publish_ai', [
			'type'              => 'boolean',
			'sanitize_callback' => static fn( $v ) => (bool) $v,
			'default'           => false,
		] );
		register_setting( self::GROUP_BEHAVIOR, 'seoflix_ingestion_cron_enabled', [
			'type'              => 'boolean',
			'sanitize_callback' => static fn( $v ) => (bool) $v,
			'default'           => true,
		] );
		register_setting( self::GROUP_BEHAVIOR, 'seoflix_lock_videos_to_users', [
			'type'              => 'boolean',
			'sanitize_callback' => static fn( $v ) => (bool) $v,
			'default'           => false,
		] );
		register_setting( self::GROUP_BEHAVIOR, 'seoflix_default_fallback_products', [
			'type'              => 'array',
			'sanitize_callback' => static function ( $v ) {
				if ( ! is_array( $v ) ) {
					return [];
				}
				return array_values( array_filter( array_map( 'intval', $v ) ) );
			},
			'default'           => [],
		] );

		// Contact
		register_setting( self::GROUP_CONTACT, \Seoflix\Contact::OPTION_RECIPIENT, [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_email',
			'default'           => '',
		] );
		register_setting( self::GROUP_CONTACT, \Seoflix\Contact::OPTION_TURNSTILE_SITE, [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );
		register_setting( self::GROUP_CONTACT, \Seoflix\Contact::OPTION_TURNSTILE_SECRET, [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
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
		$lock_videos  = (bool) get_option( 'seoflix_lock_videos_to_users', false );
		$fallback_ids = (array) get_option( 'seoflix_default_fallback_products', [] );

		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-youtube-api.php';
		$today_usage = \Seoflix\YouTube_API::get_today_usage();
		$hour_usage  = \Seoflix\YouTube_API::get_current_hour_usage();

		?>
		<div class="wrap seoflix-wrap">
			<h1>Seoflix — Réglages</h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP_YOUTUBE ); ?>

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

				<?php submit_button( 'Enregistrer YouTube' ); ?>
			</form>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP_BEHAVIOR ); ?>
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
							<th scope="row">Verrouiller les vidéos</th>
							<td>
								<label>
									<input type="checkbox" name="seoflix_lock_videos_to_users" value="1" <?php checked( $lock_videos ); ?>>
									Réserver le visionnage aux utilisateurs connectés
								</label>
								<p class="description">Si activé (et que les comptes utilisateurs sont activés), les visiteurs non connectés voient le titre, les badges et les produits liés mais le player vidéo est remplacé par un encart « Connecte-toi pour regarder ». Lead-gen friendly.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="seoflix_default_fallback_products">Produits par défaut (sidebar vidéo)</label></th>
							<td>
								<?php
								$all_products = get_posts( [
									'post_type'      => 'seoflix_product',
									'post_status'    => 'publish',
									'posts_per_page' => -1,
									'orderby'        => 'title',
									'order'          => 'ASC',
								] );
								?>
								<select id="seoflix_default_fallback_products" name="seoflix_default_fallback_products[]" multiple size="8" style="min-width:360px;">
									<?php foreach ( $all_products as $p ) : ?>
										<option value="<?php echo (int) $p->ID; ?>" <?php echo in_array( $p->ID, array_map( 'intval', $fallback_ids ), true ) ? 'selected' : ''; ?>>
											<?php echo esc_html( $p->post_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Sélectionne 1 à 3 produits (Cmd/Ctrl + clic pour multi-sélection). Affichés sur les pages vidéos qui n'ont aucun produit lié, sous le texte « ces outils pourraient t'intéresser ».</p>
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

				<?php submit_button( 'Enregistrer le comportement' ); ?>
			</form>

			<div class="seoflix-card">
				<h2>Formulaire de contact</h2>
				<form method="post" action="options.php">
					<?php settings_fields( self::GROUP_CONTACT ); ?>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( \Seoflix\Contact::OPTION_RECIPIENT ); ?>">E-mail destinataire</label></th>
							<td>
								<input type="email" id="<?php echo esc_attr( \Seoflix\Contact::OPTION_RECIPIENT ); ?>" name="<?php echo esc_attr( \Seoflix\Contact::OPTION_RECIPIENT ); ?>" value="<?php echo esc_attr( (string) get_option( \Seoflix\Contact::OPTION_RECIPIENT, '' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
								<p class="description">Adresse qui reçoit les messages de <code>/contact/</code> et les notifications d'inscription. Si vide, fallback sur <code><?php echo esc_html( get_option( 'admin_email' ) ); ?></code>. L'envoi passe par <strong>FluentSMTP</strong> si le plugin est actif.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( \Seoflix\Contact::OPTION_TURNSTILE_SITE ); ?>">Cloudflare Turnstile — Site Key</label></th>
							<td>
								<input type="text" id="<?php echo esc_attr( \Seoflix\Contact::OPTION_TURNSTILE_SITE ); ?>" name="<?php echo esc_attr( \Seoflix\Contact::OPTION_TURNSTILE_SITE ); ?>" value="<?php echo esc_attr( (string) get_option( \Seoflix\Contact::OPTION_TURNSTILE_SITE, '' ) ); ?>" class="regular-text code" placeholder="0x4AAAAAAA...">
								<p class="description">Crée gratuitement une clé sur <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">Cloudflare Turnstile</a> (anti-bot privacy-friendly, pas de CAPTCHA visuel). Laisse vide pour désactiver.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( \Seoflix\Contact::OPTION_TURNSTILE_SECRET ); ?>">Cloudflare Turnstile — Secret Key</label></th>
							<td>
								<input type="password" id="<?php echo esc_attr( \Seoflix\Contact::OPTION_TURNSTILE_SECRET ); ?>" name="<?php echo esc_attr( \Seoflix\Contact::OPTION_TURNSTILE_SECRET ); ?>" value="<?php echo esc_attr( (string) get_option( \Seoflix\Contact::OPTION_TURNSTILE_SECRET, '' ) ); ?>" class="regular-text code" autocomplete="off">
								<p class="description">Donnée par Cloudflare en même temps que la Site Key. Utilisée côté serveur pour valider le token.</p>
							</td>
						</tr>
					</table>
					<?php submit_button( 'Enregistrer' ); ?>
				</form>
			</div>

			<div class="seoflix-card">
				<h2>Pages légales &amp; contact</h2>
				<p>Génère les 4 pages référencées dans le footer (Mentions légales, Politique de confidentialité, Politique d'affiliation, Contact).</p>
				<?php Legal_Pages::render_button(); ?>
			</div>
		</div>
		<?php
	}
}
