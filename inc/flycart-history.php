<?php
// Flycart Vylepšení (debug do logu) – součást sjednoceného pluginu

add_action('wp_footer', function () {
    ?>
    <style>
    /* Úprava tlačítka Vymazat košík (velikost stejná jako ostatní tlačítka) */
    #clear-cart-btn.flycart-style-btn {
        display: inline-block;
        width: auto;
        background: #111;
        color: #fff;
        font-size: 16px;
        border: 2px solid #111;
        border-radius: 0;
        padding: 0.7em 1.7em;
        margin: 0.7em 0 0 0;
        text-align: center;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.2s, color 0.2s, border 0.2s;
        letter-spacing: 1px;
        text-transform: uppercase;
        font-family: inherit;
        line-height: 1.2;
        box-sizing: border-box;
    }
    #clear-cart-btn.flycart-style-btn:hover,
    #clear-cart-btn.flycart-style-btn:focus {
        background: #fff;
        color: #111;
        border: 2px solid #111;
        outline: none;
    }
    /* Styl pro ostatní tlačítka */
    .flycart-panel__footer button,
    .wpc-fly-cart__footer button,
    .woocommerce-mini-cart__buttons button {
        margin-bottom: 0.7em;
    }
    </style>
    <script>
    (function(){
        var panelSelector = '.wpc-fly-cart, .flycart-panel, .woocommerce-mini-cart';
        var openBtnSelector = '.wpc-fly-cart-toggle, .flycart-toggle, .mini-cart-toggle';
        var closeBtnSelector = '.wpc-fly-cart-close, .flycart-panel__close, .flycart-close, .close, .woocommerce-mini-cart__close';
        var overlaySelectors = [
            '.flycart-overlay',
            '.flycart-bg',
            '.wpc-fly-cart-overlay',
            '.woocommerce-mini-cart__overlay',
            '.cartify-mini-cart-overlay'
        ];

        function flycartLog(msg) {
            return; // Funkce bez efektu
        }

        function isFlycartOpen() {
            var panel = document.querySelector(panelSelector);
            if(!panel) {
                flycartLog('isFlycartOpen: FALSE (panel nenalezen)');
                return false;
            }
            var style = window.getComputedStyle(panel);
            var visible = style.visibility !== 'hidden' && style.opacity !== '0' && style.display !== 'none';
            var rect = panel.getBoundingClientRect();
            var size = rect.width > 30 && rect.height > 30;
            var open = visible && size;
            flycartLog('isFlycartOpen: ' + (open ? 'TRUE' : 'FALSE') + ' (vis: '+visible+' size: '+rect.width+'x'+rect.height+' class: '+panel.className+' style: '+panel.style.cssText+')');
            return open;
        }

        // Funkce openFlycartIfNeeded odstraněna
        document.body.removeEventListener('added_to_cart', openFlycartIfNeeded);
        if(typeof jQuery !== 'undefined') {
            jQuery(document.body).off('added_to_cart', openFlycartIfNeeded);
        }

        function addClearCartButton() {
            var panel = document.querySelector(panelSelector);
            if(!panel) return;
            if(panel.querySelector('#clear-cart-btn')) return;
            var target = panel.querySelector('.cart-actions, .actions, .wpc-fly-cart__footer, .flycart-panel__footer, .woocommerce-mini-cart__buttons');
            if(!target) target = panel;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.id = 'clear-cart-btn';
            btn.textContent = 'Vymazat košík';
            btn.className = 'flycart-style-btn';
            btn.onclick = function(e){
                e.preventDefault();
                flycartLog('Klik na Vymazat košík');
                if(confirm('Opravdu chcete vymazat celý košík?')) {
                    jQuery.post(woocommerce_params.ajax_url, {action: 'woocommerce_clear_cart'}, function(){
                        jQuery(document.body).trigger('wc_fragment_refresh');
                        flycartLog('Košík vymazán (AJAX)');
                    });
                }
            };
            target.appendChild(btn);
            flycartLog('Přidáno tlačítko Vymazat košík');
        }
        setInterval(addClearCartButton, 500);

        var lastPanelState = false;
        setInterval(function(){
            var isOpen = isFlycartOpen();
            if(isOpen && !lastPanelState) {
                history.pushState({flycart_open:true}, document.title, window.location.href);
                flycartLog('PUSHSTATE: Otevření panelu, push do historie');
            }
            if(!isOpen && lastPanelState) {
                flycartLog('Zavření panelu');
            }
            lastPanelState = isOpen;
        }, 500);

        window.addEventListener('popstate', function(e){
            var panel = document.querySelector(panelSelector);
            flycartLog('POPSTATE event! Flycart open? ' + isFlycartOpen());
            if(isFlycartOpen() && panel) {
                var closeBtn = panel.querySelector(closeBtnSelector);
                if(closeBtn) {
                    closeBtn.click();
                    flycartLog('Košík zavřený přes closeBtn');
                } else {
                    panel.classList.remove('active');
                    panel.classList.remove('is-open');
                    panel.style.display = 'none';
                    flycartLog('Košík zavřený přes třídu/display');
                }
                var overlayHidden = false;
                overlaySelectors.forEach(function(sel){
                    var overlay = document.querySelector(sel);
                    if(overlay) {
                        overlay.style.display = 'none';
                        overlay.classList.remove('active', 'is-open', 'visible', 'show');
                        flycartLog('Overlay "'+sel+'" schován');
                        overlayHidden = true;
                    }
                });
                document.documentElement.classList.remove('flycart-open');
                flycartLog('Třída flycart-open odebrána z <html>');
                if(!overlayHidden) flycartLog('Overlay nenašel!');
            }
        }, false);

        document.body.addEventListener('click', function(e){
            var el = e.target;
            if(el.matches(closeBtnSelector)) {
                flycartLog('Klik na zavírací tlačítko košíku ('+el.className+')');
            }
        });
    })();
    </script>
    <?php
});

add_action('wp_ajax_woocommerce_clear_cart', function(){
    WC()->cart->empty_cart();
    wp_send_json_success();
});
add_action('wp_ajax_nopriv_woocommerce_clear_cart', function(){
    WC()->cart->empty_cart();
    wp_send_json_success();
});