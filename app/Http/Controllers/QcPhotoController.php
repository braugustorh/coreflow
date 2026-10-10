<?php

namespace App\Http\Controllers;

use App\Models\QcSamplePhoto;
use App\Services\QcPhotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class QcPhotoController extends Controller
{
    /**
     * Muestra o transmite la fotografía QC de manera autenticada y segura.
     */
    public function show(Request $request, QcSamplePhoto $photo): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $sample = $photo->drillHoleSample;
        if (!$sample) {
            abort(404, 'Muestra asociada no encontrada.');
        }

        $service = app(QcPhotoService::class);
        if (!$service->canManage($user, $sample)) {
            abort(403, 'No tienes permisos para visualizar fotografías de este distrito minero.');
        }

        $disk = Storage::disk($photo->disk ?? 'coreflow');
        if (!$disk->exists($photo->path)) {
            abort(404, 'El archivo de la fotografía no existe en el almacenamiento.');
        }

        return $disk->response(
            $photo->path,
            $photo->original_name ?? basename($photo->path),
            [
                'Cache-Control' => 'private, max-age=3600',
            ]
        );
    }
}
