<?php

$shib_urls = [
	// IDPs
	getenv('GOOGLE_IDP_URL') . '/idp/profile/Logout',
	getenv('TWITTER_IDP_URL') . '/idp/profile/Logout',
	getenv('MLA_IDP_URL') . '/idp/profile/Logout',
	getenv('HC_IDP_URL') . '/idp/profile/Logout',
	// SPs
	getenv('REGISTRY_SP_URL') . '/Shibboleth.sso/Logout',
	get_site_url() . '/Shibboleth.sso/Logout',
];

setcookie( '_saml_idp', false, time()-3600, '/', '.' . getenv('WP_DOMAIN'), false, true );
setcookie( 'stickyIdPSelection', false, time()-3600, '/', '.' . getenv('WP_DOMAIN'), true, true );
setcookie( 'redirect_to', false, time()-3600, '/', '.' . getenv('WP_DOMAIN'), true, true );
wp_destroy_current_session();
wp_clear_auth_cookie();


get_header(); ?>

<div class="page-full-width">

	<div id="primary" class="site-content">
		<div id="content" role="main">

			<?php while ( have_posts() ) : the_post(); ?>
				<?php get_template_part( 'content', 'page' ); ?>
				<?php comments_template( '', true ); ?>
			<?php endwhile; // end of the loop. ?>


			<?php foreach( $shib_urls as $shib_url ): ?>
				<iframe src="<?php echo $shib_url ?>" style="display:none" title="Log Out" ></iframe>
			<?php endforeach ?>

			<article>
				<a class="button" href="<?php echo wp_logout_url( home_url() ) ?>">Log Out</a>
			</article>

		</div><!-- #content -->
	</div><!-- #primary -->

</div><!-- .page-full-width -->
<?php get_footer(); ?>
