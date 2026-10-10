<div>
    <style>
        /* ===== QC Photo Uploader - Senior UX Scoped Styles ===== */
        .qcp-wrapper {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            width: 100%;
            font-family: inherit;
        }

        /* SVG Sizing Defenses */
        .qcp-wrapper svg {
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
        }

        /* Top Header */
        .qcp-header-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.875rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.03);
        }
        .dark .qcp-header-card {
            background: #111827;
            border-color: #1f2937;
        }

        .qcp-header-left {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }

        .qcp-sample-line {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            flex-wrap: wrap;
        }

        .qcp-sample-title {
            font-size: 1.125rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.01em;
        }
        .dark .qcp-sample-title {
            color: #f8fafc;
        }

        .qcp-pill-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .qcp-tag-std {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        .dark .qcp-tag-std {
            background: rgba(30, 58, 138, 0.4);
            color: #93c5fd;
            border-color: #1e40af;
        }
        .qcp-tag-blk {
            background: #faf5ff;
            color: #7e22ce;
            border: 1px solid #e9d5ff;
        }
        .dark .qcp-tag-blk {
            background: rgba(88, 28, 135, 0.4);
            color: #d8b4fe;
            border-color: #6b21a8;
        }

        .qcp-meta-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.75rem;
            color: #64748b;
        }
        .dark .qcp-meta-row {
            color: #94a3b8;
        }
        .qcp-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }

        .qcp-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.875rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .qcp-status-complete {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .dark .qcp-status-complete {
            background: rgba(6, 78, 59, 0.35);
            color: #6ee7b7;
            border-color: #047857;
        }
        .qcp-status-pending {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .dark .qcp-status-pending {
            background: rgba(120, 53, 15, 0.35);
            color: #fde68a;
            border-color: #b45309;
        }
        .qcp-status-locked {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .dark .qcp-status-locked {
            background: rgba(127, 29, 29, 0.35);
            color: #fca5a5;
            border-color: #991b1b;
        }

        /* Slots Grid */
        .qcp-slots-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
        }
        @media (min-width: 640px) {
            .qcp-slots-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Slot Card */
        .qcp-slot-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.875rem;
            padding: 1.125rem;
            display: flex;
            flex-direction: column;
            gap: 0.875rem;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.03);
        }
        .dark .qcp-slot-card {
            background: #111827;
            border-color: #1f2937;
        }
        .qcp-slot-card.is-complete {
            border-color: #86efac;
            background: linear-gradient(to bottom, #f0fdf4 0%, #ffffff 100%);
        }
        .dark .qcp-slot-card.is-complete {
            border-color: #047857;
            background: linear-gradient(to bottom, rgba(6, 78, 59, 0.2) 0%, #111827 100%);
        }

        /* Slot Card Header */
        .qcp-slot-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
        }
        .qcp-slot-title-group {
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
        }
        .qcp-slot-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 700;
            color: #0f172a;
        }
        .dark .qcp-slot-title {
            color: #f8fafc;
        }
        .qcp-step-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 6px;
            background: #e2e8f0;
            color: #334155;
            font-size: 0.7rem;
            font-weight: 800;
        }
        .dark .qcp-step-num {
            background: #334155;
            color: #f1f5f9;
        }
        .qcp-slot-card.is-complete .qcp-step-num {
            background: #10b981;
            color: #ffffff;
        }
        .qcp-slot-subtitle {
            font-size: 0.725rem;
            color: #64748b;
        }
        .dark .qcp-slot-subtitle {
            color: #94a3b8;
        }

        /* Photo Viewer / Image Container */
        .qcp-image-wrap {
            position: relative;
            width: 100%;
            aspect-ratio: 4 / 3;
            border-radius: 0.625rem;
            overflow: hidden;
            background: #0f172a;
            border: 1px solid #cbd5e1;
            box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.05);
        }
        .dark .qcp-image-wrap {
            border-color: #334155;
        }
        .qcp-image-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.25s ease;
        }
        .qcp-image-wrap:hover .qcp-image-preview {
            transform: scale(1.02);
        }

        /* Overlay zoom button */
        .qcp-image-zoom-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(4px);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.375rem;
            padding: 0.35rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.15s;
        }
        .qcp-image-zoom-btn:hover {
            background: rgba(15, 23, 42, 0.95);
        }

        /* Photo Meta and Actions Bar */
        .qcp-photo-footer {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
        }
        .qcp-photo-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            font-size: 0.725rem;
            color: #64748b;
            padding: 0 0.25rem;
        }
        .dark .qcp-photo-info {
            color: #94a3b8;
        }

        .qcp-actions-bar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        /* Buttons */
        .qcp-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            padding: 0.45rem 0.875rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            border: none;
            line-height: 1.25;
        }
        .qcp-btn-primary {
            background: #4f46e5;
            color: #ffffff;
            box-shadow: 0 1px 2px 0 rgba(79, 70, 229, 0.25);
        }
        .qcp-btn-primary:hover {
            background: #4338ca;
        }
        .qcp-btn-secondary {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .dark .qcp-btn-secondary {
            background: #1e293b;
            color: #e2e8f0;
            border-color: #475569;
        }
        .qcp-btn-secondary:hover {
            background: #e2e8f0;
        }
        .dark .qcp-btn-secondary:hover {
            background: #334155;
        }

        .qcp-btn-replace {
            background: #f8fafc;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 0.35rem 0.75rem;
            font-size: 0.725rem;
        }
        .dark .qcp-btn-replace {
            background: #1e293b;
            color: #cbd5e1;
            border-color: #475569;
        }
        .qcp-btn-replace:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        .dark .qcp-btn-replace:hover {
            background: #334155;
            color: #ffffff;
        }

        .qcp-btn-delete {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 0.35rem 0.75rem;
            font-size: 0.725rem;
        }
        .dark .qcp-btn-delete {
            background: rgba(153, 27, 27, 0.25);
            color: #fca5a5;
            border-color: #991b1b;
        }
        .qcp-btn-delete:hover {
            background: #fee2e2;
            color: #b91c1c;
        }
        .dark .qcp-btn-delete:hover {
            background: rgba(153, 27, 27, 0.45);
        }

        /* Dropzone / Upload Box */
        .qcp-dropzone {
            border: 2px dashed #cbd5e1;
            border-radius: 0.75rem;
            padding: 1.5rem 1rem;
            min-height: 220px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 0.875rem;
            background: #f8fafc;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .dark .qcp-dropzone {
            border-color: #334155;
            background: rgba(15, 23, 42, 0.5);
        }
        .qcp-dropzone.is-dragover {
            border-color: #6366f1;
            background: rgba(99, 102, 241, 0.08);
        }

        /* Icon Circle */
        .qcp-icon-bubble {
            width: 44px;
            height: 44px;
            min-width: 44px;
            min-height: 44px;
            border-radius: 9999px;
            background: #e0e7ff;
            color: #4f46e5;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(79, 70, 229, 0.1);
        }
        .dark .qcp-icon-bubble {
            background: rgba(79, 70, 229, 0.25);
            color: #a5b4fc;
        }

        .qcp-dropzone-prompt {
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
        }
        .dark .qcp-dropzone-prompt {
            color: #cbd5e1;
        }

        .qcp-dropzone-hint {
            font-size: 0.7rem;
            color: #94a3b8;
        }

        /* Instant Uploading State Container */
        .qcp-uploading-overlay {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(3px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1.5rem;
            z-index: 10;
        }

        .qcp-glass-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.625rem;
            width: 90%;
            max-width: 280px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }
        .dark .qcp-glass-card {
            background: rgba(30, 41, 59, 0.95);
        }

        .qcp-spinner {
            animation: qcp-spin 0.9s linear infinite;
        }
        @keyframes qcp-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .qcp-progress-bar {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 9999px;
            overflow: hidden;
        }
        .dark .qcp-progress-bar {
            background: #475569;
        }
        .qcp-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4f46e5, #6366f1);
            border-radius: 9999px;
            transition: width 0.25s ease;
        }

        .qcp-alert-error {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.35rem 0.75rem;
            border-radius: 0.375rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            font-size: 0.725rem;
            font-weight: 600;
            margin-top: 0.25rem;
        }
        .dark .qcp-alert-error {
            background: rgba(153, 27, 27, 0.3);
            border-color: #991b1b;
            color: #fca5a5;
        }
    </style>

    <script>
        function qcSlotUploader(slotType, wireProperty) {
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
                        if (typeof window.createImageBitmap !== 'function') {
                            return file;
                        }
                        const bmp = await createImageBitmap(file);
                        const maxDim = 1920;
                        const maxCurrent = Math.max(bmp.width, bmp.height);
                        const scale = maxCurrent > maxDim ? (maxDim / maxCurrent) : 1;
                        
                        const canvas = document.createElement('canvas');
                        canvas.width = Math.round(bmp.width * scale);
                        canvas.height = Math.round(bmp.height * scale);
                        const ctx = canvas.getContext('2d');
                        if (!ctx) {
                            return file;
                        }
                        ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height);

                        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.82));
                        if (!blob) {
                            return file;
                        }
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

                    // 1. Instant Visual Feedback: Generar vista previa inmediata en el cliente
                    try {
                        if (this.localPreview) {
                            URL.revokeObjectURL(this.localPreview);
                        }
                        this.localPreview = URL.createObjectURL(file);
                    } catch (e) {
                        this.localPreview = null;
                    }

                    this.isUploading = true;
                    this.progress = 10;

                    try {
                        // 2. Compresión cliente en segundo plano
                        let finalFile = file;
                        try {
                            finalFile = await this.compressImage(file);
                        } catch (errCompress) {
                            console.warn('Compresión falló, subiendo original:', errCompress);
                            finalFile = file;
                        }

                        this.progress = 25;

                        // 3. Subida Livewire hacia endpoint local
                        this.$wire.upload(
                            this.wireProp,
                            finalFile,
                            () => {
                                this.isUploading = false;
                                this.progress = 100;
                            },
                            (error) => {
                                this.isUploading = false;
                                this.progress = 0;
                                if (this.localPreview) {
                                    URL.revokeObjectURL(this.localPreview);
                                    this.localPreview = null;
                                }
                                console.error('Error durante subida Livewire:', error);
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
                        if (this.localPreview) {
                            URL.revokeObjectURL(this.localPreview);
                            this.localPreview = null;
                        }
                        console.error('Error procesando imagen:', err);
                        this.errorMessage = 'Error al procesar el archivo seleccionado.';
                    }
                },

                handleDrop(event) {
                    this.isDragOver = false;
                    const files = event.dataTransfer?.files;
                    if (files && files.length > 0) {
                        this.processFile(files[0]);
                    }
                },

                handleInput(event) {
                    const files = event.target?.files;
                    if (files && files.length > 0) {
                        this.processFile(files[0]);
                    }
                    event.target.value = '';
                }
            };
        }
    </script>

    <div class="qcp-wrapper">
        @if($sample)
            @php
                $isSent = $sample->isSent();
                $scalePhoto = $sample->qcPhotos->firstWhere('type', \App\Enums\QcPhotoType::Scale);
                $contextPhoto = $sample->qcPhotos->firstWhere('type', \App\Enums\QcPhotoType::Context);
                $totalPhotos = ($scalePhoto ? 1 : 0) + ($contextPhoto ? 1 : 0);
                $isComplete = $totalPhotos >= 2;
                $isStd = str_contains(strtoupper((string) $sample->control_type), 'STD');
            @endphp

            {{-- Cabecera con datos de la Muestra --}}
            <div class="qcp-header-card">
                <div class="qcp-header-left">
                    <div class="qcp-sample-line">
                        <span class="qcp-sample-title">Muestra {{ $sample->sample_number }}</span>
                        <span class="qcp-pill-tag {{ $isStd ? 'qcp-tag-std' : 'qcp-tag-blk' }}">
                            {{ $sample->standardSample?->standard_name ?? $sample->control_type }}
                        </span>
                    </div>

                    <div class="qcp-meta-row">
                        @if($sample->weight)
                            <span class="qcp-meta-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px; flex-shrink: 0;"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h18"/></svg>
                                <span>{{ number_format($sample->weight, 2) }} kg</span>
                            </span>
                        @endif

                        @if($sample->barreno)
                            <span class="qcp-meta-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px; flex-shrink: 0;"><circle cx="12" cy="12" r="10"/><path d="m12 8 4 4-4 4M8 12h8"/></svg>
                                <span>Sondaje: {{ $sample->barreno->nombre_barreno }}</span>
                            </span>
                        @endif

                        @if($sample->workOrder)
                            <span class="qcp-meta-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px; flex-shrink: 0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                <span>WO: {{ $sample->workOrder->work_order_code }}</span>
                            </span>
                        @endif
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    @if($isComplete)
                        <span class="qcp-status-pill qcp-status-complete">
                            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" style="width: 15px; height: 15px; flex-shrink: 0;"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" /></svg>
                            <span>Completa (2/2)</span>
                        </span>
                    @else
                        <span class="qcp-status-pill qcp-status-pending">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px; flex-shrink: 0;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                            <span>Pendiente ({{ $totalPhotos }}/2)</span>
                        </span>
                    @endif

                    @if($isSent)
                        <span class="qcp-status-pill qcp-status-locked" title="WO enviada al laboratorio: solo lectura">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px; flex-shrink: 0;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <span>Bloqueada (Enviada)</span>
                        </span>
                    @endif
                </div>
            </div>

            {{-- Grid con las 2 ranuras obligatorias --}}
            <div class="qcp-slots-grid">

                {{-- ================= RANURA 1: BÁSCULA ================= --}}
                <div class="qcp-slot-card {{ $scalePhoto ? 'is-complete' : '' }}" x-data="qcSlotUploader('scale', 'scaleUpload')">
                    <div class="qcp-slot-header">
                        <div class="qcp-slot-title-group">
                            <div class="qcp-slot-title">
                                <span class="qcp-step-num">1</span>
                                <span>Fotografía en Báscula</span>
                            </div>
                            <span class="qcp-slot-subtitle">Muestra QC en la báscula con peso claramente visible</span>
                        </div>

                        <div>
                            @if($scalePhoto)
                                <span class="qcp-status-pill qcp-status-complete" style="padding: 0.2rem 0.5rem; font-size: 0.7rem;">
                                    <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor" style="width: 13px; height: 13px; flex-shrink: 0;"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" /></svg>
                                    <span>Cargada</span>
                                </span>
                            @else
                                <span class="qcp-status-pill qcp-status-pending" style="padding: 0.2rem 0.5rem; font-size: 0.7rem;">
                                    <span>Pendiente</span>
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Inputs ocultos de cámara y archivo --}}
                    @if(!$isSent)
                        <input type="file" accept="image/*" capture="environment" x-ref="camInput" @change="handleInput($event)" style="display: none;">
                        <input type="file" accept="image/jpeg,image/png,image/webp" x-ref="fileInput" @change="handleInput($event)" style="display: none;">
                    @endif

                    @if($scalePhoto)
                        {{-- Foto guardada en servidor --}}
                        <div class="qcp-image-wrap">
                            <img src="{{ $scalePhoto->url() }}" alt="Báscula Muestra {{ $sample->sample_number }}" class="qcp-image-preview" loading="lazy">
                            <a href="{{ $scalePhoto->url() }}" target="_blank" rel="noopener noreferrer" class="qcp-image-zoom-btn" title="Ver tamaño completo">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px;"><path d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607ZM10.5 7.5v6m3-3h-6"/></svg>
                            </a>
                        </div>

                        <div class="qcp-photo-footer">
                            <div class="qcp-photo-info">
                                <span>{{ $scalePhoto->created_at?->format('d/m/Y H:i') }}</span>
                                @if($scalePhoto->uploader)
                                    <span>Por: {{ $scalePhoto->uploader->name }}</span>
                                @endif
                            </div>

                            @if(!$isSent)
                                <div class="qcp-actions-bar">
                                    <button type="button" @click="$refs.camInput.click()" class="qcp-btn qcp-btn-replace">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 13px; height: 13px;"><path d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                                        <span>Reemplazar</span>
                                    </button>
                                    <button type="button" wire:click="removePhoto('scale')" wire:confirm="¿Deseas eliminar la fotografía de la báscula?" class="qcp-btn qcp-btn-delete">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 13px; height: 13px;"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                        <span>Eliminar</span>
                                    </button>
                                </div>
                            @endif
                        </div>
                    @else
                        @if(!$isSent)
                            {{-- Dropzone / Área de Carga --}}
                            <div class="qcp-dropzone"
                                 @dragover.prevent="isDragOver = true"
                                 @dragleave.prevent="isDragOver = false"
                                 @drop.prevent="handleDrop($event)"
                                 :class="{ 'is-dragover': isDragOver }">

                                {{-- Vista Previa Instantánea mientras sube --}}
                                <template x-if="isUploading">
                                    <div class="qcp-uploading-overlay">
                                        <template x-if="localPreview">
                                            <img :src="localPreview" class="qcp-image-preview" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0.35;">
                                        </template>

                                        <div class="qcp-glass-card">
                                            <svg class="qcp-spinner" width="24" height="24" viewBox="0 0 24 24" fill="none" style="width: 24px; height: 24px; color: #4f46e5;">
                                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                                                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                            </svg>
                                            <span style="font-size: 0.75rem; font-weight: 700; color: #0f172a;" class="dark:text-white">
                                                Subiendo evidencia... <span x-text="progress + '%'"></span>
                                            </span>
                                            <div class="qcp-progress-bar">
                                                <div class="qcp-progress-fill" :style="'width: ' + progress + '%'"></div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="!isUploading">
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 0.75rem; width: 100%;">
                                        <div class="qcp-icon-bubble">
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 22px; height: 22px; flex-shrink: 0;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                        </div>

                                        <div style="display: flex; flex-direction: column; gap: 0.125rem;">
                                            <span class="qcp-dropzone-prompt">Cargar fotografía de báscula</span>
                                            <span class="qcp-dropzone-hint">Formatos: JPG, PNG o WEBP (máx. 15MB)</span>
                                        </div>

                                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: center; margin-top: 0.25rem;">
                                            <button type="button" @click="$refs.camInput.click()" class="qcp-btn qcp-btn-primary">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px; flex-shrink: 0;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                                <span>Tomar foto</span>
                                            </button>
                                            <button type="button" @click="$refs.fileInput.click()" class="qcp-btn qcp-btn-secondary">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px; flex-shrink: 0;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                                <span>Buscar archivo</span>
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="errorMessage">
                                    <div class="qcp-alert-error">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 13px; height: 13px; flex-shrink: 0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                        <span x-text="errorMessage"></span>
                                    </div>
                                </template>
                            </div>
                        @else
                            <div style="padding: 1.5rem; text-align: center; font-size: 0.75rem; color: #94a3b8; background: #f8fafc; border-radius: 0.625rem;" class="dark:bg-gray-800">
                                Sin fotografía capturada (Bloqueada por envío al laboratorio).
                            </div>
                        @endif
                    @endif
                </div>

                {{-- ================= RANURA 2: MUESTRAS CIRCUNDANTES ================= --}}
                <div class="qcp-slot-card {{ $contextPhoto ? 'is-complete' : '' }}" x-data="qcSlotUploader('context', 'contextUpload')">
                    <div class="qcp-slot-header">
                        <div class="qcp-slot-title-group">
                            <div class="qcp-slot-title">
                                <span class="qcp-step-num">2</span>
                                <span>Muestras circundantes</span>
                            </div>
                            <span class="qcp-slot-subtitle">Muestra QC en contexto con las bandejas y muestras adyacentes</span>
                        </div>

                        <div>
                            @if($contextPhoto)
                                <span class="qcp-status-pill qcp-status-complete" style="padding: 0.2rem 0.5rem; font-size: 0.7rem;">
                                    <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor" style="width: 13px; height: 13px; flex-shrink: 0;"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" /></svg>
                                    <span>Cargada</span>
                                </span>
                            @else
                                <span class="qcp-status-pill qcp-status-pending" style="padding: 0.2rem 0.5rem; font-size: 0.7rem;">
                                    <span>Pendiente</span>
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Inputs ocultos de cámara y archivo --}}
                    @if(!$isSent)
                        <input type="file" accept="image/*" capture="environment" x-ref="camInput" @change="handleInput($event)" style="display: none;">
                        <input type="file" accept="image/jpeg,image/png,image/webp" x-ref="fileInput" @change="handleInput($event)" style="display: none;">
                    @endif

                    @if($contextPhoto)
                        {{-- Foto guardada en servidor --}}
                        <div class="qcp-image-wrap">
                            <img src="{{ $contextPhoto->url() }}" alt="Circundantes Muestra {{ $sample->sample_number }}" class="qcp-image-preview" loading="lazy">
                            <a href="{{ $contextPhoto->url() }}" target="_blank" rel="noopener noreferrer" class="qcp-image-zoom-btn" title="Ver tamaño completo">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px;"><path d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607ZM10.5 7.5v6m3-3h-6"/></svg>
                            </a>
                        </div>

                        <div class="qcp-photo-footer">
                            <div class="qcp-photo-info">
                                <span>{{ $contextPhoto->created_at?->format('d/m/Y H:i') }}</span>
                                @if($contextPhoto->uploader)
                                    <span>Por: {{ $contextPhoto->uploader->name }}</span>
                                @endif
                            </div>

                            @if(!$isSent)
                                <div class="qcp-actions-bar">
                                    <button type="button" @click="$refs.camInput.click()" class="qcp-btn qcp-btn-replace">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 13px; height: 13px;"><path d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                                        <span>Reemplazar</span>
                                    </button>
                                    <button type="button" wire:click="removePhoto('context')" wire:confirm="¿Deseas eliminar la fotografía de muestras circundantes?" class="qcp-btn qcp-btn-delete">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 13px; height: 13px;"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                        <span>Eliminar</span>
                                    </button>
                                </div>
                            @endif
                        </div>
                    @else
                        @if(!$isSent)
                            {{-- Dropzone / Área de Carga --}}
                            <div class="qcp-dropzone"
                                 @dragover.prevent="isDragOver = true"
                                 @dragleave.prevent="isDragOver = false"
                                 @drop.prevent="handleDrop($event)"
                                 :class="{ 'is-dragover': isDragOver }">

                                {{-- Vista Previa Instantánea mientras sube --}}
                                <template x-if="isUploading">
                                    <div class="qcp-uploading-overlay">
                                        <template x-if="localPreview">
                                            <img :src="localPreview" class="qcp-image-preview" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0.35;">
                                        </template>

                                        <div class="qcp-glass-card">
                                            <svg class="qcp-spinner" width="24" height="24" viewBox="0 0 24 24" fill="none" style="width: 24px; height: 24px; color: #4f46e5;">
                                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                                                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                            </svg>
                                            <span style="font-size: 0.75rem; font-weight: 700; color: #0f172a;" class="dark:text-white">
                                                Subiendo evidencia... <span x-text="progress + '%'"></span>
                                            </span>
                                            <div class="qcp-progress-bar">
                                                <div class="qcp-progress-fill" :style="'width: ' + progress + '%'"></div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="!isUploading">
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 0.75rem; width: 100%;">
                                        <div class="qcp-icon-bubble">
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 22px; height: 22px; flex-shrink: 0;"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                                        </div>

                                        <div style="display: flex; flex-direction: column; gap: 0.125rem;">
                                            <span class="qcp-dropzone-prompt">Cargar fotografía de contexto</span>
                                            <span class="qcp-dropzone-hint">Formatos: JPG, PNG o WEBP (máx. 15MB)</span>
                                        </div>

                                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: center; margin-top: 0.25rem;">
                                            <button type="button" @click="$refs.camInput.click()" class="qcp-btn qcp-btn-primary">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px; flex-shrink: 0;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                                <span>Tomar foto</span>
                                            </button>
                                            <button type="button" @click="$refs.fileInput.click()" class="qcp-btn qcp-btn-secondary">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px; flex-shrink: 0;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                                <span>Buscar archivo</span>
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="errorMessage">
                                    <div class="qcp-alert-error">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 13px; height: 13px; flex-shrink: 0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                        <span x-text="errorMessage"></span>
                                    </div>
                                </template>
                            </div>
                        @else
                            <div style="padding: 1.5rem; text-align: center; font-size: 0.75rem; color: #94a3b8; background: #f8fafc; border-radius: 0.625rem;" class="dark:bg-gray-800">
                                Sin fotografía capturada (Bloqueada por envío al laboratorio).
                            </div>
                        @endif
                    @endif
                </div>

            </div>
        @else
            <div style="padding: 2rem; text-align: center; font-size: 0.875rem; color: #64748b;">
                No se ha seleccionado ninguna muestra para documentar.
            </div>
        @endif
    </div>
</div>
