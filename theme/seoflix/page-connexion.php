<?php
/**
 * Page custom : /connexion/
 */

if ( is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/mon-parcours/' ) );
	exit;
}

get_header();

$login_post   = \Seoflix\Custom_Auth::frontend_action_url( 'login' );
$redirect_to  = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/mon-parcours/' );
$registered   = isset( $_GET['registered'] );
$reset        = isset( $_GET['reset'] );
$activated    = isset( $_GET['activated'] ) ? sanitize_text_field( wp_unslash( $_GET['activated'] ) ) : '';
$has_error    = ! empty( $_GET['login'] );
$error_code   = $has_error ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';
$turnstile    = (string) get_option( \Seoflix\Contact::OPTION_TURNSTILE_SITE, '' );

$error_messages = [
	'failed'           => 'Identifiants incorrects.',
	'empty_fields'     => 'Saisis ton e-mail/identifiant et ton mot de passe.',
	'session_expired'  => 'Session expirée, recharge la page.',
	'seoflix_locked'   => 'Trop d\'échecs récents depuis ton IP. Réessaye dans 30 minutes.',
	'turnstile'        => 'Vérification anti-bot échouée. Recharge la page.',
	'seoflix_pending'  => 'Compte non activé. Vérifie ta boîte mail (et tes spams) — un lien d\'activation t\'a été envoyé.',
];
$error_text = $error_messages[ $error_code ] ?? '';

$activated_messages = [
	'invalid' => 'Lien d\'activation invalide.',
	'expired' => 'Lien d\'activation expiré. Demande un nouveau lien.',
];
$activated_error = $activated_messages[ $activated ] ?? '';
?>

<div class="sx-container sx-page sx-auth-page">
	<div class="sx-auth-card">
		<h1>Connexion</h1>
		<p class="sx-auth-card__lead">Reprends ton apprentissage là où tu t'étais arrêté.</p>

		<?php if ( $registered ) : ?>
			<div class="sx-notice sx-notice--ok">
				<strong>Compte créé.</strong> Vérifie ta boîte mail (et tes spams) pour le mot de passe envoyé par WordPress.
			</div>
		<?php endif; ?>
		<?php if ( $reset ) : ?>
			<div class="sx-notice sx-notice--ok">
				Mot de passe réinitialisé. Connecte-toi avec le nouveau.
			</div>
		<?php endif; ?>
		<?php if ( $activated_error ) : ?>
			<div class="sx-notice sx-notice--err"><?php echo esc_html( $activated_error ); ?></div>
		<?php endif; ?>
		<?php if ( $error_text ) : ?>
			<div class="sx-notice sx-notice--err"><?php echo esc_html( $error_text ); ?></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $login_post ); ?>" class="sx-form sx-form--auth">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
			<?php wp_nonce_field( 'seoflix_login', '_seoflix_login_nonce' ); ?>

			<label class="sx-form__label">
				<span>E-mail ou identifiant</span>
				<input type="text" name="log" required autocomplete="username" autofocus>
			</label>

			<label class="sx-form__label">
				<span>Mot de passe</span>
				<span class="sx-input-pwd">
					<input type="password" name="pwd" required autocomplete="current-password">
					<button type="button" class="sx-input-pwd__toggle" aria-label="Afficher le mot de passe" tabindex="-1">
						<svg class="sx-eye sx-eye--show" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
						<svg class="sx-eye sx-eye--hide" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
					</button>
				</span>
			</label>

			<label class="sx-form__label sx-form__label--check">
				<input type="checkbox" name="rememberme" value="forever">
				<span>Se souvenir de moi</span>
			</label>

			<?php if ( $turnstile ) : ?>
				<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile ); ?>" data-theme="dark"></div>
				<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
			<?php endif; ?>

			<button type="submit" class="sx-btn sx-btn--full">Se connecter</button>
		</form>

		<div class="sx-auth-links">
			<a href="<?php echo esc_url( home_url( '/mot-de-passe-oublie/' ) ); ?>">Mot de passe oublié ?</a>
			<span class="sx-sep">·</span>
			<a href="<?php echo esc_url( home_url( '/inscription/' ) ); ?>">Créer un compte</a>
		</div>
	</div>
</div>

<?php get_footer();
