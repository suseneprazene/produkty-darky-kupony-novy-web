<?php
/*
* Automaticky generované kupóny – sjednocená stránka: nahoře seznam kuponů, pod tím pravidla generování.
*/

if (!defined('ABSPATH')) exit;

// === HLAVNÍ ADMIN PAGE: seznam kuponů + pravidla ===
function pdk_coupons_main_page() {
    echo '<div class="wrap">';
    // Seznam aktivních/neaktivních kuponů nahoře
    pdk_coupons_list_html();
    echo '<hr style="margin:2em 0">';
    // Formulář pravidel generování pod tím
    pdk_coupons_settings_html();
    echo '</div>';
}

// === SEZNAM KUPÓNŮ ===
function pdk_coupons_list_html() {
    $args = [
        'post_type' => 'shop_coupon',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ];
    $coupons = get_posts($args);
    $now = time();
    $active = [];
    $inactive = [];
    foreach ($coupons as $coupon) {
        $expiry = (int)get_post_meta($coupon->ID, 'date_expires', true);
        $order_id = get_post_meta($coupon->ID, 'pdk_coupons_order_id', true);
        $rule_idx = get_post_meta($coupon->ID, 'pdk_coupons_rule_idx', true);
        $coupon_obj = new WC_Coupon($coupon->ID);
        $used_count = $coupon_obj->get_usage_count();
        $limit = $coupon_obj->get_usage_limit();
        $is_used = ($limit && $used_count >= $limit);
        $row = [
            'code' => $coupon->post_title,
            'edit_url' => admin_url('post.php?post=' . $coupon->ID . '&action=edit'),
            'order_id' => $order_id,
            'order_url' => $order_id ? admin_url('post.php?post=' . $order_id . '&action=edit') : '',
            'expiry' => $expiry ? date_i18n('d.m.Y', $expiry) : __('Bez expirace', 'produkty-darky-kupony'),
            'used' => $is_used ? __('Ano', 'produkty-darky-kupony') : __('Ne', 'produkty-darky-kupony'),
            'rule' => $rule_idx !== '' ? intval($rule_idx)+1 : '-'
        ];
        if ((!$expiry || $expiry >= $now) && !$is_used) {
            $active[] = $row;
        } else {
            $inactive[] = $row;
        }
    }
    echo '<h1>' . esc_html__('Seznam vygenerovaných kupónů', 'produkty-darky-kupony') . '</h1>';

    // Aktivní
    echo '<h2>' . esc_html__('Aktivní kupóny', 'produkty-darky-kupony') . '</h2>';
    if (count($active)) {
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Kód kupónu', 'produkty-darky-kupony') . '</th>';
        echo '<th>' . esc_html__('Objednávka #', 'produkty-darky-kupony') . '</th>';
        echo '<th>' . esc_html__('Pravidlo #', 'produkty-darky-kupony') . '</th>';
        echo '<th>' . esc_html__('Platnost do', 'produkty-darky-kupony') . '</th>';
        echo '<th>' . esc_html__('Uplatněn', 'produkty-darky-kupony') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($active as $row) {
            echo '<tr>';
            echo '<td><a href="' . esc_url($row['edit_url']) . '">' . esc_html($row['code']) . '</a></td>';
            echo '<td>' . ($row['order_id'] ? '<a href="' . esc_url($row['order_url']) . '">' . esc_html($row['order_id']) . '</a>' : '-') . '</td>';
            echo '<td>' . esc_html($row['rule']) . '</td>';
            echo '<td>' . esc_html($row['expiry']) . '</td>';
            echo '<td>' . esc_html($row['used']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>' . esc_html__('Žádné aktivní kupóny.', 'produkty-darky-kupony') . '</p>';
    }

    // Neaktivní
    echo '<h2 style="margin-top:2em;">' . esc_html__('Neaktivní kupóny', 'produkty-darky-kupony') . '</h2>';
    if (count($inactive)) {
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Kód kupónu', 'produkty-darky-kupony') . '</th>';
        echo '<th>' . esc_html__('Objednávka #', 'produkty-darky-kupony') . '</th>';
        echo '<th>' . esc_html__('Pravidlo #', 'produkty-darky-kupony') . '</th>';
        echo '<th>' . esc_html__('Platnost do', 'produkty-darky-kupony') . '</th>';
        echo '<th>' . esc_html__('Uplatněn', 'produkty-darky-kupony') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($inactive as $row) {
            echo '<tr>';
            echo '<td><a href="' . esc_url($row['edit_url']) . '">' . esc_html($row['code']) . '</a></td>';
            echo '<td>' . ($row['order_id'] ? '<a href="' . esc_url($row['order_url']) . '">' . esc_html($row['order_id']) . '</a>' : '-') . '</td>';
            echo '<td>' . esc_html($row['rule']) . '</td>';
            echo '<td>' . esc_html($row['expiry']) . '</td>';
            echo '<td>' . esc_html($row['used']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>' . esc_html__('Žádné neaktivní kupóny.', 'produkty-darky-kupony') . '</p>';
    }
}

// === HTML pro nastavení pravidel ===
function pdk_coupons_settings_html() {
    $rules = get_option('pdk_coupons_rules', []);
    if (!is_array($rules) || count($rules) === 0) {
        $rules = [pdk_coupons_empty_rule()];
    }
    ?>
    <h1><?php _e('Nastavení automatických kupónů (více pravidel)', 'produkty-darky-kupony'); ?></h1>
    <form method="post" action="" id="pdk-coupons-rules-form">
        <?php wp_nonce_field('pdk_coupons_save_rules', 'pdk_coupons_rules_nonce'); ?>
        <div id="pdk-coupons-rules-list">
            <?php foreach ($rules as $i => $rule): ?>
                <?php pdk_coupons_rule_fields($i, $rule); ?>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button" id="pdk-coupons-add-rule"><?php _e('Přidat další pravidlo', 'produkty-darky-kupony'); ?></button>
        <br><br>
        <?php submit_button(__('Uložit všechna pravidla', 'produkty-darky-kupony')); ?>
    </form>
    <style>
        .pdk-coupons-rule-block { border:1px solid #ddd;padding:20px;margin-bottom:30px;background:#f9f9f9;position:relative;}
        .pdk-coupons-remove-rule { position:absolute;top:10px;right:10px;color:#a00;cursor:pointer;}
        .pdk-coupons-minimize-rule { position:absolute;top:10px;right:40px;font-size:20px;background:none;border:none;color:#666;cursor:pointer;}
        .pdk-coupons-rule-content { transition: all 0.2s; }
        .pdk-coupons-customer-note-tags { font-size:90%;color:#555;background:#eef;padding:7px 10px;margin-bottom:7px;display:inline-block;max-width:650px;}
        .pdk-coupons-product-search,
        .pdk-coupons-category-search { min-width:320px !important; width:380px !important; max-width:100% !important;}
        .pdk-coupons-rule-block fieldset,
        .pdk-coupons-rule-block table.form-table { max-width:700px !important;}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        let ruleIdx = <?php echo count($rules); ?>;
        document.getElementById('pdk-coupons-add-rule').addEventListener('click', function(e){
            e.preventDefault();
            let container = document.getElementById('pdk-coupons-rules-list');
            let xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function(){
                if (xhr.status === 200) {
                    container.insertAdjacentHTML('beforeend', xhr.responseText);
                    setTimeout(function(){
                        pdkCouponsInitSelectAjax(ruleIdx);
                        ruleIdx++;
                    }, 50);
                }
            };
            xhr.send('action=pdk_coupons_add_rule_block&idx=' + ruleIdx + '&_ajax_nonce=<?php echo wp_create_nonce("pdk_coupons_add_rule_block"); ?>');
        });

        document.getElementById('pdk-coupons-rules-list').addEventListener('click', function(e){
            if(e.target.classList.contains('pdk-coupons-remove-rule')){
                e.target.closest('.pdk-coupons-rule-block').remove();
            }
            if(e.target.classList.contains('pdk-coupons-minimize-rule')){
                const block = e.target.closest('.pdk-coupons-rule-block');
                const content = block.querySelector('.pdk-coupons-rule-content');
                if (content.style.display === 'none') {
                    content.style.display = '';
                    e.target.textContent = '–';
                } else {
                    content.style.display = 'none';
                    e.target.textContent = '+';
                }
            }
        });

        function pdkCouponsInitSelectAjax(idx) {
            jQuery('.pdk-coupons-product-search[data-idx="'+idx+'"]').select2({
                minimumInputLength: 3,
                allowClear: true,
                placeholder: '<?php echo esc_js(__('Vyberte produkty...', 'produkty-darky-kupony')); ?>',
                width: 'resolve'
                ,
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params){
                        return {
                            term: params.term,
                            action: 'woocommerce_json_search_products_and_variations',
                            security: '<?php echo esc_js(wp_create_nonce("search-products")); ?>'
                        };
                    },
                    processResults: function(data){
                        if (typeof data !== "object") return {results: []};
                        return {
                            results: jQuery.map(data, function(item, id){
                                return {id: id, text: item};
                            })
                        };
                    },
                    cache: true
                }
            });
            jQuery('.pdk-coupons-category-search[data-idx="'+idx+'"]').select2({
                minimumInputLength: 2,
                allowClear: true,
                placeholder: '<?php echo esc_js(__('Vyberte kategorie...', 'produkty-darky-kupony')); ?>',
                width: 'resolve'
                ,
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params){
                        return {
                            term: params.term,
                            action: 'woocommerce_json_search_product_categories',
                            security: '<?php echo esc_js(wp_create_nonce("search-categories")); ?>'
                        };
                    },
                    processResults: function(data){
                        if (typeof data !== "object") return {results: []};
                        return {
                            results: jQuery.map(data, function(item, id){
                                return {id: id, text: item};
                            })
                        };
                    },
                    cache: true
                }
            });
        }
        for(let i=0;i<ruleIdx;i++)pdkCouponsInitSelectAjax(i);
    });
    </script>
    <?php
}

// === AJAX: Přidání nového bloku pravidla ===
add_action('wp_ajax_pdk_coupons_add_rule_block', function(){
    check_ajax_referer('pdk_coupons_add_rule_block');
    $idx = intval($_POST['idx']);
    pdk_coupons_rule_fields($idx, pdk_coupons_empty_rule());
    if (defined('DOING_AJAX') && DOING_AJAX) do_action('admin_footer');
    wp_die();
});

// === Výchozí prázdné pravidlo ===
function pdk_coupons_empty_rule() {
    return [
        'name' => '',
        'trigger_products' => [],
        'trigger_status' => '',
        'coupon_settings' => [
            'amount' => '',
            'discount_type' => 'fixed_cart',
            'prefix' => 'KUPON-',
            'expiry_days' => 30,
            'usage_limit' => 1,
            'usage_limit_per_user' => 1,
            'apply_to_products' => [],
            'apply_to_categories' => [],
            'customer_note' => '',
            'only_once_per_customer' => 0,
        ]
    ];
}

// === HTML políčka pro jedno pravidlo ===
function pdk_coupons_rule_fields($i, $rule) {
    $name = esc_attr($rule['name']);
    $trigger_products = is_array($rule['trigger_products']) ? $rule['trigger_products'] : [];
    $statuses = wc_get_order_statuses();
    $trigger_status = isset($rule['trigger_status']) ? esc_attr($rule['trigger_status']) : '';
    $cs = $rule['coupon_settings'];
    ?>
    <div class="pdk-coupons-rule-block">
        <span class="pdk-coupons-remove-rule dashicons dashicons-no" title="<?php esc_attr_e('Odebrat toto pravidlo', 'produkty-darky-kupony'); ?>"></span>
        <button type="button" class="pdk-coupons-minimize-rule" style="position:absolute;top:10px;right:40px;font-size:20px;background:none;border:none;cursor:pointer;">–</button>
        <h2><?php _e('Pravidlo', 'produkty-darky-kupony'); ?> #<?php echo ($i+1); ?></h2>
        <div class="pdk-coupons-rule-content">
        <p>
            <label><b><?php _e('Název pravidla', 'produkty-darky-kupony'); ?>:</b>
                <input type="text" name="pdk_coupons_rules[<?php echo $i; ?>][name]" value="<?php echo $name; ?>" class="regular-text" style="width:350px;">
            </label>
        </p>
        <p>
            <label><b><?php _e('Produkty spouštěče', 'produkty-darky-kupony'); ?>:</b>
                <select class="pdk-coupons-product-search" data-idx="<?php echo $i; ?>" name="pdk_coupons_rules[<?php echo $i; ?>][trigger_products][]" multiple="multiple" style="width:380px;">
                    <?php
                    foreach ($trigger_products as $pid) {
                        $product = wc_get_product($pid);
                        if ($product) {
                            echo '<option value="' . esc_attr($pid) . '" selected="selected">' . esc_html($product->get_name()) . '</option>';
                        }
                    }
                    ?>
                </select>
            </label>
        </p>
        <p>
            <label><b><?php _e('Stav objednávky', 'produkty-darky-kupony'); ?>:</b>
                <select name="pdk_coupons_rules[<?php echo $i; ?>][trigger_status]" class="regular-text" style="width:350px;">
                    <option value=""><?php _e('-- Vyberte stav objednávky --', 'produkty-darky-kupony'); ?></option>
                    <?php foreach ($statuses as $status_key => $status_label): ?>
                        <option value="<?php echo esc_attr($status_key); ?>" <?php selected($trigger_status, $status_key); ?>><?php echo esc_html($status_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
        <fieldset style="border:1px dashed #ccc; padding:10px; max-width:700px;">
            <legend><?php _e('Nastavení kupónu', 'produkty-darky-kupony'); ?></legend>
            <table class="form-table" style="max-width:650px;">
                <tr>
                    <th><?php _e('Výše slevy:', 'produkty-darky-kupony'); ?></th>
                    <td>
                      <input type="number" step="0.01" name="pdk_coupons_rules[<?php echo $i; ?>][coupon_settings][amount]" value="<?php echo esc_attr($cs['amount']); ?>" style="width:100px;display:inline-block;">
                      <label style="margin-left:10px;">
                        <input type="radio" name="pdk_coupons_rules[<?php echo $i; ?>][coupon_settings][discount_type]" value="fixed_cart" <?php checked(($cs['discount_type']??'fixed_cart'), 'fixed_cart'); ?>> Kč
                      </label>
                      <label style="margin-left:10px;">
                        <input type="radio" name="pdk_coupons_rules[<?php echo $i; ?>][coupon_settings][discount_type]" value="percent" <?php checked(($cs['discount_type']??''), 'percent'); ?>> %
                      </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Prefix kódu:', 'produkty-darky-kupony'); ?></th>
                    <td><input type="text" name="pdk_coupons_rules[<?php echo $i; ?>][coupon_settings][prefix]" value="<?php echo esc_attr($cs['prefix']); ?>" style="width:100px;"></td>
                </tr>
                <tr>
                    <th><?php _e('Platnost kupónu (dní):', 'produkty-darky-kupony'); ?></th>
                    <td><input type="number" name="pdk_coupons_rules[<?php echo $i; ?>][coupon_settings][expiry_days]" value="<?php echo esc_attr($cs['expiry_days']); ?>" style="width:100px;"></td>
                </tr>
                <tr>
                    <th><?php _e('Použití na produkty:', 'produkty-darky-kupony'); ?></th>
                    <td>
                        <select class="pdk-coupons-product-search" data-idx="<?php echo $i; ?>" name="pdk_coupons_rules[<?php echo $i; ?>][coupon_settings][apply_to_products][]" multiple="multiple" style="width:380px;">
                            <?php
                            foreach ((array)$cs['apply_to_products'] as $pid) {
                                $product = wc_get_product($pid);
                                if ($product) {
                                    echo '<option value="' . esc_attr($pid) . '" selected="selected">' . esc_html($product->get_name()) . '</option>';
                                }
                            }
                            ?>
                        </select>
                        <span style="font-size:90%;color:#777;"><?php _e('Volitelné', 'produkty-darky-kupony'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Použití na kategorie:', 'produkty-darky-kupony'); ?></th>
                    <td>
                        <select class="pdk-coupons-category-search" data-idx="<?php echo $i; ?>" name="pdk_coupons_rules[<?php echo $i; ?>][coupon_settings][apply_to_categories][]" multiple="multiple" style="width:380px;">
                            <?php
                            foreach ((array)$cs['apply_to_categories'] as $cat_id) {
                                $cat = get_term($cat_id, 'product_cat');
                                if ($cat && !is_wp_error($cat)) {
                                    echo '<option value="' . esc_attr($cat_id) . '" selected="selected">' . esc_html($cat->name) . '</option>';
                                }
                            }
                            ?>
                        </select>
                        <span style="font-size:90%;color:#777;"><?php _e('Volitelné', 'produkty-darky-kupony'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Limit použití:', 'produkty-darky-kupony'); ?></th>
                    <td><input type="number" name="pdk_coupons_rules[<?php echo $i; ?>][coupon_settings][usage_limit]" value="<?php echo esc_attr($cs['usage_limit'] ?? 1); ?>" style="width:80px;"></td>
                </tr>
                <tr>
                    <th><?php _e('Limit na zákazníka:', 'produkty-darky-kupony'); ?></th>
                    <td>
                        <input type="number" name="pdk_coupons_rules[<?php echo $i; ?>][coupon_settings][usage_limit_per_user]" value="<?php echo esc_attr($cs['usage_limit_per_user'] ?? 1); ?>" style="width:80px;">
                        <span style="font-size:90%;color:#777;"><?php _e('Kolikrát může jeden zákazník využít tento kupón.', 'produkty-darky-kupony'); ?></span>
                        <br>
                        <label>
                            <input type="checkbox" name="pdk_coupons_rules[<?php echo $i; ?>][coupon_settings][only_once_per_customer]" value="1" <?php checked(!empty($cs['only_once_per_customer'])); ?>>
                            <?php _e('Vygenerovat kupón pouze jednou pro každého zákazníka.', 'produkty-darky-kupony'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Poznámka pro zákazníka:', 'produkty-darky-kupony'); ?></th>
                    <td>
                        <div class="pdk-coupons-customer-note-tags">
                            <?php _e('Dostupné tagy:', 'produkty-darky-kupony'); ?>
                            <b>%KOD%</b> = <?php _e('kód kupónu', 'produkty-darky-kupony'); ?>,
                            <b>%PLATNOST%</b> = <?php _e('datum expirace', 'produkty-darky-kupony'); ?>,
                            <b>%PRODUKTY%</b> = <?php _e('odkazy na produkty', 'produkty-darky-kupony'); ?>,
                            <b>%OBJEDNAVKA%</b> = <?php _e('číslo objednávky', 'produkty-darky-kupony'); ?>,
                            <b>%DATUM_OBJEDNAVKY%</b> = <?php _e('datum objednávky', 'produkty-darky-kupony'); ?>,
                            <b>%SLEVA%</b> = <?php _e('výše slevy', 'produkty-darky-kupony'); ?>
                        </div>
                        <textarea name="pdk_coupons_rules[<?php echo $i; ?>][coupon_settings][customer_note]" style="width:100%;height:60px;"><?php echo esc_textarea($cs['customer_note'] ?? ''); ?></textarea>
                        <span style="font-size:90%;color:#777;"><?php _e('Text poznámky bude přidán zákazníkovi do detailu objednávky. Můžeš použít tagy výše.', 'produkty-darky-kupony'); ?></span>
                    </td>
                </tr>
            </table>
        </fieldset>
        </div>
    </div>
    <?php
}

// === ULOŽENÍ VÍCE PRAVIDEL ===
add_action('admin_init', function(){
    if (!isset($_POST['pdk_coupons_rules_nonce']) || !wp_verify_nonce($_POST['pdk_coupons_rules_nonce'], 'pdk_coupons_save_rules')) return;
    if (!current_user_can('manage_woocommerce')) return;
    if (!isset($_POST['pdk_coupons_rules']) || !is_array($_POST['pdk_coupons_rules'])) return;

    $input = $_POST['pdk_coupons_rules'];
    $rules = [];
    foreach ($input as $idx => $rule) {
        $r = [];
        $r['name'] = sanitize_text_field($rule['name'] ?? '');
        $r['trigger_products'] = isset($rule['trigger_products']) ? array_map('absint', (array)$rule['trigger_products']) : [];
        $r['trigger_status'] = sanitize_text_field($rule['trigger_status'] ?? '');
        $cs = isset($rule['coupon_settings']) && is_array($rule['coupon_settings']) ? $rule['coupon_settings'] : [];
        $r['coupon_settings'] = [
            'prefix' => sanitize_text_field($cs['prefix'] ?? 'KUPON-'),
            'amount' => floatval($cs['amount'] ?? 0),
            'discount_type' => in_array(($cs['discount_type'] ?? 'fixed_cart'), ['fixed_cart', 'percent']) ? $cs['discount_type'] : 'fixed_cart',
            'expiry_days' => absint($cs['expiry_days'] ?? 30),
            'usage_limit' => absint($cs['usage_limit'] ?? 1),
            'usage_limit_per_user' => absint($cs['usage_limit_per_user'] ?? 1),
            'apply_to_products' => isset($cs['apply_to_products']) ? array_map('absint', (array)$cs['apply_to_products']) : [],
            'apply_to_categories' => isset($cs['apply_to_categories']) ? array_map('absint', (array)$cs['apply_to_categories']) : [],
            'customer_note' => array_key_exists('customer_note', $cs) ? wp_kses_post($cs['customer_note']) : '',
            'only_once_per_customer' => !empty($cs['only_once_per_customer']) ? 1 : 0,
        ];
        if (
            empty($r['name']) &&
            empty($r['trigger_products']) &&
            empty($r['trigger_status']) &&
            empty($r['coupon_settings']['amount'])
        ) {
            continue; // neukládej prázdné
        }
        $rules[] = $r;
    }
    update_option('pdk_coupons_rules', $rules);
    add_action('admin_notices', function(){
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Pravidla byla uložena.', 'produkty-darky-kupony') . '</p></div>';
    });
});

// === ENQUEUE SELECT2 ===
add_action('admin_enqueue_scripts', function($hook){
    if (strpos($hook, 'pdk-coupons') !== false) {
        wp_enqueue_script('select2');
        wp_enqueue_style('select2');
        wp_enqueue_style('woocommerce_admin_styles');
    }
});

// === AUTOMATICKÉ GENEROVÁNÍ KUPÓNU NA ZÁKLADĚ PRAVIDEL ===
add_action('woocommerce_order_status_changed', function($order_id, $old_status, $new_status){
    if (!$order_id) return;
    $rules = get_option('pdk_coupons_rules', []);
    if (empty($rules)) return;
    $order = wc_get_order($order_id);
    if (!$order) return;

    foreach ($rules as $rule_idx => $rule) {
        // Kontrola stavu objednávky
        $wanted_status = $rule['trigger_status'] ?? '';
        if (!$wanted_status) continue;
        if ($wanted_status !== 'wc-'.$new_status) continue;

        // Najde produkt v objednávce?
        $trigger_products = is_array($rule['trigger_products']) ? $rule['trigger_products'] : [];
        $found = false;
        foreach ($order->get_items() as $item) {
            if (in_array($item->get_product_id(), $trigger_products)) {
                $found = true;
                break;
            }
        }
        if (!$found) continue;

        // Generování zprávy
        $produkty = [];
        $apply_products = $rule['coupon_settings']['apply_to_products'] ?? [];
        $apply_categories = $rule['coupon_settings']['apply_to_categories'] ?? [];

        // Seznam produktů
        if ($apply_products) {
            foreach ($apply_products as $product_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $produkty[] = '<a href="' . get_permalink($product->get_id()) . '">' . $product->get_name() . '</a>';
                }
            }
        }

        if ($apply_categories) {
            foreach ($apply_categories as $category_id) {
                $category_products = wc_get_products([
                    'category' => [(string) $category_id],
                    'status' => 'publish',
                    'limit' => -1,
                ]);
                foreach ($category_products as $product) {
                    $produkty[] = '<a href="' . get_permalink($product->get_id()) . '">' . $product->get_name() . '</a>';
                }
            }
        }

        // Pokud nejsou produkty ani kategorie obsazeny, nastav alternativní text
        if (empty($produkty)) {
            $produkty[] = __('vybrané produkty', 'produkty-darky-kupony');
        }

        // Výměna ve zprávě
        $replace = [
            '%KOD%' => '123ABC',
            '%PLATNOST%' => date('d.m.Y'),
            '%PRODUKTY%' => implode(', ', $produkty),
        ];

        $note = strtr('...', $replace);
        
        if ($note) {
            // Přidání poznámky (příklad ukončení bloku)
            $order->add_order_note($note, true);
        }
    }
}, 10, 3);