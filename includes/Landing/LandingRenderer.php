<?php
/**
 * Landing view model and rendering.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Landing;

use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Curriculum\ModuleRepository;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Editions\EditionStatus;
use SIQA\AulaVirtual\Enrollments\EnrollmentLinkRepository;
use SIQA\AulaVirtual\Enrollments\RegistrationService;
use SIQA\AulaVirtual\WooCommerce\ProductLink;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns the stored landing data plus the live editions into HTML.
 *
 * The editions decide the buttons: "Comprar" goes to the checkout of the
 * edition's product, "Inscribirse" to its registration link. Templates can be
 * overridden by the theme under `aula-virtual/landing/`.
 */
final class LandingRenderer {

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Registration link persistence.
	 *
	 * @var EnrollmentLinkRepository
	 */
	private EnrollmentLinkRepository $links;

	/**
	 * Module persistence.
	 *
	 * @var ModuleRepository
	 */
	private ModuleRepository $modules;

	/**
	 * Lesson persistence.
	 *
	 * @var LessonRepository
	 */
	private LessonRepository $lessons;

	/**
	 * Constructor.
	 *
	 * @param EditionRepository        $editions Edition persistence.
	 * @param EnrollmentLinkRepository $links    Registration link persistence.
	 * @param ModuleRepository         $modules  Module persistence.
	 * @param LessonRepository         $lessons  Lesson persistence.
	 */
	public function __construct(
		EditionRepository $editions,
		EnrollmentLinkRepository $links,
		ModuleRepository $modules,
		LessonRepository $lessons
	) {
		$this->editions = $editions;
		$this->links    = $links;
		$this->modules  = $modules;
		$this->lessons  = $lessons;
	}

	/**
	 * Renders the full landing of a course.
	 *
	 * @param int $course_id Course id.
	 * @return string
	 */
	public function render( int $course_id ): string {
		$landing = LandingData::load( $course_id );
		$vm      = $this->view_model( $course_id, $landing );

		return $this->template(
			'landing',
			array(
				'course_id' => $course_id,
				'landing'   => $landing,
				'vm'        => $vm,
			)
		);
	}

	/**
	 * Renders only the editions block (info + price), for shortcodes.
	 *
	 * @param int $course_id Course id.
	 * @return string
	 */
	public function render_editions( int $course_id ): string {
		$landing = LandingData::load( $course_id );
		$vm      = $this->view_model( $course_id, $landing );

		return $this->template( 'sections/info', array( 'course_id' => $course_id, 'landing' => $landing, 'vm' => $vm, 'section' => $landing['info'] ) )
			. $this->template( 'sections/price', array( 'course_id' => $course_id, 'landing' => $landing, 'vm' => $vm, 'section' => $landing['price'] ) );
	}

	/**
	 * Data derived from the live state of the course: editions, buttons,
	 * curriculum, images.
	 *
	 * @param int                                 $course_id Course id.
	 * @param array<string, array<string, mixed>> $landing   Landing data.
	 * @return array<string, mixed>
	 */
	public function view_model( int $course_id, array $landing ): array {
		$editions = array();

		foreach ( $this->editions->for_course( $course_id ) as $edition ) {
			if ( ! EditionStatus::accepts_enrollments( (string) $edition['status'] ) ) {
				continue;
			}

			$editions[] = $this->edition_card( $edition );
		}

		$primary = $editions[0] ?? null;

		$buttons = array();

		foreach ( array( 'hero', 'price', 'cta' ) as $section ) {
			$buttons[ $section ] = $this->button( $landing[ $section ], $editions, $landing['cta']['whatsapp'] ?? '' );
		}

		return array(
			'editions'   => $editions,
			'primary'    => $primary,
			'buttons'    => $buttons,
			'curriculum' => null === $primary ? array() : $this->curriculum( (int) $primary['id'] ),
			'modalities' => EditionService::modalities(),
			'image'      => static fn( int $id, string $size = 'large' ): string => $id > 0 ? (string) wp_get_attachment_image_url( $id, $size ) : '',
			'video'      => array( $this, 'video_html' ),
		);
	}

	/**
	 * Builds the public representation of an edition.
	 *
	 * @param array<string, mixed> $edition Edition row.
	 * @return array<string, mixed>
	 */
	private function edition_card( array $edition ): array {
		$register_url = '';

		foreach ( $this->links->for_edition( (int) $edition['id'] ) as $link ) {
			if ( EnrollmentLinkRepository::STATUS_ACTIVE === $link['status'] ) {
				$register_url = RegistrationService::link_url( (string) $link['token'] );
				break;
			}
		}

		$modalities = EditionService::modalities();

		return array(
			'id'            => (int) $edition['id'],
			'name'          => (string) $edition['name'],
			'status'        => EditionStatus::label( (string) $edition['status'] ),
			'modality'      => $modalities[ $edition['modality'] ] ?? (string) $edition['modality'],
			'start_date'    => $this->date( $edition['start_date'] ),
			'end_date'      => $this->date( $edition['end_date'] ),
			'schedule_days' => (string) ( $edition['schedule_days'] ?? '' ),
			'schedule_time' => (string) ( $edition['schedule_time'] ?? '' ),
			'timezone'      => (string) $edition['timezone'],
			'price'         => (string) ( $edition['price_display'] ?? '' ),
			'capacity'      => (int) $edition['capacity'],
			'buy_url'       => ProductLink::buy_url( $edition ),
			'register_url'  => $register_url,
		);
	}

