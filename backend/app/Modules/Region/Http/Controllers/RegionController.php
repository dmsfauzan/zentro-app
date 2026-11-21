<?php

namespace App\Modules\Region\Http\Controllers;

use App\Modules\Region\Models\District;
use App\Modules\Region\Models\Province;
use App\Modules\Region\Models\Regency;
use App\Modules\Region\Models\Village;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Direktori wilayah administrasi Indonesia (publik, hanya baca).
 *
 * Endpoint dirancang bertingkat agar frontend hanya memuat data yang
 * dibutuhkan: provinsi -> kabupaten/kota -> kecamatan -> kelurahan/desa.
 */
class RegionController
{
    public function provinces(): JsonResponse
    {
        $items = Province::query()
            ->orderBy('name')
            ->get(['code', 'name']);

        return ApiResponse::success($items, 'Daftar provinsi berhasil diambil.');
    }

    public function regencies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'province' => ['required', 'string', 'max:2'],
        ]);

        $items = Regency::query()
            ->where('province_code', $validated['province'])
            ->orderBy('name')
            ->get(['code', 'province_code', 'name']);

        return ApiResponse::success($items, 'Daftar kabupaten/kota berhasil diambil.');
    }

    public function districts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'regency' => ['required', 'string', 'max:4'],
        ]);

        $items = District::query()
            ->where('regency_code', $validated['regency'])
            ->orderBy('name')
            ->get(['code', 'regency_code', 'name']);

        return ApiResponse::success($items, 'Daftar kecamatan berhasil diambil.');
    }

    public function villages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'district' => ['required', 'string', 'max:6'],
        ]);

        $items = Village::query()
            ->where('district_code', $validated['district'])
            ->orderBy('name')
            ->get(['code', 'district_code', 'name', 'postal_code']);

        return ApiResponse::success($items, 'Daftar kelurahan/desa berhasil diambil.');
    }
}
