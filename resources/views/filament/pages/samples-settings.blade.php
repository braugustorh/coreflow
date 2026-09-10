<x-filament-panels::page>
    <form wire:submit.prevent="save" style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div>
            {{ $this->form }}
        </div>

        <div style="margin-top: 1.5rem; padding-top: 1.25rem; display: flex; justify-content: flex-start; align-items: center; border-top: 1px solid #e5e7eb;">
            <x-filament::button
                type="submit"
                size="lg"
                color="primary"
                icon="heroicon-m-check"
            >
                Guardar Configuración
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
