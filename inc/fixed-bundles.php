<?php
// Zabezpečení souboru
if (!defined('ABSPATH')) exit;

// === Metabox pro pevné balíčky ===
function fb_register_metabox() {
    add_meta_box(
        'fb_fixed_bundles',
        'Pevné balíčky',
        'fb_fixed_bundles_callback',
        'product',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'fb_register_metabox');

function fb_fixed_bundles_callback($post) {
    wp_nonce_field('fb_save_fixed_bundle', 'fb_fixed_bundle_nonce');
    $is_fixed_bundle = get_post_meta($post->ID, '_fb_is_fixed_bundle', true);
    $fixed_items = get_post_meta($post->ID, '_fb_fixed_items', true) ?: [];

    ?>
    <p>
        <label>
            <input type="checkbox" name="fb_is_fixed_bundle" id="fb_is_fixed_bundle" <?php checked($is_fixed_bundle, '1'); ?> />
            Varianta, kdy si zákazník koupí předem stanovený balíček, nastavení hlídá, že jsou jednotlivé produkty skladem a že se ze skladu odečtou
        </label>
    </p>
    <div id="fb-fixed-items" style="display: <?php echo $is_fixed_bundle ? 'block' : 'none'; ?>;">
        <p><b>Produkty v balíčku:</b></p>
        <?php for ($i = 0; $i < 10; $i++): ?>
            <div class="fb-row" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
                <select class="fb-product-search" name="fb_fixed_items[<?php echo $i; ?>][product_id]" style="width: 33%;" data-placeholder="Vyberte produkt">
                    <?php if (!empty($fixed_items[$i]['product_id'])): 
                        $product = wc_get_product($fixed_items[$i]['product_id']);
                        if ($product): ?>
                            <option value="<?php echo esc_attr($product->get_id()); ?>" selected>
                                <?php echo esc_html($product->get_name()); ?>
                            </option>
                        <?php endif; ?>
                    <?php endif; ?>
                </select>
                <input type="number" name="fb_fixed_items[<?php echo $i; ?>][quantity]" placeholder="Počet" value="<?php echo esc_attr($fixed_items[$i]['quantity'] ?? ''); ?>" style="width: 80px; text-align: center;" min="1">
            </div>
        <?php endfor; ?>
    </div>
    <p><b>Vygenerovaný HTML shortcode (vložte do popisu produktu):</b></p>
    <div style="display: flex; align-items: center; gap: 10px; max-width: 50%;">
        <textarea readonly id="fb_shortcode" rows="2" style="flex: 1; background-color: #f9f9f9; border: 1px solid #ddd; resize: none;"><?php echo fb_generate_bundle_preview_shortcode_raw($post->ID); ?></textarea>
        <button type="button" id="fb_copy_button" style="background-color: #007cba; color: #fff; padding: 8px 15px; border: none; cursor: pointer; white-space: nowrap;">Kopírovat</button>
    </div>
    <script>
        jQuery(document).ready(function($) {
            // Zobrazení/skrytí polí pro balíček
            $('#fb_is_fixed_bundle').change(function() {
                $('#fb-fixed-items').toggle(this.checked);
            });

            // Inicializace Select2 pro vyhledávání produktů
            $('.fb-product-search').select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'fb_search_products',
                            term: params.term
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 2,
                placeholder: 'Vyberte produkt'
            });

            // Kopírování shortcodu
            $('#fb_copy_button').click(function(e) {
                e.preventDefault();
                var shortcodeTextarea = document.getElementById('fb_shortcode');
                shortcodeTextarea.select();
                shortcodeTextarea.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(shortcodeTextarea.value.trim()).then(function() {
                    alert('Shortcode byl zkopírován: ' + shortcodeTextarea.value.trim());
                });
            });
        });
    </script>
    <?php
}
// === Připojení Select2 v administraci ===
add_action('admin_enqueue_scripts', function($hook) {
    if ('post.php' !== $hook && 'post-new.php' !== $hook) {
        return;
    }

    global $post;
    if ($post && 'product' === $post->post_type) {
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
    }
});

// === Uložení pevného balíčku ===
function fb_save_fixed_bundle($post_id) {
    if (!isset($_POST['fb_fixed_bundle_nonce']) || !wp_verify_nonce($_POST['fb_fixed_bundle_nonce'], 'fb_save_fixed_bundle')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (isset($_POST['fb_is_fixed_bundle'])) {
        update_post_meta($post_id, '_fb_is_fixed_bundle', '1');
        $fixed_items = array_filter($_POST['fb_fixed_items'], function($item) {
            return !empty($item['product_id']) && !empty($item['quantity']);
        });
        update_post_meta($post_id, '_fb_fixed_items', $fixed_items);
    } else {
        delete_post_meta($post_id, '_fb_is_fixed_bundle');
        delete_post_meta($post_id, '_fb_fixed_items');
    }
}
add_action('save_post', 'fb_save_fixed_bundle');

// === Vyhledávání produktů s pomocí AJAX (opraveno pro přesné vyhledávání) ===
add_action('wp_ajax_fb_search_products', 'fb_search_products');
function fb_search_products() {
    if (!current_user_can('edit_products')) {
        wp_send_json([]);
    }

    $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
    if (empty($term)) {
        wp_send_json([]);
    }

    // Vyhledávání pouze v názvech produktů pomocí WP_Query
    $args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 30,
        's' => $term, // Vyhledávání v názvu
    ];

    $query = new WP_Query($args);
    $results = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $product = wc_get_product(get_the_ID());
            
            $results[] = [
                'id' => $product->get_id(),
                'text' => sanitize_text_field($product->get_name() . ' - #' . $product->get_id()),
            ];

            // Pokud je produkt variabilní, přidáme varianty
            if ($product->is_type('variable')) {
                $children = $product->get_children();
                foreach ($children as $child_id) {
                    $variation = wc_get_product($child_id);
                    if ($variation) {
                        $variation_name = wp_strip_all_tags($variation->get_formatted_name());
                        $results[] = [
                            'id' => $variation->get_id(),
                            'text' => sanitize_text_field($variation_name . ' - #' . $variation->get_id()),
                        ];
                    }
                }
            }
        }
    }
    wp_reset_postdata();

    wp_send_json($results);
}

