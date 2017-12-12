<?php
			$name_class = '';
			$update_data = wp_get_update_data();

			if ($update_data['counts']['total'] && current_user_can( 'update_core' ) && current_user_can( 'update_plugins' ) && current_user_can( 'update_themes' )) {
					$name_class = 'has_updates';
					?>
					<!-- Notification -->
					<div class="header-notifications updates">
							<a class="notification-link fa fa-refresh" href="<?php echo network_admin_url( 'update-core.php' ); ?>">
								 <span class="ab-label"><?php echo number_format_i18n( $update_data['counts']['total'] ); ?></span>
							</a>
					</div><?php
}

if ( buddyboss_is_bp_active() && bp_is_active( 'notifications' ) ):

	if ( function_exists( 'buddyboss_notification_bp_members_shortcode_bar_notifications_menu' ) ) {
		echo do_shortcode( '[buddyboss_notification_bar]' );
	} else {

		$notifications	 = buddyboss_adminbar_notification();
		$link			 = $notifications[ 0 ];
		unset( $notifications[ 0 ] );
		?>

		<!-- Notification -->
		<div class="header-notifications all-notifications">
			<a class="notification-link fa fa-bell" href="<?php
			if ( $link ) {
				echo $link->href;
			}
			?>">
					 <?php
					 if ( $link ) {
						 echo $link->title;
					 }
					 ?>
			</a>

			<div class="pop">
				<?php
				if ( $link ) {
					foreach ( $notifications as $notification ) {
						echo '<a href="' . $notification->href . '">' . $notification->title . '</a>';
					}
				}
				?>
			</div>
		</div>

		<?php
	}
	?>

<?php endif; ?>

			<!-- Woocommerce Notification -->
			<?php echo boss_cart_icon_html(); ?>

<?php if ( buddyboss_is_bp_active() ) { ?>

	<!--Account details -->
	<div class="header-account-login">

		<?php do_action( "buddyboss_before_header_account_login_block" ); ?>

		<a class="user-link" href="<?php echo bp_core_get_user_domain( get_current_user_id() ); ?>">
			<span class="name <?php echo $name_class; ?>"><?php echo bp_core_get_user_displayname( get_current_user_id() ); ?></span>
			<span>
				<?php echo bp_core_fetch_avatar( array( 'item_id' => get_current_user_id(), 'type' => 'full', 'width' => '100', 'height' => '100' ) ); ?>                        </span>
		</a>

		<div class="pop">
			<!-- Dashboard links -->
			<?php
			if ( boss_get_option( 'boss_dashboard' ) &&  current_user_can( 'read' ) ) :
				get_template_part( 'template-parts/header-dashboard-links' );
			endif;
			?>

			<!-- Adminbar -->
			<div id="adminbar-links" class="bp_components">
				<?php buddyboss_adminbar_myaccount(); ?>
			</div>

			<?php
			if ( boss_get_option( 'boss_profile_adminbar' ) ) {
				wp_nav_menu( array( 'theme_location' => 'header-my-account', 'fallback_cb' => '', 'menu_class' => 'links' ) );
			}
			?>

			<span class="logout">
				<a href="<?php echo wp_logout_url(); ?>"><?php _e( 'Logout', 'boss' ); ?></a>
			</span>
		</div>

		<?php do_action( "buddyboss_after_header_account_login_block" ); ?>

	</div><!--.header-account-login-->

<?php } ?>
