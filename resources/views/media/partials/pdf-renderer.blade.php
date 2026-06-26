@once
<script>
(function () {
    var PDFJS_CDN = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.min.mjs';
    var PDFJS_WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.worker.min.mjs';

    function renderPdfCanvas(canvas) {
        var url = canvas.dataset.url;
        if (!url || canvas.dataset.rendered) return;
        canvas.dataset.rendered = '1';

        import(PDFJS_CDN).then(function (pdfjsLib) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER;
            pdfjsLib.getDocument({ url: url, withCredentials: false }).promise.then(function (pdf) {
                pdf.getPage(1).then(function (page) {
                    var isTile = canvas.classList.contains('mz-listing__tile-pdf-canvas');
                    var isPreview = canvas.classList.contains('mz-listing__preview-pdf-canvas');
                    var targetSize = isPreview ? 1200 : (isTile ? 440 : 88); // 2× for retina
                    var viewport = page.getViewport({ scale: 1 });
                    var scale = targetSize / Math.max(viewport.width, viewport.height);
                    var scaledViewport = page.getViewport({ scale: scale });
                    canvas.width = scaledViewport.width;
                    canvas.height = scaledViewport.height;
                    page.render({ canvasContext: canvas.getContext('2d'), viewport: scaledViewport });
                });
            }).catch(function () {});
        }).catch(function () {});
    }

    function renderAll() {
        document.querySelectorAll('.mz-listing__pdf-thumb:not([data-rendered])').forEach(renderPdfCanvas);
    }

    document.addEventListener('DOMContentLoaded', renderAll);
    document.addEventListener('livewire:navigated', renderAll);
    document.addEventListener('livewire:updated', renderAll);

    // Catch canvases added dynamically (e.g. picker panel opening)
    new MutationObserver(function (mutations) {
        var found = false;
        mutations.forEach(function (m) {
            m.addedNodes.forEach(function (n) {
                if (n.nodeType === 1) {
                    if (n.matches && n.matches('.mz-listing__pdf-thumb')) found = true;
                    else if (n.querySelectorAll && n.querySelectorAll('.mz-listing__pdf-thumb').length) found = true;
                }
            });
        });
        if (found) renderAll();
    }).observe(document.body, { childList: true, subtree: true });
})();
</script>
@endonce
