/* Cart Block – injekce dárkového UI (pdk-cart-blocks-gifts) */
(function () {
    'use strict';

    if (typeof pdk_blocks_gifts === 'undefined') return;

    var WRAPPER_ID       = 'pdk-free-gifts';
    var WRAPPER_CLASS    = 'pdk-free-gifts-wrapper';
    var RULE_NAME_PREFIX = 'wcgift_choice_';
    var isLoading        = false;
    var unsubscribe      = null;
    var lastCartSignature = null;

    /* -------------------------------------------------- */
    /* Cart signature – detekce skutečné změny košíku     */
    /* -------------------------------------------------- */

    function getCartSignature() {
        try {
            if (window.wp && window.wp.data) {
                var cartData = window.wp.data.select('wc/store/cart').getCartData();
                if (!cartData) return null;
                // Signatura = počet položek + jejich key+qty + celková cena
                var items = Array.isArray(cartData.items) ? cartData.items : [];
                var sig = items.map(function (i) {
                    return i.key + ':' + i.quantity;
                }).join(',');
                sig += '|' + (cartData.totals && cartData.totals.total_price ? cartData.totals.total_price : '');
                return sig;
            }
        } catch (e) { /* ignore */ }
        return null;
    }

    /* -------------------------------------------------- */
    /* DOM helpers                                         */
    /* -------------------------------------------------- */

    function findInsertContainer() {
        return (
            document.querySelector('.wc-block-cart__main') ||
            document.querySelector('.wp-block-woocommerce-cart-items-block')
        );
    }

    function insertWrapper(html) {
        var existing = document.getElementById(WRAPPER_ID);
        if (existing) {
            if (html) {
                if (existing.innerHTML !== html) {
                    existing.innerHTML = html;
                }
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

        var fresh = wrapper.cloneNode(true);
        wrapper.parentNode.replaceChild(fresh, wrapper);

        fresh.addEventListener('change', function (e) {
            if (e.target && e.target.type === 'radio') {
                var name = e.target.name;
                if (name.indexOf(RULE_NAME_PREFIX) !== 0) return;
                var ruleId = name.slice(RULE_NAME_PREFIX.length);
                if (!/^\d+$/.test(ruleId)) return;
                var btn = fresh.querySelector('.wcgift-add-gift-to-cart[data-rule="' + ruleId + '"]');
                if (btn) btn.disabled = false;
            }
        });

        fresh.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.wcgift-add-gift-to-cart') : null;
            if (!btn) {
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

        var xhr = new XMLHttpRequest();
        xhr.open('POST', pdk_blocks_gifts.ajaxurl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

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
            isLoading = false;
        };
        xhr.onerror = function () {
            console.error('pdk-cart-blocks-gifts: Network error');
            isLoading = false;
        };

        xhr.send(
            'action=pdk_gifts_render_cart_blocks' +
            '&nonce=' + encodeURIComponent(pdk_blocks_gifts.nonce)
        );
    }

    /* -------------------------------------------------- */
    /* Poslouchání změn košíku přes wp.data.subscribe     */
    /* Refresh se spustí POUZE při změně obsahu košíku.   */
    /* -------------------------------------------------- */

    function startStoreSubscription() {
        if (!window.wp || !window.wp.data) return false;

        unsubscribe = window.wp.data.subscribe(function () {
            var sig = getCartSignature();
            if (sig === null) return;          // store ještě není připraven
            if (sig === lastCartSignature) return; // košík se nezměnil – nic neděláme
            lastCartSignature = sig;
            loadAndInject();
        });

        return true;
    }

    /* -------------------------------------------------- */
    /* Inicializace                                        */
    /* -------------------------------------------------- */

    function init() {
        if (!document.querySelector('.wp-block-woocommerce-cart')) return;

        // Načti dárky hned při otevření stránky
        lastCartSignature = getCartSignature();
        loadAndInject();

        // Preferuj wp.data.subscribe (reaguje jen na skutečnou změnu košíku)
        if (!startStoreSubscription()) {
            // Fallback: wp.data není dostupné – zkus znovu za 1 s (store se načítá async)
            setTimeout(function () {
                if (!startStoreSubscription()) {
                    console.warn('pdk-cart-blocks-gifts: wp.data není dostupné, dárky se nebudou automaticky aktualizovat.');
                }
            }, 1000);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
