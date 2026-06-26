<div style="margin: -1.5rem -1.5rem; display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; height: calc(100vh - 4rem);">
    <livewire:media-cropper-panel
        :state-path="$statePath"
        :modal-id="$modalId"
        :media="$media"
        :presets="$presets"
        :formats="$formats"
        :default-location="$defaultLocation ?? null"
    />
</div>
