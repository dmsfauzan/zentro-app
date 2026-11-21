<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Impor direktori wilayah administrasi Indonesia dari berkas CSV.
 *
 * Sumber: Kepmendagri (via github.com/vermaysha/database-wilayah-indonesia).
 * Idempotent: baris di-upsert berdasarkan kode wilayah.
 */
class RegionSeeder extends Seeder
{
    private const CHUNK = 1000;

    public function run(): void
    {
        $this->import(
            'provinces.csv',
            'provinces',
            ['code' => 0, 'name' => 1],
            ['name'],
        );

        $this->import(
            'regencies.csv',
            'regencies',
            ['code' => 0, 'province_code' => 1, 'name' => 2],
            ['province_code', 'name'],
        );

        $this->import(
            'districts.csv',
            'districts',
            ['code' => 0, 'regency_code' => 2, 'name' => 3],
            ['regency_code', 'name'],
        );

        $this->import(
            'villages.csv',
            'villages',
            ['code' => 0, 'district_code' => 3, 'name' => 4, 'postal_code' => 5],
            ['district_code', 'name', 'postal_code'],
        );
    }

    /**
     * @param  array<string, int>  $map  kolom => indeks kolom CSV
     * @param  list<string>  $update  kolom yang diperbarui saat kode sudah ada
     */
    private function import(string $file, string $table, array $map, array $update): void
    {
        $path = database_path('data/regions/'.$file);

        if (! is_file($path)) {
            $this->command?->warn("Berkas wilayah tidak ditemukan: {$file} (dilewati).");

            return;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            $this->command?->warn("Berkas wilayah tidak dapat dibuka: {$file} (dilewati).");

            return;
        }

        // Lewati header.
        fgetcsv($handle);

        $buffer = [];
        $total = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            $record = [];

            foreach ($map as $column => $index) {
                $value = isset($row[$index]) ? trim((string) $row[$index]) : '';
                $record[$column] = $value === '' ? null : $value;
            }

            if ($record[array_key_first($map)] === null) {
                continue;
            }

            $buffer[] = $record;
            $total++;

            if (count($buffer) >= self::CHUNK) {
                DB::table($table)->upsert($buffer, ['code'], $update);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::table($table)->upsert($buffer, ['code'], $update);
        }

        fclose($handle);

        $this->command?->info("Wilayah {$table}: {$total} baris.");
    }
}
