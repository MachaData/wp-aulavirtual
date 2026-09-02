<?php
/**
 * Base repository for custom tables.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Database;

use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared persistence behaviour for the plugin custom tables.
 *
 * Every column that can be written or filtered has to be declared in
 * {@see Repository::columns()} together with its placeholder. Anything outside
 * that map is rejected, which keeps identifiers out of interpolated SQL and
 * values inside $wpdb->prepare().
 */
abstract class Repository {

	/**
	 * Table definitions.
	 *
	 * @var Schema
	 */
	protected Schema $schema;

	/**
	 * Constructor.
	 *
	 * @param Schema $schema Table definitions.
	 */
	public function __construct( Schema $schema ) {
		$this->schema = $schema;
	}

	/**
	 * Logical table name handled by the repository.
	 *
	 * @return string
	 */
	abstract protected function table_name(): string;

	/**
	 * Writable and filterable columns mapped to their $wpdb placeholder.
	 *
	 * @return array<string, string>
	 */
	abstract protected function columns(): array;

	/**
	 * Returns the fully qualified table name.
	 *
	 * @return string
	 */
	public function table(): string {
		return $this->schema->table( $this->table_name() );
	}

	/**
	 * Finds a single row by primary key.
	 *
	 * @param int $id Row id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from the schema map.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Finds the first row matching a set of equality conditions.
	 *
	 * @param array<string, mixed> $where Conditions keyed by column.
	 * @return array<string, mixed>|null
	 */
	public function first( array $where ): ?array {
		$rows = $this->all(
			array(
				'where' => $where,
				'limit' => 1,
			)
		);

		return $rows[0] ?? null;
	}

	/**
	 * Returns rows matching the given arguments.
	 *
	 * @param array{where?: array<string, mixed>, order_by?: string, order?: string, limit?: int, offset?: int} $args Query arguments.
	 * @return array<int, array<string, mixed>>
	 */
	public function all( array $args = array() ): array {
		global $wpdb;

		$table  = $this->table();
		$values = array();
		$sql    = "SELECT * FROM {$table}" . $this->where_clause( $args['where'] ?? array(), $values );
		$sql   .= $this->order_clause( $args['order_by'] ?? 'id', $args['order'] ?? 'DESC' );

		$limit  = isset( $args['limit'] ) ? max( 0, (int) $args['limit'] ) : 0;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		if ( $limit > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$values[] = $limit;
			$values[] = $offset;
		}

		if ( array() !== $values ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders were built from the column map.
			$sql = $wpdb->prepare( $sql, $values );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Counts rows matching a set of conditions.
	 *
	 * @param array<string, mixed> $where Conditions keyed by column.
	 * @return int
	 */
	public function count( array $where = array() ): int {
		global $wpdb;

		$table  = $this->table();
		$values = array();
		$sql    = "SELECT COUNT(*) FROM {$table}" . $this->where_clause( $where, $values );

		if ( array() !== $values ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders were built from the column map.
			$sql = $wpdb->prepare( $sql, $values );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Returns a page of rows together with the total row count.
	 *
	 * @param array<string, mixed> $args     Query arguments accepted by all().
	 * @param int                  $page     Page number, 1 based.
	 * @param int                  $per_page Rows per page.
	 * @return array{items: array<int, array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
	 */
	public function paginate( array $args = array(), int $page = 1, int $per_page = 20 ): array {
		$page     = max( 1, $page );
		$per_page = min( 200, max( 1, $per_page ) );
		$where    = $args['where'] ?? array();
		$total    = $this->count( $where );

		$args['limit']  = $per_page;
		$args['offset'] = ( $page - 1 ) * $per_page;

		return array(
			'items'    => $this->all( $args ),
			'total'    => $total,
			'pages'    => (int) ceil( $total / $per_page ),
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Inserts a row.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int Inserted id, 0 on failure.
	 */
	public function insert( array $data ): int {
		global $wpdb;

		$data    = $this->filter_columns( $data );
		$formats = $this->formats_for( $data );

		if ( array() === $data ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table write.
		$inserted = $wpdb->insert( $this->table(), $data, $formats );

		return false === $inserted ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Updates a row by primary key.
	 *
	 * @param int                  $id   Row id.
	 * @param array<string, mixed> $data Column values.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$data = $this->filter_columns( $data );

		if ( array() === $data || $id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table write.
		$updated = $wpdb->update(
			$this->table(),
			$data,
			array( 'id' => $id ),
			$this->formats_for( $data ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Deletes a row by primary key.
	 *
	 * @param int $id Row id.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		if ( $id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table write.
		return false !== $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Keeps only the values whose column is declared by the repository.
	 *
	 * @param array<string, mixed> $data Raw values.
	 * @return array<string, mixed>
	 */
	protected function filter_columns( array $data ): array {
		return array_intersect_key( $data, $this->columns() );
	}

	/**
	 * Returns the $wpdb placeholders matching a set of columns.
	 *
	 * @param array<string, mixed> $data Values keyed by column.
	 * @return array<int, string>
	 */
	protected function formats_for( array $data ): array {
		$columns = $this->columns();

		return array_values(
			array_map(
				static fn( string $column ): string => $columns[ $column ],
				array_keys( $data )
			)
		);
	}

	/**
	 * Builds a WHERE clause with placeholders and collects its values.
	 *
	 * @param array<string, mixed> $where  Conditions keyed by column.
	 * @param array<int, mixed>    $values Collected values, passed by reference.
	 * @return string
	 * @throws InvalidArgumentException When a column is not declared.
	 */
	protected function where_clause( array $where, array &$values ): string {
		if ( array() === $where ) {
			return '';
		}

		$columns = $this->columns();
		$parts   = array();

		foreach ( $where as $column => $value ) {
			if ( ! isset( $columns[ $column ] ) ) {
				throw new InvalidArgumentException(
					sprintf( 'Aula Virtual: columna "%s" no declarada en %s.', esc_html( (string) $column ), esc_html( static::class ) )
				);
			}

			if ( null === $value ) {
				$parts[] = "{$column} IS NULL";
				continue;
			}

			if ( is_array( $value ) ) {
				if ( array() === $value ) {
					$parts[] = '1 = 0';
					continue;
				}

				$placeholders = implode( ', ', array_fill( 0, count( $value ), $columns[ $column ] ) );
				$parts[]      = "{$column} IN ({$placeholders})";

				foreach ( $value as $item ) {
					$values[] = $item;
				}

				continue;
			}

			$parts[]  = "{$column} = {$columns[ $column ]}";
			$values[] = $value;
		}

		return ' WHERE ' . implode( ' AND ', $parts );
	}

	/**
	 * Builds a validated ORDER BY clause.
	 *
	 * @param string $order_by Column to sort by.
	 * @param string $order    ASC or DESC.
	 * @return string
	 */
	protected function order_clause( string $order_by, string $order ): string {
		$columns = $this->columns();

		if ( 'id' !== $order_by && ! isset( $columns[ $order_by ] ) ) {
			$order_by = 'id';
		}

		$order = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		return " ORDER BY {$order_by} {$order}";
	}
}
