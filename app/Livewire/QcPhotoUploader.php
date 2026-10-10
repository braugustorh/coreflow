<?php

namespace App\Livewire;

use App\Enums\QcPhotoType;
use App\Models\DrillHoleSample;
use App\Services\QcPhotoService;
use Filament\Notifications\Notification;
use Livewire\Component;
use Livewire\WithFileUploads;

class QcPhotoUploader extends Component
{
    use WithFileUploads;

    public ?int $sampleId = null;
    public $scaleUpload = null;
    public $contextUpload = null;

    public function mount(DrillHoleSample|int|null $sample = null, ?int $sampleId = null): void
    {
        if ($sample instanceof DrillHoleSample) {
            $this->sampleId = $sample->id;
        } elseif (is_numeric($sample)) {
            $this->sampleId = (int) $sample;
        } elseif ($sampleId) {
            $this->sampleId = $sampleId;
        }
    }

    /**
     * Obtiene el modelo fresco de la base de datos con relaciones cargadas
     * sin depender de cachés de propiedades de Livewire.
     */
    public function getFreshSample(): ?DrillHoleSample
    {
        if (!$this->sampleId) {
            return null;
        }

        return DrillHoleSample::with(['qcPhotos.uploader', 'standardSample', 'workOrder', 'barreno'])
            ->find($this->sampleId);
    }

    public function getSampleProperty(): ?DrillHoleSample
    {
        return $this->getFreshSample();
    }

    public function updatedScaleUpload(): void
    {
        $this->handleUpload(QcPhotoType::Scale, $this->scaleUpload, 'scaleUpload');
        $this->reset('scaleUpload');
    }

    public function updatedContextUpload(): void
    {
        $this->handleUpload(QcPhotoType::Context, $this->contextUpload, 'contextUpload');
        $this->reset('contextUpload');
    }

    protected function handleUpload(QcPhotoType $type, $file, string $propertyName): void
    {
        if (!$file) {
            return;
        }

        $this->validate([
            $propertyName => 'image|mimes:jpg,jpeg,png,webp|max:15360',
        ], [
            "{$propertyName}.image" => 'El archivo debe ser una imagen válida.',
            "{$propertyName}.mimes" => 'Formatos permitidos: JPG, PNG, WEBP.',
            "{$propertyName}.max"   => 'El tamaño máximo es de 15MB.',
        ]);

        $sample = $this->getFreshSample();
        if (!$sample) {
            Notification::make()->title('Muestra no encontrada.')->danger()->send();
            return;
        }

        try {
            $service = app(QcPhotoService::class);
            $service->store($sample, $type, $file, auth()->user());

            Notification::make()
                ->title("Fotografía de {$type->label()} guardada")
                ->body("Muestra {$sample->sample_number}")
                ->success()
                ->send();

            $this->dispatch('qc-photos-updated', sampleId: $this->sampleId);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al guardar la fotografía')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function removePhoto(string $typeValue): void
    {
        $sample = $this->getFreshSample();
        if (!$sample) {
            return;
        }

        try {
            $type = QcPhotoType::from($typeValue);
            $photo = $sample->qcPhotos()->where('type', $type->value)->first();

            if ($photo) {
                $service = app(QcPhotoService::class);
                $service->delete($photo, auth()->user());

                Notification::make()
                    ->title("Fotografía de {$type->label()} eliminada")
                    ->info()
                    ->send();

                $this->dispatch('qc-photos-updated', sampleId: $this->sampleId);
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al eliminar fotografía')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        return view('livewire.qc-photo-uploader', [
            'sample' => $this->getFreshSample(),
        ]);
    }
}
