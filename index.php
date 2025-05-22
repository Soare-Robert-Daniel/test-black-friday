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
        echo '<div class="updated"><p>Date saved.</p></div>';
    }
    $saved_date = get_option('test_black_friday_date', '');

    // Calculate Black Friday related dates for the current year
    $current_year = date('Y');
    $november_first = new DateTime("first day of November $current_year");
    $black_friday = new DateTime("fourth Friday of November $current_year");
    
    $sale_start_day_name = 'Monday'; // Monday of Black Friday week
    $sale_start_date = clone $black_friday;
    if ($sale_start_date->format('N') != 1) { // 1 is Monday
        $sale_start_date->modify("previous $sale_start_day_name");
    }

    $cyber_monday_date = clone $black_friday;
    $cyber_monday_date->modify('next Monday');

    $black_friday_date_str = $black_friday->format('Y-m-d');
    $sale_start_date_str = $sale_start_date->format('Y-m-d');
    $cyber_monday_date_str = $cyber_monday_date->format('Y-m-d');

    ?>
    <div class="wrap">
        <h1>Test Black Friday Date</h1>
        <p>
            For <?php echo esc_html($current_year); ?>, the Black Friday sale period is from 
            <strong><?php echo esc_html($sale_start_date->format('l, F jS')); ?></strong> (Sale Start) 
            to <strong><?php echo esc_html($cyber_monday_date->format('l, F jS')); ?></strong> (Cyber Monday).<br>
            Black Friday is on <strong><?php echo esc_html($black_friday->format('l, F jS')); ?></strong>.
        </p>
        <form method="post">
            <?php wp_nonce_field('test_black_friday_date_save', 'test_black_friday_date_nonce'); ?>
            <div>
                <label for="test_black_friday_date">Override Date:</label>
                <input type="date" id="test_black_friday_date" name="test_black_friday_date" value="<?php echo esc_attr($saved_date); ?>">
            </div>
            <p class="description">Leave blank to use the current date.</p>
            
            <div style="margin-top: 10px; margin-bottom: 20px;">
                <button type="button" class="button" onclick="document.getElementById('test_black_friday_date').value = '<?php echo esc_js($sale_start_date_str); ?>';">Set to Sale Start (<?php echo esc_html($sale_start_date->format('M j')); ?>)</button>
                <button type="button" class="button" onclick="document.getElementById('test_black_friday_date').value = '<?php echo esc_js($black_friday_date_str); ?>';">Set to Black Friday (<?php echo esc_html($black_friday->format('M j')); ?>)</button>
                <button type="button" class="button" onclick="document.getElementById('test_black_friday_date').value = '<?php echo esc_js($cyber_monday_date_str); ?>';">Set to Cyber Monday (<?php echo esc_html($cyber_monday_date->format('M j')); ?>)</button>
                <button type="button" class="button button-link" onclick="document.getElementById('test_black_friday_date').value = '';">Clear Date</button>
            </div>

            <input type="submit" class="button button-primary" value="Save Date">
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
