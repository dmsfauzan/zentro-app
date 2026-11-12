<?php

namespace App\Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Deskriptor layanan di root `/`.
 *
 * Backend ZENTRO bersifat API-only; root bukan halaman HTML. Endpoint ini
 * memberi tahu klien/manusia bahwa layanan hidup dan di mana API berada,
 * sekaligus aman dipakai untuk uptime check sederhana.
 */
class ServiceDescriptorController
{
    public function __invoke(): JsonResponse
    {
        $showDocs = app()->environment(['local', 'testing']);

        return response()->json([
            'name' => 'ZENTRO API',
            'tagline' => 'Everything in One Place',
            'status' => 'ok',
            'environment' => app()->environment(),
            'version' => 'v1',
            'api_base' => url('/api/v1'),
            'health' => url('/up'),
            'docs' => $showDocs ? url('/docs/api') : null,
            'time' => now()->toIso8601String(),
        ]);
    }
}
