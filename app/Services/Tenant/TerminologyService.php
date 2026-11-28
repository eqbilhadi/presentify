<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant;

class TerminologyService
{
    /**
     * Canonical terminology keys and metadata used across all tenant portals.
     *
     * @var array<string, array{label: string, description: string, default: string, placeholder: string}>
     */
    private const DEFINITIONS = [
        'employee' => [
            'label' => 'Anggota / Pegawai',
            'description' => 'Sebutan untuk pengguna yang melakukan presensi.',
            'default' => 'Pegawai',
            'placeholder' => 'Contoh: Pegawai, ASN, Karyawan, Guru, Siswa',
        ],
        'unit' => [
            'label' => 'Unit Kerja',
            'description' => 'Sebutan untuk satuan struktur organisasi atau kelompok.',
            'default' => 'Unit Kerja',
            'placeholder' => 'Contoh: Bidang, Divisi, Departemen, Kelas, Poli',
        ],
        'supervisor' => [
            'label' => 'Atasan / Verifikator',
            'description' => 'Sebutan untuk pejabat atau pemeriksa pengajuan.',
            'default' => 'Atasan',
            'placeholder' => 'Contoh: Kepala Bidang, Manajer, Wali Kelas',
        ],
        'delegation' => [
            'label' => 'Pelaksana Tugas',
            'description' => 'Sebutan untuk penerima pelimpahan wewenang sementara.',
            'default' => 'Pelaksana Tugas',
            'placeholder' => 'Contoh: Plt., Plh., Pengganti Sementara',
        ],
        'attendance' => [
            'label' => 'Aktivitas Presensi',
            'description' => 'Istilah untuk pencatatan masuk dan pulang.',
            'default' => 'Presensi',
            'placeholder' => 'Contoh: Presensi, Absensi, Kehadiran',
        ],
        'leave' => [
            'label' => 'Izin & Cuti',
            'description' => 'Istilah untuk pengajuan ketidakhadiran resmi.',
            'default' => 'Izin & Cuti',
            'placeholder' => 'Contoh: Izin & Cuti, Dispensasi, Permohonan Izin',
        ],
        'nip' => [
            'label' => 'Nomor Identitas',
            'description' => 'Label nomor induk resmi pada profil dan tabel anggota.',
            'default' => 'NIP',
            'placeholder' => 'Contoh: NIP, NIK, NIS, NUPTK, ID Karyawan',
        ],
        'office' => [
            'label' => 'Instansi / Organisasi',
            'description' => 'Penyebutan entitas utama penyelenggara sistem.',
            'default' => 'Instansi',
            'placeholder' => 'Contoh: Instansi, Sekolah, Klinik, Perusahaan',
        ],
    ];

    /**
     * @var array<int, array{key: string, label: string, description: string, terms: array<string, string>}>
     */
    private const PRESETS = [
        [
            'key' => 'pemerintah',
            'label' => 'Instansi Pemerintah',
            'description' => 'Istilah baku birokrasi dan kepegawaian ASN.',
            'terms' => [
                'employee' => 'Pegawai',
                'unit' => 'Bidang',
                'supervisor' => 'Kepala Bidang',
                'delegation' => 'Plt.',
                'attendance' => 'Presensi',
                'leave' => 'Izin & Cuti',
                'nip' => 'NIP',
                'office' => 'Instansi',
            ],
        ],
        [
            'key' => 'sekolah',
            'label' => 'Sekolah / Kampus',
            'description' => 'Istilah akademik untuk guru, staf, dan kelas.',
            'terms' => [
                'employee' => 'Guru & Staf',
                'unit' => 'Kelas / Jurusan',
                'supervisor' => 'Wali Kelas',
                'delegation' => 'Guru Pengganti',
                'attendance' => 'Kehadiran',
                'leave' => 'Izin & Dispensasi',
                'nip' => 'NUPTK',
                'office' => 'Sekolah',
            ],
        ],
        [
            'key' => 'klinik',
            'label' => 'Klinik / Rumah Sakit',
            'description' => 'Istilah layanan kesehatan untuk tenaga medis dan poli.',
            'terms' => [
                'employee' => 'Tenaga Medis',
                'unit' => 'Poli / Unit',
                'supervisor' => 'Kepala Ruangan',
                'delegation' => 'Pengganti Jaga',
                'attendance' => 'Kehadiran Shift',
                'leave' => 'Cuti & Tukar Shift',
                'nip' => 'STR / NIP',
                'office' => 'Fasyankes',
            ],
        ],
        [
            'key' => 'perusahaan',
            'label' => 'Perusahaan Swasta',
            'description' => 'Istilah korporat untuk karyawan dan divisi kerja.',
            'terms' => [
                'employee' => 'Karyawan',
                'unit' => 'Divisi',
                'supervisor' => 'Manajer',
                'delegation' => 'Acting Manager',
                'attendance' => 'Absensi',
                'leave' => 'Cuti & Izin',
                'nip' => 'ID Karyawan',
                'office' => 'Perusahaan',
            ],
        ],
    ];

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /**
     * @return array<string, string>
     */
    public function getDefaults(): array
    {
        $defaults = [];

        foreach (self::DEFINITIONS as $key => $meta) {
            $defaults[$key] = $meta['default'];
        }

        return $defaults;
    }

    /**
     * Merge tenant-specific terminology overrides with canonical default terms.
     *
     * @return array<string, string>
     */
    public function resolveTerms(?Tenant $tenant): array
    {
        $resolved = $this->getDefaults();

        if (! $tenant instanceof Tenant) {
            return $resolved;
        }

        $raw = $tenant->terminology;

        if (! is_array($raw)) {
            return $resolved;
        }

        foreach ($resolved as $key => $defaultValue) {
            $candidate = $raw[$key] ?? null;

            if (is_string($candidate) && trim($candidate) !== '') {
                $resolved[$key] = trim($candidate);
            }
        }

        return $resolved;
    }

    /**
     * @return array{
     *     terms: array<string, string>,
     *     defaults: array<string, string>,
     *     definitions: array<int, array{key: string, label: string, description: string, default: string, placeholder: string, value: string}>,
     *     presets: array<int, array{key: string, label: string, description: string, terms: array<string, string>}>,
     *     is_customized: bool,
     * }
     */
    public function getEditorData(Tenant $tenant): array
    {
        $terms = $this->resolveTerms($tenant);
        $defaults = $this->getDefaults();
        $definitions = [];

        foreach (self::DEFINITIONS as $key => $meta) {
            $definitions[] = [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'default' => $meta['default'],
                'placeholder' => $meta['placeholder'],
                'value' => $terms[$key],
            ];
        }

        $isCustomized = is_array($tenant->terminology)
            && $tenant->terminology !== []
            && $terms !== $defaults;

        return [
            'terms' => $terms,
            'defaults' => $defaults,
            'definitions' => $definitions,
            'presets' => self::PRESETS,
            'is_customized' => $isCustomized,
        ];
    }

    /**
     * @param  array<string, string>  $terms
     */
    public function updateTerminology(Tenant $tenant, array $terms): Tenant
    {
        $normalized = [];

        foreach (self::DEFINITIONS as $key => $meta) {
            $value = $terms[$key] ?? $meta['default'];
            $trimmed = trim($value);
            $normalized[$key] = $trimmed === '' ? $meta['default'] : $trimmed;
        }

        $tenant->terminology = $normalized;
        $tenant->save();

        return $tenant;
    }

    public function resetTerminology(Tenant $tenant): Tenant
    {
        $tenant->terminology = null;
        $tenant->save();

        return $tenant;
    }
}
