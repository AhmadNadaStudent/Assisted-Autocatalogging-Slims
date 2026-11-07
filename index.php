<?php
/**
 * Assisted Autocataloguing - Main Plugin Page
 */

// Key to authenticate
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}

// 1. Ensure SLiMS sysconfig is loaded
$slimsRoot = dirname(__DIR__, 2);
if (!defined('SB') && file_exists($slimsRoot . '/sysconfig.inc.php')) {
    require_once $slimsRoot . '/sysconfig.inc.php';
}

// 2. IP based access limitation
if (defined('LIB') && file_exists(LIB . 'ip_based_access.inc.php')) {
    require_once LIB . 'ip_based_access.inc.php';
    if (function_exists('do_checkIP')) {
        do_checkIP('smc');
        do_checkIP('smc-bibliography');
    }
}

// 3. Start SLiMS admin session & session validation
if (defined('SB')) {
    if (file_exists(SB . 'admin/default/session.inc.php')) {
        require_once SB . 'admin/default/session.inc.php';
    }
    // Only run session_check if not already validated in current request lifecycle
    if (!isset($_SESSION['uid']) && file_exists(SB . 'admin/default/session_check.inc.php')) {
        require_once SB . 'admin/default/session_check.inc.php';
    }
}

// 4. Privilege check
$canRead = false;
if (isset($_SESSION['uid']) && $_SESSION['uid'] == 1) {
    $canRead = true; // Super admin always has full access
} elseif (class_exists('utility') && method_exists('utility', 'havePrivilege')) {
    $canRead = \utility::havePrivilege('bibliography', 'r');
}

if (!$canRead) {
    die('<div class="errorBox" style="padding:15px;margin:15px;background:#fee2e2;border:1px solid #ef4444;border-radius:8px;color:#991b1b;font-weight:600;">' .
        __('You are not authorized to view this section') .
        '<div style="font-weight:normal;font-size:0.85em;margin-top:5px;">Pastikan Anda telah masuk (login) ke panel admin SLiMS dan akun Anda memiliki hak akses modul Bibliografi.</div>' .
        '</div>');
}

// 5. Handle AJAX actions directly if called via plugin container
if (isset($_GET['action']) || isset($_POST['action'])) {
    require __DIR__ . '/api.php';
    exit;
}

// 6. Bootstrap plugin application & render layout
require_once __DIR__ . '/src/Core/Application.php';
\AssistedAutocataloguing\Core\Application::registerAutoloader(__DIR__);

require __DIR__ . '/templates/main_layout.php';
