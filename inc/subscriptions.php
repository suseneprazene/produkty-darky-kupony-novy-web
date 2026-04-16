<?php
/*
* Součást sjednoceného pluginu: Správa předplatných WooCommerce
* (původní plugin: Admin Subscriptions)
*/

if (!defined('ABSPATH')) exit;

class PDK_Subs_Plugin {
    public function __construct() {
        // Pole v produktu pro předplatné
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_subscription_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_subscription_fields']);
        add_filter('woocommerce_product_single_add_to_cart_text', [$this, 'change_add_to_cart_text']);
        add_filter('woocommerce_product_add_to_cart_text', [$this, 'change_add_to_cart_text']);
        add_action('admin_init', [$this, 'handle_save_extra_fields']);
        add_action('admin_footer', [$this, 'admin_footer_js']);
        add_action('wp_ajax_pdk_subs_send_test_mail', [$this, 'send_test_mail']);
        // Cron: denně v 8:00
        add_action('pdk_subs_daily_cron_hook', [$this, 'daily_cron']);
        if (!wp_next_scheduled('pdk_subs_daily_cron_hook')) {
            wp_schedule_event(strtotime('08:00:00'), 'daily', 'pdk_subs_daily_cron_hook');
        }
        // Omezení dopravy v košíku dle předplatného
        add_filter('woocommerce_package_rates', [$this, 'restrict_shipping_methods_by_subscription'], 100, 2);
        // Deduplikace error hlášek a JS/CSS k popiskům dopravy
        add_action('wp_footer', [$this, 'shipping_notes_and_error_deduplication'], 10000);
    }

    // --- Řízení dopravy dle atributu "Doručení" u předplatného ---
    public function restrict_shipping_methods_by_subscription($rates, $package) {
        $zasilkovna_id = 'packetery_shipping_method:packetery_carrier_zpointcz';
        $osobni_id = 'free_shipping:1';
        $doruceni_predplatne = [];

        foreach ($package['contents'] as $item) {
            $attr = '';
            if (!empty($item['variation_id'])) {
                $attr = get_post_meta($item['variation_id'], 'attribute_doruceni', true);
            } else {
                $attr = get_post_meta($item['product_id'], 'attribute_doruceni', true);
            }
            if (!empty($attr)) {
                $clean = strtolower(trim($attr, "\"' \t\n\r\0\x0B"));
                $doruceni_predplatne[] = $clean;
            }
        }
        $doruceni_predplatne = array_unique($doruceni_predplatne);

        $zasilkovna_message = " Osobní odběr není pro Tebou vybrané předplatné možný. Pokud chceš produkt doručit osobně, toto předplatné zruš a přidej znovu do košíku s parametrem doručení „Osobní odběr“. ";
        $osobni_message = " Doručení Zásilkovnou není pro Tebou vybrané předplatné možná. Pokud chceš doručení Zásilkovnou, toto předplatné zruš a přidej znovu do košíku s parametrem doručení „Zásilkovnou“. ";

        if (count($doruceni_predplatne) > 0) {
            if (count($doruceni_predplatne) === 1 && in_array('zásilkovnou', $doruceni_predplatne)) {
                foreach ($rates as $rate_id => $rate) {
                    if ($rate_id === $zasilkovna_id) {
                        $rates[$rate_id]->cost = 0;
                        $rates[$rate_id]->taxes = [];
                        $rates[$rate_id]->label .= $zasilkovna_message;
                    } else {
                        unset($rates[$rate_id]);
                    }
                }
            } elseif (count($doruceni_predplatne) === 1 && in_array('osobně', $doruceni_predplatne)) {
                foreach ($rates as $rate_id => $rate) {
                    if ($rate_id === $osobni_id) {
                        $rates[$rate_id]->label .= $osobni_message;
                    } else {
                        unset($rates[$rate_id]);
                    }
                }
            } else {
                $unique_error = 'V košíku máte předplatné s různým způsobem doručení. Prosím, rozdělte objednávku podle způsobu doručení.';
                wc_add_notice($unique_error, 'error');
                $rates = [];
            }
        }
        return $rates;
    }

