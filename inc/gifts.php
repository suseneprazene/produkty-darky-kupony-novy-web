<?php
/*
* Součást sjednoceného pluginu: Dárky zdarma při splnění podmínek
*/
if (!defined('ABSPATH')) exit;

// ŽÁDNÉ add_action('admin_menu', ...) zde! Pouze kód stránky a logika:

define('PDK_GIFT_OPTION', 'pdk_gift_rules');

// === ADMIN PAGE ===
function pdk_gifts_page() {
    $rules = get_option(PDK_GIFT_OPTION, []);
    $roles = [];
    foreach (wp_roles()->roles as $key => $role) $roles[] = $key;
    ?>
    <div class="wrap">
        <h1>Dárky zdarma – nastavení pravidel</h1>
        <form method="post" id="pdk_gift_rules_form">
            <?php wp_nonce_field('pdk_gift_save_rules', 'pdk_gift_rules_nonce'); ?>
            <input type="hidden" name="pdk_gift_rules_data" id="pdk_gift_rules_data" value='<?php echo esc_attr(json_encode($rules)); ?>'>
            <div id="wcgift-rules-list"></div>
            <p><button type="button" id="wcgift-add-rule" class="button">Přidat pravidlo</button></p>
            <p><input type="submit" class="button button-primary" value="Uložit pravidla"></p>
        </form>
    </div>
    <script>
    window.wcgift_rules = <?php echo json_encode($rules); ?>;
    window.wcgift_roles = <?php echo json_encode($roles); ?>;
    </script>
    <?php
    // Enqueue admin JS/CSS for gifts
    wp_enqueue_script('select2', '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
    wp_enqueue_style('select2', '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('pdk-gift-admin-js', plugins_url('../assets/admin-wcgift.js', __FILE__), ['jquery', 'select2'], null, true);
    wp_enqueue_style('pdk-gift-admin-css', plugins_url('../assets/admin-wcgift.css', __FILE__));
}

// === ULOŽENÍ PRAVIDEL ===
add_action('admin_init', function() {
    if (isset($_POST['pdk_gift_rules_nonce']) && wp_verify_nonce($_POST['pdk_gift_rules_nonce'], 'pdk_gift_save_rules')) {
        if (isset($_POST['pdk_gift_rules_data'])) {
            $data = json_decode(stripslashes($_POST['pdk_gift_rules_data']), true);
            update_option(PDK_GIFT_OPTION, $data);
            add_action('admin_notices', function(){
                echo '<div class="notice notice-success is-dismissible"><p>Pravidla byla uložena.</p></div>';
            });
        }
    }
});

// === ULOŽENÍ PRAVIDEL ===
add_action('admin_init', function() {
    if (isset($_POST['pdk_gift_rules_nonce']) && wp_verify_nonce($_POST['pdk_gift_rules_nonce'], 'pdk_gift_save_rules')) {
        if (isset($_POST['pdk_gift_rules_data'])) {
            $data = json_decode(stripslashes($_POST['pdk_gift_rules_data']), true);
            update_option(PDK_GIFT_OPTION, $data);
            add_action('admin_notices', function(){
                echo '<div class="notice notice-success is-dismissible"><p>Pravidla byla uložena.</p></div>';
            });
        }
    }
});

// === ULOŽENÍ PRAVIDEL ===
add_action('admin_init', function() {
    if (isset($_POST['pdk_gift_rules_nonce']) && wp_verify_nonce($_POST['pdk_gift_rules_nonce'], 'pdk_gift_save_rules')) {
        if (isset($_POST['pdk_gift_rules_data'])) {
            $data = json_decode(stripslashes($_POST['pdk_gift_rules_data']), true);
            update_option(PDK_GIFT_OPTION, $data);
            add_action('admin_notices', function(){
                echo '<div class="notice notice-success is-dismissible"><p>Pravidla byla uložena.</p></div>';
            });
        }
    }
});

// === AJAX: Vyhledávání produktů a kategorií pro select2 ===
add_action('wp_ajax_wcgift_search_products', function(){
    $results = [];
    $term = sanitize_text_field($_GET['term'] ?? '');
    $ids = array_filter(array_map('absint', explode(',', $_GET['ids'] ?? '')));
    if (!empty($ids)) {
        foreach ($ids as $pid) {
            $prod = wc_get_product($pid);
            if (!$prod) continue;
            $label = $prod->get_name();
            if ($prod->is_type('variation')) {
                $parent = wc_get_product($prod->get_parent_id());
                if ($parent) {
                    $attr_str = wc_get_formatted_variation($prod, true, false, false);
                    $label = $parent->get_name() . ' – ' . $attr_str;
                }
            }
            $results[] = ['id' => $pid, 'text' => $label];
        }
    } elseif ($term) {
        $query = new WP_Query([
            'post_type' => ['product', 'product_variation'],
            'posts_per_page' => 20,
            's' => $term,
            'post_status' => ['publish', 'private'],
        ]);
        foreach ($query->posts as $p) {
            $prod = wc_get_product($p->ID);
            if (!$prod) continue;
            $label = $prod->get_name();
            if ($prod->is_type('variation')) {
                $parent = wc_get_product($prod->get_parent_id());
                if ($parent) {
                    $attr_str = wc_get_formatted_variation($prod, true, false, false);
                    $label = $parent->get_name() . ' – ' . $attr_str;
                }
            }
            $results[] = ['id' => $prod->get_id(), 'text' => $label];
        }
    }
    wp_send_json(['results' => $results]);
});
add_action('wp_ajax_wcgift_search_categories', function(){
    $results = [];
    $term = sanitize_text_field($_GET['term'] ?? '');
    $ids = array_filter(array_map('absint', explode(',', $_GET['ids'] ?? '')));
    if (!empty($ids)) {
        foreach ($ids as $cid) {
            $cat = get_term($cid, 'product_cat');
            if ($cat && !is_wp_error($cat)) $results[] = ['id' => $cid, 'text' => $cat->name];
        }
    } elseif ($term) {
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'name__like' => $term,
            'number' => 20,
        ]);
        foreach ($terms as $t) {
            $results[] = ['id' => $t->term_id, 'text' => $t->name];
        }
    }
    wp_send_json(['results' => $results]);
});

