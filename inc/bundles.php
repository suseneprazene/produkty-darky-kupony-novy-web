<?php
/*
* Součást sjednoceného pluginu: Bundly příchutí
* (původní plugin: Custom Flavor Bundle)
*/

if (!defined('ABSPATH')) exit;

function cfb_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p><strong>Custom Flavor Bundle:</strong> Tento plugin vyžaduje aktivní WooCommerce.</p></div>';
        });
        return false;
    }
    return true;
}

add_action('add_meta_boxes', function() {
    if (!cfb_check_woocommerce()) return;
    add_meta_box(
        'cfb_product_metabox',
        'Nastavení Flavor Bundle',
        'cfb_product_metabox_callback',
        'product',
        'normal',
        'high'
    );
});

function cfb_get_product_select_html($input_name, $selected_ids = []) {
    $all_products = get_posts([
        'post_type' => 'product',
        'numberposts' => -1,
        'post_status' => 'publish'
    ]);
    $html = '<select name="' . esc_attr($input_name) . '[]" multiple class="cfb-product-select" style="width:100%;">';
    foreach ($all_products as $prod) {
        $wc_product = wc_get_product($prod->ID);
        if ($wc_product && $wc_product->is_type('variable')) {
            $children = $wc_product->get_children();
            foreach ($children as $child_id) {
                $child = wc_get_product($child_id);
                if ($child) {
                    $main_title = get_the_title($prod->ID);
                    $attrs = [];
                    foreach ($child->get_attributes() as $attr_name => $attr_value) {
                        $taxonomy = wc_attribute_label(str_replace('attribute_', '', $attr_name));
                        $attrs[] = $taxonomy . ': ' . wc_attribute_label($attr_value);
                    }
                    $variant_summary = $main_title . ' – ' . implode(', ', $attrs);
                    $selected = in_array($child_id, $selected_ids) ? 'selected' : '';
                    $html .= '<option value="' . $child_id . '" ' . $selected . '>' . esc_html($variant_summary) . '</option>';
                }
            }
        } elseif ($wc_product) {
            $selected = in_array($prod->ID, $selected_ids) ? 'selected' : '';
            $html .= '<option value="' . $prod->ID . '" ' . $selected . '>' . esc_html($prod->post_title) . '</option>';
        }
    }
    $html .= '</select>';
    return $html;
}

