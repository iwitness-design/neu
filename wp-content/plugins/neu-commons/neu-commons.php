<?php
/**
 * The Humanities Commons Plugin
 *
 * Humanities Commons is a set of functions, filters and actions used to support a specific multi-network BuddyPress configuration.
 *
 * @package    Humanities Commons
 * @subpackage Configuration
 */

/**
 * Plugin Name: NEU Commons
 * Description: NEU Commons is a set of functions, filters and actions used to support a specific multi-network BuddyPress configuration. Forked from Humanities Commons.
 * Version: 1.0
 * Author: Tanner Moushey (iWitness Design)
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( dirname( __FILE__ ) . '/society-settings.php' );
require_once( dirname( __FILE__ ) . '/wpmn-taxonomy-functions.php' );
require_once( dirname( __FILE__ ) . '/admin-toolbar.php' );
require_once( dirname( __FILE__ ) . '/class.comanage-api.php' );
require_once( dirname( __FILE__ ) . '/class.mla-hcommons.php' );
require_once( dirname( __FILE__ ) . '/class-logger.php' );

/**
 * Change BuddyPress default Members landing tab.
 */
if ( ! defined( 'BP_DEFAULT_COMPONENT' ) ) {
	define( 'BP_DEFAULT_COMPONENT', 'profile' );
}

use MLA\Commons\Plugin\Logging\Logger;

global $hcommons_logger;
$hcommons_logger = new Logger( 'hcommons_error' );
$hcommons_logger->createLog( 'hcommons_error' );

/**
 * Write a formatted HCommons error or informational message.
 */
function hcommons_write_error_log( $error_type, $error_message, $info = null ) {

	global $hcommons_logger;
	if ( 'info' === $error_type ) {
		if ( empty( $info ) ) {
			$hcommons_logger->addInfo( $error_message );
		} else {
			$hcommons_logger->addInfo( $error_message . ' : ', $info );
		}
	} else {
		$hcommons_logger->addError( $error_message );
	}
}

class Humanities_Commons {

	/**
	 * the network called "Humanities Commons" a.k.a. the hub
	 */
	public static $main_network;

	/**
	 * root blog of the main network
	 */
	public static $main_site;

	/**
	 * current society id
	 */
	public static $society_id;

	/**
	 * current shib session id
	 */
	public static $shib_session_id;

	public function __construct() {

		if ( ! defined( 'NEU_DEFAULT_SOCIETY' ) ) {
			define( 'NEU_DEFAULT_SOCIETY', 'nc' );
		}

		if ( defined( 'HC_SITE_ID' ) ) {
			self::$main_network = get_network( (int) HC_SITE_ID );
		} else {
			self::$main_network = get_network( (int) '1' );
		}

		self::$main_site  = get_site_by_path( self::$main_network->domain, self::$main_network->path );
		self::$society_id = get_network_option( '', 'society_id' );

		add_shortcode( 'hcommons_society_page', array( $this, 'hcommons_get_society_page_by_slug' ) );
		add_shortcode( 'hcommons_env_variable', array( $this, 'hcommons_get_env_variable' ) );

		add_filter( 'bp_get_taxonomy_term_site_id', array( $this, 'hcommons_filter_bp_taxonomy_storage_site' ), 10, 2 );
		add_filter( 'wpmn_get_taxonomy_term_site_id', array( $this, 'hcommons_filter_hc_taxonomy_storage_site' ), 10, 2 );
		add_action( 'bp_after_has_members_parse_args', array( $this, 'hcommons_set_members_query' ) );
		add_filter( 'bp_before_has_groups_parse_args', array( $this, 'hcommons_set_groups_query_args' ) );
		add_filter( 'groups_get_groups', array( $this, 'hcommons_groups_get_groups' ), 10, 2 );
		add_action( 'groups_create_group_step_save_group-details', array( $this, 'hcommons_set_group_type' ) );
		add_action( 'groups_create_group_step_save_group-details', array( $this, 'hcommons_set_group_mla_oid' ) );
		add_filter( 'invite_anyone_send_follow_requests_on_acceptance', '__return_false' );
		add_filter( 'bp_before_has_blogs_parse_args', array( $this, 'hcommons_set_network_blogs_query' ) );
		add_filter( 'bp_get_total_blog_count', array( $this, 'hcommons_get_total_blog_count' ) );
		add_filter( 'bp_get_total_blog_count_for_user', array( $this, 'hcommons_get_total_blog_count_for_user' ) );
		add_filter( 'bp_before_has_activities_parse_args', array( $this, 'hcommons_set_network_activities_query' ) );
		add_filter( 'bp_activity_get_where_conditions', array( $this, 'hcommons_filter_activity_where_conditions' ) );
		add_action( 'bp_activity_after_save', array( $this, 'hcommons_set_activity_society_meta' ) );
		add_action( 'bp_notification_after_save', array( $this, 'hcommons_set_notification_society_meta' ) );
		add_filter( 'bp_activity_get_permalink', array( $this, 'hcommons_filter_activity_permalink' ), 10, 2 );
		add_filter( 'body_class', array( $this, 'hcommons_society_body_class_name' ) );
		// this filter makes 'bp_xprofile_change_field_visibility' false which is required for profile plugin visibility controls
		// doesn't work with local users without a member type, but also doesn't work when member type & blog_id don't match?
		// should always return true for any logged-in user, since visibility controls on xprofile fields are not restricted
		//add_filter( 'bp_current_user_can', array( $this, 'hcommons_check_site_member_can' ), 10, 4 );
		add_filter( 'bp_get_groups_directory_permalink', array( $this, 'hcommons_set_groups_directory_permalink' ) );
		add_filter( 'bp_get_group_permalink', array( $this, 'hcommons_set_group_permalink' ), 10, 2 );
		add_filter( 'bp_core_get_user_domain', array( $this, 'hcommons_set_members_directory_permalink' ), 10, 4 );
		add_filter( 'get_blogs_of_user', array( $this, 'hcommons_filter_get_blogs_of_user' ), 10, 3 );
		add_filter( 'bp_core_avatar_upload_path', array( $this, 'hcommons_set_bp_core_avatar_upload_path' ) );
		add_filter( 'bp_core_avatar_url', array( $this, 'hcommons_set_bp_core_avatar_url' ) );

		// disable in favor of bp-blog-avatar
		// see https://buddypress.trac.wordpress.org/ticket/6544
		add_filter( 'bp_is_blogs_site-icon_active', '__return_false' );

		add_filter( 'bp_get_group_join_button', array( $this, 'hcommons_check_bp_get_group_join_button' ), 10, 2 );

//		add_action( 'init', array( $this, 'hcommons_shibboleth_autologout' ) );
//		add_action( 'wp_login_failed', array( $this, 'hcommons_login_failed' ) );
//		add_filter( 'wp_safe_redirect_fallback', array( $this, 'hcommons_remove_admin_redirect' ) );
//		add_filter( 'login_redirect', array( $this, 'hcommons_remove_admin_redirect' ) );
//		add_filter( 'shibboleth_session_active', array( $this, 'hcommons_shibboleth_session_active' ) );
//		add_action( 'login_init', array( $this, 'hcommons_login_init' ) );
//		add_filter( 'site_option_shibboleth_login_url', [ $this, 'hcommons_filter_site_option_shibboleth_urls' ] );
//		add_filter( 'site_option_shibboleth_logout_url', [ $this, 'hcommons_filter_site_option_shibboleth_urls' ] );

		// @todo re-enable once we get Shibboleth setup these require shibboleth
		/*
		add_action( 'shibboleth_set_user_roles', array( $this, 'hcommons_set_user_member_types' ) );
		add_action( 'shibboleth_set_user_roles', array( $this, 'hcommons_maybe_set_user_role_for_site' ) );
		add_action( 'shibboleth_set_user_roles', array( $this, 'hcommons_set_shibboleth_based_user_meta' ) );
		add_action( 'shibboleth_set_user_roles', array( $this, 'hcommons_invite_anyone_activate_user' ) );
		add_action( 'shibboleth_set_user_roles', array( $this, 'hcommons_sync_bp_profile' ) );
		add_filter( 'shibboleth_user_email', array( $this, 'hcommons_set_shibboleth_based_user_email' ) );
		add_filter( 'shibboleth_user_role', array( $this, 'hcommons_check_user_site_membership' ) );
		*/

		add_filter( 'bp_get_signup_page', array( $this, 'hcommons_register_url' ) );
		add_action( 'pre_user_query', array( &$this, 'hcommons_filter_site_users_only' ) ); // do_action_ref_array() is used for pre_user_query
		add_filter( 'invite_anyone_is_large_network', '__return_true' ); //hide invite anyone member list on create/edit group screen
		add_action( 'bp_init', array( $this, 'hcommons_remove_nav_items' ) );
		add_action( 'bp_init', array( $this, 'hcommons_set_default_scope_society' ) );
		add_filter( 'bbp_topic_admin_links', array( $this, 'hcommons_topic_admin_links' ), 10, 2 );
		add_filter( 'bbp_reply_admin_links', array( $this, 'hcommons_reply_admin_links' ), 10, 2 );
		add_filter( 'bp_activity_time_since', array( $this, 'hcommons_filter_activity_time_since' ), 10, 2 );
		add_filter( 'bp_attachments_cover_image_upload_dir', array( $this, 'hcommons_cover_image_upload_dir' ), 10, 2 );
		add_filter( 'bp_attachments_uploads_dir_get', array( $this, 'hcommons_attachments_uploads_dir_get' ), 10, 2 );
		add_filter( 'bp_attachment_upload_dir', array( $this, 'hcommons_attachment_upload_dir' ), 10, 2 );

		// replace default bbp notification formatter with our own multinetwork-compatible version
		remove_filter( 'bp_notifications_get_notifications_for_user', 'bbp_format_buddypress_notifications' );
		add_filter( 'bp_notifications_get_notifications_for_user', array( $this, 'hcommons_bbp_format_buddypress_notifications' ) );
		add_filter( 'bp_get_new_group_enable_forum', array( $this, 'hcommons_get_new_group_enable_forum' ) );
		add_action( 'wp_ajax_hcommons_settings_general', array( $this, 'hcommons_settings_general_ajax' ) );
		add_filter( 'bp_before_activity_get_parse_args', array( $this, 'hcommons_set_network_admin_activities_query' ) );
//		add_action( 'init', array( $this, 'hcommons_remove_bp_settings_general' ) );
		add_action( 'bp_before_group_settings_creation_step', array( $this, 'hcommons_groups_group_before_save' ) );
		add_action( 'bp_groups_admin_meta_boxes', array( $this, 'hcommons_remove_group_type_meta_boxes' ) );
		add_action( 'bp_groups_admin_meta_boxes', array( $this, 'hcommons_add_group_type_meta_box' ) );
		add_action( 'bp_members_admin_user_metaboxes', array( $this, 'hcommons_remove_member_type_meta_boxes' ), 10, 2 );
		add_action( 'bp_members_admin_user_metaboxes', array( $this, 'hcommons_add_member_type_meta_box' ), 10, 2 );
		add_action( 'bp_groups_admin_meta_boxes', array( $this, 'hcommons_add_manage_group_memberships_meta_box' ) );
		add_action( 'bp_groups_admin_load', array( $this, 'hcommons_save_managed_group_membership' ) );
		add_filter( 'bp_docs_map_meta_caps', array( $this, 'hcommons_check_docs_new_member_caps' ), 10, 4 );
		add_filter( 'wpmu_active_signup', array( $this, 'hcommons_check_sites_new_member_status' ) );

	}

