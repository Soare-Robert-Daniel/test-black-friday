<?php
/*
Plugin Name: Test Black Friday
Description: Allows admin to override the date returned by the 'themeisle_sdk_current_date' filter for testing.
Version: 1.0
Author: Robert
*/

// Add admin menu page
add_action('admin_menu', function() {
    add_menu_page(
        'Test Black Friday',
        'Test Black Friday',
        'manage_options',
        'test-black-friday-date',
        'test_black_friday_date_admin_page',
        'dashicons-calendar-alt', // Optional: icon for the menu
        25 // Optional: position in the menu
    );
});

// Render admin page
function test_black_friday_date_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    // Handle form submission
    if (isset($_POST['test_black_friday_date_nonce']) && wp_verify_nonce($_POST['test_black_friday_date_nonce'], 'test_black_friday_date_save')) {
        $date = sanitize_text_field($_POST['test_black_friday_date'] ?? '');
        update_option('test_black_friday_date', $date);
        
        $swap_domain = sanitize_text_field($_POST['themeisle_sdk_blackfriday_swap_domain'] ?? '');
        update_option('themeisle_sdk_blackfriday_swap_domain', $swap_domain);
        
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }
    $saved_date = get_option('test_black_friday_date', '');
    $saved_swap_domain = get_option('themeisle_sdk_blackfriday_swap_domain', '');

    // Calculate Black Friday related dates for the current year
    $now = new DateTime();
    $current_year = $now->format('Y');

    $black_friday_day = new DateTime("last Friday of November $current_year");
    $sale_start = clone $black_friday_day;
    $sale_start->modify('monday this week');
    $sale_start->setTime(0, 0);

    $sale_end = clone $sale_start;
    $sale_end->modify('+7 days');
    $sale_end->setTime(23, 59, 59);

    $black_friday_date_str = $black_friday_day->format('Y-m-d');
    $sale_start_date_str = $sale_start->format('Y-m-d');
    $sale_end_date_str = $sale_end->format('Y-m-d');

    ?>
    <div class="wrap">
        <h1>Test Black Friday Settings</h1>
        <p style="background: #fff8e5; border-left: 4px solid #ffb900; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            For <?php echo esc_html($current_year); ?>, the Black Friday sale period is from 
            <strong><?php echo esc_html($sale_start->format('l, F jS')); ?></strong> (Sale Start) 
            to <strong><?php echo esc_html($sale_end->format('l, F jS')); ?></strong> (Sale End).<br>
            Black Friday is on <strong><?php echo esc_html($black_friday_day->format('l, F jS')); ?></strong>.
        </p>
        <form method="post">
            <?php wp_nonce_field('test_black_friday_date_save', 'test_black_friday_date_nonce'); ?>
            
            <!-- Date Override Section -->
            <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <h2 style="margin-top: 0; margin-bottom: 15px; font-size: 18px;">Display Testing</h2>
                <div style="margin-bottom: 10px;">
                    <label for="test_black_friday_date" style="display: block; font-weight: 500; margin-bottom: 5px;">Override Date:</label>
                    <input type="date" id="test_black_friday_date" name="test_black_friday_date" value="<?php echo esc_attr($saved_date); ?>" style="padding: 5px; width: 200px;">
                </div>
                <p class="description" style="margin: 5px 0 15px 0;">Leave blank to use the current date. Use this option to modify the SDK time to trigger the display of the Black Friday notices.</p>
                
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="button" onclick="document.getElementById('test_black_friday_date').value = '<?php echo esc_js($sale_start_date_str); ?>';">Set to Sale Start (<?php echo esc_html($sale_start->format('M j')); ?>)</button>
                    <button type="button" class="button" onclick="document.getElementById('test_black_friday_date').value = '<?php echo esc_js($black_friday_date_str); ?>';">Set to Black Friday (<?php echo esc_html($black_friday_day->format('M j')); ?>)</button>
                    <button type="button" class="button" onclick="document.getElementById('test_black_friday_date').value = '<?php echo esc_js($sale_end_date_str); ?>';">Set to Sale End (<?php echo esc_html($sale_end->format('M j')); ?>)</button>
                    <button type="button" class="button button-link" onclick="document.getElementById('test_black_friday_date').value = '';">Clear Date</button>
                </div>
            </div>

            <!-- Swap Domain Section -->
            <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <h2 style="margin-top: 0; margin-bottom: 15px; font-size: 18px;">Links Testing</h2>
                <div style="margin-bottom: 10px;">
                    <label for="themeisle_sdk_blackfriday_swap_domain" style="display: block; font-weight: 500; margin-bottom: 5px;">Swap Domain:</label>
                    <input type="text" id="themeisle_sdk_blackfriday_swap_domain" name="themeisle_sdk_blackfriday_swap_domain" value="<?php echo esc_attr($saved_swap_domain); ?>" placeholder="e.g., example.com" style="padding: 5px; width: 300px;">
                </div>
                <p class="description" style="margin: 5px 0 15px 0;">Leave blank to disable domain swapping. Enter the domain to replace sale URLs with. The default domain is "themeisle.link", if you are testing with another domain, use this option.</p>
                
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="button" onclick="document.getElementById('themeisle_sdk_blackfriday_swap_domain').value = 'themeisle-links.r.optml.cloud';">Set to themeisle-links.r.optml.cloud</button>
                    <button type="button" class="button button-link" onclick="document.getElementById('themeisle_sdk_blackfriday_swap_domain').value = '';">Clear Domain</button>
                </div>
            </div>

            <input type="submit" class="button button-primary" value="Save Settings" style="margin-top: 10px;">
        </form>
    </div>
    <?php
}

// Filter to override the date
add_filter('themeisle_sdk_current_date', function($date) {
    $saved_date = get_option('test_black_friday_date', '');
    if ($saved_date) {
        try {
            // Ensure time is set to prevent issues with comparisons if only date is provided
            return new \DateTime($saved_date . ' 00:00:00');
        } catch (\Exception $e) {
            // fallback to now if invalid
        }
    }
    // Ensure time is set for 'now' as well for consistency
    return new \DateTime('now');
});


add_filter( 'themeisle_sdk_blackfriday_data', function( $configs )  {
    $swap_domain = get_option( 'themeisle_sdk_blackfriday_swap_domain', false );
    if ( ! $swap_domain ) {
        return $configs;
    }

    foreach ( $configs as $key => $config ) {
        if ( ! empty( $config['sale_url'] ) ) {
            $parsed_url = wp_parse_url( $config['sale_url'] );
            
            if ( ! empty( $parsed_url['host'] ) ) {
                $configs[ $key ]['sale_url'] = str_replace( 
                    $parsed_url['host'], 
                    $swap_domain, 
                    $config['sale_url'] 
                );
                
                // Sanitize the final URL
                $configs[ $key ]['sale_url'] = esc_url_raw( $configs[ $key ]['sale_url'] );
            }
        }
    }

    return $configs;
}, 9999 );