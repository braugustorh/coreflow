<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Peso Máximo por Costal / Saco (kg)
    |--------------------------------------------------------------------------
    |
    | Define el límite de peso en kilogramos para la agrupación automática de
    | muestras en costales/sacos de despacho.
    |
    */
    'max_sack_weight' => 25.0,

    /*
    |--------------------------------------------------------------------------
    | Peso por Defecto de Muestra (kg)
    |--------------------------------------------------------------------------
    |
    | En caso de que una muestra no tenga registrado su peso (weight = null o 0),
    | se utiliza este valor por defecto para el cálculo acumulativo.
    |
    */
    'default_sample_weight' => 1.0,
];
