document.addEventListener('DOMContentLoaded', function() {
    const previewItems = document.querySelectorAll('.fb-preview-item');
    const modal = document.getElementById('fb-modal');
    const modalContent = document.getElementById('fb-modal-content');
    const closeButton = document.getElementById('fb-modal-close');
    const prevButton = document.getElementById('fb-modal-prev');
    const nextButton = document.getElementById('fb-modal-next');

    let currentIndex = 0;
    let productList = [];

    // Vytvoření seznamu produktů z preview položek
    previewItems.forEach(function(item) {
        productList.push({
            id: item.dataset.productId,
            url: item.dataset.productUrl
        });
    });

    // Funkce pro načtení produktu do modálního okna
    function loadProduct(index) {
        if (index < 0 || index >= productList.length) return;

        currentIndex = index;
        const product = productList[currentIndex];
        const ajaxUrl = fbQuickView.ajaxUrl + '?action=fb_quick_view&product_id=' + product.id;

        fetch(ajaxUrl)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modalContent.innerHTML = data.data.html + 
                        '<a href="' + product.url + '" style="display: block; margin-top: 10px; text-decoration: none; color: #fff; background-color: #000; padding: 10px 20px; border-radius: 0px; font-weight: bold;">Zobrazit celý produkt</a>';
                    modal.style.display = 'block';

                    // Zobrazit/skrýt šipky podle pozice
                    prevButton.style.display = currentIndex > 0 ? 'block' : 'none';
                    nextButton.style.display = currentIndex < productList.length - 1 ? 'block' : 'none';
                } else {
                    alert('Nelze načíst produkt: ' + (data.message || 'Neznámá chyba'));
                }
            })
            .catch(error => {
                console.error('Chyba při načítání produktu:', error);
            });
    }

    // Kliknutí na preview položku
    previewItems.forEach(function(item) {
        item.addEventListener('click', function() {
            const index = parseInt(this.dataset.index);
            loadProduct(index);
        });
    });

    // Navigace - předchozí produkt
    prevButton.addEventListener('click', function() {
        loadProduct(currentIndex - 1);
    });

    // Navigace - další produkt
    nextButton.addEventListener('click', function() {
        loadProduct(currentIndex + 1);
    });

    // Zavření modálního okna
    closeButton.addEventListener('click', function() {
        modal.style.display = 'none';
    });
});