<?php
/**
 * Order to enrollment bridge.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\WooCommerce;

use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\Events\Events;
use SIQA\AulaVirtual\Core\Logger;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Enrollments\RegistrationRequestRepository;
use SIQA\AulaVirtual\Permissions\Roles;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a paid order into enrollments and a refund into their revocation.
 *
 * Everything here is idempotent: WooCommerce may fire the same status hook
 * more than once, and the enrollment service returns the existing row instead
 * of creating a second one.
 */
final class OrderHandler {

	/**
	 * Order meta marking editions already processed, to avoid repeated emails.
	 */
	private const META_PROCESSED = '_av_enrolled_editions';

	/**
	 * Product link.
	 *
	 * @var ProductLink
	 */
	private ProductLink $products;

	/**
	 * Enrollment rules.
	 *
	 * @var EnrollmentService
	 */
	private EnrollmentService $enrollments;

	/**
	 * Enrollment persistence.
	 *
	 * @var EnrollmentRepository
	 */
	private EnrollmentRepository $enrollment_rows;

	/**
	 * Request persistence.
	 *
	 * @var RegistrationRequestRepository
	 */
	private RegistrationRequestRepository $requests;

	/**
	 * Domain events.
	 *
	 * @var EventBus
	 */
	private EventBus $events;

	/**
	 * Plugin logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param ProductLink                   $products        Product link.
	 * @param EnrollmentService             $enrollments     Enrollment rules.
	 * @param EnrollmentRepository          $enrollment_rows Enrollment persistence.
	 * @param RegistrationRequestRepository $requests        Request persistence.
	 * @param EventBus                      $events          Domain events.
	 * @param Logger                        $logger          Plugin logger.
	 */
	public function __construct(
		ProductLink $products,
		EnrollmentService $enrollments,
		EnrollmentRepository $enrollment_rows,
		RegistrationRequestRepository $requests,
		EventBus $events,
		Logger $logger
	) {
		$this->products        = $products;
		$this->enrollments     = $enrollments;
		$this->enrollment_rows = $enrollment_rows;
		$this->requests        = $requests;
		$this->events          = $events;
		$this->logger          = $logger;
	}

