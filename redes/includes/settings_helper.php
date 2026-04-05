<?php
/**
 * Authentication Settings Helper
 * 
 * Fetches configuration from the public.auth_settings_ums table.
 */

if (!function_exists('getAuthSetting')) {
    /**
     * Retrieves a setting value from the database.
     * 
     * @param string $key The setting key (e.g., 'two_factor_auth')
     * @param mixed $default Default value if setting not found
     * @return mixed The setting value
     */
    function getAuthSetting($key, $default = null) {
        global $conn;
        static $settings_cache = [];

        // Return from cache if already fetched during this request
        if (isset($settings_cache[$key])) {
            return $settings_cache[$key];
        }

        // Ensure connection exists
        if (!$conn) {
            require_once __DIR__ . '/../config/db.php';
        }

        if (!$conn) {
            return $default;
        }

        try {
            // Fetch all settings once and cache them to reduce DB hits
            if (empty($settings_cache)) {
                $stmt = $conn->query("SELECT setting_key, setting_value FROM public.auth_settings_ums");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $settings_cache[$row['setting_key']] = $row['setting_value'];
                }
            }

            return $settings_cache[$key] ?? $default;
        } catch (Exception $e) {
            error_log("Auth Settings Error: " . $e->getMessage());
            return $default;
        }
    }
}
?>
