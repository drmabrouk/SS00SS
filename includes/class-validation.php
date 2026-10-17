<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Validation {

    public static function normalize_phone($phone) {
        // Remove spaces, hyphens, brackets
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        if (empty($cleaned)) {
            return '';
        }

        // Default UAE country code +971 if starts with 05 or 5 or 00971
        if (strpos($cleaned, '00971') === 0) {
            $cleaned = '+' . substr($cleaned, 2);
        } elseif (strpos($cleaned, '971') === 0) {
            $cleaned = '+' . $cleaned;
        } elseif (strpos($cleaned, '05') === 0) {
            $cleaned = '+971' . substr($cleaned, 1);
        } elseif (strpos($cleaned, '5') === 0 && strlen($cleaned) === 9) {
            $cleaned = '+971' . $cleaned;
        } elseif (strpos($cleaned, '+') !== 0) {
            $cleaned = '+' . $cleaned;
        }

        return $cleaned;
    }

    public static function validate_coordinates($lat, $lng) {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return false;
        }
        $lat = (float)$lat;
        $lng = (float)$lng;

        return ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180);
    }
}
