<?php
/**
 * Plugin orchestrator.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Core;

use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Admin\AdminServiceProvider;
use SIQA\AulaVirtual\Campus\CampusServiceProvider;
use SIQA\AulaVirtual\Courses\CoursesServiceProvider;
use SIQA\AulaVirtual\Curriculum\CurriculumServiceProvider;
use SIQA\AulaVirtual\Database\DatabaseServiceProvider;
use SIQA\AulaVirtual\Database\Migrator;
use SIQA\AulaVirtual\Database\Schema;
use SIQA\AulaVirtual\Editions\EditionsServiceProvider;
use SIQA\AulaVirtual\Enrollments\EnrollmentsServiceProvider;
use SIQA\AulaVirtual\Permissions\PermissionsServiceProvider;
use SIQA\AulaVirtual\Progress\ProgressServiceProvider;
use SIQA\AulaVirtual\REST\RestServiceProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the plugin modules together and boots them on `plugins_loaded`.
 */
final class Plugin {

	/**
	 * Shared instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Registered service providers.
	 *
	 * @var array<int, ServiceProvider>
	 */
	private array $providers = array();

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Builds the container and the base services.
	 */
	private function __construct() {
		$this->container = new Container();
		$this->register_core_services();
	}

	/**
	 * Returns the shared plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Exposes the container to modules and tests.
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Registers and boots every module.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		load_plugin_textdomain( 'aula-virtual', false, dirname( AV_BASENAME ) . '/languages' );

		foreach ( $this->service_providers() as $provider ) {
			$this->providers[] = $provider;
			$provider->register( $this->container );
		}

		foreach ( $this->providers as $provider ) {
			$provider->boot( $this->container );
		}

		/**
		 * Fires once every Aula Virtual module has been registered and booted.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'aula_virtual/booted', $this );
	}

	/**
	 * Binds the services shared by every module.
	 *
	 * @return void
	 */
	private function register_core_services(): void {
		$this->container->singleton( EventBus::class, static fn(): EventBus => new EventBus() );
		$this->container->singleton( Schema::class, static fn(): Schema => new Schema() );
		$this->container->singleton(
			Migrator::class,
			static fn( Container $container ): Migrator => new Migrator( $container->get( Schema::class ) )
		);
		$this->container->singleton(
			Logger::class,
			static fn( Container $container ): Logger => new Logger( $container->get( Schema::class ) )
		);
		$this->container->singleton(
			AuditLog::class,
			static fn( Container $container ): AuditLog => new AuditLog( $container->get( Schema::class ) )
		);
	}

	/**
	 * Builds the list of modules that make up the plugin.
	 *
	 * @return array<int, ServiceProvider>
	 */
	private function service_providers(): array {
		$providers = array(
			new DatabaseServiceProvider(),
			new PermissionsServiceProvider(),
			new CoursesServiceProvider(),
			new EditionsServiceProvider(),
			new CurriculumServiceProvider(),
			new EnrollmentsServiceProvider(),
			new ProgressServiceProvider(),
			new AdminServiceProvider(),
			new CampusServiceProvider(),
			new RestServiceProvider(),
		);

		/**
		 * Filters the service providers loaded by Aula Virtual.
		 *
		 * Add-ons can append their own providers here. Entries that do not
		 * implement the ServiceProvider contract are discarded.
		 *
		 * @param array<int, ServiceProvider> $providers Providers to boot.
		 */
		$providers = apply_filters( 'aula_virtual/service_providers', $providers );

		return array_values(
			array_filter(
				(array) $providers,
				static fn( $provider ): bool => $provider instanceof ServiceProvider
			)
		);
	}
}
