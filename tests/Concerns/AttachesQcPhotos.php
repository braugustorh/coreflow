<?php

namespace Tests\Concerns;

use App\Enums\QcPhotoType;
use App\Models\DrillHoleSample;
use App\Models\User;
use App\Services\QcPhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait AttachesQcPhotos
{
    /**
     * Adjunta las 2 fotografías obligatorias de control (Báscula y Contexto)
     * a una muestra usando el servicio QcPhotoService sobre el disco coreflow faked.
     */
    public function attachQcPhotos(DrillHoleSample $sample, ?User $user = null): void
    {
        Storage::fake('coreflow');

        $user = $user ?? auth()->user() ?? User::first() ?? User::factory()->create();
        $service = app(QcPhotoService::class);

        foreach ([QcPhotoType::Scale, QcPhotoType::Context] as $type) {
            $fakeFile = UploadedFile::fake()->image("{$type->value}.jpg", 800, 600);
            $service->store($sample, $type, $fakeFile, $user);
        }
    }
}
