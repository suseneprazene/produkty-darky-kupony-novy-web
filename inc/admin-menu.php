<?php
if (!defined('ABSPATH')) exit;

// Hlavní menu a všechny podmenu pouze zde!
add_action('admin_menu', function() {
    add_menu_page(
        __('Produkty, dárky, kupony', 'produkty-darky-kupony'),
        __('Produkty, dárky, kupony', 'produkty-darky-kupony'),
        'manage_woocommerce',
        'produkty-darky-kupony',
        '', // žádný callback = prázdná stránka (bude skryto níže)
        'dashicons-cart',
        55
    );

    // Podmenu: Dárky zdarma
    add_submenu_page(
        'produkty-darky-kupony',
        __('Dárky zdarma', 'produkty-darky-kupony'),
        __('Dárky zdarma', 'produkty-darky-kupony'),
        'manage_woocommerce',
        'pdk-gifts',
        'pdk_gifts_page'
    );

    // Podmenu: Automatické kupóny
    add_submenu_page(
        'produkty-darky-kupony',
        __('Automatické kupóny', 'produkty-darky-kupony'),
        __('Automatické kupóny', 'produkty-darky-kupony'),
        'manage_woocommerce',
        'pdk-coupons',
        'pdk_coupons_main_page'
    );

    // Podmenu: Slevy podle role
    add_submenu_page(
        'produkty-darky-kupony',
        __('Slevy podle role', 'produkty-darky-kupony'),
        __('Slevy podle role', 'produkty-darky-kupony'),
        'manage_woocommerce',
        'pdk-pricing',
        'pdk_pricing_rules_page'
    );

    // Podmenu: Import/Export
    add_submenu_page(
        'produkty-darky-kupony',
        __('Import/Export', 'produkty-darky-kupony'),
        __('Import/Export', 'produkty-darky-kupony'),
        'manage_woocommerce',
        'pdk-import-export',
        'pdk_import_export_page'
    );

    // Skryté podmenu - pouze pro přímý přístup
    add_submenu_page(
        'produkty-darky-kupony',
        __('Bundly příchutí', 'produkty-darky-kupony'),
        '', // prázdný menu title = nezobrazí se v menu
        'manage_woocommerce',
        'pdk-bundles',
        'pdk_bundles_page'
    );

    add_submenu_page(
        'produkty-darky-kupony',
        __('Změna uživatelského jména', 'produkty-darky-kupony'),
        '', // prázdný menu title = nezobrazí se v menu
        'manage_woocommerce',
        'pdk-username-change',
        'pdk_username_change_page'
    );
}, 10);

// Skryje defaultní prázdnou hlavní stránku z podmenu
add_action('admin_menu', function() {
    global $submenu;
    remove_submenu_page('produkty-darky-kupony', 'produkty-darky-kupony');
}, 999);