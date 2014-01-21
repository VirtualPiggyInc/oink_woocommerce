<?php
/**
 * Workaround to fix the unfreeze orders bug
 */
class VP_WC_Order extends  WC_Order{
	/**
	 * Populates an order from the loaded post data.
	 *
	 * @access public
	 * @param mixed $result
	 * @return void
	 */
	function populate( $result ) {
		// Standard post data
		$this->id = $result->ID;
		$this->order_date = $result->post_date;
		$this->modified_date = $result->post_modified;
		$this->customer_note = $result->post_excerpt;
		$this->order_custom_fields = get_post_custom( $this->id );

		// Define the data we're going to load: Key => Default value
		$load_data = apply_filters( 'woocommerce_load_order_data', array(
			'order_key'				=> '',
			'billing_first_name'	=> '',
			'billing_last_name' 	=> '',
			'billing_company'		=> '',
			'billing_address_1'		=> '',
			'billing_address_2'		=> '',
			'billing_city'			=> '',
			'billing_postcode'		=> '',
			'billing_country'		=> '',
			'billing_state' 		=> '',
			'billing_email'			=> '',
			'billing_phone'			=> '',
			'shipping_first_name'	=> '',
			'shipping_last_name'	=> '',
			'shipping_company'		=> '',
			'shipping_address_1'	=> '',
			'shipping_address_2'	=> '',
			'shipping_city'			=> '',
			'shipping_postcode'		=> '',
			'shipping_country'		=> '',
			'shipping_state'		=> '',
			'shipping_method'		=> '',
			'shipping_method_title'	=> '',
			'payment_method'		=> '',
			'payment_method_title' 	=> '',
			'order_discount'		=> '',
			'cart_discount'			=> '',
			'order_tax'				=> '',
			'order_shipping'		=> '',
			'order_shipping_tax'	=> '',
			'order_total'			=> '',
			'customer_user'			=> '',
			'completed_date'		=> $this->modified_date
		) );

		// Load the data from the custom fields
		foreach ( $load_data as $key => $default ) {
			if ( isset( $this->order_custom_fields[ '_' . $key ][0] ) && $this->order_custom_fields[ '_' . $key ][0] !== '' ) {
				$this->$key = $this->order_custom_fields[ '_' . $key ][0];
			} else {
				$this->$key = $default;
			}
		}

		// Aliases
		$this->user_id = (int) $this->customer_user;

		// Get status
		$terms = wp_get_object_terms( $this->id, 'shop_order_status', array('fields' => 'slugs') );
		$this->status = is_array($terms) && isset($terms[0]) ? $terms[0] : 'pending';
	}

}
