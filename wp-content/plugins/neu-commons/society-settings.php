<?php
/**
 * Society Settings
 *
 * Register member types and group types for all societies
 *
 * @package    Humanities Commons
 * @subpackage Configuration
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function hcommons_register_member_types() {

	bp_register_member_type(
		'globalresilience',
		array(
			'labels'        => array(
				'name'          => 'GR',
				'singular_name' => 'GR',
			),
			'has_directory' => 'globalresilience'
		) );
	bp_register_member_type(
		'nc',
		array(
			'labels'        => array(
				'name'          => 'NC',
				'singular_name' => 'NC',
			),
			'has_directory' => 'nc'
		) );
}
add_action( 'bp_register_member_types', 'hcommons_register_member_types' );

function hcommons_register_group_types() {

	bp_groups_register_group_type(
		'globalresilience',
		array(
			'labels'        => array(
				'name'          => 'GR',
				'singular_name' => 'GR',
			),
			'has_directory' => 'globalresilience'
		) );
	bp_groups_register_group_type(
		'nc',
		array(
			'labels'        => array(
				'name'          => 'NC',
				'singular_name' => 'NC',
			),
			'has_directory' => 'nc'
		) );
}

add_action( 'bp_groups_register_group_types', 'hcommons_register_group_types' );

