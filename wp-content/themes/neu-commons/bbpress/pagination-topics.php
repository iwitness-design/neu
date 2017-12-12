<?php

/**
 * Pagination for pages of topics (when viewing a forum)
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php
   $bbp = bbpress();

    if(10 <  $bbp->topic_query->found_posts):

?>

<?php do_action( 'bbp_template_before_pagination_loop' ); ?>

<div class="bbp-pagination">
       	<div class="bbp-pagination-count">

                <?php bbp_forum_pagination_count(); ?>

	</div>

	<div class="bbp-pagination-links">

		<?php bbp_forum_pagination_links(); ?>

	</div>
</div>


<?php do_action( 'bbp_template_after_pagination_loop' ); ?>

<?php endif; ?>
