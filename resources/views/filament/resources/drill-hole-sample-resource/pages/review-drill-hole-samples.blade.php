<x-filament-panels::page>
    {{-- 1. Ficha de información arriba con botón de volver a la bandeja --}}
    <div class="mb-4 flex flex-col md:flex-row md:items-center md:justify-between bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xs">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                    Distrito: {{ $this->drillHole?->sede?->name ?? 'N/A' }}
                </span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                    Proyecto: {{ $this->drillHole?->proyecto?->nombre ?? 'N/A' }}
                </span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                    Barreno: {{ $this->drillHole?->nombre_barreno }}
                </span>
            </div>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Responsable de carga: <strong class="text-gray-800 dark:text-gray-200">{{ $this->drillHole?->draft_uploader_name }}</strong>
            </p>
        </div>
        <div class="mt-3 md:mt-0">
            <x-filament::button
                color="gray"
                icon="heroicon-o-arrow-left"
                tag="a"
                :href="\App\Filament\Resources\DrillHoleSampleResource::getUrl('index')"
            >
                Volver a la Bandeja
            </x-filament::button>
        </div>
    </div>

    {{-- 2. Widgets estadísticos pegados a la tabla --}}
    <div class="mb-4">
        @livewire(\App\Filament\Resources\DrillHoleSampleResource\Widgets\DrillHoleSampleStats::class, ['barrenoId' => (int) $this->record], key('stats-barreno-' . $this->record))
    </div>

    {{-- 3. Tabla de muestras --}}
    {{ $this->table }}
</x-filament-panels::page>
