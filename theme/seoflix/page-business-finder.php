<?php
/**
 * Vue dédiée : orientation vers un business à apprendre.
 * URL : /commencer/
 */
get_header();

$path_terms = get_terms(
	[
		'taxonomy'   => 'seoflix_path',
		'hide_empty' => true,
	]
);
$eligible_paths = [];
if ( ! is_wp_error( $path_terms ) ) {
	foreach ( $path_terms as $term ) {
		if ( (int) $term->count > 0 ) {
			$term_link = get_term_link( $term );
			foreach ( \Seoflix\Business_Finder::PATHS as $path_id => $path ) {
				if ( $path['slug'] === $term->slug && ! is_wp_error( $term_link ) ) {
					$eligible_paths[ $term->slug ] = [
						'id'   => $path_id,
						'term' => $term,
						'url'  => $term_link,
					];
					break;
				}
			}
		}
	}
}

$is_submission = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] );
$alert_message = '';
$recommendation = null;
if ( $is_submission && count( $eligible_paths ) >= 2 ) {
	$nonce = isset( $_POST['seoflix_business_finder_nonce'] ) && is_string( $_POST['seoflix_business_finder_nonce'] )
		? wp_unslash( $_POST['seoflix_business_finder_nonce'] )
		: '';
	$raw_answers = [];
	foreach ( \Seoflix\Business_Finder::ANSWER_ALLOWLISTS as $question => $allowed_values ) {
		if ( array_key_exists( $question, $_POST ) ) {
			$raw_answers[ $question ] = wp_unslash( $_POST[ $question ] );
		}
	}
	$answers = \Seoflix\Business_Finder::validate_answers( $raw_answers );
	if ( ! wp_verify_nonce( $nonce, 'seoflix_business_finder' ) || null === $answers ) {
		$alert_message = 'Le questionnaire est incomplet ou invalide. Vérifie les huit réponses puis réessaie.';
	} else {
		$recommendation = \Seoflix\Business_Finder::recommend( $answers, array_keys( $eligible_paths ) );
		if ( null === $recommendation ) {
			$alert_message = 'Aucune recommandation fiable ne peut être affichée avec les parcours actuellement disponibles.';
		}
	}
}
?>

