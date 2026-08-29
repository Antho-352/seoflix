<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Consentement analytics et chargement conditionnel de Microsoft Clarity.
 *
 * Aucun code ni appel réseau Clarity n'est créé avant un consentement explicite.
 */
final class Analytics_Consent {

	public const OPTION_PROJECT_ID = 'seoflix_clarity_project_id';
	public const OPTION_BANNER_TITLE = 'seoflix_consent_banner_title';
	public const OPTION_BANNER_DESCRIPTION = 'seoflix_consent_banner_description';
	public const OPTION_PRIVACY_LABEL = 'seoflix_consent_privacy_label';
	public const OPTION_ACCEPT_LABEL = 'seoflix_consent_accept_label';
	public const OPTION_DENY_LABEL = 'seoflix_consent_deny_label';
	public const OPTION_MANAGE_LABEL = 'seoflix_consent_manage_label';
	private const STORAGE_KEY      = 'weas_analytics_consent_v1';
	private const DEFAULT_WORDING  = [
		'title'       => 'Mesure d’audience',
		'description' => 'Avec ton accord, Microsoft Clarity nous aide à comprendre l’utilisation du site grâce à des statistiques et des enregistrements de navigation. Tu peux accepter, refuser ou changer d’avis à tout moment.',
		'privacy'     => 'Consulter la politique de confidentialité',
		'accept'      => 'Accepter les statistiques',
		'deny'        => 'Refuser',
		'manage'      => 'Gérer mes cookies',
	];

	public static function init(): void {
		add_action( 'wp_head', [ self::class, 'render_head_bootstrap' ], 0 );
		add_action( 'wp_footer', [ self::class, 'render_consent_ui' ], 100 );
		add_filter( 'the_content', [ self::class, 'filter_privacy_content' ], 20 );
	}

	public static function sanitize_project_id( mixed $value ): string {
		$value = strtolower( trim( (string) $value ) );
		return preg_match( '/\A[a-z0-9]{4,64}\z/', $value ) ? $value : '';
	}

	public static function sanitize_short_copy( mixed $value ): string {
		return self::limit_copy( sanitize_text_field( (string) $value ), 120 );
	}

	public static function sanitize_description( mixed $value ): string {
		return self::limit_copy( sanitize_textarea_field( (string) $value ), 500 );
	}

