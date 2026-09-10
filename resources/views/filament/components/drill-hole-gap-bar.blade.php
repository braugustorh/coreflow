@props([
    'record' => null,
])

@php
    $record = $record ?? $getRecord();
    $segments = $record ? \App\Services\GapCalculatorService::getVisualSegments($record) : [];
    $startDepth = $record?->start_depth ?? 0;
    $maxDepth = $record?->max_depth ?? 0;
    $gapsOnly = array_filter($segments, fn($s) => $s['type'] === 'gap');
    $validationsList = $record ? $record->gapValidations()->with('validator')->orderBy('from_depth')->get() : collect();
@endphp

@if(!$record || $maxDepth <= $startDepth)
    <div style="padding: 10px; background-color: #f3f4f6; color: #6b7280; border-radius: 6px; font-size: 12px; font-style: italic; text-align: center;">
        Sin datos de profundidad máxima para generar el perfil visual.
    </div>
@else
    <div x-data="{ activePopover: null }" style="margin-top: 10px; margin-bottom: 10px; font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;">
        
        <!-- Leyenda e Información General -->
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; font-size: 12px; padding: 10px 12px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 10px;">
            <div style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: #1e293b;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px; min-width: 16px; display: inline-block;">
                    <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Perfil Continuo de Profundidad ({{ number_format($startDepth, 2) }}m - {{ number_format($maxDepth, 2) }}m)
            </div>
            
            <div style="display: flex; align-items: center; gap: 16px;">
                <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #334155;">
                    <span style="width: 12px; height: 12px; border-radius: 3px; background-color: #059669; display: inline-block;"></span> Muestreado
                </span>
                <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #0284c7; font-weight: 600;">
                    <span style="width: 12px; height: 12px; border-radius: 3px; background-color: #0284c7; display: inline-block;"></span> Gap Validado
                </span>
                <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #b45309;">
                    <span style="width: 12px; height: 12px; border-radius: 3px; background-color: #fbbf24; border: 1px solid #d97706; display: inline-block;"></span> GAP Pendiente
                </span>
            </div>
        </div>

        <!-- Barra Horizontal Continua de Profundidad -->
        <div style="position: relative; width: 100%; background-color: #059669; height: 28px; border-radius: 8px; overflow: visible; display: flex; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1); border: 1px solid #047857; padding: 2px;">
            @foreach($segments as $index => $seg)
                @php
                    $isGap = $seg['type'] === 'gap';
                    $isValidated = $seg['type'] === 'validation';
                    $isInteractive = $isGap || $isValidated;
                    
                    $bgColor = match($seg['type']) {
                        'sample' => '#059669',
                        'validation' => '#0284c7',
                        'gap' => '#fbbf24',
                        default => '#9ca3af',
                    };
                    $borderColor = match($seg['type']) {
                        'gap' => '#d97706',
                        'validation' => '#0369a1',
                        default => 'transparent',
                    };
                @endphp
                
                <div style="width: {{ max($seg['percentage'], 0.5) }}%; height: 100%; background-color: {{ $bgColor }}; border: 1px solid {{ $borderColor }}; position: relative; border-radius: 2px; box-sizing: border-box; cursor: {{ $isInteractive ? 'pointer' : 'default' }};"
                     @if($isInteractive)
                         @mouseenter="activePopover = {{ $index }}"
                         @mouseleave="activePopover = null"
                         @click="activePopover = (activePopover === {{ $index }} ? null : {{ $index }})"
                     @endif
                >
                    @if($isGap)
                        <!-- Popover / Tooltip Interactivo para GAP PENDIENTE -->
                        <div x-show="activePopover === {{ $index }}"
                             x-transition
                             x-cloak
                             style="position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: 8px; width: 260px; background-color: #0f172a; color: #ffffff; padding: 10px 12px; border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5); font-size: 11px; z-index: 9999; border: 1px solid #f59e0b;"
                        >
                            <div style="display: flex; align-items: center; gap: 6px; color: #fbbf24; font-weight: 700; border-bottom: 1px solid #334155; padding-bottom: 4px; margin-bottom: 6px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px; min-width: 14px; display: inline-block;">
                                    <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                {{ $seg['label'] }}
                            </div>
                            <div style="line-height: 1.5; color: #cbd5e1;">
                                <div><span style="color: #94a3b8;">Tramo:</span> <strong style="color: #ffffff;">{{ $seg['from'] }}m</strong> a <strong style="color: #ffffff;">{{ $seg['to'] }}m</strong></div>
                                <div><span style="color: #94a3b8;">Longitud sin muestrear:</span> <strong style="color: #fde047;">{{ $seg['length'] }}m</strong></div>
                                <div style="font-size: 10px; color: #94a3b8; font-style: italic; margin-top: 4px; border-top: 1px solid #1e293b; padding-top: 4px;">
                                    {{ $seg['description'] }}
                                </div>
                            </div>
                        </div>
                    @elseif($isValidated)
                        <!-- Popover / Tooltip Interactivo para GAP VALIDADO -->
                        <div x-show="activePopover === {{ $index }}"
                             x-transition
                             x-cloak
                             style="position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: 8px; width: 280px; background-color: #0f172a; color: #ffffff; padding: 10px 12px; border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5); font-size: 11px; z-index: 9999; border: 1px solid #38bdf8;"
                        >
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px; border-bottom: 1px solid #334155; padding-bottom: 4px; margin-bottom: 6px;">
                                <div style="display: flex; align-items: center; gap: 6px; color: #38bdf8; font-weight: 700;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px; min-width: 14px; display: inline-block;">
                                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Gap Validado
                                </div>
                                @if(!empty($seg['reason']))
                                    <span style="background-color: #0369a1; color: #f0f9ff; font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 4px;">
                                        {{ $seg['reason'] }}
                                    </span>
                                @endif
                            </div>
                            <div style="line-height: 1.5; color: #cbd5e1;">
                                <div><span style="color: #94a3b8;">Tramo:</span> <strong style="color: #ffffff;">{{ $seg['from'] }}m</strong> a <strong style="color: #ffffff;">{{ $seg['to'] }}m</strong> (<span style="color: #7dd3fc; font-weight: 600;">{{ $seg['length'] }}m</span>)</div>
                                @if(!empty($seg['notes']))
                                    <div style="margin-top: 4px; padding: 4px 6px; background-color: #1e293b; border-radius: 4px; border-left: 2px solid #38bdf8;">
                                        <span style="color: #94a3b8; font-size: 10px;">Nota:</span> <span style="color: #e2e8f0; font-style: italic;">{{ $seg['notes'] }}</span>
                                    </div>
                                @endif
                                <div style="display: flex; justify-content: space-between; font-size: 10px; color: #94a3b8; margin-top: 6px; border-top: 1px solid #1e293b; padding-top: 4px;">
                                    <span>Por: <strong style="color: #f1f5f9;">{{ $seg['validated_by'] ?? 'Geología' }}</strong></span>
                                    @if(!empty($seg['validated_at']))
                                        <span>{{ $seg['validated_at'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Resumen Compacto de GAPs Detectados (Pendientes) -->
        @if(count($gapsOnly) > 0)
            <div style="margin-top: 10px; padding: 10px 12px; background-color: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px;">
                <div style="font-size: 12px; font-weight: 600; color: #92400e; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px; min-width: 16px; display: inline-block;">
                        <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Resumen de Tramos Faltantes ({{ count($gapsOnly) }} GAP{{ count($gapsOnly) > 1 ? 'S' : '' }} Pendiente{{ count($gapsOnly) > 1 ? 's' : '' }}):
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    @foreach($gapsOnly as $g)
                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background-color: #fef3c7; color: #78350f; font-size: 12px; font-weight: 500; border-radius: 6px; border: 1px solid #fde68a;">
                            📍 <strong>{{ $g['from'] }}m</strong> a <strong>{{ $g['to'] }}m</strong> (Faltan {{ $g['length'] }}m)
                        </span>
                    @endforeach
                </div>
            </div>
        @else
            <div style="font-size: 12px; color: #047857; font-weight: 500; display: flex; align-items: center; gap: 6px; padding-top: 4px; margin-top: 4px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px; min-width: 16px; display: inline-block;">
                    <path d="M5 13l4 4L19 7"></path>
                </svg>
                Perfil completo sin huecos pendientes. El 100% de la perforación está muestreada o validada.
            </div>
        @endif

        <!-- Historial y Resumen de GAPs Validados -->
        @if($validationsList->isNotEmpty())
            <div style="margin-top: 12px; padding: 12px; background-color: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px;">
                <div style="font-size: 12px; font-weight: 700; color: #0369a1; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">
                    <span style="display: flex; align-items: center; gap: 6px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px; min-width: 16px; display: inline-block;">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Historial de GAPs Validados ({{ $validationsList->count() }} tramo{{ $validationsList->count() > 1 ? 's' : '' }}):
                    </span>
                    <span style="font-size: 11px; font-weight: 600; color: #0284c7;">
                        Total Validado: {{ number_format($validationsList->sum(fn($v) => (float)$v->to_depth - (float)$v->from_depth), 2) }} m
                    </span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 6px;">
                    @foreach($validationsList as $v)
                        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 10px; background-color: #ffffff; border: 1px solid #e0f2fe; border-radius: 6px; font-size: 12px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-weight: 700; color: #0f172a;">
                                    📍 {{ number_format($v->from_depth, 2) }}m - {{ number_format($v->to_depth, 2) }}m
                                </span>
                                <span style="color: #64748b; font-size: 11px;">
                                    ({{ number_format((float)$v->to_depth - (float)$v->from_depth, 2) }}m)
                                </span>
                                <span style="padding: 2px 8px; background-color: #e0f2fe; color: #0369a1; border-radius: 4px; font-weight: 600; font-size: 11px;">
                                    {{ $v->reason }}
                                </span>
                            </div>

                            <div style="display: flex; align-items: center; gap: 12px; font-size: 11px; color: #64748b;">
                                @if(!empty($v->notes))
                                    <span style="color: #475569; font-style: italic; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $v->notes }}">
                                        💬 "{{ $v->notes }}"
                                    </span>
                                @endif
                                <span>👤 {{ $v->validator?->name ?? 'Usuario' }}</span>
                                @if($v->validated_at)
                                    <span>📅 {{ $v->validated_at->format('d/m/Y H:i') }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endif
