(function($){
    window.rpr_rules = window.rpr_data.rules;
    if (!Array.isArray(window.rpr_rules)) window.rpr_rules = [];

    window.rpr_rules.forEach(function(rule){
        if (typeof rule.minimized === "undefined") rule.minimized = true;
        if (typeof rule.custom_discount_label === "undefined") rule.custom_discount_label = '';
        if (typeof rule.show_badge_discount === "undefined") rule.show_badge_discount = true;
        if (typeof rule.show_price_discount === "undefined") rule.show_price_discount = true;
    });

    function renderRules() {
        var $list = $('#rpr_rules_list');
        $list.empty();
        if(window.rpr_rules.length === 0){
            $list.append('<p>Žádná pravidla nejsou nastavena.</p>');
        }
        window.rpr_rules.forEach(function(rule, i){
            $list.append(renderRule(rule, i));
        });
        bindRuleEvents();
    }

    function renderRule(rule, i){
        var html = '<div class="rpr-rule'+(rule.active===false?' rpr-off':'')+'" data-idx="'+i+'">';
        html += '<span class="delete-btn" title="Smazat pravidlo">&times;</span>';
        html += '<span class="min-toggle" title="Minimalizovat">'+(rule.minimized ? '+' : '&#8211;')+'</span>';
        html += '<strong>Název pravidla:</strong> <input type="text" class="rpr-name" value="'+(rule.name||'')+'" style="width: 30%;"> ';
        html += '<label><input type="checkbox" class="rpr-active" '+(rule.active!==false?'checked':'')+'> Aktivní</label><br>';

        html += '<strong>Cílení:</strong> ';
        html += '<select class="rpr-target-type">';
        html += '<option value="role"'+(rule.target_type==="role"?' selected':'')+'>Role</option>';
        html += '<option value="email_domain"'+(rule.target_type==="email_domain"?' selected':'')+'>E-mailová doména</option>';
        html += '</select> ';
        html += '<span class="rpr-target-role"'+(rule.target_type!=="role"?' style="display:none;"':'')+'>';
        html += '<select class="rpr-role">';
        window.rpr_data.roles.forEach(function(role){
            html += '<option value="'+role+'"'+(rule.role===role?' selected':'')+'>'+role+'</option>';
        });
        html += '</select></span>';
        html += '<span class="rpr-target-domain"'+(rule.target_type!=="email_domain"?' style="display:none;"':'')+'>';
        html += '<input type="text" class="rpr-domain" placeholder="např. firma.cz" value="'+(rule.domain||'')+'"></span>';
        html += '<br>';

        html += '<strong>Typ slevy:</strong> ';
        html += '<select class="rpr-discount-type">';
        html += '<option value="cart_pct"'+(rule.discount_type==="cart_pct"?' selected':'')+'>% z košíku</option>';
        html += '<option value="cart_fixed"'+(rule.discount_type==="cart_fixed"?' selected':'')+'>Pevná částka z košíku</option>';
        html += '<option value="product_pct"'+(rule.discount_type==="product_pct"?' selected':'')+'>% z produktů</option>';
        html += '<option value="product_fixed"'+(rule.discount_type==="product_fixed"?' selected':'')+'>Pevná cena na produkt</option>';
        html += '</select> ';
        html += '<input type="number" step="any" class="rpr-value" style="width:80px;" value="'+(rule.value||'')+'"><br>';

        html += '<label style="margin-top:4px;display:block;">Vlastní text pro zvýraznění slevy: ';
        html += '<input type="text" class="rpr-custom-discount-label" style="width: 250px;" maxlength="64" placeholder="např. Entry Sleva!, Rodinná sleva..." value="'+(rule.custom_discount_label||'')+'">';
        html += ' <small style="color:#888;">(vlastní text slevy)</small></label>';

        // Checkboxy pro zobrazení slevy
        html += '<div style="margin-top:8px;">';
        html += '<label style="margin-right:15px;"><input type="checkbox" class="rpr-show-badge" '+(rule.show_badge_discount!==false?'checked':'')+'> Badge sleva v obrázku</label>';
        html += '<label><input type="checkbox" class="rpr-show-price" '+(rule.show_price_discount!==false?'checked':'')+'> Sleva u cenovky</label>';
        html += '</div>';

        html += '<div class="rpr-targets" style="display:'+(rule.minimized?'none':'block')+';">';

        html += '<div class="rpr-row"><label><input type="checkbox" class="rpr-all-products" '+(rule.all_products?'checked':'')+'> Uplatnit na všechny produkty</label></div>';
        if (!rule.all_products) {
            let selectedIncludeProducts = (rule.include_products||[]).map(String);
            let selectedExcludeProducts = (rule.exclude_products||[]).map(String);
            html += '<div class="rpr-row"><label class="rpr-label">Vybrané produkty: </label><select class="rpr-include-products" multiple style="min-width:220px;max-width:420px;">';
            window.rpr_data.products.forEach(function(prod){
                html += '<option value="'+prod.id+'"'+(selectedIncludeProducts.indexOf(String(prod.id))!==-1?' selected':'')+'>'+prod.name+'</option>';
            });
            html += '</select></div>';
            html += '<div class="rpr-row"><label class="rpr-label">Vynechat produkty: </label><select class="rpr-exclude-products" multiple style="min-width:220px;max-width:420px;">';
            window.rpr_data.products.forEach(function(prod){
                html += '<option value="'+prod.id+'"'+(selectedExcludeProducts.indexOf(String(prod.id))!==-1?' selected':'')+'>'+prod.name+'</option>';
            });
            html += '</select></div>';
        }

        html += '<div class="rpr-row"><label><input type="checkbox" class="rpr-all-categories" '+(rule.all_categories?'checked':'')+'> Uplatnit na všechny kategorie</label></div>';
        if (!rule.all_categories) {
            let selectedIncludeCategories = (rule.include_categories||[]).map(String);
            let selectedExcludeCategories = (rule.exclude_categories||[]).map(String);
            html += '<div class="rpr-row"><label class="rpr-label">Vybrané kategorie: </label><select class="rpr-include-categories" multiple style="min-width:220px;max-width:420px;">';
            window.rpr_data.categories.forEach(function(cat){
                html += '<option value="'+cat.id+'"'+(selectedIncludeCategories.indexOf(String(cat.id))!==-1?' selected':'')+'>'+cat.name+'</option>';
            });
            html += '</select></div>';
            html += '<div class="rpr-row"><label class="rpr-label">Vynechat kategorie: </label><select class="rpr-exclude-categories" multiple style="min-width:220px;max-width:420px;">';
            window.rpr_data.categories.forEach(function(cat){
                html += '<option value="'+cat.id+'"'+(selectedExcludeCategories.indexOf(String(cat.id))!==-1?' selected':'')+'>'+cat.name+'</option>';
            });
            html += '</select></div>';
        }

        html += '</div>';
        return html;
    }

    function bindRuleEvents(){
        $('.rpr-rule .delete-btn').off('click').on('click', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules.splice(idx, 1);
            renderRules();
        });
        $('.rpr-rule .rpr-active').off('change').on('change', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules[idx].active = this.checked;
            var $rule = $(this).closest('.rpr-rule');
            if (this.checked) $rule.removeClass('rpr-off');
            else $rule.addClass('rpr-off');
        });
        $('.rpr-rule .rpr-name').off('input').on('input', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules[idx].name = $(this).val();
        });
        $('.rpr-rule .rpr-target-type').off('change').on('change', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            var val = $(this).val();
            window.rpr_rules[idx].target_type = val;
            renderRules();
        });
        $('.rpr-rule .rpr-role').off('change').on('change', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules[idx].role = $(this).val();
        });
        $('.rpr-rule .rpr-domain').off('input').on('input', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules[idx].domain = $(this).val();
        });
        $('.rpr-rule .rpr-discount-type').off('change').on('change', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules[idx].discount_type = $(this).val();
            renderRules();
        });
        $('.rpr-rule .rpr-value').off('input').on('input', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules[idx].value = $(this).val();
        });
        $('.rpr-rule .rpr-custom-discount-label').off('input').on('input', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules[idx].custom_discount_label = $(this).val();
        });

        // Nové handlery pro checkboxy
        $('.rpr-rule .rpr-show-badge').off('change').on('change', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules[idx].show_badge_discount = this.checked;
        });
        $('.rpr-rule .rpr-show-price').off('change').on('change', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules[idx].show_price_discount = this.checked;
        });

        $('.rpr-rule .rpr-all-products').off('change').on('change', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules[idx].all_products = this.checked ? true : false;
            renderRules();
        });
        $('.rpr-rule .rpr-all-categories').off('change').on('change', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules[idx].all_categories = this.checked ? true : false;
            renderRules();
        });

        $('.rpr-rule .rpr-include-products').off('change').on('change', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules[idx].include_products = ($(this).val() || []).map(Number);
        });
        $('.rpr-rule .rpr-include-categories').off('change').on('change', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules[idx].include_categories = ($(this).val() || []).map(Number);
        });
        $('.rpr-rule .rpr-exclude-products').off('change').on('change', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules[idx].exclude_products = ($(this).val() || []).map(Number);
        });
        $('.rpr-rule .rpr-exclude-categories').off('change').on('change', function(){
            var idx = $(this).closest('.rpr-rule').data('idx');
            window.rpr_rules[idx].exclude_categories = ($(this).val() || []).map(Number);
        });
        $('.rpr-rule .min-toggle').off('click').on('click', function(){
            var $rule = $(this).closest('.rpr-rule');
            var idx = $rule.data('idx');
            window.rpr_rules[idx].minimized = !window.rpr_rules[idx].minimized;
            renderRules();
        });
    }

    $('#rpr_add_rule_btn').on('click', function(){
        window.rpr_rules.push({
            name: '',
            active: true,
            minimized: true,
            target_type: 'role',
            role: window.rpr_data.roles[0] || '',
            domain: '',
            discount_type: 'cart_pct',
            value: '',
            all_products: false,
            all_categories: false,
            include_products: [],
            include_categories: [],
            exclude_products: [],
            exclude_categories: [],
            custom_discount_label: '',
            show_badge_discount: true,
            show_price_discount: true
        });
        renderRules();
    });

    $(document).ready(function(){
        renderRules();
        $('#rpr_rules_form').on('submit', function(){
            $('#rpr_rules_data').val(JSON.stringify(window.rpr_rules));
        });
    });
})(jQuery);