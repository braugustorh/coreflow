<x-filament-panels::page>
    <style>
        /* ===== QC Evidence Page - Senior UX Scoped Styles ===== */
        .qce-page {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            width: 100%;
            font-family: inherit;
        }

        .qce-page svg {
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
        }

        /* Card panels */
        .qce-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.875rem;
            padding: 1.25rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
        }
        .dark .qce-card {
            background: #111827;
            border-color: #1f2937;
        }

        /* Filters Layout */
        .qce-filters-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            align-items: end;
        }
        @media (min-width: 768px) {
            .qce-filters-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .qce-form-group {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }
        .qce-form-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #334155;
            letter-spacing: -0.01em;
        }
        .dark .qce-form-label {
            color: #94a3b8;
        }
        .qce-select {
            width: 100%;
            font-size: 0.875rem;
            border-radius: 0.5rem;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            color: #0f172a;
            padding: 0.5rem 0.75rem;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .dark .qce-select {
            border-color: #334155;
            background-color: #1e293b;
            color: #f8fafc;
        }
        .qce-select:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
        }

        /* Filter Tabs */
        .qce-tabs {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            background: #f1f5f9;
            padding: 0.25rem;
            border-radius: 0.5rem;
        }
        .dark .qce-tabs {
            background: #1e293b;
        }
        .qce-tab-btn {
            flex: 1;
            padding: 0.45rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            border-radius: 0.375rem;
            text-align: center;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .qce-tab-btn.active-pending {
            background: #ffffff;
            color: #d97706;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .dark .qce-tab-btn.active-pending {
            background: #334155;
            color: #fbbf24;
        }
        .qce-tab-btn.active-all {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .dark .qce-tab-btn.active-all {
            background: #334155;
            color: #f8fafc;
        }
        .qce-tab-btn.active-completed {
            background: #ffffff;
            color: #059669;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .dark .qce-tab-btn.active-completed {
            background: #334155;
            color: #34d399;
        }
        .qce-tab-btn.inactive {
            background: transparent;
            color: #64748b;
        }
        .dark .qce-tab-btn.inactive {
            color: #94a3b8;
        }
        .qce-tab-btn.inactive:hover {
            color: #0f172a;
        }
        .dark .qce-tab-btn.inactive:hover {
            color: #f8fafc;
        }

        /* Progress Bar */
        .qce-progress-wrap {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #f1f5f9;
        }
        .dark .qce-progress-wrap {
            border-color: #1e293b;
        }
        .qce-progress-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            margin-bottom: 0.375rem;
            color: #475569;
        }
        .dark .qce-progress-info {
            color: #94a3b8;
        }
        .qce-progress-bar-bg {
            width: 100%;
            background: #e2e8f0;
            border-radius: 9999px;
            height: 0.5rem;
            overflow: hidden;
        }
        .dark .qce-progress-bar-bg {
            background: #334155;
        }
        .qce-progress-bar-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width 0.4s ease;
        }
        .qce-progress-amber {
            background: #f59e0b;
        }
        .qce-progress-green {
            background: #10b981;
        }

        /* Main 2-column Grid */
        .qce-main-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
            align-items: start;
        }
        @media (min-width: 1024px) {
            .qce-main-grid {
                grid-template-columns: 340px 1fr;
            }
        }
        @media (min-width: 1280px) {
            .qce-main-grid {
                grid-template-columns: 380px 1fr;
            }
        }

        /* Sample List (Left Column) */
        .qce-sample-list-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.875rem;
            padding: 0.875rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
            max-height: 720px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .dark .qce-sample-list-card {
            background: #111827;
            border-color: #1f2937;
        }
        .qce-sample-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }
        .dark .qce-sample-list-header {
            color: #94a3b8;
        }
        .qce-sample-items-container {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }

        /* Sample Button Card */
        .qce-sample-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.625rem;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            cursor: pointer;
            text-align: left;
            transition: all 0.15s ease;
        }
        .dark .qce-sample-item {
            background: #111827;
            border-color: #1f2937;
        }
        .qce-sample-item:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        .dark .qce-sample-item:hover {
            background: #1e293b;
            border-color: #334155;
        }
        .qce-sample-item.selected {
            border-color: #6366f1;
            background: #eef2ff;
            box-shadow: 0 0 0 1px #6366f1;
        }
        .dark .qce-sample-item.selected {
            border-color: #818cf8;
            background: rgba(99, 102, 241, 0.2);
        }

        .qce-sample-number {
            font-size: 0.875rem;
            font-weight: 800;
            color: #0f172a;
        }
        .dark .qce-sample-number {
            color: #f8fafc;
        }
        .qce-sample-meta {
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 0.125rem;
        }
        .dark .qce-sample-meta {
            color: #94a3b8;
        }

        /* Badges */
        .qce-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .qce-badge-std {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        .dark .qce-badge-std {
            background: rgba(30, 58, 138, 0.4);
            color: #93c5fd;
            border-color: #1e40af;
        }
        .qce-badge-blk {
            background: #faf5ff;
            color: #7e22ce;
            border: 1px solid #e9d5ff;
        }
        .dark .qce-badge-blk {
            background: rgba(88, 28, 135, 0.4);
            color: #d8b4fe;
            border-color: #6b21a8;
        }
        .qce-badge-complete {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .dark .qce-badge-complete {
            background: rgba(6, 78, 59, 0.4);
            color: #6ee7b7;
            border-color: #047857;
        }
        .qce-badge-pending {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .dark .qce-badge-pending {
            background: rgba(120, 53, 15, 0.4);
            color: #fde68a;
            border-color: #b45309;
        }

        /* Top Action Buttons */
        .qce-btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.45rem 0.85rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.5rem;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #334155;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .dark .qce-btn-nav {
            background: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
        }
        .qce-btn-nav:hover {
            background: #f8fafc;
        }
        .dark .qce-btn-nav:hover {
            background: #334155;
        }

        .qce-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.45rem 0.85rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.5rem;
            border: none;
            background: #4f46e5;
            color: #ffffff;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(79, 70, 229, 0.2);
            transition: all 0.15s ease;
        }
        .qce-btn-primary:hover {
            background: #4338ca;
        }

        .qce-btn-advance {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 1rem;
            font-size: 0.75rem;
            font-weight: 700;
            border-radius: 0.5rem;
            border: none;
            background: #0f172a;
            color: #ffffff;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .dark .qce-btn-advance {
            background: #f8fafc;
            color: #0f172a;
        }
        .qce-btn-advance:hover {
            background: #1e293b;
        }
        .dark .qce-btn-advance:hover {
            background: #ffffff;
        }

        /* Empty State */
        .qce-empty-state {
            padding: 3.5rem 1.5rem;
            text-align: center;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.875rem;
        }
        .dark .qce-empty-state {
            background: #111827;
            border-color: #1f2937;
        }
        .qce-empty-icon-wrap {
            margin: 0 auto 0.875rem auto;
            display: flex;
            width: 3.5rem;
            height: 3.5rem;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            background: #ecfdf5;
            color: #059669;
        }
        .dark .qce-empty-icon-wrap {
            background: rgba(6, 78, 59, 0.35);
            color: #34d399;
        }
    </style>

    <div class="qce-page">
        {{-- Barra Superior de Filtros --}}
        <div class="qce-card">
            <div class="qce-filters-grid">
                {{-- Selector Barreno --}}
                <div class="qce-form-group">
                    <label class="qce-form-label">Filtrar por Barreno</label>
                    <select wire:model.live="barrenoId" class="qce-select">
                        <option value="">— Todos los Barrenos —</option>
                        @foreach($this->barrenosOptions as $b)
                            <option value="{{ $b->id }}">{{ $b->nombre_barreno }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Selector Work Order --}}
                <div class="qce-form-group">
                    <label class="qce-form-label">Filtrar por Work Order</label>
                    <select wire:model.live="workOrderId" class="qce-select">
                        <option value="">— Todas las Órdenes —</option>
                        @foreach($this->workOrdersOptions as $wo)
                            <option value="{{ $wo->id }}">{{ $wo->work_order_code }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Tabs de Estado --}}
                <div class="qce-tabs">
                    <button type="button"
                            wire:click="$set('filter', 'pending')"
                            class="qce-tab-btn {{ $filter === 'pending' ? 'active-pending' : 'inactive' }}">
                        Pendientes ({{ $this->stats['pending'] }})
                    </button>
                    <button type="button"
                            wire:click="$set('filter', 'all')"
                            class="qce-tab-btn {{ $filter === 'all' ? 'active-all' : 'inactive' }}">
                        Todas ({{ $this->stats['total'] }})
                    </button>
                    <button type="button"
                            wire:click="$set('filter', 'completed')"
                            class="qce-tab-btn {{ $filter === 'completed' ? 'active-completed' : 'inactive' }}">
                        Listas ({{ $this->stats['completed'] }})
                    </button>
                </div>
            </div>

            {{-- Barra de Progreso --}}
            @if($this->stats['total'] > 0)
                <div class="qce-progress-wrap">
                    <div class="qce-progress-info">
                        <span>
                            Progreso de documentación fotográfica QC:
                            <strong style="color: #0f172a;" class="dark:text-white">{{ $this->stats['completed'] }} de {{ $this->stats['total'] }} muestras</strong>
                        </span>
                        <span style="font-weight: 800; color: {{ $this->stats['percentage'] === 100 ? '#10b981' : '#d97706' }};">
                            {{ $this->stats['percentage'] }}%
                        </span>
                    </div>
                    <div class="qce-progress-bar-bg">
                        <div class="qce-progress-bar-fill {{ $this->stats['percentage'] === 100 ? 'qce-progress-green' : 'qce-progress-amber' }}"
                             style="width: {{ $this->stats['percentage'] }}%;"></div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Vista Principal --}}
        @if($this->samples->isEmpty())
            <div class="qce-empty-state">
                <div class="qce-empty-icon-wrap">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 28px; height: 28px;"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a;" class="dark:text-white">
                    @if($filter === 'pending')
                        ¡No hay muestras de control pendientes!
                    @else
                        No se encontraron muestras con los filtros seleccionados
                    @endif
                </h3>
                <p style="font-size: 0.775rem; color: #64748b; margin-top: 0.25rem; max-width: 26rem; margin-left: auto; margin-right: auto;" class="dark:text-gray-400">
                    @if($filter === 'pending')
                        Todas las muestras de control en este alcance tienen sus 2 fotografías registradas.
                    @else
                        Intenta cambiando los filtros de barreno o work order en la parte superior.
                    @endif
                </p>
                @if($filter === 'pending' && $this->stats['total'] > 0)
                    <div style="margin-top: 1rem;">
                        <button type="button"
                                wire:click="$set('filter', 'all')"
                                class="qce-btn-nav">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px;"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <span>Ver todas las muestras</span>
                        </button>
                    </div>
                @endif
            </div>
        @else
            <div class="qce-main-grid">
                {{-- Columna Izquierda: Lista de Muestras --}}
                <div class="qce-sample-list-card">
                    <div class="qce-sample-list-header">
                        <span>Muestras ({{ $this->samples->count() }})</span>
                        <span>Estado</span>
                    </div>

                    <div class="qce-sample-items-container">
                        @foreach($this->samples as $sample)
                            @php
                                $photoCount = $sample->qc_photos_count ?? $sample->qcPhotos->count();
                                $isCompleted = $photoCount >= 2;
                                $isSelected = $sample->id === $this->selectedSampleId;
                                $isStd = str_contains(strtoupper((string) $sample->control_type), 'STD');
                            @endphp
                            <button type="button"
                                    wire:click="selectSample({{ $sample->id }})"
                                    class="qce-sample-item {{ $isSelected ? 'selected' : '' }}">
                                <div style="display: flex; flex-direction: column; gap: 0.125rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span class="qce-sample-number">
                                            {{ $sample->sample_number }}
                                        </span>
                                        <span class="qce-badge {{ $isStd ? 'qce-badge-std' : 'qce-badge-blk' }}">
                                            {{ $sample->standardSample?->standard_name ?? $sample->control_type }}
                                        </span>
                                    </div>
                                    <div class="qce-sample-meta">
                                        <span>{{ $sample->barreno?->nombre_barreno ?? 'Sin barreno' }}</span>
                                        @if($sample->workOrder)
                                            <span> • WO: {{ $sample->workOrder->work_order_code }}</span>
                                        @endif
                                    </div>
                                </div>

                                <div style="flex-shrink: 0; margin-left: 0.5rem;">
                                    <span class="qce-badge {{ $isCompleted ? 'qce-badge-complete' : 'qce-badge-pending' }}">
                                        @if($isCompleted)
                                            <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor" style="width: 13px; height: 13px; flex-shrink: 0;"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" /></svg>
                                        @else
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 13px; height: 13px; flex-shrink: 0;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                        @endif
                                        <span>{{ $photoCount }}/2</span>
                                    </span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Columna Derecha: Tarjeta de Captura / Muestra Seleccionada --}}
                <div>
                    @if($this->selectedSample)
                        @php
                            $current = $this->selectedSample;
                        @endphp
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            {{-- Barra Superior de Navegación Rápida --}}
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <button type="button"
                                            wire:click="goToPrevious"
                                            class="qce-btn-nav"
                                            title="Ir a la muestra anterior">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px; flex-shrink: 0;"><path d="m15 18-6-6 6-6"/></svg>
                                        <span>Anterior</span>
                                    </button>
                                    <button type="button"
                                            wire:click="goToNextPending"
                                            class="qce-btn-primary"
                                            title="Ir a la siguiente muestra pendiente">
                                        <span>Siguiente</span>
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px; flex-shrink: 0;"><path d="m9 18 6-6-6-6"/></svg>
                                    </button>
                                </div>

                                <div>
                                    <button type="button"
                                            wire:click="goToNextPending"
                                            class="qce-btn-advance">
                                        <span>Siguiente muestra pendiente</span>
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px; flex-shrink: 0;"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Componente Uploader Livewire --}}
                            <div>
                                <livewire:qc-photo-uploader :sample-id="$current->id" :wire:key="'qc-evidence-uploader-'.$current->id" />
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
