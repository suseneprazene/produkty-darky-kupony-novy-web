(function($){

    window.wcgift_rules = window.wcgift_rules || [];
    window.wcgift_roles = window.wcgift_roles || [];

    function fetchNamesForSelect($select, action) {
        var ids = $select.val() || [];
        if (!ids.length) return;
        $.ajax({
            url: ajaxurl,
            data: {
                action: action,
                ids: ids.join(','),
            },
            success: function(data){
                if (data && data.results) {
                    $select.empty();
                    data.results.forEach(function(obj){
                        $select.append($('<option>', { value: obj.id, text: obj.text, selected: true }));
                    });
                    $select.trigger('change.select2');
                }
            }
        });
    }

    function renderRules() {
        var $list = $('#wcgift-rules-list');
        $list.empty();
        if(window.wcgift_rules.length === 0){
            $list.append('<p>Žádná pravidla nejsou nastavena.</p>');
        }
        window.wcgift_rules.forEach(function(rule, i){
            $list.append(renderRule(rule, i));
        });
        bindRuleEvents();
        applySelect2();
    }

    function renderRule(rule, i){
        var min = rule.minimized ? 'wcgift-minimized' : '';
        var html = '<div class="wcgift-rule '+min+'" data-idx="'+i+'">';
        html += '<div class="wcgift-rule-header">';
        html += '<button type="button" class="wcgift-min-toggle" style="font-size:1.5em;line-height:1;width:32px;height:32px;padding:0;margin-right:8px;vertical-align:middle;" title="Minimalizovat">'+(rule.minimized ? '+' : '−')+'</button> ';
        html += '<input type="checkbox" class="wcgift-active" '+(rule.active!==false?'checked':'')+'> Aktivní &nbsp; ';
        html += '<strong>Pravidlo:</strong> <input type="text" class="wcgift-name" value="'+(rule.name||'')+'" style="width: 30%;"> ';
        html += '<span class="wcgift-delete" title="Smazat pravidlo" style="float:right;color:red;cursor:pointer;">&times;</span>';
        html += '</div>';

        html += '<div class="wcgift-rule-body" style="display:'+(rule.minimized?'none':'block')+';">';

        // Dárkové produkty
        html += '<div class="wcgift-row">';
        html += '<label class="wcgift-label"><b>Dárkové produkty:</b></label>';
        html += '<select class="wcgift-gifts wcgift-select" multiple data-placeholder="Vyhledej produkt nebo variantu">';
        if(rule.gifts && rule.gifts.length){
            rule.gifts.forEach(function(pid){
                html += '<option value="'+pid+'" selected>'+pid+'</option>';
            });
        }
        html += '</select>';
        html += '</div>';

        // Required products
        html += '<div class="wcgift-row">';
        html += '<input type="checkbox" class="wcgift-required-products-active wcgift-checkbox" '+(rule.required_products_active?'checked':'')+'>';
        html += '<label class="wcgift-label"><b>Podmínka: některý z těchto produktů v košíku:</b></label>';
        html += '<select class="wcgift-required-products wcgift-select" multiple data-placeholder="Vyhledej produkt" '+(rule.required_products_active?'':'disabled')+'>';
        if(rule.required_products && rule.required_products.length){
            rule.required_products.forEach(function(pid){
                html += '<option value="'+pid+'" selected>'+pid+'</option>';
            });
        }
        html += '</select>';
        html += '</div>';

        // Required categories
        html += '<div class="wcgift-row">';
        html += '<input type="checkbox" class="wcgift-required-categories-active wcgift-checkbox" '+(rule.required_categories_active?'checked':'')+'>';
        html += '<label class="wcgift-label"><b>Podmínka: některá z těchto kategorií v košíku:</b></label>';
        html += '<select class="wcgift-required-categories wcgift-select" multiple data-placeholder="Vyhledej kategorii" '+(rule.required_categories_active?'':'disabled')+'>';
        if(rule.required_categories && rule.required_categories.length){
            rule.required_categories.forEach(function(cid){
                html += '<option value="'+cid+'" selected>'+cid+'</option>';
            });
        }
        html += '</select>';
        html += '</div>';

        // Excluded products
        html += '<div class="wcgift-row">';
        html += '<input type="checkbox" class="wcgift-excluded-products-active wcgift-checkbox" '+(rule.excluded_products_active?'checked':'')+'>';
        html += '<label class="wcgift-label"><b>Vyloučit pokud v košíku:</b></label>';
        html += '<select class="wcgift-excluded-products wcgift-select" multiple data-placeholder="Vyhledej produkt" '+(rule.excluded_products_active?'':'disabled')+'>';
        if(rule.excluded_products && rule.excluded_products.length){
            rule.excluded_products.forEach(function(pid){
                html += '<option value="'+pid+'" selected>'+pid+'</option>';
            });
        }
        html += '</select>';
        html += '</div>';

        // Excluded categories
        html += '<div class="wcgift-row">';
        html += '<input type="checkbox" class="wcgift-excluded-categories-active wcgift-checkbox" '+(rule.excluded_categories_active?'checked':'')+'>';
        html += '<label class="wcgift-label"><b>Vyloučit pokud v košíku (kategorie):</b></label>';
        html += '<select class="wcgift-excluded-categories wcgift-select" multiple data-placeholder="Vyhledej kategorii" '+(rule.excluded_categories_active?'':'disabled')+'>';
        if(rule.excluded_categories && rule.excluded_categories.length){
            rule.excluded_categories.forEach(function(cid){
                html += '<option value="'+cid+'" selected>'+cid+'</option>';
            });
        }
        html += '</select>';
        html += '</div>';

        // Min total
        html += '<div class="wcgift-row">';
        html += '<input type="checkbox" class="wcgift-min-total-active wcgift-checkbox" '+(rule.min_total_active?'checked':'')+'>';
        html += '<label class="wcgift-label"><b>Minimální hodnota košíku:</b></label>';
        html += '<input type="number" min="0" class="wcgift-min-total wcgift-input-number" value="'+(rule.min_total||'')+'" '+(rule.min_total_active?'':'disabled')+'> Kč';
        html += '</div>';

        // Max gifts
        html += '<div class="wcgift-row">';
        html += '<label class="wcgift-label"><b>Maximální počet dárků na objednávku:</b></label>';
        html += '<input type="number" min="1" class="wcgift-max-gifts wcgift-input-number" value="'+(rule.max_gifts||1)+'">';
        html += '</div>';

        // Note
        html += '<div class="wcgift-row">';
        html += '<label class="wcgift-label"><b>Poznámka k dárku:</b></label>';
        html += '<input type="text" class="wcgift-note wcgift-note" value="'+(rule.note||'')+'" style="width:50%;">';
        html += '</div>';

        // Validity dates
        html += '<div class="wcgift-row">';
        html += '<label class="wcgift-label"><b>Platnost od:</b></label>';
        html += '<input type="date" class="wcgift-date-from wcgift-date" value="'+(rule.date_from||'')+'">';
        html += '<label class="wcgift-label"><b>do:</b></label>';
        html += '<input type="date" class="wcgift-date-to wcgift-date" value="'+(rule.date_to||'')+'">';
        html += '</div>';

        // Roles
        html += '<div class="wcgift-row">';
        html += '<label class="wcgift-label"><b>Pouze pro role:</b></label>';
        html += '<select class="wcgift-roles wcgift-select" multiple>';
        window.wcgift_roles.forEach(function(role){
            var sel = (rule.roles||[]).indexOf(role)!==-1 ? 'selected':'';
            html += '<option value="'+role+'" '+sel+'>'+role+'</option>';
        });
        html += '</select>';
        html += '</div>';

        // Other checkboxes
        html += '<div class="wcgift-row">';
        html += '<label><input type="checkbox" class="wcgift-first-purchase" '+(rule.first_purchase_only?'checked':'')+'> Pouze pro první nákup</label> &nbsp; ';
        html += '<label><input type="checkbox" class="wcgift-exclude-coupon" '+(rule.exclude_if_coupon?'checked':'')+'> Nevztahuje se pokud je použit kupon</label> &nbsp; ';
        html += '<label><input type="checkbox" class="wcgift-hide-catalog" '+(rule.hide_gift_in_catalog?'checked':'')+'> Skrýt dárek v katalogu</label>';
        html += '</div>';

        html += '</div></div>';
        return html;
    }

    function bindRuleEvents(){
        $('.wcgift-rule .wcgift-delete').off('click').on('click', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            window.wcgift_rules.splice(idx, 1);
            renderRules();
        });
        $('.wcgift-rule .wcgift-active').off('change').on('change', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            window.wcgift_rules[idx].active = this.checked;
        });
        $('.wcgift-rule .wcgift-min-toggle').off('click').on('click', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            window.wcgift_rules[idx].minimized = !window.wcgift_rules[idx].minimized;
            renderRules();
        });
        $('.wcgift-rule .wcgift-name').off('input').on('input', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            window.wcgift_rules[idx].name = $(this).val();
        });

        // Aktivace podmínek - zaškrtnutý = aktivní; odškrtnutý = pole disabled a podmínka se nevyhodnocuje
        $('.wcgift-rule .wcgift-required-products-active').off('change').on('change', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            var checked = this.checked;
            window.wcgift_rules[idx].required_products_active = checked;
            renderRules();
        });
        $('.wcgift-rule .wcgift-required-categories-active').off('change').on('change', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            var checked = this.checked;
            window.wcgift_rules[idx].required_categories_active = checked;
            renderRules();
        });
        $('.wcgift-rule .wcgift-excluded-products-active').off('change').on('change', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            var checked = this.checked;
            window.wcgift_rules[idx].excluded_products_active = checked;
            renderRules();
        });
        $('.wcgift-rule .wcgift-excluded-categories-active').off('change').on('change', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            var checked = this.checked;
            window.wcgift_rules[idx].excluded_categories_active = checked;
            renderRules();
        });
        $('.wcgift-rule .wcgift-min-total-active').off('change').on('change', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            var checked = this.checked;
            window.wcgift_rules[idx].min_total_active = checked;
            renderRules();
        });

        // Výběry
        $('.wcgift-rule .wcgift-gifts').off().each(function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            var $sel = $(this);
            $sel.select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params){ return { term: params.term, action: 'wcgift_search_products' }; },
                    processResults: function(data){ return data; },
                },
                minimumInputLength: 2,
                width: '60%',
                allowClear: true,
                placeholder: "Vyhledej produkt nebo variantu"
            }).on('change', function(){
                window.wcgift_rules[idx].gifts = $(this).val() || [];
            });
            fetchNamesForSelect($sel, 'wcgift_search_products');
        });
        $('.wcgift-rule .wcgift-required-products').off().each(function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            var $sel = $(this);
            $sel.select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params){ return { term: params.term, action: 'wcgift_search_products' }; },
                    processResults: function(data){ return data; },
                },
                minimumInputLength: 2,
                width: '60%',
                allowClear: true,
                placeholder: "Vyhledej produkt"
            }).on('change', function(){
                window.wcgift_rules[idx].required_products = $(this).val() || [];
            });
            fetchNamesForSelect($sel, 'wcgift_search_products');
        });
        $('.wcgift-rule .wcgift-required-categories').off().each(function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            var $sel = $(this);
            $sel.select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params){ return { term: params.term, action: 'wcgift_search_categories' }; },
                    processResults: function(data){ return data; },
                },
                minimumInputLength: 2,
                width: '60%',
                allowClear: true,
                placeholder: "Vyhledej kategorii"
            }).on('change', function(){
                window.wcgift_rules[idx].required_categories = $(this).val() || [];
            });
            fetchNamesForSelect($sel, 'wcgift_search_categories');
        });
        $('.wcgift-rule .wcgift-excluded-products').off().each(function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            var $sel = $(this);
            $sel.select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params){ return { term: params.term, action: 'wcgift_search_products' }; },
                    processResults: function(data){ return data; },
                },
                minimumInputLength: 2,
                width: '60%',
                allowClear: true,
                placeholder: "Vyhledej produkt"
            }).on('change', function(){
                window.wcgift_rules[idx].excluded_products = $(this).val() || [];
            });
            fetchNamesForSelect($sel, 'wcgift_search_products');
        });
        $('.wcgift-rule .wcgift-excluded-categories').off().each(function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            var $sel = $(this);
            $sel.select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params){ return { term: params.term, action: 'wcgift_search_categories' }; },
                    processResults: function(data){ return data; },
                },
                minimumInputLength: 2,
                width: '60%',
                allowClear: true,
                placeholder: "Vyhledej kategorii"
            }).on('change', function(){
                window.wcgift_rules[idx].excluded_categories = $(this).val() || [];
            });
            fetchNamesForSelect($sel, 'wcgift_search_categories');
        });

        $('.wcgift-rule .wcgift-min-total').off('input').on('input', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            window.wcgift_rules[idx].min_total = $(this).val();
        });
        $('.wcgift-rule .wcgift-max-gifts').off('input').on('input', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            window.wcgift_rules[idx].max_gifts = $(this).val();
        });
        $('.wcgift-rule .wcgift-note').off('input').on('input', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            window.wcgift_rules[idx].note = $(this).val();
        });
        $('.wcgift-rule .wcgift-date-from').off('change').on('change', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            window.wcgift_rules[idx].date_from = $(this).val();
        });
        $('.wcgift-rule .wcgift-date-to').off('change').on('change', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            window.wcgift_rules[idx].date_to = $(this).val();
        });
        $('.wcgift-rule .wcgift-roles').off().each(function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            $(this).select2({
                width: '50%',
                allowClear: true,
                placeholder: "Vyber role"
            }).on('change', function(){
                window.wcgift_rules[idx].roles = $(this).val() || [];
            });
        });
        $('.wcgift-rule .wcgift-first-purchase').off('change').on('change', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            window.wcgift_rules[idx].first_purchase_only = this.checked;
        });
        $('.wcgift-rule .wcgift-exclude-coupon').off('change').on('change', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            window.wcgift_rules[idx].exclude_if_coupon = this.checked;
        });
        $('.wcgift-rule .wcgift-hide-catalog').off('change').on('change', function(){
            var idx = $(this).closest('.wcgift-rule').data('idx');
            window.wcgift_rules[idx].hide_gift_in_catalog = this.checked;
        });
    }

    function applySelect2(){
        $('.wcgift-rule select').each(function(){
            if (!$(this).hasClass('select2-hidden-accessible') && $(this).attr('multiple')) {
                $(this).select2({ width: '60%', allowClear: true });
            }
        });
    }

    $('#wcgift-add-rule').on('click', function(){
        window.wcgift_rules.push({
            name: '',
            active: true,
            minimized: true,
            gifts: [],
            required_products: [],
            required_products_active: false,
            required_categories: [],
            required_categories_active: false,
            excluded_products: [],
            excluded_products_active: false,
            excluded_categories: [],
            excluded_categories_active: false,
            min_total: '',
            min_total_active: false,
            max_gifts: 1,
            note: '',
            date_from: '',
            date_to: '',
            roles: [],
            first_purchase_only: false,
            exclude_if_coupon: false,
            hide_gift_in_catalog: false
        });
        renderRules();
    });

    $('#pdk_gift_rules_form').on('submit', function(){
        window.wcgift_rules.forEach(function(rule){
            if (!rule.required_products_active) rule.required_products = [];
            if (!rule.required_categories_active) rule.required_categories = [];
            if (!rule.excluded_products_active) rule.excluded_products = [];
            if (!rule.excluded_categories_active) rule.excluded_categories = [];
            if (!rule.min_total_active) rule.min_total = '';
        });
        $('#pdk_gift_rules_data').val(JSON.stringify(window.wcgift_rules));
    });

    $(document).ready(function(){
        for(var i=0;i<window.wcgift_rules.length;i++){
            if(typeof window.wcgift_rules[i].minimized === 'undefined') window.wcgift_rules[i].minimized = true;
            if(typeof window.wcgift_rules[i].required_products_active === 'undefined') window.wcgift_rules[i].required_products_active = false;
            if(typeof window.wcgift_rules[i].required_categories_active === 'undefined') window.wcgift_rules[i].required_categories_active = false;
            if(typeof window.wcgift_rules[i].excluded_products_active === 'undefined') window.wcgift_rules[i].excluded_products_active = false;
            if(typeof window.wcgift_rules[i].excluded_categories_active === 'undefined') window.wcgift_rules[i].excluded_categories_active = false;
            if(typeof window.wcgift_rules[i].min_total_active === 'undefined') window.wcgift_rules[i].min_total_active = false;
        }
        renderRules();
    });

})(jQuery);