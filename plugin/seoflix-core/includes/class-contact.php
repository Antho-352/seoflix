<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module contact :
 *  - CPT `seoflix_message` (privé, pour stocker les messages)
 *  - Shortcode [seoflix_contact_form] qui rend le formulaire
 *  - Handler admin-post `seoflix_contact_submit` qui valide, stocke, envoie 2 mails (admin + auto-reply)
 *  - Hook user_register pour envoi mail à l'admin lors d'une inscription (V2 dormant)
 *
 * Les e-mails sortants utilisent wp_mail() — FluentSMTP intercepte et route via SMTP.
 * Anti-spam minimaliste : honeypot + nonce + rate-limiting transient (1 envoi / 60s / IP).
 */
final class Contact {

	public const CPT                 = 'seoflix_message';
	public const NONCE               = 'seoflix_contact';
	public const OPTION_RECIPIENT    = 'seoflix_contact_recipient';
	public const OPTION_TURNSTILE_SITE   = 'seoflix_turnstile_site_key';
	public const OPTION_TURNSTILE_SECRET = 'seoflix_turnstile_secret_key';

	public static function init(): void {
		add_action( 'init',                            [ self::class, 'register_cpt' ] );
		add_shortcode( 'seoflix_contact_form',         [ self::class, 'render_shortcode' ] );
		add_action( 'admin_post_nopriv_seoflix_contact_submit', [ self::class, 'handle_submit' ] );
		add_action( 'admin_post_seoflix_contact_submit',        [ self::class, 'handle_submit' ] );
		// NB : on_user_register désactivé car Custom_Auth::handle_register envoie déjà
		// la notification admin. Garder ce hook produirait un doublon.
		// add_action( 'user_register', [ self::class, 'on_user_register' ] );
	}

