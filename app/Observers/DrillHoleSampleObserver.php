<?php

namespace App\Observers;

use App\Models\DrillHoleSample;

class DrillHoleSampleObserver
{
    public function saving(DrillHoleSample $drillHoleSample): void
    {
        if ($drillHoleSample->to_depth !== null && $drillHoleSample->from_depth !== null) {
            $drillHoleSample->length = $drillHoleSample->to_depth - $drillHoleSample->from_depth;
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
