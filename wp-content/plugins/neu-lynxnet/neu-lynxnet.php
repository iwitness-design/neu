<?php

/**
 * Plugin Name: NEU LynxNet
 * Description: NEU LynxNet Customizations
 * Version: 1.0
 * Author: Tanner Moushey (iWitness Design)
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

NEU_LynxNet::get_instance();

class NEU_LynxNet {

	/**
	 * @var
	 */
	protected static $_instance;

	/**
	 * Only make one instance of the NEU_LynxNet
	 *
	 * @return NEU_LynxNet
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof NEU_LynxNet ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Add Hooks and Actions
	 */
	protected function __construct() {
		add_filter( 'neu_get_societies', array( $this, 'lynxnet_society' ) );
	}

	/**
	 * @param $societies
	 * 
	 *
	 * @since  1.0.0
	 *
	 * @return array
	 * @author Tanner Moushey
	 */
	public function lynxnet_society( $societies ) {
		return array(
			'lynxnet'          => array(
				'labels'        => array(
					'name'          => 'LN',
					'singular_name' => 'LN',
				),
				'has_directory' => 'lynxnet'
			),
		);
	}

}