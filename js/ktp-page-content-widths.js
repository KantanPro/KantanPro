/* class-ktpwp-settings.php から切り出し（以前は PHP の nowdoc で返していた） */
(function () {
    function toggleCustomWidthInput(select) {
        var pageId = select.getAttribute('data-page-id');
        var customInput = document.querySelector('.ktp-page-content-width-custom[data-page-id="' + pageId + '"]');
        var suffix = document.querySelector('.ktp-page-content-width-custom-suffix[data-page-id="' + pageId + '"]');
        var showCustom = select.value === 'custom';
        if (customInput) {
            customInput.style.display = showCustom ? '' : 'none';
        }
        if (suffix) {
            suffix.style.display = showCustom ? '' : 'none';
        }
    }

    document.querySelectorAll('.ktp-page-content-width-select').forEach(function (select) {
        select.addEventListener('change', function () {
            toggleCustomWidthInput(select);
        });
    });
})();
