<div>
    <script src="https://cdn.tailwindcss.com"></script>
    @if(isset($record) && $record)
        <livewire:qc-photo-uploader :sample-id="$record->id" :key="'qcp-modal-'.$record->id" />
    @endif
</div>
