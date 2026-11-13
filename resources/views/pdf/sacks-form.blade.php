<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Registro de Costales — WO {{ $workOrder->work_order_code }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 14mm 14mm 15mm 14mm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #111827;
            background: #ffffff;
        }

        /* ── Encabezado superior ────────────────────────── */
        .header-bar {
            background-color: #2E5C8A;
            color: #ffffff;
            padding: 8px 12px;
            margin-bottom: 10px;
        }

        .header-bar table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-title {
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 0.4px;
        }

        .header-subtitle {
            font-size: 7.5pt;
            color: #dbeafe;
            margin-top: 2px;
        }

        .wo-badge {
            text-align: right;
            font-size: 8pt;
            color: #dbeafe;
        }

        .wo-code {
            font-size: 13pt;
            font-weight: bold;
            color: #ffffff;
        }

        /* ── Tabla de información general ──────────────── */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .meta-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 7px;
            font-size: 8.5pt;
            vertical-align: middle;
        }

        .meta-label {
            background-color: #f1f5f9;
            font-weight: bold;
            color: #1e3a5f;
            width: 21%;
        }

        .meta-value {
            width: 29%;
            color: #0f172a;
        }

        /* ── Bloques de Costales (5 por fila) ──────────── */
        .section-heading {
            font-size: 9pt;
            font-weight: bold;
            color: #1B365D;
            border-bottom: 1.5px solid #2E5C8A;
            padding-bottom: 3px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .sack-block {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }

        .sack-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .sack-grid th {
            background-color: #2E5C8A;
            color: #ffffff;
            font-weight: bold;
            font-size: 9pt;
            text-align: center;
            padding: 5px 4px;
            border: 1px solid #1B365D;
            width: 20%;
        }

        .sack-grid th.empty-col {
            background-color: #ffffff;
            border: none;
        }

        .sack-grid td {
            text-align: center;
            vertical-align: middle;
            font-size: 9pt;
            padding: 4px 4px;
            height: 20px;
        }

        .sack-grid td.sample-cell {
            background-color: #F2F2F2;
            color: #000000;
            border: 1px solid #D9D9D9;
        }

        .sack-grid td.series-start-cell {
            background-color: #FCE4D6;
            color: #782800;
            font-weight: bold;
            border: 1.5px solid #ED7D31;
        }

        .sack-grid td.blank-sample-cell {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
        }

        .sack-grid td.empty-col {
            background-color: #ffffff;
            border: none;
        }

        /* ── Leyenda de nueva serie ────────────────────── */
        .legend {
            font-size: 7.5pt;
            color: #475569;
            margin-bottom: 14px;
        }

        .legend-swatch {
            display: inline-block;
            width: 10px;
            height: 10px;
            background-color: #FCE4D6;
            border: 1.5px solid #ED7D31;
            vertical-align: middle;
            margin-right: 4px;
        }

        /* ── Firmas de Autorización ────────────────────── */
        .signatures-box {
            margin-top: 14px;
            border: 1px solid #94a3b8;
            page-break-inside: avoid;
        }

        .signatures-title {
            background-color: #2E5C8A;
            color: #ffffff;
            font-size: 8.5pt;
            font-weight: bold;
            padding: 4px 8px;
            text-transform: uppercase;
        }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
        }

        .signatures-table th {
            background-color: #f1f5f9;
            color: #1e3a5f;
            font-size: 7.5pt;
            font-weight: bold;
            text-align: center;
            padding: 4px;
            border: 1px solid #cbd5e1;
        }

        .signatures-table td {
            border: 1px solid #cbd5e1;
            height: 44px;
            vertical-align: bottom;
            text-align: center;
            font-size: 7.5pt;
            color: #64748b;
            padding: 4px;
        }
    </style>
</head>
<body>
    <div class="header-bar">
        <table>
            <tr>
                <td>
                    <div class="header-title">REGISTRO DE COSTALES</div>
                    <div class="header-subtitle">Control de Embalaje y Despacho de Muestras a Laboratorio</div>
                </td>
                <td class="wo-badge">
                    <div>WORK ORDER</div>
                    <div class="wo-code">{{ $workOrder->work_order_code }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Tipo de Muestra</td>
            <td class="meta-value">Drill Core</td>
            <td class="meta-label">Proyecto</td>
            <td class="meta-value">{{ $proyectoNombre }}</td>
        </tr>
        <tr>
            <td class="meta-label">Cantidad de Muestras</td>
            <td class="meta-value">{{ $totalSamples }}</td>
            <td class="meta-label">Enviado desde</td>
            <td class="meta-value">{{ $sentFrom }}</td>
        </tr>
        <tr>
            <td class="meta-label">Cantidad de Sacos</td>
            <td class="meta-value">{{ $totalSacks }}</td>
            <td class="meta-label">Geólogo</td>
            <td class="meta-value">{{ $geologo }}</td>
        </tr>
        <tr>
            <td class="meta-label">Laboratorio</td>
            <td class="meta-value">ALS</td>
            <td class="meta-label">Vehículo</td>
            <td class="meta-value"></td>
        </tr>
        <tr>
            <td class="meta-label">Fecha de Envío</td>
            <td class="meta-value">{{ $sentDate }}</td>
            <td class="meta-label">Conductor</td>
            <td class="meta-value"></td>
        </tr>
    </table>

    <div class="section-heading">Detalle de Muestras por Costal</div>

    @php
        $sackBlocks = array_chunk($sacks, 5);
    @endphp

    @foreach($sackBlocks as $blockSacks)
        @php
            $maxRows = 4;
            foreach ($blockSacks as $sack) {
                if (count($sack['samples']) > $maxRows) {
                    $maxRows = count($sack['samples']);
                }
            }
        @endphp

        <div class="sack-block">
            <table class="sack-grid">
                <thead>
                    <tr>
                        @for($col = 0; $col < 5; $col++)
                            @if(isset($blockSacks[$col]))
                                <th>COSTAL {{ $blockSacks[$col]['number'] }}</th>
                            @else
                                <th class="empty-col"></th>
                            @endif
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @for($row = 0; $row < $maxRows; $row++)
                        <tr>
                            @for($col = 0; $col < 5; $col++)
                                @if(!isset($blockSacks[$col]))
                                    <td class="empty-col"></td>
                                @elseif(isset($blockSacks[$col]['samples'][$row]))
                                    @php
                                        $code = $blockSacks[$col]['samples'][$row];
                                        $isSeriesStart = !empty($seriesStarts[$code]);
                                    @endphp
                                    <td class="{{ $isSeriesStart ? 'series-start-cell' : 'sample-cell' }}">
                                        {{ $code }}
                                    </td>
                                @else
                                    <td class="blank-sample-cell"></td>
                                @endif
                            @endfor
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    @endforeach

    @if(!empty($seriesStarts))
        <div class="legend">
            <span class="legend-swatch"></span>
            Indica inicio de nueva serie o salto numérico de folios.
        </div>
    @endif

    <div class="signatures-box">
        <div class="signatures-title">Firmas de Autorización</div>
        <table class="signatures-table">
            <thead>
                <tr>
                    <th style="width: 38%;">ELABORÓ / SUPERVISOR DE MUESTREO</th>
                    <th style="width: 38%;">RECIBIÓ / CONDUCTOR</th>
                    <th style="width: 24%;">FECHA</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Nombre y Firma</td>
                    <td>Nombre y Firma</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
