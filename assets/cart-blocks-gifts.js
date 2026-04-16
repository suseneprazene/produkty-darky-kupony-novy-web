/* Cart Block – injekce dárkového UI (pdk-cart-blocks-gifts) */
(function () {
    'use strict';

    if (typeof pdk_blocks_gifts === 'undefined') return;

    var WRAPPER_ID    = 'pdk-free-gifts';
    var WRAPPER_CLASS = 'pdk-free-gifts-wrapper';
    var DEBOUNCE_DELAY = 350; // ms
    var RULE_NAME_PREFIX = 'wcgift_choice_';
    var observer      = null;
    var debounceTimer = null;
    var isLoading     = false;

    /* -------------------------------------------------- */
    /* DOM helpers                                         */
    /* -------------------------------------------------- */

    function findInsertContainer() {
        // Vložit UVNITŘ levého sloupce Cart Block, nikoliv za něj jako sourozenec
        return (
            document.querySelector('.wc-block-cart__main') ||
            document.querySelector('.wp-block-woocommerce-cart-items-block')
        );
    }

    function insertWrapper(html) {
        var existing = document.getElementById(WRAPPER_ID);
        if (existing) {
            if (html) {
                existing.innerHTML = html;
            } else {
                existing.parentNode.removeChild(existing);
            }
            return;
        }
        if (!html) return;

        var container = findInsertContainer();
        if (!container) return;

        var wrapper       = document.createElement('div');
        wrapper.id        = WRAPPER_ID;
        wrapper.className = WRAPPER_CLASS;
        wrapper.innerHTML = html;

        container.appendChild(wrapper);
    }

    /* -------------------------------------------------- */
    /* Event binding (přidání dárku)                      */
    /* -------------------------------------------------- */

    function bindGiftEvents() {
        var wrapper = document.getElementById(WRAPPER_ID);
        if (!wrapper) return;

        // Odstraň staré listenery klonováním uzlu
        var fresh = wrapper.cloneNode(true);
        wrapper.parentNode.replaceChild(fresh, wrapper);

        // Aktivuj tlačítko po výběru rádia
        fresh.addEventListener('change', function (e) {
            if (e.target && e.target.type === 'radio') {
                var name = e.target.name;
                // Validace: name musí začínat prefixem a mít číselné ID
                if (name.indexOf(RULE_NAME_PREFIX) !== 0) return;
                var ruleId = name.slice(RULE_NAME_PREFIX.length);
                if (!/^\d+$/.test(ruleId)) return;
                var btn = fresh.querySelector('.wcgift-add-gift-to-cart[data-rule="' + ruleId + '"]');
                if (btn) btn.disabled = false;
            }
        });

        // Klik na "Přidat dárek"
        fresh.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.wcgift-add-gift-to-cart') : null;
            if (!btn) {
                // Fallback for IE-like environments
                var el = e.target;
                while (el && el !== fresh) {
                    if (el.classList && el.classList.contains('wcgift-add-gift-to-cart')) { btn = el; break; }
                    el = el.parentNode;
                }
            }
            if (!btn || btn.disabled) return;
            e.preventDefault();
            e.stopPropagation();

            var ruleId = btn.getAttribute('data-rule');
            var radio  = fresh.querySelector('input[name="wcgift_choice_' + ruleId + '"]:checked');
            if (!radio) {
                alert('Nejprve vyberte dárek.');
                return;
            }
            var pid = radio.value;
            btn.disabled = true;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', pdk_blocks_gifts.ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp && resp.success) {
                        // Spusti refresh WC Blocks store
                        if (window.wp && window.wp.data) {
                            try {
                                window.wp.data.dispatch('wc/store/cart').invalidateResolutionForStore();
                            } catch (err) { /* ignore */ }
                        }
                        loadAndInject();
                    } else {
                        var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Nepodařilo se přidat dárek.';
                        alert(msg);
                        btn.disabled = false;
                    }
                } catch (err) {
                    console.error('pdk-cart-blocks-gifts: Parse error on add', err);
                    btn.disabled = false;
                }
            };
            xhr.onerror = function () {
                alert('Chyba sítě – zkuste to prosím znovu.');
                btn.disabled = false;
            };
            xhr.send(
                'action=wcgift_choose_gift' +
                '&nonce='      + encodeURIComponent(pdk_blocks_gifts.choose_nonce) +
                '&product_id=' + encodeURIComponent(pid) +
                '&rule_idx='   + encodeURIComponent(ruleId)
            );
        });
    }

    /* -------------------------------------------------- */
    /* AJAX load + inject                                 */
    /* -------------------------------------------------- */

    function loadAndInject() {
        if (isLoading) return;
        isLoading = true;

        // Pozastavíme observer, aby injekce nevyvolala smyčku
        if (observer) observer.disconnect();

        var xhr = new XMLHttpRequest();
        xhr.open('POST', pdk_blocks_gifts.ajaxurl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        function resume() {
            isLoading = false;
            var cartRoot = document.querySelector('.wp-block-woocommerce-cart');
            if (observer && cartRoot) {
                observer.observe(cartRoot, { childList: true, subtree: true });
            }
        }

        xhr.onload = function () {
            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp && resp.success && resp.data && typeof resp.data.html !== 'undefined') {
                        insertWrapper(resp.data.html);
                        bindGiftEvents();
                    }
                } catch (e) {
                    console.error('pdk-cart-blocks-gifts: Failed to parse response', e);
                }
            }
            resume();
        };
        xhr.onerror = function () {
            console.error('pdk-cart-blocks-gifts: Network error');
            resume();
        };

        xhr.send(
            'action=pdk_gifts_render_cart_blocks' +
            '&nonce=' + encodeURIComponent(pdk_blocks_gifts.nonce)
        );
    }

    /* -------------------------------------------------- */
    /* MutationObserver pro reaktivní refresh             */
    /* -------------------------------------------------- */

    function startObserver() {
        if (observer) return;

        var cartRoot = document.querySelector('.wp-block-woocommerce-cart');
        if (!cartRoot) return;

        observer = new MutationObserver(function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadAndInject, DEBOUNCE_DELAY);
        });

        observer.observe(cartRoot, { childList: true, subtree: true });
    }

    /* -------------------------------------------------- */
    /* Inicializace                                        */
    /* -------------------------------------------------- */

    function init() {
        if (!document.querySelector('.wp-block-woocommerce-cart')) return;
        loadAndInject();
        startObserver();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
