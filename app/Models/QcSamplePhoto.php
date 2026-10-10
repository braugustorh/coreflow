<?php

namespace App\Models;

use App\Enums\QcPhotoType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class QcSamplePhoto extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'type'        => QcPhotoType::class,
        'size_bytes'  => 'integer',
        'width'       => 'integer',
        'height'      => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'path', 'uploaded_by', 'disk'])
            ->useLogName('qc_photos')
            ->logOnlyDirty();
    }

    protected static function booted(): void
    {
        static::deleted(function (self $photo) {
            if (!empty($photo->path)) {
                Storage::disk($photo->disk ?? 'coreflow')->delete($photo->path);
            }
        });
    }

    public function drillHoleSample(): BelongsTo
    {
        return $this->belongsTo(DrillHoleSample::class, 'drill_hole_sample_id');
    }

    public function sample(): BelongsTo
    {
        return $this->drillHoleSample();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return route('qc-photos.show', $this);
    }
}
