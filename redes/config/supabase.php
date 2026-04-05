<?php
/**
 * Supabase Configuration
 * Loads credentials from environment variables manually loaded via env_loader.php
 */
require_once __DIR__ . '/env_loader.php';

// Supabase URL and Anon Key
define('SUPABASE_URL',      getenv('SUPABASE_URL')       ?: '');
define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY')  ?: '');

// Optional: For debugging (ensure keys are loaded)
// if (empty(SUPABASE_URL) || empty(SUPABASE_ANON_KEY)) {
//     error_log("Supabase Configuration Error: URL or Key is missing.");
// }
?>
