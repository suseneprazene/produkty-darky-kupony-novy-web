<?php
/*
* Součást sjednoceného pluginu: Pravidla slev podle role/e-mailu
* (původní plugin: Flexible Role & Email Domain Pricing Rules for WooCommerce)
*/

if (!defined('ABSPATH')) exit;


// Helper: bezpečné číslo
function pdk_pricing_safe_float($val) {
    if (is_array($val) || is_object($val)) return 0;
    if (is_string($val)) $val = str_replace(',', '.', $val);
    if ($val === '' || $val === null) return 0;
    return is_numeric($val) ? floatval($val) : 0;
}

function pdk_pricing_safe_array($maybe) {
    return (is_array($maybe) ? $maybe : []);
}

// --- ADMIN skripty a stránka ---
add_action('admin_enqueue_scripts', function($hook){
    if (strpos($hook, 'pdk-pricing') !== false) {
        wp_enqueue_script('jquery');
        wp_enqueue_script('select2');
        wp_enqueue_style('select2');
        wp_enqueue_script('pdk-pricing-admin-js', plugins_url('../assets/role-pricing-rules-admin.js', __FILE__), ['jquery','select2'], '2.9.3', true);
    }
});

// === ADMIN PAGE ===
function pdk_pricing_rules_page() {
    if (!current_user_can('manage_woocommerce')) return;

    $rules = get_option('pdk_pricing_rules', '[]');
if (isset($_POST['pdk_pricing_rules_data'])) {
    if (check_admin_referer('pdk_pricing_save_rules')) {
        update_option('pdk_pricing_rules', stripslashes_deep($_POST['pdk_pricing_rules_data']));
        wp_redirect(admin_url('admin.php?page=pdk-pricing&saved=1'));
        exit;
    } else {
        echo '<div class="error"><p>Chyba zabezpečení: pravidla nebyla uložena.</p></div>';
    }
}

    if (!empty($_GET['saved'])) {
        echo '<div class="updated"><p>Pravidla byla uložena.</p></div>';
    }

    $all_roles = wp_roles()->roles;
    $products = get_posts(['post_type' => 'product', 'numberposts' => 500, 'post_status' => 'publish']);
    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    ?>
    <div class="wrap">
        <h2>Pravidla slev (role/e-mail doména)</h2>
        <form method="post" id="rpr_rules_form">
            <?php wp_nonce_field('pdk_pricing_save_rules'); ?>
            <div id="rpr_rules_list"></div>
            <button type="button" class="button" id="rpr_add_rule_btn">+ Přidat pravidlo</button>
            <br><br>
            <input type="hidden" name="pdk_pricing_rules_data" id="rpr_rules_data" value="">
            <input type="submit" class="button-primary" value="Uložit pravidla">
        </form>
        <style>
            .rpr-rule {border:1px solid #ccd0d4; padding:15px; margin-bottom:15px; background:#f9f9f9;}
            .rpr-rule .delete-btn {float:right; color:red; cursor:pointer;}
            .rpr-rule .min-toggle {float:right; margin-right:10px; color: #666; font-size: 15px; cursor:pointer;}
            .rpr-rule-min {background:#f1f1f1; border:1px solid #ccd0d4; padding:8px; margin-bottom:7px;}
            .rpr-multiselect {width:100%;}
            .rpr-off {opacity:0.5;}
            .rpr-row {margin-bottom:8px; display:flex; align-items:flex-start;}
            .rpr-label {display:inline-block; min-width:140px; padding-top:7px; font-weight:normal;}
            .rpr-row select {max-width: 420px; min-width:220px;}
        </style>
        <script>
window.rpr_data = {
    roles: <?php echo json_encode(array_keys($all_roles)); ?>,
    products: <?php echo json_encode(array_map(function($p){return ['id'=>$p->ID,'name'=>$p->post_title];}, $products)); ?>,
    categories: <?php echo json_encode(array_map(function($c){return ['id'=>$c->term_id,'name'=>$c->name];}, $categories)); ?>,
    rules: <?php echo $rules ? $rules : '[]'; ?>
};
        </script>
    </div>
    <?php
}

function pdk_pricing_rule_applies($product_id, $categories, $rule) {
    $include_products = isset($rule['include_products']) ? $rule['include_products'] : [];
    $exclude_products = isset($rule['exclude_products']) ? $rule['exclude_products'] : [];
    $include_categories = isset($rule['include_categories']) ? $rule['include_categories'] : [];
    $exclude_categories = isset($rule['exclude_categories']) ? $rule['exclude_categories'] : [];

       // Vyloučení produktů má vždy vyšší prioritu
    if (in_array($product_id, $exclude_products)) {
        return false;
    }
    if (!empty($exclude_categories) && array_intersect($exclude_categories, $categories)) {
        return false;
    }

    // Pokud nebyly zadány žádné produkty ani kategorie k zahrnutí, pravidlo platí pro všechny produkty
    $has_includes = !empty($include_products) || !empty($include_categories);
    if (!$has_includes) {
        return true;
    }

    // Validace zahrnutí produktů (pokud jsou definovány)
    $product_included = empty($include_products) || in_array($product_id, $include_products);

    // Validace zahrnutí kategorií (pokud jsou definovány)
    $category_included = empty($include_categories) || !empty(array_intersect($include_categories, $categories));

    // Pravidlo platí pouze, pokud produkt nebo jeho kategorie jsou zahrnuty a nejsou vyloučeny
    $applies = $product_included && $category_included;

    return $applies;
}

// --- APLIKACE PRAVIDEL NA KOŠÍK A CENY PRODUKTŮ ---
add_action('woocommerce_cart_calculate_fees', function($cart) {
    $rules = json_decode(get_option('pdk_pricing_rules', '[]'), true);
    $user = is_user_logged_in() ? wp_get_current_user() : false;

    if (!$user || !is_array($rules)) return;

    foreach ($rules as $rule) {
        if (empty($rule['active'])) continue;

        $applies = false;

        if (isset($rule['target_type']) && $rule['target_type'] === 'role' && isset($rule['role']) && in_array($rule['role'], $user->roles)) {
            $applies = true;
        }

        if (isset($rule['target_type']) && $rule['target_type'] === 'email_domain' && isset($user->user_email) && isset($rule['domain']) && strtolower(substr(strrchr($user->user_email, "@"), 1)) === strtolower($rule['domain'])) {
            $applies = true;
        }

        if (!$applies) continue;

        foreach ($cart->get_cart() as $item) {
            $product_id = $item['product_id'];
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);

            if (!pdk_pricing_rule_applies($product_id, $categories, $rule)) continue;

            $discount_value = pdk_pricing_safe_float(isset($rule['value']) ? $rule['value'] : 0);
            if ($rule['discount_type'] === 'cart_fixed' && $discount_value > 0) {
                $cart->add_fee($rule['name'] ? esc_html($rule['name']) : 'Sleva podle pravidla', -1 * $discount_value);
            }
        }
    }
}, 20, 1);

// --- OPRAVENÉ FILTRY NA CENU PRODUKTU ---
function pdk_pricing_apply_price_rules($price, $product) {
    $rules = json_decode(get_option('pdk_pricing_rules', '[]'), true);
    $user = is_user_logged_in() ? wp_get_current_user() : false;

    if (!$user || !is_array($rules)) {
        return $price;
    }

    $email = $user->user_email;
    $product_id = $product->get_id();
    $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    $appliedRules = [];

     foreach ($rules as $rule) {
        // Pravidlo musí být aktivní
        if (empty($rule['active'])) {
            continue;
        }

        $applies = false;

        // Kontrola podle role uživatele
        if (isset($rule['target_type']) && $rule['target_type'] === 'role' && isset($rule['role']) && in_array($rule['role'], $user->roles)) {
            $applies = true;
        }

        // Kontrola podle e-mailové domény
        if (isset($rule['target_type']) && $rule['target_type'] === 'email_domain' && $email && isset($rule['domain']) && strtolower(substr(strrchr($email, "@"), 1)) === strtolower($rule['domain'])) {
            $applies = true;
        }

        if (!$applies) {
            continue;
        }

        // Validace podle pravidel přímo pro varianty
        $include_products = $rule['include_products'] ?? [];
        $exclude_products = $rule['exclude_products'] ?? [];
        $include_categories = $rule['include_categories'] ?? [];
        $exclude_categories = $rule['exclude_categories'] ?? [];

        // Pokud je produkt explicitně vyloučen pokračujeme na další pravidlo
        if (in_array($product_id, $exclude_products)) {
            continue;
        }

        // Validace podle zahrnutých produktů nebo kategorií
        $product_is_included = empty($include_products) || in_array($product_id, $include_products);
        $category_is_included = empty($include_categories) || !empty(array_intersect($include_categories, $categories));
        
        if (!$product_is_included || !$category_is_included) {
            continue;
        }

        // Aplikace pravidla
        $appliedRules[] = $rule;
        $discount_value = floatval($rule['value']);
        if ($rule['discount_type'] === 'product_pct' && $discount_value > 0) {
            $price = floatval($price) * (1 - $discount_value / 100);
        } elseif ($rule['discount_type'] === 'product_fixed' && $discount_value > 0) {
            $price = min($price, $discount_value);
        }
    }

    if (empty($appliedRules)) {
    } else {
    }

    return $price;
}
// Registrace filtrů pro produkty a varianty
add_filter('woocommerce_product_get_price', 'pdk_pricing_apply_price_rules', 10, 2);
add_filter('woocommerce_product_variation_get_price', 'pdk_pricing_apply_price_rules', 10, 2);
add_filter('woocommerce_product_get_sale_price', 'pdk_pricing_apply_price_rules', 10, 2);
add_filter('woocommerce_product_variation_get_sale_price', 'pdk_pricing_apply_price_rules', 10, 2);

// --- VLASTNÍ TEXT SLEVY DLE ROLE ---
add_filter('woocommerce_get_price_html', function($price_html, $product) {
    if (!is_user_logged_in()) return $price_html;

    $user = wp_get_current_user();
    $rules = json_decode(get_option('pdk_pricing_rules', '[]'), true);
    $email = $user->user_email;

    $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'ids']);
    $custom_label = false;

    foreach ($rules as $rule) {
        if (empty($rule['active'])) continue;

        $applies = false;

        if (isset($rule['target_type']) && $rule['target_type'] === 'role' && isset($rule['role']) && in_array($rule['role'], $user->roles)) {
            $applies = true;
        }

        if (isset($rule['target_type']) && $rule['target_type'] === 'email_domain' && $email && isset($rule['domain']) && strtolower(substr(strrchr($email, "@"), 1)) === strtolower($rule['domain'])) {
            $applies = true;
        }

        if (!$applies) continue;

        if (!pdk_pricing_rule_applies($product->get_id(), $categories, $rule)) continue;

        // Kontrola, zda je aktivní zobrazení slevy u cenovky a má vlastní text
        if (!empty($rule['show_price_discount']) && !empty($rule['custom_discount_label'])) {
            $custom_label = $rule['custom_discount_label'];
            break;
        }
    }

    // Pokud je aktivní checkbox a existuje vlastní text, zobrazí ho pod cenou
    if ($custom_label) {
        $price_html .= sprintf(
            '<div style="font-size: 22px; font-weight: semi-bold; color: #8d7b35; margin-top: 5px; margin-bottom: 10px;">%s</div>',
            esc_html($custom_label)
        );
    }

    return $price_html;
}, 10, 2);

