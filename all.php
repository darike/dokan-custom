<?php

/**
 * Plugin Name: Dokan Custom
 * Plugin URI: https://lieferfair.com/dokan-custom
 * Description: Custom functionality for Dokan including grouped products support
 * Version: 1.0.0
 * Author: Hamza Kurt
 * Author URI: https://lieferfair.com
 * Text Domain: dokan-custom
 * Requires at least: 5.8
 * Requires PHP: 7.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Dokan Seller Profile Enhancement
 */

// Add the custom content after seller profile frame
add_action('dokan_store_profile_frame_after', 'custom_content_after_seller_profile', 10, 2);

function custom_content_after_seller_profile($store_user_data, $store_info) {
    $store_user = get_user_by('id', $store_user_data->ID);
    $profile_img_class = 'some-custom-class';
    
    $rating = dokan_get_readable_seller_rating($store_user->ID);
    $store_slug = dokan_get_store_url($store_user->ID);
    $reviews_url = trailingslashit($store_slug) . 'reviews/';
    $dokan_store_times = !empty($store_info['dokan_store_time']) ? $store_info['dokan_store_time'] : [];
    $current_time = dokan_current_datetime();
    $today = strtolower($current_time->format('l')); // Get current day
    

    $allowed_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    $opening_time = '';
    $closing_time = '';
   


    // Profile section
    echo '<div class="main_vendor_prf">
        <div class="points">
            <div class="profile-img ' . esc_attr($profile_img_class) . '">
                <img src="' . esc_url(get_avatar_url($store_user->ID)) . '" 
                     alt="' . esc_attr($store_user->display_name) . '" 
                     width="150" height="150">
            </div>';

    // Store timing section
    echo '<div class="store-timing-below-logo">';
    foreach ($allowed_days as $day) {
        if (isset($dokan_store_times[$day]) && !empty($dokan_store_times[$day]['opening_time']) && !empty($dokan_store_times[$day]['closing_time'])) {
            $opening_time = $dokan_store_times[$day]['opening_time'][0];
            $closing_time = $dokan_store_times[$day]['closing_time'][0];
            break; // First valid time set karne ke baad loop break
        }
    }

    if ($opening_time && $closing_time) {
        $formatted_opening_time = $current_time->modify($opening_time)->format(wc_time_format());
        $formatted_closing_time = $current_time->modify($closing_time)->format(wc_time_format());

        echo '<div class="store-timing-box" style="background: #FFD700; padding: 10px 15px; border-radius: 10px; display: flex; align-items: center; max-width: 250px;">
                <i class="fas fa-clock" style="color: #166534; margin-right: 8px;"></i>
                <span style="font-weight: bold; color: #166534;">Mon-Sat, ' . esc_html($formatted_opening_time) . ' - ' . esc_html($formatted_closing_time) . '</span>
              </div>';
    } else {
        echo '<p>' . __('No store hours available', 'dokan-lite') . '</p>';
    }
    echo '</div></div>';

    // Vendor name and rating section
    echo '<div class="col-1">
        <div class="name_of_vendor"><h1>' . esc_html($store_info['store_name']) . '</h1></div>
        <div class="align">
            <ul class="rating_vendor">
                <li style="list-style:none;">
                    <i class="fas fa-star"></i>
                    ' . wp_kses_post($rating) . '
                </li>
            </ul>
            <a href="#" class="show-reviews-popup">' . __('See Reviews', 'dokan-lite') . '</a>
        </div>
    </div></div>';

    // Tag tabs and products section
    $seller_id = $store_user->ID;
    
    $args = array(
        'taxonomy'   => 'product_tag',
        'orderby'    => 'name',
        'hide_empty' => true,
    );
    $tags = get_terms($args);

    // Reorder tags to place "Popular" first
    usort($tags, function($a, $b) {
        if ($a->name == 'Popular') return -1;
        if ($b->name == 'Popular') return 1;
        return 0;
    });

    // Create tag tabs
    echo '<div class="category-tabs">';
    
    // Custom live search bar
    echo '<div class="live-search-container">
        <input type="text" id="live-search-input" class="live-search-input" placeholder="Search products..." autocomplete="off">
        <i class="fa fa-search search-icon"></i>
    </div>';
    
    foreach ($tags as $index => $tag) {
        // Check if there are products under this tag
        $product_args = array(
            'post_type' => 'product',
            'posts_per_page' => 1,
            'author' => $seller_id,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_tag',
                    'field'    => 'id',
                    'terms'    => $tag->term_id,
                    'operator' => 'IN',
                ),
            ),
        );
        $product_query = new WP_Query($product_args);
        
        // Display tab if there are products under the tag
        if ($product_query->have_posts()) {
            $active_class = ($index === 0) ? 'active' : ''; // Mark first tab (Popular) as active by default
            echo '<button class="category-tab ' . esc_attr($active_class) . '" data-tag="' . esc_attr($tag->term_id) . '" data-tag-name="' . esc_attr($tag->name) . '">' 
                . esc_html($tag->name) . 
            '</button>';
        }
        wp_reset_postdata();
    }
    echo '</div>';

    // Add products container
    echo '<div id="products-container">';
    
    // Loop through tags and display products
    $product_found = false; // To check if any products are found
    foreach ($tags as $tag) {
        // Check if products exist under this tag before displaying the section
        $product_args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'author' => $seller_id,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_tag',
                    'field'    => 'id',
                    'terms'    => $tag->term_id,
                    'operator' => 'IN',
                ),
            ),
        );
        $product_query = new WP_Query($product_args);

        if ($product_query->have_posts()) {
            echo '<h2 class="section-title">' . esc_html($tag->name) . '</h2>';
            echo '<div id="tag-' . esc_attr($tag->term_id) . '" class="product-grid">';

          while ($product_query->have_posts()) {
    $product_query->the_post();
    $product = wc_get_product(get_the_ID());

    if ($product->is_type('variable')) {
        $available_variations = $product->get_available_variations();
        $min_variation_price = $product->get_variation_price('min', true);
        $max_variation_price = $product->get_variation_price('max', true);
        
        if ($min_variation_price !== $max_variation_price) {
            $price_html = wc_price($min_variation_price) . ' - ' . wc_price($max_variation_price);
        } else {
            $price_html = wc_price($min_variation_price);
        }
    } else {
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();
        $price_html = $sale_price ? wc_price($sale_price) . ' <del>' . wc_price($regular_price) . '</del>' : wc_price($regular_price);
    }
                
                echo '<div class="product-card">
                <a href="#" class="quick-view-button" data-product-id="' . get_the_ID() . '">
                    <div class="title_same">
                       
                            <h3 class="product-name">' . get_the_title() . '</h3>
                     
                        <p class="product-price">' . $price_html . '</p>
                    </div>
                    <div class="main_light">
                       
                            <img src="' . get_the_post_thumbnail_url() . '" alt="' . get_the_title() . '" class="product-image">
                        
                        <button class="add-button" data-product-id="' . get_the_ID() . '"><img src="https://lieferfair-prod-staging.azurewebsites.net/wp-content/uploads/2025/03/Rectangle-47.png"></button>

                    </div>
                    </a>
                </div>
                 <div id="popup-' . get_the_ID() . '" class="product-quick-view-popup" style="display: none;">
                    <div class="product-quick-view-popup-content">
                        <span class="close-popup">&times;</span>
                        <div class="popup-product-image">
                            <img src="' . get_the_post_thumbnail_url() . '" alt="' . get_the_title() . '">
                        </div>
                        <div class="popup-product-details">
                            <h2>' . get_the_title() . '</h2>
                            <p class="price">' . $price_html . '</p>';
                            
                            // Get variations if product is variable
                            if ($product->is_type('variable')) {
                                $variations = $product->get_available_variations();
                                if (!empty($variations)) {
                                    echo '<div class="variable-grouped-products">
                                        <h4>Select variation:</h4>
                                        <div class="variation-checkboxes">';
                                    
                                    // Get default attributes
                                    $default_attributes = $product->get_default_attributes();
                                    $default_variation_id = null;
                                    $lowest_price = PHP_FLOAT_MAX;
                                    $lowest_price_variation_id = null;

                                    // Find the variation with lowest price
                                    foreach ($variations as $variation) {
                                        $variation_price = floatval($variation['display_price']);
                                        if ($variation_price < $lowest_price) {
                                            $lowest_price = $variation_price;
                                            $lowest_price_variation_id = $variation['variation_id'];
                                        }
                                    }

                                    // Debug output for selected variation
                                    echo '<!-- Selected Lowest Price Variation ID: ' . $lowest_price_variation_id . ' -->';

                                    foreach ($variations as $variation) {
                                        $variation_obj = wc_get_product($variation['variation_id']);
                                        $variation_name = implode(', ', $variation['attributes']);
                                        $regular_price = $variation['display_regular_price'];
                                        $sale_price = $variation['display_price'];
                                        
                                        // Check if this is the lowest price variation
                                        $is_lowest_price = ($variation['variation_id'] == $lowest_price_variation_id);
                                        
                                        $is_checked = $is_lowest_price ? ' checked="checked"' : '';
                                        
                                        echo '<div class="variation-item' . ($is_lowest_price ? ' selected' : '') . '">
                                            <label class="checkbox-container">
                                                <input type="radio" name="variation_id" value="' . esc_attr($variation['variation_id']) . '" 
                                                    class="variation-radio" data-is-default="' . ($is_lowest_price ? 'true' : 'false') . '"' . $is_checked . '>
                                                <span class="checkmark"></span>
                                                <span class="variation-name">' . esc_html($variation_name) . '</span>';
                                        
                                        if ($sale_price < $regular_price) {
                                            echo '<span class="variation-price"><del>' . wc_price($regular_price) . '</del> ' . wc_price($sale_price) . '</span>';
                                        } else {
                                            echo '<span class="variation-price">' . wc_price($regular_price) . '</span>';
                                        }
                                        
                                        echo '</label>
                                            </div>';
                                    }
                                    
                                    echo '</div>
                                    </div>';
                                }
                            }

                            // Get grouped products
                            $grouped_products = get_post_meta($product->get_id(), '_variable_grouped_products', true);
                            
                            if (!empty($grouped_products)) {
                                echo '<div class="variable-grouped-products">
                                    <h4>Would you like to add:</h4>
                                    <div class="grouped-products-checkboxes">';
                                
                                foreach ($grouped_products as $grouped_product_id) {
                                    $grouped_product = wc_get_product($grouped_product_id);
                                    if (!$grouped_product || !$grouped_product->is_purchasable()) {
                                        continue;
                                    }
                                    
                                    $regular_price = $grouped_product->get_regular_price();
                                    $sale_price = $grouped_product->get_sale_price();
                                    
                                    echo '<div class="grouped-product-item">
                                        <label class="checkbox-container">
                                            <input type="checkbox" name="add_grouped_product[' . esc_attr($grouped_product_id) . ']" value="1" class="grouped-product-checkbox">
                                            <span class="checkmark"></span>
                                            <span class="product-name">' . esc_html($grouped_product->get_name()) . '</span>';
                                    
                                    if ($sale_price) {
                                        echo '<span class="product-price"><del>' . wc_price($regular_price) . '</del> ' . wc_price($sale_price) . '</span>';
                                    } else {
                                        echo '<span class="product-price">' . wc_price($regular_price) . '</span>';
                                    }
                                    
                                    echo '</label>
                                        <div class="grouped-qty-wrapper" style="display: none;">
                                            <button type="button" class="minus-grouped-qty">-</button>
                                            <input type="number" name="grouped_quantity[' . esc_attr($grouped_product_id) . ']" value="1" min="1" max="99" class="grouped-qty">
                                            <button type="button" class="plus-grouped-qty">+</button>
                                        </div>
                                    </div>';
                                }
                                
                                echo '</div>
                                </div>';
                            }
                            
                            // Get the field label from product meta
                            $field_label = get_post_meta($product->get_id(), '_custom_user_field', true);

                            // Only show if label is set
                            if (!empty($field_label)) {
                                ?>
                                <div class="custom-user-input-field popup-custom-field">
                                    <label for="popup_user_custom_input"><?php echo esc_html($field_label); ?></label>
                                    <input type="text" id="popup_user_custom_input" name="user_custom_input" class="input-text">
                                </div>
                                <?php
                            }
                            
                            // Add quantity controls and Add to Cart button in a flex container
                            echo '<div class="add-to-cart-container">
                                <div class="quantity-controls">
                                    <div class="quantity-wrapper">
                                        <button type="button" class="minus-qty">-</button>
                                        <input type="number" class="qty-input" value="1" min="1" max="99">
                                        <button type="button" class="plus-qty">+</button>
                                    </div>
                                </div>
                                <button class="add-to-cart-popup" data-product-id="' . get_the_ID() . '">Add to Cart</button>
                            </div>
                        </div>
                    </div>
                 </div>
                
                
                ';
            }
            wp_reset_postdata();

            echo '</div>';
            $product_found = true;
        }
    }

    // If no products are found initially
    if (!$product_found) {
        echo '<div id="no-products-message" class="section-title">No products found for your search.</div>';
    }
    echo '</div>';

    echo '<div id="reviews-popup" class="reviews-popup-overlay">
        <div class="reviews-popup-content">
            <span class="close-popup">&times;</span>
            <h2>' . esc_html($store_info['store_name']) . ' Reviews</h2>
            <div class="reviews-container">';
            
            $args = array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'author' => $seller_id,
            );
            $products = new WP_Query($args);
            
            if ($products->have_posts()) {
                $product_ids = wp_list_pluck($products->posts, 'ID');
                $review_args = array(
                    'post_type' => 'product',
                    'post__in' => $product_ids,
                    'status' => 'approve',
                );
                $comments = get_comments($review_args);
                
                if ($comments) {
                    foreach ($comments as $comment) {
                        $rating = get_comment_meta($comment->comment_ID, 'rating', true);
                        echo '<div class="review-item">
                            <div class="review-header">
                                <span class="reviewer-name">' . esc_html($comment->comment_author) . '</span>
                                <div class="review-rating">';
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating) {
                                        echo '<i class="fas fa-star"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                        echo '</div>
                            </div>
                            <div class="review-content">' . esc_html($comment->comment_content) . '</div>
                            <div class="review-date">' . date('F j, Y', strtotime($comment->comment_date)) . '</div>
                        </div>';
                    }
                } else {
                    echo '<p>No reviews yet.</p>';
                }
            } else {
                echo '<p>No products found for this vendor.</p>';
            }
            
    echo '</div>
        </div>
    </div>';

    // Switch from PHP to HTML
    ?>
          <aside class="custom-cart">
        <div class="cart-container">
            <div class="cart-header">
                <div class="delivery-pickup">
                    <button class="delivery active">Delivery <p>Standard (15-30 min)</p></button>
                    <button class="pickup">Pick-up</button>
                </div>
                <h2>Your Items</h2>
            </div>
            <div class="cart-items">
                <ul id="cart-item-list">
                    <?php
                    if (WC()->cart) {
                        $deal_counter = 1;
                        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                            $_product = $cart_item['data'];
                            if ($_product) {
                                $quantity = $cart_item['quantity'];
                                echo '<li class="cart-item" data-cart-item-key="' . esc_attr($cart_item_key) . '">
                                    <div class="item-details">
                                        <div class="item-left">
                                            <div class="item-image">
                                                ' . $_product->get_image('thumbnail') . '
                                            </div>
                                            <div class="item-info">
                                                <div class="item-name">Deal ' . $deal_counter . '</div>
                                                
                                            </div>
                                        </div>
                                        <div class="item-controls">
                                            <div class="price-quantity">
                                                <div class="item-price">' . wc_price($_product->get_price() * $quantity) . '</div>
                                                <div class="item-quantity">
                                                    <button class="' . ($quantity == 1 ? 'remove-item' : 'decrement-item') . '" data-product-id="' . esc_attr($_product->get_id()) . '">
                                                        ' . ($quantity == 1 ? '<i class="fas fa-trash-alt"></i>' : '-') . '
                                                    </button>
                                                    <span>' . $quantity . '</span>
                                                    <button class="increment-item" data-product-id="' . esc_attr($_product->get_id()) . '">+</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>';
                                $deal_counter++;
                            }
                        }
                    }
                    ?>
                </ul>
            </div>

            <!-- Popular with your order section -->
            <div class="popular-with-order">
                <h3>Popular with your order</h3>
				<p class="popular-with-des">
					Based on what customers bought together
				</p>
                <div class="popular-items">
                    <?php
                    $upload_dir = wp_upload_dir();
                    $image_url = $upload_dir['baseurl'] . '/2025/03/Plus-circle.png';

                    $args = array(
                        'post_type' => 'product',
                        'posts_per_page' => -1,
                        'author' => $seller_id,
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'product_tag',
                                'field'    => 'name',
                                'terms'    => 'Popular'
                            )
                        )
                    );
                    $popular_products = new WP_Query($args);

                    if ($popular_products->have_posts()) {
                        while ($popular_products->have_posts()) {
                            $popular_products->the_post();
                            $product = wc_get_product(get_the_ID());
                            
                            // Handle price display for variable products
                            if ($product->is_type('variable')) {
                                $min_price = $product->get_variation_regular_price('min');
                                $max_price = $product->get_variation_regular_price('max');
                                $min_sale_price = $product->get_variation_sale_price('min');
                                $max_sale_price = $product->get_variation_sale_price('max');
                                
                                if ($min_sale_price < $min_price) {
                                    $price_html = '<span class="popular-item-price">' . wc_price($min_sale_price) . ' <del>' . wc_price($min_price) . '</del></span>';
                                } else {
                                    $price_html = '<span class="popular-item-price">' . wc_price($min_price) . '</span>';
                                }
                            } else {
                                // Regular product price handling
                                $regular_price = $product->get_regular_price();
                                $sale_price = $product->get_sale_price();
                                $price_html = $sale_price ? 
                                    '<span class="popular-item-price">' . wc_price($sale_price) . ' <del>' . wc_price($regular_price) . '</del></span>' : 
                                    '<span class="popular-item-price">' . wc_price($regular_price) . '</span>';
                            }

                            echo '<div class="popular-item">
                                <div class="popular-item-box">
                                    <img src="' . get_the_post_thumbnail_url(get_the_ID(), 'thumbnail') . '" alt="' . esc_attr(get_the_title()) . '">
                                    <button class="add-popular" data-product-id="' . get_the_ID() . '">
                                        <img src="' . esc_url($image_url) . '" alt="Add to cart" class="plus-icon"/>
                                    </button>
                                </div>
                                <div class="popular-item-details">
                                    <span class="popular-item-label">' . get_the_title() . '</span>
                                    ' . $price_html . '
                                </div>
                            </div>';
                        }
                    }
                    wp_reset_postdata();
                    ?>
                </div>
            </div>

            <div class="cart-summary">
                <div class="summary-item">
                    <span>Subtotal</span>
                    <span id="cart-subtotal"><?php echo WC()->cart ? wc_price(WC()->cart->get_subtotal()) : wc_price(0); ?></span>
                </div>
                <div class="summary-item">
                    <span>Standard delivery</span>
                    <span>Free</span>
                </div>
                <div class="summary-item">
                    <span>Service</span>
                    <span>Rs. XYZ</span>
                </div>
              
                <hr>
                <div class="summary-item total">
                    <span>Total (incl. VAT)</span>
                    <span id="cart-total"><?php echo WC()->cart ? wc_price(WC()->cart->get_total()) : wc_price(0); ?></span>
                </div>
                <button id="checkout-button">Review Payment and Address</button>
            </div>
        </div>
    </aside>

    <style>
		p.popular-with-des {
    font-size: 14px;
    font-family: poppins;
    color: #005959;
}
  .custom-cart {
    position: fixed;
    right: 0;
    width: 350px;
    height: 600px;
    background: white;
    box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    overflow-y: scroll;
    border: 1.39px solid #828282;
    border-radius: 20px;
}

    .cart-container {
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .cart-header {
        padding: 20px;
        border-bottom: 1px solid #eee;
    }

    .delivery-pickup {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
        justify-content: space-between;
      
    }
		.delivery-pickup p {
    margin: 0;
}

   .delivery-pickup button {
    /* padding: 8px 24px; */
    border: none;
    border-radius: 6px;
    background: transparent;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
    font-weight: 500;
    color: #666;
    width: 100%;
    border: solid 2px #005959;
}

  .delivery-pickup button.active {
    background: #F8FFE3;
    color: #005959;
    /* box-shadow: 0 2px 4px rgba(0, 89, 89, 0.2); */
    border: solid 2px;
}

    .delivery-pickup button:not(.active):hover {
        background: rgba(0, 89, 89, 0.1);
    }

    .cart-items {
        flex: 1;
      
    }

    .cart-item {
        padding: 15px;
        border-bottom: 1px solid #eee;
    }

    .item-details {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .item-left {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
		.cart-header h2 {
    font-family: 'Poppins';
    margin: 0;
    font-size: 24px;
    font-weight: bold;
    text-align: left;
    color: #005959;
}

    .item-image {
        width: 50px;
        height: 50px;
        flex-shrink: 0;
    }

    .item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 4px;
    }

    .item-info {
        padding-top: 4px;
    }

    .item-name {
        font-weight: 500;
        color: #333;
        margin-bottom: 2px;
        font-size: 14px;
    }

    .item-desc {
        color: #666;
        font-size: 13px;
    }

    .item-controls {
        display: flex;
        align-items: center;
        margin-left: auto;
        padding-top: 4px;
    }

  .price-quantity {
    display: flex
;
    align-items: center;
    gap: 110px;
    justify-content: space-between;
}

    .item-price {
        font-weight: 500;
        color: #333;
        font-size: 14px;
    }

    .item-quantity {
        display: flex;
        align-items: center;
        gap: 8px;
        background: transparent;
        padding: 0;
    }

    .item-quantity button {
        width: 24px;
        height: 24px;
        border: none;
        background: transparent;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #333;
        padding: 0;
    }

    .item-quantity span {
        min-width: 16px;
        text-align: center;
        font-weight: 400;
        font-size: 14px;
    }

    .remove-item {
        color: #666 !important;
    }

    .remove-item:hover {
        color: #dc2626 !important;
    }

    /* Popular with your order section */
    .popular-with-order {
        padding: 20px;
        border-top: 1px solid #eee;
        border-bottom: 1px solid #eee;
        background: #fff;
    }

  .popular-with-order h3 {
    margin-bottom: 15px;
    color: #005959;
    font-weight: 700;
    font-size: 20px;
    font-family: poppins;
}
		
		.cart-items ul {
    list-style: none;
    padding: 0;
    overflow-y: scroll;
    height: 200px;
}

    .popular-items {
        display: flex;
        gap: 15px;
        overflow-x: auto;
        padding: 4px;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }

    .popular-item {
        min-width: 70px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .popular-item-box {
        aspect-ratio: 1;
        background: #fff;
        border: 1px solid #eee;
        border-radius: 12px;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        overflow: hidden;
    }

    .popular-item-box img {
    width: 71.12108612060547px;
    height: 71.3946304321289px;
    object-fit: cover;
    display: block;
    border-radius: 15px;
}
   .add-popular {
    border: none;
    cursor: pointer;
    display: flex
;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s ease;
    position: absolute;
    right: -11px;
    z-index: 2;
    top: 35px;
}

    .add-popular:hover {
    transform: scale(1.1);
    background: transparent;
}

    .popular-item-details {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
		button.add-popular img {
    width: 100%;
    height: 100%;
}

    .popular-item-label {
        font-size: 14px;
        color: #333;
        text-align: left;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        white-space: normal;
        min-height: 36px;
    }

    .popular-item-price {
        font-size: 14px;
        font-weight: 600;
        color: #166534;
        text-align: left;
    }

    /* Custom scrollbar for popular items */
    .popular-items::-webkit-scrollbar {
        height: 4px;
    }

    .popular-items::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 2px;
    }

    .popular-items::-webkit-scrollbar-thumb {
        background: #ddd;
        border-radius: 2px;
    }

    .popular-items::-webkit-scrollbar-thumb:hover {
        background: #ccc;
    }

    .cart-summary {
        padding: 20px;
        background: #f9f9f9;
        border-top: 1px solid #eee;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        color: #666;
    }

    .summary-item.total {
        font-weight: bold;
        font-size: 1.1em;
        margin-top: 10px;
        color: #333;
    }

    hr {
        border: none;
        border-top: 1px solid #ddd;
        margin: 15px 0;
    }

   #checkout-button {
    width: 100%;
    padding: 15px;
    background: #005959;
    color: white;
    border: none;
    border-radius: 8px;
    margin-top: 15px;
    cursor: pointer;
    font-weight: bold;
    transition: background-color 0.3s ease;
}

    #checkout-button:hover {
        background: #125429;
    }

    /* Scrollbar styling */
    .cart-items::-webkit-scrollbar,
    .popular-items::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    .cart-items::-webkit-scrollbar-track,
    .popular-items::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    .cart-items::-webkit-scrollbar-thumb,
    .popular-items::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 3px;
    }

    .cart-items::-webkit-scrollbar-thumb:hover,
    .popular-items::-webkit-scrollbar-thumb:hover {
        background: #666;
    }
    </style> 

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var checkoutUrl = '<?php echo wc_get_checkout_url(); ?>';

        // Popup open/close functionality
        $(document).on('click', '.quick-view-button', function(e) {
            e.preventDefault();
            var productId = $(this).data('product-id');
            var $popup = $('#popup-' + productId);
            $popup.fadeIn(300);
            
            // Find the default variation and trigger change
            setTimeout(function() {
                var $defaultVariation = $popup.find('.variation-radio[data-is-default="true"]');
                if ($defaultVariation.length) {
                    $defaultVariation.prop('checked', true).trigger('change');
                    $defaultVariation.closest('.variation-item').addClass('selected');
                } else {
                    // Only if no default is set, fall back to the first variation
                    var $firstVariation = $popup.find('.variation-radio').first();
                    $firstVariation.prop('checked', true).trigger('change');
                    $firstVariation.closest('.variation-item').addClass('selected');
                }
            }, 100);
        });

        $(document).on('click', '.close-popup', function() {
            $(this).closest('.product-quick-view-popup').fadeOut(300);
        });

        $(window).on('click', function(e) {
            if($(e.target).hasClass('product-quick-view-popup')) {
                $('.product-quick-view-popup').fadeOut(300);
            }
        });

        // Update the variation selection handler
        $(document).on('change', '.variation-radio', function() {
            var $this = $(this);
            var $popup = $this.closest('.product-quick-view-popup');
            var $addToCartButton = $popup.find('.add-to-cart-popup');
            var variationId = $this.val();
            
            // Remove selected class from all items and add to the selected one
            $popup.find('.variation-item').removeClass('selected');
            $this.closest('.variation-item').addClass('selected');
            
            // Update add to cart button data
            $addToCartButton.attr('data-variation-id', variationId);
            
            // Update main product price display
            var selectedPrice = $this.closest('.variation-item').find('.variation-price').html();
            $popup.find('.popup-product-details .price').html(selectedPrice);
            
            // Enable the add to cart button
            $addToCartButton.prop('disabled', false).removeClass('disabled');
        });

        // Combined add to cart functionality for both plus button and popup
        $(document).on('click', '.add-button, .add-to-cart-popup', function(e) {
            e.preventDefault();
            var $button = $(this);
            var productId = $button.data('product-id');
            var isPopup = $button.hasClass('add-to-cart-popup');
            var $popup = isPopup ? $button.closest('.product-quick-view-popup') : null;
            var variationId = null;
            
            // Add loading state
            $button.addClass('loading');
            
            // Get quantity from popup
            var quantity = 1;
            if (isPopup && $popup) {
                quantity = parseInt($popup.find('.qty-input').val());
                if (isNaN(quantity) || quantity < 1) {
                    quantity = 1;
                }
            }
            
            // Get grouped products data
            var groupedProducts = [];
            if (isPopup && $popup) {
                $popup.find('.grouped-product-checkbox:checked').each(function() {
                    var $checkbox = $(this);
                    var groupedId = $checkbox.attr('name').match(/\[(\d+)\]/)[1];
                    var groupedQty = parseInt($checkbox.closest('.grouped-product-item').find('.grouped-qty').val()) || 1;
                    groupedProducts.push({
                        id: groupedId,
                        quantity: groupedQty
                    });
                });
            }
            
            // If it's the plus button (not popup) and it's a variable product
            if (!isPopup) {
                var $productPopup = $('#popup-' + productId);
                if ($productPopup.find('.variation-radio').length > 0) {
                    variationId = $productPopup.find('.variation-radio:checked').val();
                }
            } else if (isPopup && $popup) {
                variationId = $popup.find('.variation-radio:checked').val();
            }
            
            // Add to cart AJAX call
            var data = {
                action: 'woocommerce_ajax_add_to_cart',
                product_id: productId,
                quantity: quantity,
                grouped_products: groupedProducts
            };

            // Add variation ID if present
            if (variationId) {
                data.variation_id = variationId;
            }

            // Add custom field value if present
            var $customField = isPopup && $popup ? $popup.find('#popup_user_custom_input') : null;
            if ($customField && $customField.length) {
                data.custom_field = $customField.val();
            }

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.error) {
                        handleAddToCartError($button, response.message);
                        return;
                    }
                    handleAddToCartSuccess($button, $popup);
                    
                    // Update cart fragments if they exist
                    if (response.fragments) {
                        $.each(response.fragments, function(key, value) {
                            $(key).replaceWith(value);
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Cart error:", error);
                    handleAddToCartError($button, 'Server error occurred');
                }
            });
        });

        function handleAddToCartSuccess($button, $popup) {
            $button.removeClass('loading').addClass('added');
            setTimeout(function() {
                $button.removeClass('added');
                if ($popup) {
                    $popup.fadeOut(300);
                }
            }, 1000);
            
            updateCartContents();
            
            $('<div class="cart-notification">Products added to cart</div>')
                .appendTo('body')
                .fadeIn(300)
                .delay(1500)
                .fadeOut(300, function() {
                    $(this).remove();
                });
        }

        function handleAddToCartError($button, message) {
            $button.removeClass('loading');
            alert('Error adding products to cart: ' + message);
        }

        // Live search functionality
        $('#live-search-input').on('input', function() {
            var searchQuery = $(this).val().toLowerCase();
            
            // Loop through all products and hide/show based on search query
            var productFound = false;
            $('.section-title').each(function() {
                var section = $(this).next('.product-grid');
                var sectionProductFound = false;
                section.find('.product-card').each(function() {
                    var productName = $(this).find('.product-name').text().toLowerCase();
                    if (productName.indexOf(searchQuery) > -1) {
                        $(this).show();
                        productFound = true;
                        sectionProductFound = true;
                    } else {
                        $(this).hide();
                    }
                });

                // If no product matches in this section, hide the section
                if (!sectionProductFound) {
                    $(this).hide();
                    section.hide();
                } else {
                    $(this).show();
                    section.show();
                }
            });

            // If no product matches in all sections, show the global "No products found" message
            if (!productFound) {
                if ($('#no-products-message').length === 0) {
                    $('#products-container').append('<div id="no-products-message" class="section-title" style="color: red;">No products found for your search.</div>');
                } else {
                    $('#no-products-message').show();
                }
            } else {
                $('#no-products-message').hide();
            }
        });

        // Smooth scroll for tags
        $('.category-tab').on('click', function() {
            $('.category-tab').removeClass('active');
            $(this).addClass('active');
            const tagId = $(this).data('tag');
            const offset = $('#tag-' + tagId).offset().top - 20;
            $('html, body').animate({scrollTop: offset}, 800);
        });

        // Review popup functionality
        $('.show-reviews-popup').on('click', function(e) {
            e.preventDefault();
            $('#reviews-popup').fadeIn(300);
        });

        $('.close-popup').on('click', function() {
            $('#reviews-popup').fadeOut(300);
        });

        $(window).on('click', function(e) {
            if ($(e.target).hasClass('reviews-popup-overlay')) {
                $('#reviews-popup').fadeOut(300);
            }
        });

        // Function to update cart items, totals, etc. without page reload
        function updateCartContents() {
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'update_cart_fragments'
                },
                success: function(response) {
                    // Update cart items list
                    if (response.cart_items) {
                        $('#cart-item-list').html(response.cart_items);
                    }
                    
                    // Update totals
                    if (response.cart_subtotal) {
                        $('#cart-subtotal').html(response.cart_subtotal);
                    }
                    
                    if (response.cart_total) {
                        $('#cart-total').html(response.cart_total);
                    }
                    
                    // Update WooCommerce mini-cart fragments if they exist
                    if (response.fragments) {
                        $.each(response.fragments, function(key, value) {
                            $(key).replaceWith(value);
                        });
                    }
                }
            });
        }

        // Handle incrementing item quantity
        $(document).on('click', '.increment-item', function(e) {
            e.preventDefault();
            var $this = $(this);
            var cartItemKey = $this.closest('.cart-item').data('cart-item-key');
            var currentQty = parseInt($this.siblings('span').text());
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woocommerce_update_cart_item',
                    cart_item_key: cartItemKey,
                    quantity: currentQty + 1
                },
                success: function(response) {
                    // Update quantity display immediately for responsive feel
                    $this.siblings('span').text(currentQty + 1);
                    
                    // If quantity is now 2, replace trash with decrement
                    if (currentQty === 1) {
                        var $removeBtn = $this.siblings('.remove-item');
                        $removeBtn.removeClass('remove-item').addClass('decrement-item');
                        $removeBtn.html('<svg width="24" height="24" viewBox="0 0 24 24"><path d="M18.25 11C18.6642 11 19 11.3358 19 11.75C19 12.1297 18.7178 12.4435 18.3518 12.4932L18.25 12.5H5.75C5.33579 12.5 5 12.1642 5 11.75C5 11.3703 5.28215 11.0565 5.64823 11.0068L5.75 11H18.25Z"></path></svg>');
                    }
                    
                    // Update cart totals
                    updateCartContents();
                }
            });
        });

        // Handle decrementing item quantity
        $(document).on('click', '.decrement-item', function(e) {
            e.preventDefault();
            var $this = $(this);
            var cartItemKey = $this.closest('.cart-item').data('cart-item-key');
            var currentQty = parseInt($this.siblings('span').text());
            
            if (currentQty > 1) {
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'woocommerce_update_cart_item',
                        cart_item_key: cartItemKey,
                        quantity: currentQty - 1
                    },
                    success: function(response) {
                        // Update quantity display immediately
                        var newQty = currentQty - 1;
                        $this.siblings('span').text(newQty);
                        
                        // If quantity is now 1, replace decrement with trash
                        if (newQty === 1) {
                            $this.removeClass('decrement-item').addClass('remove-item');
                            $this.html('<i class="fas fa-trash-alt"></i>');
                        }
                        
                        // Update cart totals
                        updateCartContents();
                    }
                });
            }
        });

        // Handle removing item from cart
        $(document).on('click', '.remove-item', function(e) {
            e.preventDefault();
            var $this = $(this);
            var cartItemKey = $this.closest('.cart-item').data('cart-item-key');
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woocommerce_remove_cart_item',
                    cart_item_key: cartItemKey
                },
                success: function(response) {
                    // Remove item from display with animation
                    $this.closest('.cart-item').slideUp(300, function() {
                        $(this).remove();
                    });
                    
                    // Update cart totals
                    updateCartContents();
                }
            });
        });

        // Checkout button
        $('#checkout-button').on('click', function() {
            window.location.href = checkoutUrl;
        });

        // Initial cart update when page loads
        updateCartContents();

        // Quantity controls for main product
        $(document).on("click", ".minus-qty", function() {
            var $input = $(this).siblings(".qty-input");
            var value = parseInt($input.val());
            if (value > 1) {
                $input.val(value - 1).trigger("change");
            }
        });

        $(document).on("click", ".plus-qty", function() {
            var $input = $(this).siblings(".qty-input");
            var value = parseInt($input.val());
            if (value < 99) {
                $input.val(value + 1).trigger("change");
            }
        });

        // Quantity controls for grouped products
        $(document).on("click", ".minus-grouped-qty", function() {
            var $input = $(this).siblings(".grouped-qty");
            var value = parseInt($input.val());
            if (value > 1) {
                $input.val(value - 1).trigger("change");
            }
        });

        $(document).on("click", ".plus-grouped-qty", function() {
            var $input = $(this).siblings(".grouped-qty");
            var value = parseInt($input.val());
            if (value < 99) {
                $input.val(value + 1).trigger("change");
            }
        });

        // Show/hide grouped product quantity controls
        $(document).on("change", ".grouped-product-checkbox", function() {
            var $qtyWrapper = $(this).closest(".grouped-product-item").find(".grouped-qty-wrapper");
            $qtyWrapper.toggle(this.checked);
        });

        // Delivery/Pickup toggle functionality
        $('.delivery-pickup button').on('click', function() {
            $('.delivery-pickup button').removeClass('active');
            $(this).addClass('active');
            
            // Store the selected option (delivery or pickup)
            var selectedOption = $(this).hasClass('delivery') ? 'delivery' : 'pickup';
            localStorage.setItem('deliveryOption', selectedOption);
        });

        // Popular products add to cart functionality
        $(document).on('click', '.add-popular', function(e) {
            e.preventDefault();
            var $button = $(this);
            var productId = $button.data('product-id');
            
            // Add loading state
            $button.addClass('loading');
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woocommerce_ajax_add_to_cart',
                    product_id: productId,
                    quantity: 1
                },
                success: function(response) {
                    if (response.error) {
                        alert(response.message);
                    } else {
                        // Show success animation
                        $button.removeClass('loading').addClass('added');
                        setTimeout(function() {
                            $button.removeClass('added');
                        }, 1000);
                        
                        // Update cart contents
                        updateCartContents();
                        
                        // Show notification
                        $('<div class="cart-notification">Product added to cart</div>')
                            .appendTo('body')
                            .fadeIn(300)
                            .delay(1500)
                            .fadeOut(300, function() {
                                $(this).remove();
                            });
                    }
                },
                error: function() {
                    alert('Error adding product to cart');
                    $button.removeClass('loading');
                }
            });
        });
    });
    </script>

    <style>



    /* Button states */
    .add-button {
        position: relative;
        overflow: hidden;
    }
    
    .add-button.loading:after {
        content: "";
        position: absolute;
        top: 50%;
        left: 50%;
        width: 20px;
        height: 20px;
        margin-top: -10px;
        margin-left: -10px;
        border: 2px solid white;
        border-top-color: transparent;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    .add-button.added:after {
        content: "✓";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: white;
        font-size: 16px;
    }
    
    .cart-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #166534;
        color: white;
        padding: 10px 15px;
        border-radius: 4px;
        z-index: 9999;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        display: none;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .product-quick-view-popup {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 9999;
        display: none;
    }

    .product-quick-view-popup-content {
    position: relative;
    background: white;
    max-width: 800px;
    width: 90%;
    margin: 40px auto;
    padding: 20px;
    border-radius: 8px;
    display: flex;
    gap: 20px;
    flex-direction: column;
    height: 500px;
    overflow-y: scroll;
}

.variable-grouped-products {
    margin: 20px 0;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #f9f9f9;
}
.variable-grouped-products h4 {
    margin-top: 0;
    margin-bottom: 15px;
    font-weight: bold;
    font-family: 'Poppins';
    color: #005959;
    font-size: 20px;
}
.grouped-product-item {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}
.grouped-product-item:last-child {
    border-bottom: none;
}
.checkbox-container {
    display: flex;
    align-items: center;
    position: relative;
    padding-left: 35px;
    cursor: pointer;
    user-select: none;
}
.checkbox-container input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
}
.checkmark {
    position: absolute;
    left: 0;
    height: 20px;
    width: 20px;
    background-color: #fff;
    border: 2px solid #ddd;
    border-radius: 3px;
}
.checkbox-container:hover input ~ .checkmark {
    border-color: #ccc;
}
.checkbox-container input:checked ~ .checkmark {
    background-color: #ffffff;
    border-color: #005959;
}
.checkmark:after {
    content: "";
    position: absolute;
    display: none;
}
.checkbox-container input:checked ~ .checkmark:after {
    display: block;
}
.checkbox-container .checkmark:after {
    left: 6px;
    top: 2px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0;
    transform: rotate(45deg);
}
.product-name {
    flex: 1;
}
.product-price {
    margin-left: 15px;
    font-weight: bold;
}
.product-price del {
    color: #999;
    margin-right: 5px;
}
    
    .variation-checkboxes {
        margin: 15px 0;
    }
    
    .variation-item {
        margin-bottom: 10px;
        padding: 8px;
        border: 1px solid #eee;
        border-radius: 4px;
        transition: background-color 0.2s;
    }
    
    .variation-item:hover {
        background-color: #f9f9f9;
    }
    
    .variation-item label {
        display: flex;
        align-items: center;
        width: 100%;
        cursor: pointer;
    }
    
    .variation-name {
        flex: 1;
        margin-right: 15px;
    }
    
    .variation-price {
        font-weight: bold;
        white-space: nowrap;
    }
    
    .variation-price del {
        color: #999;
        margin-right: 5px;
    }
    
    /* Radio button styles */
    .variation-radio {
        position: absolute;
        opacity: 0;
    }
    
    .variation-radio + .checkmark {
        position: relative;
        display: inline-block;
        width: 18px;
        height: 18px;
        margin-right: 10px;
        border: 2px solid #ddd;
        border-radius: 50%;
        transition: all 0.2s;
    }
    
    .variation-radio:checked + .checkmark {
        border-color: #2196F3;
    }
    
    .variation-radio:checked + .checkmark:after {
    content: '';
    position: absolute;
    top: 53%;
    left: 54%;
    transform: translate(-49%, -57%);
    width: 10px;
    height: 10px;
    background: #005959;
    border-radius: 73%;
}