// === FRONTEND: Výběr dárku v košíku ===
add_action('woocommerce_after_cart_table', function(){
    $rules = get_option(PDK_GIFT_OPTION, []);
    if (empty($rules)) return;
    $cart = WC()->cart->get_cart();

    // Vypočítat, kolik dárků už je v košíku pro každý rule
    $gift_counts = [];
    foreach ($cart as $item) {
        if (!empty($item['wcgift_gift']) && isset($item['wcgift_rule'])) {
            $ridx = $item['wcgift_rule'];
            if (!isset($gift_counts[$ridx])) $gift_counts[$ridx] = 0;
            $gift_counts[$ridx] += (int)$item['quantity'];
        }
    }

    // Získat subtotal bez dopravy a bez dárků
    $cart_items_subtotal = 0;
    $cart_products = [];
    $cart_categories = [];
    foreach ($cart as $item) {
        if (empty($item['wcgift_gift'])) {
            $cart_items_subtotal += $item['line_subtotal'];
            if (isset($item['product_id'])) {
                $cart_products[] = $item['product_id'];
                $cats = wp_get_post_terms($item['product_id'], 'product_cat', ['fields'=>'ids']);
                if ($cats) $cart_categories = array_merge($cart_categories, $cats);
            }
        }
    }
    $cart_products = array_unique($cart_products);
    $cart_categories = array_unique($cart_categories);

    // Info o uživateli
    $user = wp_get_current_user();

    // Počet předchozích dokončených objednávek (pro první nákup)
    $has_orders = false;
    if ($user && $user->ID) {
        $args = [
            'customer_id' => $user->ID,
            'post_status' => ['wc-completed', 'wc-processing', 'wc-on-hold'],
            'return' => 'ids',
            'posts_per_page' => 1,
        ];
        $orders = wc_get_orders($args);
        $has_orders = !empty($orders);
    }

    // Je použit kupón?
    $coupons_used = false;
    $applied_coupons = WC()->cart->get_applied_coupons();
    if (!empty($applied_coupons)) $coupons_used = true;

    // Uživatelské role
    $user_roles = [];
    if ($user && !empty($user->roles)) $user_roles = $user->roles;

    // Aktuální datum
    $now = current_time('Y-m-d');

    // Vykresli výběr dárku podle pravidel
    foreach ($rules as $ridx => $rule) {
        if (empty($rule['active'])) continue;
        if (empty($rule['gifts']) || count($rule['gifts']) < 1) continue;

        // ---- Podmínka: PLATNOST DATUM ----
        if (!empty($rule['date_from']) && $now < $rule['date_from']) continue;
        if (!empty($rule['date_to']) && $now > $rule['date_to']) continue;

        // ---- Podmínka: MINIMÁLNÍ HODNOTA KOŠÍKU ----
        if (!empty($rule['min_total_active']) && isset($rule['min_total']) && is_numeric($rule['min_total'])) {
            $min_total = floatval($rule['min_total']);
            if ($cart_items_subtotal < $min_total) continue;
        }

        // ---- Podmínka: REQUIRED PRODUCTS ----
        if (!empty($rule['required_products_active']) && !empty($rule['required_products']) && is_array($rule['required_products'])) {
            $found = false;
            foreach ($rule['required_products'] as $pid) {
                if (in_array($pid, $cart_products)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) continue;
        }

        // ---- Podmínka: REQUIRED CATEGORIES ----
        if (!empty($rule['required_categories_active']) && !empty($rule['required_categories']) && is_array($rule['required_categories'])) {
            $found = false;
            foreach ($rule['required_categories'] as $catid) {
                if (in_array($catid, $cart_categories)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) continue;
        }

        // ---- Podmínka: EXCLUDED PRODUCTS ----
        if (!empty($rule['excluded_products_active']) && !empty($rule['excluded_products']) && is_array($rule['excluded_products'])) {
            foreach ($rule['excluded_products'] as $pid) {
                if (in_array($pid, $cart_products)) continue 2; // přeskočí celé pravidlo
            }
        }

        // ---- Podmínka: EXCLUDED CATEGORIES ----
        if (!empty($rule['excluded_categories_active']) && !empty($rule['excluded_categories']) && is_array($rule['excluded_categories'])) {
            foreach ($rule['excluded_categories'] as $catid) {
                if (in_array($catid, $cart_categories)) continue 2; // přeskočí celé pravidlo
            }
        }

        // ---- Podmínka: POUZE PRO PRVNÍ NÁKUP ----
        if (!empty($rule['first_purchase_only']) && $has_orders) continue;

        // ---- Podmínka: ROLE ----
        if (!empty($rule['roles']) && is_array($rule['roles'])) {
            $has_role = false;
            foreach ($rule['roles'] as $role) {
                if (in_array($role, $user_roles)) {
                    $has_role = true;
                    break;
                }
            }
            if (!$has_role) continue;
        }

        // ---- Podmínka: NEVZTAHUJE SE POKUD JE KUPÓN ----
        if (!empty($rule['exclude_if_coupon']) && $coupons_used) continue;

        // Podmínka max_gifts
        $max_gifts = isset($rule['max_gifts']) && (int)$rule['max_gifts'] > 0 ? (int)$rule['max_gifts'] : 1;
        $gift_count = isset($gift_counts[$ridx]) ? $gift_counts[$ridx] : 0;
        $can_add_gift = $gift_count < $max_gifts;

        // Layout + CSS
        echo '<style>
        .wcgift-gift-list {display:flex;flex-direction:column;gap:14px;margin:1em 0 0 0;}
        .wcgift-choice-row {display:flex;align-items:center;gap:1em;}
        .wcgift-choice-row input[type=radio] {margin:0 8px 0 0;}
        .wcgift-choice-row .product-thumbnail img {
            width:48px !important;height:48px !important;object-fit:contain;border-radius:4px;border:1px solid #eee;background:#fff;margin:0;padding:0;box-shadow:none;
        }
        .wcgift-choice-row .product-name a {color:#222;text-decoration:none;font-weight:bold;font-size:1.05em;cursor:pointer;}
        .wcgift-choice-row .product-name a:hover {text-decoration:underline;color:#0073aa;}
        .wcgift-choice-row .product-price {margin-left:auto;color:#008000;font-size:1em;font-weight:bold;}
        .cart-gift-row {padding:1.2em 1em 1em 1em;}
        </style>';

        echo '<div class="cart-gift-row" style="background:#fff;color:#222;margin-bottom:2em;border-radius:7px;">';
        echo '<strong style="font-size:1.04em;">'.esc_html($rule['note'] ?? 'Vyberte si dárek zdarma:').'</strong>';
        echo '<div class="wcgift-gift-list">';
        foreach ($rule['gifts'] as $pid) {
            $prod = wc_get_product($pid);
            if (!$prod) continue;
            $prod_name = $prod->get_name();
            $permalink = get_permalink($pid);
            $thumb = $prod->get_image('woocommerce_thumbnail', ['style'=>'width:48px;height:48px;object-fit:contain;display:inline-block;vertical-align:middle;']);
            echo '<div class="wcgift-choice-row">';
            // Rádio - vedle obrázku!
            echo '<input type="radio" name="wcgift_choice_'.$ridx.'" value="'.$pid.'" id="wcgift_radio_'.$ridx.'_'.$pid.'" style="margin-right:8px;"'.($can_add_gift?'':' disabled').'>'; 
            echo '<span class="product-thumbnail">'.$thumb.'</span>';
            echo '<span class="product-name"><a href="'.esc_url($permalink).'" class="wcgift-modal-link" data-product_id="'.$pid.'">'.esc_html($prod_name).'</a></span>';
            echo '<span class="product-price">'.__('Zdarma','woocommerce').'</span>';
            echo '</div>';
        }
        echo '</div>';
        // Tlačítko
        echo '<button type="button" class="button wcgift-add-gift-to-cart" data-rule="'.$ridx.'" '.($can_add_gift?'':'disabled').' style="margin-top:7px;font-size:0.95em;padding:6px 16px;">Přidat dárek do košíku</button>';
        // Info o limitu
        if (!$can_add_gift) echo '<div style="color:#c00;font-size:0.96em;margin-top:0.7em;">Maximální počet dárků vyčerpán.</div>';
        echo '</div>';
    }
});

// === CENA DÁRKU NA 0 ===
add_filter('woocommerce_get_cart_item_from_session', function($item, $values, $key){
    if (!empty($item['wcgift_gift'])) $item['data']->set_price(0);
    return $item;
}, 99, 3);
add_action('woocommerce_before_calculate_totals', function($cart){
    foreach ($cart->get_cart() as $cart_item) {
        if (!empty($cart_item['wcgift_gift'])) $cart_item['data']->set_price(0);
    }
}, 99);

// === AJAX: VLOŽENÍ DÁRKU DO KOŠÍKU ===
add_action('wp_ajax_wcgift_choose_gift', function(){
    $pid = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $rule_idx = isset($_POST['rule_idx']) ? intval($_POST['rule_idx']) : 0;
    if (!$pid) wp_die('Chybí produkt.');
    WC()->cart->add_to_cart($pid, 1, 0, [], [
        'wcgift_gift' => true,
        'wcgift_rule' => $rule_idx
    ]);
    wp_die('OK');
});
add_action('wp_ajax_nopriv_wcgift_choose_gift', function(){
    $pid = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $rule_idx = isset($_POST['rule_idx']) ? intval($_POST['rule_idx']) : 0;
    if (!$pid) wp_die('Chybí produkt.');
    WC()->cart->add_to_cart($pid, 1, 0, [], [
        'wcgift_gift' => true,
        'wcgift_rule' => $rule_idx
    ]);
    wp_die('OK');
});

// === AJAX: NÁHLED PRODUKTU ===
add_action('wp_ajax_wcgift_quickview', function(){
    $pid = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if (!$pid) wp_die();
    $prod = wc_get_product($pid);
    if (!$prod) wp_die();
    $desc = $prod->get_description();
    if ($prod->is_type('variation') && empty($desc)) {
        $parent = wc_get_product($prod->get_parent_id());
        if ($parent) $desc = $parent->get_description();
    }
    echo '<h3 style="margin-top:0;">'.$prod->get_name().'</h3>';
    echo $prod->get_image('medium');
    echo '<div style="margin:1em 0;">'.($desc ?: __('(Produkt nemá popis)','woocommerce')).'</div>';
    wp_die();
});
add_action('wp_ajax_nopriv_wcgift_quickview', function(){
    $pid = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if (!$pid) wp_die();
    $prod = wc_get_product($pid);
    if (!$prod) wp_die();
    $desc = $prod->get_description();
    if ($prod->is_type('variation') && empty($desc)) {
        $parent = wc_get_product($prod->get_parent_id());
        if ($parent) $desc = $parent->get_description();
    }
    echo '<h3 style="margin-top:0;">'.$prod->get_name().'</h3>';
    echo $prod->get_image('medium');
    echo '<div style="margin:1em 0;">'.($desc ?: __('(Produkt nemá popis)','woocommerce')).'</div>';
    wp_die();
});

// === MODAL HTML ===
add_action('wp_footer', function(){
    ?>
    <div id="wcgift-modal-bg"
         style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.65);z-index:9999;">
        <div id="wcgift-modal-content"
             style="background:#fff;max-width:480px;margin:6vh auto;padding:2em 1em;box-shadow:0 6px 32px #0006;border-radius:10px;position:relative;">
            <!-- AJAX obsah zde -->
        </div>
    </div>
    <?php
});

// === FRONTEND SCRIPT ===
add_action('wp_enqueue_scripts', function() {
    if (is_cart() || is_checkout()) {
        wp_enqueue_script('pdk-gift-frontend', plugins_url('../assets/frontend-wcgift.js', __FILE__), ['jquery'], '1.0', true);
        wp_localize_script('pdk-gift-frontend', 'wcgift_ajax', [
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }
});

// Zákaz editace množství u dárků zdarma v košíku
add_filter('woocommerce_cart_item_quantity', function($quantity, $cart_item_key, $cart_item) {
    if (!empty($cart_item['wcgift_gift'])) {
        // Pokud je produkt označen jako dárek, nahraďte pole pro editaci množství statickým textem
        $quantity = $cart_item['quantity'];
    }
    return $quantity;
}, 10, 3);

// Kontrola pravidel pro dárky při aktualizaci košíku
add_action('woocommerce_cart_updated', function() {
    $rules = get_option(PDK_GIFT_OPTION, []);
    if (empty($rules)) return;

    $cart = WC()->cart->get_cart();
    $cart_items_subtotal = 0;
    $cart_products = [];
    $cart_categories = [];

    // Vypočítat subtotal (bez dárků) a strukturu košíku
    foreach ($cart as $cart_item_key => $cart_item) {
        if (empty($cart_item['wcgift_gift'])) {
            $cart_items_subtotal += $cart_item['line_subtotal'];
            if (isset($cart_item['product_id'])) {
                $cart_products[] = $cart_item['product_id'];
                $cats = wp_get_post_terms($cart_item['product_id'], 'product_cat', ['fields' => 'ids']);
                if ($cats) $cart_categories = array_merge($cart_categories, $cats);
            }
        }
    }
    $cart_products = array_unique($cart_products);
    $cart_categories = array_unique($cart_categories);

    // Odebrání neplatných dárků z košíku
    foreach ($cart as $cart_item_key => $cart_item) {
        if (!empty($cart_item['wcgift_gift']) && isset($cart_item['wcgift_rule'])) {
            $rule_idx = $cart_item['wcgift_rule'];
            $rule = $rules[$rule_idx] ?? null;

            if (empty($rule) || empty($rule['active'])) {
                // Pravidlo neexistuje nebo není aktivní
                WC()->cart->remove_cart_item($cart_item_key);
                continue;
            }

            // Kontrola minimální útraty
            if (!empty($rule['min_total_active']) && isset($rule['min_total']) && is_numeric($rule['min_total'])) {
                $min_total = floatval($rule['min_total']);
                if ($cart_items_subtotal < $min_total) {
                    WC()->cart->remove_cart_item($cart_item_key);
                    continue;
                }
            }

            // Další podmínky (můžete doplnit podle potřeby)
            // Přídání podobného typu kontroly pro produkty, kategorie, kupóny, apod., podle vašich pravidel
        }
    }
}, 10);

// Zobrazení upozornění pod košíkem, pokud zákazník nesplňuje minimální útratu pro dárek zdarma
add_action('woocommerce_after_cart_table', function() {
    $rules = get_option(PDK_GIFT_OPTION, []);
    if (empty($rules)) return;

    $cart = WC()->cart->get_cart();
    $cart_items_subtotal = 0;

    // Zkontrolujeme, zda je v košíku alespoň jeden produkt
    if (empty($cart)) return;

    // Vypočítat subtotal (bez dárků)
    foreach ($cart as $cart_item) {
        if (empty($cart_item['wcgift_gift'])) {
            $cart_items_subtotal += $cart_item['line_subtotal'];
        }
    }

    $min_total = null;
    foreach ($rules as $rule) {
        if (!empty($rule['active']) && !empty($rule['min_total_active']) && isset($rule['min_total']) && is_numeric($rule['min_total'])) {
            $min_total = floatval($rule['min_total']);
            if ($cart_items_subtotal >= $min_total) {
                // Zákazník splňuje podmínku, netřeba zobrazovat oznámení
                return;
            }
        }
    }

    if (!is_null($min_total)) {
        // Zobrazíme upozornění
        echo '<div style="margin-top:1.5em; padding:1em; border:1px dashed #ccc; border-radius:8px; background:#f9f9f9; text-align:center; font-size:1.1em;line-height:1.6em;">';
        echo '<span style="font-size:2em; display:block;">🎁</span>'; // Symbol dárku
        echo 'Ještě zbystři! Čeká tu na Tebe ještě <strong>zdarma dárek ke každé objednávce</strong> nad <strong>' . wc_price($min_total) . '</strong>.';
        echo '</div>';
    }
});