add_filter('woocommerce_sale_flash', function($html, $post, $product){
    if (!is_user_logged_in()) return $html;

    $user = wp_get_current_user();
    $rules = json_decode(get_option('pdk_pricing_rules', '[]'), true);
    $email = $user->user_email;
    $custom_label = false;

    if (is_array($rules)) {
        foreach ($rules as $rule) {
            if (empty($rule['active'])) continue;

            $applies = false;

            if (isset($rule['target_type']) && $rule['target_type'] == 'role' && isset($rule['role']) && in_array($rule['role'], $user->roles)) {
                $applies = true;
            }

            if (isset($rule['target_type']) && $rule['target_type'] == 'email_domain' && $email && isset($rule['domain']) 
                && strtolower(substr(strrchr($email, "@"), 1)) == strtolower($rule['domain'])) {
                $applies = true;
            }

            if (!$applies) continue;

            $include_products = isset($rule['include_products']) ? $rule['include_products'] : [];
            $exclude_products = isset($rule['exclude_products']) ? $rule['exclude_products'] : [];
            $product_id = $product->get_id();

            if (in_array($product_id, $exclude_products)) continue;
            if (!empty($include_products) && !in_array($product_id, $include_products)) continue;

            // Kontrola, zda je aktivní zobrazení badge a má vlastní text
            if (!empty($rule['show_badge_discount']) && !empty($rule['custom_discount_label'])) {
                $custom_label = $rule['custom_discount_label'];
                break;
            }
        }
    }

    // Pokud je aktivní checkbox a existuje vlastní text, zobrazí ho v badge
    if ($custom_label) {
        return '<span class="onsale">' . esc_html($custom_label) . '</span>';
    }

    return $html;
}, 10, 3);