.grouped-qty-wrapper {
    margin-top: 20px;
    border: none;
}

.grouped-qty-wrapper button {
    border: none;
    background: transparent !important;
    color: #005959 !important;
}
.grouped-qty-wrapper input.grouped-qty:focus-visible {
    outline: none;
}

.grouped-qty-wrapper input.grouped-qty {
    border: none;
    background: transparent;
    padding: 0;
    border-bottom: solid 1px #005959;
    border-radius: 0;
}
.popup-product-image img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.grouped-qty-wrapper input.grouped-qty {
    border: none;
    background: transparent;
}
    
    .variation-radio:focus + .checkmark {
        box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.2);
    }
    
    /* Selected variation highlight */
    .variation-radio:checked ~ .variation-name {
    color: #000000;
}
    .variation-item.selected {
    background-color: #00595959;
    border-color: #005959;
}

    .quantity-controls {
        margin: 15px 0;
    }
    
    .quantity-wrapper, .grouped-qty-wrapper {
        display: flex;
        align-items: center;
        max-width: 120px;
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
    }
    .minus-qty, .plus-qty, .minus-grouped-qty, .plus-grouped-qty {
        padding: 8px 12px;
        background: #f5f5f5;
        border: none;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .minus-qty:hover, .plus-qty:hover, .minus-grouped-qty:hover, .plus-grouped-qty:hover {
        background: #e0e0e0;
    }
    .qty-input, .grouped-qty {
        width: 40px;
        text-align: center;
        border: none;
        border-left: 1px solid #ddd;
        border-right: 1px solid #ddd;
        padding: 8px 0;
    }
    .qty-input::-webkit-inner-spin-button, 
    .qty-input::-webkit-outer-spin-button,
    .grouped-qty::-webkit-inner-spin-button,
    .grouped-qty::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .grouped-product-checkbox:checked ~ .grouped-qty-wrapper {
        display: flex !important;
        margin-top: 10px;
    }
    </style>

    <style>
    .popup-custom-field {
        margin: 15px 0;
    }
    
    .popup-custom-field label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
    
    .popup-custom-field input[type="text"] {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .popup-custom-field input[type="text"]:focus {
        border-color: #2196F3;
        outline: none;
        box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.2);
    }
    </style>

    <style>
    .add-to-cart-container {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-top: 20px;
    }

    .add-to-cart-container .quantity-controls {
        margin: 0;
    }

    .add-to-cart-container .quantity-wrapper {
        height: 100%;
    }

    .add-to-cart-container .add-to-cart-popup {
        flex: 1;
    }

    .quantity-wrapper {
        display: flex;
        align-items: center;
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
        height: 42px;
    }

    .minus-qty, .plus-qty {
        padding: 8px 12px;
        background: #f5f5f5;
        border: none;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .qty-input {
        width: 40px;
        text-align: center;
        border: none;
        border-left: 1px solid #ddd;
        border-right: 1px solid #ddd;
        padding: 8px 0;
    }

    .add-to-cart-popup {
        padding: 12px 24px;
        background: #005959;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        transition: background-color 0.3s;
    }

    .add-to-cart-popup:hover {
        background: #004444;
    }
    </style>
<?php
}

// AJAX handlers for cart functionality
add_action('wp_ajax_woocommerce_ajax_add_to_cart', 'custom_ajax_add_to_cart');
add_action('wp_ajax_nopriv_woocommerce_ajax_add_to_cart', 'custom_ajax_add_to_cart');

function custom_ajax_add_to_cart() {
    $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint($_POST['product_id']));
    $quantity = empty($_POST['quantity']) ? 1 : wc_stock_amount(wp_unslash($_POST['quantity']));
    $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
    $grouped_products = isset($_POST['grouped_products']) ? $_POST['grouped_products'] : array();
    
    // Get the product
    $product = wc_get_product($product_id);
    
    if (!$product) {
        wp_send_json(array(
            'error' => true,
            'message' => __('Product not found', 'woocommerce')
        ));
        wp_die();
    }
    
    $added_to_cart = false;
    
    // Handle variable product
    if ($product->is_type('variable')) {
        if (!$variation_id) {
            // Get the default variation or lowest price variation
            $available_variations = $product->get_available_variations();
            if (!empty($available_variations)) {
                $lowest_price = PHP_FLOAT_MAX;
                foreach ($available_variations as $variation) {
                    $variation_price = floatval($variation['display_price']);
                    if ($variation_price < $lowest_price) {
                        $lowest_price = $variation_price;
                        $variation_id = $variation['variation_id'];
                        $variation_data = $variation['attributes'];
                    }
                }
            }
        }
        
        if ($variation_id) {
            // Get variation attributes
            $variation = array();
            $variation_data = wc_get_product_variation_attributes($variation_id);
            foreach ($variation_data as $attr_name => $attr_value) {
                $variation['attribute_' . $attr_name] = $attr_value;
            }
            
            // Add main variable product
            $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variation);
            if ($passed_validation) {
                $added_to_cart = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
            }
        }
    } else {
        // Add simple product
        $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity);
        if ($passed_validation) {
            $added_to_cart = WC()->cart->add_to_cart($product_id, $quantity);
        }
    }
    
    // Handle grouped products
    if (!empty($grouped_products)) {
        foreach ($grouped_products as $grouped_item) {
            $grouped_id = absint($grouped_item['id']);
            $grouped_qty = absint($grouped_item['quantity']);
            
            if ($grouped_id > 0 && $grouped_qty > 0) {
                $grouped_product = wc_get_product($grouped_id);
                if ($grouped_product && $grouped_product->is_purchasable()) {
                    WC()->cart->add_to_cart($grouped_id, $grouped_qty);
                }
            }
        }
    }
    
    if ($added_to_cart) {
        do_action('woocommerce_ajax_added_to_cart', $product_id);
        
        // Get updated cart fragments
        ob_start();
        woocommerce_mini_cart();
        $mini_cart = ob_get_clean();
        
        wp_send_json(array(
            'success' => true,
            'cart_hash' => WC()->cart->get_cart_hash(),
            'cart_quantity' => WC()->cart->get_cart_contents_count(),
            'fragments' => array(
                'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>'
            )
        ));
    } else {
        wp_send_json(array(
            'error' => true,
            'message' => __('Error adding product to cart', 'woocommerce')
        ));
    }
    
    wp_die();
}

