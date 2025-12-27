<?php
/**
 * PHP script to generate test appointments for mpeti-booking-calendar
 * Can be run via: wp eval-file generate-test-appointments.php --count=10
 * Or directly: php generate-test-appointments.php 10
 */

// Get count from constant (set by batch file), environment variable, command line argument, or default
$count = 10;

// First, try constant (set by batch file wrapper)
if (defined('MBC_APPOINTMENT_COUNT') && is_numeric(MBC_APPOINTMENT_COUNT)) {
    $count = (int)MBC_APPOINTMENT_COUNT;
} elseif (isset($_ENV['MBC_APPOINTMENT_COUNT']) && is_numeric($_ENV['MBC_APPOINTMENT_COUNT'])) {
    // Try environment variable
    $count = (int)$_ENV['MBC_APPOINTMENT_COUNT'];
} elseif (php_sapi_name() === 'cli') {
    // If run directly via PHP CLI
    if (isset($argv[1]) && is_numeric($argv[1])) {
        $count = (int)$argv[1];
    }
}

// Debug output
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::log("Generating $count appointments...");
}

// If not loaded in WordPress context, load WordPress
if (!defined('ABSPATH')) {
    // Try to find wp-load.php
    $wp_load_paths = [
        __DIR__ . '/app/public/wp-load.php',
        __DIR__ . '/wp-load.php',
        dirname(__DIR__) . '/wp-load.php',
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die("Error: Could not find wp-load.php. Please run this script via WP-CLI or ensure WordPress is loaded.\n");
    }
}

// Sample data
$first_names = [
    'John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'Robert', 'Jessica',
    'William', 'Ashley', 'James', 'Amanda', 'Christopher', 'Melissa', 'Daniel',
    'Michelle', 'Matthew', 'Kimberly', 'Anthony', 'Amy', 'Mark', 'Angela',
    'Donald', 'Stephanie', 'Steven', 'Nicole', 'Paul', 'Elizabeth', 'Andrew',
    'Helen', 'Joshua', 'Sandra', 'Kenneth', 'Donna', 'Kevin', 'Carol', 'Brian',
    'Ruth', 'George', 'Sharon', 'Edward', 'Michelle', 'Ronald', 'Laura'
];

$last_names = [
    'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
    'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Wilson', 'Anderson', 'Thomas',
    'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Thompson', 'White', 'Harris',
    'Clark', 'Lewis', 'Robinson', 'Walker', 'Young', 'Allen', 'King', 'Wright',
    'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores', 'Green', 'Adams', 'Nelson'
];

$email_domains = [
    'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com',
    'aol.com', 'mail.com', 'protonmail.com', 'live.com', 'msn.com'
];

$sample_notes = [
    'First time customer',
    'Returning customer',
    'Please call when arrived',
    'Customer prefers morning appointments',
    'Has allergies - please note',
    'VIP customer',
    'Requires wheelchair access',
    'Prepaid appointment',
    '',
    '',
    ''
];

$statuses = ['pending', 'confirmed', 'pending', 'confirmed', 'pending'];

// Get all services
$services = get_posts([
    'post_type' => 'mbc_service',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
]);

if (empty($services)) {
    die("Error: No services found. Please create services first.\n");
}

echo "Found " . count($services) . " service(s)\n";

// Get all staff members
$staff = get_posts([
    'post_type' => 'mbc_staff',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
]);

if (empty($staff)) {
    die("Error: No staff members found. Please create staff members first.\n");
}

echo "Found " . count($staff) . " staff member(s)\n\n";

/**
 * Get available timeslots for a staff member on a date
 */
