"use client";

import { Field, Input, Select } from "@/components/ui/Input";
import { useDistricts, useProvinces, useRegencies, useVillages } from "@/features/regions";
import type { RegionOption } from "@/lib/api/domain";

export type RegionValue = {
  province: string;
  city: string;
  district: string | null;
  village: string | null;
  postal_code: string;
};

type RegionFieldsProps = {
  value: RegionValue;
  onChange: (value: RegionValue) => void;
  disabled?: boolean;
};

function normalize(value: string | null | undefined): string {
  return (value ?? "").trim().replace(/\s+/g, " ").toLowerCase();
}

function findCode(options: RegionOption[] | undefined, name: string): string {
  if (!name) return "";

  return options?.find((option) => normalize(option.name) === normalize(name))?.code ?? "";
}

/**
 * Pilihan wilayah berjenjang: provinsi -> kota/kabupaten -> kecamatan ->
 * kelurahan/desa, dengan kode pos terisi otomatis dari kelurahan terpilih.
 *
 * Nilai yang tersimpan adalah nama wilayah (sesuai kolom alamat yang ada).
 * Bila alamat lama berisi nama yang tidak ada di direktori, nama tersebut
 * tetap ditampilkan agar tidak hilang saat diedit.
 */
export function RegionFields({ value, onChange, disabled }: RegionFieldsProps) {
  const provinces = useProvinces();
  const provinceCode = findCode(provinces.data, value.province);

  const regencies = useRegencies(provinceCode);
  const regencyCode = findCode(regencies.data, value.city);

  const districts = useDistricts(regencyCode);
  const districtCode = findCode(districts.data, value.district ?? "");

  const villages = useVillages(districtCode);

  const update = (partial: Partial<RegionValue>) => onChange({ ...value, ...partial });

  const selectProvince = (name: string) =>
    update({ province: name, city: "", district: "", village: "", postal_code: "" });

  const selectCity = (name: string) =>
    update({ city: name, district: "", village: "", postal_code: "" });

  const selectDistrict = (name: string) =>
    update({ district: name, village: "", postal_code: "" });

  const selectVillage = (name: string) => {
    const village = villages.data?.find((item) => normalize(item.name) === normalize(name));

    update({ village: name, postal_code: village?.postal_code ?? "" });
  };

  const legacyProvince =
    value.province && provinceCode === "" ? value.province : null;
  const legacyCity = value.city && regencyCode === "" ? value.city : null;
  const legacyDistrict =
    value.district && districtCode === "" ? value.district : null;

  return (
    <>
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Provinsi" htmlFor="province" required>
          <Select
            id="province"
            value={value.province}
            onChange={(event) => selectProvince(event.target.value)}
            disabled={disabled || provinces.isPending}
          >
            <option value="">
              {provinces.isPending ? "Memuat provinsi..." : "Pilih provinsi"}
            </option>
            {legacyProvince ? (
              <option value={legacyProvince}>{legacyProvince}</option>
            ) : null}
            {provinces.data?.map((province) => (
              <option key={province.code} value={province.name}>
                {province.name}
              </option>
            ))}
          </Select>
        </Field>

        <Field label="Kota/Kabupaten" htmlFor="city" required>
          <Select
            id="city"
            value={value.city}
            onChange={(event) => selectCity(event.target.value)}
            disabled={disabled || !provinceCode || regencies.isPending}
          >
            <option value="">
              {!provinceCode
                ? "Pilih provinsi dulu"
                : regencies.isPending
                  ? "Memuat kota/kabupaten..."
                  : "Pilih kota/kabupaten"}
            </option>
            {legacyCity ? <option value={legacyCity}>{legacyCity}</option> : null}
            {regencies.data?.map((regency) => (
              <option key={regency.code} value={regency.name}>
                {regency.name}
              </option>
            ))}
          </Select>
        </Field>
      </div>

      <div className="grid gap-3 sm:grid-cols-3">
        <Field label="Kecamatan" htmlFor="district">
          <Select
            id="district"
            value={value.district ?? ""}
            onChange={(event) => selectDistrict(event.target.value)}
            disabled={disabled || !regencyCode || districts.isPending}
          >
            <option value="">
              {!regencyCode
                ? "Pilih kota dulu"
                : districts.isPending
                  ? "Memuat kecamatan..."
                  : "Pilih kecamatan"}
            </option>
            {legacyDistrict ? (
              <option value={legacyDistrict}>{legacyDistrict}</option>
            ) : null}
            {districts.data?.map((district) => (
              <option key={district.code} value={district.name}>
                {district.name}
              </option>
            ))}
          </Select>
        </Field>

        <Field label="Kelurahan/Desa" htmlFor="village">
          <Select
            id="village"
            value={value.village ?? ""}
            onChange={(event) => selectVillage(event.target.value)}
            disabled={disabled || !districtCode || villages.isPending}
          >
            <option value="">
              {!districtCode
                ? "Pilih kecamatan dulu"
                : villages.isPending
                  ? "Memuat kelurahan..."
                  : "Pilih kelurahan/desa"}
            </option>
            {value.village && !villages.data?.some(
              (village) => normalize(village.name) === normalize(value.village),
            ) ? (
              <option value={value.village}>{value.village}</option>
            ) : null}
            {villages.data?.map((village) => (
              <option key={village.code} value={village.name}>
                {village.name}
              </option>
            ))}
          </Select>
        </Field>

        <Field label="Kode pos" htmlFor="postal_code" required>
          <Input
            id="postal_code"
            value={value.postal_code}
            onChange={(event) => update({ postal_code: event.target.value })}
            disabled={disabled}
            inputMode="numeric"
            maxLength={5}
            placeholder="Otomatis dari kelurahan"
          />
        </Field>
      </div>
    </>
  );
}
