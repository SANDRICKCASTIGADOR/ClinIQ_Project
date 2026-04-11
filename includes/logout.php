<?php
// ================================================
// Logout - Works with ANY folder name
// ================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (isset($_SESSION['user_id'])) {
    try { logActivity($_SESSION['user_id'], 'Logout', 'User logged out'); } catch(Exception $e){}
}

// Destroy session fully
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// Build redirect URL dynamically - works with hospital_mangement, hospital-tms, or any name
$scriptPath  = str_replace('\\', '/', __FILE__);          // e.g. /var/www/html/hospital_mangement/includes/logout.php
$includesDir = str_replace('\\', '/', __DIR__);            // e.g. /var/www/html/hospital_mangement/includes
$projectDir  = dirname($includesDir);                         // e.g. /var/www/html/hospital_mangement
$docRoot     = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']); // e.g. /var/www/html

// Get web path of project root
$webPath = str_replace($docRoot, '', $projectDir);           // e.g. /hospital_mangement
$webPath = str_replace('\\', '/', $webPath);

header('Location: ' . $webPath . '/index.php');
exit;