@php use Illuminate\Support\Str; @endphp
{{-- Etiqueta de muestra con QR visual --}}
<div class="label-preview-container" id="label-preview-{{ $record->id }}">

    {{-- Header de la etiqueta --}}
    <div class="label-header">
        <div class="label-logo-area">
            <div class="label-logo-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M11.54 22.351l.07.04.028.016a.76.76 0 00.723 0l.028-.015.071-.041a16.975 16.975 0 001.144-.742 19.58 19.58 0 002.683-2.282c1.944-1.99 3.963-4.98 3.963-8.827a8.25 8.25 0 00-16.5 0c0 3.846 2.02 6.837 3.963 8.827a19.58 19.58 0 002.682 2.282 16.975 16.975 0 001.145.742zM12 13.5a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="label-company">
                <span class="label-company-name">CoreFlow</span>
                <span class="label-company-sub">Sistema de Gestión Minera</span>
            </div>
        </div>
        <div class="label-status-badge">
            <span>MUESTRA ACTIVA</span>
        </div>
    </div>

    {{-- Body principal --}}
    <div class="label-body">

        {{-- Lado izquierdo: datos de la muestra --}}
        <div class="label-data-section">
            <div class="label-sample-number">
                <span class="label-field-hint">No. de Muestra</span>
                <span class="label-sample-value">{{ $record->sample_number }}</span>
            </div>

            <div class="label-fields-grid">
                <div class="label-field">
                    <span class="label-field-label">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="label-field-icon"><path fill-rule="evenodd" d="M4 16.5v-13h-.25a.75.75 0 010-1.5h12.5a.75.75 0 010 1.5H16v13h.25a.75.75 0 010 1.5h-3.5a.75.75 0 01-.75-.75v-2.5a.75.75 0 00-.75-.75h-2.5a.75.75 0 00-.75.75v2.5a.75.75 0 01-.75.75h-3.5a.75.75 0 010-1.5H4zm3-11a.5.5 0 01.5-.5h1a.5.5 0 01.5.5v1a.5.5 0 01-.5.5h-1a.5.5 0 01-.5-.5v-1zm.5 3.5a.5.5 0 00-.5.5v1a.5.5 0 00.5.5h1a.5.5 0 00.5-.5v-1a.5.5 0 00-.5-.5h-1zM11 5.5a.5.5 0 01.5-.5h1a.5.5 0 01.5.5v1a.5.5 0 01-.5.5h-1a.5.5 0 01-.5-.5v-1zm.5 3.5a.5.5 0 00-.5.5v1a.5.5 0 00.5.5h1a.5.5 0 00.5-.5v-1a.5.5 0 00-.5-.5h-1z" clip-rule="evenodd" /></svg>
                        Sede
                    </span>
                    <span class="label-field-value">{{ $record->proyecto?->sede?->nombre ?? '—' }}</span>
                </div>

                <div class="label-field">
                    <span class="label-field-label">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="label-field-icon"><path d="M2 4.5A2.5 2.5 0 014.5 2h11a2.5 2.5 0 010 5h-11A2.5 2.5 0 012 4.5zM2.75 9.083a.75.75 0 000 1.5h14.5a.75.75 0 000-1.5H2.75zM2.75 12.663a.75.75 0 000 1.5h14.5a.75.75 0 000-1.5H2.75zM2.75 16.25a.75.75 0 000 1.5h14.5a.75.75 0 000-1.5H2.75z" /></svg>
                        Proyecto
                    </span>
                    <span class="label-field-value">{{ $record->proyecto?->nombre ?? '—' }}</span>
                </div>

                <div class="label-field">
                    <span class="label-field-label">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="label-field-icon"><path fill-rule="evenodd" d="M6 3.75A2.75 2.75 0 018.75 1h2.5A2.75 2.75 0 0114 3.75v.443c.572.055 1.14.122 1.706.2C17.053 4.582 18 5.75 18 7.07v3.469c0 1.126-.694 2.191-1.83 2.54-1.952.599-4.024.921-6.17.921s-4.219-.322-6.17-.921C2.694 12.73 2 11.665 2 10.539V7.07c0-1.321.947-2.489 2.294-2.676A41.047 41.047 0 016 4.193V3.75zm6.5 0v.325a41.622 41.622 0 00-5 0V3.75c0-.69.56-1.25 1.25-1.25h2.5c.69 0 1.25.56 1.25 1.25zM10 10a1 1 0 00-1 1v.01a1 1 0 001 1h.01a1 1 0 001-1V11a1 1 0 00-1-1H10z" clip-rule="evenodd" /><path d="M3 15.055v-.684c.126.053.255.1.39.142 2.1.644 4.313.992 6.61.992 2.297 0 4.51-.348 6.61-.992.135-.041.264-.089.39-.142v.684c0 1.347-.985 2.53-2.363 2.686a41.454 41.454 0 01-9.274 0C3.985 17.585 3 16.402 3 15.055z" /></svg>
                        Work Order
                    </span>
                    <span class="label-field-value {{ $record->workOrder ? '' : 'label-field-empty' }}">
                        {{ $record->workOrder?->work_order_code ?? 'Sin asignar' }}
                    </span>
                </div>



                @if($record->core_size)
                <div class="label-field">
                    <span class="label-field-label">Core Size</span>
                    <span class="label-core-badge label-core-{{ strtolower($record->core_size) }}">{{ $record->core_size }}</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Lado derecho: QR Code --}}
        <div class="label-qr-section">
            <div class="label-qr-wrapper">
                {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(110)->color(30, 41, 59)->generate($record->qr_token) !!}
            </div>
            <div class="label-qr-token">{{ Str::limit($record->qr_token, 18) }}</div>
        </div>
    </div>

    <div class="label-footer">
        @if($record->weight)
            <span>Peso: <strong>{{ $record->weight }} kg</strong></span>
        @endif
    </div>

    {{-- Botón imprimir --}}
    <div class="label-print-actions">
        <button onclick="printLabel('label-preview-{{ $record->id }}')" class="label-print-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5 2.75C5 1.784 5.784 1 6.75 1h6.5c.966 0 1.75.784 1.75 1.75v3.552c.377.046.752.097 1.126.153A2.212 2.212 0 0118 8.653v4.097A2.25 2.25 0 0115.75 15h-.241l.305 1.984A1.75 1.75 0 0114.084 19H5.915a1.75 1.75 0 01-1.73-2.016L4.49 15H4.25A2.25 2.25 0 012 12.75V8.653c0-1.082.775-2.034 1.874-2.198.374-.056.75-.107 1.126-.153V2.75zM6.5 3.25v3.29l.687-.051C8.116 6.421 9.05 6.375 10 6.375s1.884.046 2.813.114l.687.051V3.25a.25.25 0 00-.25-.25h-6.5a.25.25 0 00-.25.25zm-.878 5.343c-.999.074-1.985.181-2.954.32A.714.714 0 002 9.605v3.145c0 .414.336.75.75.75h.714l.328-2.14A.75.75 0 014.535 10.5H5.5v-1.75a.75.75 0 01.75-.75h7.5a.75.75 0 01.75.75v1.75h.965a.75.75 0 01.743.86l-.328 2.14h.714a.75.75 0 00.75-.75V9.605a.714.714 0 00-.668-.692c-.969-.139-1.955-.246-2.954-.32a.25.25 0 01-.228-.249V8.5H6.5v.344a.25.25 0 01-.228.249z" clip-rule="evenodd" />
            </svg>
            Imprimir Etiqueta
        </button>
    </div>
