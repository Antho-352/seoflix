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

$register_post = \Seoflix\Auth_Pages::login_post_url() . '?action=register';
$turnstile     = (string) get_option( \Seoflix\Contact::OPTION_TURNSTILE_SITE, '' );

$has_error  = ! empty( $_GET['registration'] );
$error_code = $has_error ? sanitize_text_field( wp_unslash( $_GET['registration'] ) ) : '';

$error_messages = [
	'disabled'           => 'Les inscriptions sont actuellement désactivées.',
	'empty_username'     => 'Saisis un identifiant.',
	'empty_email'        => 'Saisis ton adresse e-mail.',
	'invalid_email'      => 'E-mail invalide.',
	'invalid_username'   => 'Identifiant invalide (lettres et chiffres uniquement).',
	'username_exists'    => 'Cet identifiant est déjà pris.',
	'email_exists'       => 'Un compte existe déjà avec cet e-mail.',
	'seoflix_rate_limit' => 'Trop d\'inscriptions depuis cette adresse. Réessaye dans 1 heure.',
	'seoflix_honeypot'   => 'Champ invalide.',
	'seoflix_turnstile'  => 'Vérification anti-bot échouée.',
];
$error_text = $error_messages[ $error_code ] ?? '';
?>

<div class="sx-container sx-page sx-auth-page">
	<div class="sx-auth-card">
		<h1>Créer un compte</h1>
		<p class="sx-auth-card__lead">Suis ta progression sur les parcours d'apprentissage. Gratuit, sans pub.</p>

		<?php if ( $error_text ) : ?>
			<div class="sx-notice sx-notice--err"><?php echo esc_html( $error_text ); ?></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $register_post ); ?>" class="sx-form sx-form--auth">

			<!-- Honeypot anti-spam -->
			<div style="position:absolute; left:-9999px;" aria-hidden="true">
				<label>Ne pas remplir<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
			</div>

			<label class="sx-form__label">
				<span>Identifiant</span>
				<input type="text" name="user_login" required autocomplete="username" pattern="[a-zA-Z0-9_-]{3,30}" minlength="3" maxlength="30">
				<small class="sx-form__hint">3 à 30 caractères, lettres, chiffres, <code>_</code>, <code>-</code>.</small>
			</label>

			<label class="sx-form__label">
				<span>E-mail</span>
				<input type="email" name="user_email" required autocomplete="email">
				<small class="sx-form__hint">Le mot de passe te sera envoyé à cette adresse.</small>
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
	</div>
</div>

<?php get_footer();