function get_available_timeslots($date, $staff_id) {
    global $wpdb;
    
    $date_obj = new DateTime($date);
    $day_of_week = (int)$date_obj->format('w');
    
    $table = $wpdb->prefix . 'mbc_timeslots';
    $timeslots = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT start_time, end_time, capacity FROM {$table} WHERE weekday = %d AND staff_id = %d AND is_active = 1",
            $day_of_week,
            $staff_id
        ),
        ARRAY_A
    );
    
    if (empty($timeslots)) {
        return [];
    }
    
    // Generate all possible time slots (30-minute intervals)
    $all_slots = [];
    foreach ($timeslots as $slot) {
        $start = strtotime($date . ' ' . $slot['start_time']);
        $end = strtotime($date . ' ' . $slot['end_time']);
        $current = $start;
        
        while ($current < $end) {
            $all_slots[] = [
                'time' => date('H:i', $current),
                'capacity' => (int)$slot['capacity']
            ];
            $current += 30 * 60; // 30 minutes
        }
    }
    
    // Get booked appointments for this staff/date
    $booked = get_posts([
        'post_type' => 'mbc_appointment',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            ['key' => 'staff_id', 'value' => $staff_id],
            ['key' => 'appointment_date', 'value' => $date],
        ],
    ]);
    
    $booked_times = [];
    foreach ($booked as $apt) {
        $time = get_post_meta($apt->ID, 'appointment_time', true);
        if ($time) {
            $booked_times[] = $time;
        }
    }
    
    // Filter available slots based on capacity
    $available = [];
    foreach ($all_slots as $slot) {
        $booked_count = count(array_filter($booked_times, function($t) use ($slot) {
            return $t === $slot['time'];
        }));
        
        if ($booked_count < $slot['capacity']) {
            $available[] = $slot['time'];
        }
    }
    
    return $available;
}

/**
 * Find an available slot
 */
function find_available_slot($staff_ids, $max_attempts = 50) {
    for ($i = 0; $i < $max_attempts; $i++) {
        $staff_id = $staff_ids[array_rand($staff_ids)];
        $date = date('Y-m-d', strtotime('+' . rand(0, 90) . ' days'));
        $slots = get_available_timeslots($date, $staff_id);
        
        if (!empty($slots)) {
            return [
                'staff_id' => $staff_id,
                'date' => $date,
                'time' => $slots[array_rand($slots)]
            ];
        }
    }
    
    return null;
}

// Generate appointments
$success_count = 0;
$fail_count = 0;

for ($i = 1; $i <= $count; $i++) {
    echo "Creating appointment $i/$count... ";
    
    // Generate random customer data
    $first_name = $first_names[array_rand($first_names)];
    $last_name = $last_names[array_rand($last_names)];
    $full_name = "$first_name $last_name";
    $email_domain = $email_domains[array_rand($email_domains)];
    $email = strtolower($first_name) . '.' . strtolower($last_name) . '@' . $email_domain;
    $phone = '555-' . rand(100, 999) . '-' . rand(1000, 9999);
    $notes = $sample_notes[array_rand($sample_notes)];
    $status = $statuses[array_rand($statuses)];
    
    // Find available slot
    $slot = find_available_slot($staff);
    
    if (!$slot) {
        echo "SKIPPED (no available slots)\n";
        $fail_count++;
        continue;
    }
    
    // Get a service that the staff member provides
    $staff_services = get_post_meta($slot['staff_id'], 'staff_services', true);
    $service_id = 0;
    
    if (!empty($staff_services) && is_array($staff_services)) {
        $service_id = $staff_services[array_rand($staff_services)];
    } else {
        // If staff has no assigned services, pick a random service
        $service_id = $services[array_rand($services)];
    }
    
    // Create appointment post
    $post_title = $slot['date'] . ' - ' . $full_name;
    $post_id = wp_insert_post([
        'post_title' => $post_title,
        'post_type' => 'mbc_appointment',
        'post_status' => 'publish',
    ]);
    
    if (is_wp_error($post_id) || !$post_id) {
        echo "FAILED (post creation)\n";
        $fail_count++;
        continue;
    }
    
    // Set appointment metadata
    update_post_meta($post_id, 'customer_name', $full_name);
    update_post_meta($post_id, 'customer_email', $email);
    update_post_meta($post_id, 'customer_phone', $phone);
    update_post_meta($post_id, 'appointment_date', $slot['date']);
    update_post_meta($post_id, 'appointment_time', $slot['time']);
    update_post_meta($post_id, 'appointment_status', $status);
    update_post_meta($post_id, 'service_id', $service_id);
    update_post_meta($post_id, 'staff_id', $slot['staff_id']);
    update_post_meta($post_id, 'appointment_datetime', $slot['date'] . ' ' . $slot['time']);
    
    if ($notes) {
        update_post_meta($post_id, 'notes', $notes);
    }
    
    $staff_name = get_the_title($slot['staff_id']);
    echo "OK - $full_name on {$slot['date']} at {$slot['time']} with $staff_name (Status: $status)\n";
    $success_count++;
}

echo "\n";
echo "Generation complete!\n";
echo "  Success: $success_count\n";
echo "  Failed: $fail_count\n";

