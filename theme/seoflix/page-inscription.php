<?php
/**
 * Page custom : /inscription/
 */

if ( is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/mon-parcours/' ) );
	exit;
}

if ( ! get_option( 'users_can_register' ) ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

get_header();

$register_post = admin_url( 'admin-post.php' );
$turnstile     = (string) get_option( \Seoflix\Contact::OPTION_TURNSTILE_SITE, '' );

$check_email = isset( $_GET['check'] ) && $_GET['check'] === 'email';
$has_error   = ! empty( $_GET['registration'] );
$error_code  = $has_error ? sanitize_text_field( wp_unslash( $_GET['registration'] ) ) : '';

$error_messages = [
	'session_expired'  => 'Session expirée, recharge la page.',
	'invalid_email'    => 'E-mail invalide.',
	'invalid_username' => 'Identifiant invalide. 3 à 30 caractères, lettres, chiffres, _ et - uniquement.',
	'username_exists'  => 'Cet identifiant est déjà pris.',
	'email_exists'     => 'Un compte existe déjà avec cet e-mail.',
	'rgpd_required'    => 'Tu dois accepter les conditions.',
	'rate_limit'       => 'Trop d\'inscriptions depuis cette adresse. Réessaye dans 1 heure.',
	'turnstile'        => 'Vérification anti-bot échouée. Recharge la page.',
	'create_failed'    => 'Erreur lors de la création du compte. Réessaye.',
];
$error_text = $error_messages[ $error_code ] ?? '';
?>

<div class="sx-container sx-page sx-auth-page">
	<div class="sx-auth-card">
		<h1>Créer un compte</h1>
		<p class="sx-auth-card__lead">Suis ta progression sur les parcours d'apprentissage. Gratuit, sans pub.</p>

		<?php if ( $check_email ) : ?>
			<div class="sx-notice sx-notice--ok">
				<strong>Vérifie ta boîte mail.</strong> On vient d'envoyer un lien d'activation à l'adresse fournie. Clique dessus pour finaliser ton compte (regarde aussi tes spams).
			</div>
			<p style="text-align:center; font-size:0.9rem; color:var(--sx-color-text-muted);">
				Pas reçu ? <a href="#" onclick="document.getElementById('sx-resend').classList.toggle('is-open');return false;">Renvoyer l'e-mail</a>
			</p>
			<form id="sx-resend" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none; margin-top:1rem;" class="sx-form">
				<input type="hidden" name="action" value="seoflix_resend">
				<label class="sx-form__label">
					<span>E-mail utilisé à l'inscription</span>
					<input type="email" name="user_email" required>
				</label>
				<button type="submit" class="sx-btn sx-btn--ghost sx-btn--full">Renvoyer</button>
			</form>
		<?php else : ?>

		<?php if ( $error_text ) : ?>
			<div class="sx-notice sx-notice--err"><?php echo esc_html( $error_text ); ?></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $register_post ); ?>" class="sx-form sx-form--auth">
			<input type="hidden" name="action" value="seoflix_register">
			<input type="hidden" name="_t" value="<?php echo esc_attr( (string) time() ); ?>">
			<?php wp_nonce_field( 'seoflix_register', '_seoflix_register_nonce' ); ?>

			<!-- Honeypot anti-spam -->
			<div style="position:absolute; left:-9999px;" aria-hidden="true">
				<label>Ne pas remplir<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
			</div>

			<label class="sx-form__label">
				<span>Identifiant</span>
				<input type="text" name="user_login" required autocomplete="username" minlength="3" maxlength="30">
				<small class="sx-form__hint">3 à 30 caractères, lettres, chiffres, <code>_</code>, <code>-</code>.</small>
			</label>

			<label class="sx-form__label">
				<span>E-mail</span>
				<input type="email" name="user_email" required autocomplete="email">
				<small class="sx-form__hint">Tu recevras un lien d'activation à cette adresse.</small>
			</label>

			<label class="sx-form__label sx-form__label--check">
				<input type="checkbox" name="rgpd_ok" required>
				<span>J'accepte les <a href="<?php echo esc_url( home_url( '/mentions-legales/' ) ); ?>">mentions légales</a> et la <a href="<?php echo esc_url( home_url( '/confidentialite/' ) ); ?>">politique de confidentialité</a>. <em>*</em></span>
			</label>

			<?php if ( $turnstile ) : ?>
				<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile ); ?>" data-theme="dark"></div>
				<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
			<?php endif; ?>

			<button type="submit" class="sx-btn sx-btn--full">Créer mon compte</button>
		</form>

		<div class="sx-auth-links">
			Déjà inscrit ? <a href="<?php echo esc_url( home_url( '/connexion/' ) ); ?>">Se connecter</a>
		</div>
		<?php endif; ?>
	</div>
</div>

<?php get_footer();