function cfb_product_metabox_callback($post) {
    wp_nonce_field('cfb_save_product_metabox', 'cfb_product_metabox_nonce');
    
    // Načteme Select2 (WordPress má vestavěnou verzi)
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
    
    $is_bundle = get_post_meta($post->ID, '_cfb_is_bundle', true);

    $bundle_items = get_post_meta($post->ID, '_cfb_bundle_items', true);
    if (!is_array($bundle_items) || empty($bundle_items)) {
        $bundle_items = [['type' => 'category', 'category_id' => '', 'product_ids' => [], 'limit' => 1, 'title' => '']];
    }

    $product_categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ]);
    ?>
    <div class="cfb-metabox">
        <p>
            <label>
                <input type="checkbox" name="cfb_is_bundle" <?php checked($is_bundle, '1'); ?> />
                Povolit výběr příchutí/balíčků pro tento produkt
            </label>
        </p>
        <div id="cfb-bundle-items">
            <?php foreach ($bundle_items as $index => $item): ?>
            <div class="cfb-bundle-row" data-index="<?php echo $index; ?>">
                <p>
                    <label for="cfb_bundle_title_<?php echo $index; ?>"><b>Nadpis výběru (volitelné):</b></label><br>
                    <input type="text" id="cfb_bundle_title_<?php echo $index; ?>" name="cfb_bundle_items[<?php echo $index; ?>][title]" style="width:90%;" value="<?php echo esc_attr($item['title'] ?? ''); ?>" maxlength="128" placeholder="Např. Vyberte si kávu 250g">
                </p>
                <p>
                    <label for="cfb_bundle_type_<?php echo $index; ?>"><b>Typ výběru:</b></label><br>
                    <select id="cfb_bundle_type_<?php echo $index; ?>" name="cfb_bundle_items[<?php echo $index; ?>][type]" class="cfb-bundle-type">
                        <option value="category" <?php selected($item['type'], 'category'); ?>>Kategorie</option>
                        <option value="products" <?php selected($item['type'], 'products'); ?>>Konkrétní produkty/varianty</option>
                    </select>
                </p>
                <div class="cfb-type-category" <?php if ($item['type'] !== 'category') echo 'style="display:none"'; ?>>
                    <label for="cfb_category_id_<?php echo $index; ?>">Kategorie:</label><br>
                    <select id="cfb_category_id_<?php echo $index; ?>" name="cfb_bundle_items[<?php echo $index; ?>][category_id]" style="width: 100%;">
                        <option value="">Vyberte kategorii</option>
                        <?php foreach ($product_categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($item['category_id'] ?? '', $cat->term_id); ?>>
                                <?php echo esc_html($cat->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cfb-type-products" <?php if ($item['type'] !== 'products') echo 'style="display:none"'; ?>>
                    <label>Vyber produkty/varianty:</label><br>
                    <?php echo cfb_get_product_select_html('cfb_bundle_items[' . $index . '][product_ids]', $item['product_ids'] ?? []); ?>
                </div>
                <p>
                    <label for="cfb_bundle_limit_<?php echo $index; ?>">Počet balíčků (limit):</label><br>
                    <input type="number" id="cfb_bundle_limit_<?php echo $index; ?>" name="cfb_bundle_items[<?php echo $index; ?>][limit]" value="<?php echo esc_attr($item['limit'] ?? 1); ?>" min="1" style="width: 100px;" />
                    <br><small>Zákazník musí vybrat přesně tento počet.</small>
                </p>
                <?php if ($index > 0): ?>
                    <button type="button" class="cfb-remove-bundle">Odstranit výběr</button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <p><button type="button" id="cfb-add-bundle-item">Přidat další výběr</button></p>
    </div>
    <style>
        .cfb-metabox p { margin-bottom: 15px; }
        .cfb-metabox label { font-weight: bold; }
        .cfb-metabox input[type="text"], .cfb-metabox input[type="number"], .cfb-metabox select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; }
        .cfb-bundle-row { border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; border-radius: 4px; background: #fbfbfb; }
        #cfb-add-bundle-item { padding: 8px 15px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer; }
        #cfb-add-bundle-item:hover { background: #005d87; }
        .cfb-remove-bundle { padding: 5px 10px; background: #dc3232; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 5px; }
        .cfb-remove-bundle:hover { background: #a00; }
        
        /* Select2 styling úpravy */
        .select2-container { width: 100% !important; }
        .select2-container--default .select2-selection--multiple { min-height: 38px; }
    </style>
    <script>
        jQuery(document).ready(function($) {
            let bundleIndex = <?php echo count($bundle_items); ?>;
            let productSelectHtml = <?php echo json_encode(cfb_get_product_select_html('cfb_bundle_items[REPLACE_INDEX][product_ids]')); ?>;
            
            // Inicializace Select2 pro existující selecty
            function initSelect2ForProducts() {
                $('.cfb-type-products select').each(function() {
                    if (!$(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2({
                            placeholder: 'Začněte psát pro vyhledání produktu...',
                            allowClear: true,
                            width: '100%',
                            language: {
                                noResults: function() {
                                    return "Žádné produkty nenalezeny";
                                },
                                searching: function() {
                                    return "Vyhledávám...";
                                }
                            }
                        });
                    }
                });
            }
            
            // Inicializuj při načtení stránky
            initSelect2ForProducts();
            
            $('#cfb-add-bundle-item').click(function() {
                let htmlSelect = productSelectHtml.replace(/REPLACE_INDEX/g, bundleIndex);
                let newRow = `
                    <div class="cfb-bundle-row" data-index="${bundleIndex}">
                        <p>
                            <label for="cfb_bundle_title_${bundleIndex}"><b>Nadpis výběru (volitelné):</b></label><br>
                            <input type="text" id="cfb_bundle_title_${bundleIndex}" name="cfb_bundle_items[${bundleIndex}][title]" style="width:90%;" value="" maxlength="128" placeholder="Např. Vyberte si kávu 250g">
                        </p>
                        <p>
                            <label for="cfb_bundle_type_${bundleIndex}"><b>Typ výběru:</b></label><br>
                            <select id="cfb_bundle_type_${bundleIndex}" name="cfb_bundle_items[${bundleIndex}][type]" class="cfb-bundle-type">
                                <option value="category">Kategorie</option>
                                <option value="products">Konkrétní produkty/varianty</option>
                            </select>
                        </p>
                        <div class="cfb-type-category">
                            <label for="cfb_category_id_${bundleIndex}">Kategorie:</label><br>
                            <select id="cfb_category_id_${bundleIndex}" name="cfb_bundle_items[${bundleIndex}][category_id]" style="width: 100%;">
                                <option value="">Vyberte kategorii</option>
                                <?php foreach ($product_categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>">
                                        <?php echo esc_html($cat->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cfb-type-products" style="display:none;">
                            <label>Vyber produkty/varianty:</label><br>
                            ${htmlSelect}
                        </div>
                        <p>
                            <label for="cfb_bundle_limit_${bundleIndex}">Počet balíčků (limit):</label><br>
                            <input type="number" id="cfb_bundle_limit_${bundleIndex}" name="cfb_bundle_items[${bundleIndex}][limit]" value="1" min="1" style="width: 100px;" />
                            <br><small>Zákazník musí vybrat přesně tento počet.</small>
                        </p>
                        <button type="button" class="cfb-remove-bundle">Odstranit výběr</button>
                    </div>
                `;
                $('#cfb-bundle-items').append(newRow);
                
                // Inicializuj Select2 pro nově přidaný select
                initSelect2ForProducts();
                
                bundleIndex++;
            });
            
            $(document).on('change', '.cfb-bundle-type', function() {
                let $row = $(this).closest('.cfb-bundle-row');
                if ($(this).val() === 'category') {
                    $row.find('.cfb-type-category').show();
                    $row.find('.cfb-type-products').hide();
                } else {
                    $row.find('.cfb-type-category').hide();
                    $row.find('.cfb-type-products').show();
                    // Reinicializuj Select2 při zobrazení
                    initSelect2ForProducts();
                }
            });
            
            $(document).on('click', '.cfb-remove-bundle', function() {
                let $row = $(this).closest('.cfb-bundle-row');
                // Zničíme Select2 před odstraněním elementu
                $row.find('.select2-hidden-accessible').select2('destroy');
                $row.remove();
            });
        });
    </script>
    <?php
}

add_action('save_post', function($post_id) {
    if (!cfb_check_woocommerce()) return;
    
    // Pokud nonce neexistuje, neděláme nic (nejedná se o submit z našeho formuláře)
    if (!isset($_POST['cfb_product_metabox_nonce'])) {
        return;
    }
    
    if (!wp_verify_nonce($_POST['cfb_product_metabox_nonce'], 'cfb_save_product_metabox')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (get_post_type($post_id) !== 'product') return;
    
    // Nyní víme, že se jedná o legitimní submit z našeho metaboxu
    if (isset($_POST['cfb_is_bundle'])) {
        update_post_meta($post_id, '_cfb_is_bundle', '1');
        
        // Zpracuj bundle items pouze když je bundle aktivní
        if (isset($_POST['cfb_bundle_items']) && is_array($_POST['cfb_bundle_items'])) {
            $items = array_values(array_filter($_POST['cfb_bundle_items'], function($item) {
                if (isset($item['type']) && $item['type'] === 'category') {
                    return !empty($item['category_id']) && intval($item['limit']) >= 1;
                } else {
                    return isset($item['product_ids']) && is_array($item['product_ids']) && count(array_filter($item['product_ids'])) > 0 && intval($item['limit']) >= 1;
                }
            }));
            if (!empty($items)) {
                update_post_meta($post_id, '_cfb_bundle_items', $items);
            }
        }
    } else {
        // Checkbox není zaškrtnutý - uživatel explicitně vypnul bundle
        delete_post_meta($post_id, '_cfb_is_bundle');
        delete_post_meta($post_id, '_cfb_bundle_items');
    }
}, 10, 1);

add_action('woocommerce_before_add_to_cart_button', function() {
    if (!function_exists('wc_get_product')) return;
    global $product;
    if (!$product || !is_a($product, 'WC_Product') || !get_post_meta($product->get_id(), '_cfb_is_bundle', true)) return;
    $bundle_items = get_post_meta($product->get_id(), '_cfb_bundle_items', true);

    if ((!is_array($bundle_items) || empty($bundle_items)) && ($categories = get_post_meta($product->get_id(), '_cfb_categories', true))) {
        $bundle_items = [];
        foreach ($categories as $cat) {
            $bundle_items[] = [
                'type' => 'category',
                'category_id' => $cat['category_id'],
                'limit' => $cat['limit'],
                'product_ids' => [],
                'title' => ''
            ];
        }
    }
    if (!is_array($bundle_items) || empty($bundle_items)) return;

    $limits = [];
    foreach ($bundle_items as $index => $item) {
        $limits[$index] = intval($item['limit']);
    }
    ?>
    <div class="cfb-flavor-selector">
        <?php foreach ($bundle_items as $index => $item): ?>
            <?php
            $limit = intval($item['limit']);
            if ($limit < 1) continue;
            $title = trim($item['title'] ?? '');
            if ($item['type'] === 'category') {
                $category_id = $item['category_id'];
                $cat_term = get_term($category_id, 'product_cat');
                $category_name = $cat_term ? $cat_term->name : 'Není kategorie';
                $section_name = $title !== '' ? $title : $category_name;
                $products_in_category = get_posts([
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'tax_query' => [
                        [
                            'taxonomy' => 'product_cat',
                            'field' => 'term_id',
                            'terms' => $category_id,
                        ],
                    ],
                    'fields' => 'ids'
                ]);
                $flavors = [];
                foreach ($products_in_category as $prod_id) {
                    $wc_product = wc_get_product($prod_id);
                    if ($wc_product && $wc_product->is_type('variable')) {
                        foreach ($wc_product->get_children() as $child_id) {
                            $child = wc_get_product($child_id);
                            if ($child) {
                                $main_title = get_the_title($prod_id);
                                $attrs = [];
                                foreach ($child->get_attributes() as $attr_name => $attr_value) {
                                    $taxonomy = wc_attribute_label(str_replace('attribute_', '', $attr_name));
                                    $attrs[] = $taxonomy . ': ' . wc_attribute_label($attr_value);
                                }
                                $variant_summary = $main_title . ' – ' . implode(', ', $attrs);

                                $is_managed = $child->managing_stock();
                                $is_in_stock = $child->is_in_stock();
                                $stock = $is_managed ? $child->get_stock_quantity() : null;
                                $is_chooseable = $is_managed ? ($is_in_stock && $stock > 0) : $is_in_stock;

                                $flavors[] = [
                                    'id' => $child_id,
                                    'name' => $variant_summary,
                                    'stock' => $is_managed ? $stock : 99999,
                                    'is_in_stock' => $is_chooseable,
                                    'permalink' => get_permalink($prod_id),
                                    'prod_id' => $prod_id
                                ];
                            }
                        }
                    } elseif ($wc_product) {
                        $is_managed = $wc_product->managing_stock();
                        $is_in_stock = $wc_product->is_in_stock();
                        $stock = $is_managed ? $wc_product->get_stock_quantity() : null;
                        $is_chooseable = $is_managed ? ($is_in_stock && $stock > 0) : $is_in_stock;

                        $flavors[] = [
                            'id' => $prod_id,
                            'name' => get_the_title($prod_id),
                            'stock' => $is_managed ? $stock : 99999,
                            'is_in_stock' => $is_chooseable,
                            'permalink' => get_permalink($prod_id),
                            'prod_id' => $prod_id
                        ];
                    }
                }
            } else {
                $section_name = $title !== '' ? $title : "Výběr z produktů";
                $flavors = [];
                foreach ($item['product_ids'] as $pid) {
                    $wc_product = wc_get_product($pid);
                    if (!$wc_product) continue;
                    if ($wc_product->is_type('variation')) {
                        $parent = wc_get_product($wc_product->get_parent_id());
                        $main_title = $parent ? $parent->get_title() : $wc_product->get_title();
                        $attrs = [];
                        foreach ($wc_product->get_attributes() as $attr_name => $attr_value) {
                            $taxonomy = wc_attribute_label(str_replace('attribute_', '', $attr_name));
                            $attrs[] = $taxonomy . ': ' . wc_attribute_label($attr_value);
                        }
                        $variant_summary = $main_title . ' – ' . implode(', ', $attrs);
                        $name = $variant_summary;
                        $permalink = $parent ? get_permalink($parent->get_id()) : '';
                        $parent_id = $parent ? $parent->get_id() : $wc_product->get_id();
                    } else {
                        $name = $wc_product->get_title();
                        $permalink = get_permalink($wc_product->get_id());
                        $parent_id = $wc_product->get_id();
                    }

                    $is_managed = $wc_product->managing_stock();
                    $is_in_stock = $wc_product->is_in_stock();
                    $stock = $is_managed ? $wc_product->get_stock_quantity() : null;
                    $is_chooseable = $is_managed ? ($is_in_stock && $stock > 0) : $is_in_stock;

                    $flavors[] = [
                        'id' => $pid,
                        'name' => $name,
                        'stock' => $is_managed ? $stock : 99999,
                        'is_in_stock' => $is_chooseable,
                        'permalink' => $permalink,
                        'prod_id' => $parent_id
                    ];
                }
            }
            if (empty($flavors)) continue;
            ?>
            <div class="cfb-category-section" data-index="<?php echo $index; ?>">
                <h3><?php echo esc_html($section_name); ?></h3>
                <div class="cfb-limit-info">
                  Limit: <?php echo esc_html($limit); ?> <?php echo cfb_get_balicek_form($limit); ?>
                </div>
                <div id="cfb-error-<?php echo $index; ?>" class="cfb-error"></div>
                <div class="cfb-flavor-list">
                    <?php foreach ($flavors as $flavor): ?>
                        <div class="cfb-flavor">
                            <a class="cfb-label cfb-product-link"
                                  data-product-id="<?php echo esc_attr($flavor['prod_id']); ?>"
                                  data-product-permalink="<?php echo esc_url($flavor['permalink']); ?>"
                                  href="javascript:void(0);"
                                  tabindex="0"
                                  role="button"
                                  title="Zobrazit detail produktu">
                                <?php echo esc_html($flavor['name']); ?>:
                            </a>
                            <div class="cfb-controls">
                                <button type="button" class="cfb-button button cfb-minus" 
                                    data-category="<?php echo $index; ?>" 
                                    data-flavor-id="<?php echo esc_attr($flavor['id']); ?>" 
                                    data-flavor-name="<?php echo esc_attr($flavor['name']); ?>" 
                                    data-stock="<?php echo $flavor['stock']; ?>"
                                    <?php if (!$flavor['is_in_stock']) echo 'disabled style="opacity:0.5;cursor:not-allowed;"'; ?>>-</button>
                                <input type="number" class="cfb-quantity" 
                                    data-category="<?php echo $index; ?>" 
                                    data-flavor-id="<?php echo esc_attr($flavor['id']); ?>" 
                                    data-flavor-name="<?php echo esc_attr($flavor['name']); ?>" 
                                    data-stock="<?php echo $flavor['stock']; ?>"
                                    value="0" min="0" max="<?php echo $flavor['stock']; ?>" readonly
                                    <?php if (!$flavor['is_in_stock']) echo 'disabled style="opacity:0.5;"'; ?>>
                                <button type="button" class="cfb-button button cfb-plus" 
                                    data-category="<?php echo $index; ?>" 
                                    data-flavor-id="<?php echo esc_attr($flavor['id']); ?>" 
                                    data-flavor-name="<?php echo esc_attr($flavor['name']); ?>" 
                                    data-stock="<?php echo $flavor['stock']; ?>"
                                    <?php if (!$flavor['is_in_stock']) echo 'disabled style="opacity:0.5;cursor:not-allowed;"'; ?>>+</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <input type="hidden" name="cfb_flavor_selection" id="cfb_flavor_selection">
    </div>
    <div id="cfbModalBg" class="cfb-modal-bg" style="display:none"></div>
    <div id="cfbModal" class="cfb-modal" tabindex="-1" role="dialog" aria-modal="true" style="display:none"></div>
    <style>
/* ... původní CSS nezměněno ... */
.cfb-flavor-list {display: flex;flex-direction: column;gap: 2px;margin: 0;padding: 0;}
.cfb-flavor {display: flex;align-items: center;justify-content: flex-start;padding: 0 0 0 0.5rem;background: transparent;min-height: 32px;margin: 0;}
.cfb-label.cfb-product-link {flex: 1;font-weight: normal;font-size: inherit;margin-right: 6px;padding: 0;text-decoration: underline dotted #aaa;color: #111;cursor: pointer;outline: none;transition: color 0.15s;}
.cfb-label.cfb-product-link:focus, .cfb-label.cfb-product-link:hover {color: #0072c1;text-decoration: underline solid #0072c1;}
.cfb-label {flex: 1;font-weight: normal;font-size: inherit;margin-right: 6px;padding: 0;}
.cfb-controls {display: flex;flex-direction: row;align-items: center;gap: 0;height: 32px;justify-content: center;}
.cfb-quantity,
.cfb-button {margin: 0 2px;box-sizing: border-box;width: 32px !important;height: 32px !important;line-height: 32px !important;vertical-align: middle !important;}
.cfb-button {display: flex;align-items: center;justify-content: center;padding: 0 !important;font-size: 18px !important;background: #111;color: #fff;border: none;border-radius: 0;}
.cfb-button.button { font-family: inherit; }
.cfb-button:active { filter: brightness(0.85);}
.cfb-button[disabled] {background: #ddd;color: #aaa;cursor: not-allowed;}
.cfb-error { color: #b00 !important; margin-bottom: 5px; display: none; }
.cfb-limit-info { color: inherit !important; font-weight: 500; margin-bottom: 7px; font-size: inherit; }
.cfb-modal-bg {position: fixed; left:0; top:0; width: 100vw; height: 100vh; z-index: 10001; background: rgba(20,20,20,0.5);}
.cfb-modal {display: none;position: fixed;max-width: 98vw;min-width: 280px;top: 50%; left: 50%;transform: translate(-50%, -50%);z-index: 10002;background: #fff;color: #222;border-radius: 7px;box-shadow: 0 7px 32px #2225;padding: 30px 22px 18px 22px;font-size: 1rem;outline: none;min-height: 80px;}
.cfb-modal-close {position: absolute;right: 19px; top: 14px;border: none;background: none;color: #222;font-size: 24px;font-weight: bold;cursor: pointer;padding: 0;line-height: 1;z-index: 10003;}
.cfb-modal-title {font-weight: bold;font-size: 1.1rem;margin-bottom: 5px;line-height: 1.15;}
.cfb-modal-img {display:block;margin: 10px 0 10px 0; max-width: 180px; max-height:130px;object-fit: contain;}
.cfb-modal-price {font-size: 1rem;font-weight: bold;color: #007600;margin-bottom: 4px;}
.cfb-modal-desc {font-size: 0.98em;color: #444;margin-top: 0.45em;margin-bottom: 0.6em;line-height: 1.40;}
.cfb-hover-tooltip {display: none !important;} /* Tooltip display forcibly off as deprecated */
@media (max-width: 600px) {
    .cfb-flavor-list { gap: 2px; }
    .cfb-flavor { padding: 0 0 0 0.2rem; }
    .cfb-quantity { width: 28px; height: 28px; font-size: 13px;}
    .cfb-button { width: 28px; height: 28px; font-size: 16px; min-width: 28px; }
    .cfb-modal { padding:15px 5px; }
}
    </style>
    <script>
        (function($) {
            let cfbTooltipTimer = null;
            function balicekForm(count) {
                if (count == 1) return 'balíček';
                else if (count >= 2 && count <= 4) return 'balíčky';
                else return 'balíčků';
            }
            $(document).ready(function() {
                let selections = {};
                let categoryLimits = <?php echo json_encode($limits); ?>;
                let addToCartButton = $('button.add_to_cart_button, .single_add_to_cart_button');
                if (addToCartButton.length) addToCartButton.prop('disabled', false);
                $('.cfb-quantity').each(function() {
                    let flavorId = $(this).data('flavor-id');
                    let flavorName = $(this).data('flavor-name');
                    let stock = $(this).data('stock');
                    let disabled = $(this).prop('disabled');
                    if (!selections[flavorId]) selections[flavorId] = {name: flavorName, qty: 0, stock: stock, disabled: disabled};
                });
                function updateSelection(categoryIndex) {
                    let total = 0;
                    let categoryTotal = {};
                    $('.cfb-quantity').each(function() {
                        let flavorId = $(this).data('flavor-id');
                        let cat = $(this).data('category');
                        let disabled = $(this).prop('disabled');
                        categoryTotal[cat] = categoryTotal[cat] || 0;
                        if (!disabled) {
                            categoryTotal[cat] += selections[flavorId] ? selections[flavorId].qty : 0;
                            total += selections[flavorId] ? selections[flavorId].qty : 0;
                        }
                    });
                    $('#cfb_flavor_selection').val(JSON.stringify(selections));
                    let isValid = true;
                    $('.cfb-category-section').each(function() {
                        let idx = $(this).data('index');
                        let catName = $(this).find('h3').text().replace(/ \(Limit: .*/, '');
                        let catLimit = categoryLimits[idx];
                        let catTotal = categoryTotal[idx] || 0;
                        if (catTotal !== catLimit) {
                            $('#cfb-error-' + idx).hide();
                            isValid = false;
                        } else {
                            $('#cfb-error-' + idx).hide();
                        }
                    });
                    $('.cfb-quantity').each(function() {
                        let flavorId = $(this).data('flavor-id');
                        let stock = parseInt($(this).data('stock'));
                        let qty = selections[flavorId] ? selections[flavorId].qty : 0;
                        let disabled = $(this).prop('disabled');
                        if (!disabled && qty > stock) {
                            $(this).closest('.cfb-category-section').find('.cfb-error').text('Nelze vybrat více než je skladem.').show();
                            isValid = false;
                        }
                    });
                    if (addToCartButton.length) {
                        if (isValid) addToCartButton.prop('disabled', false);
                        else addToCartButton.prop('disabled', true);
                    }
                }
                $('.cfb-limit-info').show();

                $('.cfb-flavor-selector').on('click', '.cfb-plus', function(e) {
                    e.preventDefault();
                    if ($(this).prop('disabled')) return;
                    let input = $(this).siblings('.cfb-quantity');
                    let flavorId = $(this).data('flavor-id');
                    let flavorName = $(this).data('flavor-name');
                    let category = $(this).data('category');
                    let categoryLimit = categoryLimits[category];
                    let maxStock = parseInt($(this).data('stock'));
                    let categoryTotal = 0;
                    $('.cfb-quantity').each(function() {
                        let fl = $(this).data('flavor-id');
                        let cat = $(this).data('category');
                        let disabled = $(this).prop('disabled');
                        if (cat == category && !disabled) categoryTotal += selections[fl] ? selections[fl].qty : 0;
                    });
                    if (!selections[flavorId]) selections[flavorId] = {name: flavorName, qty: 0, stock: maxStock, disabled: false};
                    if (categoryTotal < categoryLimit && selections[flavorId].qty < maxStock) {
                        selections[flavorId].qty++;
                        input.val(selections[flavorId].qty);
                        updateSelection(category);
                    }
                });
                $('.cfb-flavor-selector').on('click', '.cfb-minus', function(e) {
                    e.preventDefault();
                    if ($(this).prop('disabled')) return;
                    let input = $(this).siblings('.cfb-quantity');
                    let flavorId = $(this).data('flavor-id');
                    let flavorName = $(this).data('flavor-name');
                    let category = $(this).data('category');
                    if (!selections[flavorId]) selections[flavorId] = {name: flavorName, qty: 0, stock: parseInt($(this).data('stock')), disabled: false};
                    if (selections[flavorId].qty > 0) {
                        selections[flavorId].qty--;
                        input.val(selections[flavorId].qty);
                        updateSelection(category);
                    }
                });

                // NOVÁ FUNKCE: Zobrazit popup modal pouze na klik (žádné hover tooltips)
                $('.cfb-flavor-selector').off('mouseenter.cfb').off('mouseleave.cfb');
                $('.cfb-flavor-selector').on('click', '.cfb-product-link', function(ev){
                    ev.preventDefault();
                    let $this = $(this);
                    let product_id = $this.data('product-id');
                    if (!product_id) return;

                    // Zavři starou modal
                    $('#cfbModal').hide().html('');
                    $('#cfbModalBg').hide();

                    // Zobraz loading
                    $('#cfbModal').html('<div style="text-align:center;padding:45px 0 40px 0;">Načítám...</div>').show();
                    $('#cfbModalBg').show();

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        method: 'POST',
                        data: {
                            action: 'cfb_quick_product_preview',
                            product_id: product_id
                        },
                        success: function(response){
                            // Modal HTML - přidáme tlačítko a vypneme možný hover tooltip na stejný produkt
                            let productLink = $this.data('product-permalink') || '';
                            let buttonHtml = productLink ? ('<div style="margin-top:10px;text-align:center;"><a href="'+productLink+'" class="button" target="_blank" rel="noopener" style="padding:9px 16px;margin-top:10px;background:#0073aa;color:#fff;border-radius:4px;text-decoration:none;">Otevřít detail produktu</a></div>') : '';
                            let closeBtn = '<button class="cfb-modal-close" aria-label="Zavřít" tabindex="0">&times;</button>';
                            $('#cfbModal').html(closeBtn + response + buttonHtml);
                        },
                        error: function(){
                            $('#cfbModal').html('<div style="padding:35px 0 30px 0;">Nepodařilo se načíst detail produktu.</div>');
                        }
                    });
                });
                // Zavřít modal na pozadí nebo kliknutí na close
                $('#cfbModalBg').on('click', function(){
                    $('#cfbModalBg').hide();
                    $('#cfbModal').hide().html('');
                });
                $(document).on('click', '.cfb-modal-close', function(){
                    $('#cfbModalBg').hide();
                    $('#cfbModal').hide().html('');
                });

                $('form.cart').on('submit', function(e) {
                    let categoryTotal = {};
                    let isValid = true;
                    $('.cfb-quantity').each(function() {
                        let flavorId = $(this).data('flavor-id');
                        let cat = $(this).data('category');
                        let stock = parseInt($(this).data('stock'));
                        let qty = selections[flavorId] ? selections[flavorId].qty : 0;
                        let disabled = $(this).prop('disabled');
                        categoryTotal[cat] = categoryTotal[cat] || 0;
                        if (!disabled) {
                            categoryTotal[cat] += qty;
                            if (qty > stock) isValid = false;
                        }
                    });
                    for (let idx in categoryLimits) {
                        let limit = categoryLimits[idx];
                        let catTotal = categoryTotal[idx] || 0;
                        if (catTotal !== limit) {
                            $('#cfb-error-' + idx).text(`Musíte vybrat přesně ${limit} ${balicekForm(limit)}.`).show();
                            isValid = false;
                        }
                    }
                    if (!isValid || $('.cfb-error:visible').length > 0) {
                        e.preventDefault();
                    }
                });
            });
        })(jQuery);
    </script>
    <?php
});

// AJAX handler for product modal preview (frontend)
add_action('wp_ajax_cfb_quick_product_preview', 'cfb_ajax_product_preview');
add_action('wp_ajax_nopriv_cfb_quick_product_preview', 'cfb_ajax_product_preview');
function cfb_ajax_product_preview() {
    if (empty($_POST['product_id'])) exit;
    $pid = intval($_POST['product_id']);
    $product = wc_get_product($pid);
    if (!$product) exit;
    $img   = $product->get_image('medium', array('class' => 'cfb-modal-img'), false);
    $title = $product->get_title();
    $price = $product->get_price_html();
    $short = $product->get_short_description();
    $link  = get_permalink($product->get_id());
    echo '<div class="cfb-modal-title">' . esc_html($title) . '</div>';
    echo '<div class="cfb-modal-price">' . $price . '</div>';
    if ($img) echo $img;
    if ($short) echo '<div class="cfb-modal-desc">' . $short . '</div>';
    // tlačítko "Otevřít detail produktu" přidáváme v JavaScriptu pro lepší stylování a zachování formuláře
    exit;
}

// -- Košík, objednávky a další WooCommerce integrace - originál funkce zůstávají beze změn --

// ── Store API (Block Cart) – registrace cfb dat pro woocommerce/cart block ──
add_action('woocommerce_blocks_loaded', function() {
    if (!function_exists('woocommerce_store_api_register_endpoint_data')) return;
    woocommerce_store_api_register_endpoint_data([
        'endpoint'        => Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
        'namespace'       => 'cfb_flavor',
        'data_callback'   => function($cart_item) {
            // Primary source: cfb_flavor_selection (set by bundles.php itself on single product page)
            // Fallback: sp_cfb_selection (set by sp-product-archive plugin when adding via archive modal)
            $source = null;
            if (!empty($cart_item['cfb_flavor_selection']) && is_array($cart_item['cfb_flavor_selection'])) {
                $source = $cart_item['cfb_flavor_selection'];
            } elseif (!empty($cart_item['sp_cfb_selection']) && is_array($cart_item['sp_cfb_selection'])) {
                $source = $cart_item['sp_cfb_selection'];
            }
            if (!$source) return ['selected_flavors' => []];
            $items = [];
            foreach ($source as $fid => $data) {
                if (isset($data['qty']) && $data['qty'] > 0) {
                    $items[] = ['id' => (string)$fid, 'name' => $data['name'], 'qty' => (int)$data['qty']];
                }
            }
            return ['selected_flavors' => $items];
        },
        'schema_callback' => function() {
            return ['selected_flavors' => ['description' => 'Vybrané varianty', 'type' => 'array', 'context' => ['view'], 'readonly' => true, 'items' => ['type' => 'object']]];
        },
        'schema_type' => ARRAY_A,
    ]);
});

// Zachovat cfb_flavor_selection při načtení košíku ze session
add_filter('woocommerce_get_cart_item_from_session', function($cart_item, $values, $key) {
    if (isset($values['cfb_flavor_selection'])) $cart_item['cfb_flavor_selection'] = $values['cfb_flavor_selection'];
    return $cart_item;
}, 10, 3);

add_filter('woocommerce_add_cart_item', function($cart_item, $cart_item_key) {
    if (isset($cart_item['cfb_flavor_selection']) && is_array($cart_item['cfb_flavor_selection'])) {
        $desc = '';
        foreach ($cart_item['cfb_flavor_selection'] as $flavor_id => $data) {
            if (isset($data['qty']) && $data['qty'] > 0) {
                $desc .= $data['name'] . ': ' . $data['qty'] . ' ' . cfb_get_balicek_form($data['qty']) . "\n";
            }
        }
        if (!empty($desc)) {
            $cart_item['data']->set_description("Balíček obsahuje:\n" . $desc);
        }
    }
    return $cart_item;
}, 20, 2);

add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id) {
    if (!cfb_check_woocommerce()) return $cart_item_data;
    if (isset($_POST['cfb_flavor_selection']) && !empty($_POST['cfb_flavor_selection'])) {
        $flavor_selection = json_decode(stripslashes($_POST['cfb_flavor_selection']), true);
        $bundle_items = get_post_meta($product_id, '_cfb_bundle_items', true);
        if ((!is_array($bundle_items) || empty($bundle_items)) && ($categories = get_post_meta($product_id, '_cfb_categories', true))) {
            $bundle_items = [];
            foreach ($categories as $cat) {
                $bundle_items[] = [
                    'type' => 'category',
                    'category_id' => $cat['category_id'],
                    'limit' => $cat['limit'],
                    'product_ids' => [],
                    'title' => ''
                ];
            }
        }
        if (json_last_error() === JSON_ERROR_NONE && is_array($flavor_selection) && is_array($bundle_items)) {
            foreach ($bundle_items as $item) {
                $limit = intval($item['limit']);
                if ($limit < 1) continue;
                $item_ids = [];
                if ($item['type'] === 'category') {
                    $products_in_category = get_posts([
                        'post_type' => 'product',
                        'posts_per_page' => -1,
                        'tax_query' => [
                            [
                                'taxonomy' => 'product_cat',
                                'field' => 'term_id',
                                'terms' => $item['category_id'],
                            ],
                        ],
                        'fields' => 'ids'
                    ]);
                    foreach ($products_in_category as $prod_id) {
                        $wc_product = wc_get_product($prod_id);
                        if ($wc_product && $wc_product->is_type('variable')) {
                            foreach ($wc_product->get_children() as $child_id) $item_ids[] = $child_id;
                        } else {
                            $item_ids[] = $prod_id;
                        }
                    }
                } else {
                    $item_ids = $item['product_ids'];
                }
                $cat_total = 0;
                foreach ($flavor_selection as $flavor_id => $data) {
                    if (in_array($flavor_id, $item_ids) && isset($data['qty'])) {
                        $cat_total += intval($data['qty']);
                    }
                    if (isset($data['qty']) && $data['qty'] > 0) {
                        $wc_product = wc_get_product($flavor_id);
                        if ($wc_product) {
                            $is_managed = $wc_product->managing_stock();
                            $is_in_stock = $wc_product->is_in_stock();
                            $stock = $is_managed ? $wc_product->get_stock_quantity() : null;
                            $is_chooseable = $is_managed ? ($is_in_stock && $stock > 0) : $is_in_stock;
                            if (!$is_chooseable) {
                                wc_add_notice('Produkt "' . esc_html($data['name']) . '" není skladem a nelze jej vybrat.', 'error');
                                return false;
                            }
                            if ($is_managed && $data['qty'] > $stock) {
                                wc_add_notice('Nelze vložit více "' . esc_html($data['name']) . '" než je skladem.', 'error');
                                return false;
                            }
                        }
                    }
                }
                if ($cat_total !== $limit) {
                    wc_add_notice("Musíte vybrat přesně {$limit} položek v jednom z výběrů.", 'error');
                    return false;
                }
            }
            $cart_item_data['cfb_flavor_selection'] = $flavor_selection;
        }
    }
    return $cart_item_data;
}, 10, 2);

add_filter('woocommerce_cart_item_name', function($item_name, $cart_item, $cart_item_key) {
    if (!cfb_check_woocommerce()) return $item_name;
    if (isset($cart_item['cfb_flavor_selection']) && is_array($cart_item['cfb_flavor_selection'])) {
        $flavors = $cart_item['cfb_flavor_selection'];
        if (!empty($flavors)) {
            $item_name .= '<div class="cfb-cart-flavors"><strong>Vybrané položky:</strong><ul>';
            foreach ($flavors as $flavor_id => $data) {
                if (isset($data['qty']) && $data['qty'] > 0) {
                    $item_name .= '<li>' . esc_html($data['name']) . ': ' . esc_html($data['qty']) . ' ' . cfb_get_balicek_form($data['qty']) . '</li>';
                }
            }
            $item_name .= '</ul></div>';
        }
    }
    return $item_name;
}, 20, 3);

add_filter('woocommerce_get_item_data', function($cart_data, $cart_item) {
    // If sp-product-archive plugin is active and handling display via sp_cfb_selection, skip to avoid duplicate rows in cart.
    if ( ! empty( $cart_item['sp_cfb_selection'] ) ) {
        return $cart_data;
    }
    if (isset($cart_item['cfb_flavor_selection']) && is_array($cart_item['cfb_flavor_selection'])) {
        $flavors = $cart_item['cfb_flavor_selection'];
        $summary = [];
        foreach ($flavors as $flavor_id => $data) {
            if (isset($data['qty']) && $data['qty'] > 0) {
                $summary[] = $data['name'] . ': ' . $data['qty'] . ' ' . cfb_get_balicek_form($data['qty']);
            }
        }
        if (!empty($summary)) {
            $cart_data[] = [
                'key'   => 'Obsah balíčku',
                'value' => implode(', ', $summary),
            ];
        }
    }
    return $cart_data;
}, 25, 2);

add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    if (!cfb_check_woocommerce()) return;
    if (isset($values['cfb_flavor_selection']) && is_array($values['cfb_flavor_selection'])) {
        $flavors = $values['cfb_flavor_selection'];
        if (!empty($flavors)) {
            // Přidáme každou položku jako samostatné meta s unikátním klíčem,
            // aby ji WooCommerce mobilní aplikace zobrazila na vlastním řádku.
            $i = 0;
            foreach ($flavors as $flavor_id => $data) {
                if (isset($data['qty']) && $data['qty'] > 0) {
                    $i++;
                    $line = $data['name'] . ': ' . $data['qty'] . ' ' . cfb_get_balicek_form($data['qty']);
                    // Klíč bude např. "Vybraná položka 1", "Vybraná položka 2", ...
                    $meta_key = 'Vybraná položka ' . $i;
                    $item->add_meta_data($meta_key, $line, false);
                }
            }
            // Uložíme i surová data pro další použití (odečty skladu apod.)
            $item->add_meta_data('_cfb_flavors_raw', $flavors);
        }
    }
}, 10, 4);

// Enqueue Block Cart JS pro zobrazení vybraných variant balíčku
add_action('wp_enqueue_scripts', function() {
    if (!is_cart() && !is_checkout()) return;
    wp_enqueue_script(
        'cfb-cart-blocks-bundles',
        plugins_url('../assets/cart-blocks-bundles.js', __FILE__),
        ['wp-data'],
        '1.0.1',
        true
    );
});

// Odečítání skladu na dokončení a zpracování objednávky
function cfb_sklad_odecet($order_id) {
    if (!cfb_check_woocommerce()) return;
    $order = wc_get_order($order_id);
    if (!$order) return;
    // Použijeme add_post_meta s unique=true – databáze zamítne druhý zápis, ochrana před dvojitým odečtem
    if (!add_post_meta((int) $order->get_id(), '_cfb_stock_deducted', '1', true)) return;
    foreach ($order->get_items() as $item) {
        $flavors = $item->get_meta('_cfb_flavors_raw', true);
        $item_qty = $item->get_quantity();
        if ($flavors && is_array($flavors) && $item_qty > 0) {
            foreach ($flavors as $prod_id => $data) {
                $qty = isset($data['qty']) ? intval($data['qty']) : 0;
                $total_qty = $qty * $item_qty;
                if ($total_qty > 0 && (get_post_type($prod_id) === 'product_variation' || get_post_type($prod_id) === 'product')) {
                    $product = wc_get_product($prod_id);
                    if ($product && $product->managing_stock()) {
                        $stock = $product->get_stock_quantity();
                        $product->set_stock_quantity(max(0, $stock - $total_qty));
                        $product->save();
                    }
                }
            }
        }
    }
}
add_action('woocommerce_order_status_completed', 'cfb_sklad_odecet');
add_action('woocommerce_order_status_processing', 'cfb_sklad_odecet');
add_action('woocommerce_checkout_order_processed', 'cfb_sklad_odecet');

function cfb_get_balicek_form($count) {
    if ($count == 1) return 'balíček';
    elseif ($count >= 2 && $count <= 4) return 'balíčky';
    else return 'balíčků';
}

add_filter('woocommerce_loop_add_to_cart_link', function($html, $product) {
    $is_bundle = get_post_meta($product->get_id(), '_cfb_is_bundle', true);
    if ($is_bundle == '1') {
        // Kontrola dostupnosti produktu
        if (!$product->is_in_stock()) {
            $product_url = get_permalink($product->get_id());
            $button = sprintf(
                '<a href="%s" class="button" style="color: #999; cursor: pointer;">%s</a>',
                esc_url($product_url),
                'Není skladem, čtěte více'
            );
        } else {
            $product_url = get_permalink($product->get_id());
            $button = sprintf(
                '<a href="%s" class="button">%s</a>',
                esc_url($product_url),
                'VÝBĚR MOŽNOSTÍ'
            );
        }
        return $button;
    }
    // Pro ostatní produkty vrať původní HTML tlačítko
    return $html;
}, 20, 2);