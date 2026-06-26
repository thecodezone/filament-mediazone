document.addEventListener('alpine:init', function () {
    Alpine.data('mediaCropper', function (config) {
        return {
            cropper: null,
            preset: 'custom',
            location: config.defaultLocation || '',
            breakpoints: ['mobile', 'tablet', 'desktop'],
            cropKey: '',
            label: '',
            format: config.defaultFormat,
            quality: 90,
            targetWidth: 0,
            targetHeight: 0,
            cropData: { x: 0, y: 0, width: 0, height: 0, rotate: 0, scaleX: 1, scaleY: 1 },
            aspectRatio: 'NaN',
            presets: config.presets,
            locations: config.locations || [],
            modalId: config.modalId || '',
            saving: false,
            _initAttempts: 0,
            _fitTimer: null,
            _userHasInteracted: false,
            guideLines: [],
            _cropBoxData: null,

            init: function () {
                // Pre-select the default location and its matching preset
                if (this.location) {
                    this.selectLocation();
                }
                this._tryInit();
            },

            _tryInit: function () {
                var img = this.$refs.image;
                if (img && img.offsetParent !== null && typeof Cropper !== 'undefined') {
                    this.initCropper();
                    return;
                }
                if (this._initAttempts++ < 40) {
                    var self = this;
                    setTimeout(function () { self._tryInit(); }, 100);
                }
            },

            initCropper: function () {
                var img = this.$refs.image;
                if (!img || typeof Cropper === 'undefined') return;
                if (this.cropper) { this.cropper.destroy(); this.cropper = null; }
                var self = this;
                this.cropper = new Cropper(img, {
                    viewMode: 0,
                    autoCropArea: 0.8,
                    background: true,
                    guides: true,
                    center: true,
                    movable: true,
                    zoomable: true,
                    rotatable: true,
                    scalable: true,
                    minCanvasWidth: 0,
                    minCanvasHeight: 0,
                    ready: function () { self.cropData = self.cropper.getData(true); self._cropBoxData = self.cropper.getCropBoxData(); },
                    cropstart: function () { self._userHasInteracted = true; },
                    cropend: function () { self.cropData = self.cropper.getData(true); self._cropBoxData = self.cropper.getCropBoxData(); },
                    crop: function () { self._cropBoxData = self.cropper.getCropBoxData(); },
                    zoom: function (e) {
                        self._userHasInteracted = true;
                        // After Cropper recalculates boundaries on zoom, it clamps canvas.top >= 0
                        // when the canvas is smaller than the container (known Cropper.js bug).
                        // We restore the previous top so the user can drag the canvas above the container.
                        var prevCanvas = self.cropper ? self.cropper.getCanvasData() : null;
                        if (prevCanvas) {
                            var prevTop = prevCanvas.top;
                            setTimeout(function () {
                                if (!self.cropper) return;
                                var c = self.cropper.getCanvasData();
                                if (prevTop < 0 && c.top === 0) {
                                    self.cropper.setCanvasData({ top: prevTop });
                                }
                            }, 0);
                        }
                    },
                });
            },

            selectLocation: function () {
                if (!this.location) {
                    // Cleared to "None" — reset key so user must enter custom
                    this.cropKey = '';
                    return;
                }
                // Find matching location for label and default_preset
                var loc = null;
                for (var i = 0; i < this.locations.length; i++) {
                    if (this.locations[i].key === this.location) { loc = this.locations[i]; break; }
                }
                if (loc) {
                    this.cropKey = loc.key;
                    this.label = loc.label;
                    // Auto-select the default preset if one is defined
                    if (loc.default_preset) {
                        this.preset = loc.default_preset;
                        this.selectPreset();
                    }
                }
            },

            selectPreset: function () {
                if (this.preset === 'custom') {
                    this.guideLines = [];
                    return;
                }
                var p = null;
                for (var i = 0; i < this.presets.length; i++) {
                    if (this.presets[i].key === this.preset) { p = this.presets[i]; break; }
                }
                if (!p) return;
                // Only override cropKey/label when there's no location selected
                // (location already set these; preset just updates dimensions/format/quality)
                if (!this.location) {
                    this.cropKey = p.key;
                    this.label = p.label;
                }
                this.format = p.format;
                this.quality = p.quality;
                this.targetWidth = p.width;
                this.targetHeight = p.height;
                this.guideLines = p.guide_lines || [];
                if (this.cropper && p.width && p.height) {
                    this.cropper.setAspectRatio(p.width / p.height);
                    this._fitImageToCropHeight(p.width / p.height);
                    this.aspectRatio = '';
                }
            },

            _fitImageToCropHeight: function (aspectRatio) {
                var self = this;
                if (self._userHasInteracted) return;
                if (self._fitTimer) { clearTimeout(self._fitTimer); }
                self._fitTimer = setTimeout(function () {
                    self._fitTimer = null;
                    if (!self.cropper || self._userHasInteracted) return;
                    var imgData = self.cropper.getImageData();
                    var contData = self.cropper.getContainerData();

                    // Scale the canvas so it fills the container height, clamped to container width.
                    // Always derived from natural dimensions so repeated calls are idempotent.
                    var imgRatio = imgData.naturalWidth / imgData.naturalHeight;
                    var canvasH = contData.height;
                    var canvasW = canvasH * imgRatio;
                    if (canvasW > contData.width) {
                        canvasW = contData.width;
                        canvasH = canvasW / imgRatio;
                    }
                    var canvasLeft = (contData.width - canvasW) / 2;
                    var canvasTop = 0;

                    self.cropper.setCanvasData({
                        left: canvasLeft,
                        top: canvasTop,
                        width: canvasW,
                        height: canvasH,
                    });

                    // Fit the crop box inside the canvas, honouring the preset aspect ratio,
                    // anchored to the top-left of the canvas.
                    var cropW = canvasW;
                    var cropH = canvasH;
                    if (aspectRatio) {
                        if (cropW / cropH > aspectRatio) {
                            cropW = cropH * aspectRatio;
                        } else {
                            cropH = cropW / aspectRatio;
                        }
                    }
                    self.cropper.setCropBoxData({
                        left: canvasLeft + (canvasW - cropW) / 2,
                        top: canvasTop,
                        width: cropW,
                        height: cropH,
                    });

                    self.cropData = self.cropper.getData(true);
                }, 50);
            },

            toggleBreakpoint: function (bp) {
                var idx = this.breakpoints.indexOf(bp);
                if (idx === -1) {
                    this.breakpoints.push(bp);
                } else {
                    this.breakpoints.splice(idx, 1);
                }
            },

            flipH: function () {
                if (!this.cropper) return;
                var d = this.cropper.getData(true);
                this.cropper.scaleX(d.scaleX === -1 ? 1 : -1);
                this.cropData = this.cropper.getData(true);
            },

            flipV: function () {
                if (!this.cropper) return;
                var d = this.cropper.getData(true);
                this.cropper.scaleY(d.scaleY === -1 ? 1 : -1);
                this.cropData = this.cropper.getData(true);
            },

            guideLineStyle: function (guide) {
                var box = this._cropBoxData;
                if (!box) return 'display:none';
                if (guide.axis === 'y') {
                    var top = box.top + (box.height * guide.percent / 100);
                    return 'top:' + top + 'px;left:' + box.left + 'px;width:' + box.width + 'px;';
                }
                var left = box.left + (box.width * guide.percent / 100);
                return 'left:' + left + 'px;top:' + box.top + 'px;height:' + box.height + 'px;';
            },

            save: async function () {
                if (this.saving) return;
                var effectiveKey = this.location || this.cropKey;
                if (!effectiveKey) {
                    alert('Please select a location or enter a custom key.');
                    return;
                }
                if (!this.cropper) {
                    alert('Cropper not ready. Please wait and try again.');
                    return;
                }
                this.saving = true;
                var saveBtn = document.querySelector('[data-mz-cropper__save-btn]');
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.style.opacity = '0.6';
                    saveBtn.style.pointerEvents = 'none';
                    var originalHTML = saveBtn.innerHTML;
                    saveBtn.innerHTML = '<svg style="display:inline-block;width:14px;height:14px;margin-right:6px;vertical-align:middle;animation:mz-cropper__spin 0.7s linear infinite" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>Saving…';
                }
                var d = this.cropper.getData(true);
                try {
                    await this.$wire.saveCrop({
                        x: d.x, y: d.y,
                        width: d.width, height: d.height,
                        rotate: d.rotate,
                        scaleX: d.scaleX, scaleY: d.scaleY,
                        key: effectiveKey,
                        label: this.label || effectiveKey,
                        location: this.location || null,
                        breakpoints: this.breakpoints,
                        format: this.format,
                        quality: parseInt(this.quality),
                        targetWidth: parseInt(this.targetWidth) || 0,
                        targetHeight: parseInt(this.targetHeight) || 0,
                    });
                    // Close the slide-over — try all ancestor wire IDs with all known Filament suffixes
                    var wireId = this.$wire.id;
                    var suffixes = ['-form-component-action', '-table-action', '-action'];
                    var node = this.$el.parentElement;
                    var tried = new Set([wireId]);
                    while (node) {
                        var id = node.getAttribute && node.getAttribute('wire:id');
                        if (id && !tried.has(id)) {
                            tried.add(id);
                            for (var s = 0; s < suffixes.length; s++) {
                                window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: id + suffixes[s] } }));
                            }
                        }
                        node = node.parentElement;
                    }
                    // Also try own wire ID as fallback
                    for (var si = 0; si < suffixes.length; si++) {
                        window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: wireId + suffixes[si] } }));
                    }
                } catch (e) {
                    console.error('MediaZone crop save error:', e);
                    alert('Crop save failed: ' + e.message);
                } finally {
                    this.saving = false;
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.style.opacity = '';
                        saveBtn.style.pointerEvents = '';
                        saveBtn.innerHTML = originalHTML || 'Save crop';
                    }
                }
            },
        };
    });
});
