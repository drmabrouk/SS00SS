<?php
if (!defined('ABSPATH')) {
    exit;
}

class VS_Geolocation {

    /**
     * Calculate distance between two points in meters using Haversine formula.
     */
    public static function calculate_distance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371000; // Earth radius in meters

        $lat1_rad = deg2rad((float)$lat1);
        $lon1_rad = deg2rad((float)$lon1);
        $lat2_rad = deg2rad((float)$lat2);
        $lon2_rad = deg2rad((float)$lon2);

        $delta_lat = $lat2_rad - $lat1_rad;
        $delta_lon = $lon2_rad - $lon1_rad;

        $a = sin($delta_lat / 2) * sin($delta_lat / 2) +
             cos($lat1_rad) * cos($lat2_rad) *
             sin($delta_lon / 2) * sin($delta_lon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earth_radius * $c; // distance in meters
    }

    /**
     * Validate if employee location is within branch geofence radius.
     */
    public static function verify_location($emp_lat, $emp_lng, $accuracy, $branch_lat, $branch_lng, $geofence_radius, $max_allowed_accuracy = 200) {
        if (!VS_Validation::validate_coordinates($emp_lat, $emp_lng) || !VS_Validation::validate_coordinates($branch_lat, $branch_lng)) {
            return array(
                'valid' => false,
                'message' => __('Invalid GPS coordinates format.', 'verification-system'),
                'distance' => null
            );
        }

        if ((float)$accuracy > $max_allowed_accuracy) {
            return array(
                'valid' => false,
                'message' => sprintf(__('GPS signal accuracy (%dm) is too low. Please move to an area with better GPS reception.', 'verification-system'), round($accuracy)),
                'distance' => null
            );
        }

        $distance = self::calculate_distance($emp_lat, $emp_lng, $branch_lat, $branch_lng);

        if ($distance > (float)$geofence_radius) {
            return array(
                'valid' => false,
                'message' => sprintf(__('You are outside the authorized work location (%dm away, allowed radius is %dm).', 'verification-system'), round($distance), round($geofence_radius)),
                'distance' => $distance
            );
        }

        return array(
            'valid' => true,
            'message' => __('Location verified successfully.', 'verification-system'),
            'distance' => $distance
        );
    }
}