	public static function register_cpt(): void {
		register_post_type( self::CPT, [
			'labels'              => [
				'name'          => 'Messages',
				'singular_name' => 'Message',
				'menu_name'     => 'Messages',
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'seoflix',
			'show_in_rest'        => false,
			'supports'            => [ 'title', 'editor', 'custom-fields' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'capabilities'        => [
				'create_posts' => 'do_not_allow', // pas de création manuelle
			],
		] );
	}

	public static function get_recipient(): string {
		$opt = (string) get_option( self::OPTION_RECIPIENT, '' );
		return $opt ?: get_option( 'admin_email' );
	}

	public static function render_shortcode( $atts = [], $content = null ): string {
		$success = isset( $_GET['seoflix_contact'] ) && $_GET['seoflix_contact'] === 'sent';
		$error   = isset( $_GET['seoflix_contact_error'] ) ? sanitize_text_field( wp_unslash( $_GET['seoflix_contact_error'] ) ) : '';

		ob_start();
		?>
		<div class="sx-contact-form" id="sx-contact-form">
			<?php if ( $success ) : ?>
				<div class="sx-contact-form__notice sx-contact-form__notice--ok" id="sx-contact-notice">
					<strong>Message envoyé.</strong> Une copie de confirmation t'a été envoyée. Réponse sous 48h ouvrées.
				</div>
			<?php endif; ?>
			<?php if ( $error ) : ?>
				<div class="sx-contact-form__notice sx-contact-form__notice--err" id="sx-contact-notice">
					<strong>Erreur :</strong> <?php echo esc_html( $error ); ?>
				</div>
			<?php endif; ?>
			<?php if ( $success || $error ) : ?>
				<script>document.getElementById('sx-contact-notice')?.scrollIntoView({behavior:'smooth',block:'center'});</script>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sx-form">
				<input type="hidden" name="action" value="seoflix_contact_submit">
				<?php wp_nonce_field( self::NONCE, '_seoflix_contact_nonce' ); ?>
				<input type="hidden" name="_t" value="<?php echo esc_attr( (string) time() ); ?>">

				<!-- Honeypot anti-spam (champ caché que les bots remplissent) -->
				<div style="position:absolute; left:-9999px;" aria-hidden="true">
					<label>Ne pas remplir<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
				</div>
				<?php
				$turnstile_site = (string) get_option( self::OPTION_TURNSTILE_SITE, '' );
				if ( $turnstile_site ) :
					?>
					<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile_site ); ?>" data-theme="dark"></div>
					<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
				<?php endif; ?>

				<label class="sx-form__label">
					<span>Nom <em>*</em></span>
					<input type="text" name="name" required maxlength="100" autocomplete="name">
				</label>

				<label class="sx-form__label">
					<span>E-mail <em>*</em></span>
					<input type="email" name="email" required maxlength="200" autocomplete="email">
				</label>

				<label class="sx-form__label">
					<span>Sujet <em>*</em></span>
					<input type="text" name="subject" required maxlength="200">
				</label>

				<label class="sx-form__label">
					<span>Message <em>*</em></span>
					<textarea name="message" required rows="6" maxlength="5000"></textarea>
				</label>

				<label class="sx-form__label sx-form__label--check">
					<input type="checkbox" name="rgpd_ok" required>
					<span>J'accepte que ces données soient utilisées pour répondre à ma demande, conformément à la <a href="<?php echo esc_url( home_url( '/confidentialite/' ) ); ?>">politique de confidentialité</a>. <em>*</em></span>
				</label>

				<button type="submit" class="sx-btn">Envoyer le message</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function handle_submit(): void {
		$page_url = wp_get_referer() ?: home_url( '/contact/' );

		if ( ! isset( $_POST['_seoflix_contact_nonce'] ) || ! wp_verify_nonce( $_POST['_seoflix_contact_nonce'], self::NONCE ) ) {
			self::redirect_error( $page_url, 'Session expirée, recharge la page et réessaye.' );
		}

		// Honeypot
		if ( ! empty( $_POST['website'] ) ) {
			// Silencieusement OK pour ne pas indiquer aux bots qu'on les a détectés
			wp_safe_redirect( add_query_arg( 'seoflix_contact', 'sent', $page_url ) );
			exit;
		}

		// Honeypot temporel : un humain ne remplit pas le form en moins de 3 secondes
		$ts = isset( $_POST['_t'] ) ? (int) $_POST['_t'] : 0;
		if ( $ts && ( time() - $ts ) < 3 ) {
			// Silencieux, comme le honeypot champ
			wp_safe_redirect( add_query_arg( 'seoflix_contact', 'sent', $page_url ) );
			exit;
		}

		// Rate limit avec backoff exponentiel : 30s → 60s → 120s → 300s
		$ip          = \Seoflix\Security::client_ip();
		$rate_key    = 'seoflix_contact_rate_' . md5( $ip );
		$attempt_key = 'seoflix_contact_attempts_' . md5( $ip );
		$is_admin    = current_user_can( 'manage_options' );
		if ( ! $is_admin && get_transient( $rate_key ) ) {
			$attempts = (int) get_transient( $attempt_key );
			$delay    = min( 300, 30 * ( 2 ** $attempts ) );
			self::redirect_error( $page_url, sprintf( 'Trop de tentatives. Réessaye dans %d secondes.', $delay ) );
		}

		// Sanitization + protection email-header injection (CRLF strip systématique)
		$crlf_strip = static fn( $s ) => str_replace( [ "\r", "\n", "\0" ], '', (string) $s );

		$name    = $crlf_strip( isset( $_POST['name'] )    ? sanitize_text_field( wp_unslash( $_POST['name'] ) )    : '' );
		$email   = $crlf_strip( isset( $_POST['email'] )   ? sanitize_email( wp_unslash( $_POST['email'] ) )        : '' );
		$subject = $crlf_strip( isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '' );
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$rgpd    = ! empty( $_POST['rgpd_ok'] );

		if ( ! $name || ! $email || ! $subject || ! $message ) {
			self::redirect_error( $page_url, 'Tous les champs sont requis.' );
		}
		if ( ! is_email( $email ) ) {
			self::redirect_error( $page_url, 'Adresse e-mail invalide.' );
		}
		if ( ! $rgpd ) {
			self::redirect_error( $page_url, 'Tu dois accepter la politique de confidentialité.' );
		}
		if ( strlen( $message ) < 10 ) {
			self::redirect_error( $page_url, 'Message trop court (min 10 caractères).' );
		}

		// Validation Cloudflare Turnstile (si configuré)
		$turnstile_secret = (string) get_option( self::OPTION_TURNSTILE_SECRET, '' );
		if ( $turnstile_secret ) {
			$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
			if ( ! $token || ! self::verify_turnstile( $token, $turnstile_secret, $ip ) ) {
				self::redirect_error( $page_url, 'Échec de la vérification anti-bot. Recharge la page.' );
			}
		}

		// Détection messages dupliqués (anti-spam aggressive)
		$msg_hash = md5( mb_strtolower( $message ) );
		$dup_key  = 'seoflix_contact_dup_' . $msg_hash;
		if ( ! $is_admin && get_transient( $dup_key ) ) {
			// On simule le succès pour ne pas signaler aux bots qu'on les a détectés
			wp_safe_redirect( add_query_arg( 'seoflix_contact', 'sent', $page_url ) );
			exit;
		}
		set_transient( $dup_key, 1, HOUR_IN_SECONDS );

		// Stockage en CPT — anti log-injection : strip CRLF sur les valeurs $_SERVER
		$ua_raw      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 255 ) : '';
		$referer_raw = isset( $_SERVER['HTTP_REFERER'] )    ? substr( (string) wp_get_referer(), 0, 255 ) : '';

		$post_id = wp_insert_post( [
			'post_type'    => self::CPT,
			'post_title'   => sprintf( '[%s] %s', $name, $subject ),
			'post_content' => $message,
			'post_status'  => 'private',
			'meta_input'   => [
				'_seoflix_contact_name'    => $name,
				'_seoflix_contact_email'   => $email,
				'_seoflix_contact_subject' => $subject,
				'_seoflix_contact_ip_hash' => $ip ? hash( 'sha256', $ip . wp_salt() ) : '',
				'_seoflix_contact_ua'      => sanitize_text_field( $crlf_strip( $ua_raw ) ),
				'_seoflix_contact_referer' => esc_url_raw( $crlf_strip( $referer_raw ) ),
			],
		], true );

		if ( is_wp_error( $post_id ) ) {
			self::redirect_error( $page_url, 'Erreur serveur, réessaye plus tard.' );
		}

		if ( ! $is_admin ) {
			$attempts = (int) get_transient( $attempt_key );
			$delay    = min( 300, 30 * ( 2 ** $attempts ) );
			set_transient( $rate_key, 1, $delay );
			set_transient( $attempt_key, $attempts + 1, DAY_IN_SECONDS );
		}

		// Stocke les mails à envoyer après la redirection (évite le freeze côté UX
		// si SMTP timeout ou FluentSMTP pas encore configuré)
		$site_name = get_bloginfo( 'name' );
		$mails = [
			[
				'to'      => self::get_recipient(),
				'subject' => sprintf( '[%s] Nouveau message : %s', $site_name, $subject ),
				'body'    => "Nouveau message reçu depuis le formulaire de contact.\n\n"
					. "De : {$name} <{$email}>\n"
					. "Sujet : {$subject}\n"
					. "Date : " . current_time( 'd/m/Y H:i' ) . "\n\n"
					. "Message :\n" . str_repeat( '-', 60 ) . "\n"
					. $message . "\n"
					. str_repeat( '-', 60 ) . "\n\n"
					. "Voir dans l'admin : " . admin_url( 'post.php?post=' . $post_id . '&action=edit' ) . "\n",
				'headers' => [ 'Reply-To: ' . sprintf( '%s <%s>', $name, $email ) ],
			],
			[
				'to'      => $email,
				'subject' => sprintf( "[%s] Confirmation de réception", $site_name ),
				'body'    => "Bonjour {$name},\n\n"
					. "J'ai bien reçu ton message et te répondrai sous 48h ouvrées.\n\n"
					. "Pour mémoire, voici ton message :\n\n"
					. "Sujet : {$subject}\n"
					. str_repeat( '-', 60 ) . "\n"
					. $message . "\n"
					. str_repeat( '-', 60 ) . "\n\n"
					. "Si tu n'es pas à l'origine de ce message, ignore simplement cet e-mail.\n\n"
					. "À bientôt,\n"
					. "Anthony — {$site_name}\n"
					. home_url(),
				'headers' => [],
			],
		];

		// Redirige immédiatement pour ne PAS faire attendre l'utilisateur
		// le temps que SMTP/sendmail réponde.
		wp_safe_redirect( add_query_arg( 'seoflix_contact', 'sent', $page_url ) );

		// Si dispo (PHP-FPM), termine la réponse HTTP avant l'envoi des mails
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}

		// Envoi des mails (peut prendre quelques secondes selon le SMTP)
		foreach ( $mails as $m ) {
			wp_mail( $m['to'], $m['subject'], $m['body'], $m['headers'] );
		}
		exit;
	}

	public static function on_user_register( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf( '[%s] Nouvelle inscription : %s', $site_name, $user->user_email );
		$body      = "Nouvel utilisateur inscrit sur {$site_name}.\n\n"
			. "Login : {$user->user_login}\n"
			. "E-mail : {$user->user_email}\n"
			. "Date : " . current_time( 'd/m/Y H:i' ) . "\n"
			. "IP : " . ( $_SERVER['REMOTE_ADDR'] ?? 'inconnue' ) . "\n\n"
			. "Profil : " . admin_url( 'user-edit.php?user_id=' . $user_id );
		wp_mail( self::get_recipient(), $subject, $body );
	}

	private static function redirect_error( string $url, string $message ): void {
		wp_safe_redirect( add_query_arg( 'seoflix_contact_error', rawurlencode( $message ), $url ) );
		exit;
	}

	/**
	 * Vérifie un token Cloudflare Turnstile côté serveur.
	 */
	private static function verify_turnstile( string $token, string $secret, string $ip ): bool {
		$response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
			'timeout' => 8,
			'body'    => [
				'secret'   => $secret,
				'response' => $token,
				'remoteip' => $ip,
			],
		] );
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return ! empty( $body['success'] );
	}
}
