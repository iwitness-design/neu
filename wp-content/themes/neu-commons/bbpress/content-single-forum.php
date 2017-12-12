<?php

/**
 * Single Forum Content Part
 *
 * @package bbPress
 * @subpackage Boss
 */

?>

<div id="bbpress-forums">

	<?php do_action( 'bbp_template_before_single_forum' ); ?>

	<?php if ( post_password_required() ) : ?>

		<?php bbp_get_template_part( 'form', 'protected' ); ?>

	<?php else : ?>

        <div class="bbp-forum-details">
            <div class="table-cell">
                <?php bbp_breadcrumb(); ?>
                <?php bbp_forum_subscription_link(); ?>
            </div>

            <?php if ( current_user_can('publish_topics') ) : ?>

            	<div class="table-cell">
	            <span id="topic-form-toggle">
                        <button id="add">Create New Topic</button>
                     </span>
                </div>

            <?php  endif; ?>

            <?php buddyboss_bbp_single_forum_description(array('before'=>'<div class="bbp-forum-data">', 'after'=>'</div>')); ?>
        </div>

	<div class="topic-form">
            <?php bbp_get_template_part( 'form',       'topic'     ); ?>
        </div>

		<?php if ( bbp_has_forums() ) : ?>

			<?php bbp_get_template_part( 'loop', 'forums' ); ?>

		<?php endif; ?>

		<?php if ( !bbp_is_forum_category() && bbp_has_topics() ) : ?>

			<?php bbp_get_template_part( 'pagination', 'topics'    ); ?>

			<?php bbp_get_template_part( 'loop',       'topics'    ); ?>

			<?php bbp_get_template_part( 'pagination', 'topics'    ); ?>

		<?php elseif ( !bbp_is_forum_category() ) : ?>

			<?php bbp_get_template_part( 'feedback',   'no-topics' ); ?>

		<?php endif; ?>

	<?php endif; ?>

	<?php do_action( 'bbp_template_after_single_forum' ); ?>

</div>
