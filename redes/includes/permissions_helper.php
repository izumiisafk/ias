<?php
/**
 * Permissions Helper - Manages Role-Based Access Control (RBAC) permissions.
 */

if (!function_exists('fetchRolePermissions')) {
    /**
     * Fetches all permission names associated with a given role ID.
     * 
     * @param PDO $conn Database connection.
     * @param int $role_id The ID of the role.
     * @return array List of permission names.
     */
    function fetchRolePermissions($conn, $role_id) {
        if (!$role_id) return [];
        
        try {
            $stmt = $conn->prepare("
                SELECT p.name 
                FROM public.permissions_ums p
                JOIN public.role_permissions_ums rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
            ");
            $stmt->execute([$role_id]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            // Log error or handle as needed
            error_log("Error fetching permissions for role $role_id: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('hasPermission')) {
    /**
     * Checks if the currently logged-in user has a specific permission.
     * 
     * @param string $permission_name The name of the permission to check.
     * @return bool True if the user has the permission, false otherwise.
     */
    function hasPermission($permission_name) {
        if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
            return false;
        }
        return in_array($permission_name, $_SESSION['permissions']);
    }
}
?>