<div class="sx-finder sx-container sx-page" id="contenu">
	<header class="sx-finder-hero">
		<p class="sx-finder-kicker">Commencer</p>
		<h1>Trouver le business à apprendre</h1>
		<p>Choisis directement un parcours ou utilise huit critères concrets pour comparer les options disponibles. Ce questionnaire donne une orientation explicable, pas une prédiction.</p>
	</header>

	<section class="sx-finder-choices" aria-labelledby="sx-finder-choices-title">
		<h2 id="sx-finder-choices-title">Comment veux-tu commencer&nbsp;?</h2>
		<div class="sx-finder-choices__grid">
			<a class="sx-finder-choice sx-finder-choice--questionnaire" href="#questionnaire">
				<strong>Trouver le business qui me correspond</strong>
				<span>Comparer les parcours avec le questionnaire.</span>
			</a>
			<a class="sx-finder-choice" href="<?php echo esc_url( home_url( '/parcours/' ) ); ?>">
				<strong>Je sais déjà ce que je veux apprendre</strong>
				<span>Voir directement tous les parcours disponibles.</span>
			</a>
		</div>
	</section>

	<?php if ( count( $eligible_paths ) < 2 ) : ?>
		<div class="sx-finder-alert" id="questionnaire" role="status">
			<strong>Orientation temporairement indisponible.</strong>
			<p>Moins de deux parcours publiés et non vides sont disponibles. Le questionnaire ne fabriquera pas de recommandation.</p>
			<a href="<?php echo esc_url( home_url( '/parcours/' ) ); ?>">Consulter les parcours disponibles</a>
		</div>
	<?php else : ?>
		<section class="sx-finder-questionnaire" id="questionnaire" aria-labelledby="sx-finder-form-title">
			<h2 id="sx-finder-form-title">Le questionnaire en 8 questions</h2>
			<p>Toutes les réponses sont nécessaires. Elles servent uniquement au calcul affiché sur cette page.</p>

			<?php if ( $alert_message ) : ?>
				<div class="sx-finder-alert" role="alert"><?php echo esc_html( $alert_message ); ?></div>
			<?php endif; ?>

			<form class="sx-finder-form" method="post" action="<?php echo esc_url( home_url( '/commencer/' ) ); ?>">
				<?php wp_nonce_field( 'seoflix_business_finder', 'seoflix_business_finder_nonce' ); ?>

				<fieldset>
					<legend>1. Quel type d’activité préfères-tu&nbsp;?</legend>
					<label class="sx-finder-option"><input type="radio" name="model" value="asset" required> Construire un actif qui peut rapporter plus tard</label>
					<label class="sx-finder-option"><input type="radio" name="model" value="service"> Vendre un service dès maintenant</label>
					<label class="sx-finder-option"><input type="radio" name="model" value="open"> Garder les deux options ouvertes</label>
				</fieldset>

				<fieldset>
					<legend>2. Quand les premiers revenus sont-ils nécessaires&nbsp;?</legend>
					<label class="sx-finder-option"><input type="radio" name="horizon" value="rapid" required> Rapidement</label>
					<label class="sx-finder-option"><input type="radio" name="horizon" value="months"> Dans quelques mois</label>
					<label class="sx-finder-option"><input type="radio" name="horizon" value="no_urgency"> Sans urgence particulière</label>
				</fieldset>

				<fieldset>
					<legend>3. Quel budget peux-tu engager sans difficulté&nbsp;?</legend>
					<label class="sx-finder-option"><input type="radio" name="budget" value="zero" required> Presque zéro</label>
					<label class="sx-finder-option"><input type="radio" name="budget" value="recurring"> Un petit budget récurrent</label>
					<label class="sx-finder-option"><input type="radio" name="budget" value="invest"> Un investissement plus important</label>
				</fieldset>

				<fieldset>
					<legend>4. Veux-tu prospecter et gérer régulièrement des clients&nbsp;?</legend>
					<label class="sx-finder-option"><input type="radio" name="clients" value="willing" required> Oui</label>
					<label class="sx-finder-option"><input type="radio" name="clients" value="limited"> De manière limitée</label>
					<label class="sx-finder-option"><input type="radio" name="clients" value="no"> Non</label>
				</fieldset>

				<fieldset>
					<legend>5. Quelle exposition te convient&nbsp;?</legend>
					<label class="sx-finder-option"><input type="radio" name="exposure" value="face" required> Montrer mon visage</label>
					<label class="sx-finder-option"><input type="radio" name="exposure" value="voice"> Utiliser seulement ma voix</label>
					<label class="sx-finder-option"><input type="radio" name="exposure" value="discreet"> Rester discret</label>
				</fieldset>

				<fieldset>
					<legend>6. Combien de temps peux-tu consacrer chaque semaine&nbsp;?</legend>
					<label class="sx-finder-option"><input type="radio" name="time" value="low" required> Peu de temps</label>
					<label class="sx-finder-option"><input type="radio" name="time" value="medium"> Un temps régulier</label>
					<label class="sx-finder-option"><input type="radio" name="time" value="high"> Beaucoup de temps</label>
				</fieldset>

				<fieldset>
					<legend>7. Quel est ton rapport aux outils et à la veille technique&nbsp;?</legend>
					<label class="sx-finder-option"><input type="radio" name="technical" value="low" required> Je préfère les limiter</label>
					<label class="sx-finder-option"><input type="radio" name="technical" value="tools"> Je peux apprendre de nouveaux outils</label>
					<label class="sx-finder-option"><input type="radio" name="technical" value="continuous"> J’aime tester et suivre une veille continue</label>
				</fieldset>

				<fieldset>
					<legend>8. Quel équilibre recherches-tu&nbsp;?</legend>
					<label class="sx-finder-option"><input type="radio" name="potential" value="stable" required> Un modèle plutôt stable et borné</label>
					<label class="sx-finder-option"><input type="radio" name="potential" value="high_uncertain"> Un potentiel plus élevé mais plus incertain</label>
				</fieldset>

				<button class="sx-finder-submit" type="submit">Comparer les parcours</button>
			</form>
		</section>
	<?php endif; ?>

	<?php if ( $recommendation ) : ?>
		<section class="sx-finder-results" aria-labelledby="sx-finder-results-title">
			<h2 id="sx-finder-results-title">Deux pistes à explorer</h2>
			<p>Le classement repose sur la table WEAS <?php echo esc_html( $recommendation['version'] ); ?> et sur les parcours réellement disponibles.</p>
			<p>Ordre de départage en cas d’égalité&nbsp;: <?php echo esc_html( implode( ' → ', $recommendation['tie_break']['order'] ) ); ?>.</p>
			<?php if ( $recommendation['tie_break']['primary_tie'] ) : ?>
				<p>La piste principale et l’alternative avaient le même score&nbsp;: cet ordre stable a départagé la première place.</p>
			<?php endif; ?>
			<?php if ( $recommendation['tie_break']['alternative_tie'] ) : ?>
				<p>L’alternative et la troisième piste avaient le même score&nbsp;: cet ordre stable a déterminé la seconde piste affichée.</p>
			<?php endif; ?>
			<?php foreach ( [ 'primary', 'alternative' ] as $position ) : ?>
				<?php
				$result = $recommendation[ $position ];
				$path_data = $eligible_paths[ $result['slug'] ];
				$is_primary = 'primary' === $position;
				?>
				<article class="sx-finder-result sx-finder-result--<?php echo esc_attr( $position ); ?>">
					<p class="sx-finder-result__label"><?php echo $is_primary ? 'Piste principale' : 'Alternative'; ?></p>
					<h3><a href="<?php echo esc_url( $path_data['url'] ); ?>"><?php echo esc_html( $result['name'] ); ?></a></h3>
					<p class="sx-finder-result__score">Score d’adéquation&nbsp;: <?php echo (int) $result['score']; ?> points</p>
					<div class="sx-finder-result__details">
						<div>
							<h4>Pourquoi ce parcours</h4>
							<ul>
								<?php foreach ( $result['reasons'] as $reason ) : ?>
									<li><?php echo esc_html( $reason ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
						<div>
							<h4>Points de vigilance</h4>
							<ul>
								<?php foreach ( $result['constraints'] as $constraint ) : ?>
									<li><?php echo esc_html( $constraint ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
					<a class="sx-finder-result__link" href="<?php echo esc_url( $path_data['url'] ); ?>">Explorer ce parcours</a>
					<?php if ( class_exists( '\Seoflix\Focus' ) ) : ?>
						<form class="sx-finder-focus" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="seoflix_focus_set">
							<input type="hidden" name="seoflix_focus_path" value="<?php echo esc_attr( $result['slug'] ); ?>">
							<input type="hidden" name="seoflix_focus_destination" value="path">
							<?php wp_nonce_field( \Seoflix\Focus::NONCE_ACTION, \Seoflix\Focus::NONCE_FIELD ); ?>
							<button type="submit">Activer FOCUS pour ce parcours</button>
						</form>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>
</div>

<?php get_footer();
