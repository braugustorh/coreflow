@push('styles')
    <style>
        /* ===== Agile Capture — Scoped Premium Styles ===== */

        .ac-page {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* --- Search Section --- */
        .ac-search-section {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(139, 92, 246, 0.06) 100%);
            border: 1px solid rgba(99, 102, 241, 0.15);
            border-radius: 1rem;
            padding: 1.5rem;
            transition: box-shadow 0.3s ease, border-color 0.3s ease;
        }

        .ac-search-section:hover {
            box-shadow: 0 4px 24px rgba(99, 102, 241, 0.1);
            border-color: rgba(99, 102, 241, 0.25);
        }

        .ac-search-header {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            margin-bottom: 1rem;
        }

        .ac-search-icon {
            width: 2.25rem;
            height: 2.25rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 0.625rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .ac-search-icon svg {
            width: 1.125rem;
            height: 1.125rem;
        }

        .ac-search-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--fi-body-text-color, #1e293b);
        }

        .ac-search-subtitle {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 0.125rem;
        }

        .ac-search-row {
            display: flex;
            align-items: stretch;
            gap: 0.75rem;
        }

        .ac-search-row .ac-input-wrap {
            flex: 1;
            position: relative;
        }

        .ac-search-row .ac-input-wrap input {
            width: 100%;
            padding: 0.625rem 2.75rem 0.625rem 1rem;
            border-radius: 0.625rem;
            border: 1px solid rgba(99, 102, 241, 0.25);
            background: rgba(255, 255, 255, 0.95);
            font-size: 0.9rem;
            color: #1e293b;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .ac-search-row .ac-input-wrap input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
        }

        .ac-search-row .ac-input-wrap input::placeholder {
            color: #94a3b8;
        }

        /* --- Input Controls (Spinner & Clear) --- */
        .ac-input-controls {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            gap: 0.35rem;
            z-index: 10;
        }

        .ac-input-spinner {
            width: 1.25rem;
            height: 1.25rem;
            color: #6366f1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ac-input-spinner svg {
            width: 1.1rem;
            height: 1.1rem;
            animation: ac-spin 0.75s linear infinite;
        }

        @keyframes ac-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .ac-input-clear-btn {
            background: none;
            border: none;
            padding: 0.25rem;
            color: #94a3b8;
            cursor: pointer;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.15s, background 0.15s;
        }

        .ac-input-clear-btn:hover {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }

        .ac-input-clear-btn svg {
            width: 1rem;
            height: 1rem;
        }

        /* --- Suggestions Autocomplete Dropdown --- */
        .ac-suggestions-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            background: #ffffff;
            border: 1px solid rgba(99, 102, 241, 0.25);
            border-radius: 0.875rem;
            box-shadow: 0 20px 35px -5px rgba(15, 23, 42, 0.15), 0 10px 15px -5px rgba(15, 23, 42, 0.06);
            z-index: 60;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .ac-suggestions-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.625rem 1rem;
            background: rgba(99, 102, 241, 0.05);
            border-bottom: 1px solid rgba(99, 102, 241, 0.12);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6366f1;
        }

        .ac-suggestions-list {
            max-height: 280px;
            overflow-y: auto;
            padding: 0.375rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .ac-suggestion-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 0.875rem;
            border-radius: 0.625rem;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
        }

        .ac-suggestion-item:hover {
            background: rgba(99, 102, 241, 0.08);
            transform: translateX(2px);
        }

        .ac-sugg-icon {
            width: 2.125rem;
            height: 2.125rem;
            border-radius: 0.5rem;
            background: rgba(99, 102, 241, 0.12);
            color: #6366f1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .ac-sugg-icon svg {
            width: 1.15rem;
            height: 1.15rem;
        }

        .ac-sugg-content {
            flex: 1;
            min-width: 0;
        }

        .ac-sugg-name {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.925rem;
            font-weight: 700;
            color: #1e293b;
        }

        .ac-sugg-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.125rem;
            flex-wrap: wrap;
        }

        .ac-sugg-action {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6366f1;
            opacity: 0;
            transform: translateX(-4px);
            transition: all 0.15s ease;
        }

        .ac-suggestion-item:hover .ac-sugg-action {
            opacity: 1;
            transform: translateX(0);
        }

        .ac-suggestions-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.625rem 1rem;
            background: rgba(248, 250, 252, 0.9);
            border-top: 1px solid rgba(226, 232, 240, 0.8);
            font-size: 0.8rem;
            color: #64748b;
        }

        .ac-create-link {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 600;
            color: #6366f1;
            text-decoration: none;
            transition: color 0.15s;
        }

        .ac-create-link:hover {
            color: #4f46e5;
            text-decoration: underline;
        }

        /* --- Empty state suggestion --- */
        .ac-empty-suggestions {
            padding: 1.5rem 1.25rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
        }

        .ac-empty-icon {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 50%;
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.25rem;
        }

        .ac-empty-icon svg {
            width: 1.5rem;
            height: 1.5rem;
        }

        .ac-empty-title {
            font-size: 0.925rem;
            font-weight: 600;
            color: #1e293b;
        }

        .ac-empty-desc {
            font-size: 0.8rem;
            color: #64748b;
            max-width: 320px;
        }

        .ac-btn-create-suggestion {
            margin-top: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white !important;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 0.625rem;
            text-decoration: none !important;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .ac-btn-create-suggestion:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(99, 102, 241, 0.4);
        }

        .ac-badge-tag {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 0.125rem 0.375rem;
            border-radius: 0.375rem;
            text-transform: uppercase;
        }

        .ac-badge-tag.warning {
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
        }

        .ac-badge-tag.success {
            background: rgba(34, 197, 94, 0.15);
            color: #16a34a;
        }

        .ac-badge-tag.gray {
            background: rgba(148, 163, 184, 0.2);
            color: #64748b;
        }

        /* --- Barreno Active Badge & Row --- */
        .ac-barreno-active-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 0.875rem;
            flex-wrap: wrap;
        }

        .ac-barreno-active {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.875rem;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(16, 185, 129, 0.08));
            border: 1px solid rgba(34, 197, 94, 0.25);
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #16a34a;
        }

        .ac-btn-clear-barreno {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            background: rgba(148, 163, 184, 0.1);
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
        }

        .ac-btn-clear-barreno:hover {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .ac-btn-clear-barreno svg {
            width: 0.875rem;
            height: 0.875rem;
        }

        .ac-barreno-active .ac-dot {
            width: 8px;
            height: 8px;
            background: #22c55e;
            border-radius: 50%;
            animation: ac-pulse 2s ease-in-out infinite;
        }

        @keyframes ac-pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.5;
                transform: scale(0.8);
            }
        }

        /* --- Stats Grid --- */
        .ac-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        @media (max-width: 900px) {
            .ac-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 500px) {
            .ac-stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .ac-stat-card {
            position: relative;
            border-radius: 1rem;
            padding: 1.25rem;
            overflow: hidden;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            cursor: default;
        }

        .ac-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        .ac-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            opacity: 0.12;
            transform: translate(20px, -20px);
        }

        .ac-stat-card.total {
            background: linear-gradient(135deg, #6366f1 0%, #818cf8 100%);
            color: white;
        }

        .ac-stat-card.total::before {
            background: white;
        }

        .ac-stat-card.estandar {
            background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
            color: white;
        }

        .ac-stat-card.estandar::before {
            background: white;
        }

        .ac-stat-card.blanco {
            background: linear-gradient(135deg, #64748b 0%, #94a3b8 100%);
            color: white;
        }

        .ac-stat-card.blanco::before {
            background: white;
        }

        .ac-stat-card.duplicado {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
        }

        .ac-stat-card.duplicado::before {
            background: white;
        }

        .ac-stat-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.75rem;
        }

        .ac-stat-icon svg {
            width: 1.25rem;
            height: 1.25rem;
            color: white;
        }

        .ac-stat-label {
            font-size: 0.775rem;
            font-weight: 500;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .ac-stat-value {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.2;
            margin-top: 0.25rem;
        }

        /* --- Capture Form Section --- */
        .ac-form-section {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 1rem;
            padding: 1.5rem;
            transition: box-shadow 0.3s ease;
        }

        .ac-form-section:hover {
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.05);
        }

        .ac-form-header {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            margin-bottom: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.6);
        }

        .ac-form-icon {
            width: 2.25rem;
            height: 2.25rem;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 0.625rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .ac-form-icon svg {
            width: 1.125rem;
            height: 1.125rem;
        }

        .ac-form-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--fi-body-text-color, #1e293b);
        }

        .ac-form-actions {
            display: flex;
            justify-content: flex-end;
            padding-top: 1.25rem;
            margin-top: 0.5rem;
            border-top: 1px solid rgba(226, 232, 240, 0.6);
        }

        .ac-table-header {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            margin-bottom: 1.25rem;
        }

        .ac-table-icon {
            width: 2.25rem;
            height: 2.25rem;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-radius: 0.625rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .ac-table-icon svg {
            width: 1.125rem;
            height: 1.125rem;
        }

        .ac-table-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--fi-body-text-color, #1e293b);
        }

        /* --- Entrance Animation --- */
        .ac-animate-in {
            animation: ac-fadeSlideUp 0.4s ease-out both;
        }

        /* Global Modal Stacking Defense */
        .fi-modal-close-overlay {
            z-index: 50 !important;
        }

        .fi-modal-window-ctn {
            z-index: 50 !important;
        }

        .fi-modal {
            z-index: 50 !important;
        }

        .ac-animate-in:nth-child(2) {
            animation-delay: 0.05s;
        }

        .ac-animate-in:nth-child(3) {
            animation-delay: 0.1s;
        }

        .ac-animate-in:nth-child(4) {
            animation-delay: 0.15s;
        }

        @keyframes ac-fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes ac-fadeSlideUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        /* --- Dark Mode Support --- */
        .dark .ac-search-section {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(139, 92, 246, 0.08) 100%);
            border-color: rgba(99, 102, 241, 0.2);
        }

        .dark .ac-search-row .ac-input-wrap input {
            background: rgba(30, 41, 59, 0.85);
            border-color: rgba(99, 102, 241, 0.3);
            color: #e2e8f0;
        }

        .dark .ac-search-subtitle {
            color: #94a3b8;
        }

        .dark .ac-form-section,
        .dark .ac-table-section {
            background: rgba(30, 41, 59, 0.95);
            border-color: rgba(51, 65, 85, 0.6);
        }

        .dark .ac-form-header,
        .dark .ac-form-actions {
            border-color: rgba(51, 65, 85, 0.6);
        }

        .dark .ac-barreno-active {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.15), rgba(16, 185, 129, 0.1));
            border-color: rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }

        /* --- Grid Styles --- */
        .ac-grid-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .ac-grid-table th {
            background: rgba(99, 102, 241, 0.1);
            color: #4f46e5;
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid rgba(99, 102, 241, 0.2);
        }

        .ac-grid-table td {
            padding: 0.5rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            vertical-align: top;
        }

        .ac-grid-input {
            width: 100%;
            padding: 0.4rem 0.5rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.375rem;
            background: white;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: inherit;
            font-size: 0.85rem;
        }

        .ac-grid-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
        }

        .ac-grid-input:disabled {
            background: #f1f5f9;
            color: #94a3b8;
        }

        .ac-btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.25rem;
            color: #ef4444;
            border-radius: 0.25rem;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .ac-btn-icon:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        .dark .ac-grid-table th {
            background: rgba(99, 102, 241, 0.15);
            color: #818cf8;
            border-bottom-color: rgba(99, 102, 241, 0.3);
        }

        .dark .ac-grid-table td {
            border-bottom-color: rgba(51, 65, 85, 0.8);
        }

        .dark .ac-grid-input {
            background: #1e293b;
            border-color: #475569;
            color: #e2e8f0;
        }

        .dark .ac-grid-input:disabled {
            background: #0f172a;
            color: #475569;
        }

        .dark .ac-suggestions-dropdown {
            background: #1e293b;
            border-color: rgba(99, 102, 241, 0.35);
            box-shadow: 0 20px 35px -5px rgba(0, 0, 0, 0.5), 0 10px 15px -5px rgba(0, 0, 0, 0.2);
        }

        .dark .ac-suggestions-header {
            background: rgba(99, 102, 241, 0.1);
            border-bottom-color: rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
        }

        .dark .ac-sugg-name {
            color: #f8fafc;
        }

        .dark .ac-sugg-meta {
            color: #94a3b8;
        }

        .dark .ac-suggestion-item:hover {
            background: rgba(99, 102, 241, 0.15);
        }

        .dark .ac-sugg-icon {
            background: rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
        }

        .dark .ac-suggestions-footer {
            background: rgba(15, 23, 42, 0.6);
            border-top-color: rgba(51, 65, 85, 0.6);
            color: #94a3b8;
        }

        .dark .ac-empty-title {
            color: #f1f5f9;
        }

        .dark .ac-empty-desc {
            color: #94a3b8;
        }

        .dark .ac-btn-clear-barreno {
            background: rgba(51, 65, 85, 0.4);
            border-color: rgba(71, 85, 105, 0.6);
            color: #cbd5e1;
        }

        .dark .ac-btn-clear-barreno:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.4);
            color: #f87171;
        }
    </style>
