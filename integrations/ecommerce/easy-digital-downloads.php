<?php

class Segment_Commerce_Easy_Digital_Downloads extends Segment_Commerce {

	/**
	 * Init method registers two types of hooks: Standard hooks, and those fired in-between page loads.
	 *
	 * For all our events, we hook into either `segment_get_current_page` or `segment_get_current_page_track`
	 * depending on the API we want to use.
	 *
	 * For events that occur between page loads, we hook into the appropriate action and set a Segment_Cookie
	 * instance to check on the next page load.
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 */
	public function init() {

		$this->register_hook( 'segment_get_current_page'      , 'viewed_category'  , 3, $this );
		$this->register_hook( 'segment_get_current_page_track', 'viewed_product'   , 1, $this );

		// Set the purchase cookie
		add_action( 'edd_complete_purchase', array( $this, 'complete_order' ), 100 );

		// Track the order
		$this->register_hook( 'segment_get_current_page_track', 'completed_order'  , 1, $this );

		// Set the add to cart cookie
		add_action( 'edd_post_add_to_cart', array( $this, 'add_to_cart' ), 10, 2 );

		// Track the action
		$this->register_hook( 'segment_get_current_page_track', 'added_to_cart', 2, $this );

		// Set the remove from cart cookie
		add_action( 'edd_pre_remove_from_cart', array( $this, 'remove_from_cart' ), 10 );

		// Track the action
		$this->register_hook( 'segment_get_current_page_track', 'removed_from_cart', 2, $this );
	}


	/**
	 * Adds category name to analytics.page()
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @uses  func_get_args() Because our abstract class doesn't know how many parameters are passed to each hook
	 *                        for each different platform, we use func_get_args().
	 *
	 * @return array Filtered array of name and properties for analytics.page().
	 */
	public function viewed_category() {

		if ( ! is_tax( array( 'download_tag', 'download_category' ) ) ) {
			$args  = func_get_args();
			return $args[0];
		}

		$term = get_queried_object();

		if( 'download_tag' === $term->taxonomy ) {
			$page_name = __( 'Viewed %s %s Tag', 'segment' );
		} else {
			$page_name = __( 'Viewed %s %s Category', 'segment' );
		}

		return array(
			'page'       => sprintf( $page_name, single_term_title( '', false ), edd_get_label_singular() ),
			'properties' => array(
				'term_id' => $term->term_id,
			),
		);
	}

	/**
	 * Adds product properties to analytics.track() when product added to cart.
	 *
	 * @todo Switch to `edd_add_to_cart_item` filter; it runs for each item added to cart
	 * @todo Update after {@link https://github.com/easydigitaldownloads/easy-digital-downloads/pull/4821} PR #4821 is merged
	 *
	 * @param int $download_id Download IDs to be added to the cart
	 * @param array $options Array of options, such as variable price
	 *
	 * @return void
	 */
	public function add_to_cart( $download_id = 0, $options = array() ) {

		$options = $this->update_add_to_cart_quantity( $options );

		$cart_item_details = array(
			'download_id'       => $download_id,
			'options'  => $options,
		);

		Segment_Cookie::set_cookie( 'added_to_cart', json_encode( $cart_item_details ) );
	}

	/**
	 * EDD doesn't pass the quantity added to the cart in the `edd_post_add_to_cart` action; we need to recreate
	 *
	 * @param array $options `price_id`
	 *
	 * @return array $options, updated with quantity, if set during adding
	 */
	private function update_add_to_cart_quantity( $options ) {

		if( empty( $_POST['post_data'] ) || isset( $options['quantity'] ) ) {
			return $options;
		}

		parse_str( $_POST['post_data'], $post_data );

		if ( ! empty( $post_data['edd_download_quantity'] ) ) {
			$options['quantity'] = absint( $post_data['edd_download_quantity'] );
		}

		if ( isset( $options['price_id'] ) ) {
			$price_id = $options['price_id'];
			if( isset( $post_data[ 'edd_download_quantity_' . $price_id ] ) ) {
				$options['quantity'] = absint( $post_data[ 'edd_download_quantity_' . $price_id ] );
			}
		}

		return $options;
	}


