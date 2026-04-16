<?php
if (!defined('ABSPATH')) exit;

// Seznam všech klíčů, které plugin používá v options
function pdk_get_all_plugin_options() {
    return [
        'pdk_gift_rules',         // Dárky zdarma
        'pdk_coupons_rules',      // Automatické kupony
        'pdk_pricing_rules',      // Pravidla slev podle role
        // Přidej další pokud budeš potřebovat např. bundly, další custom...
    ];
}

function pdk_import_export_page() {
    // Export
    if (isset($_POST['pdk_export_nonce']) && wp_verify_nonce($_POST['pdk_export_nonce'], 'pdk_export')) {
        $data = [];
        foreach (pdk_get_all_plugin_options() as $option) {
            $data[$option] = get_option($option, null);
        }
        $filename = 'pdk-export-' . date('Y-m-d-H-i-s') . '.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Import
    if (isset($_POST['pdk_import_nonce']) && wp_verify_nonce($_POST['pdk_import_nonce'], 'pdk_import') && !empty($_FILES['pdk_import_file']['tmp_name'])) {
        $import_content = file_get_contents($_FILES['pdk_import_file']['tmp_name']);
        $import_data = json_decode($import_content, true);
        if (is_array($import_data)) {
            foreach (pdk_get_all_plugin_options() as $option) {
                if (array_key_exists($option, $import_data)) {
                    update_option($option, $import_data[$option]);
                }
            }
            echo '<div class="notice notice-success is-dismissible"><p>Data byla úspěšně importována.</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Chyba: neplatný soubor nebo formát JSON.</p></div>';
        }
    }

    ?>
    <div class="wrap">
        <h1>Import / Export nastavení</h1>
        <h2>Exportovat data</h2>
        <form method="post">
            <?php wp_nonce_field('pdk_export', 'pdk_export_nonce'); ?>
            <input type="submit" class="button button-primary" value="Stáhnout zálohu (JSON)">
        </form>
        <hr>
        <h2>Importovat data</h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('pdk_import', 'pdk_import_nonce'); ?>
            <input type="file" name="pdk_import_file" accept=".json" required>
            <input type="submit" class="button button-primary" value="Nahrát a obnovit">
        </form>
        <p><small>POZOR: Importem přepíšeš všechna stávající nastavení pluginu těmi ze souboru!</small></p>
    </div>
    <?php
}