<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Formulario ALS
    |--------------------------------------------------------------------------
    */

    // Nombre fijo de la empresa en el formulario.
    'company' => 'Minera Media Luna',

    // Teléfono de contacto según el código de supervisor elegido en el monitor.
    'supervisor_phones' => [
        'MF' => '7335821809',
        'EC' => '7335832680',
    ],

    // Proyectos (Proyecto.code) para los que NO debe aparecer el texto
    // predefinido "OA-GRA09s (a excepción de muestras Qc)" (campo "Analytical 7").
    'blank_gra09_projects' => ['ATZ', 'NJO', 'NAR'],

    // Texto cuando el barreno no tiene proyecto capturado.
    'unregistered_label' => 'Sin registrar',

];
