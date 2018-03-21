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
		add_action( 'add_user_to_blog', array( $this, 'set_member_type' ) );
		add_action( 'init', array( $this, 'resources_cpt' ) );
		add_filter( 'post_type_link', array( $this, 'resource_link' ), 10, 2 );
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
			'lynxnet' => array(
				'labels'        => array(
					'name'          => 'LN',
					'singular_name' => 'LN',
				),
				'has_directory' => 'lynxnet'
			),
		);
	}

	public function set_member_type( $user_id ) {
		bp_set_member_type( $user_id, Humanities_Commons::$society_id );
	}

	/**
	 * Register resource CPT
	 *
	 * @author Tanner Moushey
	 */
	public function resources_cpt() {

		$labels = array(
			'name'               => _x( 'Resources', 'post type general name', 'neu-lynxnet' ),
			'singular_name'      => _x( 'Resource', 'post type singular name', 'neu-lynxnet' ),
			'menu_name'          => _x( 'Resources', 'admin menu', 'neu-lynxnet' ),
			'name_admin_bar'     => _x( 'Resource', 'add new on admin bar', 'neu-lynxnet' ),
			'add_new'            => _x( 'Add New', 'resource', 'neu-lynxnet' ),
			'add_new_item'       => __( 'Add New Resource', 'neu-lynxnet' ),
			'new_item'           => __( 'New Resource', 'neu-lynxnet' ),
			'edit_item'          => __( 'Edit Resource', 'neu-lynxnet' ),
			'view_item'          => __( 'View Resource', 'neu-lynxnet' ),
			'all_items'          => __( 'All Resources', 'neu-lynxnet' ),
			'search_items'       => __( 'Search Resources', 'neu-lynxnet' ),
			'parent_item_colon'  => __( 'Parent Resources:', 'neu-lynxnet' ),
			'not_found'          => __( 'No resources found.', 'neu-lynxnet' ),
			'not_found_in_trash' => __( 'No resources found in Trash.', 'neu-lynxnet' )
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => 'resource',
				'with_front' => false,
			),
			'capability_type'    => 'post',
			'has_archive'        => 'resources',
			'hierarchical'       => false,
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-book-alt',
			'supports'           => array( 'title', 'editor', 'author' )
		);

		register_post_type( 'resource', $args );

	}

	/**
	 * Use the feed link as the permalink if applicable
	 *
	 * @param $permalink
	 * @param WP_Post $post
	 *
	 * @return mixed
	 * @author Tanner Moushey
	 */
	public function resource_link( $permalink, $post ) {
		if ( 'resource' !== $post->post_type ) {
			return $permalink;
		}

		if ( $feed_link = get_post_meta( $post->ID, 'feed_link', true ) ) {
			return $feed_link;
		}

		return $permalink;
	}
}