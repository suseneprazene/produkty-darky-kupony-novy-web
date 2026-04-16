jQuery(document).ready(function($){
    // Inicalizace Select2 pro produkty a kategorie v AGK
    $('.agk-coupon-product-search').select2({
        minimumInputLength: 2,
        allowClear: true,
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function(params){
                return {
                    term: params.term,
                    action: 'woocommerce_json_search_products_and_variations',
                    security: woocommerce_admin_meta_boxes.search_products_nonce
                };
            },
            processResults: function(data){
                return { results: $.map(data, function(text, id){ return {id:id, text:text}; }) };
            },
            cache: true
        }
    });
    $('.agk-coupon-category-search').select2({
        minimumInputLength: 2,
        allowClear: true,
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function(params){
                return {
                    term: params.term,
                    action: 'woocommerce_json_search_product_categories',
                    security: woocommerce_admin_meta_boxes.search_categories_nonce
                };
            },
            processResults: function(data){
                return { results: $.map(data, function(text, id){ return {id:id, text:text}; }) };
            },
            cache: true
        }
    });
});