    // --- DEDUPLIKACE ERROR HLÁŠEK + JS/CSS ---
    public function shipping_notes_and_error_deduplication() {
        if (function_exists('wc_get_notices')) {
            $errors = wc_get_notices('error');
            if (is_array($errors) && count($errors) > 1) {
                $unique = [];
                foreach ($errors as $err) {
                    $msg = is_array($err) && isset($err['notice']) ? $err['notice'] : (string)$err;
                    if (!in_array($msg, $unique, true)) {
                        $unique[] = $msg;
                    }
                }
                wc_clear_notices();
                foreach ($unique as $msg) {
                    wc_add_notice($msg, 'error');
                }
            }
        }
        ?>
        <style>
            .custom-shipping-note-block {
                display: block;
                font-size: 0.93em !important;
                font-style: normal;
                font-weight: 400 !important;
                color: #111 !important;
                line-height: 1.5;
                margin-top: 4px;
                margin-bottom: 0;
                width: 100% !important;
                max-width: none !important;
                grid-column: 1 / -1;
            }
            .wc-block-components-radio-control__label-group {
                display: grid !important;
            }
        </style>
        <script>
        (function() {
            const zasilkovnaMsg = "Osobní odběr není pro Tebou vybrané předplatné možný. Pokud chceš produkt doručit osobně, toto předplatné zruš a přidej znovu do košíku s parametrem doručení „Osobní odběr“.";
            const osobniMsg = "Doručení Zásilkovnou není pro Tebou vybrané předplatné možná. Pokud chceš doručení Zásilkovnou, toto předplatné zruš a přidej znovu do košíku s parametrem doručení „Zásilkovnou“.";

            const zdarmaText = "ZDARMA";
            const predplatneText = "V RÁMCI PŘEDPLATNÉHO";

            function processLabels() {
                document.querySelectorAll('.wc-block-components-radio-control__label').forEach(function(el) {
                    if (el.textContent.includes(zasilkovnaMsg) && !el.innerHTML.includes('custom-shipping-note-block')) {
                        const full = el.textContent;
                        const base = full.substring(0, full.indexOf(zasilkovnaMsg));
                        el.innerHTML =
                            '<span>' + base.trim() + '</span>' +
                            '<span class="custom-shipping-note-block">' + zasilkovnaMsg + '</span>';
                    }
                    if (el.textContent.includes(osobniMsg) && !el.innerHTML.includes('custom-shipping-note-block')) {
                        const full = el.textContent;
                        const base = full.substring(0, full.indexOf(osobniMsg));
                        el.innerHTML =
                            '<span>' + base.trim() + '</span>' +
                            '<span class="custom-shipping-note-block">' + osobniMsg + '</span>';
                    }
                });
                document.querySelectorAll('.wc-block-components-radio-control__option').forEach(function(option) {
                    const label = option.querySelector('.wc-block-components-radio-control__label');
                    const secondary = option.querySelector('.wc-block-components-radio-control__secondary-label');
                    if (label && secondary && label.textContent.includes(zasilkovnaMsg)) {
                        secondary.querySelectorAll('.wc-block-checkout__shipping-option--free').forEach(function(span) {
                            if (span.textContent.trim().toUpperCase() === zdarmaText) {
                                span.textContent = predplatneText;
                            }
                        });
                    }
                });
                document.querySelectorAll('label.shipping_method').forEach(function(el) {
                    if (el.textContent.includes(zasilkovnaMsg)) {
                        const spans = el.querySelectorAll('span');
                        spans.forEach(function(span) {
                            if (span.textContent.trim().toUpperCase() === zdarmaText) {
                                span.textContent = predplatneText;
                            }
                        });
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                processLabels();
                const checkoutRoot = document.querySelector('#checkout-root, .wp-block-woocommerce-checkout, .wc-block-checkout, .wc-block-cart');
                if (checkoutRoot) {
                    const observer = new MutationObserver(function() {
                        processLabels();
                    });
                    observer.observe(checkoutRoot, { childList: true, subtree: true });
                }
            });
        })();
        </script>
        <?php
    }

    // --- Nastavení pole pro předplatné a frekvenci v produktu ---
    public function add_subscription_fields() {
        woocommerce_wp_checkbox([
            'id' => '_is_subscription',
            'label' => __('Produkt je předplatné', 'pdk-subs'),
            'desc_tip' => true,
            'description' => __('Zaškrtněte, pokud je tento produkt předplatné.', 'pdk-subs')
        ]);
        woocommerce_wp_select([
            'id' => '_aspi_frequency',
            'label' => __('Frekvence doručení', 'pdk-subs'),
            'description' => __('Jak často bude zákazník dostávat balíček?', 'pdk-subs'),
            'options' => [
                '' => '-- vyberte --',
                'monthly' => 'Měsíčně',
                'weekly' => 'Týdně',
                'quarterly' => 'Kvartálně',
                'variable' => 'Variabilní (nastaví se ručně v přehledu)'
            ]
        ]);
    }
    public function save_subscription_fields($post_id) {
        $is_sub = isset($_POST['_is_subscription']) ? 'yes' : 'no';
        update_post_meta($post_id, '_is_subscription', $is_sub);
        $freq = isset($_POST['_aspi_frequency']) ? sanitize_text_field($_POST['_aspi_frequency']) : '';
        update_post_meta($post_id, '_aspi_frequency', $freq);
    }

    // --- Určení typu předplatného z parametru produktu/poznámky ---
    public function get_subscription_type($item, $order) {
        $meta_data = $item->get_formatted_meta_data('_', true);
        foreach ($meta_data as $meta) {
            if (isset($meta->display_key) && $meta->display_key && 
                preg_match('/(kvartální|půlroční|roční)/ui', $meta->display_value, $m)) {
                return mb_strtolower($m[1]);
            }
        }
        $note = $order->get_customer_note();
        if (preg_match('/(kvartální|půlroční|roční)/ui', $note, $m)) {
            return mb_strtolower($m[1]);
        }
        return 'roční';
    }

    // --- Určení frekvence produktu ---
    public function get_subscription_frequency($item, $order, $order_id) {
        $freq = get_post_meta($item->get_product_id(), '_aspi_frequency', true);
        if ($freq == 'variable') {
            $freq = get_post_meta($order_id, '_aspi_frequency', true);
        }
        if (!$freq) $freq = 'monthly';
        return $freq;
    }

    // --- Generování dat doručení ---
    public function get_subscription_dates($start_day, $type, $frequency, $first_sent = false) {
        $year = date('Y');
        $dates = [];
        switch($type) {
            case 'kvartální': $months_length = 3; break;
            case 'půlroční': $months_length = 6; break;
            case 'roční': default: $months_length = 12; break;
        }
        $start_date = new DateTime($year . '-' . date('m') . '-' . str_pad($start_day,2,'0',STR_PAD_LEFT));
        if ($start_date < new DateTime()) $start_date->modify('+1 month');

        if ($frequency === 'monthly') {
            for($i=0;$i<$months_length;$i++) {
                $date = clone $start_date;
                $date->modify("+$i month");
                $dates[] = $date->format('Y-m-d');
            }
        }
        elseif ($frequency === 'weekly') {
            $weeks = $months_length * 4.34524;
            for($i=0;$i<round($weeks);$i++) {
                $date = clone $start_date;
                $date->modify("+$i week");
                $dates[] = $date->format('Y-m-d');
            }
        }
        elseif ($frequency === 'quarterly') {
            for($i=0;$i<$months_length;$i+=3) {
                $date = clone $start_date;
                $date->modify("+$i month");
                $dates[] = $date->format('Y-m-d');
            }
        }
        if ($first_sent && count($dates) > 1) {
            array_shift($dates);
        }
        return $dates;
    }

    // --- Přehled objednávek (pro admin menu) ---
    public function subscriptions_overview_page() {
        $args = [
            'limit'        => 50,
            'status'       => ['wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending'],
            'orderby'      => 'date',
            'order'        => 'DESC',
        ];
        $orders = wc_get_orders($args);

        echo '<div class="wrap"><h1>Přehled předplatných</h1>';

        echo '<table class="widefat fixed" style="margin-top:20px;"><thead>';
        echo '<tr>
            <th>Objednávka</th>
            <th>Zákazník</th>
            <th>Produkty (parametry)</th>
            <th>Frekvence</th>
            <th>Doprava</th>
            <th>Den v měsíci</th>
            <th>První balíček odeslán</th>
            <th>Poznámka</th>
            <th>Test e-mail</th>
        </tr></thead><tbody>';

        foreach ($orders as $order) {
            $items = $order->get_items();
            foreach ($items as $item) {
                $product = $item->get_product();
                if (!$product) continue;
                $is_sub = get_post_meta($product->get_id(), '_is_subscription', true);

                if ('yes' !== $is_sub && $product->is_type('variation')) {
                    $parent_id = $product->get_parent_id();
                    $is_sub_parent = get_post_meta($parent_id, '_is_subscription', true);
                    if ('yes' === $is_sub_parent) {
                        $is_sub = 'yes';
                    }
                }
                if ('yes' !== $is_sub) continue;

                $customer_name = $order->get_formatted_billing_full_name();
                $order_link = admin_url('post.php?post=' . $order->get_id() . '&action=edit');

                $params = [];
                if ($item->get_variation_id()) {
                    $variation = new WC_Product_Variation($item->get_variation_id());
                    $params = $variation->get_attributes();
                } else {
                    $params = $item->get_formatted_meta_data('_', true);
                }

                $shipping_method = $order->get_shipping_method();
                $den_v_mesici = get_post_meta($order->get_id(), '_aspi_den_v_mesici', true);
                $poznamka = get_post_meta($order->get_id(), '_aspi_poznamka', true);

                $type = $this->get_subscription_type($item, $order);
                $frequency = $this->get_subscription_frequency($item, $order, $order->get_id());

                $first_sent = get_post_meta($order->get_id(), '_aspi_first_sent', true);

                $test_mail_idx = get_post_meta($order->get_id(), '_aspi_test_mail_idx', true);
                if (!$test_mail_idx) $test_mail_idx = 0;

                echo '<tr>';
                echo '<td><a href="' . esc_url($order_link) . '">#' . $order->get_id() . '</a></td>';
                echo '<td>' . esc_html($customer_name) . '</td>';
                echo '<td>';
                foreach ($params as $key => $value) {
                    if (is_object($value) && method_exists($value, 'display_key')) {
                        echo esc_html($value->display_key) . ': ' . esc_html($value->display_value) . '<br>';
                    } else {
                        echo esc_html($key) . ': ' . esc_html($value) . '<br>';
                    }
                }
                echo '<div><strong>Typ předplatného:</strong> ' . esc_html($type) . '</div>';
                echo '</td>';
                echo '<td>';
                if ($frequency === 'variable') {
                    $current = get_post_meta($order->get_id(), '_aspi_frequency', true);
                    echo '<form class="aspi-edit-form" method="post" style="margin:0;">
                        <input type="hidden" name="aspi_order_id" value="' . esc_attr($order->get_id()) . '">
                        <select name="aspi_frequency">
                            <option value="">-- vyberte --</option>
                            <option value="monthly" '.selected($current, 'monthly', false).'>Měsíčně</option>
                            <option value="weekly" '.selected($current, 'weekly', false).'>Týdně</option>
                            <option value="quarterly" '.selected($current, 'quarterly', false).'>Kvartálně</option>
                        </select>
                        <input type="submit" class="button" name="aspi_save_frequency" value="Uložit">
                    </form>';
                } else {
                    $freqnames = ['monthly'=>'Měsíčně','weekly'=>'Týdně','quarterly'=>'Kvartálně','variable'=>'Variabilní'];
                    echo esc_html($freqnames[$frequency] ?? $frequency);
                }
                echo '</td>';

                echo '<td>' . esc_html($shipping_method) . '</td>';

                echo '<td>
                <form class="aspi-edit-form" method="post" style="margin:0;">
                    <input type="hidden" name="aspi_order_id" value="' . esc_attr($order->get_id()) . '">
                    <select name="aspi_den_v_mesici">
                        <option value="">--</option>';
                for ($i=1; $i<=30; $i++) {
                    echo '<option value="' . $i . '"' . selected($den_v_mesici, $i, false) . '>' . $i . '</option>';
                }
                echo '</select>
                    <input type="submit" class="button" name="aspi_save_den" value="Uložit">
                </form>
                </td>';

                echo '<td>
                <form class="aspi-edit-form" method="post" style="margin:0;">
                    <input type="hidden" name="aspi_order_id" value="' . esc_attr($order->get_id()) . '">
                    <input type="checkbox" name="aspi_first_sent" value="1" '.checked($first_sent, '1', false).'> První odesláno
                    <input type="submit" class="button" name="aspi_save_first_sent" value="Uložit">
                </form>
                </td>';

                echo '<td>
                <form class="aspi-edit-form" method="post" style="margin:0;">
                    <input type="hidden" name="aspi_order_id" value="' . esc_attr($order->get_id()) . '">
                    <textarea name="aspi_poznamka" rows="2" cols="16">' . esc_textarea($poznamka) . '</textarea>
                    <br>
                    <input type="submit" class="button" name="aspi_save_poznamka" value="Uložit">
                </form>
                </td>';

                echo '<td>
                    <button class="button aspi-send-test-mail" 
                        data-order="' . esc_attr($order->get_id()) . '" 
                        data-type="' . esc_attr($type) . '" 
                        data-den="' . esc_attr($den_v_mesici) . '"
                        data-mail-idx="' . esc_attr($test_mail_idx) . '"
                        data-first-sent="' . esc_attr($first_sent) . '"
                        data-frequency="' . esc_attr($frequency) . '"
                        >Odeslat testovací e-mail</button>
                </td>';

                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    public function admin_footer_js() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'toplevel_page_pdk-subs') {
            ?>
            <script>
            jQuery(document).ready(function($){
                $('.aspi-edit-form').on('submit', function(e){
                    var $form = $(this);
                    setTimeout(function(){
                        let d = $form.find('[name=aspi_den_v_mesici]').val();
                        if(d) {
                            var type = $form.closest('tr').find('.aspi-send-test-mail').data('type');
                            var frequency = $form.closest('tr').find('.aspi-send-test-mail').data('frequency');
                            if (!type) type = "roční";
                            if (!frequency) frequency = "monthly";
                            var year = (new Date()).getFullYear();
                            var months_length = 12;
                            if(type==="kvartální") months_length = 3;
                            if(type==="půlroční") months_length = 6;
                            var start = new Date(year, (new Date()).getMonth(), d);
                            if(start < new Date()) start.setMonth(start.getMonth()+1);
                            var dates = [];
                            if(frequency==="monthly") {
                                for(var i=0;i<months_length;i++) {
                                    var dt = new Date(start);
                                    dt.setMonth(start.getMonth()+i);
                                    dates.push(new Date(dt));
                                }
                            } else if(frequency==="weekly") {
                                var weeks = months_length * 4.34524;
                                for(var i=0;i<Math.round(weeks);i++) {
                                    var dt = new Date(start);
                                    dt.setDate(start.getDate()+i*7);
                                    dates.push(new Date(dt));
                                }
                            } else if(frequency==="quarterly") {
                                for(var i=0;i<months_length;i+=3) {
                                    var dt = new Date(start);
                                    dt.setMonth(start.getMonth()+i);
                                    dates.push(new Date(dt));
                                }
                            }
                            var first_sent = $form.closest('tr').find('[name=aspi_first_sent]').is(':checked');
                            if(first_sent && dates.length>1) dates.shift();
                            var info = "";
                            dates.forEach(function(dt){
                                var minus5 = new Date(dt);
                                minus5.setDate(dt.getDate()-5);
                                info += "Výročí: " + dt.toLocaleDateString('cs-CZ') + " (upozornění: " + minus5.toLocaleDateString('cs-CZ') + ")\n";
                            });
                            alert("Nastavení bylo uloženo.\nBalíček bude doručen v těchto termínech:\n" + info);
                        } else {
                            alert("Nastavení bylo uloženo.");
                        }
                    }, 150);
                });

                $('.aspi-send-test-mail').on('click', function(e){
                    e.preventDefault();
                    var $btn = $(this);
                    $btn.prop('disabled', true).text("Odesílám...");
                    $.post(ajaxurl, {
                        action: 'pdk_subs_send_test_mail',
                        order_id: $btn.data('order'),
                        type: $btn.data('type'),
                        den: $btn.data('den'),
                        mail_idx: $btn.data('mail-idx'),
                        first_sent: $btn.data('first-sent'),
                        frequency: $btn.data('frequency')
                    }, function(resp){
                        if(resp && resp.success) {
                            alert(resp.data);
                            location.reload();
                        } else {
                            alert("Něco se pokazilo!");
                        }
                    }).fail(function(){
                        alert("Něco se pokazilo při komunikaci se serverem!");
                    });
                });
            });
            </script>
            <?php
        }
    }

    public function handle_save_extra_fields() {
        if (isset($_POST['aspi_save_den']) && !empty($_POST['aspi_order_id'])) {
            $order_id = intval($_POST['aspi_order_id']);
            $den = isset($_POST['aspi_den_v_mesici']) ? intval($_POST['aspi_den_v_mesici']) : '';
            update_post_meta($order_id, '_aspi_den_v_mesici', $den);
            wp_redirect(remove_query_arg(array('aspi_save_den','aspi_order_id')));
            exit;
        }
        if (isset($_POST['aspi_save_frequency']) && !empty($_POST['aspi_order_id'])) {
            $order_id = intval($_POST['aspi_order_id']);
            $freq = isset($_POST['aspi_frequency']) ? sanitize_text_field($_POST['aspi_frequency']) : '';
            update_post_meta($order_id, '_aspi_frequency', $freq);
            wp_redirect(remove_query_arg(array('aspi_save_frequency','aspi_order_id')));
            exit;
        }
        if (isset($_POST['aspi_save_first_sent']) && !empty($_POST['aspi_order_id'])) {
            $order_id = intval($_POST['aspi_order_id']);
            $first_sent = isset($_POST['aspi_first_sent']) ? '1' : '';
            update_post_meta($order_id, '_aspi_first_sent', $first_sent);
            wp_redirect(remove_query_arg(array('aspi_save_first_sent','aspi_order_id')));
            exit;
        }
        if (isset($_POST['aspi_save_poznamka']) && !empty($_POST['aspi_order_id'])) {
            $order_id = intval($_POST['aspi_order_id']);
            $poznamka = isset($_POST['aspi_poznamka']) ? sanitize_textarea_field($_POST['aspi_poznamka']) : '';
            update_post_meta($order_id, '_aspi_poznamka', $poznamka);
            wp_redirect(remove_query_arg(array('aspi_save_poznamka','aspi_order_id')));
            exit;
        }
    }

    public function change_add_to_cart_text($text) {
        global $product;
        if (is_object($product) && 'yes' === get_post_meta($product->get_id(), '_is_subscription', true)) {
            return __('Přidat do košíku předplatné', 'pdk-subs');
        }
        return $text;
    }

    public function daily_cron() {
        $args = [
            'limit'        => -1,
            'status'       => ['wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending'],
        ];
        $orders = wc_get_orders($args);

        $today = date('Y-m-d');
        $admin_email = get_option('admin_email');
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) continue;
                $is_sub = get_post_meta($product->get_id(), '_is_subscription', true);
                if ('yes' !== $is_sub && $product->is_type('variation')) {
                    $parent_id = $product->get_parent_id();
                    $is_sub_parent = get_post_meta($parent_id, '_is_subscription', true);
                    if ('yes' === $is_sub_parent) {
                        $is_sub = 'yes';
                    }
                }
                if ('yes' !== $is_sub) continue;

                $den_v_mesici = get_post_meta($order->get_id(), '_aspi_den_v_mesici', true);
                if (!$den_v_mesici) continue;

                $first_sent = get_post_meta($order->get_id(), '_aspi_first_sent', true);
                $type = $this->get_subscription_type($item, $order);
                $frequency = $this->get_subscription_frequency($item, $order, $order->get_id());
                $dates = $this->get_subscription_dates($den_v_mesici, $type, $frequency, $first_sent);

                foreach ($dates as $date) {
                    $date_obj = DateTime::createFromFormat('Y-m-d', $date);
                    $date_obj->modify('-5 days');
                    $alert_date = $date_obj->format('Y-m-d');
                    if ($today == $alert_date) {
                        $customer_name = $order->get_formatted_billing_full_name();
                        $order_link = admin_url('post.php?post=' . $order->get_id() . '&action=edit');
                        $params = [];
                        if ($item->get_variation_id()) {
                            $variation = new WC_Product_Variation($item->get_variation_id());
                            $params = $variation->get_attributes();
                        } else {
                            $params = $item->get_formatted_meta_data('_', true);
                        }
                        $shipping_method = $order->get_shipping_method();

                        $subject = 'Připomínka výročí odeslání předplatného pro objednávku #' . $order->get_id();
                        $body = "Blíží se den pravidelného odeslání předplatného:\n\n";
                        $body .= "Objednávka: #" . $order->get_id() . " – " . $order_link . "\n";
                        $body .= "Zákazník: " . $customer_name . "\n";
                        $body .= "Doprava: " . $shipping_method . "\n";
                        $body .= "Den v měsíci: " . $den_v_mesici . "\n";
                        $body .= "Typ předplatného: " . $type . "\n";
                        $body .= "Frekvence: " . $frequency . "\n";
                        $body .= "Parametry produktu: \n";
                        foreach ($params as $key => $value) {
                            if (is_object($value) && method_exists($value, 'display_key')) {
                                $body .= $value->display_key . ': ' . $value->display_value . "\n";
                            } else {
                                $body .= $key . ': ' . $value . "\n";
                            }
                        }
                        $body .= "\nPoznámka: " . get_post_meta($order->get_id(), '_aspi_poznamka', true);
                        wp_mail($admin_email, $subject, $body);
                    }
                }
            }
        }
    }

