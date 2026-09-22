<?php

// ==============================
// TYTs Supabase Configuration
// ==============================

// Your Supabase project URL
define('SUPABASE_URL', 'https://almcfhqbrqnnsfvkdbbk.supabase.co');

// Use anon key for normal frontend-safe operations.
// Later, if needed for secure admin-only server actions, use service role carefully.
define('SUPABASE_ANON_KEY', 'const SUPABASE_ANON_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImFsbWNmaHFicnFubnNmdmtkYmJrIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUwNDU4MTksImV4cCI6MjA5MDYyMTgxOX0.SRSN4uvvM54IOUElPXk4Rb9busbSt0Z6ghIiwoQhETI";
');

// Optional: service role key for admin/server-only operations
// NEVER expose this in frontend JavaScript
define('SUPABASE_SERVICE_ROLE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImFsbWNmaHFicnFubnNmdmtkYmJrIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3NTA0NTgxOSwiZXhwIjoyMDkwNjIxODE5fQ.KSELLLY_JHKB8bT3rijIEFAgkGsqv9cY-Slokeg_olw');

// Database table names
define('TABLE_ADMINS', 'admins');
define('TABLE_USERS', 'users');
define('TABLE_EXAMS', 'exams');
define('TABLE_SUBJECTS', 'subjects');
define('TABLE_CHAPTERS', 'chapters');
define('TABLE_MOCK_TESTS', 'mock_tests');
define('TABLE_QUESTIONS', 'questions');
define('TABLE_RESULTS', 'results');

// App settings
define('APP_NAME', 'Test Your Tests Admin');
define('BASE_URL', 'http://localhost/tyt_admin');

// Default timezone
date_default_timezone_set('Asia/Kolkata');