<?php
/**
 * Campus: sessions of an edition.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed>             $edition
 * @var WP_Post|null                     $course
 * @var array<int, array<string, mixed>> $items
 * @var float                            $percentage
 * @var array<int, array<string, mixed>> $announcements
 * @var string                           $back_url
 * @var bool                             $can_retake
 * @var string                           $certificate_url
 */

use SIQA\AulaVirtual\Campus\CampusController;
use SIQA\AulaVirtual\Curriculum\LessonType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="av-campus av-campus--edition">
	<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Mis cursos', 'aula-virtual' ); ?></a></p>

	<h2><?php echo esc_html( $course instanceof WP_Post ? get_the_title( $course ) : (string) $edition['name'] ); ?></h2>
	<p class="av-edition__name"><?php echo esc_html( (string) $edition['name'] ); ?></p>

	<p class="av-edition__progress">
		<progress max="100" value="<?php echo esc_attr( (string) $percentage ); ?>"></progress>
		<?php
		printf(
			/* translators: %s: completion percentage. */
			esc_html__( '%s completado', 'aula-virtual' ),
			esc_html( number_format_i18n( $percentage, 0 ) . '%' )
		);
		?>
	</p>

	<?php if ( ! empty( $announcements ) ) : ?>
		<section class="av-announcements">
			<h3><?php esc_html_e( 'Anuncios', 'aula-virtual' ); ?></h3>
			<?php foreach ( $announcements as $av_news ) : ?>
				<details class="av-announcement">
					<summary><?php echo esc_html( (string) $av_news['title'] ); ?> <small><?php echo esc_html( mysql2date( (string) get_option( 'date_format' ), (string) $av_news['created_at'] ) ); ?></small></summary>
					<div class="av-announcement__body"><?php echo wp_kses_post( wpautop( (string) $av_news['content'] ) ); ?></div>
				</details>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<?php if ( empty( $items ) ) : ?>
		<p><?php esc_html_e( 'Esta edición todavía no tiene sesiones publicadas.', 'aula-virtual' ); ?></p>
	<?php else : ?>
		<ol class="av-lesson-list">
			<?php foreach ( $items as $av_item ) : ?>
				<li class="av-lesson-list__item<?php echo $av_item['completed'] ? ' is-completed' : ''; ?><?php echo empty( $av_item['available'] ) ? ' is-locked' : ''; ?>">
					<?php if ( ! empty( $av_item['available'] ) ) : ?>
						<a href="<?php echo esc_url( (string) $av_item['url'] ); ?>">
							<?php echo esc_html( (string) $av_item['lesson']['title'] ); ?>
						</a>
					<?php else : ?>
						<span class="av-lesson-list__title"><?php echo esc_html( (string) $av_item['lesson']['title'] ); ?></span>
					<?php endif; ?>
					<span class="av-lesson-list__type">
						<?php echo esc_html( LessonType::label( (string) $av_item['lesson']['lesson_type'] ) ); ?>
					</span>
					<?php if ( $av_item['completed'] ) : ?>
						<span class="av-lesson-list__done"><?php esc_html_e( 'Completada', 'aula-virtual' ); ?></span>
					<?php elseif ( empty( $av_item['available'] ) ) : ?>
						<span class="av-lesson-list__locked">
							<?php
							printf(
								/* translators: %s: date. */
								esc_html__( 'Disponible el %s', 'aula-virtual' ),
								esc_html( (string) $av_item['available_at'] )
							);
							?>
						</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>

	<?php if ( ! empty( $certificate_url ) ) : ?>
		<p class="av-edition__certificate">
			<a href="<?php echo esc_url( $certificate_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ver mi certificado', 'aula-virtual' ); ?></a>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $can_retake ) ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="av-retake"
			onsubmit="return confirm('<?php echo esc_js( __( 'Se borrará tu progreso en esta edición y empezarás desde la primera sesión. Tu certificado, si lo tienes, se conserva.', 'aula-virtual' ) ); ?>');">
			<input type="hidden" name="action" value="<?php echo esc_attr( CampusController::ACTION_RETAKE ); ?>">
			<input type="hidden" name="edition_id" value="<?php echo esc_attr( (string) (int) $edition['id'] ); ?>">
			<?php wp_nonce_field( CampusController::ACTION_RETAKE ); ?>
			<button type="submit"><?php echo esc_html( $percentage >= 100 ? __( 'Volver a hacer el curso', 'aula-virtual' ) : __( 'Reiniciar mi progreso', 'aula-virtual' ) ); ?></button>
		</form>
	<?php endif; ?>
</div>
