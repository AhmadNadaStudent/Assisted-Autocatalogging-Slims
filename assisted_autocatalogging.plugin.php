<?php
/**
 * Plugin Name: Assisted Autocataloguing
 * Plugin URI: https://github.com/AhmadNadaStudent/Assisted-Autocatalogging-Slims
 * Description: Plugin copy cataloguing cerdas berbantuan AI dan OCR untuk SLiMS 9 Bulian dengan verifikasi bukti fisik buku dan integrasi Perpustakaan Nasional.
 * Version: 1.1.0
 * Author: Nekodan & Antigravity
 * Author URI: https://slims.web.id
 */

// Block direct execution outside SLiMS
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}

// Get SLiMS plugin instance
$plugin = \SLiMS\Plugins::getInstance();

// Register menu in Bibliography module
$plugin->registerMenu('bibliography', __('Assisted Autocataloguing'), __DIR__ . '/index.php');
