<?php
/**
 * Page custom : /mot-de-passe-oublie/
 */

if ( is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/mon-parcours/' ) );
	exit;
}

get_header();

$lostpass_post = \Seoflix\Auth_Pages::login_post_url() . '?action=lostpassword';
$turnstile     = (string) get_option( \Seoflix\Contact::OPTION_TURNSTILE_SITE, '' );

$checkemail = isset( $_GET['checkemail'] );
$has_error  = ! empty( $_GET['login'] );
$error_code = $has_error ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';

$error_messages = [
	'invalid_email'         => 'Aucun compte trouvé avec cet e-mail.',
	'empty_username'        => 'Saisis ton e-mail.',
	'invalidkey'            => 'Lien invalide ou expiré.',
	'expiredkey'            => 'Lien expiré, redemande-en un.',
];
$error_text = $error_messages[ $error_code ] ?? '';
?>

<div class="sx-container sx-page sx-auth-page">
	<div class="sx-auth-card">
		<h1>Mot de passe oublié</h1>
		<p class="sx-auth-card__lead">Entre ton e-mail, on t'envoie un lien pour le réinitialiser.</p>

		<?php if ( $checkemail ) : ?>
			<div class="sx-notice sx-notice--ok">
				<strong>E-mail envoyé.</strong> Vérifie ta boîte mail (et tes spams). Si tu ne reçois rien dans 5 minutes, l'adresse n'est probablement pas associée à un compte.
			</div>
		<?php endif; ?>
		<?php if ( $error_text ) : ?>
			<div class="sx-notice sx-notice--err"><?php echo esc_html( $error_text ); ?></div>
		<?php endif; ?>

		<?php if ( ! $checkemail ) : ?>
			<form method="post" action="<?php echo esc_url( $lostpass_post ); ?>" class="sx-form sx-form--auth">
				<label class="sx-form__label">
					<span>E-mail</span>
					<input type="email" name="user_login" required autocomplete="email" autofocus>
				</label>

				<?php if ( $turnstile ) : ?>
					<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile ); ?>" data-theme="dark"></div>
					<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
				<?php endif; ?>

				<button type="submit" class="sx-btn sx-btn--full">Envoyer le lien</button>
			</form>
		<?php endif; ?>

		<div class="sx-auth-links">
			<a href="<?php echo esc_url( home_url( '/connexion/' ) ); ?>">← Retour à la connexion</a>
		</div>
	</div>
</div>

<?php get_footer();
