<div style="margin: -1.5rem -1.5rem; display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; height: calc(100vh - 4rem);">
    <livewire:media-cropper-panel
        :state-path="$statePath"
        :modal-id="$modalId"
        :media="$media"
        :presets="$presets"
        :formats="$formats"
        :default-location="$defaultLocation ?? null"
        :editing-crop-id="$editingCropId ?? null"
        :initial-geometry="$initialGeometry ?? null"
        :initial-key="$initialKey ?? null"
        :initial-label="$initialLabel ?? null"
        :initial-location="$initialLocation ?? null"
        :initial-breakpoints="$initialBreakpoints ?? null"
        :initial-format="$initialFormat ?? null"
        :initial-quality="$initialQuality ?? null"
        :initial-target-width="$initialTargetWidth ?? null"
        :initial-target-height="$initialTargetHeight ?? null"
    />
</div>
