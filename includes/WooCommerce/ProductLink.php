<?php
/**
 * Product to edition link.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\WooCommerce;

use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Editions\EditionStatus;
use SIQA\AulaVirtual\Permissions\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the "Aula Virtual" tab to the WooCommerce product and keeps the link
 * between a product and an edition in both directions.
 *
 * One product sells one edition. The next cohort is another product, which
 * WooCommerce duplicates in two clicks; the plugin never creates products.
 */
final class ProductLink {

	public const META_EDITION = '_av_edition_id';
	public const META_COURSE  = '_av_course_id';
	public const PANEL_ID     = 'av_product_data';

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Constructor.
	 *
	 * @param EditionRepository $editions Edition persistence.
	 */
	public function __construct( EditionRepository $editions ) {
		$this->editions = $editions;
	}

	/**
	 * Returns the edition sold by a product, 0 when it sells none.
	 *
	 * The product meta is the source of truth; the `product_id` column of the
	 * edition is a denormalised copy for listings and for the buy button.
	 *
	 * @param int $product_id Product id (a variation resolves to its parent).
	 * @return int
	 */
	public function edition_for_product( int $product_id ): int {
		if ( $product_id <= 0 ) {
			return 0;
		}

		$edition_id = (int) get_post_meta( $product_id, self::META_EDITION, true );

		if ( $edition_id > 0 ) {
			return $edition_id;
		}

		$parent_id = (int) wp_get_post_parent_id( $product_id );

		return $parent_id > 0 ? (int) get_post_meta( $parent_id, self::META_EDITION, true ) : 0;
	}

	/**
	 * One-step checkout URL for an edition, empty when it has no product.
	 *
	 * @param array<string, mixed> $edition Edition row.
	 * @return string
	 */
	public static function buy_url( array $edition ): string {
		$product_id = (int) ( $edition['product_id'] ?? 0 );

		if ( $product_id <= 0 || ! function_exists( 'wc_get_checkout_url' ) ) {
			return '';
		}

		return add_query_arg( array( 'add-to-cart' => $product_id ), wc_get_checkout_url() );
	}

	/**
	 * Adds the tab to the product data box.
	 *
	 * @param array<string, array<string, mixed>> $tabs Existing tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_tab( array $tabs ): array {
		$tabs['aula_virtual'] = array(
			'label'    => __( 'Aula Virtual', 'aula-virtual' ),
			'target'   => self::PANEL_ID,
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 65,
		);

		return $tabs;
	}

	/**
	 * Renders the tab content.
	 *
	 * @return void
	 */
	public function render_panel(): void {
		global $post;

		$product_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
		$current    = (int) get_post_meta( $product_id, self::META_EDITION, true );
		$editions   = $this->editions->all(
			array(
				'where'    => array( 'status' => EditionStatus::enrollable() ),
				'order_by' => 'start_date',
				'order'    => 'DESC',
				'limit'    => 200,
			)
		);

		if ( $current > 0 && ! in_array( $current, array_map( 'intval', wp_list_pluck( $editions, 'id' ) ), true ) ) {
			$linked = $this->editions->find( $current );

			if ( null !== $linked ) {
				array_unshift( $editions, $linked );
			}
		}
		?>
		<div id="<?php echo esc_attr( self::PANEL_ID ); ?>" class="panel woocommerce_options_panel">
			<div class="options_group">
				<p class="form-field">
					<label for="av-edition-select"><?php esc_html_e( 'Edición que vende este producto', 'aula-virtual' ); ?></label>
					<select name="<?php echo esc_attr( self::META_EDITION ); ?>" id="av-edition-select" class="select short">
						<option value="0"><?php esc_html_e( 'Ninguna (producto normal)', 'aula-virtual' ); ?></option>
						<?php foreach ( $editions as $edition ) : ?>
							<option value="<?php echo esc_attr( (string) (int) $edition['id'] ); ?>" <?php selected( $current, (int) $edition['id'] ); ?>>
								<?php echo esc_html( get_the_title( (int) $edition['course_id'] ) . ' — ' . $edition['name'] . ' (' . EditionStatus::label( (string) $edition['status'] ) . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<span class="description" style="display:block;margin-top:6px">
						<?php esc_html_e( 'Al confirmarse el pago, el comprador queda matriculado en esta edición y recibe la bienvenida. Un producto vende una sola edición; para la siguiente cohorte duplica el producto.', 'aula-virtual' ); ?>
					</span>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Saves the link when the product is saved.
	 *
	 * WooCommerce verifies its own nonce before firing this hook; the plugin
	 * checks the capability and sanitises the value.
	 *
	 * @param int $product_id Product id.
	 * @return void
	 */
	public function save( int $product_id ): void {
		if ( ! current_user_can( Capabilities::MANAGE_COMMERCE ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified woocommerce_meta_nonce before this hook.
		$edition_id = isset( $_POST[ self::META_EDITION ] ) ? absint( wp_unslash( $_POST[ self::META_EDITION ] ) ) : 0;
		$previous   = (int) get_post_meta( $product_id, self::META_EDITION, true );

		if ( $previous > 0 && $previous !== $edition_id ) {
			$this->editions->update( $previous, array( 'product_id' => 0 ) );
		}

		if ( 0 === $edition_id ) {
			delete_post_meta( $product_id, self::META_EDITION );
			delete_post_meta( $product_id, self::META_COURSE );

			return;
		}

		$edition = $this->editions->find( $edition_id );

		if ( null === $edition ) {
			return;
		}

		update_post_meta( $product_id, self::META_EDITION, $edition_id );
		update_post_meta( $product_id, self::META_COURSE, (int) $edition['course_id'] );

		$this->editions->update( $edition_id, array( 'product_id' => $product_id ) );
	}
}
