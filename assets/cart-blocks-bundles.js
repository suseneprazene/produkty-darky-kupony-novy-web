/* Cart Block – injekce vybraných variant balíčku (cfb-cart-blocks-bundles) */
(function () {
    'use strict';

    var INJECTED_CLASS = 'cfb-block-cart-flavors';
    var DEBOUNCE_DELAY = 350; // ms
    var observer       = null;
    var debounceTimer  = null;
    var isInjecting    = false;

    /* -------------------------------------------------- */
    /* Helpers                                             */
    /* -------------------------------------------------- */

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function balicekForm(count) {
        if (count === 1) return 'balíček';
        if (count >= 2 && count <= 4) return 'balíčky';
        return 'balíčků';
    }

    /* -------------------------------------------------- */
    /* Store API – načtení cart items                      */
    /* -------------------------------------------------- */

    function getCartItems() {
        try {
            if (window.wp && window.wp.data) {
                var cartData = window.wp.data.select('wc/store/cart').getCartData();
                if (cartData && Array.isArray(cartData.items)) {
                    return cartData.items;
                }
            }
        } catch (e) { /* ignore */ }
        return [];
    }

    /* -------------------------------------------------- */
    /* DOM injekce                                         */
    /* -------------------------------------------------- */

    function injectFlavors() {
        if (isInjecting) return;
        isInjecting = true;

        if (observer) observer.disconnect();

        var cartItems = getCartItems();

        // Sestav seřazený seznam položek se selected_flavors (zachová pořadí z Store API)
        // Pořadí Store API items odpovídá pořadí .wc-block-cart-item řádků v DOM
        var flavorsList = [];
        cartItems.forEach(function (item) {
            var flavors = null;
            if (
                item.extensions &&
                item.extensions.cfb_flavor &&
                Array.isArray(item.extensions.cfb_flavor.selected_flavors) &&
                item.extensions.cfb_flavor.selected_flavors.length > 0
            ) {
                flavors = item.extensions.cfb_flavor.selected_flavors;
            }
            flavorsList.push(flavors); // null pro položky bez flavors
        });

        // Nejprve odstraň všechny stávající injektované elementy
        var existing = document.querySelectorAll('.' + INJECTED_CLASS);
        for (var i = 0; i < existing.length; i++) {
            existing[i].parentNode.removeChild(existing[i]);
        }

        if (!flavorsList.some(Boolean)) {
            resume();
            return;
        }

        // Projdi cart item řádky v DOM – index odpovídá indexu v Store API items
        var rows = document.querySelectorAll('.wc-block-cart-item');
        rows.forEach(function (row, idx) {
            var flavors = flavorsList[idx] || null;
            if (!flavors || flavors.length === 0) return;

            // Hledej product name element
            var nameEl = row.querySelector('.wc-block-components-product-name, .wc-block-cart-item__product-name');
            if (!nameEl) return;

            // Sestav HTML seznam
            var html = '<ul class="' + INJECTED_CLASS + '">';
            flavors.forEach(function (f) {
                var qty = parseInt(f.qty, 10);
                if (qty > 0) {
                    html += '<li>' + escapeHtml(f.name) + ': ' + qty + '\u00a0' + balicekForm(qty) + '</li>';
                }
            });
            html += '</ul>';

            // Vlož za název produktu
            var div = document.createElement('div');
            div.innerHTML = html;
            var list = div.firstChild;

            nameEl.parentNode.insertBefore(list, nameEl.nextSibling);
        });

        resume();
    }

    function resume() {
        isInjecting = false;
        var cartRoot = document.querySelector('.wp-block-woocommerce-cart');
        if (observer && cartRoot) {
            observer.observe(cartRoot, { childList: true, subtree: true });
        }
    }

    /* -------------------------------------------------- */
    /* MutationObserver pro reaktivní aktualizace          */
    /* -------------------------------------------------- */

    function startObserver() {
        if (observer) return;

        var cartRoot = document.querySelector('.wp-block-woocommerce-cart');
        if (!cartRoot) return;

        observer = new MutationObserver(function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(injectFlavors, DEBOUNCE_DELAY);
        });

        observer.observe(cartRoot, { childList: true, subtree: true });
    }

    /* -------------------------------------------------- */
    /* CSS styly                                           */
    /* -------------------------------------------------- */

    function injectStyles() {
        if (document.getElementById('cfb-block-cart-bundles-style')) return;
        var style = document.createElement('style');
        style.id = 'cfb-block-cart-bundles-style';
        style.textContent =
            '.' + INJECTED_CLASS + ' {' +
            '  margin: 4px 0 0 0;' +
            '  padding: 0;' +
            '  list-style: none;' +
            '  font-size: 0.85em;' +
            '  color: #555;' +
            '}' +
            '.' + INJECTED_CLASS + ' li {' +
            '  margin: 1px 0;' +
            '  padding: 0;' +
            '}';
        document.head.appendChild(style);
    }

    /* -------------------------------------------------- */
    /* Inicializace                                        */
    /* -------------------------------------------------- */

    function init() {
        if (!document.querySelector('.wp-block-woocommerce-cart')) return;
        injectStyles();
        injectFlavors();
        startObserver();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
