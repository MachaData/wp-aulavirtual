<?php
/**
 * Report exports.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Reports;

use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns report rows into files an administrator can download.
 *
 * The CSV is built for Excel in a Spanish locale: UTF-8 with BOM so accents
 * survive, and ";" as delimiter because "," is the decimal separator there.
 * The formatting helpers are static and free of WordPress calls so they can
 * be exercised from the smoke test.
 */
final class ReportService {

	/**
	 * CSV delimiter.
	 */
	public const DELIMITER = ';';

	/**
	 * UTF-8 byte order mark.
	 */
	public const BOM = "\xEF\xBB\xBF";

	/**
	 * Date format used inside the CSV.
	 */
	public const DATE_FORMAT = 'Y-m-d H:i';

	/**
	 * Report queries.
	 *
	 * @var ReportRepository
	 */
	private ReportRepository $reports;

	/**
	 * Constructor.
	 *
	 * @param ReportRepository $reports Report queries.
	 */
	public function __construct( ReportRepository $reports ) {
		$this->reports = $reports;
	}

	/**
	 * Builds the CSV with the students of an edition.
	 *
	 * @param int $edition_id Edition id.
	 * @return string
	 */
	public function csv_students( int $edition_id ): string {
		$rows = array();

		foreach ( $this->reports->students( $edition_id ) as $student ) {
			$rows[] = self::student_row( $student );
		}

		return self::to_csv( self::students_header(), $rows );
	}

	/**
	 * File name of the students export.
	 *
	 * @param array<string, mixed> $edition Edition row.
	 * @return string
	 */
	public function csv_filename( array $edition ): string {
		$code = trim( (string) ( $edition['code'] ?? '' ) );

		if ( '' === $code ) {
			$code = 'edicion-' . (int) ( $edition['id'] ?? 0 );
		}

		return sanitize_file_name( sprintf( 'alumnos-%s-%s.csv', $code, gmdate( 'Ymd' ) ) );
	}

	/**
	 * Header row of the students export.
	 *
	 * @return array<int, string>
	 */
	public static function students_header(): array {
		return array(
			'ID usuario',
			'Nombre',
			'Apellidos',
			'Nombre visible',
			'Email',
			'Telefono',
			'Estado',
			'Origen',
			'Progreso (%)',
			'Matriculado el',
			'Completado el',
			'Ultima actividad',
		);
	}

	/**
	 * Maps a students() row to the CSV columns.
	 *
	 * @param array<string, mixed> $student Row from ReportRepository::students().
	 * @return array<int, string>
	 */
	public static function student_row( array $student ): array {
		return array(
			(string) (int) ( $student['user_id'] ?? 0 ),
			(string) ( $student['first_name'] ?? '' ),
			(string) ( $student['last_name'] ?? '' ),
			(string) ( $student['display_name'] ?? '' ),
			(string) ( $student['email'] ?? '' ),
			(string) ( $student['phone'] ?? '' ),
			EnrollmentStatus::label( (string) ( $student['status'] ?? '' ) ),
			(string) ( $student['source'] ?? '' ),
			number_format( (float) ( $student['progress_percentage'] ?? 0 ), 2, '.', '' ),
			self::format_date( $student['enrolled_at'] ?? null ),
			self::format_date( $student['completed_at'] ?? null ),
			self::format_date( $student['last_activity'] ?? null ),
		);
	}

	/**
	 * Formats a MySQL datetime for the CSV, empty when missing.
	 *
	 * @param mixed $value Raw datetime.
	 * @return string
	 */
	public static function format_date( mixed $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value || str_starts_with( $value, '0000-00-00' ) ) {
			return '';
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? '' : gmdate( self::DATE_FORMAT, $timestamp );
	}

	/**
	 * Serialises header and rows as a UTF-8 CSV with BOM and ";" delimiter.
	 *
	 * Pure: no WordPress calls, so it can be unit tested.
	 *
	 * @param array<int, string>             $header Column names.
	 * @param array<int, array<int, string>> $rows   Data rows.
	 * @return string
	 */
	public static function to_csv( array $header, array $rows ): string {
		$handle = fopen( 'php://temp', 'r+' );

		if ( false === $handle ) {
			return self::BOM;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- in-memory stream.
		fputcsv( $handle, array_map( 'strval', $header ), self::DELIMITER, '"', '\\', "\r\n" );

		foreach ( $rows as $row ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- in-memory stream.
			fputcsv( $handle, array_map( 'strval', $row ), self::DELIMITER, '"', '\\', "\r\n" );
		}

		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- in-memory stream.

		return self::BOM . ( false === $csv ? '' : $csv );
	}
}
