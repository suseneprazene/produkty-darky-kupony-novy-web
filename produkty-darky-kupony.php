<?php
/*
Plugin Name: Produkty, dárky, kupony
Description: Kompletní plugin pro správu dárků, automatických kupónů, bundlů příchutí, slev podle role a změnu uživatelského jména ve WooCommerce. Obsahuje také zálohování/export/import nastavení.
Author: suseneprazene + AI Copilot
Version: 1.0.1
Text Domain: produkty-darky-kupony
*/

if (!defined('ABSPATH')) exit;

// Načti všechny části pluginu (každý modul zvlášť)
require_once plugin_dir_path(__FILE__) . 'inc/admin-menu.php';
require_once plugin_dir_path(__FILE__) . 'inc/gifts.php';
require_once plugin_dir_path(__FILE__) . 'inc/coupons.php';
require_once plugin_dir_path(__FILE__) . 'inc/bundles.php';
require_once plugin_dir_path(__FILE__) . 'inc/pricing-rules.php';
require_once plugin_dir_path(__FILE__) . 'inc/subscriptions.php';
require_once plugin_dir_path(__FILE__) . 'inc/import-export.php';
require_once plugin_dir_path(__FILE__) . 'inc/username-change.php';
require_once plugin_dir_path(__FILE__) . 'inc/flycart-history.php';
require_once plugin_dir_path(__FILE__) . 'inc/fixed-bundles.php'; // Nový modul

// Připojení JavaScriptového souboru pro funkci modálních oken
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'fb-quick-view', // Handle skriptu
        plugin_dir_url(__FILE__) . 'assets/js/fb-quick-view.js', // Cesta k JavaScriptovému souboru
        ['jquery'], // Závislosti
        null, // Verze
        true // Umístění v patičce stránky
    );

    // Předání AJAX URL do JS souboru
    wp_localize_script('fb-quick-view', 'fbQuickView', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
    ]);
});