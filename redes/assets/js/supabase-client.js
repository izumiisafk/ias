/**
 * Supabase Client Initialization
 * This file sets up the global 'supabase' object for real-time features.
 */

// Create a single supabase client for interacting with your database
// Note: SUPABASE_URL and SUPABASE_KEY are injected in header.php from environment variables
window.supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_KEY);


console.log('⚡ Supabase Client Initialized');
