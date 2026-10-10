<?php

namespace App\Filament\Pages;

use App\Models\DrillHole;
use App\Models\DrillHoleSample;
use App\Models\WorkOrder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class QcPhotoEvidence extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-camera';
    protected static ?string $navigationLabel = 'Evidencia Fotos QC';
    protected static ?string $title = 'Evidencia Fotográfica de Muestras de Control (QC)';
    protected static string|\UnitEnum|null $navigationGroup = 'Muestreo';
    protected static ?int $navigationSort = 3;
    protected string $view = 'filament.pages.qc-photo-evidence';

    #[Url]
    public ?int $barrenoId = null;

    #[Url]
    public ?int $workOrderId = null;

    #[Url]
    public string $filter = 'pending'; // 'pending', 'all', 'completed'

    #[Url]
    public ?int $selectedSampleId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->hasRole(['super_admin', 'Admin CoreFlow', 'Supervisor CoreS', 'Geologo'])
            || $user->can('View:AgileCapture')
            || $user->can('View:SamplingMonitor');
    }

    public function mount(): void
    {
        $this->ensureSelectedSample();
    }

    public function updatedBarrenoId(): void
    {
        $this->selectedSampleId = null;
        $this->ensureSelectedSample();
    }

    public function updatedWorkOrderId(): void
    {
        $this->selectedSampleId = null;
        $this->ensureSelectedSample();
    }

    public function updatedFilter(): void
    {
        $this->selectedSampleId = null;
        $this->ensureSelectedSample();
    }

    public function selectSample(int $sampleId): void
    {
        $this->selectedSampleId = $sampleId;
    }

    public function getSamplesProperty()
    {
        $query = DrillHoleSample::query()
            ->requiringQcPhotos()
            ->with(['barreno', 'workOrder', 'standardSample', 'qcPhotos'])
            ->withCount('qcPhotos');

        if ($this->barrenoId) {
            $query->where('barreno_id', $this->barrenoId);
        }

        if ($this->workOrderId) {
            $query->where('work_order_id', $this->workOrderId);
        }

        if ($this->filter === 'pending') {
            $query->missingQcPhotos();
        } elseif ($this->filter === 'completed') {
            $query->has('qcPhotos', '>=', DrillHoleSample::QC_PHOTOS_REQUIRED);
        }

        return $query->orderBy('sample_number', 'asc')->get();
    }

    public function getSelectedSampleProperty(): ?DrillHoleSample
    {
        if (!$this->selectedSampleId) {
            return null;
        }

        return DrillHoleSample::with(['barreno', 'workOrder', 'standardSample', 'qcPhotos'])
            ->find($this->selectedSampleId);
    }

    public function getBarrenosOptionsProperty()
    {
        $query = DrillHole::query()
            ->whereHas('drillHoleSamples', fn($q) => $q->requiringQcPhotos())
            ->orderBy('nombre_barreno', 'asc');

        $user = auth()->user();
        if ($user && $user->sede_id && !$user->hasAnyRole(['super_admin', 'Admin CoreFlow'])) {
            $query->where('sede_id', $user->sede_id);
        }

        return $query->get(['id', 'nombre_barreno']);
    }

    public function getWorkOrdersOptionsProperty()
    {
        $query = WorkOrder::query()
            ->whereHas('drillHoleSamples', fn($q) => $q->requiringQcPhotos())
            ->orderBy('work_order_code', 'desc');

        $user = auth()->user();
        if ($user && $user->sede_id && !$user->hasAnyRole(['super_admin', 'Admin CoreFlow'])) {
            $query->where('sede_id', $user->sede_id);
        }

        return $query->get(['id', 'work_order_code']);
    }

    public function getStatsProperty(): array
    {
        $baseQuery = DrillHoleSample::query()->requiringQcPhotos();

        if ($this->barrenoId) {
            $baseQuery->where('barreno_id', $this->barrenoId);
        }

        if ($this->workOrderId) {
            $baseQuery->where('work_order_id', $this->workOrderId);
        }

        $total = (clone $baseQuery)->count();
        $pending = (clone $baseQuery)->missingQcPhotos()->count();
        $completed = $total - $pending;
        $percentage = $total > 0 ? (int) round(($completed / $total) * 100) : 100;

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'percentage' => $percentage,
        ];
    }

    public function goToNextPending(): void
    {
        $currentId = $this->selectedSampleId;
        $samples = $this->samples;

        if ($samples->isEmpty()) {
            $this->selectedSampleId = null;
            return;
        }

        $currentIndex = $samples->search(fn($s) => $s->id === $currentId);
        if ($currentIndex !== false && $currentIndex < $samples->count() - 1) {
            $this->selectedSampleId = $samples[$currentIndex + 1]->id;
        } else {
            $this->selectedSampleId = $samples->first()->id;
        }
    }

    public function goToPrevious(): void
    {
        $currentId = $this->selectedSampleId;
        $samples = $this->samples;

        if ($samples->isEmpty()) {
            $this->selectedSampleId = null;
            return;
        }

        $currentIndex = $samples->search(fn($s) => $s->id === $currentId);
        if ($currentIndex !== false && $currentIndex > 0) {
            $this->selectedSampleId = $samples[$currentIndex - 1]->id;
        } else {
            $this->selectedSampleId = $samples->last()->id;
        }
    }

    #[On('qc-photos-updated')]
    public function onQcPhotosUpdated(): void
    {
        $current = $this->selectedSampleId
            ? DrillHoleSample::with('qcPhotos')->find($this->selectedSampleId)
            : null;

        if ($current && $current->hasCompleteQcPhotos()) {
            Notification::make()
                ->title('Muestra documentada con éxito')
                ->body("La muestra {$current->sample_number} cuenta con sus 2 fotografías QC registradas.")
                ->success()
                ->send();

            if ($this->filter === 'pending') {
                $this->goToNextPending();
            }
        }
    }

    protected function ensureSelectedSample(): void
    {
        $samples = $this->samples;
        if ($samples->isNotEmpty()) {
            if (!$this->selectedSampleId || !$samples->contains('id', $this->selectedSampleId)) {
                $this->selectedSampleId = $samples->first()->id;
            }
        } else {
            $this->selectedSampleId = null;
        }
    }
}
