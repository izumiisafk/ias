<?php
/**
 * Activity Logging Helper
 * 
 * Logs user actions into the public.activity_logs_ums table.
 */

if (!function_exists('logActivity')) {
    /**
     * Logs an activity to the database.
     * 
     * @param int|null $user_id The ID of the user (null for guest/unknown)
     * @param string $event_type The category of the event (e.g., 'login', 'security')
     * @param string $action Descriptive message of the action
     * @param int $module_id Module ID (default 4: Authentication)
     * @param int $submodule_id Submodule ID (default 3: Authentication & Login Security)
     * @return bool Success or failure
     */
    function logActivity($user_id, $event_type, $action, $module_id = 4, $submodule_id = 3) {
        global $conn;
        
        // Ensure connection exists
        if (!$conn) {
            require_once __DIR__ . '/../config/db.php';
        }
        
        if (!$conn) {
            error_log("Activity Log Error: No database connection.");
            return false;
        }

        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            
            $stmt = $conn->prepare("
                INSERT INTO public.activity_logs_ums 
                (user_id, event_type, action, ip_address, module_id, submodule_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            return $stmt->execute([
                $user_id, 
                $event_type, 
                $action, 
                $ip, 
                $module_id, 
                $submodule_id
            ]);
        } catch (Exception $e) {
            error_log("Activity Log Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