add_action('wp_ajax_update_cart_fragments', 'get_cart_fragments');
add_action('wp_ajax_nopriv_update_cart_fragments', 'get_cart_fragments');
function get_cart_fragments() {
    $fragments = array();
    
    ob_start();
    woocommerce_mini_cart();
    $mini_cart = ob_get_clean();
    
    $fragments['div.widget_shopping_cart_content'] = '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>';
    
    // Add cart count and totals
    $fragments['cart_count'] = WC()->cart->get_cart_contents_count();
    $fragments['cart_subtotal'] = WC()->cart->get_cart_subtotal();
    $fragments['cart_total'] = WC()->cart->get_total();
    
    // Add cart items HTML
    ob_start();
    if (!WC()->cart->is_empty()) {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $_product = $cart_item['data'];
            if ($_product) {
                $quantity = $cart_item['quantity'];
                echo '<li class="cart-item" data-cart-item-key="' . esc_attr($cart_item_key) . '">';
                echo '<div class="item-details">';
                echo '<div class="item-left">';
                echo '<div class="item-image">';
                echo $_product->get_image('thumbnail');
                echo '</div>';
                echo '<div class="item-info">';
                echo '<div class="item-name">' . $_product->get_name() . '</div>';
//                 echo '<div class="item-desc">' . $_product->get_name() . '</div>';
                echo '</div>';
                echo '</div>';
                echo '<div class="item-controls">';
                echo '<div class="price-quantity">';
                echo '<div class="item-price">' . wc_price($_product->get_price() * $quantity) . '</div>';
                echo '<div class="item-quantity">';
                echo '<button class="' . ($quantity == 1 ? 'remove-item' : 'decrement-item') . '" data-product-id="' . esc_attr($_product->get_id()) . '">';
                echo ($quantity == 1 ? '<i class="fas fa-trash-alt"></i>' : '-');
                echo '</button>';
                echo '<span>' . $quantity . '</span>';
                echo '<button class="increment-item" data-product-id="' . esc_attr($_product->get_id()) . '">+</button>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '</li>';
            }
        }
    } else {
        echo '<li class="empty-cart-message">Your cart is empty</li>';
    }
    $fragments['cart_items'] = ob_get_clean();
    
    wp_send_json($fragments);
    wp_die();
}

