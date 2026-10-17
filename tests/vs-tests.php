<?php
/**
 * Comprehensive Test Suite for Verification System
 */

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { return true; }
}

if (!function_exists('add_role')) {
    function add_role($role, $display_name, $capabilities = array()) { return true; }
}

if (!function_exists('get_role')) {
    function get_role($role) { return null; }
}

if (!function_exists('home_url')) {
    function home_url() { return 'https://example.com'; }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
}

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

// Simple test framework
class VS_Test_Runner {

    private $passed = 0;
    private $failed = 0;

    public function assert_equals($expected, $actual, $message) {
        if ($expected === $actual) {
            $this->passed++;
            echo "✔ PASS: {$message}\n";
        } else {
            $this->failed++;
            echo "✖ FAIL: {$message} (Expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")\n";
        }
    }

    public function assert_true($condition, $message) {
        $this->assert_equals(true, (bool)$condition, $message);
    }

    public function summary() {
        echo "\n=========================================\n";
        echo "TEST SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "=========================================\n";
        return $this->failed === 0;
    }
}

require_once __DIR__ . '/../includes/class-roles.php';
require_once __DIR__ . '/../includes/class-permissions.php';
require_once __DIR__ . '/../includes/class-validation.php';
require_once __DIR__ . '/../includes/class-geolocation.php';
require_once __DIR__ . '/../includes/class-payroll.php';
require_once __DIR__ . '/../includes/class-passkeys.php';

$runner = new VS_Test_Runner();

// Test 1: Phone Normalization
$runner->assert_equals('+971501234567', VS_Validation::normalize_phone('0501234567'), 'Normalize UAE local number');
$runner->assert_equals('+971501234567', VS_Validation::normalize_phone('+971 50 123 4567'), 'Normalize formatted UAE number');
$runner->assert_equals('+971501234567', VS_Validation::normalize_phone('00971501234567'), 'Normalize 00971 prefix');

// Test 2: Coordinates Validation
$runner->assert_true(VS_Validation::validate_coordinates(25.2048, 55.2708), 'Valid Dubai GPS coordinates');
$runner->assert_equals(false, VS_Validation::validate_coordinates(100.0, 55.2708), 'Invalid Latitude > 90');

// Test 3: Haversine Geodesic Distance
$dist = VS_Geolocation::calculate_distance(25.1972, 55.2797, 25.1972, 55.2744);
$runner->assert_true($dist > 500 && $dist < 600, 'Haversine distance calculation approx ~530m');

// Test 4: Geofence Verification
$geo_pass = VS_Geolocation::verify_location(25.1972, 55.2797, 10, 25.1972, 55.2797, 100);
$runner->assert_true($geo_pass['valid'], 'Geofence pass when at exact location');

$geo_fail = VS_Geolocation::verify_location(25.1972, 55.2797, 10, 25.2500, 55.3000, 100);
$runner->assert_equals(false, $geo_fail['valid'], 'Geofence reject when outside allowed radius');

// Test 5: System Roles Definition
$runner->assert_equals('administrator', VS_Roles::ROLE_ADMIN, 'Role Admin is administrator');
$runner->assert_equals('vs_general_manager', VS_Roles::ROLE_GENERAL_MANAGER, 'Role General Manager is vs_general_manager');
$runner->assert_equals('vs_branch_manager', VS_Roles::ROLE_BRANCH_MANAGER, 'Role Branch Manager is vs_branch_manager');
$runner->assert_equals('vs_employee', VS_Roles::ROLE_EMPLOYEE, 'Role Employee is vs_employee');

// Test 6: Passkey RP ID Extraction
$rp_id = VS_Passkeys::get_rp_id();
$runner->assert_equals('example.com', $rp_id, 'Passkey Relying Party ID correctly extracted domain');

if (!$runner->summary()) {
    exit(1);
}