	/**
	 * Handles saving of manage group metabox
	 *
	 * @return void
	 */
	public function hcommons_save_managed_group_membership() {

		//displays what action we are in
		$action = bp_admin_list_table_current_bulk_action();

		//lets check if the request method and action are on post and save
		if ( $action == 'save' ) {

			//is the new value set?
			if ( isset( $_POST['autopopulate'] ) ) {

				//grabs group_id from get and sanitizes it
				$group_id = filter_var( $_GET['gid'], FILTER_SANITIZE_NUMBER_INT );

				$autopopulate      = filter_var( $_POST['autopopulate'], FILTER_SANITIZE_STRIPPED );
				$autopopulate_meta = groups_get_groupmeta( $group_id, 'autopopulate', true );

				//lets update the group meta for manage membership
				if ( $autopopulate !== $autopopulate_meta ) {

					groups_update_groupmeta( $group_id, 'autopopulate', $autopopulate );
					wp_cache_delete( self::$society_id . '_managed_group_names', 'hcommons_settings' );

				}


			}

		}

	}

	/**
	 * Handles metabox creation for manage membership metabox
	 *
	 * @return void
	 */
	public function hcommons_add_manage_group_memberships_meta_box() {

		if ( is_admin() && $_GET['page'] == 'bp-groups' ) {

			add_meta_box(
				'hcommons_admin_groups_manage',
				_x( 'Manage Group Memberships', 'Manages group memberships', 'buddypress' ),
				array( $this, 'hcommons_admin_manage_group_memberships_view' ),
				get_current_screen()->id,
				'side',
				'core'
			);

		}
	}

	/**
	 * Outputs view for manage membership metabox
	 *
	 * @return void
	 */
	public function hcommons_admin_manage_group_memberships_view() {

		//grabs group_id from get and sanitizes it
		$group_id          = filter_var( $_GET['gid'], FILTER_SANITIZE_NUMBER_INT );
		$autopopulate_meta = groups_get_groupmeta( $group_id, 'autopopulate', true );
		?>

		<label>
			<input type="radio" name="autopopulate" value="Y" <?php echo ( $autopopulate_meta == 'Y' ) ? 'checked' : ''; ?> />Yes
		</label>
		<br />
		<label>
			<input type="radio" name="autopopulate" value="N" <?php echo ( $autopopulate_meta == 'N' ) ? 'checked' : ''; ?> />No
		</label>

		<?php

	}

	/**
	 * Removes member type meta box on user screen in wp-admin
	 *
	 * @return void
	 */
	public function hcommons_remove_member_type_meta_boxes() {

		if ( is_admin() && $_GET['page'] == 'bp-profile-edit' ) {
			remove_meta_box( 'bp_members_admin_member_type', 'users_page_bp-profile-edit-network', 'side' );
		}

	}

	/**
	 * Adds new member type meta box on user screen in wp-admin
	 *
	 * @return void
	 */
	public function hcommons_add_member_type_meta_box( $profile, $user_id ) {

		if ( is_admin() && $_GET['page'] == 'bp-profile-edit' ) {
			add_meta_box(
				'hcommons_members_admin_member_type',
				_x( 'Member Type', 'members user-admin edit screen', 'buddypress' ),
				array( $this, 'hcommons_member_type_meta_box_view' ),
				get_current_screen()->id,
				'side',
				'core'
			);
		}

	}

	/**
	 * Outputs view for member type meta box on user screen in wp-admin
	 *
	 * @return void
	 */
	public function hcommons_member_type_meta_box_view() {

		if ( isset( $_GET['user_id'] ) && is_admin() ) {

			//make sure user id is only numerical
			$user_id      = filter_var( $_GET['user_id'], FILTER_SANITIZE_NUMBER_INT );
			$member_types = bp_get_member_type( $user_id, false );

			echo "<ul>";

			//output member types user currently has
			foreach ( (array) $member_types as $type ) {

				echo "<li>" . strtoupper( $type ) . "</li>";

			}

			echo "</ul>";

		}

	}

	/**
	 * Adds new group type metabox to user admin area in bp-groups
	 *
	 * @return void
	 */
	public function hcommons_add_group_type_meta_box() {

		if ( is_admin() && $_GET['page'] == 'bp-groups' ) {
			add_meta_box(
				'hcommons_admin_group_type',
				_x( 'Group Type', 'groups admin edit screen', 'buddypress' ),
				array( $this, 'hcommons_group_type_meta_box_view' ),
				get_current_screen()->id,
				'side',
				'core'
			);
		}

	}

	/**
	 * Outputs view for new group type metabox
	 *
	 * @return void
	 */
	public function hcommons_group_type_meta_box_view() {

		//make sure group id is only numerical
		$group_id      = filter_var( $_GET['gid'], FILTER_SANITIZE_NUMBER_INT );
		$current_types = (array) bp_groups_get_group_type( $group_id, false );

		?>

		<ul class="categorychecklist form-no-clear">
			<?php foreach ( $current_types as $type ) : ?>
				<li>
					<label class="selectit">
						<?php echo strtoupper( esc_html( $type ) ); ?>
					</label>
				</li>

			<?php endforeach; ?>
		</ul>

		<?php

	}

	/**
	 * Removes current group type meta box to be replaced by another
	 *
	 * @return void
	 */
	public function hcommons_remove_group_type_meta_boxes() {

		if ( is_admin() && $_GET['page'] == 'bp-groups' ) {
			remove_meta_box( 'bp_groups_admin_group_type', 'toplevel_page_bp-groups-network', 'side' );
		}

	}

	public function hcommons_filter_bp_taxonomy_storage_site( $site_id, $taxonomy ) {

		if ( in_array( $taxonomy, array( 'bp_group_type', 'bp_member_type' ) ) ) {
			return self::$main_site->blog_id;
		} else {
			return $site_id;
		}

	}

	public function hcommons_filter_hc_taxonomy_storage_site( $site_id, $taxonomy ) {

		if ( in_array( $taxonomy, array(
			'mla_academic_interests',
			'humcore_deposit_language',
			'humcore_deposit_subject',
			'humcore_deposit_tag',
			'hcommons_society_member_id'
		) ) ) {
			return (int) '1'; // Go legacy during beta.
		} else {
			return $site_id;
		}

	}

	public function hcommons_set_members_query( $args ) {

		if ( ( empty( $args['include'] ) && ! bp_is_members_directory() ) || ( isset( $args['scope'] ) && 'society' === $args['scope'] ) ) {
			$args['member_type'] = self::$society_id;
		}

		return $args;
	}

	public function hcommons_set_groups_query_args( $args ) {
		// profile loops per-type, leave as-is
		if ( bp_is_user_profile() ) {
			return $args;
		}

		//hcommons_write_error_log( 'info', '****GROUPS_QUERY_ARGS****-' . var_export( $args, true ) );
		if ( isset( $args['scope'] ) && $args['scope'] == 'personal' ) {
			$args['group_type'] = '';

			return $args;
		}

		if ( NEU_DEFAULT_SOCIETY === self::$society_id && empty( $args['scope'] ) && ! self::backtrace_contains( 'class', 'EP_BP_API' ) ) {
			$args['group_type'] = '';
		} else {
			$args['group_type'] = self::$society_id;
		}

		// only show default society groups on /members/*/invite-anyone
		if (
			! is_super_admin() &&
			( bp_is_user() && false !== strpos( $_SERVER['REQUEST_URI'], 'invite-anyone' ) )
		) {
			$args['group_type'] = NEU_DEFAULT_SOCIETY;
		}

		return $args;
	}