add_action('wp_ajax_woocommerce_update_cart_item', 'update_cart_item_quantity');
add_action('wp_ajax_nopriv_woocommerce_update_cart_item', 'update_cart_item_quantity');
function update_cart_item_quantity() {
    $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
    $quantity = intval($_POST['quantity']);
    
    if ($quantity > 0) {
        WC()->cart->set_quantity($cart_item_key, $quantity);
        wp_send_json(array(
            'success' => true,
            'cart_hash' => WC()->cart->get_cart_hash()
        ));
    } else {
        wp_send_json(array(
            'success' => false,
            'message' => 'Invalid quantity'
        ));
    }
    
    wp_die();
}

add_action('wp_ajax_woocommerce_remove_cart_item', 'remove_cart_item');
add_action('wp_ajax_nopriv_woocommerce_remove_cart_item', 'remove_cart_item');
function remove_cart_item() {
    $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
    $removed = WC()->cart->remove_cart_item($cart_item_key);
    
    wp_send_json(array(
        'success' => $removed,
        'cart_hash' => WC()->cart->get_cart_hash()
    ));
    
    wp_die();
}

// Process grouped products when added to cart
add_action('woocommerce_add_to_cart', 'process_popup_grouped_products', 99, 6);
function process_popup_grouped_products($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    // Check if we have grouped products in the POST data
    if (!isset($_POST['add_grouped_product']) || !is_array($_POST['add_grouped_product'])) {
        return;
    }
    
    // Prevent duplicate processing
    static $already_processed = false;
    if ($already_processed) {
        return;
    }
    $already_processed = true;
    
    // Process selected grouped products
    $added_count = 0;
    foreach ($_POST['add_grouped_product'] as $grouped_id => $selected) {
        if ($selected) {
            $qty = isset($_POST['grouped_quantity'][$grouped_id]) ? absint($_POST['grouped_quantity'][$grouped_id]) : 1;
            if ($qty > 0) {
                // Check if product already in cart
                $product_in_cart = false;
                foreach (WC()->cart->get_cart() as $cart_id => $cart_item) {
                    if ($cart_item['product_id'] == $grouped_id) {
                        // Update quantity
                        $new_qty = $cart_item['quantity'] + $qty;
                        WC()->cart->set_quantity($cart_id, $new_qty);
                        $product_in_cart = true;
                        $added_count++;
                        break;
                    }
                }
                
                // Add to cart if not already there
                if (!$product_in_cart) {
                    $added = WC()->cart->add_to_cart($grouped_id, $qty);
                    if ($added) {
                        $added_count++;
                    }
                }
            }
        }
    }
    
    // Show success message
    if ($added_count > 0) {
        wc_add_notice(sprintf(
            _n('Added %d additional product to your cart.', 'Added %d additional products to your cart.', $added_count, 'woocommerce'),
            $added_count
        ), 'success');
    }
}

add_action('wp_head', function() {
    ?>
    <style>
		button.add-popular:focus {
    background: transparent;
}
    .popup-custom-field {
        margin: 15px 0;
    }
    
    .popup-custom-field label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
li.empty-cart-message {
    text-align: center;
    font-size: 20px;
    color: #005959;
    font-weight: 700;
}    
    .popup-custom-field input[type="text"] {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .popup-custom-field input[type="text"]:focus {
        border-color: #2196F3;
        outline: none;
        box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.2);
    }

    .popup-product-image {
    text-align: center;
    width: 100%;
    height: 100%;
}
.popup-product-details h2 {
    font-size: 36px;
    font-family: 'Roboto';
    color: #005959;
}

.popup-product-details p.price {
    font-size: 16px;
    font-weight: 700;
    color: #005959;
    font-family: 'Poppins';
}
    </style>
    <?php
});
