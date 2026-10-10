<x-filament-panels::page>
    {{-- Importación de Tailwind CSS solicitada por el usuario para renderizado completo sin restricciones de Filament --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        if (typeof tailwind !== 'undefined') {
            tailwind.config = {
                darkMode: 'class',
                corePlugins: {
                    preflight: false,
                },
                theme: {
                    extend: {
                        colors: {
                            primary: {
                                50: '#fffbeb',
                                100: '#fef3c7',
                                200: '#fde68a',
                                300: '#fcd34d',
                                400: '#fbbf24',
                                500: '#f59e0b',
                                600: '#d97706',
                                700: '#b45309',
                                800: '#92400e',
                                900: '#78350f',
                                950: '#451a03',
                            }
                        }
                    }
                }
            };
        }
    </script>

    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif

    <style>
        /* Scoped protections for icons and layout in QC Evidence Module */
        .qc-evidence-page svg {
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
            max-width: 100%;
        }
        .qc-evidence-page svg.w-3\.5,
        .qc-evidence-page svg.h-3\.5,
        .qc-evidence-page .icon-sm {
            width: 14px !important;
            height: 14px !important;
            min-width: 14px !important;
            min-height: 14px !important;
        }
        .qc-evidence-page svg.w-4,
        .qc-evidence-page svg.h-4,
        .qc-evidence-page .icon-md {
            width: 16px !important;
            height: 16px !important;
            min-width: 16px !important;
            min-height: 16px !important;
        }
        .qc-evidence-page svg.w-8,
        .qc-evidence-page svg.h-8,
        .qc-evidence-page .icon-lg {
            width: 32px !important;
            height: 32px !important;
            min-width: 32px !important;
            min-height: 32px !important;
        }
        .qc-sample-card-btn {
            cursor: pointer;
            text-align: left;
            width: 100%;
        }
    </style>

    <div class="qc-evidence-page space-y-6">
        {{-- Barra Superior de Filtros --}}
        <div class="p-4 bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                {{-- Selector Barreno --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Filtrar por Barreno
                    </label>
                    <select wire:model.live="barrenoId" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 focus:ring-primary-500 focus:border-primary-500 py-2 px-3">
                        <option value="">— Todos los Barrenos —</option>
                        @foreach($this->barrenosOptions as $b)
                            <option value="{{ $b->id }}">{{ $b->nombre_barreno }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Selector Work Order --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Filtrar por Work Order
                    </label>
                    <select wire:model.live="workOrderId" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 focus:ring-primary-500 focus:border-primary-500 py-2 px-3">
                        <option value="">— Todas las Órdenes —</option>
                        @foreach($this->workOrdersOptions as $wo)
                            <option value="{{ $wo->id }}">{{ $wo->work_order_code }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Tabs de Estado --}}
                <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 p-1 rounded-lg">
                    <button type="button"
                            wire:click="$set('filter', 'pending')"
                            class="flex-1 py-1.5 px-3 text-xs font-semibold rounded-md transition {{ $filter === 'pending' ? 'bg-white dark:bg-gray-700 text-amber-600 dark:text-amber-400 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}">
                        Pendientes ({{ $this->stats['pending'] }})
                    </button>
                    <button type="button"
                            wire:click="$set('filter', 'all')"
                            class="flex-1 py-1.5 px-3 text-xs font-semibold rounded-md transition {{ $filter === 'all' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}">
                        Todas ({{ $this->stats['total'] }})
                    </button>
                    <button type="button"
                            wire:click="$set('filter', 'completed')"
                            class="flex-1 py-1.5 px-3 text-xs font-semibold rounded-md transition {{ $filter === 'completed' ? 'bg-white dark:bg-gray-700 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}">
                        Listas ({{ $this->stats['completed'] }})
                    </button>
                </div>
            </div>

            {{-- Barra de Progreso --}}
            @if($this->stats['total'] > 0)
                <div class="pt-2 border-t border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="text-gray-600 dark:text-gray-400">
                            Progreso de documentación fotográfica QC:
                            <strong class="text-gray-900 dark:text-gray-100">{{ $this->stats['completed'] }} de {{ $this->stats['total'] }} muestras</strong>
                        </span>
                        <span class="font-bold {{ $this->stats['percentage'] === 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                            {{ $this->stats['percentage'] }}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full transition-all duration-500 {{ $this->stats['percentage'] === 100 ? 'bg-emerald-500' : 'bg-amber-500' }}"
                             style="width: {{ $this->stats['percentage'] }}%"></div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Vista Principal --}}
        @if($this->samples->isEmpty())
            <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950/50 mb-3">
                    <x-heroicon-o-check-badge class="w-8 h-8 text-emerald-600 dark:text-emerald-400" style="width: 32px; height: 32px;" />
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                    @if($filter === 'pending')
                        ¡No hay muestras de control pendientes!
                    @else
                        No se encontraron muestras con los filtros seleccionados
                    @endif
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                    @if($filter === 'pending')
                        Todas las muestras de control en este alcance tienen sus 2 fotografías registradas.
                    @else
                        Intenta cambiando los filtros de barreno o work order en la parte superior.
                    @endif
                </p>
                @if($filter === 'pending' && $this->stats['total'] > 0)
                    <div class="mt-4">
                        <button type="button"
                                wire:click="$set('filter', 'all')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 transition">
                            <x-heroicon-o-eye class="w-4 h-4" style="width: 16px; height: 16px;" />
                            Ver todas las muestras
                        </button>
                    </div>
                @endif
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                {{-- Columna Izquierda: Lista de Muestras (Selector) --}}
                <div class="lg:col-span-4 bg-white dark:bg-gray-900 rounded-xl p-3 border border-gray-200 dark:border-gray-800 shadow-sm max-h-[750px] overflow-y-auto space-y-2">
                    <div class="flex items-center justify-between px-2 py-1 text-xs font-bold text-gray-500 uppercase tracking-wider">
                        <span>Muestras ({{ $this->samples->count() }})</span>
                        <span>Estado</span>
                    </div>

                    <div class="space-y-1.5">
                        @foreach($this->samples as $sample)
                            @php
                                $photoCount = $sample->qc_photos_count ?? $sample->qcPhotos->count();
                                $isCompleted = $photoCount >= 2;
                                $isSelected = $sample->id === $this->selectedSampleId;
                            @endphp
                            <button type="button"
                                    wire:click="selectSample({{ $sample->id }})"
                                    class="qc-sample-card-btn p-3 rounded-lg border transition flex items-center justify-between {{ $isSelected ? 'border-amber-500 bg-amber-50/70 dark:bg-amber-950/40 ring-1 ring-amber-500' : 'border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-gray-900 dark:text-gray-100">
                                            {{ $sample->sample_number }}
                                        </span>
                                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded {{ str_contains(strtoupper($sample->control_type), 'STD') ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' : 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300' }}">
                                            {{ $sample->standardSample?->standard_name ?? $sample->control_type }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                        <span>{{ $sample->barreno?->nombre_barreno ?? 'Sin barreno' }}</span>
                                        @if($sample->workOrder)
                                            <span> • WO: {{ $sample->workOrder->work_order_code }}</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex-shrink-0 ml-2">
                                    <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full {{ $isCompleted ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' }}">
                                        @if($isCompleted)
                                            <x-heroicon-s-check-circle class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" style="width: 14px; height: 14px; min-width: 14px; min-height: 14px; display: inline-block;" />
                                        @else
                                            <x-heroicon-m-camera class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" style="width: 14px; height: 14px; min-width: 14px; min-height: 14px; display: inline-block;" />
                                        @endif
                                        <span>{{ $photoCount }}/2</span>
                                    </span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Columna Derecha: Tarjeta de Captura / Muestra Seleccionada --}}
                <div class="lg:col-span-8 space-y-4">
                    @if($this->selectedSample)
                        @php
                            $current = $this->selectedSample;
                            $count = $current->qcPhotos->count();
                            $complete = $count >= 2;
                        @endphp
                        <div class="bg-white dark:bg-gray-900 rounded-xl p-5 border border-gray-200 dark:border-gray-800 shadow-sm space-y-5">
                            {{-- Cabecera de la Muestra --}}
                            <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-gray-100 dark:border-gray-800">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h2 class="text-xl font-black text-gray-900 dark:text-gray-100">
                                            Muestra {{ $current->sample_number }}
                                        </h2>
                                        <span class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-full {{ $complete ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                            @if($complete)
                                                <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-600 dark:text-emerald-400" style="width: 16px; height: 16px; display: inline-block;" />
                                            @else
                                                <x-heroicon-m-camera class="w-4 h-4 text-amber-600 dark:text-amber-400" style="width: 16px; height: 16px; display: inline-block;" />
                                            @endif
                                            <span>{{ $complete ? 'Completa (2/2)' : "Pendiente ({$count}/2)" }}</span>
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-400 mt-1">
                                        <span><strong>Barreno:</strong> {{ $current->barreno?->nombre_barreno ?? '—' }}</span>
                                        <span><strong>Work Order:</strong> {{ $current->workOrder?->work_order_code ?? 'Sin asignar' }}</span>
                                        <span><strong>Control:</strong> {{ $current->standardSample?->standard_name ?? $current->control_type }}</span>
                                        @if($current->weight)
                                            <span><strong>Peso:</strong> {{ $current->weight }} kg</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Botones Navegación --}}
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            wire:click="goToPrevious"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 transition">
                                        <x-heroicon-m-chevron-left class="w-4 h-4" style="width: 16px; height: 16px;" />
                                        <span>Anterior</span>
                                    </button>
                                    <button type="button"
                                            wire:click="goToNextPending"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-500 hover:bg-amber-600 text-white shadow-sm transition">
                                        <span>Siguiente</span>
                                        <x-heroicon-m-chevron-right class="w-4 h-4" style="width: 16px; height: 16px;" />
                                    </button>
                                </div>
                            </div>

                            {{-- Componente Uploader Livewire --}}
                            <div>
                                <livewire:qc-photo-uploader :sample="$current" :wire:key="'qc-evidence-uploader-'.$current->id" />
                            </div>

                            {{-- Footer rápido de avance móvil --}}
                            <div class="pt-4 border-t border-gray-100 dark:border-gray-800 flex justify-end">
                                <button type="button"
                                        wire:click="goToNextPending"
                                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl bg-gray-900 hover:bg-gray-800 dark:bg-gray-100 dark:hover:bg-white text-white dark:text-gray-900 transition">
                                    <span>Siguiente muestra pendiente</span>
                                    <x-heroicon-m-arrow-right class="w-4 h-4" style="width: 16px; height: 16px;" />
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