	/**
	 * on members/groups directories, set default scope to society
	 */
	function hcommons_set_default_scope_society() {
		if ( ( bp_is_groups_directory() && NEU_DEFAULT_SOCIETY !== self::$society_id ) || ( bp_is_members_directory() && NEU_DEFAULT_SOCIETY !== self::$society_id ) ) {
			$object_name = bp_current_component();
			$cookie_name = 'bp-' . $object_name . '-scope';

			if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
				setcookie( $cookie_name, 'society', null, '/' );
				// unless the $_COOKIE global is updated in addition to the actual cookie above,
				// bp will not use the value for the first pageload.
				$_COOKIE[ $cookie_name ] = 'society';
			}
		}
	}

	/**
	 * Target specific occurances of groups_get_groups filter to restrict groups to society and don't show hidden groups.
	 *
	 * @since HCommons
	 *
	 * @param object $data Groups
	 * @param array  $r    Arguments
	 *
	 * @return object $new_groups or $data
	 */
	public function hcommons_groups_get_groups( $data, $r ) {

		if (
			self::backtrace_contains( 'class', 'BuddyPress_Event_Organiser_EO' ) ||
			self::backtrace_contains( 'function', 'bpmfp_get_other_groups_for_user' )
		) {

			$new_groups = BP_Groups_Group::get( array(
				'type'               => $r['type'],
				'user_id'            => $r['user_id'],
				'include'            => $r['include'],
				'exclude'            => $r['exclude'],
				'search_terms'       => $r['search_terms'],
				'group_type'         => self::$society_id,
				'group_type__in'     => $r['group_type__in'],
				'group_type__not_in' => $r['group_type__not_in'],
				'meta_query'         => $r['meta_query'],
				'show_hidden'        => false,
				'per_page'           => $r['per_page'],
				'page'               => $r['page'],
				'populate_extras'    => $r['populate_extras'],
				'update_meta_cache'  => $r['update_meta_cache'],
				'order'              => $r['order'],
				'orderby'            => $r['orderby'],
			) );

			return $new_groups;
		}

		return $data;
	}

	public function hcommons_set_group_type( $group_id ) {

		global $bp;
		if ( $bp->groups->new_group_id ) {
			$id = $bp->groups->new_group_id;
		} else {
			$id = $group_id;
		}

		bp_groups_set_group_type( $id, self::$society_id );
	}

	public function hcommons_set_group_mla_oid( $group_id ) {

		$society_id = self::$society_id;

		if ( 'mla' === $society_id ) {

			global $bp;
			if ( $bp->groups->new_group_id ) {
				$id = $bp->groups->new_group_id;
			} else {
				$id = $group_id;
			}
			$result = groups_add_groupmeta( $id, 'mla_oid', 'UXX', true );
			if ( is_wp_error( $result ) ) {
				hcommons_write_error_log( 'info', '****MLA_OID_WRITE_FAILURE****-' . $id . '-' . var_export( $result, true ) );
				echo "ERROR: " . var_export( $result, true );
			}
			bp_groups_set_group_type( $id, self::$society_id );
		}
	}

	public function hcommons_set_user_member_types( $user ) {

		$user_id = $user->ID;

		$shib_session_id = get_user_meta( $user_id, 'shib_session_id', true );
		/*
				if ( $shib_session_id == self::$shib_session_id ) {
					hcommons_write_error_log( 'info', '****SET_USER_MEMBER_TYPES_OUT****-' . var_export( $shib_session_id, true ) );
					return;
				}
		*/
		$memberships = $this->hcommons_get_user_memberships();
		hcommons_write_error_log( 'info', '****RETURNED_MEMBERSHIPS****-' . $_SERVER['HTTP_HOST'] . '-' . var_export( $user->user_login, true ) . '-' . var_export( $memberships, true ) );
		$member_societies = (array) bp_get_member_type( $user_id, false );
		hcommons_write_error_log( 'info', '****PRE_SET_USER_MEMBER_TYPES****-' . var_export( $member_societies, true ) );
		$result = bp_set_member_type( $user_id, '' ); // Clear existing types, if any.
		$append = true;
		foreach ( $memberships['societies'] as $member_type ) {
			$result = bp_set_member_type( $user_id, $member_type, $append );
			hcommons_write_error_log( 'info', '****SET_EACH_MEMBER_TYPE****-' . $user_id . '-' . $member_type . '-' . var_export( $result, true ) );
		}

		//If site is a society we are mapping groups for and the user is member of the society, map any groups from comanage to wp.
		//TODO add logic to remove groups the user is no longer a member of
		if ( in_array( self::$society_id, array( 'gr' ) ) &&
		     in_array( self::$society_id, $memberships['societies'] )
		) {
			foreach ( $memberships['groups'][ self::$society_id ] as $group_name ) {
				$group_id = $this->hcommons_lookup_society_group_id( self::$society_id, $group_name );
				if ( ! groups_is_user_member( $user_id, $group_id ) ) {
					$success = groups_join_group( $group_id, $user_id );
					hcommons_write_error_log( 'info', '****ADD_GROUP_MEMBERSHIP***-' . $group_id . '-' . $user_id );
				}
			}
		}

	}

	public function hcommons_maybe_set_user_role_for_site( $user ) {

		//TODO Can we find WP functions that avoid messing directly with usermeta for a user that has not yet signed in?
		global $wpdb;
		$prefix          = $wpdb->get_blog_prefix();
		$user_id         = $user->ID;
		$site_caps       = get_user_meta( $user_id, $prefix . 'capabilities', true );
		$site_caps_array = maybe_unserialize( $site_caps );
		$memberships     = $this->hcommons_get_user_memberships();
		$is_site_member  = in_array( self::$society_id, $memberships['societies'] );

		if ( $is_site_member ) {
			//TODO Copy role check logic from hcommons_check_user_site_membership().
			$site_role_found = false;
			foreach ( $site_caps_array as $key => $value ) {
				if ( in_array( $key, array( 'subscriber', 'contributor', 'author', 'editor', 'administrator' ) ) ) {
					$site_role_found = true;
					break;
				}
			}
			if ( $is_site_member && ! $site_role_found ) {
				$site_caps_array['subscriber'] = true;
				$site_caps_updated             = maybe_serialize( $site_caps_array );
				$result                        = update_user_meta( $user_id, $prefix . 'capabilities', $site_caps_updated );
				$user->init_caps();
				hcommons_write_error_log( 'info', '****MAYBE_SET_USER_ROLE_FOR_SITE***-' . var_export( $result, true ) . '-' . var_export( $is_site_member, true ) . '-' . var_export( $site_caps_updated, true ) . '-' . var_export( $prefix, true ) . '-' . var_export( $user_id, true ) );
			}
		} else {
			if ( ! empty( $site_caps ) ) {
				delete_user_meta( $user_id, $prefix . 'capabilities' );
				delete_user_meta( $user_id, $prefix . 'user_level' );
			}
		}
	}

	/**
	 * Capture shibboleth data in user meta once per shibboleth session
	 *
	 * @since HCommons
	 *
	 * @param object $user
	 */
	public function hcommons_set_shibboleth_based_user_meta( $user ) {

		$user_id         = $user->ID;
		$shib_session_id = get_user_meta( $user_id, 'shib_session_id', true );

		if ( $shib_session_id == self::$shib_session_id ) {
			return;
		}

		hcommons_write_error_log( 'info', '****SHIB_BASED_USER_META****-' . var_export( self::$shib_session_id, true ) );
		$login_host = $_SERVER['HTTP_X_FORWARDED_HOST'];
		$result     = update_user_meta( $user_id, 'shib_session_id', self::$shib_session_id );
		$result     = update_user_meta( $user_id, 'shib_login_host', $login_host );

		$shib_orcid = $_SERVER['HTTP_EDUPERSONORCID'];
		if ( ! empty( $shib_orcid ) ) {
			if ( false === strpos( $shib_orcid, ';' ) ) {
				$shib_orcid_updated = str_replace( array(
					'https://orcid.org/',
					'http://orcid.org/'
				), '', $shib_orcid );
				$result             = update_user_meta( $user_id, 'shib_orcid', $shib_orcid_updated );
			} else {
				$shib_orcid_updated = array();
				$shib_orcids        = explode( ';', $shib_orcid );
				foreach ( $shib_orcids as $each_orcid ) {
					if ( ! empty( $each_orcid ) ) {
						$shib_orcid_updated[] = str_replace( array(
							'https://orcid.org/',
							'http://orcid.org/'
						), '', $each_orcid );
					}
				}
				$result = update_user_meta( $user_id, 'shib_orcid', $shib_orcid_updated[0] );
			}
		}

		$shib_org = $_SERVER['HTTP_O'];
		if ( false === strpos( $shib_org, ';' ) ) {
			$shib_org_updated = $shib_org;
			if ( 'Humanities Commons' === $shib_org_updated ) {
				$shib_org_updated = '';
			}
		} else {
			$shib_org_updated = array();
			$shib_orgs        = explode( ';', $shib_org );
			foreach ( $shib_orgs as $shib_org ) {
				if ( 'Humanities Commons' !== $shib_org && ! empty( $shib_org ) ) {
					$shib_org_updated[] = $shib_org;
				}
			}
		}
		$result = update_user_meta( $user_id, 'shib_org', maybe_serialize( $shib_org_updated ) );

		$shib_title = $_SERVER['HTTP_TITLE'];
		if ( false === strpos( $shib_title, ';' ) ) {
			$shib_title_updated = $shib_title;
		} else {
			$shib_title_updated = explode( ';', $shib_title );
		}
		$result = update_user_meta( $user_id, 'shib_title', maybe_serialize( $shib_title_updated ) );

		$shib_uid = $_SERVER['HTTP_UID'];
		if ( false === strpos( $shib_uid, ';' ) ) {
			$shib_uid_updated = $shib_uid;
		} else {
			$shib_uid_updated = explode( ';', $shib_uid );
		}
		$result = update_user_meta( $user_id, 'shib_uid', maybe_serialize( $shib_uid_updated ) );

		$shib_ismemberof = $_SERVER['HTTP_ISMEMBEROF'];
		if ( false === strpos( $shib_ismemberof, ';' ) ) {
			$shib_ismemberof_updated = $shib_ismemberof;
		} else {
			$shib_ismemberof_updated = explode( ';', $shib_ismemberof );
		}
		$result = update_user_meta( $user_id, 'shib_ismemberof', maybe_serialize( $shib_ismemberof_updated ) );

		$shib_email = $_SERVER['HTTP_MAIL'];
		if ( false === strpos( $shib_email, ';' ) ) {
			$shib_email_updated = $shib_email;
		} else {
			$shib_email_updated = explode( ';', $shib_email );
		}
		$result = update_user_meta( $user_id, 'shib_email', maybe_serialize( $shib_email_updated ) );

		$shib_identity_provider = $_SERVER['HTTP_SHIB_IDENTITY_PROVIDER'];
		if ( false === strpos( $shib_identity_provider, ';' ) ) {
			$shib_identity_provider_updated = $shib_identity_provider;
		} else {
			$shib_identity_provider_updated = explode( ';', $shib_identity_provider );
		}
		$result = update_user_meta( $user_id, 'shib_identity_provider', maybe_serialize( $shib_identity_provider_updated ) );
	}

	/**
	 * Syncs the HCommons managed WordPress profile data to HCommons XProfile Group fields.
	 *
	 * @since HCommons
	 *
	 * @param object $user User object whose profile is being synced. Passed by reference.
	 */
	function hcommons_sync_bp_profile( $user ) {

		$user_id = $user->ID;

		$shib_session_id = get_user_meta( $user_id, 'shib_session_id', true );
		/*
				if ( $shib_session_id == self::$shib_session_id ) {
					hcommons_write_error_log( 'info', '****SYNC_BP_PROFILE_OUT****-' . var_export( $shib_session_id, true ) );
					return;
				}
		*/
		hcommons_write_error_log( 'info', '****SYNC_BP_PROFILE****-' . var_export( $user->ID, true ) );

		$current_name = xprofile_get_field_data( 'Name', $user->ID );
		if ( empty( $current_name ) ) {
			$name = $_SERVER['HTTP_DISPLAYNAME']; // user record maybe not fully populated for first time users.
			if ( ! empty( $name ) ) {
				xprofile_set_field_data( 'Name', $user->ID, $name );
			}
		}

		$current_title = xprofile_get_field_data( 'Title', $user->ID );
		if ( empty( $current_title ) ) {
			$titles = maybe_unserialize( get_user_meta( $user->ID, 'shib_title', true ) );
			if ( is_array( $titles ) ) {
				$title = $titles[0];
			} else {
				$title = $titles;
			}
			if ( ! empty( $title ) ) {
				xprofile_set_field_data( 'Title', $user->ID, $title );
			}
		}

		$current_org = xprofile_get_field_data( 'Institutional or Other Affiliation', $user->ID );
		if ( empty( $current_org ) ) {
			$orgs = maybe_unserialize( get_user_meta( $user->ID, 'shib_org', true ) );
			if ( is_array( $orgs ) ) {
				$org = $orgs[0];
			} else {
				$org = $orgs;
			}
			if ( ! empty( $org ) ) {
				xprofile_set_field_data( 'Institutional or Other Affiliation', $user->ID, str_replace( 'Mla', 'MLA', $org ) );
			}
		}

		$current_orcid = xprofile_get_field_data( 18, $user->ID );
		if ( empty( $current_orcid ) ) {
			$orcid = get_user_meta( $user->ID, 'shib_orcid', true );
			if ( ! empty( $orcid ) ) {
				xprofile_set_field_data( 18, $user->ID, $orcid );
			}
		}

	}

	/**
	 * Return first email if multiple provided in shibboleth session.
	 *
	 * @since HCommons
	 *
	 * @param string $shib_email
	 *
	 * @return string $shib_email_array[0]
	 */
	public function hcommons_set_shibboleth_based_user_email( $shib_email ) {

		$shib_email_array = explode( ';', $shib_email );

		return $shib_email_array[0];

	}

	/**
	 * ensure invite-anyone correctly sets up notifications after user registers
	 */
	public function hcommons_invite_anyone_activate_user( $user ) {
		$meta_key = 'hcommons_invite_anyone_activate_user_done';

		if (
			! empty( $user->user_email ) &&
			! get_user_meta( $user->ID, $meta_key ) &&
			function_exists( 'invite_anyone_activate_user' )
		) {
			invite_anyone_activate_user( $user->ID, null, null );
			update_user_meta( $user->ID, $meta_key, true );
		}
	}

	/**
	 * Get the society_id for the current blog or a given blog.
	 *
	 * @since HCommons
	 *
	 * @param string $blog_id
	 *
	 * @return string $blog_society_id or self::$society_id
	 */
	public function hcommons_get_blog_society_id( $blog_id = '' ) {

		$fields = array();
		if ( ! empty( $blog_id ) ) {
			$fields['blog_id'] = $blog_id;
		} else {
			return self::$society_id;
		}
		$blog_details    = get_blog_details( $fields );
		$blog_society_id = get_network_option( $blog_details->site_id, 'society_id' );

		return $blog_society_id;
	}

	/**
	 * Filter the count returned by bp_get_total_blog_count() which ultimately depends on BP_Blogs_Blog::get_all().
	 * We want to use the filtered results returned by BP_Blogs_Blog::get() instead, so that we accommodate MPO.
	 *
	 * @since HCommons
	 *
	 * @param string $count
	 *
	 * @return string $count
	 */
	public function hcommons_get_total_blog_count( $count ) {
		// let's see what the blogs query will actually include and use that for the count
		$blogs_query_args = $this->hcommons_set_network_blogs_query( array() );

		// now make sure the More Privacy Options filter removes any blogs it needs to
		$mpo_filtered_blogs = bp_blogs_get_blogs( $blogs_query_args );

		if ( $mpo_filtered_blogs ) {
			$count = $mpo_filtered_blogs['total'];
		}

		return $count;
	}

	/**
	 * Like hcommons_get_total_blog_count() except for users.
	 * Because the logged-in logic in BP_Blogs_Blog::get_blogs_for_user() doesn't check the 'public' column,
	 * MPO doesn't need to be accommodated, which is different than in hcommons_get_total_blog_count().
	 *
	 * @since HCommons
	 *
	 * @param string $count
	 *
	 * @return string $count
	 */
	public function hcommons_get_total_blog_count_for_user( $count ) {
		$user_blogs = bp_blogs_get_blogs_for_user( get_current_user_id() );


		if ( $user_blogs ) {
			// do not include HC
			foreach ( $user_blogs['blogs'] as $key => $user_blog ) {
				if ( $user_blog->blog_id === self::$main_site->blog_id ) {
					unset( $user_blogs['blogs'][ $key ] );
				}
			}

			// $user_blogs['total'] is WRONG! that's why this filter is here, just count the actual blogs instead.
			$count = count( $user_blogs['blogs'] );
		}

		return $count;
	}

	/**
	 * Filter the sites query by the society id for the current network except for HC.
	 *
	 * @since HCommons
	 *
	 * @param array $args
	 *
	 * @return array $args
	 */
	public function hcommons_set_network_blogs_query( $args ) {

		$blog_ids        = array();
		$current_blog_id = get_current_blog_id();


		if (
			NEU_DEFAULT_SOCIETY !== self::$society_id &&
			empty( $args['user_id'] ) &&
			! bp_is_current_action( 'my-sites' ) &&
			! bp_is_current_component( 'profile' )
		) {


			$current_network = get_current_site();
			$network_sites   = get_sites( array( 'network_id' => $current_network->id, 'number' => 9999 ) );
			foreach ( $network_sites as $site ) {
				if ( $site->blog_id != $current_blog_id ) {
					$blog_ids[] = $site->blog_id;
				}
			}
		} else {
			//TODO Find a better way, this won't scale to all of HC.
			$sites = get_sites( array( 'network_id' => null, 'number' => 9999 ) );
			foreach ( $sites as $site ) {
				if ( $site->blog_id != $current_blog_id ) {
					$blog_ids[] = $site->blog_id;
				}
			}
		}

		if ( ! empty( $blog_ids ) ) {
			$include_blogs            = implode( ',', $blog_ids );
			$args['include_blog_ids'] = $include_blogs;
		}

		//hcommons_write_error_log( 'info', '****SET_NETWORK_BLOGS_QUERY***-'.var_export( $args, true ) );
		return $args;
	}

	/**
	 * Filter the activity query by the society id for the current network.
	 *
	 * @since HCommons
	 *
	 * @param array $args
	 *
	 * @return array $args
	 */
	public function hcommons_set_network_activities_query( $args ) {
		if ( isset( $args['type'] ) && 'sitewide' === $args['type'] ) {
			if ( is_user_logged_in() ) {
				$current_user_id            = get_current_user_id();
				$current_user_blog_ids      = BP_Blogs_Blog::get_blog_ids_for_user( $current_user_id );
				$current_user_following_ids = bp_follow_get_following( [ 'user_id' => $current_user_id ] );
				$current_user_groups        = groups_get_user_groups( $current_user_id );
				$current_user_group_ids     = $current_user_groups['groups'];

				$filter_query = array_merge( ( isset( $args['filter_query'] ) ) ? $args['filter_query'] : [], [
					// exclude self
					[
						'column'  => 'user_id',
						'value'   => $current_user_id,
						'compare' => '!=',
					],

					// otherwise, any of these relevant activities
					[
						'relation' => 'OR',

						// any new deposits, groups, or blogs
						[
							'column'  => 'type',
							'value'   => [ 'new_deposit', 'new_group_deposit', 'created_group', 'new_blog' ],
							'compare' => 'IN',
						],

						// any activity by my followers
						[
							'column'  => 'user_id',
							'value'   => $current_user_following_ids,
							'compare' => 'IN',
						],

						// any activity on my blogs
						[
							[
								'column' => 'component',
								'value'  => 'blogs',
							],
							[
								'column'  => 'item_id',
								'value'   => $current_user_blog_ids,
								'compare' => 'IN',
							],
						],

						// any activity on my groups
						[
							[
								'column' => 'component',
								'value'  => 'groups',
							],
							[
								'column'  => 'item_id',
								'value'   => $current_user_group_ids,
								'compare' => 'IN',
							],
						],
					],

				] );

				$args['filter_query'] = $filter_query;
			}
		}

		if ( NEU_DEFAULT_SOCIETY !== self::$society_id && ! bp_is_user_profile() && ! bp_is_user_activity() ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'society_id',
					'value'   => self::$society_id,
					'type'    => 'string',
					'compare' => '='
				),
			);
		}

		return $args;
	}

	/**
	 * Filter the activity query "WHERE" conditions to exclude 'joined_group' (etc.?) types
	 *
	 * @since HCommons
	 *
	 * @param array $args
	 *
	 * @return array $args
	 */
	public function hcommons_filter_activity_where_conditions( $args ) {
		// BP_Activity_Activity::get() hardcodes this sql string only if $excluded_types is non-empty,
		// so we can assume a non-empty value here means there is at least one type in the sql array
		if ( ! empty( $args['excluded_types'] ) ) {
			// these are the types we intend to filter out in addition to whatever is passed to this filter
			$not_in = [ 'joined_group', 'friendship_created' ];

			// parse the existing excluded types and merge with our own
			preg_match_all( "/a.type NOT IN \('(.*)'\)/", $args['excluded_types'], $matches );
			$not_in = array_merge( $not_in, explode( "', '", $matches[1][0] ) );

			// build new sql using combined types
			$args['excluded_types'] = "a.type NOT IN ('" . implode( "', '", $not_in ) . "')";
		}

		return $args;
	}

	/**
	 * Add the current society id to the current activity as an activity_meta record.
	 *
	 * @since HCommons
	 *
	 * @param array $activity
	 */
	public function hcommons_set_activity_society_meta( $activity ) {

		bp_activity_add_meta( $activity->id, 'society_id', self::$society_id, true );
	}

	/**
	 * Add the current society id to the current notificaiton as a notification_meta record.
	 *
	 * @since HCommons
	 *
	 * @param array $notification
	 */
	public function hcommons_set_notification_society_meta( $notification ) {

		hcommons_write_error_log( 'info', '****SET_NOTIFICATION_SOCIETY_META***-' . var_export( $notification, true ) );
		bp_notifications_add_meta( $notification->id, 'society_id', self::$society_id, true );
	}

	/**
	 * Set the activity permalink to contain the proper network.
	 *
	 * @since HCommons
	 *
	 * @param string $link
	 * @param object $activity Passed by reference.
	 *
	 * @return string $link
	 */
	public function hcommons_filter_activity_permalink( $link, $activity ) {

		$activity_society_id = bp_activity_get_meta( $activity->id, 'society_id', true );
		if ( self::$society_id == $activity_society_id ) {
			return $link;
		}

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT site_id FROM $wpdb->sitemeta WHERE meta_key = '%s' AND meta_value = '%s'", 'society_id', $activity_society_id ) );
		if ( is_object( $row ) ) {
			$society_network      = get_network( $row->site_id );
			$scheme               = ( is_ssl() ) ? 'https://' : 'http://';
			$activity_root_domain = $scheme . $society_network->domain . $society_network->path;
		}
		$society_activity_link = str_replace( trailingslashit( bp_get_root_domain() ), $activity_root_domain, $link );

		//hcommons_write_error_log( 'info', '****FILTER_ACTIVITY_PERMALINK***-'.$link.'-'.$society_activity_link.'-'.bp_get_root_domain().'-'.self::$society_id.'-'.var_export( $activity->id, true ) );

		return $society_activity_link;

	}

	/**
	 * Add the current society id to the body classes.
	 *
	 * @since HCommons
	 *
	 * @param array $classes
	 *
	 * @return array $classes
	 */
	public function hcommons_society_body_class_name( $classes ) {

		if ( function_exists( 'shibboleth_session_active' ) ) {
			if ( shibboleth_session_active() ) {
				$classes[]        = 'active-session';
				$user_memberships = self::hcommons_get_user_memberships();
				if ( ! in_array( self::$society_id, $user_memberships['societies'] ) ) {
					$classes[] = 'non-member';
				}
			}
		}
		$classes[] = 'society-' . self::$society_id;

		return $classes;
	}

	/**
	 * Check if user has a capability on a given site.
	 *
	 * @since HCommons
	 *
	 * @param string $retval
	 * @param string $capability
	 * @param string $blog_id
	 * @param array  $args
	 *
	 * @return string|bool $retval or false
	 */
	public function hcommons_check_site_member_can( $retval, $capability, $blog_id, $args ) {

		$user_id = get_current_user_id();
		if ( $user_id < 2 ) {
			return $retval;
		}
		//TODO Why is taxonomy invalid here on HC?
		if ( 'hc' === self::$society_id && ! get_taxonomy( 'bp_member_type' ) ) {
			bp_register_taxonomies();
		}
		$member_societies = (array) bp_get_member_type( $user_id, false );
		if ( bp_has_member_type( $user_id, self::$society_id ) ) {
			//hcommons_write_error_log( 'info', '****CHECK_USER_MEMBER_TYPE_TRUE***-' . var_export( $user_id, true ) . '-' . var_export( $member_societies, true ) . '-' . var_export( self::$society_id, true ) . var_export( $capability, true ) );
			return $retval;
		} else {
			//hcommons_write_error_log( 'info', '****CHECK_USER_MEMBER_TYPE_FALSE***-' . var_export( $user_id, true ) . '-' . var_export( $member_societies, true ) . '-' . var_export( self::$society_id, true ) . var_export( $capability, true ) );
			return false;
		}
	}

	/**
	 * Check the user's membership to this network prior to login and if valid return the role.
	 *
	 * @since HCommons
	 *
	 * @param string $user_role
	 *
	 * @return string $user_role Role or null.
	 */
	public function hcommons_check_user_site_membership( $user_role ) {

		$username = $_SERVER['HTTP_EMPLOYEENUMBER'];

		$user                = get_user_by( 'login', $username );
		$user_id             = $user->ID;
		$global_super_admins = array();
		if ( defined( 'GLOBAL_SUPER_ADMINS' ) ) {
			$global_super_admin_list = constant( 'GLOBAL_SUPER_ADMINS' );
			$global_super_admins     = explode( ',', $global_super_admin_list );
		}
		$memberships      = $this->hcommons_get_user_memberships();
		$member_societies = (array) $memberships['societies'];
		if ( ! in_array( self::$society_id, $member_societies ) && ! in_array( $user->user_login, $global_super_admins ) ) {
			hcommons_write_error_log( 'info', '****CHECK_USER_SITE_MEMBERSHIP_FAIL****-' . var_export( $memberships['societies'], true ) .
			                                  var_export( self::$society_id, true ) . var_export( $user, true ) );

			return '';
		}

		//Check for existing user role, we don't want to overwrite role assignments made in WP.
		global $wp_roles;
		$user_role_set = false;
		foreach ( $wp_roles->roles as $role_key => $role_name ) {
			if ( false === strpos( $role_key, 'bbp_' ) ) {
				$user_role_set = user_can( $user, $role_key );
			}
			if ( $user_role_set ) {
				$user_role = $role_key;
				break;
			}
		}
		hcommons_write_error_log( 'info', '****CHECK_USER_SITE_MEMBERSHIP****-' . var_export( $user_role, true ) . var_export( $user_role_set, true ) . var_export( $user->user_login, true ) );

		return $user_role;

	}

	/**
	 * Set the group permalink to contain the proper network.
	 *
	 * @since HCommons
	 *
	 * @param string $group_permalink
	 *
	 * @return string $group_permalink Modified url.
	 */
	public function hcommons_set_groups_directory_permalink( $group_permalink ) {
		global $groups_template;

		if ( ! empty( $groups_template->group ) ) {
			$group_id         = bp_get_group_id();
			$group_society_id = bp_groups_get_group_type( $group_id );

			if ( $group_society_id === self::$society_id ) {
				return $group_permalink;
			}

			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT site_id FROM $wpdb->sitemeta WHERE meta_key = '%s' AND meta_value = '%s'", 'society_id', $group_society_id ) );
			if ( is_object( $row ) ) {
				$society_network = get_network( $row->site_id );
				$scheme          = ( is_ssl() ) ? 'https://' : 'http://';
				$group_permalink = trailingslashit( $scheme . $society_network->domain . $society_network->path . bp_get_groups_root_slug() );
			}
		}

		return $group_permalink;
	}

	/**
	 * Set a given group permalink to contain the proper network.
	 *
	 * @since HCommons
	 *
	 * @param string $group_permalink
	 * @param object $group
	 *
	 * @return string $group_permalink Modified url.
	 */
	public function hcommons_set_group_permalink( $group_permalink, $group ) {

		$group_id         = $group->id;
		$group_society_id = bp_groups_get_group_type( $group_id );

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT site_id FROM $wpdb->sitemeta WHERE meta_key = '%s' AND meta_value = '%s'", 'society_id', $group_society_id ) );
		if ( is_object( $row ) ) {
			$society_network = get_network( $row->site_id );
			$scheme          = ( is_ssl() ) ? 'https://' : 'http://';
			$group_permalink = trailingslashit( $scheme . $society_network->domain . $society_network->path . bp_get_groups_root_slug() . '/' . $group->slug );
		}

		return $group_permalink;
	}

	/**
	 * Set a given member permalink to contain the proper network.
	 *
	 * @since HCommons
	 *
	 * @param string $member_permalink
	 * @param int    $user_id
	 *
	 * @return string $member_permalink Modified url.
	 */
	public function hcommons_set_members_directory_permalink( $member_permalink, $user_id, $user_nicename, $user_login ) {

		if ( ! bp_is_members_directory() ) {
			return $member_permalink;
		}

		$all_types = bp_get_member_types();

		//hcommons_write_error_log( 'info', '****SET_MEMBERS_DIRECTORY_PERMALINK****-'.var_export( $member_permalink, true ) );
		$member_types = bp_get_member_type( $user_id, false );

		if ( in_array( self::$society_id, (array) $member_types ) || count( $all_types ) < 2 ) {
			return $member_permalink;
		}
		$after_domain = bp_core_enable_root_profiles() ? $user_login : bp_get_members_root_slug() . '/' . $user_login;

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT site_id FROM $wpdb->sitemeta WHERE meta_key = '%s' AND meta_value = '%s'", 'society_id', 'hc' ) );
		if ( is_object( $row ) ) {
			$society_network  = get_network( $row->site_id );
			$scheme           = ( is_ssl() ) ? 'https://' : 'http://';
			$member_permalink = trailingslashit( $scheme . $society_network->domain . $society_network->path . $after_domain );
		}

		return $member_permalink;
	}

	/**
	 * Filter out the user blogs that are not in the current network.
	 *
	 * @since HCommons
	 *
	 * @param array  $blogs
	 * @param string $user_id
	 * @param bool   $all
	 *
	 * @return array $network_blogs
	 */
	public function hcommons_filter_get_blogs_of_user( $blogs, $user_id, $all ) {
		// Remove root blogs (of any network).
		foreach ( $blogs as $i => $blog ) {
			foreach ( get_networks() as $network ) {
				if ( $blog->domain === $network->domain ) {
					unset( $blogs[ $i ] );
				}
			}
		}

		if ( NEU_DEFAULT_SOCIETY !== self::$society_id && ! bp_is_current_action( 'my-sites' ) ) {

			$network_blogs   = $blogs;
			$current_network = get_current_site();
			$current_blog_id = get_current_blog_id();

			foreach ( $blogs as $blog ) {

				if ( $current_network->id != $blog->site_id || $current_blog_id == $blog->userblog_id ) {
					unset ( $network_blogs[ $blog->userblog_id ] );
				}

			}

			//hcommons_write_error_log( 'info', '****GET_BLOGS_OF_USER****-'.var_export( $user_id, true ) );
			$blogs = $network_blogs;
		}

		return $blogs;
	}

	/**
	 * Filter the BP Core avatar upload path to be global and not network specific.
	 *
	 * @since HCommons
	 *
	 * @param string $path
	 *
	 * @return string $path Modified path.
	 */
	public function hcommons_set_bp_core_avatar_upload_path( $path ) {

		if ( ! empty( $path ) ) {
			$site_loc = strpos( $path, '/site' );
			if ( false === $site_loc ) {
				return $path;
			} else {
				$global_path = substr( $path, 0, $site_loc );

				//hcommons_write_error_log( 'info', '****BP_CORE_AVATAR_UPLOAD_PATH****-'.var_export( $global_path, true ) );
				return $global_path;
			}
		} else {
			return $path;
		}

	}

	/**
	 * Filter the BP Core avatar url to be global and not network specific.
	 *
	 * @since HCommons
	 *
	 * @param string $url
	 *
	 * @return string $url Modified url.
	 */
	public function hcommons_set_bp_core_avatar_url( $url ) {

		if ( ! empty( $url ) ) {
			$site_loc = strpos( $url, '/site' );
			if ( false === $site_loc ) {
				return $url;
			} else {
				$global_url = substr( $url, 0, $site_loc );

				//hcommons_write_error_log( 'info', '****BP_CORE_AVATAR_URL****-'.var_export( $global_url, true ) );
				return $global_url;
			}
		} else {
			return $url;
		}

	}

	/**
	 * Filter the Invite Anyone user query by member type for this network.
	 *
	 * @since HCommons
	 *
	 * @param Invite_Anyone_User_Query $query Current instance of Invite_Anyone_User_Query. Passed by reference.
	 */
	public function hcommons_filter_site_users_only( $query ) {

		global $wpdb;
		$context = debug_backtrace(); //TODO get a proper filter in Invite Anyone and get rid of backtrace.

		if ( 'Invite_Anyone_User_Query' === get_class( $context[1]['args'][1][0] ) ) {
			//hcommons_write_error_log( 'info', '****FILTER_SITE_USERS_ONLY_QUERY****-'.var_export( $query, true ) );
			//hcommons_write_error_log( 'info', '****FILTER_SITE_USERS_ONLY_TRACE****-'.var_export( get_class( $context[1]['args'][1][0] ), true ) );
			$tax_query = new WP_Tax_Query( array(
				array(
					'taxonomy' => 'bp_member_type',
					'field'    => 'name',
					'operator' => 'IN',
					'terms'    => self::$society_id,
				),
			) );

			// Switch to the root blog, where member type taxonomies live.
			$site_id  = bp_get_taxonomy_term_site_id( 'bp_member_type' );
			$switched = false;
			if ( $site_id !== get_current_blog_id() ) {
				switch_to_blog( $site_id );
				$switched = true;
			}

			$sql_clauses = $tax_query->get_sql( 'u', $this->uid_name );

			$clause = '';

			if ( false !== strpos( $sql_clauses['where'], '0 = 1' ) ) {
				$clause = array( 'join' => '', 'where' => '0 = 1' );
				// IN clauses must be converted to a subquery.
			} elseif ( preg_match( '/' . $wpdb->term_relationships . '\.term_taxonomy_id IN \([0-9, ]+\)/', $sql_clauses['where'], $matches ) ) {
				$clause = "wp_users.ID IN ( SELECT object_id FROM $wpdb->term_relationships WHERE {$matches[0]} )";
			}

			if ( $switched ) {
				restore_current_blog();
			}
			//hcommons_write_error_log( 'info', '****FILTER_SITE_USERS_ONLY_CLAUSE****-'.var_export( $clause, true ) );
			$query->query_where .= ' AND ' . $clause;
		}

	}

	/**
	 * Filter the group join button by network.
	 *
	 * @since HCommons
	 *
	 * @param array  $button Button settings.
	 * @param object $group
	 *
	 * @return array|null Button attributes.
	 */
	public function hcommons_check_bp_get_group_join_button( $button, $group ) {

		if ( NEU_DEFAULT_SOCIETY !== self::$society_id ) {
			return $button;
		}
		$group_society_id = bp_groups_get_group_type( $group->id );
		//hcommons_write_error_log( 'info', '****BP_GET_GROUP_JOIN_BUTTON****-'.var_export( $group_society_id, true ).'-'.var_export( $group, true ) );
		if ( NEU_DEFAULT_SOCIETY !== $group_society_id ) {
			return null;
		} else {
			return $button;
		}

	}

	/**
	 * Handle a failed login attempt. Determine if the user has visitor status.
	 *
	 * @since HCommons
	 *
	 * @param string $username User who is attempting to log in.
	 */
	public function hcommons_login_failed( $username ) {

		global $wpdb;

		$referrer = $_SERVER['HTTP_REFERER'];
		hcommons_write_error_log( 'info', '****LOGIN_FAILED****-' . $_SERVER['HTTP_REFERER'] . ' ' . $_SERVER['HTTP_X_FORWARDED_FOR'] . ' ' . $_SERVER['HTTP_EMPLOYEENUMBER'] );
		if ( ! empty( $referrer ) && strstr( $referrer, 'idp/profile/SAML2/Redirect/SSO?' ) ) {
			if ( ! strstr( $_SERVER['REQUEST_URI'], '/not-a-member' ) && ! strstr( $_SERVER['REQUEST_URI'], '/inactive-member' ) ) { // one redirect
				wp_redirect( 'https://' . $_SERVER['HTTP_X_FORWARDED_HOST'] . '/not-a-member' );
				exit();
			}
		}

	}

	/**
	 * Filter the login redirect to prevent landing on wp-admin when logging in with shibboleth.
	 *
	 * @since HCommons
	 *
	 * @param string $location
	 *
	 * @return string $location Modified url
	 */
	public function hcommons_remove_admin_redirect( $location ) {
		if (
			isset( $_REQUEST['action'] ) &&
			'shibboleth' === $_REQUEST['action'] &&
			strpos( $location, 'wp-admin' ) !== false
		) {
			$location = get_site_url();
		}

		return $location;
	}

	/**
	 * Force logout of current network if shibboleth session has expired.
	 * This is intended to make logging out of one network log the user out of all networks,
	 * but also serves to deal with shibboleth expiration or other unexpected scenarios.
	 */
	public function hcommons_shibboleth_autologout() {
		if ( is_user_logged_in() && ! shibboleth_session_active() ) {
			$logout_url = get_site_option( 'shibboleth_logout_url' );
			wp_logout();
			wp_redirect( $logout_url );
			exit;
		}
	}

	/**
	 * filter shibboleth_login_url & shibboleth_logout_url to always use https
	 */
	function hcommons_filter_site_option_shibboleth_urls( $value ) {
		$value = str_replace( 'http:', 'https:', $value );

		return $value;
	}

	/**
	 * Require shibboleth login rather than allowing vanilla wp-login.
	 *
	 * @since HCommons
	 */
	public function hcommons_login_init() {
		if (
			! isset( $_REQUEST['action'] ) ||
			! in_array( $_REQUEST['action'], [ 'shibboleth', 'logout' ] )
		) {
			$exploded_url = explode( '?', $_SERVER['REQUEST_URI'] );

			parse_str( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY ), $parsed_query );

			$parsed_query['action'] = 'shibboleth';

			wp_safe_redirect( $exploded_url[0] . '?' . http_build_query( $parsed_query ) );
		}
	}

	/**
	 * Filter shibboleth_session_active to set class variable
	 *
	 * @since HCommons
	 *
	 * @param bool $active
	 *
	 * @return bool $active
	 */
	public function hcommons_shibboleth_session_active( $active ) {

		if ( $active ) {
			self::$shib_session_id = $_SERVER['Shib-Session-ID'];
		}

		return $active;
	}

	/**
	 * Filter the register url to be society specific
	 *
	 * @since HCommons
	 *
	 * @param string $register_url
	 *
	 * @return string $register_url Modified url.
	 */
	public function hcommons_register_url( $register_url ) {

		if ( ! empty( self::$society_id ) && defined( strtoupper( self::$society_id ) . '_ENROLLMENT_URL' ) ) {
			return constant( strtoupper( self::$society_id ) . '_ENROLLMENT_URL' );
		} else {
			return $register_url;
		}

	}

	/**
	 * Action to modify nav and sub nav items
	 *
	 * @since HCommons
	 *
	 */
	public function hcommons_remove_nav_items() {

		global $bp;
		//bp_core_remove_subnav_item( 'settings', 'general' );
		bp_core_remove_subnav_item( 'settings', 'profile' );
		// Example of how you change the default tab.
		//bp_core_new_nav_default( array( 'parent_slug' => 'settings', 'screen_function' =>'bp_settings_screen_notification', 'subnav_slug' => 'notifications' ) );

	}

	/**
	 * Lets modify the admin links for a forum topic so admins cannot modify other users posts
	 * and only their own on the front-end
	 *
	 * @param  array $array array of the links to modify
	 * @param  int   $id    id for admin links on the front-end
	 *
	 * @return array $array  modified array of items
	 */
	public function hcommons_topic_admin_links( $array, $id ) {

		$cap = groups_filter_bbpress_caps( 'bp_moderate' );

		$user = wp_get_current_user();

		if ( $cap == true && bbp_get_current_user_id() !== bbp_get_topic_author_id( bbp_get_topic_id() ) ) {
			unset( $array['edit'] );
		}

		return $array;

	}

	/**
	 * Lets modify the admin links for a forum reply so admins cannot modify other users posts
	 * and only their own on the front-end
	 *
	 * @param  array $array array of the links to modify
	 * @param  int   $id    id for admin links on the front-end
	 *
	 * @return array $array  modified array of items
	 */
	public function hcommons_reply_admin_links( $array, $id ) {

		$cap = groups_filter_bbpress_caps( 'bp_moderate' );

		if ( $cap == true && bbp_get_current_user_id() !== bbp_get_reply_author_id( bbp_get_reply_id() ) ) {
			unset( $array['edit'] );
		}

		return $array;
	}

	/**
	 * Lets modify the admin links for a forum topic so admins cannot modify other users posts
	 *
	 * @param  string $time_markup preformatted time string
	 * @param  object $activity    the activity
	 *
	 * @return string $society_time_markup society prepended to the time string
	 */
	public function hcommons_filter_activity_time_since( $time_markup, $activity ) {

		$society_id = bp_activity_get_meta( $activity->id, 'society_id', true );
		$commons_name = strtoupper( $society_id ) . ' Commons';
		if ( false !== strpos( $time_markup, ' on ' . $commons_name ) ) { // Deja vu
			return $time_markup;
		}
		$society_time_markup = sprintf( '<span class="time-since"> on %1$s </span>%2$s', $commons_name, $time_markup );

		return $society_time_markup;
	}

	/**
	 * Filter the BP cover image upload dir to be global and not network specific.
	 * Really hacked to handle new group cover images. TODO get this fixed in BP.
	 *
	 * @since HCommons
	 *
	 * @param array $upload_dir
	 *
	 * @return array $upload_dir Modified dir.
	 */
	public function hcommons_cover_image_upload_dir( $upload_dir ) {

		//hcommons_write_error_log( 'info', '****BP_CORE_COVER_IMAGE_UPLOAD_DIR_BEFORE****-' . var_export( $upload_dir, true ) );

		$bp_params = $_POST['bp_params'];

		$path = preg_replace( '~/sites/\d+/~', '/', $upload_dir['path'] );
		if ( 'group' === $bp_params['object'] && ! empty( $bp_params['item_id'] ) && false !== strpos( $path, '/groups/0/cover-image' ) ) {
			$path = str_replace( '/groups/0/cover-image', '/groups/' . $bp_params['item_id'] . '/cover-image', $path );
		}
		if ( ! empty( $path ) ) {
			$upload_dir['path'] = $path;
		}
		$url = preg_replace( '~/sites/\d+/~', '/', $upload_dir['url'] );
		if ( 'group' === $bp_params['object'] && ! empty( $bp_params['item_id'] ) && false !== strpos( $url, '/groups/0/cover-image' ) ) {
			$url = str_replace( '/groups/0/cover-image', '/groups/' . $bp_params['item_id'] . '/cover-image', $url );
		}
		if ( ! empty( $url ) ) {
			$upload_dir['url'] = $url;
		}
		$subdir = $upload_dir['subdir'];
		if ( 'group' === $bp_params['object'] && ! empty( $bp_params['item_id'] ) && false !== strpos( $subdir, '/groups/0/cover-image' ) ) {
			$subdir = str_replace( '/groups/0/cover-image', '/groups/' . $bp_params['item_id'] . '/cover-image', $subdir );
		}
		if ( ! empty( $subdir ) ) {
			$upload_dir['subdir'] = $subdir;
		}
		$basedir = preg_replace( '~/sites/\d+/~', '/', $upload_dir['basedir'] );
		if ( ! empty( $basedir ) ) {
			$upload_dir['basedir'] = $basedir;
		}
		$baseurl = preg_replace( '~/sites/\d+/~', '/', $upload_dir['baseurl'] );
		if ( ! empty( $baseurl ) ) {
			$upload_dir['baseurl'] = $baseurl;
		}

		//hcommons_write_error_log( 'info', '****BP_CORE_COVER_IMAGE_UPLOAD_DIR_AFTER****-' . '-' . var_export( $upload_dir, true ) );

		return $upload_dir;
	}

	/**
	 * Filter the BP attachments upload dir to be global and not network specific.
	 *
	 * @since HCommons
	 *
	 * @param string|array $retval
	 * @param string       $data
	 *
	 * @return string|array $retval
	 */
	public function hcommons_attachments_uploads_dir_get( $retval, $data ) {

		//hcommons_write_error_log( 'info', '****BP_CORE_ATTACHMENTS_UPLOADS_DIR_GET_BEFORE****-'.var_export( $retval, true ).'-'.var_export( $data, true ) );

		if ( empty( $data ) ) {
			$basedir = preg_replace( '~/sites/\d+/~', '/', $retval['basedir'] );
			if ( ! empty( $basedir ) ) {
				$retval['basedir'] = $basedir;
			}
			$baseurl = preg_replace( '~/sites/\d+/~', '/', $retval['baseurl'] );
			if ( ! empty( $baseurl ) ) {
				$retval['baseurl'] = $baseurl;
			}
		}

		//hcommons_write_error_log( 'info', '****BP_CORE_ATTACHMENTS_UPLOADS_DIR_GET_AFTER****-'.var_export( $retval, true ).'-'.var_export( $data, true ) );

		return $retval;
	}

	/**
	 * Filter the BP attachments upload dir to be global and not network specific.
	 *
	 * @since HCommons
	 *
	 * @param string|array $data
	 * @param string       $dir
	 *
	 * @return string|array $data
	 */
	public function hcommons_attachment_upload_dir( $data, $dir ) {

		//hcommons_write_error_log( 'info', '****BP_CORE_ATTACHMENTS_UPLOAD_DIR_BEFORE****-'.var_export( $data, true ).'-'.var_export( $dir, true ) );

		$basedir = preg_replace( '~/sites/\d+/~', '/', $data['basedir'] );
		if ( ! empty( $basedir ) ) {
			$data['basedir'] = $basedir;
		}
		$baseurl = preg_replace( '~/sites/\d+/~', '/', $data['baseurl'] );
		if ( ! empty( $baseurl ) ) {
			$data['baseurl'] = $baseurl;
		}

		//hcommons_write_error_log( 'info', '****BP_CORE_ATTACHMENTS_UPLOAD_DIR_AFTER****-'.var_export( $data, true ).'-'.var_export( $dir, true ) );

		return $data;
	}

	/**
	 * copied from bbp_format_buddypress_notifications()
	 * added switch_to_blog logic for multinetwork compatibility
	 */
	public function hcommons_bbp_format_buddypress_notifications( $action, $item_id, $secondary_item_id, $total_items, $format = 'string', $component_action_name, $component_name, $notification_id ) {
		$return = $action;

		if ( function_exists( 'bbp_format_buddypress_notifications' ) ) {

			// New reply notifications
			if ( 'bbp_new_reply' === $action ) {
				$society_id           = bp_notifications_get_meta( $notification_id, 'society_id', true );
				$notification_blog_id = (int) constant( strtoupper( $society_id ) . '_ROOT_BLOG_ID' );
				$switched             = false;
				if ( ! empty( $notification_blog_id ) && $notification_blog_id !== get_current_blog_id() ) {
					switch_to_blog( $notification_blog_id );
					$switched = true;
				}

				$topic_id    = bbp_get_reply_topic_id( $item_id );
				$topic_title = bbp_get_topic_title( $topic_id );
				$topic_link  = wp_nonce_url( add_query_arg( array(
					'action'   => 'bbp_mark_read',
					'topic_id' => $topic_id
				), bbp_get_reply_url( $item_id ) ), 'bbp_mark_topic_' . $topic_id );
				$title_attr  = __( 'Topic Replies', 'bbpress' );

				if ( (int) $total_items > 1 ) {
					$text   = sprintf( __( 'You have %d new replies', 'bbpress' ), (int) $total_items );
					$filter = 'bbp_multiple_new_subscription_notification';
				} else {
					if ( ! empty( $secondary_item_id ) ) {
						$text = sprintf( __( 'You have %d new reply to %2$s from %3$s', 'bbpress' ), (int) $total_items, $topic_title, bp_core_get_user_displayname( $secondary_item_id ) );
					} else {
						$text = sprintf( __( 'You have %d new reply to %s', 'bbpress' ), (int) $total_items, $topic_title );
					}
					$filter = 'bbp_single_new_subscription_notification';
				}

				// WordPress Toolbar
				if ( 'string' === $format ) {
					$return = apply_filters( $filter, '<a href="' . esc_url( $topic_link ) . '" title="' . esc_attr( $title_attr ) . '">' . esc_html( $text ) . '</a>', (int) $total_items, $text, $topic_link );

					// Deprecated BuddyBar
				} else {
					$return = apply_filters( $filter, array(
						'text' => $text,
						'link' => $topic_link
					), $topic_link, (int) $total_items, $text, $topic_title );
				}

				do_action( 'bbp_format_buddypress_notifications', $action, $item_id, $secondary_item_id, $total_items );

				if ( $switched ) {
					restore_current_blog();
				}
			}

		}

		return $return;
	}

	/**
	 * Filter that enables forums by default on new group creation screen
	 *
	 * @param  int $forum false by default
	 *
	 * @return int  $forum  true to enable forum by default
	 */
	public function hcommons_get_new_group_enable_forum( $forum ) {

		//grabs current step during group creation only
		$current_step = bp_get_groups_current_create_step();

		//we only want the discussion forum to be checked by default on group creation
		if ( $current_step == 'forum' ) {

			$forum = 1;

		}

		return $forum;
	}

	/**
	 * Handles logic from ajax call in child-theme
	 *
	 * @return void
	 */
	public function hcommons_settings_general_ajax() {

		//lets check if the server is sending a POST request with the nonce as data
		if ( $_SERVER['REQUEST_METHOD'] == 'POST' && wp_verify_nonce( $_POST['nonce'], 'settings_general_nonce' ) ) {

			//lets get the current user data
			$user = wp_get_current_user();

			if ( isset( $_POST['primary_email'] ) && ! empty( $_POST['primary_email'] ) ) {

				$user->user_email = $_POST['primary_email'];
				$updated_user     = wp_update_user( [
					'ID'         => $user->ID,
					'user_email' => esc_attr( $_POST['primary_email'] )
				] );

				//if there is a wp_error on wp_update_user(),
				//there was a problem saving the record, if there isnt then output json data for ajax
				if ( ! is_wp_error( $updated_user ) ) {
					echo json_encode( [ 'updated' => true, 'primary_email' => $user->user_email ] );
				}

			}

		}

		die();

	}

	/* Filter the activity query by the society id for the current network admin.
	 *
	 * @since HCommons
	 *
	 * @param array $args
	 * @return array $args
	 */
	public function hcommons_set_network_admin_activities_query( $args ) {

		if ( ! is_admin() ) {
			return $args;
		}

		$args['meta_query'] = array(
			array(
				'key'     => 'society_id',
				'value'   => self::$society_id,
				'type'    => 'string',
				'compare' => '='
			),
		);

		return $args;

	}

	/**
	 * Removes bp_settings_general action for front-end so custom built primary email switching can work
	 *
	 * @return void
	 */
	public function hcommons_remove_bp_settings_general() {
		remove_action( 'bp_actions', 'bp_settings_action_general', 10 );
	}

	/**
	 * Sets default group subscription settings in group creation step to 'digest' instead of 'all emails'
	 *
	 * @return void
	 */
	public function hcommons_groups_group_before_save() {

		global $bp;

		groups_update_groupmeta( $bp->groups->new_group_id, 'ass_default_subscription', 'dig' );

	}

	/**
	 * Waiting period for BP DOCS
	 *
	 * @return array
	 */
	public function hcommons_check_docs_new_member_caps( $caps, $cap, $user_id, $args ) {

		$vetted_user = $this->hcommons_vet_user();

		if ( ! $vetted_user ) {
			return array( 'do_not_allow' );
		} else {
			return $caps;
		}
	}

	/**
	 * Waiting period for site creation
	 *
	 * @return string
	 */
	public function hcommons_check_sites_new_member_status( $active_signup ) {

		$vetted_user = $this->hcommons_vet_user();

		if ( ! $vetted_user ) {
			return 'none';
		} else {
			return $active_signup;
		}
	}

	/**
	 * Get page content from a page on given society network
	 *
	 * @return string
	 */
	public static function hcommons_get_society_page_by_slug( $atts ) {

		$atts = shortcode_atts( array( 'society_id' => 'hc', 'slug' => '' ), $atts, 'hcommons_society_page' );
		if ( empty( $atts['slug'] ) ) {
			return;
		}

		$switched = false;
		if ( defined( strtoupper( $atts['society_id'] ) . '_ROOT_BLOG_ID' ) ) {
			$society_blog_id = (int) constant( strtoupper( $atts['society_id'] ) . '_ROOT_BLOG_ID' );
			if ( $society_blog_id !== get_current_blog_id() ) {
				switch_to_blog( $society_blog_id );
				$switched = true;
			}
		} else {
			return;
		}

		$society_page = get_page_by_path( $atts['slug'] );
		if ( empty( $society_page ) ) {
			if ( $switched ) {
				restore_current_blog();
			}

			return;
		}
		$page_content = apply_filters( 'the_content', $society_page->post_content );

		if ( $switched ) {
			restore_current_blog();
		}

		return $page_content;

	}

	/**
	 * Shortcode to get variable from the server environment
	 *
	 * @return string
	 */
	public static function hcommons_get_env_variable( $atts ) {

		$atts = shortcode_atts( array( 'var' => '' ), $atts, 'hcommons_env_variable' );
		if ( empty( $atts['var'] ) ) {
			return;
		}
		//TODO whitelist the allowed values

		$env_variable = $_SERVER[ $atts['var'] ];

		return $env_variable;

	}

	/**
	 * Functions not tied to any filter or action.
	 */

	/**
	 * Try to catch the spammers
	 *
	 * @return boolean
	 */
	public static function hcommons_vet_user() {

		$current_user = wp_get_current_user();
		$member_types = (array) bp_get_member_type( $current_user->ID, false );
		if ( empty( $member_types ) || ( 1 == count( $member_types ) && in_array( 'hc', $member_types ) ) ) {
			$society_member = false;
		} else {
			return true;
		}

		$timeDiff = time() - strtotime( $current_user->user_registered );

		if ( $timeDiff < ( 60 * 60 * 48 ) ) {
			//return false;
			return true; // disable spammer check for now
		} else {
			return true;
		}
	}

	/**
	 * Unserializes the shib_email meta to return to the user as an array
	 *
	 * @param   object $user user object to be passed
	 *
	 * @return  array  $shib_email  array to be used
	 */
	public static function hcommons_shib_email( $user ) {

		$shib_email = maybe_unserialize( get_user_meta( $user->ID, 'shib_email', true ) );

		if ( ! is_string( $shib_email ) ) {

			//loops through the array and filters out anything that is null
			$email = array_filter( $shib_email );

			return array_unique( $email );
		} else {
			return $shib_email;
		}

	}

	/**
	 * Return user memberships from session
	 *
	 * @since HCommons
	 *
	 * @return array $memberships
	 */
	public static function hcommons_get_user_memberships() {

		return array();

		$memberships       = array();
		$member_types      = bp_get_member_types();
		$membership_header = $_SERVER['HTTP_ISMEMBEROF'] . ';';
		//hcommons_write_error_log( 'info', '**********************GET_MEMBERSHIPS********************-'.var_export( $membership_header, true ).'-'.var_export($member_types,true) );

		foreach ( $member_types as $key => $value ) {

			$pattern = sprintf( '/Humanities Commons:%1$s:members_%1$s;/', strtoupper( $key ) );
			if ( preg_match( $pattern, $membership_header, $matches ) ) {
				$memberships['societies'][] = $key;
			}

			$pattern = sprintf( '/Humanities Commons:%1$s_(.*?);/', strtoupper( $key ) );
			if ( preg_match_all( $pattern, $membership_header, $matches ) ) {
				//hcommons_write_error_log( 'info', '****GET_MATCHES****-'.$key.'-'.var_export( $matches, true ) );
				$memberships['groups'][ $key ] = $matches[1];
			}

		}

		return $memberships;
	}

	/**
	 * Return user login methods from user meta
	 *
	 * @since HCommons
	 *
	 * @param string $data
	 *
	 * @return bool|string|array $login_methods
	 */
	public static function hcommons_get_user_login_methods( $user_id ) {

		$methods = array();
		if ( defined( 'GOOGLE_LOGIN_METHOD_SCOPE' ) ) {
			$methods[ GOOGLE_LOGIN_METHOD_SCOPE ] = 'Google';
		}
		if ( defined( 'TWITTER_LOGIN_METHOD_SCOPE' ) ) {
			$methods[ TWITTER_LOGIN_METHOD_SCOPE ] = 'Twitter';
		}
		if ( defined( 'HC_LOGIN_METHOD_SCOPE' ) ) {
			$methods[ HC_LOGIN_METHOD_SCOPE ] = 'HC ID';
		}
		if ( defined( 'MLA_LOGIN_METHOD_SCOPE' ) ) {
			$methods[ MLA_LOGIN_METHOD_SCOPE ] = 'Legacy <em>MLA Commons</em>';
		}
		$user_login_methods = (array) maybe_unserialize( get_usermeta( $user_id, 'shib_uid', true ) );
		$login_methods      = array();
		foreach ( $user_login_methods as $user_login_method ) {
			$user_method = explode( '@', $user_login_method );
			if ( ! empty( $user_method[1] ) ) {
				$login_methods[] = $methods[ $user_method[1] ];
			} elseif ( ! empty( $user_login_method ) ) {
				$login_methods[] = 'University';
			}
		}

		//hcommons_write_error_log( 'info', '**********************GET_USER_LOGIN_METHODS********************-' . $user_id . '-' . var_export( $user_login_methods, true ) );

		return $login_methods;

	}

	/**
	 * Return identity provider from session
	 *
	 * @since HCommons
	 *
	 * @return string|bool $identity_provider
	 */
	public static function hcommons_get_identity_provider( $formatted = true ) {

		if ( function_exists( 'shibboleth_session_active' ) && shibboleth_session_active() ) {
			//hcommons_write_error_log( 'info', '**********************GET_IDENTITY_PROVIDER********************-' . var_export( $identity_provider, true ) );
			if ( ! $formatted ) {
				return $_SERVER['HTTP_SHIB_IDENTITY_PROVIDER'];
			}
			$providers = array();
			if ( defined( 'GOOGLE_IDENTITY_PROVIDER' ) ) {
				$providers[ GOOGLE_IDENTITY_PROVIDER ] = 'Google';
			}
			if ( defined( 'TWITTER_IDENTITY_PROVIDER' ) ) {
				$providers[ TWITTER_IDENTITY_PROVIDER ] = 'Twitter';
			}
			if ( defined( 'HC_IDENTITY_PROVIDER' ) ) {
				$providers[ HC_IDENTITY_PROVIDER ] = 'HC ID';
			}
			if ( defined( 'MLA_IDENTITY_PROVIDER' ) ) {
				$providers[ MLA_IDENTITY_PROVIDER ] = 'Legacy <em>MLA Commons</em>';
			}
			$identity_provider = '';
			$identity_provider = $_SERVER['HTTP_SHIB_IDENTITY_PROVIDER'];

			if ( empty( $providers[ $identity_provider ] ) ) {
				return 'University';
			} else {
				return $providers[ $identity_provider ];
			}

		}

		return false;
	}

	/**
	 * Check for non-member active session
	 *
	 * @since HCommons
	 *
	 * @return bool $classes
	 */
	public static function hcommons_non_member_active_session() {

		if ( function_exists( 'shibboleth_session_active' ) && shibboleth_session_active() ) {
			$user_memberships = self::hcommons_get_user_memberships();
			if ( ! empty( $user_memberships ) && ! in_array( self::$society_id, $user_memberships['societies'] ) ) {
				return true;
			}

			return false;
		}

		return false;
	}

	/**
	 * Return user login name from session
	 *
	 * @since HCommons
	 *
	 * @return string|bool $username
	 */
	public function hcommons_get_session_username() {

		if ( function_exists( 'shibboleth_session_active' ) && shibboleth_session_active() ) {
			$username = $_SERVER['HTTP_EMPLOYEENUMBER'];

			return $username;
		}

		return false;
	}

	/**
	 * Return user ORCID from session
	 *
	 * @since HCommons
	 *
	 * @return string|bool $orcid
	 */
	public static function get_session_orcid() {

		if ( function_exists( 'shibboleth_session_active' ) && shibboleth_session_active() ) {
			$shib_orcid = $_SERVER['HTTP_EDUPERSONORCID'];
			if ( ! empty( $shib_orcid ) ) {
				if ( false === strpos( $shib_orcid, ';' ) ) {
					$shib_orcid_updated = str_replace( array(
						'https://orcid.org/',
						'http://orcid.org/'
					), '', $shib_orcid );

					return $shib_orcid_updated;
				} else {
					$shib_orcid_updated = array();
					$shib_orcids        = explode( ';', $shib_orcid );
					foreach ( $shib_orcids as $each_orcid ) {
						if ( ! empty( $each_orcid ) ) {
							$shib_orcid_updated[] = str_replace(
								array( 'https://orcid.org/', 'http://orcid.org/' ),
								'', $each_orcid );
						}
					}

					return $shib_orcid_updated[0];
				}
			}

			return;
		}

		return false;
	}

	/**
	 * Return EPPN from session
	 *
	 * @since HCommons
	 *
	 * @return string|bool $username
	 */
	public static function get_session_eppn() {

		if ( function_exists( 'shibboleth_session_active' ) && shibboleth_session_active() ) {
			$eppn = $_SERVER['HTTP_EPPN'];

			return $eppn;
		}

		return false;
	}

	/**
	 * Return Meta Display Name from session
	 *
	 * @since HCommons
	 *
	 * @return string|bool $username
	 */
	public static function get_session_meta_displayname() {

		if ( function_exists( 'shibboleth_session_active' ) && shibboleth_session_active() ) {
			$meta_displayname = $_SERVER['HTTP_META_DISPLAYNAME'];

			return $meta_displayname;
		}

		return false;
	}

	/**
	 * Lookup society group id by name.
	 *
	 * @since HCommons
	 *
	 * @param string $society_id
	 * @param string $group_name
	 *
	 * @return string group id
	 */
	public function hcommons_lookup_society_group_id( $society_id, $group_name ) {

		$managed_group_names = wp_cache_get( $society_id . '_managed_group_names', 'hcommons_settings' );

		if ( false === $managed_group_names || empty( $managed_group_names ) ) {

			$bp = buddypress();
			global $wpdb;
			$managed_group_names = array();
			$all_groups          = $wpdb->get_results( 'SELECT * FROM ' . $bp->table_prefix . 'bp_groups' );
			foreach ( $all_groups as $group ) {

				$group_society_id = bp_groups_get_group_type( $group->id, true );
				if ( $society_id === $group_society_id ) {
					$autopopulate = groups_get_groupmeta( $group->id, 'autopopulate' );
					if ( ! empty( $autopopulate ) && 'Y' === $autopopulate ) {
						$managed_group_names[ strip_tags( stripslashes( $group->name ) ) ] = $group->id;
					}

				}

			}
			wp_cache_set( $society_id . '_managed_group_names', $managed_group_names, 'hcommons_settings', 24 * HOUR_IN_SECONDS );
		}

		//hcommons_write_error_log( 'info', '****DUMP_LOOKUP_TRANSIENT***-' . var_export( $managed_group_names, true ) );
		return $managed_group_names[ $group_name ];

	}

	/**
	 * helper function to facilitate conditions where caller can be identified by function/class name
	 *
	 * @param string $key   a key in the backtrace to check, e.g. 'function' or 'class'
	 * @param string $value the value of $key to look for, i.e. the function/class name
	 *
	 * @return bool does debug_backtrace() contain the specified key/value pair?
	 */
	public static function backtrace_contains( $key, $value ) {
		$retval = false;

		foreach ( debug_backtrace() as $bt ) {
			if ( isset( $bt[ $key ] ) && $value === $bt[ $key ] ) {
				$retval = true;
				break;
			}
		}

		return $retval;
	}
}

$humanities_commons = new Humanities_Commons;

function hcommons_check_non_member_active_session() {
	return Humanities_Commons::hcommons_non_member_active_session();
}

function hcommons_get_session_orcid() {
	return Humanities_Commons::get_session_orcid();
}

function hcommons_get_session_eppn() {
	return Humanities_Commons::get_session_eppn();
}

function hcommons_get_session_meta_displayname() {
	return Humanities_Commons::get_session_meta_displayname();
}
