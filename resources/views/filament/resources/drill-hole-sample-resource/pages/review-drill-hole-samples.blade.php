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

    {{-- 2. Alerta fija: muestras con baja recuperación sin comentario --}}
    @php($lowRecoveryPending = $this->getLowRecoveryPendingCountProperty())
    @if ($lowRecoveryPending > 0)
        <style>
            .lr-alert {
                margin-bottom: 1rem;
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                background: #fffbeb;
                border: 1px solid #fcd34d;
                border-radius: 0.75rem;
                padding: 1rem;
            }

            .dark .lr-alert {
                background: rgba(120, 53, 15, 0.2);
                border-color: #b45309;
            }

            .lr-alert-body {
                display: flex;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .lr-alert-icon {
                width: 1.5rem;
                height: 1.5rem;
                min-width: 1.5rem;
                min-height: 1.5rem;
                flex-shrink: 0;
                margin-top: 0.125rem;
                color: #d97706;
            }

            .dark .lr-alert-icon {
                color: #fbbf24;
            }

            .lr-alert-title {
                font-weight: 600;
                margin: 0;
                color: #78350f;
            }

            .dark .lr-alert-title {
                color: #fcd34d;
            }

            .lr-alert-desc {
                font-size: 0.8125rem;
                margin: 0.25rem 0 0;
                color: #92400e;
            }

            .dark .lr-alert-desc {
                color: #fbbf2499;
            }

            .lr-alert-action {
                flex-shrink: 0;
            }
        </style>

        <div class="lr-alert">
            <div class="lr-alert-body">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="lr-alert-icon">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <div>
                    <p class="lr-alert-title">
                        ⚠️ {{ $lowRecoveryPending }} muestra(s) con baja recuperación sin comentario
                    </p>
                    <p class="lr-alert-desc">
                        Se detectaron muestras con Sample Length &lt; Length cuyo campo Comentarios está vacío (filtra por "Registro con errores" o revisa la columna Comentarios). Esta alerta permanece visible hasta que se resuelvan, incluso en barrenos con cientos de muestras.
                    </p>
                </div>
            </div>
            <div class="lr-alert-action">
                <x-filament::button
                    color="warning"
                    icon="heroicon-o-exclamation-triangle"
                    wire:click="mountAction('apply_low_recovery_comment_header')"
                >
                    Revisar y aplicar
                </x-filament::button>
            </div>
        </div>
    @endif


    {{-- 3. Widgets estadísticos pegados a la tabla --}}
    <div class="mb-4">
        @livewire(\App\Filament\Resources\DrillHoleSampleResource\Widgets\DrillHoleSampleStats::class, ['barrenoId' => (int) $this->record], key('stats-barreno-' . $this->record))
    </div>

    {{-- 4. Tabla de muestras --}}
    {{ $this->table }}
</x-filament-panels::page>