add_filter('woocommerce_available_variation', function($variation_data, $product, $variation) {
    $rules = json_decode(get_option('pdk_pricing_rules', '[]'), true);
    $user = is_user_logged_in() ? wp_get_current_user() : false;

    if (!$user || !is_array($rules)) {
        return $variation_data;
    }

    $variation_id = $variation->get_id();
    $custom_label = false;

    foreach ($rules as $rule) {
        if (empty($rule['active'])) continue;

        $applies = false;

        if (isset($rule['target_type']) && $rule['target_type'] === 'role' && isset($rule['role']) && in_array($rule['role'], $user->roles)) {
            $applies = true;
        }

        if (isset($rule['target_type']) && $rule['target_type'] === 'email_domain' && isset($rule['domain']) 
            && strtolower(substr(strrchr($user->user_email, "@"), 1)) === strtolower($rule['domain'])) {
            $applies = true;
        }

        if (!$applies) continue;

        $include_products = isset($rule['include_products']) ? $rule['include_products'] : [];
        $exclude_products = isset($rule['exclude_products']) ? $rule['exclude_products'] : [];
        $include_categories = isset($rule['include_categories']) ? $rule['include_categories'] : [];
        $exclude_categories = isset($rule['exclude_categories']) ? $rule['exclude_categories'] : [];
        $categories = wp_get_post_terms($variation_id, 'product_cat', ['fields' => 'ids']);

        if (in_array($variation_id, $exclude_products)) continue;
        if (!empty($include_products) && !in_array($variation_id, $include_products)) continue;
        if (!empty($exclude_categories) && array_intersect($exclude_categories, $categories)) continue;
        if (!empty($include_categories) && !array_intersect($include_categories, $categories)) continue;

        // Kontrola, zda je aktivní zobrazení slevy u cenovky (pro varianty)
        if (!empty($rule['show_price_discount']) && !empty($rule['custom_discount_label'])) {
            $custom_label = $rule['custom_discount_label'];
            break;
        }
    }

    if ($custom_label) {
        $variation_data['discount_label'] = $custom_label;
    }

    return $variation_data;
}, 10, 3);

add_action('wp_ajax_get_active_rules', function() {
    // Načíst všechny pravidla z databáze
    $rules = json_decode(get_option('pdk_pricing_rules', '[]'), true);

    // Filtrovat pravidla
    if ($rules && is_array($rules)) {
        $activeRules = array_filter($rules, function($rule) {
            return !empty($rule['active']);
        });

        // Vrátit aktivní pravidla jako JSON
        wp_send_json($activeRules);
    } else {
        wp_send_json([]); // V případě problémů vrátí prázdné pole
    }
});