<?php
/**
 * @package Boss Child Theme
 * The parent theme functions are located at /boss/buddyboss-inc/theme-functions.php
 * Add your own functions in this file.
 */

if ( ! defined( 'BP_AVATAR_THUMB_WIDTH' ) ) define ( 'BP_AVATAR_THUMB_WIDTH', 150 );
if ( ! defined( 'BP_AVATAR_THUMB_HEIGHT' ) ) define ( 'BP_AVATAR_THUMB_HEIGHT', 150 );

/**
 * Sets up theme defaults
 *
 * @since Boss Child Theme 1.0.0
 */
function boss_child_theme_setup() {

	/**
	 * Makes child theme available for translation.
	 * Translations can be added into the /languages/ directory.
	 * Read more at: http://www.buddyboss.com/tutorials/language-translations/
	 */

	// Translate text from the PARENT theme.
	load_theme_textdomain( 'boss', get_stylesheet_directory() . '/languages' );

	// Translate text from the CHILD theme only.
	// Change 'boss' instances in all child theme files to 'boss_child_theme'.
	// load_theme_textdomain( 'boss_child_theme', get_stylesheet_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'boss_child_theme_setup' );

/**
 * removes dynamic css (set in boss options admin page) from page output entirely,
 * since we include all necessary rules ourselves in this child theme
 */
function boss_child_theme_remove_dynamic_css( $reduxFramework ) {
	// remove both instances of dynamic css: one from redux, one from boss
	remove_action( 'wp_head', 'boss_generate_option_css', 99 );
	remove_action( 'wp_head', array( $reduxFramework, '_output_css' ), 150 );
}
add_action( 'redux/loaded', 'boss_child_theme_remove_dynamic_css' );

/**
 * Enqueues styles for child theme front-end.
 */
function boss_child_theme_enqueue_style() {
	wp_enqueue_style( 'boss-child-custom', get_stylesheet_directory_uri() . '/css/hc.css' );
}
// priority 200 to ensure this loads after redux which uses 150
add_action( 'wp_enqueue_scripts', 'boss_child_theme_enqueue_style', 200 );


/**
 * Enqueues scripts for child theme front-end.
 */
function boss_child_theme_enqueue_script() {
	wp_enqueue_script( 'boss-child-custom', get_stylesheet_directory_uri() . '/js/boss-child.js' );
}
// priority 200 to ensure this loads after redux which uses 150
add_action( 'wp_enqueue_scripts', 'boss_child_theme_enqueue_script' );

/**
 * some thumbnails have been generated with small dimensions due to
 * BP_AVATAR_THUMB_WIDTH being too small at the time. this is a temporary
 * workaround to prevent artifacts/blurriness where those thumbnails appear by
 * using the full avatar rather than the thumb.
 *
 * TODO once bad thumbnails have been replaced/removed, this filter should be
 * removed to improve performance.
 */
function hcommons_filter_bp_get_group_invite_user_avatar() {
	global $invites_template;
	return $invites_template->invite->user->avatar; // rather than avatar_thumb
}
add_filter( 'bp_get_group_invite_user_avatar', 'hcommons_filter_bp_get_group_invite_user_avatar' );

/**
 * affects boss mobile right-hand main/top user menu
 */
function boss_child_change_profile_edit_to_view_in_adminbar() {
	global $wp_admin_bar;

	if ( is_user_logged_in() ) {
		// the item which has the user's name/avatar as title and links to "edit"
		$user_info_clone = $wp_admin_bar->get_node( 'user-info' );
		// the item which has "Profile" as title and links to "view"
		$my_account_xprofile_clone = $wp_admin_bar->get_node( 'my-account-xprofile' );

		// use "view" url for the name/avatar item
		$user_info_clone->href = $my_account_xprofile_clone->href;
		$wp_admin_bar->add_menu( $user_info_clone );

		// remove the second, now redundant, item
		$wp_admin_bar->remove_menu( 'edit-profile' );
	}
}
// priority 1000 to override boss buddyboss_strip_unnecessary_admin_bar_nodes()
add_action( 'admin_bar_menu', 'boss_child_change_profile_edit_to_view_in_adminbar', 1000 );


/**
 * Handles ajax for the boss-child theme
 * @return void
 */
function boss_child_theme_ajax() {

	//this is for settings-general ajax
	$user = wp_get_current_user();
	$nonce = wp_create_nonce('settings_general_nonce');
	wp_localize_script( 'boss-child-custom', 'settings_general_req', [ 'user' => $user, 'nonce' => $nonce ], ['jquery'] );

}

add_action('wp_enqueue_scripts', 'boss_child_theme_ajax');

function boss_child_fix_redux_script_paths() {
	global $wp_scripts;
	foreach ( $wp_scripts->registered as &$registered ) {
		$registered->src = str_replace( '/srv/www/commons/current/web', '', $registered->src );
	}
}
add_action( 'admin_enqueue_scripts', 'boss_child_fix_redux_script_paths' );

function boss_child_turn_off_redux_ajax_save( $data ) {
	$data['args']['ajax_save'] = false;
	return $data;
}
add_filter( 'redux/boss_options/localize', 'boss_child_turn_off_redux_ajax_save' );

/**
 * Adds support for user at-mentions to the Suggestions API.
 */
class MLA_Name_Suggestions extends BP_Suggestions {

        /**
        * Default arguments for this suggestions service.
        *
        * @since BuddyPress (2.1.0)
        * @var array $args {
        *     @type int $limit Maximum number of results to display. Default: 200.
        *     @type string $term The suggestion service will try to find results that contain this string.
        *           Mandatory.
        * }
        */
        protected $default_args = array(
                'limit'        => 200,
                'term'         => '',
                'type'         => '',
        );

        /**
        * Validate and sanitise the parameters for the suggestion service query.
        *
        * @return true|WP_Error If validation fails, return a WP_Error object. On success, return true (bool).
        * @since BuddyPress (2.1.0)
        */
        public function validate() {
                $this->args = apply_filters( 'mla_name_suggestions_args', $this->args, $this );

                // Check for invalid or missing mandatory parameters.
                if ( empty( $this->args['term'] ) || ! is_user_logged_in()  ) {
                        return new WP_Error( 'missing_requirement' );
                }

                return apply_filters( 'mla_name_suggestions_validate_args', parent::validate(), $this );
        }

        /**
        * Find and return a list of user name suggestions that match the query.
        *
        * @return array|WP_Error Array of results. If there were problems, returns a WP_Error object.
        * @since BuddyPress (2.1.0)
        */
        public function get_suggestions() {

                $user_query = array(
                        'count_total'     => '',  // Prevents total count
                        'populate_extras' => false,
                        'type'            => 'alphabetical',

                        'page'            => 1,
                        'per_page'        => $this->args['limit'],
                        'search_terms'    => $this->args['term'],
                        'search_wildcard' => 'right',
                );

                $user_query = apply_filters( 'mla_suggestions_query_args', $user_query, $this );

                if ( is_wp_error( $user_query ) ) {
                        return $user_query;
                }

                add_action( 'bp_pre_user_query', array( $this, 'mla_query_users_by_name' ) );

                $user_query = new BP_User_Query( $user_query );

                $results = array();
                foreach ( $user_query->results as $user ) {
                        $result        = new stdClass();
                        $result->ID    = $user->user_nicename;
                        $result->image = bp_core_fetch_avatar( array( 'html' => false, 'item_id' => $user->ID ) );
                        $result->name  = bp_core_get_user_displayname( $user->ID );

                        $results[] = $result;
                }

                return apply_filters( 'mla_name_suggestions_get_suggestions', $results, $this );
        }

        /**
        * Query users by name
        *
        * @param BP_User_Query $bp_user_query
        */
        public function mla_query_users_by_name( $bp_user_query ) {

                global $wpdb;
                $society_id = get_network_option( '', 'society_id' );

        if ( ! empty( $bp_user_query->query_vars['search_terms'] ) ) {
                        $bp_user_query->uid_clauses['where'] = " WHERE u.ID IN ( SELECT tr.object_id FROM {$wpdb->users} us, wp_1000360_terms t, wp_1000360_term_relationships tr, wp_1000360_term_taxonomy tt where t.term_id = tt.term_id and tt.term_taxonomy_id = tr.term_taxonomy_id and tt.taxonomy='bp_member_type' and t.slug='{$society_id}' and tr.object_id = us.ID and us.spam = 0 AND us.deleted = 0 AND us.user_status = 0 AND ( us.display_name LIKE '%" . ucfirst( strtolower(  $bp_user_query->query_vars['search_terms'] ) ) ."%' OR us.user_login LIKE '%" . strtolower( $bp_user_query->query_vars['search_terms'] ) . "%' ) )";
                        $bp_user_query->uid_clauses['orderby'] = "ORDER BY u.display_name";
                }

        }

}
add_filter( 'bp_suggestions_services', function() { return 'MLA_Name_Suggestions'; } );

/**
 * Override BP AJAX endpoint for Suggestions API lookups.
 *
 * @since BuddyPress (2.1.0)
 */
function mla_ajax_get_suggestions() {
        if ( ! bp_is_user_active() || empty( $_GET['term'] ) || empty( $_GET['type'] ) ) {
                wp_send_json_error( 'missing_parameter' );
                exit;
        }

        $results = bp_core_get_suggestions( array(
                'term' => sanitize_text_field( $_GET['term'] ),
                'type' => 'mla_members',
                'limit' => '200',
        ) );

        if ( is_wp_error( $results ) ) {
                wp_send_json_error( $results->get_error_message() );
                exit;
        }

        wp_send_json_success( $results );
}
remove_action( 'wp_ajax_bp_get_suggestions', 'bp_ajax_get_suggestions' );
add_action( 'wp_ajax_bp_get_suggestions', 'mla_ajax_get_suggestions' );

/**
 * Enqueue @mentions JS.
 *
*/
function mla_member_mentions_script() {
        if ( ! bp_activity_maybe_load_mentions_scripts() ) {
                return;
        }

        // Special handling for New/Edit screens in wp-admin
        if ( is_admin() ) {
                if (
                        ! get_current_screen() ||
                        ! in_array( get_current_screen()->base, array( 'page', 'post' ) ) ||
                        ! post_type_supports( get_current_screen()->post_type, 'editor' ) ) {
                        return;
                }
        }

	$min = '';
        //$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        wp_enqueue_script( 'mla-mentions', get_stylesheet_directory_uri() . "/js/mentions{$min}.js", array( 'jquery', 'jquery-atwho' ), bp_get_version(), true );
	wp_enqueue_style( 'mla-mentions-css', get_stylesheet_directory_uri() . "/css/mentions{$min}.css", array(), bp_get_version() );

}
remove_action( 'bp_enqueue_scripts', 'bp_activity_mentions_script' );
remove_action( 'bp_admin_enqueue_scripts', 'bp_activity_mentions_script' );
add_action( 'bp_enqueue_scripts', 'mla_member_mentions_script' );
add_action( 'bp_admin_enqueue_scripts', 'mla_member_mentions_script' );

function mla_mentions_script_enable( $current_status ) {
        return $current_status || bp_is_groups_component();
}
add_filter( 'bp_activity_maybe_load_mentions_scripts', 'mla_mentions_script_enable' );

/**
 * @param boolean $load
 * @param $mentions_enabled
 * @return boolean enabled ot not?
 */
function buddydev_enable_mention_autosuggestions_on_compose( $load, $mentions_enabled ) {

        if ( ! $mentions_enabled ) {
                return $load; //activity mention is  not enabled, so no need to bother
        }
        //modify this condition to suit yours
        if( is_user_logged_in() && bp_is_messages_compose_screen() ) {
                $load = true;
        }

        return $load;
}
add_filter( 'bp_activity_maybe_load_mentions_scripts', 'buddydev_enable_mention_autosuggestions_on_compose', 10, 2 );

/**
 * Removes autocomplete js and css so mentions.js can be used in compose screen for autocomplete
 *
 * @return void
 */
function remove_messages_add_autocomplete_js_css() {
        remove_action( 'bp_enqueue_scripts', 'messages_add_autocomplete_js' );
        remove_action( 'wp_head', 'messages_add_autocomplete_css' );
}

add_action( 'init', 'remove_messages_add_autocomplete_js_css' );

/**
 * This is dequeued by remove_messages_add_autocomplete_js_css(),
 * but Boss adds it back in buddyboss_scripts_styles().
 * Remove it on that action again.
 */
function hcommons_dequeue_bgiframe() {
	wp_dequeue_script( 'bp-jquery-bgiframe' );
}
add_action( 'wp_enqueue_scripts', 'hcommons_dequeue_bgiframe', 20 );

/**
 * Fixes css in admin for discussion forum metabox
 *
 * @return void
 */
function groups_discussion_admin_metabox() {

        echo '<style type="text/css">';
        echo '#bbpress_group_admin_ui_meta_box .field-group, #bbpress_group_admin_ui_meta_box p { max-width: 65% !important; float: left !important; }';
        echo '</style>';

}

add_action( 'admin_head', 'groups_discussion_admin_metabox' );

/**
 * Circumvent the signup allowed option to always show the register button in the header.
 * @uses Humanities_Commons
 */
function hcommons_filter_bp_get_signup_allowed( $allowed ) {
	if ( class_exists( 'Humanities_Commons' ) && Humanities_Commons::backtrace_contains( 'file', '/srv/www/commons/current/web/app/themes/boss/header.php' ) ) {
		$allowed = true;
	}

	return $allowed;
}
//add_filter( 'bp_get_signup_allowed', 'hcommons_filter_bp_get_signup_allowed' );

/**
 * overriding parent function to use group/member avatars in search results
 * TODO use group/member avatars in search results
 */
function buddyboss_entry_meta( $show_author = true, $show_date = true, $show_comment_info = true ) {
	global $post;

	// Translators: used between list items, there is a space after the comma.
	$categories_list = get_the_category_list( __( ', ', 'boss' ) );

	// Translators: used between list items, there is a space after the comma.
	$tag_list = get_the_tag_list( '', __( ', ', 'boss' ) );

	$date = sprintf( '<a href="%1$s" title="%2$s" rel="bookmark" class="post-date fa fa-clock-o"><time class="entry-date" datetime="%3$s">%4$s</time></a>', esc_url( get_permalink() ), esc_attr( get_the_time() ), esc_attr( get_the_date( 'c' ) ), esc_html( get_the_date() )
	);

	$author = sprintf( '<span class="author vcard"><a class="url fn n" href="%1$s" title="%2$s" rel="author">%3$s</a></span>', esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ), esc_attr( sprintf( __( 'View %s', 'boss' ), get_the_author() ) ), get_the_author()
	);

	// for bp avatars
	$args = [
		'item_id' => get_the_id(),
		'height' => 65,
		'width' => 65,
	];

	switch ( $post->post_type ) {
		case EP_BP_API::GROUP_TYPE_NAME:
			$args['type'] = 'group';
			$args = array_merge( $args, [
				'avatar_dir' => 'group-avatars',
				'object'     => 'group',
			] );
			$avatar = sprintf( '<a href="%1$s" rel="bookmark">%2$s</a>', esc_url( $post->permalink ), bp_core_fetch_avatar( $args ) );
			break;
		case 'reply':
		case 'topic':
		case 'humcore_deposit':
			$args['item_id'] = $post->post_author;
		case EP_BP_API::MEMBER_TYPE_NAME:
			$args['type'] = 'user';
			$avatar = sprintf( '<a href="%1$s" rel="bookmark">%2$s</a>', esc_url( $post->permalink ), bp_core_fetch_avatar( $args ) );
			break;
	}

	if ( empty( $avatar ) && function_exists( 'get_avatar' ) ) {
		$avatar = sprintf( '<a href="%1$s" rel="bookmark">%2$s</a>', esc_url( get_permalink() ), get_avatar( get_the_author_meta( 'email' ), 55 )
		);
	}

	if ( $show_author ) {
		echo '<span class="post-author">';
		echo $avatar;
		echo $author;
		echo '</span>';
	}

	if ( $show_date ) {
		echo $date;
	}

	if ( $show_comment_info ) {
		if ( comments_open() ) :
?>
				<!-- reply link -->
				<span class="comments-link fa fa-comment-o">
					<?php comments_popup_link( '<span class="leave-reply">' . __( '0 comments', 'boss' ) . '</span>', __( '1 comment', 'boss' ), __( '% comments', 'boss' ) ); ?>
				</span><!-- .comments-link -->
<?php
			endif; // comments_open()
	}
}
