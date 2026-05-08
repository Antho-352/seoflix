<?php
/**
 * Page custom : /definir-mot-de-passe/{token}/
 * Utilisée après activation et après reset password.
 */

$token = (string) get_query_var( \Seoflix\Custom_Auth::QV_SETPWD );
if ( ! preg_match( '/^[a-f0-9]{32,64}$/', $token ) ) {
	wp_safe_redirect( home_url( '/connexion/?activated=invalid' ) );
	exit;
}

// Vérifie le token côté serveur (pour rejeter avant rendu si invalide)
$users = get_users( [
	'meta_key'   => '_seoflix_setpwd_token',
	'meta_value' => $token,
	'number'     => 1,
	'fields'     => [ 'ID' ],
] );
if ( ! $users ) {
	wp_safe_redirect( home_url( '/connexion/?activated=invalid' ) );
	exit;
}

$user_id = (int) $users[0]->ID;
$expires = (int) get_user_meta( $user_id, '_seoflix_setpwd_expires', true );
if ( $expires && time() > $expires ) {
	wp_safe_redirect( home_url( '/connexion/?activated=expired' ) );
	exit;
}

get_header();

$has_error  = ! empty( $_GET['setpwd'] );
$error_code = $has_error ? sanitize_text_field( wp_unslash( $_GET['setpwd'] ) ) : '';

$error_messages = [
	'session_expired' => 'Session expirée, recharge la page.',
	'too_short'       => 'Mot de passe trop court (min 12 caractères).',
	'mismatch'        => 'Les deux mots de passe ne correspondent pas.',
];
$error_text = $error_messages[ $error_code ] ?? '';
?>

<div class="sx-container sx-page sx-auth-page">
	<div class="sx-auth-card">
		<h1>Définir ton mot de passe</h1>
		<p class="sx-auth-card__lead">Choisis un mot de passe robuste, tu pourras te connecter immédiatement après.</p>

		<?php if ( $error_text ) : ?>
			<div class="sx-notice sx-notice--err"><?php echo esc_html( $error_text ); ?></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( \Seoflix\Custom_Auth::frontend_action_url( 'setpwd' ) ); ?>" class="sx-form sx-form--auth">
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<?php wp_nonce_field( 'seoflix_setpwd', '_seoflix_setpwd_nonce' ); ?>

			<label class="sx-form__label">
				<span>Nouveau mot de passe</span>
				<span class="sx-input-pwd">
					<input type="password" name="pwd" required minlength="12" autocomplete="new-password">
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
					<input type="password" name="pwd2" required minlength="12" autocomplete="new-password">
					<button type="button" class="sx-input-pwd__toggle" aria-label="Afficher le mot de passe" tabindex="-1">
						<svg class="sx-eye sx-eye--show" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
						<svg class="sx-eye sx-eye--hide" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
					</button>
				</span>
			</label>

			<button type="submit" class="sx-btn sx-btn--full">Activer mon compte</button>
		</form>
	</div>
</div>

<?php get_footer();
