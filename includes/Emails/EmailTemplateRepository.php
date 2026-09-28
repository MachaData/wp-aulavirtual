<?php
/**
 * Email template persistence.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Emails;

use SIQA\AulaVirtual\Database\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the editable email templates.
 */
final class EmailTemplateRepository extends Repository {

	public const RECIPIENT_STUDENT = 'student';
	public const RECIPIENT_ADMIN   = 'admin';

	/**
	 * Logical table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return 'email_templates';
	}

	/**
	 * Writable and filterable columns.
	 *
	 * @return array<string, string>
	 */
	protected function columns(): array {
		return array(
			'id'         => '%d',
			'event'      => '%s',
			'recipient'  => '%s',
			'subject'    => '%s',
			'body'       => '%s',
			'enabled'    => '%d',
			'updated_at' => '%s',
			'updated_by' => '%d',
		);
	}

	/**
	 * Returns the enabled templates for an event.
	 *
	 * @param string $event Event name.
	 * @return array<int, array<string, mixed>>
	 */
	public function enabled_for_event( string $event ): array {
		return $this->all(
			array(
				'where' => array(
					'event'   => $event,
					'enabled' => 1,
				),
				'limit' => 10,
			)
		);
	}

	/**
	 * Finds a template by event and recipient.
	 *
	 * @param string $event     Event name.
	 * @param string $recipient Recipient type.
	 * @return array<string, mixed>|null
	 */
	public function find_for( string $event, string $recipient ): ?array {
		return $this->first(
			array(
				'event'     => $event,
				'recipient' => $recipient,
			)
		);
	}

	/**
	 * Every template, ordered by event.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all_ordered(): array {
		return $this->all(
			array(
				'order_by' => 'event',
				'order'    => 'ASC',
				'limit'    => 200,
			)
		);
	}
}
