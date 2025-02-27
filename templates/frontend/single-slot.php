<?php
get_header();

if ( have_posts() ) : 
    while ( have_posts() ) : the_post();
        $post_id       = get_the_ID();
        $star_rating   = get_post_meta( $post_id, 'star_rating', true );
        $provider_name = get_post_meta( $post_id, 'provider_name', true );
        $rtp           = get_post_meta( $post_id, 'rtp', true );
        $min_wager     = get_post_meta( $post_id, 'min_wager', true );
        $max_wager     = get_post_meta( $post_id, 'max_wager', true );

        // Featured image as hero
        $hero_url = get_the_post_thumbnail_url( $post_id, 'full' );
        ?>

        <div class="slot-hero" style="background-image: url('<?php echo esc_url($hero_url); ?>'); height: 400px; background-size: cover;">
            <!-- Hero area. You can also just echo the_post_thumbnail() if you prefer. -->
        </div>

        <div class="slot-content-wrapper">
            <h1 class="slot-title"><?php the_title(); ?></h1>
            <div class="slot-short-description">
                <?php the_excerpt(); // or a custom excerpt or short summary ?>
            </div>

            <div class="slot-details">
                <p class="slot-star-rating">
                    <?php 
                    // Display star icon and rating
                    // You could also create multiple <i> icons for a "visual" star rating, 
                    // but here we just show one star icon + numeric rating:
                    ?>
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php echo number_format( (float)$star_rating, 1 ); ?>
                </p>
                <p class="slot-provider">
                    <strong>Provider Name:</strong> <?php echo esc_html( $provider_name ); ?>
                </p>
                <p class="slot-rtp">
                    <strong>RTP:</strong> <?php echo esc_html( $rtp ); ?>%
                </p>
                <p class="slot-wager">
                    <?php 
                    // Show some text before/after the min and max wagers
                    echo 'You can wager as little as ' . esc_html( $min_wager ) . ' $ and as much as ' . esc_html( $max_wager ) . ' $.'; 
                    ?>
                </p>
            </div>

            <div class="slot-description">
                <?php the_content(); ?>
            </div>
        </div>

        <?php
    endwhile;
endif;

get_footer();
