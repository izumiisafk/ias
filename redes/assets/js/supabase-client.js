/**
 * Supabase Client Initialization
 * This file sets up the global 'supabase' object for real-time features.
 */

const SUPABASE_URL = 'https://pnbrkfpqrigmsluzhbff.supabase.co';
const SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InBuYnJrZnBxcmlnbXNsdXpoYmZmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzQ1MTc0MjIsImV4cCI6MjA5MDA5MzQyMn0.NZQX-5gXIm5LGAQmDSI9Mfm0c16JjPLDQ9FGsDmtZcU';

// Create a single supabase client for interacting with your database
window.supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_KEY);

console.log('⚡ Supabase Client Initialized');