	/**
	 * Adds product properties to analytics.track() when product added to cart.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @uses  func_get_args() Because our abstract class doesn't know how many parameters are passed to each hook
	 *                        for each different platform, we use func_get_args().
	 *
	 * @return array Filtered array of name and properties for analytics.track().
	 */
	public function added_to_cart() {

		$cookie_data = $this->maybe_get_cookie_data( 'added_to_cart' );

		if( ! $cookie_data ) {
			$args = func_get_args();

			return $args[0];
		}

		return array(
			'event'      => sprintf( __( 'Added %s', 'segment' ), edd_get_label_singular() ),
			'properties' => $this->build_download_item( $cookie_data['download_id'], $cookie_data['options'] ),
			'http_event' => 'added_to_cart'
		);
	}

	/**
	 * Adds product information to a Segment_Cookie when item is removed from cart.
	 *
	 * @param int $cart_key the cart key to remove. This key is the numerical index of the item contained within the cart array.
	 * @param int $item_id ID of download removed from cart
	 */
	public function remove_from_cart( $cart_key ) {

		$cart = edd_get_cart_contents();

		$cart_item = isset( $cart[ $cart_key ] ) ? $cart[ $cart_key ] : null;

		if( is_null( $cart_item ) || empty( $cart_item['id'] ) ) {
			return;
		}

		$item_options = isset( $cart_item['options'] ) ? $cart_item['options'] : array();

		$item_options['quantity'] = 0; // That's what the other integrations do...

		$item = array(
			'download_id' => $cart_item['id'],
			'options' => $item_options,
		);

		Segment_Cookie::set_cookie( 'removed_from_cart', json_encode( $item ) );
	}

	/**
	 * Adds product properties to analytics.track() when product added to cart.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @uses  func_get_args() Because our abstract class doesn't know how many parameters are passed to each hook
	 *                        for each different platform, we use func_get_args().
	 *
	 * @return array Filtered array of name and properties for analytics.track().
	 */
	public function removed_from_cart() {

		$cookie_data = $this->maybe_get_cookie_data( 'removed_from_cart' );

		if( ! $cookie_data ) {
			$args = func_get_args();

			return $args[0];
		}

		return array(
			'event'      => sprintf( __( 'Removed %s', 'segment' ), edd_get_label_singular() ),
			'properties' => $this->build_download_item( $cookie_data['download_id'], $cookie_data['options'] ),
			'http_event' => 'removed_from_cart'
		);
	}

	/**
	 * Set cookie to track purchase
	 * @param  int $payment_id
	 * @return null
	 */
	public function complete_order( $payment_id ) {

		// Track the purchase event
		$item = array(
			'payment_id' => $payment_id,
		);

		Segment_Cookie::set_cookie( 'completed_purchase', json_encode( $item ) );
	}

	/**
	 * Adds product properties to analytics.track() when the order is completed successfully.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @uses  func_get_args() Because our abstract class doesn't know how many parameters are passed to each hook
	 *                        for each different platform, we use func_get_args().
	 *
	 * @return array Filtered array of name and properties for analytics.track().
	 */
	public function completed_order() {

		$cookie_data = $this->maybe_get_cookie_data( 'completed_purchase' );

		if( ! $cookie_data ) {
			$args = func_get_args();
			return $args[0];
		}

		$payment_id = $cookie_data['payment_id'];

		$payment_meta = edd_get_payment_meta( $payment_id );

		$payment_meta['id'] = $payment_id;
		$payment_meta['key'] = edd_get_payment_key( $payment_id );
		$payment_meta['payment_number'] = edd_get_payment_number( $payment_id );
		$payment_meta['transaction_id'] = edd_get_payment_transaction_id( $payment_id );
		$payment_meta['subtotal'] = edd_get_payment_subtotal( $payment_id );
		$payment_meta['total'] = edd_get_payment_amount( $payment_id );
		$payment_meta['tax'] = edd_get_payment_tax( $payment_id );
		$payment_meta['currency'] = edd_get_payment_currency_code( $payment_id );
		$payment_meta['gateway'] = edd_get_payment_gateway( $payment_id );

		// User Info doesn't include some details
		$payment_meta['user_info']['ip'] = edd_get_payment_user_ip( $payment_id );
		$payment_meta['user_info']['customer_id'] = edd_get_payment_customer_id( $payment_id );
		$payment_meta['user_info']['is_guest'] = edd_is_guest_payment( $payment_id );

		$track = array(
			'event'      => __( 'Completed Order', 'segment' ),
			'properties' => $payment_meta,
			'http_event' => 'completed_purchase',
		);

		return $track;
	}


