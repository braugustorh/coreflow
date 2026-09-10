<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>ALS Geochemistry — WO {{ $workOrder->work_order_code }}</title>
    <style>
        /* ── Base ─────────────────────────────────────────────────────── */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 7.5pt;
            color: #1a1a1a;
            background: #fff;
        }

        /* ── Page Layout ────────────────────────────────────────────── */
        @page {
            margin: 5mm 10mm;
        }

        .page {
            width: 100%;
            padding: 0;
        }

        /* ── Header strip ─────────────────────────────────────────────── */
        .header-strip {
            background: #002060;
            color: #fff;
            padding: 5px 8px;
            margin-bottom: 5px;
        }
        .header-strip table { width: 100%; border-collapse: collapse; }
        .header-strip td { vertical-align: middle; }

        .logo-text  { font-size: 13pt; font-weight: bold; color: #fff; line-height: 1; }
        .logo-sub   { font-size: 6pt;  color: #b0c8f0; }
        .form-title { font-size: 9.5pt; font-weight: bold; color: #fff; text-align: center; }
        .form-sub   { font-size: 6pt; color: #b0c8f0; text-align: center; }
        .doc-num    { font-size: 6.5pt; color: #b0c8f0; text-align: right; line-height: 1.4; }

        /* ── Two-column info ──────────────────────────────────────────── */
        .info-section { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        .info-section td { vertical-align: top; }
        .col-left  { width: 50%; padding-right: 4px; }
        .col-right { width: 50%; padding-left: 4px; }

        .info-box {
            border: 1px solid #002060;
            padding: 4px 6px 5px 6px;
        }
        .box-title {
            background: #002060;
            color: #fff;
            font-size: 6.5pt;
            font-weight: bold;
            padding: 2px 4px;
            margin: -4px -6px 4px -6px;
        }

        /* info rows */
        .irow { width: 100%; border-collapse: collapse; margin-bottom: 1.5px; }
        .irow .lbl {
            font-size: 6.5pt; color: #555; font-weight: bold;
            width: 95px; vertical-align: top; padding-right: 3px;
        }
        .irow .val {
            font-size: 7pt; color: #111;
            border-bottom: 1px dotted #ccc; vertical-align: top;
        }

        /* checkboxes */
        .cbrow { font-size: 7pt; margin: 2px 0; }
        .cb {
            display: inline-block;
            width: 8px; height: 8px;
            border: 1px solid #555;
            background: #fff;
            margin-right: 3px;
            text-align: center;
            line-height: 7px;
            font-size: 6pt;
            vertical-align: middle;
        }
        .cb.on { background: #002060; color: #fff; }

        /* ── Section title ────────────────────────────────────────────── */
        .sec-title {
            background: #002060;
            color: #fff;
            font-size: 7pt;
            font-weight: bold;
            padding: 2px 6px;
            margin: 5px 0 3px 0;
        }

        /* ── Sample table ─────────────────────────────────────────────── */
        .sample-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
            margin-bottom: 5px;
        }
        .sample-table th {
            background: #002060;
            color: #fff;
            font-weight: bold;
            padding: 3px 5px;
            text-align: center;
            border: 1px solid #001540;
        }
        .sample-table td {
            border: 1px solid #b8cde0;
            padding: 4px 6px;
            text-align: center;
            vertical-align: middle;
        }
        .sample-table .col-num   { width: 16%; }
        .sample-table .col-count { width: 9%; }
        .sample-table .col-prep  { width: 13%; }
        .sample-table .col-anal  { width: 46%; text-align: left; }

        .totals-row td {
            background: #e4ecf8 !important;
            font-weight: bold;
            font-size: 8pt;
            border-top: 2px solid #002060;
        }

        /* ── Notes box ────────────────────────────────────────────────── */
        .notes-box {
            border: 1px solid #c8d4e8;
            padding: 4px 6px;
            margin-bottom: 5px;
            background: #f8fafc;
        }
        .notes-title { font-size: 6.5pt; font-weight: bold; color: #002060; margin-bottom: 2px; }
        .notes-body  { font-size: 6.5pt; color: #555; }

        /* ── Footer ───────────────────────────────────────────────────── */
        .footer-box {
            border: 1px solid #002060;
            padding: 5px 8px;
        }
        .footer-title {
            font-size: 7pt; font-weight: bold; color: #002060;
            margin-bottom: 4px;
            border-bottom: 1px solid #c8d4e8;
            padding-bottom: 2px;
        }
        .footer-table { width: 100%; border-collapse: collapse; }
        .footer-table td { vertical-align: bottom; }
        .col-sigs { width: 62%; }
        .col-rush { width: 38%; text-align: center; vertical-align: middle; }

        .sig-line  { border-bottom: 1px solid #555; width: 170px; height: 16px; margin-bottom: 1px; }
        .sig-label { font-size: 6pt; color: #666; }

        .rush-on  {
            display: inline-block;
            border: 2.5px solid #dc2626;
            color: #dc2626;
            font-size: 15pt;
            font-weight: bold;
            padding: 3px 14px;
            letter-spacing: 2px;
        }
        .rush-off {
            display: inline-block;
            border: 2px solid #d1d5db;
            color: #aaa;
            font-size: 15pt;
            font-weight: bold;
            padding: 3px 14px;
            letter-spacing: 2px;
        }
        .rush-sub { font-size: 6pt; margin-top: 2px; }

        /* ── Doc footer ───────────────────────────────────────────────── */
        .doc-footer {
            text-align: center;
            font-size: 5.5pt;
            color: #aaa;
            border-top: 1px dotted #e5e7eb;
            padding-top: 3px;
            margin-top: 4px;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- HEADER                                                      --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <div class="header-strip">
        <table>
            <tr>
                <td style="width:130px;">
                    <div class="logo-text">ALS</div>
                    <div class="logo-sub">Geochemistry</div>
                </td>
                <td>
                    <div class="form-title">FORMULARIO DE ENVÍO DE MUESTRAS</div>
                    <div class="form-sub">Sample Submission Form — ALS Global</div>
                </td>
                <td style="width:120px;">
                    <div class="doc-num">
                        Dispatch #: <strong>{{ $workOrder->work_order_code }}</strong><br>
                        Fecha: {{ now()->format('d/m/Y') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- INFO — DOS COLUMNAS                                         --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <table class="info-section">
        <tr>
            {{-- Columna izquierda --}}
            <td class="col-left">
                <div class="info-box">
                    <div class="box-title">DETALLES DE LA ORDEN</div>

                    <table class="irow"><tr>
                        <td class="lbl">Empresa:</td>
                        <td class="val">{{ $companyName }}</td>
                    </tr></table>
                    <table class="irow"><tr>
                        <td class="lbl">Enviado / Autorizado:</td>
                        <td class="val">{{ $supervisor }}</td>
                    </tr></table>
                    <table class="irow"><tr>
                        <td class="lbl">Fecha de envío:</td>
                        <td class="val">{{ $sentDate }}</td>
                    </tr></table>
                    <table class="irow"><tr>
                        <td class="lbl">Proyecto:</td>
                        <td class="val">{{ $proyecto?->nombre ?? 'N/A' }}</td>
                    </tr></table>
                    <table class="irow"><tr>
                        <td class="lbl">Dispatch # / PO #:</td>
                        <td class="val"><strong>{{ $workOrder->work_order_code }}</strong></td>
                    </tr></table>
                    <table class="irow"><tr>
                        <td class="lbl">Sede:</td>
                        <td class="val">{{ $workOrder->sede?->name ?? 'N/A' }}</td>
                    </tr></table>
                </div>
            </td>

            {{-- Columna derecha --}}
            <td class="col-right">
                <div class="info-box">
                    <div class="box-title">TIPO DE MUESTRA Y ANÁLISIS</div>

                    <div style="font-size:6.5pt;font-weight:bold;color:#002060;margin-bottom:3px;">Tipo de Material:</div>

                    <div class="cbrow">
                        <span class="cb {{ $hasCoreSize ? 'on' : '' }}">{{ $hasCoreSize ? '&#10003;' : '&nbsp;' }}</span>
                        DC — Drill Core (Testigo de Perforación)
                    </div>
                    <div class="cbrow">
                        <span class="cb">&nbsp;</span> RC — Reverse Circulation
                    </div>
                    <div class="cbrow">
                        <span class="cb">&nbsp;</span> SO — Soil Sample
                    </div>
                    <div class="cbrow">
                        <span class="cb">&nbsp;</span> RK — Rock / Chip Sample
                    </div>

                    <div style="font-size:6.5pt;font-weight:bold;color:#002060;margin:4px 0 2px;">Instrucciones de Sobrelímites:</div>
                    <div class="cbrow">
                        <span class="cb on">&#10003;</span> Reportar mediante métodos de sobrelímites
                    </div>
                    <div class="cbrow">
                        <span class="cb">&nbsp;</span> Reportar como &gt; límite superior
                    </div>

                    <div style="margin-top:4px;font-size:6pt;color:#666;font-style:italic;">
                        Laboratorio: ALS Chemex — Preparación y análisis estándar.
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- TABLA DE MUESTRAS                                           --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <div class="sec-title">MUESTRAS ENVIADAS</div>

    <table class="sample-table">
        <thead>
            <tr>
                <th class="col-num">No. Inicio</th>
                <th class="col-num">No. Final</th>
                <th class="col-count">Cantidad</th>
                <th class="col-prep">Preparación</th>
                <th class="col-anal">Análisis (Métodos)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $sampleFrom }}</td>
                <td>{{ $sampleTo }}</td>
                <td>{{ $totalSamples }}</td>
                <td>PREP-31H</td>
                <td style="text-align:left;">
                    Ag, As, Cu, S por ME-OG46 &nbsp;|&nbsp; Au por AA23
                </td>
            </tr>
            {{-- Fila de total --}}
            <tr class="totals-row">
                <td colspan="2" style="text-align:right;">TOTAL DE MUESTRAS:</td>
                <td style="color:#002060;">{{ $totalSamples }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- NOTAS                                                       --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <div class="notes-box">
        <div class="notes-title">NOTAS DE ENVÍO:</div>
        <div class="notes-body">
            Todas las muestras deben estar correctamente etiquetadas con número de muestra y código QR.
            Se incluyen los manifiestos de cadena de custodia firmados.
            Tiempo de análisis estándar: 10 días hábiles desde la recepción en laboratorio.
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- FOOTER — FIRMAS + RUSH                                      --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <div class="footer-box">
        <div class="footer-title">AUTORIZACIONES Y DECLARACIONES</div>
        <table class="footer-table">
            <tr>
                <td class="col-sigs">
                    <table style="width:100%;border-collapse:collapse;">
                        <tr>
                            <td style="width:50%;padding-right:10px;vertical-align:bottom;">
                                <div class="sig-line">&nbsp;</div>
                                <div class="sig-label">Firma — Responsable de Envío</div>
                                <div class="sig-label">Nombre: {{ $supervisor }}</div>
                            </td>
                            <td style="width:50%;vertical-align:bottom;">
                                <div class="sig-line">&nbsp;</div>
                                <div class="sig-label">Firma — Supervisor Autorizado</div>
                                <div class="sig-label">Cargo: Supervisor de Muestreo</div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-top:5px;">
                                <div style="font-size:5.5pt;color:#aaa;border-top:1px dotted #e5e7eb;padding-top:3px;">
                                    Al firmar, el remitente confirma que la información es correcta y las muestras
                                    han sido preparadas conforme a las instrucciones de ALS Geochemistry.
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="col-rush">
                    @if($isRush)
                        <div class="rush-on">&#9889; RUSH</div>
                        <div class="rush-sub" style="color:#dc2626;font-weight:bold;">PROCESAMIENTO PRIORITARIO</div>
                    @else
                        <div class="rush-off">RUSH</div>
                        <div class="rush-sub" style="color:#aaa;">Procesamiento estándar</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- PIE DE DOCUMENTO                                            --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <div class="doc-footer">
        Generado por CoreFlow &mdash;
        Formulario_ALS_WO_{{ $workOrder->work_order_code }} &mdash;
        {{ now()->format('d/m/Y H:i') }}
    </div>

</div>
</body>
</html>
