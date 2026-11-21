<?php
/**
 * Title: Featured products
 * Slug: abedin-trade-point/featured-products
 * Categories: abedin-trade-point
 * Inserter: true
 */
?>
<!-- wp:group {"align":"full","backgroundColor":"pale","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-pale-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
<!-- wp:group {"align":"wide","layout":{"type":"constrained","contentSize":"640px","justifyContent":"left"}} -->
<div class="wp-block-group alignwide">
<!-- wp:heading --><h2 class="wp-block-heading">Start Your Quote List</h2><!-- /wp:heading -->
<!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color">Select the products you are interested in, add the quantities you need and send them together in one request.</p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:group {"align":"wide","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide"><!-- wp:shortcode -->[products limit="8" columns="4" visibility="featured" orderby="menu_order"]<!-- /wp:shortcode --></div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