	private static function limit_copy( string $value, int $maximum ): string {
		$value = trim( $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $maximum );
		}
		$matched = preg_match_all( '/./us', $value, $characters );
		return false !== $matched ? implode( '', array_slice( $characters[0], 0, $maximum ) ) : '';
	}

	public static function wording(): array {
		$options = [
			'title'       => self::OPTION_BANNER_TITLE,
			'description' => self::OPTION_BANNER_DESCRIPTION,
			'privacy'     => self::OPTION_PRIVACY_LABEL,
			'accept'      => self::OPTION_ACCEPT_LABEL,
			'deny'        => self::OPTION_DENY_LABEL,
			'manage'      => self::OPTION_MANAGE_LABEL,
		];
		$wording = [];
		foreach ( $options as $key => $option ) {
			$value           = trim( (string) get_option( $option, '' ) );
			$wording[ $key ] = '' !== $value ? $value : self::DEFAULT_WORDING[ $key ];
		}
		return $wording;
	}

	public static function project_id(): string {
		return self::sanitize_project_id( get_option( self::OPTION_PROJECT_ID, '' ) );
	}

	public static function is_configured(): bool {
		return '' !== self::project_id();
	}

	public static function render_head_bootstrap(): void {
		$project_id = self::project_id();
		if ( '' === $project_id || is_admin() ) {
			return;
		}
		?>
<script id="weas-analytics-consent-bootstrap">
(function(window, document) {
	'use strict';
	const STORAGE_KEY = <?php echo wp_json_encode( self::STORAGE_KEY ); ?>;
	const PROJECT_ID = <?php echo wp_json_encode( $project_id ); ?>;
	const CLARITY_URL = 'https://www.clarity.ms/tag/';

	function getChoice() {
		try {
			const choice = window.localStorage.getItem(STORAGE_KEY);
			return choice === 'granted' || choice === 'denied' ? choice : null;
		} catch (error) {
			return null;
		}
	}

	function writeChoice(choice) {
		try {
			window.localStorage.setItem(STORAGE_KEY, choice);
			return true;
		} catch (error) {
			return false;
		}
	}

	function expireCookie(name) {
		const expires = 'Thu, 01 Jan 1970 00:00:00 GMT';
		document.cookie = name + '=; expires=' + expires + '; path=/; SameSite=Lax';
		const host = window.location.hostname.replace(/^www\./, '');
		if (host.indexOf('.') !== -1) {
			document.cookie = name + '=; expires=' + expires + '; path=/; domain=.' + host + '; SameSite=Lax';
		}
	}

	function consentV2(value) {
		if (typeof window.clarity !== 'function') {
			return;
		}
		window.clarity('consentv2', {
			ad_Storage: 'denied',
			analytics_Storage: value
		});
	}

	function loadClarity() {
		const choice = getChoice();
		if (choice !== 'granted' || !PROJECT_ID) {
			return false;
		}
		if (document.querySelector('script[data-weas-clarity]')) {
			return true;
		}
		window.clarity = window.clarity || function() {
			(window.clarity.q = window.clarity.q || []).push(arguments);
		};
		consentV2('granted');
		const script = document.createElement('script');
		script.async = true;
		script.src = CLARITY_URL + encodeURIComponent(PROJECT_ID);
		script.dataset.weasClarity = '1';
		document.head.appendChild(script);
		return true;
	}

	function apply(choice) {
		if (choice !== 'granted' && choice !== 'denied') {
			return false;
		}
		if (!writeChoice(choice)) {
			return false;
		}
		if (choice === 'granted') {
			loadClarity();
		} else {
			const hadClarity = typeof window.clarity === 'function' || !!document.querySelector('script[data-weas-clarity]');
			consentV2('denied');
			expireCookie('_clck');
			expireCookie('_clsk');
			if (hadClarity) {
				window.location.reload();
				return true;
			}
		}
		window.dispatchEvent(new CustomEvent('weas:analytics-consent', { detail: { choice: choice } }));
		return true;
	}

	window.weasAnalyticsConsent = Object.freeze({
		get: getChoice,
		apply: apply,
		load: loadClarity
	});

	if (getChoice() === 'granted') {
		loadClarity();
	}
})(window, document);
</script>
		<?php
	}

	public static function render_consent_ui(): void {
		if ( ! self::is_configured() || is_admin() ) {
			return;
		}
		$privacy_url = home_url( '/confidentialite/' );
		$wording     = self::wording();
		?>
<style id="weas-analytics-consent-styles">
.sx-consent[hidden],.sx-consent-manage[hidden]{display:none!important}.sx-consent{position:fixed;z-index:100000;left:1rem;right:1rem;bottom:1rem;max-width:760px;margin:auto;padding:1.25rem;background:#15151b;color:#f7f7f9;border:1px solid #3b3b46;border-radius:16px;box-shadow:0 18px 60px rgba(0,0,0,.55);font:400 16px/1.5 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.sx-consent h2{margin:0 0 .5rem;color:#fff;font-size:1.25rem}.sx-consent p{margin:.5rem 0;color:#d2d2d8}.sx-consent a{color:#ff5a67;text-decoration:underline}.sx-consent__actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1rem}.sx-consent button,.sx-consent-manage{min-height:44px;padding:.65rem 1rem;border:1px solid #ff2d3f;border-radius:10px;font:700 15px/1 system-ui;cursor:pointer}.sx-consent__accept{background:#ff2d3f;color:#09090c}.sx-consent__deny{background:transparent;color:#fff}.sx-consent button:focus-visible,.sx-consent-manage:focus-visible{outline:3px solid #fff;outline-offset:3px}.sx-consent-manage{position:fixed;z-index:99999;left:1rem;bottom:1rem;background:#15151b;color:#fff;border-color:#4a4a56}.sx-consent-manage--inline{position:static;display:inline-flex;align-items:center;margin-top:1rem}.sx-consent-manage--inline[hidden]{display:none!important}@media(max-width:520px){.sx-consent{left:.75rem;right:.75rem;bottom:.75rem;padding:1rem}.sx-consent__actions{display:grid}.sx-consent button{width:100%}.sx-consent-manage{left:.75rem;bottom:.75rem}}
</style>
<section class="sx-consent" id="sx-analytics-consent" role="dialog" aria-labelledby="sx-consent-title" aria-describedby="sx-consent-description" hidden>
	<h2 id="sx-consent-title"><?php echo esc_html( $wording['title'] ); ?></h2>
	<p id="sx-consent-description"><?php echo esc_html( $wording['description'] ); ?></p>
	<p><a href="<?php echo esc_url( $privacy_url ); ?>"><?php echo esc_html( $wording['privacy'] ); ?></a></p>
	<div class="sx-consent__actions">
		<button type="button" class="sx-consent__accept" data-weas-consent="granted"><?php echo esc_html( $wording['accept'] ); ?></button>
		<button type="button" class="sx-consent__deny" data-weas-consent="denied"><?php echo esc_html( $wording['deny'] ); ?></button>
	</div>
</section>
<button type="button" class="sx-consent-manage" data-weas-consent-manage hidden><?php echo esc_html( $wording['manage'] ); ?></button>
<script id="weas-analytics-consent-ui">
(function(window, document) {
	'use strict';
	const api = window.weasAnalyticsConsent;
	const panel = document.getElementById('sx-analytics-consent');
	const manageButtons = document.querySelectorAll('[data-weas-consent-manage]');
	if (!api || !panel) return;
	const firstButton = panel.querySelector('[data-weas-consent]');

	function setVisibility(forceOpen) {
		const choice = api.get();
		const open = forceOpen || !choice;
		panel.hidden = !open;
		manageButtons.forEach(function(button) { button.hidden = open || !choice; });
		if (forceOpen && firstButton) firstButton.focus();
	}

	document.addEventListener('click', function(event) {
		const choiceButton = event.target.closest('[data-weas-consent]');
		if (choiceButton && panel.contains(choiceButton)) {
			const applied = api.apply(choiceButton.dataset.weasConsent);
			if (applied) setVisibility(false);
			return;
		}
		const manage = event.target.closest('[data-weas-consent-manage]');
		if (manage) {
			event.preventDefault();
			setVisibility(true);
		}
	});
	window.addEventListener('weas:analytics-consent', function() { setVisibility(false); });
	setVisibility(false);
})(window, document);
</script>
		<?php
	}

	public static function filter_privacy_content( string $content ): string {
		if ( is_admin() || ! is_page( 'confidentialite' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$wording = self::wording();
		$legacy = '<p>WEAS utilise uniquement des cookies fonctionnels strictement nécessaires (session WordPress pour l’administration). Aucun cookie publicitaire ni de tracking tiers n’est déposé par WEAS.</p>';
		$updated = '<p>WEAS utilise des cookies fonctionnels strictement nécessaires. Microsoft Clarity n’est activé qu’après ton consentement explicite aux statistiques.</p>';
		$content = str_replace( [ $legacy, str_replace( '’', "'", $legacy ) ], $updated, $content );

		if ( ! self::is_configured() || str_contains( $content, 'id="weas-microsoft-clarity"' ) ) {
			return $content;
		}

		$disclosure = '<section id="weas-microsoft-clarity"><h2>Microsoft Clarity — mesure d’audience</h2>'
			. '<p>Avec ton accord, WEAS et Microsoft utilisent Clarity pour traiter des données techniques et d’interaction, produire des statistiques, des cartes de chaleur et des relectures de session afin d’améliorer l’ergonomie du site. Clarity peut alors utiliser des cookies de première et de tierce parties. Microsoft Clarity est chargé uniquement après ton consentement. Le refus n’empêche pas d’utiliser WEAS.</p>'
			. '<p>Tu peux retirer ton consentement à tout moment avec le bouton « ' . esc_html( $wording['manage'] ) . ' ». Le retrait empêche les futurs chargements de Clarity et supprime les cookies Clarity accessibles depuis WEAS.</p>'
			. '<p>En savoir plus : <a href="https://privacy.microsoft.com/fr-fr/privacystatement" target="_blank" rel="noopener noreferrer">Politique de confidentialité Microsoft</a>.</p>'
			. '<button type="button" class="sx-consent-manage sx-consent-manage--inline" data-weas-consent-manage>' . esc_html( $wording['manage'] ) . '</button></section>';

		return $content . $disclosure;
	}
}