</div>

<style>
.label-preview-container {
    font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
    max-width: 560px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
}
.label-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.label-logo-area { display: flex; align-items: center; gap: 10px; }
.label-logo-icon {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: white;
}
.label-logo-icon svg { width: 18px; height: 18px; }
.label-company-name { display: block; color: white; font-weight: 700; font-size: 0.9rem; }
.label-company-sub { display: block; color: #94a3b8; font-size: 0.7rem; }
.label-status-badge {
    background: rgba(34,197,94,0.15);
    border: 1px solid rgba(34,197,94,0.3);
    color: #4ade80;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.06em;
}
.label-body {
    display: flex;
    gap: 0;
    padding: 18px;
    align-items: flex-start;
}
.label-data-section { flex: 1; }
.label-sample-number { margin-bottom: 14px; }
.label-field-hint { display: block; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; margin-bottom: 2px; }
.label-sample-value { font-size: 1.75rem; font-weight: 800; color: #1e293b; letter-spacing: -0.02em; }
.label-fields-grid { display: flex; flex-direction: column; gap: 8px; }
.label-field { display: flex; flex-direction: column; gap: 1px; }
.label-field-label {
    display: flex; align-items: center; gap: 4px;
    font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.06em;
    color: #94a3b8;
}
.label-field-icon { width: 11px; height: 11px; }
.label-field-value { font-size: 0.82rem; font-weight: 600; color: #334155; }
.label-field-empty { color: #cbd5e1; font-style: italic; font-weight: 400; }
.label-core-badge {
    display: inline-flex; align-items: center;
    padding: 2px 10px; border-radius: 20px;
    font-size: 0.72rem; font-weight: 700;
}
.label-core-pq { background: #dbeafe; color: #1d4ed8; }
.label-core-hq { background: #dcfce7; color: #166534; }
.label-core-nq { background: #fef9c3; color: #854d0e; }
.label-core-bq { background: #fee2e2; color: #991b1b; }
.label-qr-section {
    display: flex; flex-direction: column; align-items: center;
    gap: 8px; padding-left: 16px; flex-shrink: 0;
}
.label-qr-wrapper {
    background: white;
    padding: 8px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    display: flex; align-items: center; justify-content: center;
}
.label-qr-canvas canvas { display: block; }
.label-qr-token {
    font-size: 0.58rem; color: #94a3b8;
    font-family: monospace; text-align: center;
    max-width: 110px; word-break: break-all;
}
.label-footer {
    padding: 10px 18px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    font-size: 0.75rem;
    color: #64748b;
    display: flex; gap: 6px; flex-wrap: wrap;
}
.label-print-actions {
    padding: 12px 18px;
    display: flex; justify-content: center;
    border-top: 1px solid #e2e8f0;
}
.label-print-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 20px; border-radius: 8px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; font-size: 0.82rem; font-weight: 600;
    border: none; cursor: pointer;
    transition: opacity 0.2s, transform 0.1s;
}
.label-print-btn:hover { opacity: 0.9; transform: translateY(-1px); }
.label-print-btn svg { width: 15px; height: 15px; }

@media print {
    .label-print-actions { display: none !important; }
    .label-preview-container { box-shadow: none; border: 1px solid #ccc; }
}
</style>

<script>
function printLabel(containerId) {
    var el = document.getElementById(containerId);
    if (!el) return;
    var win = window.open('', '_blank', 'width=700,height=600');
    win.document.write('<html><head><title>Etiqueta</title><style>body{margin:0;padding:20px;font-family:Inter,system-ui,sans-serif;background:white;}</style></head><body>');
    win.document.write(el.innerHTML);
    win.document.write('</body></html>');
    win.document.close();
    setTimeout(function() { win.print(); }, 800);
}
</script>
