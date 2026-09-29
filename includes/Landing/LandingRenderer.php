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
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\RegistrationService;
use SIQA\AulaVirtual\Videos\VideoEmbed;
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
	 * Enrollment persistence (seats left).
	 *
	 * @var EnrollmentRepository|null
	 */
	private ?EnrollmentRepository $enrollments;

	/**
	 * Constructor.
	 *
	 * @param EditionRepository        $editions Edition persistence.
	 * @param EnrollmentLinkRepository $links    Registration link persistence.
	 * @param ModuleRepository         $modules  Module persistence.
	 * @param LessonRepository         $lessons  Lesson persistence.
	 * @param EnrollmentRepository|null $enrollments Enrollment persistence.
	 */
	public function __construct(
		EditionRepository $editions,
		EnrollmentLinkRepository $links,
		ModuleRepository $modules,
		LessonRepository $lessons,
		?EnrollmentRepository $enrollments = null
	) {
		$this->editions    = $editions;
		$this->links       = $links;
		$this->modules     = $modules;
		$this->lessons     = $lessons;
		$this->enrollments = $enrollments;
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

		$html = $this->template( 'sections/info', array( 'course_id' => $course_id, 'landing' => $landing, 'vm' => $vm, 'section' => $landing['info'] ) )
			. $this->template( 'sections/price', array( 'course_id' => $course_id, 'landing' => $landing, 'vm' => $vm, 'section' => $landing['price'] ) );

		if ( '' === trim( $html ) ) {
			return '';
		}

		return '<div class="av-landing av-landing--embed" style="' . esc_attr( '--av-accent:' . $vm['accent'] . ';--av-on-accent:' . $vm['on_accent'] . ';' ) . '">' . $html . '</div>';
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

		$curriculum = null === $primary ? array() : $this->curriculum( (int) $primary['id'] );
		$lessons    = array_merge( array(), ...array_map( static fn( array $g ): array => $g['lessons'], $curriculum ) );
		$accent     = self::accent();

		return array(
			'editions'   => $editions,
			'primary'    => $primary,
			'buttons'    => $buttons,
			'curriculum' => $curriculum,
			'stats'      => array(
				'sessions' => count( $lessons ),
				'minutes'  => array_sum( array_map( static fn( array $l ): int => (int) $l['duration'], $lessons ) ),
				'modules'  => count( array_filter( $curriculum, static fn( array $g ): bool => '' !== $g['title'] ) ),
			),
			'accent'     => $accent,
			'on_accent'  => self::on_color( $accent ),
			'course'     => get_post( $course_id ),
			'icon'       => array( self::class, 'icon' ),
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
			'seats_left'    => (int) $edition['capacity'] > 0 && null !== $this->enrollments ? max( 0, (int) $edition['capacity'] - $this->enrollments->count_seats_taken( (int) $edition['id'] ) ) : null,
			'status_key'    => (string) $edition['status'],
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
	 * @return array<int, array{title: string, lessons: array<int, array{title: string, type: string, duration: int}>}>
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
			$row       = array(
				'title'    => (string) $lesson['title'],
				'type'     => (string) $lesson['lesson_type'],
				'duration' => (int) $lesson['duration'],
			);

			if ( isset( $groups[ $module_id ] ) ) {
				$groups[ $module_id ]['lessons'][] = $row;
			} else {
				$ungrouped[] = $row;
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
	 * Accent colour of the landing: the brand colour from Settings.
	 *
	 * @return string Hex colour.
	 */
	public static function accent(): string {
		$color = (string) get_option( 'av_brand_color', '#1d4ed8' );

		return 1 === preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ? strtolower( $color ) : '#1d4ed8';
	}

	/**
	 * Text colour that stays readable on a background (WCAG luminance).
	 *
	 * @param string $hex Background colour.
	 * @return string #111111 or #ffffff.
	 */
	public static function on_color( string $hex ): string {
		$hex = ltrim( $hex, '#' );

		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return '#ffffff';
		}

		$channel = static function ( string $part ): float {
			$c = hexdec( $part ) / 255;

			return $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
		};

		$luminance = 0.2126 * $channel( substr( $hex, 0, 2 ) ) + 0.7152 * $channel( substr( $hex, 2, 2 ) ) + 0.0722 * $channel( substr( $hex, 4, 2 ) );

		// Contraste con blanco frente a contraste con casi negro: gana el mayor.
		return ( 1.05 / ( $luminance + 0.05 ) ) >= ( ( $luminance + 0.05 ) / ( 0.0065 + 0.05 ) ) ? '#ffffff' : '#111111';
	}

	/**
	 * Human total duration: "18 h", "1 h 30 min", "45 min".
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	public static function duration_label( int $minutes ): string {
		if ( $minutes <= 0 ) {
			return '';
		}

		$hours = intdiv( $minutes, 60 );
		$rest  = $minutes % 60;

		if ( 0 === $hours ) {
			/* translators: %d: minutes. */
			return sprintf( __( '%d min', 'aula-virtual' ), $rest );
		}

		/* translators: 1: hours, 2: minutes. */
		return 0 === $rest ? sprintf( __( '%d h', 'aula-virtual' ), $hours ) : sprintf( __( '%1$d h %2$d min', 'aula-virtual' ), $hours, $rest );
	}

	/**
	 * Inline SVG icon (stroke, inherits the text colour).
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	public static function icon( string $name ): string {
		$paths = array(
			'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
			'video'    => '<rect x="2" y="6" width="14" height="12" rx="2"/><path d="m22 8-6 4 6 4V8z"/>',
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'book'     => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
			'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
			'check'    => '<path d="M20 6 9 17l-5-5"/>',
			'play'     => '<circle cx="12" cy="12" r="10"/><path d="m10 8 6 4-6 4V8z"/>',
			'live'     => '<circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49M7.76 16.24a6 6 0 0 1 0-8.49M19.07 4.93a10 10 0 0 1 0 14.14M4.93 19.07a10 10 0 0 1 0-14.14"/>',
			'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8"/>',
			'text'     => '<path d="M4 6h16M4 12h16M4 18h10"/>',
			'arrow'    => '<path d="M5 12h14M12 5l7 7-7 7"/>',
			'down'     => '<path d="m6 9 6 6 6-6"/>',
			'plus'     => '<path d="M12 5v14M5 12h14"/>',
			'award'    => '<circle cx="12" cy="8" r="6"/><path d="M15.48 12.89 17 22l-5-3-5 3 1.52-9.11"/>',
			'chat'     => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
			'star'     => '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return '<svg class="av-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * Embeds a video URL: oEmbed for YouTube and Vimeo, a player for MP4.
	 *
	 * @param string $url Video URL.
	 * @return string
	 */
	public function video_html( string $url ): string {
		return VideoEmbed::render( '', $url );
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
