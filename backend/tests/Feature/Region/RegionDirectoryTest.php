<?php

use App\Modules\Region\Models\District;
use App\Modules\Region\Models\Province;
use App\Modules\Region\Models\Regency;
use App\Modules\Region\Models\Village;
use Illuminate\Support\Facades\DB;

function seedJakartaRegion(): void
{
    DB::table('provinces')->insert(['code' => '31', 'name' => 'DKI Jakarta']);
    DB::table('regencies')->insert([
        ['code' => '3171', 'province_code' => '31', 'name' => 'Kota Administrasi Jakarta Pusat'],
        ['code' => '3175', 'province_code' => '31', 'name' => 'Kota Administrasi Jakarta Timur'],
    ]);
    DB::table('districts')->insert([
        ['code' => '317501', 'regency_code' => '3175', 'name' => 'Matraman'],
        ['code' => '317502', 'regency_code' => '3175', 'name' => 'Pulogadung'],
    ]);
    DB::table('villages')->insert([
        ['code' => '3175011001', 'district_code' => '317501', 'name' => 'Pisangan Baru', 'postal_code' => '13110'],
        ['code' => '3175011002', 'district_code' => '317501', 'name' => 'Utan Kayu Utara', 'postal_code' => '13120'],
    ]);
}

it('lists provinces sorted by name', function (): void {
    seedJakartaRegion();

    $this->getJson('/api/v1/regions/provinces')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.code', '31')
        ->assertJsonPath('data.0.name', 'DKI Jakarta');
});

it('lists regencies of a province', function (): void {
    seedJakartaRegion();

    $this->getJson('/api/v1/regions/regencies?province=31')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.code', '3171')
        ->assertJsonPath('data.1.code', '3175');
});

it('lists districts of a regency', function (): void {
    seedJakartaRegion();

    $this->getJson('/api/v1/regions/districts?regency=3175')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Matraman');
});

it('lists villages of a district including postal codes', function (): void {
    seedJakartaRegion();

    $this->getJson('/api/v1/regions/villages?district=317501')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.code', '3175011001')
        ->assertJsonPath('data.0.name', 'Pisangan Baru')
        ->assertJsonPath('data.0.postal_code', '13110');
});

it('requires a parent code to list child regions', function (): void {
    $this->getJson('/api/v1/regions/regencies')->assertStatus(422);
    $this->getJson('/api/v1/regions/districts')->assertStatus(422);
    $this->getJson('/api/v1/regions/villages')->assertStatus(422);
});

it('returns an empty list for an unknown parent code', function (): void {
    seedJakartaRegion();

    $this->getJson('/api/v1/regions/districts?regency=9999')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('maps region relations correctly', function (): void {
    seedJakartaRegion();

    $province = Province::query()->findOrFail('31');
    $regency = Regency::query()->findOrFail('3175');
    $district = District::query()->findOrFail('317501');
    $village = Village::query()->findOrFail('3175011001');

    expect($province->regencies)->toHaveCount(2)
        ->and($regency->province->name)->toBe('DKI Jakarta')
        ->and($district->regency->name)->toBe('Kota Administrasi Jakarta Timur')
        ->and($village->district->name)->toBe('Matraman');
});
