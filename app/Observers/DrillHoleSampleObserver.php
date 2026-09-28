<?php

namespace App\Observers;

use App\Models\DrillHoleSample;
use App\Models\WorkOrder;

class DrillHoleSampleObserver
{
    public function saving(DrillHoleSample $drillHoleSample): void
    {
        if ($drillHoleSample->to_depth !== null && $drillHoleSample->from_depth !== null) {
            $drillHoleSample->length = $drillHoleSample->to_depth - $drillHoleSample->from_depth;
        }

        // Prevenir que se asigne una Work Order que ya fue enviada al laboratorio
        if ($drillHoleSample->isDirty('work_order_id') && $drillHoleSample->work_order_id) {
            $sentWo = WorkOrder::select('work_order_code')
                ->where('id', $drillHoleSample->work_order_id)
                ->where('sent_to_lab', true)
                ->first();
            if ($sentWo) {
                throw new \InvalidArgumentException(
                    "No se pueden asignar más muestras a la Work Order {$sentWo->work_order_code} porque ya fue enviada al laboratorio."
                );
            }
        }
    }

    public function updating(DrillHoleSample $drillHoleSample): void
    {
        // Short-circuit: si la muestra misma está archivada, es enviada
        $wasSent = (bool) $drillHoleSample->getOriginal('is_archived');

        // Si no está archivada, verificar si la WO fue enviada (1 query eficiente, solo si necesario)
        if (!$wasSent && $drillHoleSample->work_order_id) {
            $wasSent = WorkOrder::where('id', $drillHoleSample->work_order_id)
                ->where('sent_to_lab', true)
                ->exists();
        }

        if ($wasSent) {
            $protectedFields = [
                'from_depth',
                'to_depth',
                'length',
                'sample_length',
                'weight',
                'sample_number',
                'sample_type',
                'control_type',
                'standard_sample_id',
                'duplicate_sample_id',
                'core_size',
                'barreno_id',
                'proyecto_id',
                'work_order_id',
            ];

            foreach ($protectedFields as $field) {
                if ($drillHoleSample->isDirty($field)) {
                    throw new \InvalidArgumentException(
                        "No se puede modificar '{$field}' en la muestra {$drillHoleSample->sample_number}: la Work Order ya fue enviada al laboratorio."
                    );
                }
            }
        }
    }

    public function deleting(DrillHoleSample $drillHoleSample): void
    {
        if ($drillHoleSample->isSent()) {
            throw new \InvalidArgumentException(
                "No se puede eliminar la muestra {$drillHoleSample->sample_number} porque pertenece a una Work Order ya enviada al laboratorio."
            );
        }
    }

    public function updated(DrillHoleSample $drillHoleSample): void
    {
        if ($drillHoleSample->wasChanged('to_depth')) {
            $difference = $drillHoleSample->to_depth - $drillHoleSample->getOriginal('to_depth');

            if ($difference != 0) {
                // Find subsequent samples based on original to_depth to cascade the change
                $subsequentSamples = DrillHoleSample::where('barreno_id', $drillHoleSample->barreno_id)
                    ->where('from_depth', '>=', $drillHoleSample->getOriginal('to_depth'))
                    ->where('id', '!=', $drillHoleSample->id)
                    ->orderBy('from_depth')
                    ->get();

                foreach ($subsequentSamples as $subSample) {
                    // Si la muestra subsecuente ya fue enviada, no alterarla
                    if ($subSample->isSent()) {
                        continue;
                    }

                    // Update silently to prevent infinite loops of events
                    DrillHoleSample::withoutEvents(function () use ($subSample, $difference) {
                        $subSample->from_depth += $difference;
                        $subSample->to_depth += $difference;
                        // length remains the same
                        $subSample->save();
                    });
                }
            }
        }
    }
}
