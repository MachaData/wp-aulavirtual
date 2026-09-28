<?php
/**
 * Spreadsheet parsing for student imports.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Imports;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads CSV natively and XLSX through PhpSpreadsheet when it is installed.
 *
 * Returns a header row plus data rows as plain string arrays; nothing here
 * touches the database or sends email.
 */
final class ImportParser {

	/**
	 * Maximum rows accepted in one file.
	 */
	public const MAX_ROWS = 5000;

	/**
	 * Largest file accepted (bytes).
	 */
	public const MAX_BYTES = 5242880;

	/**
	 * Parses an uploaded file.
	 *
	 * @param string $path     Local file path.
	 * @param string $filename Original file name (for the extension).
	 * @return array{headers: array<int, string>, rows: array<int, array<int, string>>}|WP_Error
	 */
	public static function parse( string $path, string $filename ) {
		$extension = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'av_import_unreadable', __( 'No se pudo leer el archivo.', 'aula-virtual' ) );
		}

		if ( (int) filesize( $path ) > self::MAX_BYTES ) {
			return new WP_Error( 'av_import_too_large', __( 'El archivo es demasiado grande (maximo 5 MB).', 'aula-virtual' ) );
		}

		switch ( $extension ) {
			case 'csv':
			case 'txt':
				$table = self::parse_csv( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temp file.
				break;
			case 'xlsx':
				$table = self::parse_xlsx( $path );
				break;
			default:
				return new WP_Error( 'av_import_format', __( 'Formato no admitido: usa .csv o .xlsx.', 'aula-virtual' ) );
		}

		if ( $table instanceof WP_Error ) {
			return $table;
		}

		if ( array() === $table ) {
			return new WP_Error( 'av_import_empty', __( 'El archivo esta vacio.', 'aula-virtual' ) );
		}

		$headers = array_map( static fn( $h ): string => trim( (string) $h ), array_shift( $table ) );
		$rows    = array();

		foreach ( $table as $row ) {
			$row = array_map( static fn( $c ): string => trim( (string) $c ), $row );

			if ( '' === implode( '', $row ) ) {
				continue;
			}

			$rows[] = array_pad( $row, count( $headers ), '' );

			if ( count( $rows ) >= self::MAX_ROWS ) {
				break;
			}
		}

		return array(
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	/**
	 * Parses CSV text, detecting the delimiter and stripping a BOM.
	 *
	 * @param string $content File content.
	 * @return array<int, array<int, string>>
	 */
	public static function parse_csv( string $content ): array {
		$content = preg_replace( '/^\xEF\xBB\xBF/', '', $content ) ?? $content;

		if ( '' === trim( $content ) ) {
			return array();
		}

		$first     = strtok( $content, "\r\n" );
		$delimiter = ',';
		$best      = -1;

		foreach ( array( ',', ';', "\t", '|' ) as $candidate ) {
			$count = substr_count( (string) $first, $candidate );

			if ( $count > $best ) {
				$best      = $count;
				$delimiter = $candidate;
			}
		}

		$rows   = array();
		$handle = fopen( 'php://memory', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory stream.

		if ( false === $handle ) {
			return array();
		}

		fwrite( $handle, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- in-memory stream.
		rewind( $handle );

		while ( ( $row = fgetcsv( $handle, 0, $delimiter, '"', '\\' ) ) !== false ) {
			if ( count( $rows ) > self::MAX_ROWS ) {
				break;
			}

			$rows[] = array_map( static fn( $c ): string => (string) $c, $row );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- in-memory stream.

		return $rows;
	}

	/**
	 * Parses the first sheet of an XLSX file with PhpSpreadsheet.
	 *
	 * @param string $path File path.
	 * @return array<int, array<int, string>>|WP_Error
	 */
	private static function parse_xlsx( string $path ) {
		if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
			return new WP_Error(
				'av_import_xlsx_unavailable',
				__( 'Para leer .xlsx hace falta instalar las dependencias del plugin (composer install). Mientras tanto, guarda el archivo como CSV.', 'aula-virtual' )
			);
		}

		try {
			$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader( 'Xlsx' );
			$reader->setReadDataOnly( true );
			$sheet = $reader->load( $path )->getSheet( 0 );

			$rows = array();

			foreach ( $sheet->toArray( '', false, false, false ) as $row ) {
				$rows[] = array_map( static fn( $c ): string => (string) $c, (array) $row );
			}

			return $rows;
		} catch ( \Throwable $e ) {
			return new WP_Error( 'av_import_xlsx_error', __( 'No se pudo leer el .xlsx.', 'aula-virtual' ) );
		}
	}

	/**
	 * Guesses which column holds each field from the header names.
	 *
	 * @param array<int, string> $headers Header row.
	 * @return array<string, int> Field => column index (only the ones found).
	 */
	public static function guess_mapping( array $headers ): array {
		$synonyms = array(
			'email'      => array( 'email', 'e-mail', 'email address', 'correo', 'correo electronico', 'mail' ),
			'first_name' => array( 'first_name', 'firstname', 'first name', 'nombre', 'nombres', 'name' ),
			'last_name'  => array( 'last_name', 'lastname', 'last name', 'apellido', 'apellidos', 'surname' ),
			'phone'      => array( 'phone', 'telefono', 'celular', 'whatsapp', 'movil', 'mobile' ),
			'document'   => array( 'document', 'documento', 'dni', 'cedula', 'pasaporte', 'id' ),
		);

		$mapping = array();

		foreach ( $headers as $index => $header ) {
			$key = self::normalize( $header );

			foreach ( $synonyms as $field => $names ) {
				if ( isset( $mapping[ $field ] ) ) {
					continue;
				}

				if ( in_array( $key, $names, true ) ) {
					$mapping[ $field ] = (int) $index;
					break;
				}
			}
		}

		return $mapping;
	}

	/**
	 * Lower-case, accent-free, trimmed header.
	 *
	 * @param string $header Header.
	 * @return string
	 */
	public static function normalize( string $header ): string {
		$header = strtolower( trim( $header ) );
		$header = strtr( $header, array( 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u' ) );

		return preg_replace( '/\s+/', ' ', $header ) ?? $header;
	}
}
