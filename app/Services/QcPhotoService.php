<?php

namespace App\Services;

use App\Enums\QcPhotoType;
use App\Models\DrillHoleSample;
use App\Models\QcSamplePhoto;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QcPhotoService
{
    /**
     * Disco de almacenamiento configurado para fotos QC.
     */
    public const DISK = 'coreflow';

    /**
     * Verifica si el usuario tiene permiso para gestionar fotos de la muestra.
     */
    public function canManage(User $user, DrillHoleSample $sample): bool
    {
        if ($user->hasRole(['super_admin', 'Admin CoreFlow']) || $user->id === 1) {
            return true;
        }

        $sampleSedeId = $sample->barreno?->sede_id ?? $sample->proyecto?->sede_id;
        if ($sampleSedeId && $user->sede_id) {
            return (int) $user->sede_id === (int) $sampleSedeId;
        }

        return true;
    }

    /**
     * Genera la ruta y nombre del archivo según la convención estricta:
     * Control/{Nombre del barreno}/{dispatch}-{muestra}-{std/blk}-{nameSTD/Blank}-{tipo}.{ext}
     */
    public function generatePath(DrillHoleSample $sample, QcPhotoType $type, string|UploadedFile $extensionOrFile): string
    {
        $extension = $extensionOrFile instanceof UploadedFile
            ? strtolower($extensionOrFile->getClientOriginalExtension() ?: 'jpg')
            : strtolower(ltrim($extensionOrFile, '.'));

        $sample->loadMissing(['barreno', 'workOrder', 'standardSample']);

        // 1. Nombre del barreno
        $barrenoRaw = $sample->barreno?->nombre_barreno ?? 'SIN_BARRENO';
        $barrenoClean = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $barrenoRaw);

        // 2. Dispatch (Código de Work Order)
        $dispatchRaw = $sample->workOrder?->work_order_code ?? 'SIN_WO';
        $dispatchClean = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $dispatchRaw);

        // 3. Muestra
        $muestraClean = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $sample->sample_number ?? 'MUESTRA');

        // 4. std o blk
        $isBlank = str_contains(strtoupper((string) $sample->control_type), 'BLANK')
            || str_contains(strtoupper((string) $sample->sample_number), 'BLK');
        $stdBlk = $isBlank ? 'blk' : 'std';

        // 5. Nombre del Estándar o Blanco
        if ($isBlank) {
            $nameRaw = !empty($sample->control_type) ? $sample->control_type : 'BLANK-ML';
        } else {
            $nameRaw = $sample->standardSample?->standard_name ?? $sample->control_type ?? 'STANDARD';
        }
        $nameClean = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $nameRaw);

        // 6. Tipo (scale | context)
        $tipo = $type->fileSuffix();

        $filename = "{$dispatchClean}-{$muestraClean}-{$stdBlk}-{$nameClean}-{$tipo}.{$extension}";

        return "Control/{$barrenoClean}/{$filename}";
    }

    /**
     * Almacena y registra una fotografía QC en el disco coreflow.
     */
    public function store(
        DrillHoleSample $sample,
        QcPhotoType $type,
        UploadedFile $file,
        User $user
    ): QcSamplePhoto {
        if (!$sample->requiresQcPhotos()) {
            throw new \InvalidArgumentException("La muestra {$sample->sample_number} no es una muestra de control que requiera fotografías.");
        }

        if ($sample->isSent()) {
            throw new \InvalidArgumentException("La muestra {$sample->sample_number} pertenece a una Work Order ya enviada al laboratorio; las fotografías están bloqueadas.");
        }

        if (!$this->canManage($user, $sample)) {
            throw new \InvalidArgumentException("No tienes permisos para subir fotografías a muestras de este distrito minero.");
        }

        $path = $this->generatePath($sample, $type, $file);
        $directory = dirname($path);
        $filename = basename($path);

        $width = null;
        $height = null;
        $realPath = $file->getRealPath();
        if ($realPath && file_exists($realPath)) {
            $imageInfo = @getimagesize($realPath);
            if ($imageInfo) {
                $width = (int) $imageInfo[0];
                $height = (int) $imageInfo[1];
            }
        }

        // Subir archivo a disco coreflow
        Storage::disk(self::DISK)->putFileAs($directory, $file, $filename);

        return DB::transaction(function () use ($sample, $type, $path, $file, $width, $height, $user) {
            $existing = QcSamplePhoto::where('drill_hole_sample_id', $sample->id)
                ->where('type', $type->value)
                ->first();

            $oldPath = $existing?->path;

            $photo = QcSamplePhoto::updateOrCreate(
                [
                    'drill_hole_sample_id' => $sample->id,
                    'type'                 => $type->value,
                ],
                [
                    'disk'          => self::DISK,
                    'path'          => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getClientMimeType() ?: 'image/jpeg',
                    'size_bytes'    => $file->getSize(),
                    'width'         => $width,
                    'height'        => $height,
                    'uploaded_by'   => $user->id,
                ]
            );

            // Si reemplazó una foto que tenía una ruta diferente, limpiar la vieja
            if ($oldPath && $oldPath !== $path) {
                Storage::disk(self::DISK)->delete($oldPath);
            }

            return $photo;
        });
    }

    /**
     * Elimina una fotografía QC física y lógicamente.
     */
    public function delete(QcSamplePhoto $photo, User $user): bool
    {
        $sample = $photo->drillHoleSample;

        if ($sample && $sample->isSent()) {
            throw new \InvalidArgumentException("No se puede eliminar la fotografía: la muestra pertenece a una Work Order ya enviada al laboratorio.");
        }

        if ($sample && !$this->canManage($user, $sample)) {
            throw new \InvalidArgumentException("No tienes permisos para eliminar fotografías de este distrito minero.");
        }

        return (bool) $photo->delete();
    }

    /**
     * Resumen de requerimientos y cumplimiento de fotos QC para una Work Order.
     */
    public function summaryForWorkOrder(WorkOrder $workOrder): array
    {
        $qcSamples = $workOrder->drillHoleSamples()
            ->requiringQcPhotos()
            ->withCount('qcPhotos')
            ->get();

        $required = $qcSamples->count();
        $completed = $qcSamples->filter(fn ($s) => $s->qc_photos_count >= DrillHoleSample::QC_PHOTOS_REQUIRED)->count();
        $missing = $qcSamples->filter(fn ($s) => $s->qc_photos_count < DrillHoleSample::QC_PHOTOS_REQUIRED)->pluck('sample_number');

        return [
            'required'  => $required,
            'completed' => $completed,
            'missing'   => $missing,
        ];
    }

    /**
     * Purgar fotografías para un conjunto de IDs de muestra (utilizado al descartar borradores).
     */
    public function purgeForSampleIds(array $sampleIds): void
    {
        if (empty($sampleIds)) {
            return;
        }

        QcSamplePhoto::whereIn('drill_hole_sample_id', $sampleIds)
            ->get()
            ->each
            ->delete();
    }
}