    public function send_test_mail() {
        $order_id = intval($_POST['order_id'] ?? 0);
        $type = sanitize_text_field($_POST['type'] ?? 'roční');
        $den = intval($_POST['den'] ?? 1);
        $mail_idx = intval($_POST['mail_idx'] ?? 0);
        $first_sent = !empty($_POST['first_sent']) && $_POST['first_sent'] == '1';
        $frequency = sanitize_text_field($_POST['frequency'] ?? 'monthly');

        $dates = $this->get_subscription_dates($den, $type, $frequency, $first_sent);

        if (!$order_id || !$dates || !isset($dates[$mail_idx])) {
            wp_send_json_error();
        }
        $date = $dates[$mail_idx];

        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        $date_obj->modify('-5 days');
        $alert_date = $date_obj->format('Y-m-d');

        $order = wc_get_order($order_id);
        if (!$order) wp_send_json_error();

        $customer_name = $order->get_formatted_billing_full_name();
        $order_link = admin_url('post.php?post=' . $order->get_id() . '&action=edit');
        $shipping_method = $order->get_shipping_method();
        $params = [];
        foreach ($order->get_items() as $item) {
            if ($item->get_variation_id()) {
                $variation = new WC_Product_Variation($item->get_variation_id());
                $params = $variation->get_attributes();
            } else {
                $params = $item->get_formatted_meta_data('_', true);
            }
            break;
        }
        $subject = 'TEST: Připomínka výročí odeslání předplatného pro objednávku #' . $order->get_id();
        $body = "TEST! Blíží se den pravidelného odeslání předplatného:\n\n";
        $body .= "Objednávka: #" . $order->get_id() . " – " . $order_link . "\n";
        $body .= "Zákazník: " . $customer_name . "\n";
        $body .= "Doprava: " . $shipping_method . "\n";
        $body .= "Datum výročí: " . $date . "\n";
        $body .= "Odešle se upozornění: " . $alert_date . "\n";
        $body .= "Typ předplatného: " . $type . "\n";
        $body .= "Frekvence: " . $frequency . "\n";
        $body .= "Parametry produktu: \n";
        foreach ($params as $key => $value) {
            if (is_object($value) && method_exists($value, 'display_key')) {
                $body .= $value->display_key . ': ' . $value->display_value . "\n";
            } else {
                $body .= $key . ': ' . $value . "\n";
            }
        }
        $body .= "\nPoznámka: " . get_post_meta($order->get_id(), '_aspi_poznamka', true);

        $admin_email = get_option('admin_email');
        wp_mail($admin_email, $subject, $body);

        update_post_meta($order_id, '_aspi_test_mail_idx', $mail_idx+1);
        wp_send_json_success("Testovací e-mail odeslán na {$admin_email}.\nObsah:\n\n".$body);
    }
}

// Inicializace třídy
global $pdk_subs_plugin;
$pdk_subs_plugin = new PDK_Subs_Plugin();

// --- Callback pro admin menu stránku ---
function pdk_subs_page() {
    global $pdk_subs_plugin;
    $pdk_subs_plugin->subscriptions_overview_page();
}