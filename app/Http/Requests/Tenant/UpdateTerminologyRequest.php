<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Services\Tenant\TerminologyService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTerminologyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tenant_terminology.update') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(TerminologyService $terminologyService): array
    {
        $rules = [
            'terms' => ['required', 'array'],
        ];

        foreach ($terminologyService->keys() as $key) {
            $rules["terms.{$key}"] = ['required', 'string', 'min:1', 'max:60'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms.required' => 'Daftar istilah terminologi wajib dikirimkan.',
            'terms.array' => 'Format istilah terminologi tidak valid.',
            'terms.*.required' => 'Istilah :attribute tidak boleh kosong.',
            'terms.*.string' => 'Istilah :attribute harus berupa teks.',
            'terms.*.min' => 'Istilah :attribute minimal 1 karakter.',
            'terms.*.max' => 'Istilah :attribute maksimal 60 karakter.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'terms.employee' => 'Anggota / Pegawai',
            'terms.unit' => 'Unit Kerja',
            'terms.supervisor' => 'Atasan / Verifikator',
            'terms.delegation' => 'Pelaksana Tugas',
            'terms.attendance' => 'Aktivitas Presensi',
            'terms.leave' => 'Izin & Cuti',
            'terms.nip' => 'Nomor Identitas',
            'terms.office' => 'Instansi / Organisasi',
        ];
    }
}
