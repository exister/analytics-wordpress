<?php

class Segment_Forms_Gravity extends Segment_Forms {

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

		//$this->register_hook( 'segment_get_current_page_track', 'viewed_form'   , 1, $this );
		$this->register_hook( 'segment_get_current_page_track', 'submitted_form'   , 1, $this );

		add_filter( 'gform_pre_render' , array( $this, 'view_form' )   , 30, 1 );

		add_action( 'gform_after_submission' , array( $this, 'submit_form' )   , 40, 2 );

		add_action( 'gform_entries_first_column', array( $this, 'first_column_content' ), 10, 5 );
	}

	function first_column_content( $form_id, $field_id, $value, $entry, $query_string ) {
		echo 'Sample text.';
	}


	/**
	 * Adds category name to analytics.page()
	 *
	 * @uses  func_get_args() Because our abstract class doesn't know how many parameters are passed to each hook
	 *                        for each different platform, we use func_get_args().
	 *
	 * @return array Filtered array of name and properties for analytics.page().
	 */
	public function viewed_category() {

		$args = func_get_args();
		$page = $args[0];

		if ( is_tax( 'product_cat' ) ) {
				$page = array(
					'page'       => single_term_title( '', false ),
					'properties' => array()
				);
		}

		return $page;
	}

	public function view_form() {

		$args  = func_get_args();
		$form = $args[0];
		//$title = $form['title'];
		Segment_Cookie::set_cookie( 'Viewed Form', json_encode(
				array(
					'id'    => $form['id'],
					'title' => $form['title'],
				)
			)
		);
		return $form;
	}

	public function submit_form() {

		$args  = func_get_args();
		$entry = $args[0];
		$form = $args[0];
		$form = RGFormsModel::get_form_meta($entry['form_id']);

		$fields = array();
		$properties = array();

		if(is_array($form["fields"])){
			foreach($form["fields"] as $field){
				if(isset($field["inputs"]) && is_array($field["inputs"])){

					foreach($field["inputs"] as $input)
						$fields[] =  array($input["id"], GFCommon::get_label($field, $input["id"]));
				}
				else if(!rgar($field, 'displayOnly')){
					$fields[] =  array($field["id"], GFCommon::get_label($field));
				}
			}
		}

		if ( is_array( $fields ) ){
			foreach ( $fields as $field ) {
				if ( isset( $entry[ $field[0] ]) && ! empty( $entry[ $field[0] ] )  ) {
					$properties[ $field[1] ] = $entry[ $field[0] ];
				}
			}
		}

		$properties['submitted_form_id'] = $entry['form_id'];
		$properties['submitted_form_title'] = $form['title'];
		Segment_Cookie::set_cookie( 'Submitted Form', json_encode(
				$properties
			)
		);
	}
	public function submitted_form() {

		$args  = func_get_args();
		$track = $args[0];
		if ( false !== ( $cookie = Segment_Cookie::get_cookie( 'Submitted Form' ) ) ) {

			$_form = json_decode( $cookie );

			if ( is_object( $_form ) ) {

				$track = array(
					'event'      => __( 'Submitted Form', 'segment' ),
					'properties' => $_form,
					'http_event' => 'submitted_form'
				);
			}

		}

		return $track;


	}

	/**
	 * Adds form properties to analytics.track() when form is viewed - example, credit earned when form is completed.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @uses  func_get_args() Because our abstract class doesn't know how many parameters are passed to each hook
	 *                        for each different platform, we use func_get_args().
	 *
	 * @return array Filtered array of name and properties for analytics.track().
	 */
	public function viewed_form() {

		$args  = func_get_args();
		$track = $args[0];

		if ( false !== ( $cookie = Segment_Cookie::get_cookie( 'Viewed Form' ) ) ) {

			$_form = json_decode( $cookie );

			if ( is_object( $_form ) ) {
				$track['properties']['viewed_form_id'] = $_form->id;
				$track['properties']['viewed_form_title'] = $_form->title;
			}

		}

		return $track;


	}
}

/**
 * Bootstrapper for the Segment_Forms_Gravity class.
 *
 * @since  1.0.0
 */
function segment_forms_gravity() {
	$commerce = new Segment_Forms_Gravity();

	return $commerce->init();
}

add_action( 'plugins_loaded', 'segment_forms_gravity', 100 );