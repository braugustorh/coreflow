@assets
<style>
    .qcp-container {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        font-family: inherit;
    }
    .qcp-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.875rem 1rem;
        border-radius: 0.75rem;
        background: rgba(243, 244, 246, 0.7);
        border: 1px solid rgba(229, 231, 235, 1);
    }
    .dark .qcp-header {
        background: rgba(31, 41, 55, 0.7);
        border-color: rgba(55, 65, 81, 1);
    }
    .qcp-grid {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 1rem;
    }
    @media (min-width: 640px) {
        .qcp-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    .qcp-card {
        border-radius: 0.75rem;
        border: 1px solid rgba(229, 231, 235, 1);
        background: #ffffff;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .dark .qcp-card {
        background: rgba(31, 41, 55, 0.9);
        border-color: rgba(55, 65, 81, 1);
    }
    .qcp-card.complete {
        border-color: rgba(34, 197, 94, 0.4);
        background: rgba(240, 253, 244, 0.3);
    }
    .dark .qcp-card.complete {
        border-color: rgba(34, 197, 94, 0.3);
        background: rgba(6, 78, 59, 0.1);
    }
    .qcp-dropzone {
        border: 2px dashed rgba(209, 213, 219, 1);
        border-radius: 0.625rem;
        padding: 1.25rem 0.75rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        gap: 0.625rem;
        cursor: pointer;
        background: rgba(249, 250, 251, 0.6);
        transition: all 0.2s;
        min-height: 180px;
    }
    .dark .qcp-dropzone {
        border-color: rgba(75, 85, 99, 1);
        background: rgba(17, 24, 39, 0.4);
    }
    .qcp-dropzone.dragover {
        border-color: #6366f1;
        background: rgba(99, 102, 241, 0.08);
    }
    .qcp-preview-box {
        position: relative;
        border-radius: 0.5rem;
        overflow: hidden;
        aspect-ratio: 4 / 3;
        background: #000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .qcp-preview-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .qcp-container svg {
        display: inline-block;
        vertical-align: middle;
        flex-shrink: 0;
        max-width: 100%;
    }
    .qcp-container svg.w-4,
    .qcp-container svg.h-4 {
        width: 1rem !important;
        height: 1rem !important;
        min-width: 1rem !important;
    }
    .qcp-container svg.w-5,
    .qcp-container svg.h-5 {
        width: 1.25rem !important;
        height: 1.25rem !important;
        min-width: 1.25rem !important;
    }
    .qcp-container svg.w-8,
    .qcp-container svg.h-8 {
        width: 2rem !important;
        height: 2rem !important;
        min-width: 2rem !important;
    }
</style>
<script>
    function qcSlotUploader(slotType, wireProperty) {
        return {
            isDragOver: false,
            isUploading: false,
            progress: 0,
            errorMessage: null,
            slotType: slotType,
            wireProp: wireProperty,

            async compressImage(file) {
                try {
                    const bmp = await createImageBitmap(file);
                    const maxDim = 1920;
                    const maxCurrent = Math.max(bmp.width, bmp.height);
                    const scale = maxCurrent > maxDim ? (maxDim / maxCurrent) : 1;
                    
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.round(bmp.width * scale);
                    canvas.height = Math.round(bmp.height * scale);
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height);

                    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.82));
                    const newName = file.name.replace(/\.[^/.]+$/, "") + ".jpg";
                    return new File([blob], newName, { type: 'image/jpeg' });
                } catch (e) {
                    console.warn('Compresión client-side no disponible, subiendo original:', e);
                    return file;
                }
            },

            async processFile(file) {
                if (!file || !file.type.startsWith('image/')) {
                    this.errorMessage = 'Por favor selecciona un archivo de imagen válido.';
                    return;
                }
                this.errorMessage = null;
                this.isUploading = true;
                this.progress = 5;

                try {
                    const finalFile = await this.compressImage(file);
                    this.progress = 20;

                    this.$wire.upload(
                        this.wireProp,
                        finalFile,
                        () => {
                            this.isUploading = false;
                            this.progress = 100;
                        },
                        (error) => {
                            this.isUploading = false;
                            this.errorMessage = 'Error en la subida. Intente de nuevo.';
                        },
                        (event) => {
                            this.progress = 20 + Math.round(event.detail.progress * 0.75);
                        }
                    );
                } catch (err) {
                    this.isUploading = false;
                    this.errorMessage = 'Error al procesar la imagen.';
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
@endassets

<div class="qcp-container">
    @if($sample)
        @php
            $isSent = $sample->isSent();
            $scalePhoto = $sample->qcPhotos->firstWhere('type', \App\Enums\QcPhotoType::Scale);
            $contextPhoto = $sample->qcPhotos->firstWhere('type', \App\Enums\QcPhotoType::Context);
            $totalPhotos = ($scalePhoto ? 1 : 0) + ($contextPhoto ? 1 : 0);
            $isComplete = $totalPhotos >= 2;
        @endphp

        {{-- Encabezado con detalles de la muestra --}}
        <div class="qcp-header">
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <span style="font-weight: 700; font-size: 1.1rem; color: #111827;" class="dark:text-white">
                    Muestra: {{ $sample->sample_number }}
                </span>
                
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                    {{ $sample->control_type ?? 'CONTROL' }}
                    @if($sample->standardSample)
                        ({{ $sample->standardSample->standard_name }})
                    @endif
                </span>

                @if($sample->weight)
                    <span class="inline-flex items-center text-xs font-medium text-gray-600 dark:text-gray-300">
                        ⚖️ {{ number_format($sample->weight, 2) }} kg
                    </span>
                @endif

                @if($sample->barreno)
                    <span class="inline-flex items-center text-xs text-gray-500 dark:text-gray-400">
                        Sondaje: {{ $sample->barreno->nombre_barreno }}
                    </span>
                @endif
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem;">
                @if($isComplete)
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-300">
                        <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                        Completa (2/2)
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300">
                        📷 Pendiente ({{ $totalPhotos }}/2)
                    </span>
                @endif

                @if($isSent)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300" title="WO enviada al laboratorio: solo lectura">
                        🔒 Bloqueada (Enviada)
                    </span>
                @endif
            </div>
        </div>

        {{-- Grid de 2 fotografías --}}
        <div class="qcp-grid">
            {{-- Ranura 1: Báscula --}}
            <div class="qcp-card {{ $scalePhoto ? 'complete' : '' }}">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 flex items-center gap-1.5">
                            <span>1. Báscula</span>
                            @if($scalePhoto)
                                <span class="text-green-600 font-bold">✓</span>
                            @endif
                        </h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Muestra QC en la báscula con peso visible</p>
                    </div>
                </div>

                @if($scalePhoto)
                    <div class="qcp-preview-box">
                        <a href="{{ $scalePhoto->url() }}" target="_blank" rel="noopener noreferrer" title="Ver tamaño completo">
                            <img src="{{ $scalePhoto->url() }}" alt="Báscula" class="qcp-preview-img" loading="lazy">
                        </a>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center justify-between">
                        <span>{{ $scalePhoto->created_at?->format('d/m/Y H:i') }}</span>
                        @if($scalePhoto->uploader)
                            <span>Por: {{ $scalePhoto->uploader->name }}</span>
                        @endif
                    </div>

                    @if(!$isSent)
                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.25rem;" x-data="qcSlotUploader('scale', 'scaleUpload')">
                            <input type="file" accept="image/*" capture="environment" x-ref="camInput" @change="handleInput($event)" style="display: none;">
                            <input type="file" accept="image/jpeg,image/png,image/webp" x-ref="fileInput" @change="handleInput($event)" style="display: none;">

                            <button type="button" @click="$refs.camInput.click()" class="text-xs px-2.5 py-1 font-medium bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded text-gray-700 dark:text-gray-200">
                                📷 Reemplazar
                            </button>
                            <button type="button" wire:click="removePhoto('scale')" wire:confirm="¿Deseas eliminar la fotografía de la báscula?" class="text-xs px-2.5 py-1 font-medium bg-red-50 hover:bg-red-100 text-red-600 dark:bg-red-950/40 dark:text-red-300 rounded">
                                🗑️ Eliminar
                            </button>
                        </div>
                    @endif
                @else
                    @if(!$isSent)
                        <div x-data="qcSlotUploader('scale', 'scaleUpload')"
                             @dragover.prevent="isDragOver = true"
                             @dragleave.prevent="isDragOver = false"
                             @drop.prevent="handleDrop($event)"
                             :class="{ 'dragover': isDragOver }"
                             class="qcp-dropzone">
                            
                            <input type="file" accept="image/*" capture="environment" x-ref="cameraInput" @change="handleInput($event)" style="display: none;">
                            <input type="file" accept="image/jpeg,image/png,image/webp" x-ref="fileInput" @change="handleInput($event)" style="display: none;">

                            <template x-if="!isUploading">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem; width: 100%;">
                                    <svg class="w-8 h-8 text-indigo-500 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: center;">
                                        <button type="button" @click="$refs.cameraInput.click()" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm flex items-center gap-1">
                                            <span>📷 Tomar foto</span>
                                        </button>
                                        <button type="button" @click="$refs.fileInput.click()" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-100 flex items-center gap-1">
                                            <span>🖼️ Buscar archivo</span>
                                        </button>
                                    </div>

                                    <span class="text-xs text-gray-400 hidden sm:inline">o arrastra y suelta aquí</span>
                                </div>
                            </template>

                            <template x-if="isUploading">
                                <div style="width: 100%; padding: 1rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                                    <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Optimizando y subiendo... <span x-text="progress + '%'"></span></span>
                                    <div style="width: 100%; background: #e5e7eb; border-radius: 9999px; height: 6px; overflow: hidden;">
                                        <div style="background: #4f46e5; height: 100%; transition: width 0.2s;" :style="'width: ' + progress + '%'"></div>
                                    </div>
                                </div>
                            </template>

                            <template x-if="errorMessage">
                                <span class="text-xs text-red-500 font-medium" x-text="errorMessage"></span>
                            </template>
                        </div>
                    @else
                        <div class="p-6 text-center text-xs text-gray-400 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                            Sin fotografía capturada (Bloqueada por envío).
                        </div>
                    @endif
                @endif
            </div>

            {{-- Ranura 2: Muestras circundantes --}}
            <div class="qcp-card {{ $contextPhoto ? 'complete' : '' }}">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 flex items-center gap-1.5">
                            <span>2. Muestras circundantes</span>
                            @if($contextPhoto)
                                <span class="text-green-600 font-bold">✓</span>
                            @endif
                        </h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Muestra QC en contexto con las muestras adyacentes</p>
                    </div>
                </div>

                @if($contextPhoto)
                    <div class="qcp-preview-box">
                        <a href="{{ $contextPhoto->url() }}" target="_blank" rel="noopener noreferrer" title="Ver tamaño completo">
                            <img src="{{ $contextPhoto->url() }}" alt="Circundantes" class="qcp-preview-img" loading="lazy">
                        </a>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center justify-between">
                        <span>{{ $contextPhoto->created_at?->format('d/m/Y H:i') }}</span>
                        @if($contextPhoto->uploader)
                            <span>Por: {{ $contextPhoto->uploader->name }}</span>
                        @endif
                    </div>

                    @if(!$isSent)
                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.25rem;" x-data="qcSlotUploader('context', 'contextUpload')">
                            <input type="file" accept="image/*" capture="environment" x-ref="camInput" @change="handleInput($event)" style="display: none;">
                            <input type="file" accept="image/jpeg,image/png,image/webp" x-ref="fileInput" @change="handleInput($event)" style="display: none;">

                            <button type="button" @click="$refs.camInput.click()" class="text-xs px-2.5 py-1 font-medium bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded text-gray-700 dark:text-gray-200">
                                📷 Reemplazar
                            </button>
                            <button type="button" wire:click="removePhoto('context')" wire:confirm="¿Deseas eliminar la fotografía de muestras circundantes?" class="text-xs px-2.5 py-1 font-medium bg-red-50 hover:bg-red-100 text-red-600 dark:bg-red-950/40 dark:text-red-300 rounded">
                                🗑️ Eliminar
                            </button>
                        </div>
                    @endif
                @else
                    @if(!$isSent)
                        <div x-data="qcSlotUploader('context', 'contextUpload')"
                             @dragover.prevent="isDragOver = true"
                             @dragleave.prevent="isDragOver = false"
                             @drop.prevent="handleDrop($event)"
                             :class="{ 'dragover': isDragOver }"
                             class="qcp-dropzone">
                            
                            <input type="file" accept="image/*" capture="environment" x-ref="cameraInput" @change="handleInput($event)" style="display: none;">
                            <input type="file" accept="image/jpeg,image/png,image/webp" x-ref="fileInput" @change="handleInput($event)" style="display: none;">

                            <template x-if="!isUploading">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem; width: 100%;">
                                    <svg class="w-8 h-8 text-indigo-500 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: center;">
                                        <button type="button" @click="$refs.cameraInput.click()" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm flex items-center gap-1">
                                            <span>📷 Tomar foto</span>
                                        </button>
                                        <button type="button" @click="$refs.fileInput.click()" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-100 flex items-center gap-1">
                                            <span>🖼️ Buscar archivo</span>
                                        </button>
                                    </div>

                                    <span class="text-xs text-gray-400 hidden sm:inline">o arrastra y suelta aquí</span>
                                </div>
                            </template>

                            <template x-if="isUploading">
                                <div style="width: 100%; padding: 1rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                                    <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Optimizando y subiendo... <span x-text="progress + '%'"></span></span>
                                    <div style="width: 100%; background: #e5e7eb; border-radius: 9999px; height: 6px; overflow: hidden;">
                                        <div style="background: #4f46e5; height: 100%; transition: width 0.2s;" :style="'width: ' + progress + '%'"></div>
                                    </div>
                                </div>
                            </template>

                            <template x-if="errorMessage">
                                <span class="text-xs text-red-500 font-medium" x-text="errorMessage"></span>
                            </template>
                        </div>
                    @else
                        <div class="p-6 text-center text-xs text-gray-400 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                            Sin fotografía capturada (Bloqueada por envío).
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @else
        <div class="p-4 text-center text-sm text-gray-500">
            No se seleccionó ninguna muestra para cargar fotografías.
        </div>
    @endif
</div>
