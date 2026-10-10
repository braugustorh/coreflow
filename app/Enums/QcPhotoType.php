<?php

namespace App\Enums;

enum QcPhotoType: string
{
    case Scale = 'scale';
    case Context = 'context';

    public function label(): string
    {
        return match ($this) {
            self::Scale => 'Báscula',
            self::Context => 'Muestras circundantes',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Scale => 'Muestra QC sobre la báscula con el peso visible',
            self::Context => 'Muestra QC junto a las muestras adyacentes',
        };
    }

    public function fileSuffix(): string
    {
        return match ($this) {
            self::Scale => 'scale',
            self::Context => 'context',
        };
    }
}
