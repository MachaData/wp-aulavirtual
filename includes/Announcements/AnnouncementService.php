<?php
/**
 * Announcement rules.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Announcements;

use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\Events\Events;
use SIQA\AulaVirtual\Courses\CoursePostType;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Security\Sanitizer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publishes an announcement and, when asked, emails it to every enrolled
 * student through the regular event pipeline, so the email is the editable
 * "Nuevo anuncio" template.
 */
final class AnnouncementService {

	/**
	 * Announcement persistence.
	 *
	 * @var AnnouncementRepository
	 */
	private AnnouncementRepository $announcements;

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Enrollment persistence.
	 *
	 * @var EnrollmentRepository
	 */
	private EnrollmentRepository $enrollments;

	/**
	 * Domain events.
	 *
	 * @var EventBus
	 */
	private EventBus $events;

	/**
	 * Constructor.
	 *
	 * @param AnnouncementRepository $announcements Announcement persistence.
	 * @param EditionRepository      $editions      Edition persistence.
	 * @param EnrollmentRepository   $enrollments   Enrollment persistence.
	 * @param EventBus               $events        Domain events.
	 */
	public function __construct(
		AnnouncementRepository $announcements,
		EditionRepository $editions,
		EnrollmentRepository $enrollments,
		EventBus $events
	) {
		$this->announcements = $announcements;
		$this->editions      = $editions;
		$this->enrollments   = $enrollments;
		$this->events        = $events;
	}

	/**
	 * Creates an announcement.
	 *
	 * @param array<string, mixed> $input course_id or edition_id, title, content, send_email.
	 * @return array{id: int, recipients: int}|WP_Error
	 */
	public function create( array $input ) {
		$edition_id = Sanitizer::int( $input['edition_id'] ?? 0 );
		$course_id  = Sanitizer::int( $input['course_id'] ?? 0 );

		if ( $edition_id > 0 ) {
			$edition = $this->editions->find( $edition_id );

			if ( null === $edition ) {
				return new WP_Error( 'av_edition_not_found', __( 'La edición no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
			}

			$course_id = (int) $edition['course_id'];
		}

		$course = get_post( $course_id );

		if ( ! $course instanceof \WP_Post || CoursePostType::POST_TYPE !== $course->post_type ) {
			return new WP_Error( 'av_invalid_course', __( 'Selecciona un curso o una edición.', 'aula-virtual' ), array( 'status' => 400 ) );
		}

		$title   = Sanitizer::text( $input['title'] ?? '' );
		$content = Sanitizer::html( $input['content'] ?? '' );

		if ( '' === $title || '' === $content ) {
			return new WP_Error( 'av_announcement_empty', __( 'El anuncio necesita título y contenido.', 'aula-virtual' ), array( 'status' => 400 ) );
		}

		$send_email = Sanitizer::bool( $input['send_email'] ?? false );

		$id = $this->announcements->insert(
			array(
				'course_id'  => $course_id,
				'edition_id' => $edition_id,
				'author_id'  => get_current_user_id(),
				'title'      => $title,
				'content'    => $content,
				'send_email' => $send_email ? 1 : 0,
				'status'     => 'publish',
				'created_at' => current_time( 'mysql', true ),
			)
		);

		if ( 0 === $id ) {
			return new WP_Error( 'av_announcement_not_saved', __( 'No se pudo guardar el anuncio.', 'aula-virtual' ) );
		}

		$recipients = $send_email ? $this->notify( $id, $course_id, $edition_id, $title, $content ) : 0;

		return array(
			'id'         => $id,
			'recipients' => $recipients,
		);
	}

	/**
	 * Dispatches one event per enrolled student so the email template runs
	 * for each of them.
	 *
	 * @param int    $announcement_id Announcement id.
	 * @param int    $course_id       Course id.
	 * @param int    $edition_id      Edition id, 0 for every edition of the course.
	 * @param string $title           Title.
	 * @param string $content         HTML content.
	 * @return int Number of students notified.
	 */
	private function notify( int $announcement_id, int $course_id, int $edition_id, string $title, string $content ): int {
		$where = array(
			'course_id' => $course_id,
			'status'    => EnrollmentStatus::with_access(),
		);

		if ( $edition_id > 0 ) {
			$where['edition_id'] = $edition_id;
		}

		$rows  = $this->enrollments->all( array( 'where' => $where, 'limit' => 2000 ) );
		$sent  = 0;
		$users = array();

		foreach ( $rows as $row ) {
			$user_id = (int) $row['user_id'];

			if ( isset( $users[ $user_id ] ) ) {
				continue;
			}

			$users[ $user_id ] = true;

			$this->events->dispatch(
				Events::ANNOUNCEMENT_CREATED,
				array(
					'announcement_id'      => $announcement_id,
					'announcement_title'   => $title,
					'announcement_content' => $content,
					'user_id'              => $user_id,
					'course_id'            => $course_id,
					'edition_id'           => (int) $row['edition_id'],
				)
			);

			++$sent;
		}

		return $sent;
	}

	/**
	 * Deletes an announcement.
	 *
	 * @param int $id Announcement id.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		return $this->announcements->delete( $id );
	}
}