@endpush

<x-filament-panels::page>
    <div class="ac-page">

        {{-- PASO 1: Búsqueda de Barreno con Autocompletado Reactivo --}}
        <div class="ac-search-section ac-animate-in"
             x-data="{ 
                 isOpen: false,
                 handleKeydown(e) {
                     if (e.key === 'Escape') {
                         this.isOpen = false;
                     }
                 }
             }"
             @click.outside="isOpen = false"
             @keydown.window="handleKeydown($event)">
            <div class="ac-search-header">
                <div class="ac-search-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
                <div>
                    <div class="ac-search-title">Selección de Barreno</div>
                    <div class="ac-search-subtitle">Escribe para autocompletar, seleccionar o registrar un barreno</div>
                </div>
            </div>

            <div class="ac-search-row">
                <div class="ac-input-wrap">
                    <input type="text"
                        wire:model.live.debounce.250ms="searchBarreno"
                        wire:keydown.enter="selectBarreno"
                        @focus="isOpen = true"
                        @input="isOpen = true"
                        autocomplete="off"
                        spellcheck="false"
                        placeholder="Escribe el nombre del barreno… (Ej: TDS25-007, DDH-001)" />

                    {{-- Controles integrados en el input: Spinner y Limpiar --}}
                    <div class="ac-input-controls">
                        <div wire:loading wire:target="searchBarreno" class="ac-input-spinner">
                            <svg fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                        </div>
                        @if(!empty(trim($searchBarreno)))
                            <button type="button"
                                wire:loading.remove
                                wire:target="searchBarreno"
                                wire:click="clearBarreno"
                                @click="isOpen = false"
                                class="ac-input-clear-btn"
                                title="Limpiar búsqueda">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        @endif
                    </div>

                    {{-- DROPDOWN DE SUGERENCIAS / COINCIDENCIAS --}}
                    @if(!empty(trim($searchBarreno)))
                        <div x-show="isOpen"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 transform -translate-y-2 scale-98"
                             x-transition:enter-end="opacity-100 transform translate-y-0 scale-100"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 transform translate-y-0 scale-100"
                             x-transition:leave-end="opacity-0 transform -translate-y-2 scale-98"
                             class="ac-suggestions-dropdown"
                             style="display: none;">

                            @if($this->barrenoSuggestions->isNotEmpty())
                                <div class="ac-suggestions-header">
                                    <span>Coincidencias encontradas ({{ $this->barrenoSuggestions->count() }})</span>
                                    <span style="font-weight: 500; text-transform: none; color: #94a3b8;">Clic para seleccionar</span>
                                </div>
                                <div class="ac-suggestions-list">
                                    @foreach($this->barrenoSuggestions as $suggestion)
                                        <div wire:click="selectBarrenoById({{ $suggestion->id }})"
                                             @click="isOpen = false"
                                             class="ac-suggestion-item">
                                            <div class="ac-sugg-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                                </svg>
                                            </div>
                                            <div class="ac-sugg-content">
                                                <div class="ac-sugg-name">
                                                    <span>{{ $suggestion->nombre_barreno }}</span>
                                                    @if($suggestion->is_child)
                                                        <span class="ac-badge-tag warning">Wedge / Hijo</span>
                                                    @elseif($suggestion->hasChildren())
                                                        <span class="ac-badge-tag success">Madre</span>
                                                    @endif
                                                    @if($suggestion->is_historical)
                                                        <span class="ac-badge-tag gray">Histórico</span>
                                                    @endif
                                                </div>
                                                <div class="ac-sugg-meta">
                                                    @if($suggestion->sede)
                                                        <span>📍 {{ $suggestion->sede->name }}</span>
                                                    @endif
                                                    @if($suggestion->proyecto)
                                                        <span>📁 {{ $suggestion->proyecto->nombre }}</span>
                                                    @endif
                                                    @if($suggestion->drill_hole_samples_count > 0)
                                                        <span>🧪 {{ $suggestion->drill_hole_samples_count }} muestra(s)</span>
                                                    @else
                                                        <span style="opacity: 0.7;">🧪 Sin muestras</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="ac-sugg-action">
                                                <span>Seleccionar</span>
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 14px; height: 14px;">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                                </svg>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="ac-suggestions-footer">
                                    <span>¿Buscas otro barreno?</span>
                                    <a href="{{ \App\Filament\Resources\DrillHoles\DrillHoleResource::getUrl('create', ['nombre_barreno' => trim($searchBarreno)]) }}"
                                       class="ac-create-link">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 14px; height: 14px;">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                        Crear nuevo barreno
                                    </a>
                                </div>
                            @else
                                {{-- Sin resultados --}}
                                <div class="ac-empty-suggestions">
                                    <div class="ac-empty-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607ZM10.5 7.5v6m3-3h-6" />
                                        </svg>
                                    </div>
                                    <div class="ac-empty-title">
                                        Sin coincidencias para "<strong>{{ $searchBarreno }}</strong>"
                                    </div>
                                    <div class="ac-empty-desc">
                                        No existe ningún barreno registrado con este nombre en el sistema.
                                    </div>
                                    <a href="{{ \App\Filament\Resources\DrillHoles\DrillHoleResource::getUrl('create', ['nombre_barreno' => trim($searchBarreno)]) }}"
                                       class="ac-btn-create-suggestion">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                        <span>Crear barreno "<strong>{{ $searchBarreno }}</strong>"</span>
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <x-filament::button wire:click="selectBarreno" wire:loading.attr="disabled"
                    icon="heroicon-m-magnifying-glass" color="primary">
                    <span wire:loading.remove wire:target="selectBarreno">Buscar</span>
                    <span wire:loading wire:target="selectBarreno">Buscando…</span>
                </x-filament::button>
            </div>

            @if($selectedBarrenoId && $barreno)
                <div class="ac-barreno-active-row">
                    <div class="ac-barreno-active">
                        <span class="ac-dot"></span>
                        Barreno activo: <strong style="margin-left:2px">{{ $barreno->nombre_barreno }}</strong>
                        @if($barreno->sede)
                            <span style="opacity: 0.75; font-weight: normal; margin-left: 4px;">• {{ $barreno->sede->name }}</span>
                        @endif
                        @if($barreno->proyecto)
                            <span style="opacity: 0.75; font-weight: normal; margin-left: 2px;">({{ $barreno->proyecto->nombre }})</span>
                        @endif
                    </div>
                    <button type="button" wire:click="clearBarreno" class="ac-btn-clear-barreno" title="Cambiar a otro barreno">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Cambiar Barreno
                    </button>
                </div>
            @endif
        </div>

        @if($selectedBarrenoId)
            {{-- WIDGETS DE ESTADÍSTICAS --}}
            <div class="ac-stats-grid ac-animate-in">
                <div class="ac-stat-card total">
                    <div class="ac-stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" />
                        </svg>
                    </div>
                    <div class="ac-stat-label">Total Muestras</div>
                    <div class="ac-stat-value">{{ $this->totalMuestras }}</div>
                </div>
                <div class="ac-stat-card estandar">
                    <div class="ac-stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="ac-stat-label">Estándares</div>
                    <div class="ac-stat-value">{{ $this->totalEstandares }}</div>
                </div>
                <div class="ac-stat-card blanco">
                    <div class="ac-stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.182 16.318A4.486 4.486 0 0 0 12.016 15a4.486 4.486 0 0 0-3.198 1.318M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" />
                        </svg>
                    </div>
                    <div class="ac-stat-label">Blancos</div>
                    <div class="ac-stat-value">{{ $this->totalBlancos }}</div>
                </div>
                <div class="ac-stat-card duplicado">
                    <div class="ac-stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                        </svg>
                    </div>
                    <div class="ac-stat-label">Duplicados</div>
                    <div class="ac-stat-value">{{ $this->totalDuplicados }}</div>
                </div>
            </div>

            {{-- PASO 2: Captura en Lote con Alpine.js --}}
            <div x-data="agileCaptureGrid(@js($this->selectedBarrenoId))" class="ac-form-section ac-animate-in" style="overflow:visible;">
                <div class="ac-form-header" style="justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                    <div style="display:flex; align-items:center; gap: 0.625rem;">
                        <div class="ac-form-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" />
                            </svg>
                        </div>
                        <div>
                            <div class="ac-form-title">Captura en Lote (Grid)</div>
                            <div class="ac-search-subtitle">Presione Enter en "Hasta" para añadir nueva fila</div>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap: 1rem;">
                        <div style="display:flex; align-items:center; gap: 0.5rem;">
                            <label style="font-size: 0.875rem; font-weight: 500;">Proyecto:</label>
                            <select x-model="defaultProyecto" class="ac-grid-input" style="width: 200px;">
                                <template x-for="p in proyectos" :key="p.id">
                                    <option :value="p.id" x-text="p.nombre"></option>
                                </template>
                            </select>
                        </div>
                        <x-filament::button x-on:click="saveBatch" color="success" icon="heroicon-m-check-circle"
                            x-bind:disabled="hasErrors()">
                            Guardar Lote (<span x-text="samples.length"></span>)
                        </x-filament::button>
                    </div>
                </div>

                <div style="overflow-x: auto; padding-bottom: 1rem;">
                    <table class="ac-grid-table">
                        <thead>
                            <tr>
                                <th>Sample Number</th>
                                <th>Sample Type</th>
                                <th>Control Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Sample Length</th>
                                <th>Weight</th>
                                <th>Comments</th>
                                <th>Validation</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(sample, index) in samples" :key="sample.id">
                                <tr
                                    :style="getRowError(index) ? 'background-color:#fee2e2;' : (getRowWarning(index) ? 'background-color:#fefce8;' : '')">
                                    <!-- No. Muestra -->
                                    <td>
                                        <input type="text" x-model="sample.sample_number" class="ac-grid-input"
                                            style="width: 110px;">
                                    </td>
                                    <!-- Tipo Muestra -->
                                    <td>
                                        <select x-model="sample.sample_type" class="ac-grid-input">
                                            <option value="O">Original</option>
                                            <option value="Control">Control</option>
                                        </select>
                                    </td>
                                    <!-- Control details -->
                                    <td style="min-width: 140px;">
                                        <template x-if="sample.sample_type === 'Control'">
                                            <div style="display:flex; flex-direction:column; gap:4px;">
                                                <select x-model="sample.control_type" class="ac-grid-input">
                                                    <option value="Standard">Standard</option>
                                                    <option :value="blankLabel" x-text="blankLabel"></option>
                                                    <option value="Duplicate">Duplicate</option>
                                                </select>
                                                <template x-if="sample.control_type === 'Standard'">
                                                    <select x-model="sample.standard_sample_id" class="ac-grid-input">
                                                        <option value="">Seleccione Std...</option>
                                                        <template x-for="s in estandares" :key="s.id">
                                                            <option :value="s.id" x-text="s.standard_name"></option>
                                                        </template>
                                                    </select>
                                                </template>
                                                <template x-if="sample.control_type === 'Duplicate'">
                                                    <select x-model="sample.duplicate_sample_id" class="ac-grid-input">
                                                        <option value="">Muestra...</option>
                                                        <template x-for="m in muestrasOriginales" :key="m.id">
                                                            <option :value="m.id" x-text="m.sample_number"></option>
                                                        </template>
                                                    </select>
                                                </template>
                                            </div>
                                        </template>
                                    </td>
                                    <!-- Desde -->
                                    <td>
                                        <input type="number" step="0.01" x-model="sample.from_depth" class="ac-grid-input"
                                            style="width: 70px;" :disabled="sample.sample_type === 'Control'">
                                    </td>
                                    <!-- Hasta -->
                                    <td>
                                        <input type="number" step="0.01" x-model="sample.to_depth" class="ac-grid-input"
                                            style="width: 70px;" :disabled="sample.sample_type === 'Control'">
                                    </td>
                                    <!-- Recup. -->
                                    <td>
                                        <input type="number" step="0.01" x-model="sample.sample_length"
                                            class="ac-grid-input" style="width: 70px;"
                                            :disabled="sample.sample_type === 'Control'"
                                            x-on:keydown.enter.prevent="addEmptyRow()">
                                    </td>
                                    <!-- Peso -->
                                    <td>
                                        <input type="number" step="0.01" x-model="sample.weight" class="ac-grid-input"
                                            style="width: 70px;">
                                    </td>
                                    <!-- Comentarios -->
                                    <td>
                                        <input type="text" x-model="sample.comentarios" class="ac-grid-input"
                                            style="width: 150px;" placeholder="Opcional">
                                    </td>
                                    <!-- Validación -->
                                    <td style="text-align:center; min-width: 90px;">
                                        <template x-if="getRowError(index)">
                                            <span style="color:#ef4444; font-size:0.75rem; font-weight:600;"
                                                x-text="getRowError(index)"></span>
                                        </template>
                                        <template x-if="!getRowError(index) && getRowWarning(index)">
                                            <span style="color:#ca8a04; font-size:0.75rem; font-weight:600;"
                                                x-text="getRowWarning(index)"></span>
                                        </template>
                                    </td>
                                    <!-- Acciones -->
                                    <td>
                                        <button type="button" x-on:click="removeRow(index)" class="ac-btn-icon"
                                            title="Eliminar">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor"
                                                style="width:1.2rem; height:1.2rem;">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div style="margin-top: 10px;">
                        <x-filament::button x-on:click="addEmptyRow()" color="gray" size="sm" icon="heroicon-m-plus">
                            Añadir Fila
                        </x-filament::button>
                    </div>
                </div>

                {{-- Modal Asignar Core Size --}}
                <template x-teleport="body">
                    <div x-show="showCoreSizeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display: none;" x-cloak>
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6 space-y-4 border border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>
                                Asignar Core Size
                            </h3>

                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Aplicar a:</label>
                                    <select x-model="coreSizeScope" class="ac-grid-input w-full">
                                        <option value="all">Todas las muestras del lote</option>
                                        <option value="range">Rango de muestras (Inicio a Fin)</option>
                                        <option value="single">Muestra única</option>
                                    </select>
                                </div>

                                <template x-if="coreSizeScope === 'range' || coreSizeScope === 'single'">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Muestra Inicio (X):</label>
                                        <input type="text" x-model="coreSizeStart" class="ac-grid-input w-full" placeholder="Ej: ML157100">
                                    </div>
                                </template>

                                <template x-if="coreSizeScope === 'range'">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Muestra Fin (Y):</label>
                                        <input type="text" x-model="coreSizeEnd" class="ac-grid-input w-full" placeholder="Ej: ML157110">
                                    </div>
                                </template>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Tamaño de Núcleo (Core Size):</label>
                                    <select x-model="selectedCoreSize" class="ac-grid-input w-full">
                                        <option value="">Seleccione Core Size...</option>
                                        <option value="BQ">BQ (36.5 mm)</option>
                                        <option value="NQ">NQ (47.6 mm)</option>
                                        <option value="NQ2">NQ2 (50.5 mm)</option>
                                        <option value="HQ">HQ (63.5 mm)</option>
                                        <option value="HQ3">HQ3 (61.1 mm)</option>
                                        <option value="PQ">PQ (83.0 mm)</option>
                                        <option value="N/A">N/A (No Aplica)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                                <button type="button" x-on:click="showCoreSizeModal = false" class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg dark:bg-gray-700 dark:text-gray-300">
                                    Cancelar
                                </button>
                                <button type="button" x-on:click="applyCoreSize()" class="px-4 py-1.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm">
                                    Aplicar Core Size
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- PASO 3: Tabla de Muestras --}}
            <div class="ac-table-header" style="margin-top: 0.5rem; margin-bottom: 0.875rem;">
                <div class="ac-table-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M10.875 12h-1.5m1.5 0c.621 0 1.125.504 1.125 1.125M12 12h7.5m-7.5 0c0 .621-.504 1.125-1.125 1.125M21.375 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-19.5-3.75c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m19.5 0h-17.25" />
                    </svg>
                </div>
                <div class="ac-table-title">Muestras del Barreno</div>
            </div>
            {{ $this->table }}
        @endif

    </div>

    @push('scripts')
        <script>
            window.qcSlotUploader = function(slotType, wireProperty) {
                return {
                    isDragOver: false,
                    isUploading: false,
                    progress: 0,
                    errorMessage: null,
                    localPreview: null,
                    slotType: slotType,
                    wireProp: wireProperty,

                    async compressImage(file) {
                        try {
                            if (typeof window.createImageBitmap !== 'function') return file;
                            const bmp = await createImageBitmap(file);
                            const maxDim = 1920;
                            const maxCurrent = Math.max(bmp.width, bmp.height);
                            const scale = maxCurrent > maxDim ? (maxDim / maxCurrent) : 1;
                            const canvas = document.createElement('canvas');
                            canvas.width = Math.round(bmp.width * scale);
                            canvas.height = Math.round(bmp.height * scale);
                            const ctx = canvas.getContext('2d');
                            if (!ctx) return file;
                            ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height);
                            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.82));
                            if (!blob) return file;
                            const baseName = (file.name || 'qc_photo').replace(/\.[^/.]+$/, "");
                            return new File([blob], baseName + ".jpg", { type: 'image/jpeg' });
                        } catch (e) {
                            console.warn('Compresión client-side no disponible, usando original:', e);
                            return file;
                        }
                    },

                    async processFile(file) {
                        if (!file) return;
                        if (!file.type || (!file.type.startsWith('image/') && !file.name.match(/\.(jpe?g|png|webp|heic|heif)$/i))) {
                            this.errorMessage = 'Por favor selecciona un archivo de imagen válido (JPG, PNG o WEBP).';
                            return;
                        }
                        this.errorMessage = null;
                        try {
                            if (this.localPreview) URL.revokeObjectURL(this.localPreview);
                            this.localPreview = URL.createObjectURL(file);
                        } catch (e) {
                            this.localPreview = null;
                        }
                        this.isUploading = true;
                        this.progress = 10;
                        try {
                            let finalFile = file;
                            try {
                                finalFile = await this.compressImage(file);
                            } catch (errCompress) {
                                finalFile = file;
                            }
                            this.progress = 25;
                            this.$wire.upload(
                                this.wireProp,
                                finalFile,
                                () => { this.isUploading = false; this.progress = 100; },
                                (error) => {
                                    this.isUploading = false;
                                    this.progress = 0;
                                    if (this.localPreview) { URL.revokeObjectURL(this.localPreview); this.localPreview = null; }
                                    this.errorMessage = 'Error al subir la fotografía. Por favor intenta de nuevo.';
                                },
                                (event) => {
                                    if (event && event.detail && typeof event.detail.progress !== 'undefined') {
                                        this.progress = 25 + Math.round(event.detail.progress * 0.72);
                                    }
                                }
                            );
                        } catch (err) {
                            this.isUploading = false;
                            this.progress = 0;
                            if (this.localPreview) { URL.revokeObjectURL(this.localPreview); this.localPreview = null; }
                            this.errorMessage = 'Error al procesar el archivo seleccionado.';
                        }
                    },

                    handleDrop(event) {
                        this.isDragOver = false;
                        const files = event.dataTransfer?.files;
                        if (files && files.length > 0) this.processFile(files[0]);
                    },

                    handleInput(event) {
                        const files = event.target?.files;
                        if (files && files.length > 0) this.processFile(files[0]);
                        event.target.value = '';
                    }
                };
            };

            const registerQcSlotComponent = () => {
                if (typeof window.Alpine !== 'undefined') {
                    window.Alpine.data('qcSlotUploader', window.qcSlotUploader);
                }
            };
            registerQcSlotComponent();
            document.addEventListener('alpine:init', registerQcSlotComponent);

            document.addEventListener('alpine:init', () => {
                Alpine.data('agileCaptureGrid', (initialBarrenoId = null) => ({
                    samples: [],
                    dbIntervals: [],
                    dbSampleNumbers: [],
                    proyectos: @js($this->proyectosOptions),
                    estandares: @js($this->estandaresOptions),
                    muestrasOriginales: @js($this->muestrasOriginalesOptions),
                    blankLabel: @js($this->blankLabel),
                    defaultProyecto: null,
                    lastToDepth: 0,
                    nextSampleNum: '',
                    defaultSampledAt: new Date().toISOString().slice(0, 16),
                    maxDepth: @js($this->barreno?->max_depth),
                    currentBarrenoId: initialBarrenoId,

                    showCoreSizeModal: false,
                    coreSizeScope: 'all',
                    coreSizeStart: '',
                    coreSizeEnd: '',
                    selectedCoreSize: '',

                    openCoreSizeModal() {
                        if (this.samples.length === 0) return;
                        this.coreSizeScope = 'all';
                        this.coreSizeStart = this.samples[0].sample_number || '';
                        this.coreSizeEnd = this.samples[this.samples.length - 1].sample_number || '';
                        this.selectedCoreSize = '';
                        this.showCoreSizeModal = true;
                    },

                    applyCoreSize() {
                        if (!this.selectedCoreSize) return;

                        const extractNum = (str) => {
                            let m = String(str || '').match(/(\d+)/);
                            return m ? parseInt(m[1], 10) : null;
                        };

                        if (this.coreSizeScope === 'all') {
                            this.samples.forEach(s => {
                                s.core_size = this.selectedCoreSize;
                            });
                        } else if (this.coreSizeScope === 'single') {
                            let start = String(this.coreSizeStart || '').trim().toUpperCase();
                            this.samples.forEach(s => {
                                if (String(s.sample_number || '').trim().toUpperCase() === start) {
                                    s.core_size = this.selectedCoreSize;
                                }
                            });
                        } else if (this.coreSizeScope === 'range') {
                            let n1 = extractNum(this.coreSizeStart);
                            let n2 = extractNum(this.coreSizeEnd);

                            if (n1 !== null && n2 !== null) {
                                let startNum = Math.min(n1, n2);
                                let endNum   = Math.max(n1, n2);

                                this.samples.forEach(s => {
                                    let val = extractNum(s.sample_number);
                                    if (val !== null && val >= startNum && val <= endNum) {
                                        s.core_size = this.selectedCoreSize;
                                    }
                                });
                            } else {
                                let s1 = String(this.coreSizeStart || '').trim().toUpperCase();
                                let s2 = String(this.coreSizeEnd || '').trim().toUpperCase();
                                let inRange = false;

                                this.samples.forEach(s => {
                                    let numStr = String(s.sample_number || '').trim().toUpperCase();
                                    if (numStr === s1 || numStr === s2) {
                                        s.core_size = this.selectedCoreSize;
                                        inRange = !inRange;
                                    } else if (inRange) {
                                        s.core_size = this.selectedCoreSize;
                                    }
                                });
                            }
                        }

                        this.showCoreSizeModal = false;
                    },

                    getStorageKey(barrenoId = null) {
                        const userId = @js(auth()->id());
                        const activeBarrenoId = barrenoId || this.currentBarrenoId;
                        return `coreflow_agile_capture_${userId}_${activeBarrenoId}`;
                    },

                    saveToStorage() {
                        const key = this.getStorageKey();
                        if (this.hasUnsavedChanges()) {
                            localStorage.setItem(key, JSON.stringify(this.samples));
                        } else {
                            localStorage.removeItem(key);
                        }
                    },

                    hasUnsavedChanges() {
                        if (this.samples.length > 1) return true;
                        if (this.samples.length === 1) {
                            let s = this.samples[0];
                            if (s.to_depth !== '' || s.sample_length !== '' || s.weight !== '' || s.comentarios !== '') return true;
                        }
                        return false;
                    },

                    init() {
                        if (this.proyectos.length > 0) {
                            this.defaultProyecto = this.proyectos[0].id;
                        }

                        // Cargar borrador guardado en localStorage si existe para este barreno
                        const storageKey = this.getStorageKey();
                        const saved = localStorage.getItem(storageKey);
                        if (saved) {
                            try {
                                const parsed = JSON.parse(saved);
                                if (Array.isArray(parsed) && parsed.length > 0) {
                                    this.samples = parsed;
                                } else {
                                    this.addEmptyRow();
                                }
                            } catch (e) {
                                console.error("Error al cargar el borrador de localStorage:", e);
                                this.addEmptyRow();
                            }
                        } else {
                            this.addEmptyRow();
                        }

                        // Observar cambios para autoguardar en tiempo real
                        this.$watch('samples', (value) => {
                            this.saveToStorage();
                        });

                        // Alerta al cerrar/recargar con cambios sin guardar
                        window.addEventListener('beforeunload', (event) => {
                            if (this.hasUnsavedChanges()) {
                                event.preventDefault();
                                event.returnValue = '';
                            }
                        });

                        window.addEventListener('barreno-selected', (event) => {
                            let data = event.detail[0];
                            this.dbIntervals = data.existingIntervals || [];
                            this.dbSampleNumbers = data.existingSampleNumbers || [];
                            this.nextSampleNum = data.nextSampleNumber;
                            this.lastToDepth = data.fromDepth;
                            this.maxDepth = data.maxDepth || null;
                            if (data.proyectoId) this.defaultProyecto = data.proyectoId;
                            if (data.sampledAt) this.defaultSampledAt = data.sampledAt.replace(' ', 'T');

                            // Cargar borrador para el nuevo barreno seleccionado si existe en localStorage
                            const barrenoId = data.selectedBarrenoId;
                            if (barrenoId) {
                                const newKey = this.getStorageKey(barrenoId);
                                const savedDraft = localStorage.getItem(newKey);
                                if (savedDraft) {
                                    try {
                                        const parsed = JSON.parse(savedDraft);
                                        if (Array.isArray(parsed) && parsed.length > 0) {
                                            this.samples = parsed;
                                            return; // Si restauramos borrador, evitamos limpiar y añadir fila vacía por defecto
                                        }
                                    } catch (e) {
                                        console.error("Error al cargar borrador desde localStorage en barreno-selected:", e);
                                    }
                                }
                            }

                            // Si no hay borrador previo para este barreno, limpiamos e inicializamos una fila vacía
                            this.samples = [];
                            this.addEmptyRow();
                        });

                        window.addEventListener('batch-saved', (event) => {
                            localStorage.removeItem(this.getStorageKey());
                            let data = event.detail[0] || {};
                            if (data.existingIntervals) this.dbIntervals = data.existingIntervals;
                            if (data.existingSampleNumbers) this.dbSampleNumbers = data.existingSampleNumbers;
                            if (data.nextSampleNumber) this.nextSampleNum = data.nextSampleNumber;
                            if (data.fromDepth !== undefined) this.lastToDepth = data.fromDepth;

                            this.samples = [];
                            this.addEmptyRow();
                        });
                    },

                    addEmptyRow() {
                        let sNumber = this.samples.length === 0 ? this.nextSampleNum : this.incrementSampleNumber(this.samples[this.samples.length - 1].sample_number);
                        let fDepth = this.samples.length === 0 ? this.lastToDepth : this.samples[this.samples.length - 1].to_depth;
                        let sDate = new Date().toISOString().slice(0, 10);

                        this.samples.push({
                            id: Date.now() + Math.random(),
                            proyecto_id: this.defaultProyecto,
                            sample_number: sNumber,
                            sample_type: 'O',
                            control_type: 'Standard',
                            standard_sample_id: '',
                            duplicate_sample_id: '',
                            from_depth: fDepth || 0,
                            to_depth: '',
                            sample_length: '',
                            weight: '',
                            sampled_at: sDate,
                            core_size: null,
                            comentarios: ''
                        });
                    },

                    removeRow(index) {
                        this.samples.splice(index, 1);
                        if (this.samples.length === 0) {
                            this.addEmptyRow();
                        }
                    },

                    incrementSampleNumber(num) {
                        if (!num) return '';
                        let textPart = num.replace(/[0-9]+$/, '');
                        let numPart = num.match(/[0-9]+$/);
                        if (numPart) {
                            let nextN = parseInt(numPart[0], 10) + 1;
                            return textPart + String(nextN).padStart(numPart[0].length, '0');
                        }
                        return num + '1';
                    },

                    getRowError(index) {
                        let sample = this.samples[index];

                        // 1. Validar campos obligatorios generales
                        if (!sample.sample_number || sample.sample_number.trim() === '') {
                            return 'Falta No. Muestra';
                        }

                        // Validar duplicados en el lote o base de datos
                        let sampleNum = sample.sample_number;
                        if (sampleNum) {
                            if (this.dbSampleNumbers.includes(sampleNum)) {
                                return 'Muestra Registrada (DB)';
                            }
                            for (let i = 0; i < this.samples.length; i++) {
                                if (i === index) continue;
                                if (this.samples[i].sample_number === sampleNum) {
                                    return 'Muestra Duplicada';
                                }
                            }
                        }

                        // 2. Validaciones específicas según el tipo de muestra
                        if (sample.sample_type === 'Control') {
                            if (!sample.control_type || sample.control_type.trim() === '') {
                                return 'Falta Tipo Control';
                            }
                            if (sample.control_type === 'Standard' && !sample.standard_sample_id) {
                                return 'Falta Estándar';
                            }
                            if (sample.control_type === 'Duplicate' && !sample.duplicate_sample_id) {
                                return 'Falta Muest. Original';
                            }
                            let w = parseFloat(sample.weight);
                            if (sample.weight !== '' && sample.weight !== null && (isNaN(w) || w <= 0)) {
                                return 'Peso inválido (>0)';
                            }
                        } else {
                            // Original: from, to y sample_length son obligatorios (peso es opcional)
                            let fd = parseFloat(sample.from_depth);
                            let td = parseFloat(sample.to_depth);
                            let sl = parseFloat(sample.sample_length);
                            let w = parseFloat(sample.weight);

                            if (isNaN(fd)) return 'Falta Desde (FROM)';
                            if (isNaN(td)) return 'Falta Hasta (TO)';
                            if (isNaN(sl)) return 'Falta Recuperación';
                            if (sample.weight !== '' && sample.weight !== null && (isNaN(w) || w <= 0)) return 'Peso inválido (>0)';

                            if (fd >= td) {
                                return 'Desde >= Hasta';
                            }

                            // Validar profundidad máxima (max_depth) si está declarada
                            let maxD = parseFloat(this.maxDepth);
                            if (!isNaN(maxD) && maxD > 0) {
                                if (fd > maxD) {
                                    return `Desde (${fd}m) > Max (${maxD}m)`;
                                }
                                if (td > maxD) {
                                    return `Hasta (${td}m) > Max (${maxD}m)`;
                                }
                            }

                            let length = Math.round((td - fd) * 100) / 100;
                            if (sl > length) {
                                return 'Recup < Longitud';
                            }

                            // Validar traslapes con la base de datos
                            for (let dbInterval of this.dbIntervals) {
                                let dbFd = parseFloat(dbInterval.from_depth);
                                let dbTd = parseFloat(dbInterval.to_depth);
                                if (Math.max(fd, dbFd) < Math.min(td, dbTd)) {
                                    return 'Traslape con BD';
                                }
                            }

                            // Validar traslapes dentro del mismo lote
                            for (let i = 0; i < this.samples.length; i++) {
                                if (i === index) continue;
                                let o = this.samples[i];
                                if (o.sample_type === 'Control') continue;
                                let oFd = parseFloat(o.from_depth);
                                let oTd = parseFloat(o.to_depth);
                                if (!isNaN(oFd) && !isNaN(oTd)) {
                                    if (Math.max(fd, oFd) < Math.min(td, oTd)) {
                                        return 'Traslape detectado';
                                    }
                                }
                            }
                        }
                        return null;
                    },

                    getRowWarning(index) {
                        let sample = this.samples[index];
                        if (sample.sample_type === 'Control') return null;

                        let fd = parseFloat(sample.from_depth);

                        if (index === 0) {
                            let lTd = parseFloat(this.lastToDepth);
                            if (!isNaN(fd) && !isNaN(lTd)) {
                                if (fd > lTd) return '⚠️ GAP con BD';
                            }
                        } else {
                            for (let i = index - 1; i >= 0; i--) {
                                let prev = this.samples[i];
                                if (prev.sample_type !== 'Control') {
                                    let prevTd = parseFloat(prev.to_depth);
                                    if (!isNaN(fd) && !isNaN(prevTd)) {
                                        if (fd > prevTd) return '⚠️ GAP';
                                    }
                                    break;
                                }
                            }
                        }
                        return null;
                    },

                    hasErrors() {
                        for (let i = 0; i < this.samples.length; i++) {
                            if (this.getRowError(i)) return true;
                        }
                        return false;
                    },

                    saveBatch() {
                        if (this.hasErrors()) return;

                        // Aplicar el proyecto actual a todas las muestras antes de enviarlas
                        this.samples.forEach(s => {
                            s.proyecto_id = this.defaultProyecto;
                        });

                        @this.call('saveBatch', this.samples);
                    }
                }));
            });
        </script>
    @endpush
</x-filament-panels::page>