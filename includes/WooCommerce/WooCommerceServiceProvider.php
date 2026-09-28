<?php
/**
 * WooCommerce module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\WooCommerce;

use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\Logger;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Enrollments\RegistrationRequestRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional integration: the plugin works without WooCommerce, and with it
 * a paid order becomes an enrollment.
 */
final class WooCommerceServiceProvider implements ServiceProvider {

	/**
	 * Whether WooCommerce is loaded.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			ProductLink::class,
			static fn( Container $c ): ProductLink => new ProductLink( $c->get( EditionRepository::class ) )
		);

		$container->singleton(
			OrderHandler::class,
			static fn( Container $c ): OrderHandler => new OrderHandler(
				$c->get( ProductLink::class ),
				$c->get( EnrollmentService::class ),
				$c->get( EnrollmentRepository::class ),
				$c->get( RegistrationRequestRepository::class ),
				$c->get( EventBus::class ),
				$c->get( Logger::class )
			)
		);
	}

	/**
	 * Registers the hooks, only when WooCommerce is present.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		if ( ! self::is_active() ) {
			return;
		}

		$products = static fn(): ProductLink => $container->get( ProductLink::class );
		$orders   = static fn(): OrderHandler => $container->get( OrderHandler::class );

		add_filter( 'woocommerce_product_data_tabs', static fn( array $tabs ): array => $products()->add_tab( $tabs ) );
		add_action( 'woocommerce_product_data_panels', static function () use ( $products ): void { $products()->render_panel(); } );
		add_action( 'woocommerce_process_product_meta', static function ( $product_id ) use ( $products ): void { $products()->save( (int) $product_id ); } );

		foreach ( Settings::paid_statuses() as $status ) {
			add_action( 'woocommerce_order_status_' . $status, static function ( $order_id ) use ( $orders ): void { $orders()->handle_paid( (int) $order_id ); } );
		}

		foreach ( Settings::revoking_statuses() as $status ) {
			add_action( 'woocommerce_order_status_' . $status, static function ( $order_id ) use ( $orders ): void { $orders()->handle_revoked( (int) $order_id ); } );
		}

		add_action( 'woocommerce_thankyou', static function ( $order_id ) use ( $orders ): void { $orders()->thank_you( (int) $order_id ); } );
	}
}
