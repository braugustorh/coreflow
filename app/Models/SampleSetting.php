<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SampleSetting extends Model
{
    use LogsActivity;

    protected $table = 'sample_settings';

    protected $fillable = [
        'max_sack_weight',
        'min_sample_weight',
        'max_sample_weight',
        'factor_pq',
        'factor_hq',
        'factor_nq',
        'factor_bq',
        'blank_weight',
        'standard_weight',
        'duplicate_ratio',
        'require_qc_photos',
    ];

    protected $casts = [
        'max_sack_weight'   => 'decimal:2',
        'min_sample_weight' => 'decimal:2',
        'max_sample_weight' => 'decimal:2',
        'factor_pq'        => 'decimal:2',
        'factor_hq'        => 'decimal:2',
        'factor_nq'        => 'decimal:2',
        'factor_bq'        => 'decimal:2',
        'blank_weight'     => 'decimal:2',
        'standard_weight'  => 'decimal:3',
        'duplicate_ratio'  => 'decimal:2',
        'require_qc_photos' => 'boolean',
    ];

    /**
     * Configuración del log de actividad
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'max_sack_weight',
                'min_sample_weight',
                'max_sample_weight',
                'factor_pq',
                'factor_hq',
                'factor_nq',
                'factor_bq',
                'blank_weight',
                'standard_weight',
                'duplicate_ratio',
                'require_qc_photos',
            ])
            ->logOnlyDirty()
            ->useLogName('sample_settings');
    }

    /**
     * Retorna la instancia única de configuración o la crea con valores por defecto.
     */
    public static function getSettings(): self
    {
        return static::firstOrCreate([], [
            'max_sack_weight'   => 25.00,
            'min_sample_weight' => 0.50,
            'max_sample_weight' => 15.00,
            'factor_pq'        => 7.66,
            'factor_hq'        => 4.28,
            'factor_nq'        => 2.40,
            'factor_bq'        => 1.40,
            'blank_weight'     => 3.00,
            'standard_weight'  => 0.065,
            'duplicate_ratio'  => 50.00,
            'require_qc_photos' => true,
        ]);
    }
}