	/**
	 * Resolves the button of a section.
	 *
	 * @param array<string, mixed>              $section  Section data.
	 * @param array<int, array<string, mixed>>  $editions Edition cards.
	 * @param string                            $whatsapp WhatsApp number.
	 * @return array{text: string, url: string}|null
	 */
	private function button( array $section, array $editions, string $whatsapp ): ?array {
		$text = (string) ( $section['button_text'] ?? '' );
		$type = (string) ( $section['button_type'] ?? LandingData::BUTTON_NONE );
		$url  = '';

		switch ( $type ) {
			case LandingData::BUTTON_BUY:
				foreach ( $editions as $edition ) {
					if ( '' !== $edition['buy_url'] ) {
						$url = $edition['buy_url'];
						break;
					}
				}
				break;
			case LandingData::BUTTON_REGISTER:
				foreach ( $editions as $edition ) {
					if ( '' !== $edition['register_url'] ) {
						$url = $edition['register_url'];
						break;
					}
				}
				break;
			case LandingData::BUTTON_WHATSAPP:
				$url = '' === $whatsapp ? '' : 'https://wa.me/' . ltrim( $whatsapp, '+' );
				break;
			case LandingData::BUTTON_CUSTOM:
				$url = (string) ( $section['button_url'] ?? '' );
				break;
		}

		if ( '' === $url || '' === $text ) {
			return null;
		}

		return array(
			'text' => $text,
			'url'  => $url,
		);
	}

	/**
	 * Modules and published lessons of an edition, grouped.
	 *
	 * @param int $edition_id Edition id.
	 * @return array<int, array{title: string, lessons: array<int, string>}>
	 */
	private function curriculum( int $edition_id ): array {
		$groups = array();

		foreach ( $this->modules->for_edition( $edition_id ) as $module ) {
			$groups[ (int) $module['id'] ] = array(
				'title'   => (string) $module['title'],
				'lessons' => array(),
			);
		}

		$ungrouped = array();

		foreach ( $this->lessons->for_edition( $edition_id ) as $lesson ) {
			$module_id = (int) $lesson['module_id'];

			if ( isset( $groups[ $module_id ] ) ) {
				$groups[ $module_id ]['lessons'][] = (string) $lesson['title'];
			} else {
				$ungrouped[] = (string) $lesson['title'];
			}
		}

		$groups = array_values( array_filter( $groups, static fn( array $g ): bool => array() !== $g['lessons'] ) );

		if ( array() !== $ungrouped ) {
			$groups[] = array(
				'title'   => '',
				'lessons' => $ungrouped,
			);
		}

		return $groups;
	}

	/**
	 * Embeds a video URL: oEmbed for YouTube and Vimeo, a player for MP4.
	 *
	 * @param string $url Video URL.
	 * @return string
	 */
	public function video_html( string $url ): string {
		$url = esc_url_raw( $url );

		if ( '' === $url ) {
			return '';
		}

		if ( preg_match( '/\.(mp4|webm|ogv)(\?.*)?$/i', $url ) ) {
			return '<video controls playsinline preload="metadata" src="' . esc_url( $url ) . '"></video>';
		}

		$embed = wp_oembed_get( $url );

		return false === $embed ? '' : wp_kses_post( $embed );
	}

	/**
	 * Formats a stored UTC date with the site format.
	 *
	 * @param mixed $value Stored value.
	 * @return string
	 */
	private function date( mixed $value ): string {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		return (string) date_i18n( (string) get_option( 'date_format' ), (int) strtotime( $value . ' UTC' ) );
	}

	/**
	 * Renders a landing template, letting the theme override it.
	 *
	 * @param string               $name Template path without extension.
	 * @param array<string, mixed> $data Variables exposed to the template.
	 * @return string
	 */
	public function template( string $name, array $data ): string {
		$override = locate_template( array( 'aula-virtual/landing/' . $name . '.php' ) );
		$path     = '' !== $override ? $override : AV_PATH . 'templates/landing/' . $name . '.php';

		if ( ! is_readable( $path ) ) {
			return '';
		}

		$data['renderer'] = $this;

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		ob_start();
		require $path;

		return (string) ob_get_clean();
	}
}
