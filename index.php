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
        
        // Handle license data updates
        if (isset($_POST['license_data_updates']) && is_array($_POST['license_data_updates'])) {
            $valid_statuses = array('valid', 'expired', 'active-expired', 'invalid');
            foreach ($_POST['license_data_updates'] as $option_name => $new_status) {
                $new_status = sanitize_text_field($new_status);
                if (in_array($new_status, $valid_statuses, true)) {
                    $current_data = get_option($option_name);
                    if (is_object($current_data) || is_array($current_data)) {
                        $current_data = (object) $current_data;
                        $current_data->license = $new_status;
                        update_option($option_name, $current_data);
                        
                        // Update related transient with same data (preserve existing timeout or set 1 hour)
                        global $wpdb;
                        $transient_name = $option_name;
                        $timeout_option_name = '_transient_timeout_' . $option_name;
                        
                        // Get the current transient timeout if it exists
                        $current_timeout = get_option($timeout_option_name);
                        $transient_ttl = 3600; // Default 1 hour
                        
                        if ($current_timeout && is_numeric($current_timeout)) {
                            // Calculate remaining TTL from stored timeout
                            $remaining_ttl = $current_timeout - time();
                            if ($remaining_ttl > 0) {
                                $transient_ttl = $remaining_ttl;
                            }
                        }
                        
                        // Update the transient with the new data
                        set_transient($transient_name, $current_data, $transient_ttl);
                    }
                }
            }
        }
        
        // Handle install data updates
        if (isset($_POST['install_data_updates']) && is_array($_POST['install_data_updates'])) {
            foreach ($_POST['install_data_updates'] as $option_name => $new_date) {
                $new_date = sanitize_text_field($new_date);
                if (!empty($new_date)) {
                    // Convert date string to timestamp
                    $timestamp = strtotime($new_date);
                    if ($timestamp !== false) {
                        update_option($option_name, $timestamp);
                    }
                }
            }
        }
        
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

            <!-- License Testing Section -->
            <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <h2 style="margin-top: 0; margin-bottom: 15px; font-size: 18px;">License Testing</h2>
                <p class="description" style="margin: 0 0 15px 0;">Modify license status for different testing scenarios.</p>
                
                <?php
                global $wpdb;
                // Get all options ending with _license_data (excluding transients)
                $license_options = $wpdb->get_col(
                    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%_license_data' AND option_name NOT LIKE '%_transient_%'"
                );
                
                if (empty($license_options)) {
                    echo '<p style="background: #fff8e5; border-left: 4px solid #ffb900; padding: 12px; border-radius: 4px; margin: 0;">No license data options found in the database.</p>';
                } else {
                    ?>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                        <thead>
                            <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                                <th style="padding: 10px; text-align: left; font-weight: 600;">Option Name</th>
                                <th style="padding: 10px; text-align: left; font-weight: 600;">Current Status</th>
                                <th style="padding: 10px; text-align: left; font-weight: 600;">License Key</th>
                                <th style="padding: 10px; text-align: left; font-weight: 600;">Change Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $license_statuses = array('valid', 'expired', 'active-expired', 'invalid');
                            foreach ($license_options as $option_name) {
                                $license_data = get_option($option_name);
                                $current_status = '';
                                $license_key = '';
                                
                                if (is_object($license_data)) {
                                    $current_status = isset($license_data->license) ? $license_data->license : 'unknown';
                                    $license_key = isset($license_data->key) ? $license_data->key : '';
                                } elseif (is_array($license_data)) {
                                    $current_status = isset($license_data['license']) ? $license_data['license'] : 'unknown';
                                    $license_key = isset($license_data['key']) ? $license_data['key'] : '';
                                }
                                
                                // Redact the key for display
                                $key_display = !empty($license_key) ? substr($license_key, 0, 10) . '...' . substr($license_key, -10) : 'N/A';
                                ?>
                                <tr style="border-bottom: 1px solid #ddd;">
                                    <td style="padding: 10px; font-family: monospace; font-size: 12px; word-break: break-all;"><?php echo esc_html($option_name); ?></td>
                                    <td style="padding: 10px;">
                                        <span style="display: inline-block; padding: 4px 10px; border-radius: 3px; font-size: 12px; font-weight: 600;
                                            <?php
                                            if ($current_status === 'valid') {
                                                echo 'background: #d4edda; color: #155724;';
                                            } elseif ($current_status === 'expired') {
                                                echo 'background: #f8d7da; color: #721c24;';
                                            } elseif ($current_status === 'active-expired') {
                                                echo 'background: #fff3cd; color: #856404;';
                                            } else {
                                                echo 'background: #f5f5f5; color: #333;';
                                            }
                                            ?>
                                        ">
                                            <?php echo esc_html($current_status); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 10px; font-family: monospace; font-size: 11px; color: #666;"><?php echo esc_html($key_display); ?></td>
                                    <td style="padding: 10px;">
                                        <select name="license_data_updates[<?php echo esc_attr($option_name); ?>]" style="padding: 5px; border: 1px solid #ddd; border-radius: 3px;">
                                            <option value="">-- Select Status --</option>
                                            <?php foreach ($license_statuses as $status) : ?>
                                                <option value="<?php echo esc_attr($status); ?>" <?php selected($current_status, $status); ?>>
                                                    <?php echo esc_html(ucwords(str_replace('-', ' ', $status))); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                    <?php
                }
                ?>
            </div>

            <!-- Install Data Testing Section -->
             <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 4px;">
                 <h2 style="margin-top: 0; margin-bottom: 15px; font-size: 18px;">Install Data</h2>
                 <p class="description" style="margin: 0 0 15px 0;">Modify install timestamps for testing install age-based features.</p>
                 <p style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 12px; border-radius: 4px; margin: 0 0 15px 0; font-size: 13px;">
                     <strong>Reference Time:</strong> Age calculations use the same reference date as the SDK (<strong id="reference-date-display"></strong>). Override the date above to test different install age scenarios.
                 </p>
                
                <?php
                global $wpdb;
                // Get all options ending with _install (excluding transients)
                $install_options = $wpdb->get_col(
                    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%_install' AND option_name NOT LIKE '%_transient_%' ORDER BY option_name ASC"
                );
                
                if (empty($install_options)) {
                    echo '<p style="background: #fff8e5; border-left: 4px solid #ffb900; padding: 12px; border-radius: 4px; margin: 0;">No install data options found in the database.</p>';
                } else {
                    ?>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                        <thead>
                            <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                                <th style="padding: 10px; text-align: left; font-weight: 600;">Option Name</th>
                                <th style="padding: 10px; text-align: left; font-weight: 600;">Timestamp</th>
                                <th style="padding: 10px; text-align: left; font-weight: 600;">Install Date</th>
                                <th style="padding: 10px; text-align: left; font-weight: 600;">Age</th>
                                <th style="padding: 10px; text-align: left; font-weight: 600;">Change Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Use the same filtered current date that SDK uses for comparisons
                            $reference_date = apply_filters('themeisle_sdk_current_date', new \DateTime('now'));
                            $reference_timestamp = $reference_date->getTimestamp();
                            $reference_date_display = $reference_date->format('Y-m-d H:i:s');
                            
                            foreach ($install_options as $option_name) {
                                $timestamp = get_option($option_name);
                                $is_valid_timestamp = is_numeric($timestamp) && (int)$timestamp == $timestamp && (int)$timestamp > 0;
                                
                                if ($is_valid_timestamp) {
                                    $timestamp = (int)$timestamp;
                                    $install_date = date('Y-m-d H:i:s', $timestamp);
                                    $date_only = date('Y-m-d', $timestamp);
                                    
                                    // Calculate age using the same reference time as the SDK
                                    $diff = $reference_timestamp - $timestamp;
                                    $age = test_black_friday_format_time_diff($diff);
                                    ?>
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 10px; font-family: monospace; font-size: 12px; word-break: break-all;"><?php echo esc_html($option_name); ?></td>
                                        <td style="padding: 10px; font-family: monospace; font-size: 12px;"><?php echo esc_html($timestamp); ?></td>
                                        <td style="padding: 10px; font-size: 13px;"><?php echo esc_html($install_date); ?></td>
                                        <td style="padding: 10px; font-size: 13px; font-weight: 500;">
                                            <span style="display: inline-block; padding: 4px 8px; border-radius: 3px; background: #e7f3ff; color: #0066cc;">
                                                <?php echo esc_html($age); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 10px;">
                                            <input type="date" name="install_data_updates[<?php echo esc_attr($option_name); ?>]" value="<?php echo esc_attr($date_only); ?>" style="padding: 5px; border: 1px solid #ddd; border-radius: 3px; width: 150px;">
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                    <script>
                        // Display the reference date being used for age calculations
                        document.getElementById('reference-date-display').textContent = '<?php echo esc_js($reference_date_display); ?>';
                    </script>
                    <?php
                }
                ?>
            </div>

            <input type="submit" class="button button-primary" value="Save Settings" style="margin-top: 10px;">
        </form>
    </div>
    <?php
}

/**
 * Format a time difference in seconds into a human-readable string
 * e.g., "3 days, 2 hours" or "45 minutes"
 */
function test_black_friday_format_time_diff($seconds) {
    $seconds = (int)$seconds;
    
    if ($seconds < 60) {
        return $seconds . ' second' . ($seconds !== 1 ? 's' : '');
    }
    
    $minutes = floor($seconds / 60);
    if ($minutes < 60) {
        return $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
    }
    
    $hours = floor($seconds / 3600);
    if ($hours < 24) {
        $remaining_minutes = floor(($seconds % 3600) / 60);
        $result = $hours . ' hour' . ($hours !== 1 ? 's' : '');
        if ($remaining_minutes > 0) {
            $result .= ', ' . $remaining_minutes . ' minute' . ($remaining_minutes !== 1 ? 's' : '');
        }
        return $result;
    }
    
    $days = floor($seconds / 86400);
    $remaining_hours = floor(($seconds % 86400) / 3600);
    $result = $days . ' day' . ($days !== 1 ? 's' : '');
    if ($remaining_hours > 0) {
        $result .= ', ' . $remaining_hours . ' hour' . ($remaining_hours !== 1 ? 's' : '');
    }
    return $result;
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
}, 999);


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