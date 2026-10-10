<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/qc-photos/{photo}', [\App\Http\Controllers\QcPhotoController::class, 'show'])
    ->middleware(['web', 'auth'])
    ->name('qc-photos.show');

