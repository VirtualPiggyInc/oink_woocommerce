<?php
/**
 * Review order form
 *
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     1.6.4
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $woocommerce;

$available_methods = $woocommerce->shipping->get_available_shipping_methods();
?>
<div id="order_review">

    <table class="shop_table">
        <thead>
        <tr>
            <th class="product-name"><?php _e( 'Product', 'woocommerce' ); ?></th>
            <th class="product-total"><?php _e( 'Total', 'woocommerce' ); ?></th>
        </tr>
        </thead>
        <tfoot>
        <tr class="cart-subtotal">
            <th><?php _e( 'Cart Subtotal', 'woocommerce' ); ?></th>
            <td><?php echo $woocommerce->cart->get_cart_subtotal(); ?></td>
        </tr>

        <?php if ( $woocommerce->cart->get_discounts_before_tax() ) : ?>

            <tr class="discount">
                <th><?php _e( 'Cart Discount', 'woocommerce' ); ?></th>
                <td>-<?php echo $woocommerce->cart->get_discounts_before_tax(); ?></td>
            </tr>

        <?php endif; ?>

        <?php if ( $woocommerce->cart->needs_shipping() && $woocommerce->cart->show_shipping() ) : ?>

            <?php do_action('woocommerce_review_order_before_shipping'); ?>

            <tr class="shipping">
                <th><?php _e( 'Shipping', 'woocommerce' ); ?></th>
                <td><?php woocommerce_get_template( 'cart/shipping-methods.php', array( 'available_methods' => $available_methods ) ); ?></td>
            </tr>

            <?php do_action('woocommerce_review_order_after_shipping'); ?>

        <?php endif; ?>

        <?php foreach ( $woocommerce->cart->get_fees() as $fee ) : ?>

            <tr class="fee fee-<?php echo $fee->id ?>">
                <th><?php echo $fee->name ?></th>
                <td><?php
                    if ( $woocommerce->cart->tax_display_cart == 'excl' )
                        echo woocommerce_price( $fee->amount );
                    else
                        echo woocommerce_price( $fee->amount + $fee->tax );
                    ?></td>
            </tr>

        <?php endforeach; ?>

        <?php
        // Show the tax row if showing prices exlcusive of tax only
        if ( $woocommerce->cart->tax_display_cart == 'excl' ) {
            foreach ( $woocommerce->cart->get_tax_totals() as $code => $tax ) {
                echo '<tr class="tax-rate tax-rate-' . $code . '">
							<th>' . $tax->label . '</th>
							<td>' . $tax->formatted_amount . '</td>
						</tr>';
            }
        }
        ?>

        <?php if ( $woocommerce->cart->get_discounts_after_tax() ) : ?>

            <tr class="discount">
                <th><?php _e( 'Order Discount', 'woocommerce' ); ?></th>
                <td>-<?php echo $woocommerce->cart->get_discounts_after_tax(); ?></td>
            </tr>

        <?php endif; ?>

        <?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

        <tr class="total">
            <th><strong><?php _e( 'Order Total', 'woocommerce' ); ?></strong></th>
            <td>
                <strong><?php echo $woocommerce->cart->get_total(); ?></strong>
                <?php
                // If prices are tax inclusive, show taxes here
                if ( $woocommerce->cart->tax_display_cart == 'incl' ) {
                    $tax_string_array = array();

                    foreach ( $woocommerce->cart->get_tax_totals() as $code => $tax ) {
                        $tax_string_array[] = sprintf( '%s %s', $tax->formatted_amount, $tax->label );
                    }

                    if ( ! empty( $tax_string_array ) ) {
                        ?><small class="includes_tax"><?php printf( __( '(Includes %s)', 'woocommerce' ), implode( ', ', $tax_string_array ) ); ?></small><?php
                    }
                }
                ?>
            </td>
        </tr>

        <?php do_action( 'woocommerce_review_order_after_order_total' ); ?>

        </tfoot>
        <tbody>
        <?php
        do_action( 'woocommerce_review_order_before_cart_contents' );

        if (sizeof($woocommerce->cart->get_cart())>0) :
            foreach ($woocommerce->cart->get_cart() as $cart_item_key => $values) :
                $_product = $values['data'];
                if ($_product->exists() && $values['quantity']>0) :
                    echo '
								<tr class="' . esc_attr( apply_filters('woocommerce_checkout_table_item_class', 'checkout_table_item', $values, $cart_item_key ) ) . '">
									<td class="product-name">' .
                        apply_filters( 'woocommerce_checkout_product_title', $_product->get_title(), $_product ) . ' ' .
                        apply_filters( 'woocommerce_checkout_item_quantity', '<strong class="product-quantity">&times; ' . $values['quantity'] . '</strong>', $values, $cart_item_key ) .
                        $woocommerce->cart->get_item_data( $values ) .
                        '</td>
                        <td class="product-total">' . apply_filters( 'woocommerce_checkout_item_subtotal', $woocommerce->cart->get_product_subtotal( $_product, $values['quantity'] ), $values, $cart_item_key ) . '</td>
								</tr>';
                endif;
            endforeach;
        endif;

        do_action( 'woocommerce_review_order_after_cart_contents' );
        ?>
        </tbody>
    </table>



</div>
