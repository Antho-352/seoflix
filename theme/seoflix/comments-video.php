<?php
/**
 * Discussion privée d'une vidéo. Aucun accès aux fils avant les gardes de confidentialité.
 */

if ( ! class_exists( '\Seoflix\FeatureFlags' ) || ! \Seoflix\FeatureFlags::video_discussions_enabled() ) {
	return;
}

$video_id = get_the_ID();
?>
<section id="discussion-video" class="sx-video-discussion" aria-labelledby="discussion-video-title">
	<h2 id="discussion-video-title">Questions sur cette vidéo</h2>

	<?php if ( ! is_user_logged_in() ) : ?>
		<div class="sx-video-discussion__login" role="status">
			<p>Cette discussion est privée et réservée aux membres connectés.</p>
			<a class="sx-btn" href="<?php echo esc_url( wp_login_url( get_permalink( $video_id ) . '#discussion-video' ) ); ?>">Se connecter pour participer</a>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<?php if ( ! current_user_can( 'read' ) ) : ?>
		<p class="sx-video-discussion__notice sx-video-discussion__notice--error" role="alert">Ton compte ne permet pas d’accéder à cette discussion.</p>
		<?php return; ?>
	<?php endif; ?>

	<?php
	$status_code = isset( $_GET['discussion_status'] ) && is_scalar( $_GET['discussion_status'] )
		? sanitize_key( wp_unslash( $_GET['discussion_status'] ) )
		: '';
	$status_messages = [
		'submitted'      => [ 'status', 'Ta contribution est publiée.' ],
		'pending'        => [ 'status', 'Ta contribution a été reçue et attend la modération.' ],
		'invalid'        => [ 'alert', 'La contribution n’a pas pu être traitée.' ],
		'nonce'          => [ 'alert', 'Le formulaire a expiré. Recharge la page puis réessaie.' ],
		'login'          => [ 'alert', 'Connecte-toi avant de participer.' ],
		'forbidden'      => [ 'alert', 'Ton compte ne permet pas cette action.' ],
		'disabled'       => [ 'alert', 'La discussion est actuellement indisponible.' ],
		'closed'         => [ 'alert', 'La discussion est fermée pour cette vidéo.' ],
		'video'          => [ 'alert', 'Cette vidéo n’est pas disponible.' ],
		'files'          => [ 'alert', 'Les fichiers ne sont pas acceptés.' ],
		'content_short'  => [ 'alert', 'Le message doit contenir au moins 3 caractères.' ],
		'content_long'   => [ 'alert', 'Le message ne peut pas dépasser 1 500 caractères.' ],
		'content_unsafe' => [ 'alert', 'Utilise uniquement du texte brut, sans code ni balise.' ],
		'content_link'   => [ 'alert', 'Les liens et adresses e-mail ne sont pas acceptés.' ],
		'parent'         => [ 'alert', 'La question à laquelle tu réponds n’est plus disponible.' ],
		'rate'           => [ 'alert', 'Patiente quelques secondes avant une nouvelle contribution.' ],
		'duplicate'      => [ 'alert', 'Cette contribution semble déjà avoir été envoyée.' ],
		'failed'         => [ 'alert', 'Une erreur a empêché l’enregistrement. Réessaie plus tard.' ],
	];
	if ( isset( $status_messages[ $status_code ] ) ) :
		[ $notice_role, $notice_text ] = $status_messages[ $status_code ];
		?>
		<p class="sx-video-discussion__notice<?php echo 'alert' === $notice_role ? ' sx-video-discussion__notice--error' : ''; ?>" role="<?php echo esc_attr( $notice_role ); ?>"><?php echo esc_html( $notice_text ); ?></p>
	<?php endif; ?>

	<?php
	// Requête privée seulement après vérification du flag, de la session et de la capacité.
	$discussion_comments = get_comments( [
		'post_id'              => $video_id,
		'type'                 => \Seoflix\Video_Comments::COMMENT_TYPE,
		'status'               => 'approve',
		'number'               => 100,
		'orderby'              => 'comment_date_gmt',
		'order'                => 'ASC',
		'no_found_rows'        => true,
		'update_comment_meta_cache' => false,
	] );
	$roots    = [];
	$children = [];
	foreach ( $discussion_comments as $discussion_comment ) {
		if ( 0 === (int) $discussion_comment->comment_parent ) {
			$roots[ (int) $discussion_comment->comment_ID ] = $discussion_comment;
		}
	}
	foreach ( $discussion_comments as $discussion_comment ) {
		$parent = (int) $discussion_comment->comment_parent;
		// Une réponse n'est rendue que si son parent direct est une question racine approuvée.
		if ( $parent && isset( $roots[ $parent ] ) ) {
			$children[ $parent ][] = $discussion_comment;
		}
	}

	$render_form = static function ( int $parent_id, string $label ) use ( $video_id ): void {
		$field_id = $parent_id ? 'seoflix-video-reply-' . $parent_id : 'seoflix-video-question';
		?>
		<form class="sx-video-discussion__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="seoflix_video_comment">
			<input type="hidden" name="post_id" value="<?php echo (int) $video_id; ?>">
			<input type="hidden" name="parent_id" value="<?php echo (int) $parent_id; ?>">
			<input type="hidden" name="seoflix_video_comment_nonce" value="<?php echo esc_attr( wp_create_nonce( 'seoflix_video_comment_' . $video_id ) ); ?>">
			<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?></label>
			<textarea id="<?php echo esc_attr( $field_id ); ?>" name="content" rows="5" minlength="3" maxlength="1500" required></textarea>
			<p class="sx-video-discussion__help">Texte brut uniquement, de 3 à 1 500 caractères. Aucun lien, e-mail, code ou fichier.</p>
			<button class="sx-btn" type="submit"><?php echo $parent_id ? 'Envoyer la réponse' : 'Poser la question'; ?></button>
		</form>
		<?php
	};
	?>

	<?php if ( $roots ) : ?>
		<ol class="sx-video-discussion__threads">
			<?php foreach ( $roots as $root_id => $root ) : ?>
				<li class="sx-video-discussion__thread">
					<article class="sx-video-discussion__comment">
						<header>
							<strong><?php echo esc_html( $root->comment_author ); ?></strong>
							<time datetime="<?php echo esc_attr( mysql2date( 'c', $root->comment_date_gmt, false ) ); ?>"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $root->comment_date, true ) ); ?></time>
						</header>
						<div class="sx-video-discussion__content"><?php echo nl2br( esc_html( (string) $root->comment_content ) ); ?></div>
					</article>

					<?php if ( ! empty( $children[ $root_id ] ) ) : ?>
						<ol class="sx-video-discussion__replies" aria-label="Réponses à cette question">
							<?php foreach ( $children[ $root_id ] as $reply ) : ?>
								<li class="sx-video-discussion__reply">
									<article class="sx-video-discussion__comment">
										<header>
											<strong><?php echo esc_html( $reply->comment_author ); ?></strong>
											<time datetime="<?php echo esc_attr( mysql2date( 'c', $reply->comment_date_gmt, false ) ); ?>"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $reply->comment_date, true ) ); ?></time>
										</header>
										<div class="sx-video-discussion__content"><?php echo nl2br( esc_html( (string) $reply->comment_content ) ); ?></div>
									</article>
								</li>
							<?php endforeach; ?>
						</ol>
					<?php endif; ?>

					<?php if ( comments_open( $video_id ) ) : ?>
						<div class="sx-video-discussion__reply-form">
							<?php $render_form( (int) $root_id, 'Répondre à cette question' ); ?>
						</div>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php else : ?>
		<p class="sx-video-discussion__empty">Aucune question publiée pour le moment.</p>
	<?php endif; ?>

	<?php if ( comments_open( $video_id ) ) : ?>
		<div class="sx-video-discussion__question-form">
			<h3>Poser une question</h3>
			<?php $render_form( 0, 'Ta question' ); ?>
		</div>
	<?php else : ?>
		<p class="sx-video-discussion__closed" role="status">La discussion est fermée pour cette vidéo.</p>
	<?php endif; ?>
</section>