	/**
	 * Adds product properties to analytics.track() when product is viewed.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @uses  func_get_args() Because our abstract class doesn't know how many parameters are passed to each hook
	 *                        for each different platform, we use func_get_args().
	 *
	 * @return array Filtered array of name and properties for analytics.track().
	 */
	public function viewed_product() {

		if ( ! is_singular( 'download' ) ) {
			$args  = func_get_args();
			return $args[0];
		}

		$item = $this->build_download_item( get_queried_object_id() );

		unset( $item['quantity'], $item['price_id'] );

		return array(
			'event'      => sprintf( __( 'Viewed %s', 'segment' ), edd_get_label_singular() ),
			'properties' => $item,
		);
	}

	/**
	 * Get the cookie value from the cookie name
	 *
	 * @uses Segment_Cookie::get_cookie()
	 *
	 * @param string $cookie_name Cookie name of cookie to fetch
	 *
	 * @return mixed|false False if cookie is not set or not valid JSON. Cookie data otherwise.
	 */
	function maybe_get_cookie_data( $cookie_name = '' ) {

		$cookie = Segment_Cookie::get_cookie( $cookie_name );

		if( false === $cookie ) {
			return false;
		}

		$cookie_data = is_string( $cookie ) ? json_decode( wp_unslash( $cookie ), true ) : $cookie;

		// Cookie stored incorrectly
		if( is_null( $cookie_data ) ) {
			return false;
		}

		return $cookie_data;
	}

	/**
	 * Helper function to fill in download details based on $download_id and $options
	 *
	 * @param int $download_id ID of the download
	 * @param array $passed_options [optional] Array with `quantity` and `price_id` keys
	 *
	 * @return array Download details, with `id`, `name`, `price`, `price_id`, `quanity`, and `category` keys. Also `sku`, if used on site.
	 */
	private function build_download_item( $download_id = 0, $passed_options = array() ) {

		$default_options = array(
			'quantity' => 1,
			'price_id' => null,
		);

		$download_id = absint( $download_id );

		$options = wp_parse_args( $passed_options, $default_options );

		// Passing `0` doesn't override the default `1`
		if( isset( $passed_options['quantity'] ) ) {
			$options['quantity'] = intval( $passed_options['quantity'] );
		}

		if ( edd_has_variable_prices( $download_id ) && ! is_null( $options['price_id'] ) ) {
			$price = edd_get_price_option_amount( $download_id, $options['price_id'] );
		} else {
			$price = edd_get_download_price( $download_id );
		}

		$item = array(
			'id'       => $download_id,
			'name'     => wp_strip_all_tags( get_the_title( $download_id ), true ),
			'price'    => $price,
			'price_id' => intval( $options['price_id'] ),
			'quantity' => $options['quantity'],
			'category' => implode( ', ', wp_list_pluck( get_the_terms( $download_id, 'download_category' ), 'name' ) ),
		);

		if( edd_use_skus() ) {
			$item['sku'] = edd_get_download_sku( $download_id );
		}

		return $item;
	}

}

/**
 * Bootstrapper for the Segment_Commerce_Easy_Digital_Downloads class.
 *
 * @since  1.0.0
 */
function segment_commerce_easy_digital_downloads() {
	$commerce = new Segment_Commerce_Easy_Digital_Downloads();

	return $commerce->init();
}

add_action( 'plugins_loaded', 'segment_commerce_easy_digital_downloads', 100 );