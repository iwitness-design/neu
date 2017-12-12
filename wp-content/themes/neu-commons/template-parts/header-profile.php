<?php
global $rtl, $woocommerce, $humanities_commons;
$header_style = boss_get_option('boss_header');
$boxed = boss_get_option( 'boss_layout_style' );
?>

<div class="<?php echo ($rtl) ? 'left-col' : 'right-col'; ?><?php if($woocommerce) { echo ' woocommerce'; } ?>">

    <?php if ( '2' == $header_style ): ?>
    <div class="table">
    <?php endif; ?>

	<?php if ( '1' == $header_style ): ?>
	<?php if ( $boxed == 'boxed' ): ?>
		<div class="header-notifications search-toggle">
			<a href="#" class="fa fa-search closed"></a>
		</div>
	<?php endif; ?>

	<div class="<?php echo ($rtl) ? 'left-col-inner' : 'right-col-inner'; ?>">
    <?php endif; ?>

		<?php
		if ( is_user_logged_in() ) {
			get_template_part( 'template-parts/header-profile-member' );
		} else { ?>

            <!-- Woocommerce Notification for guest users-->
            <?php echo boss_cart_icon_html(); ?>

			<?php /* non-network-member menu. user is logged in to at least one other network, but not this one. */ ?>
			<?php
				if ( is_a( $humanities_commons, 'Humanities_Commons' ) && $humanities_commons->hcommons_non_member_active_session() ) :

					$session_username = $humanities_commons->hcommons_get_session_username();
					wp_set_current_user( null, $session_username );

					get_template_part( 'template-parts/header-profile-member' );

					// restore default current user, which is logged out
					wp_set_current_user( null );
				?>
			<!-- Register/Login links for logged out users -->
			<?php elseif ( !is_user_logged_in() && buddyboss_is_bp_active() && !bp_hide_loggedout_adminbar( false ) ) : ?>
                <?php if( '2' == boss_get_option('boss_header') ){ ?>
                <div class="table-cell">
                <?php } ?>
                    <?php if ( buddyboss_is_bp_active() && bp_get_signup_allowed() ) : ?>
                        <a href="<?php echo bp_get_signup_page(); ?>" class="register screen-reader-shortcut"><?php _e( 'Register', 'boss' ); ?></a>
                    <?php endif; ?>

                    <a href="<?php echo wp_login_url(); ?>" class="login"><?php _e( 'Log In', 'boss' ); ?></a>
                <?php if( '2' == boss_get_option('boss_header') ){ ?>
                </div>
                <?php } ?>

			<?php endif; ?>

		<?php } ?> <!-- if ( is_user_logged_in() ) -->

	</div><!--.left-col-inner/.table-->

</div><!--.left-col-->
