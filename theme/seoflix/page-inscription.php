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

$register_post = \Seoflix\Custom_Auth::frontend_action_url( 'register' );
$turnstile     = (string) get_option( \Seoflix\Contact::OPTION_TURNSTILE_SITE, '' );

$check_email = isset( $_GET['check'] ) && $_GET['check'] === 'email';
$has_error   = ! empty( $_GET['registration'] );
$error_code  = $has_error ? sanitize_text_field( wp_unslash( $_GET['registration'] ) ) : '';

$error_messages = [
	'session_expired'  => 'Session expirée, recharge la page.',
	'invalid_email'    => 'E-mail invalide.',
	'invalid_username' => 'Identifiant invalide. 3 à 30 caractères, lettres, chiffres, _ et - uniquement.',
	'taken'            => "Cet identifiant ou cet e-mail est déjà associé à un compte. Si c'est toi, va sur la page Connexion.",
	'rgpd_required'    => 'Tu dois accepter les conditions.',
	'rate_limit'       => "Trop d'inscriptions depuis cette adresse. Réessaye dans 1 heure.",
	'turnstile'        => 'Vérification anti-bot échouée. Recharge la page.',
	'create_failed'    => 'Erreur lors de la création du compte. Réessaye.',
	'pwd_too_short'    => 'Mot de passe trop court (12 caractères minimum).',
	'pwd_mismatch'     => 'Les deux mots de passe ne correspondent pas.',
];
$error_text = $error_messages[ $error_code ] ?? '';
?>

<div class="sx-container sx-page sx-auth-page">
	<?php if ( $check_email ) : ?>
		<div class="sx-auth-card">
			<div style="text-align:center; font-size:3rem; margin-bottom:0.5rem;">📧</div>
			<h1 style="text-align:center;">Vérifie ta boîte mail</h1>
			<p class="sx-auth-card__lead" style="text-align:center;">
				On vient de t'envoyer un <strong>lien d'activation</strong>. Clique dessus pour valider ton compte et te connecter.
			</p>
			<div class="sx-notice sx-notice--ok" style="margin-top: 1.5rem;">
				<strong>Important :</strong>
				<ul style="margin: 0.5rem 0 0 1.2rem; padding: 0;">
					<li>Le lien est valable <strong>7 jours</strong></li>
					<li>Pense à vérifier tes <strong>spams</strong></li>
					<li>Tu ne pourras pas te connecter tant que ton compte n'est pas activé</li>
				</ul>
			</div>
			<div class="sx-auth-links">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">← Retour à l'accueil</a>
			</div>
		</div>
	<?php else : ?>
	<div class="sx-auth-card">
		<h1>Créer un compte</h1>
		<p class="sx-auth-card__lead">Suis ta progression sur les parcours d'apprentissage. Gratuit, sans pub.</p>

		<?php if ( $error_text ) : ?>
			<div class="sx-notice sx-notice--err"><?php echo esc_html( $error_text ); ?></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $register_post ); ?>" class="sx-form sx-form--auth">
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
			</label>

			<label class="sx-form__label">
				<span>Mot de passe</span>
				<span class="sx-input-pwd">
					<input type="password" name="pwd" required autocomplete="new-password" minlength="12">
					<button type="button" class="sx-input-pwd__toggle" aria-label="Afficher le mot de passe" tabindex="-1">
						<svg class="sx-eye sx-eye--show" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
						<svg class="sx-eye sx-eye--hide" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
					</button>
				</span>
				<small class="sx-form__hint">12 caractères minimum.</small>
			</label>

			<label class="sx-form__label">
				<span>Confirme le mot de passe</span>
				<span class="sx-input-pwd">
					<input type="password" name="pwd2" required autocomplete="new-password" minlength="12">
					<button type="button" class="sx-input-pwd__toggle" aria-label="Afficher le mot de passe" tabindex="-1">
						<svg class="sx-eye sx-eye--show" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
						<svg class="sx-eye sx-eye--hide" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
					</button>
				</span>
			</label>

			<label class="sx-form__label sx-form__label--check">
				<input type="checkbox" name="rgpd_ok" required>
				<span>J'accepte les <a href="<?php echo esc_url( home_url( '/mentions-legales/' ) ); ?>">mentions légales</a> et la <a href="<?php echo esc_url( home_url( '/confidentialite/' ) ); ?>">politique de confidentialité</a>. <em>*</em></span>
			</label>

			<label class="sx-form__label sx-form__label--check">
				<input type="checkbox" name="newsletter_optin" value="1">
				<span>Je m'inscris aussi à la newsletter WEAS (~2 envois/mois, désinscription en 1 clic).</span>
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
	</div>
	<?php endif; ?>
</div>

<?php get_footer();
