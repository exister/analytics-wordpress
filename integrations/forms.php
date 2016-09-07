<?php

abstract class Segment_Forms {

	protected $registered_events = array();

	/**
	 * Sets the default registered events and returns the object.
	 */
	public function __construct() {
		$this->registered_events = array(
			//'viewed_form',
			'submitted_form',
		);

		return $this;
	}

	/**
	 * Registers hooks for the Segment Form system.
	 *
	 * Usable by plugins to register methods or functions to hook into different Form events.
	 * Someday, late static binding will be available to all WordPress users, which will make this a bit less hacky.
	 *
	 * @param  string $hook  The WordPress action ( e.g. do_action( '' ) )
	 * @param  string $event The name of the function or method that handles the tracking output.
	 * @param  object $class The class, if any, that contains the $event method.
	 *
	 * @return mixed  $registered False if no event was registered, string if function was registered, array if method.
	 */
	public function register_hook( $hook, $event, $args = 1, $class = '' ) {

		$registered_events = $this->get_registered_hooks();

		if ( ! in_array( $event, $registered_events ) ) {
			return false;
		}

		if ( ! empty( $class ) && is_callable( array( $class, $event ) ) ) {

			$this->registered_events[ $hook ] = array( $class, $event );

			$registered = add_filter( $hook, array( $class, $event ), 10, $args );

		} else if ( is_callable( $event ) ) {

			$this->registered_events[ $hook ] = $event;
			$registered = add_filter( $hook, $event, 10, $args );

		} else {

			$registered = false;

		}

		return $registered;
	}

	/**
	 * Returns the registered events.
	 *
	 * Sub-classes can filter this to add additional events to be triggered.
	 *
	 * @return array Filtered events.
	 */
	public function get_registered_hooks() {
		return apply_filters( 'segment_form_events', array_filter( $this->registered_events ), $this );
	}

	/**
	 * Basic bootstrap for core integrations.
	 */
	public static function bootstrap() {

		if ( class_exists( 'GFForms' ) ) {
			include_once SEG_FILE_PATH . '/integrations/forms/gravity_forms.php';
		}

	}


	/**
	 * Event to be fired when a form is submitted.
	 */
	abstract function submitted_form();

}

Segment_Forms::bootstrap();