	/**
	 * Enrolls the buyer when an order reaches a paid status.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function handle_paid( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( ! Settings::should_enroll( $order->get_status(), Settings::enroll_trigger() ) ) {
			return;
		}

		$editions = $this->editions_in_order( $order );

		if ( array() === $editions ) {
			return;
		}

		$processed = $order->get_meta( self::META_PROCESSED, true );
		$processed = is_array( $processed ) ? array_map( 'intval', $processed ) : array();

		$user_id = $this->buyer_user_id( $order );

		if ( $user_id instanceof WP_Error ) {
			$order->add_order_note( __( 'Aula Virtual: no se pudo crear la cuenta del alumno. ', 'aula-virtual' ) . $user_id->get_error_message() );
			$this->logger->error( 'No se pudo crear el usuario del pedido.', array( 'object_type' => 'order', 'object_id' => $order_id ), 'woocommerce' );

			return;
		}

		foreach ( $editions as $edition_id ) {
			if ( in_array( $edition_id, $processed, true ) ) {
				continue;
			}

			$result = $this->enrollments->enroll(
				$user_id,
				$edition_id,
				EnrollmentStatus::SOURCE_WOOCOMMERCE,
				array(
					'order_id' => $order_id,
					'status'   => EnrollmentStatus::ACTIVE,
				)
			);

			if ( $result instanceof WP_Error ) {
				$order->add_order_note(
					sprintf(
						/* translators: 1: edition id, 2: error message. */
						__( 'Aula Virtual: no se pudo matricular en la edición %1$d. %2$s', 'aula-virtual' ),
						$edition_id,
						$result->get_error_message()
					)
				);
				$this->logger->error( 'Matricula por pedido rechazada.', array( 'object_type' => 'order', 'object_id' => $order_id, 'edition_id' => $edition_id, 'code' => $result->get_error_code() ), 'woocommerce' );

				continue;
			}

			$this->close_open_request( $order->get_billing_email(), $edition_id, $user_id );

			$processed[] = $edition_id;

			$order->add_order_note(
				sprintf(
					/* translators: %d: edition id. */
					__( 'Aula Virtual: alumno matriculado en la edición %d.', 'aula-virtual' ),
					$edition_id
				)
			);

			$this->logger->info( 'Matricula creada desde pedido.', array( 'object_type' => 'order', 'object_id' => $order_id, 'edition_id' => $edition_id, 'user_id' => $user_id ), 'woocommerce' );
		}

		$order->update_meta_data( self::META_PROCESSED, $processed );
		$order->save();

		$this->events->dispatch(
			Events::ORDER_PROCESSED,
			array(
				'order_id'    => $order_id,
				'user_id'     => $user_id,
				'edition_ids' => $processed,
				'silent'      => true,
			)
		);
	}

	/**
	 * Applies the refund policy when an order is refunded or cancelled.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function handle_revoked( int $order_id ): void {
		$status = Settings::status_for_refund( Settings::refund_policy() );

		if ( '' === $status ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$rows = $this->enrollment_rows->all(
			array(
				'where' => array( 'order_id' => $order_id ),
				'limit' => 50,
			)
		);

		foreach ( $rows as $row ) {
			if ( in_array( (string) $row['status'], array( EnrollmentStatus::CANCELLED, EnrollmentStatus::SUSPENDED ), true ) ) {
				continue;
			}

			$this->enrollments->change_status( (int) $row['id'], $status );
		}

		if ( array() !== $rows ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: enrollment status label. */
					__( 'Aula Virtual: matrículas del pedido pasadas a "%s".', 'aula-virtual' ),
					EnrollmentStatus::label( $status )
				)
			);
		}
	}

	/**
	 * Prints the campus access box on the thank-you page.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function thank_you( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order || array() === $this->editions_in_order( $order ) ) {
			return;
		}

		$pages   = get_option( 'av_pages', array() );
		$page_id = is_array( $pages ) && isset( $pages['campus'] ) ? (int) $pages['campus'] : 0;
		$campus  = $page_id > 0 ? (string) get_permalink( $page_id ) : home_url( '/campus/' );
		?>
		<section class="av-thankyou" style="margin:24px 0;padding:20px;border:1px solid #e5e7eb;border-radius:8px;">
			<h2><?php esc_html_e( 'Tu acceso al curso', 'aula-virtual' ); ?></h2>
			<p><?php esc_html_e( 'Apenas se confirme el pago recibirás un correo con el enlace para crear tu contraseña y entrar al campus.', 'aula-virtual' ); ?></p>
			<p><a class="button" href="<?php echo esc_url( $campus ); ?>"><?php esc_html_e( 'Ir al campus', 'aula-virtual' ); ?></a></p>
		</section>
		<?php
	}

	/**
	 * Edition ids sold by the items of an order, without duplicates.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<int, int>
	 */
	private function editions_in_order( \WC_Order $order ): array {
		$editions = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$product_id = (int) $item->get_variation_id() > 0 ? (int) $item->get_variation_id() : (int) $item->get_product_id();
			$edition_id = $this->products->edition_for_product( $product_id );

			if ( $edition_id > 0 ) {
				$editions[] = $edition_id;
			}
		}

		return array_values( array_unique( $editions ) );
	}

	/**
	 * Finds the buyer's account or creates one from the billing details.
	 *
	 * Guest checkout is allowed on purpose: asking for an account before
	 * paying costs sales. The account is created without a known password and
	 * the welcome email carries the link to set it.
	 *
	 * @param \WC_Order $order Order.
	 * @return int|WP_Error User id.
	 */
	private function buyer_user_id( \WC_Order $order ) {
		$customer_id = (int) $order->get_customer_id();

		if ( $customer_id > 0 ) {
			return $customer_id;
		}

		$email = sanitize_email( $order->get_billing_email() );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'av_missing_email', __( 'El pedido no tiene un correo válido.', 'aula-virtual' ) );
		}

		$existing = get_user_by( 'email', $email );

		if ( false !== $existing ) {
			return (int) $existing->ID;
		}

		$base  = sanitize_user( strstr( $email, '@', true ) ?: $email, true );
		$base  = '' === $base ? 'alumno' : $base;
		$login = $base;
		$i     = 2;

		while ( username_exists( $login ) ) {
			$login = $base . $i;
			++$i;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'first_name'   => sanitize_text_field( $order->get_billing_first_name() ),
				'last_name'    => sanitize_text_field( $order->get_billing_last_name() ),
				'display_name' => trim( sanitize_text_field( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ),
				'role'         => Roles::STUDENT,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Contrasena aleatoria y desconocida: el alumno debe crear la suya.
		\SIQA\AulaVirtual\Students\AccountHelper::mark_needs_password( (int) $user_id );

		$phone = sanitize_text_field( $order->get_billing_phone() );

		if ( '' !== $phone ) {
			update_user_meta( (int) $user_id, 'av_phone', $phone );
		}

		$order->set_customer_id( (int) $user_id );

		return (int) $user_id;
	}

	/**
	 * Marks as enrolled the registration request the payment was answering.
	 *
	 * @param string $email      Buyer email.
	 * @param int    $edition_id Edition id.
	 * @param int    $user_id    Buyer user id.
	 * @return void
	 */
	private function close_open_request( string $email, int $edition_id, int $user_id ): void {
		$email = sanitize_email( $email );

		if ( '' === $email ) {
			return;
		}

		$request = $this->requests->find_open( $email, $edition_id );

		if ( null === $request ) {
			return;
		}

		$this->requests->update(
			(int) $request['id'],
			array(
				'status'  => RegistrationRequestRepository::STATUS_ENROLLED,
				'user_id' => $user_id,
			)
		);
	}
}
