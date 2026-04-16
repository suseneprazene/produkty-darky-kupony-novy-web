jQuery(function($){
    function updateGiftButton() {
        var checkedRadio = $('.cart-gift-row input[type=radio]:checked');
        $('.wcgift-add-gift-to-cart').prop('disabled', checkedRadio.length === 0);
    }
    updateGiftButton();
    $(document).on('change', '.cart-gift-row input[type=radio]', function(){
        updateGiftButton();
    });
    $(document).on('click', '.wcgift-add-gift-to-cart', function(e){
        e.preventDefault();
        var pid = $('.cart-gift-row input[type=radio]:checked').val();
        var rule_idx = $(this).data('rule');
        if (!pid) {
            alert('Nejprve vyberte dárek.');
            return;
        }
        $.post(wcgift_ajax.ajaxurl, {
            action: 'wcgift_choose_gift',
            rule_idx: rule_idx,
            product_id: pid
        }, function(resp){
            location.reload();
        });
    });
    $(document).on('click', '.wcgift-modal-link', function(e){
        e.preventDefault();
        var product_id = $(this).data('product_id');
        var product_url = $(this).attr('href');
        $('#wcgift-modal-content').html('<div style="text-align:center;padding:2em 0;">Načítám…</div>');
        $('#wcgift-modal-bg').show();
        $.post(wcgift_ajax.ajaxurl, {
            action: 'wcgift_quickview',
            product_id: product_id
        }, function(res){
            $('#wcgift-modal-content').html(res +
                '<div style="margin-top:2em; text-align:right;">' +
                '<a href="'+product_url+'" target="_blank" class="button" style="margin-right:1em;">Otevřít produkt</a>' +
                '<button id="wcgift-modal-close" class="button" type="button">Zavřít</button>' +
                '</div>');
        });
    });
    $(document).on('click', '#wcgift-modal-bg, #wcgift-modal-close', function(e){
        if(e.target.id === 'wcgift-modal-bg' || e.target.id === 'wcgift-modal-close') {
            $('#wcgift-modal-bg').hide();
        }
    });
});