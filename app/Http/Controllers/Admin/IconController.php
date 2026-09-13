<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Icons\IconRepository;
use Illuminate\Http\JsonResponse;

/**
 * Serves one icon body to the picker preview.
 *
 * Behind the admin middleware like everything else here, and it only ever
 * returns artwork from the catalogue — the name is looked up, never used to
 * reach a file.
 */
class IconController extends Controller
{
    public function __invoke(string $name, IconRepository $icons): JsonResponse
    {
        if (! $icons->has($name)) {
            return response()->json(['message' => 'Ikon tidak ditemukan'], 404);
        }

        return response()->json(['name' => $name, 'body' => $icons->body($name)]);
    }
}