// === Rychlý náhled produktů (AJAX) ===
add_action('wp_ajax_fb_quick_view', 'fb_quick_view');
add_action('wp_ajax_nopriv_fb_quick_view', 'fb_quick_view');

function fb_quick_view() {
    if (!isset($_GET['product_id'])) {
        wp_send_json_error(['message' => 'Neplatné ID produktu.']);
    }

    $product_id = intval($_GET['product_id']);
    $product = wc_get_product($product_id);

    if (!$product) {
        wp_send_json_error(['message' => 'Produkt nebyl nalezen.']);
    }

    // Pokud jde o variantu produktu, načteme hlavní produkt
    if ($product->is_type('variation')) {
        $parent_id = $product->get_parent_id();
        $product = wc_get_product($parent_id);
    }

    // Načtení krátkého popisu aktuálního produktu
    $description = $product->get_short_description();
    if (empty($description)) {
        $description = $product->get_description();
    }
    $description = $description ?: '<p>Popis produktu není dostupný.</p>';

    // Generován�� HTML obsahu
    ob_start();
    echo '<div style="text-align: center;">';
    echo '<h2>' . esc_html($product->get_name()) . '</h2>';
    echo $product->get_image('medium', ['style' => 'max-width: 200px; height: auto; margin: 10px auto;']);
    echo '<div style="margin-top: 10px;">' . wp_kses_post($description) . '</div>';
    echo '</div>';

    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}

// === Připojení JavaScriptu ===
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('fb-quick-view', plugin_dir_url(__FILE__) . 'js/fb-quick-view.js', ['jquery'], null, true);

    wp_localize_script('fb-quick-view', 'fbQuickView', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
    ]);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'fb-modal-styles',
        plugin_dir_url(__FILE__) . '../assets/modal-styles.css',
        [],
        null
    );
});

// === Shortcode pro bundle ===
function fb_generate_bundle_preview_shortcode_raw($product_id) {
    return '[fb_bundle_preview id="' . esc_attr($product_id) . '"]';
}

add_shortcode('fb_bundle_preview', function($atts) {
    $atts = shortcode_atts(['id' => 0], $atts, 'fb_bundle_preview');
    $product_id = intval($atts['id']);
    $fixed_items = get_post_meta($product_id, '_fb_fixed_items', true) ?: [];

    if (empty($fixed_items)) {
        return '<p>Žádné produkty k zobrazení.</p>';
    }

    ob_start();
    echo '<div class="fb-bundle-preview" style="display: flex; gap: 15px; flex-wrap: wrap;">'; 
    $index = 0;
    foreach ($fixed_items as $item) {
        $product = wc_get_product($item['product_id']);
        if ($product) {
            $product_permalink = get_permalink($product->get_id());
            echo '<div class="fb-preview-item" style="width: calc(33.333% - 10px); text-align: center; margin-bottom: 15px; cursor: pointer;" data-product-id="' . esc_attr($product->get_id()) . '" data-product-url="' . esc_url($product_permalink) . '" data-index="' . $index . '">';
            echo $product->get_image('thumbnail', ['style' => 'max-width: 100%; height: auto; display: block; margin: 0 auto;']);
            echo '<p style="font-size: 14px;">' . esc_html($product->get_name()) . '</p>';
            echo '</div>';
            $index++;
        }
    }
    echo '</div>';

    // HTML modálního okna s navigačními šipkami
    echo '
    <div id="fb-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); z-index: 9999; text-align: center; max-width: 500px; border-radius: 10px;">
        <button id="fb-modal-prev" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); background: #000; color: #fff; border: none; padding: 10px 15px; cursor: pointer; font-size: 18px; border-radius: 3px;">←</button>
        <div id="fb-modal-content" style="font-size: 16px; color: #333;"></div>
        <button id="fb-modal-next" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: #000; color: #fff; border: none; padding: 10px 15px; cursor: pointer; font-size: 18px; border-radius: 3px;">→</button>
        <button id="fb-modal-close" style="margin-top: 10px; background: #000; color: #fff; padding: 10px 20px; border: none; cursor: pointer; border-radius: 0px; font-weight: bold;">Zavřít</button>
    </div>
    ';
    return ob_get_clean();
});
?>