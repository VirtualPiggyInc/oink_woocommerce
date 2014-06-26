<?php
/**
 * Checkout Form
 *
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $woocommerce;

$woocommerce->show_messages();

do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in, the user cannot checkout
if ( ! $checkout->enable_signup && ! $checkout->enable_guest_checkout && ! is_user_logged_in() ) {
    echo apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) );
    return;
}

// filter hook for include new pages inside the payment method
$get_checkout_url = apply_filters( 'woocommerce_get_checkout_url', $woocommerce->cart->get_checkout_url() ); ?>

    <form name="checkout" method="post" class="checkout" action="<?php echo esc_url( $get_checkout_url ); ?>">

        <div id="payment">
            <?php if ($woocommerce->cart->needs_payment()) : ?>
                <ul class="payment_methods methods">
                    <?php
                    $available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
                    if ( ! empty( $available_gateways ) ) {

                        // Chosen Method
                        if ( isset( $woocommerce->session->chosen_payment_method ) && isset( $available_gateways[ $woocommerce->session->chosen_payment_method ] ) ) {
                          //  $available_gateways[ $woocommerce->session->chosen_payment_method ]->set_current();
                        } elseif ( isset( $available_gateways[ get_option( 'woocommerce_default_gateway' ) ] ) ) {
                        //    $available_gateways[ get_option( 'woocommerce_default_gateway' ) ]->set_current();
                        } else {
                       //     current( $available_gateways )->set_current();
                        }

                        foreach ( $available_gateways as $gateway ) {
                            ?>
                            <li>
                                <input type="radio" id="payment_method_<?php echo $gateway->id; ?>" class="input-radio" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $gateway->chosen, true ); ?> />
                                <label for="payment_method_<?php echo $gateway->id; ?>"><?php echo $gateway->get_title(); ?> <?php echo $gateway->get_icon(); ?></label>
                                <?php
                                if ( $gateway->has_fields() || $gateway->get_description() ) :
                                    echo '<div class="payment_box payment_method_' . $gateway->id . '" ' . ( $gateway->chosen ? '' : 'style="display:none;"' ) . '>';
                                    $gateway->payment_fields();
                                    echo '</div>';
                                endif;
                                ?>
                            </li>
                        <?php
                        }
                    } else {

                        if ( ! $woocommerce->customer->get_country() )
                            echo '<p>' . __( 'Please fill in your details above to see available payment methods.', 'woocommerce' ) . '</p>';
                        else
                            echo '<p>' . __( 'Sorry, it seems that there are no available payment methods for your state. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce' ) . '</p>';

                    }
                    ?>
                </ul>
            <?php endif; ?>

            <div class="form-row place-order">

                <noscript><?php _e( 'Since your browser does not support JavaScript, or it is disabled, please ensure you click the <em>Update Totals</em> button before placing your order. You may be charged more than the amount stated above if you fail to do so.', 'woocommerce' ); ?><br/><input type="submit" class="button alt" name="woocommerce_checkout_update_totals" value="<?php _e( 'Update totals', 'woocommerce' ); ?>" /></noscript>

                <?php $woocommerce->nonce_field('process_checkout')?>

                <?php do_action( 'woocommerce_review_order_before_submit' ); ?>

                <?php
                $order_button_text = apply_filters('woocommerce_order_button_text', __( 'Place order', 'woocommerce' ));

                echo apply_filters('woocommerce_order_button_html', '<input type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="' . $order_button_text . '" />' );
                ?>

                <?php if (woocommerce_get_page_id('terms')>0) : ?>
                    <p class="form-row terms">
                        <label for="terms" class="checkbox"><?php _e( 'I have read and accept the', 'woocommerce' ); ?> <a href="<?php echo esc_url( get_permalink(woocommerce_get_page_id('terms')) ); ?>" target="_blank"><?php _e( 'terms &amp; conditions', 'woocommerce' ); ?></a></label>
                        <input type="checkbox" class="input-checkbox" name="terms" <?php checked( isset( $_POST['terms'] ), true ); ?> id="terms" />
                    </p>
                <?php endif; ?>

                <?php do_action( 'woocommerce_review_order_after_submit' ); ?>

            </div>

            <div class="clear"></div>

        </div>

        <?php if ( sizeof( $checkout->checkout_fields ) > 0 ) : ?>

            <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

            <div class="col2-set" id="customer_details">

                <div class="col-1">

                    <?php do_action( 'woocommerce_checkout_billing' ); ?>

                </div>

                <div class="col-2">

                    <?php do_action( 'woocommerce_checkout_shipping' ); ?>

                </div>

            </div>

            <?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

            <h3 id="order_review_heading"><?php _e( 'Your order', 'woocommerce' ); ?></h3>

        <?php endif; ?>

        <?php do_action( 'woocommerce_checkout_order_review' ); ?>

    </form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>