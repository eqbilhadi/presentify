# Product Requirements Document (PRD): Sistem Presensi Dinamis Multi-Tenant

**Tech Stack:** Laravel, Inertia.js, React, shadcn/ui, Tailwind CSS  
**Target Pengguna:** Instansi Pemerintah, Sekolah/Kampus, Klinik/RS, Perusahaan Swasta, & Bisnis Berbasis Shift  
**Status Dokumen:** Draft v1.1 (Core Auth & RBAC Locked)

## 1. Ringkasan Eksekutif & Visi Produk

### 1.1 Latar Belakang
Sebagian besar aplikasi presensi di pasar bersifat kaku (hanya mendukung jam masuk-pulang standar dan satu metode absen) sehingga sulit diterapkan lintas jenis instansi. Selain itu, instansi di Indonesia memiliki kebutuhan spesifik seperti alur birokrasi berjenjang, kondisi jaringan lapangan yang tidak stabil, serta potensi kecurangan presensi (*fake GPS*, titip absen, atau foto layar).

### 1.2 Visi Produk
Membangun platform SaaS presensi multi-tenant yang **dapat dikonfigurasi tanpa coding ulang** (*dynamic policy & workflow*), memiliki **tampilan white-label instan** per instansi melalui shadcn/ui, **tahan terhadap kecurangan serta kondisi offline**, dan dilengkapi **fitur AI praktis** untuk efisiensi admin maupun pimpinan.

## 2. Arsitektur Multi-Tenant, Central SSO & White-Label

| Aspek | Spesifikasi Teknis |
| :--- | :--- |
| **Model Isolasi Data** | **Database-per-Tenant** (satu database terpisah per instansi) dengan subdomain routing (`{tenant}.domain.com`), dipadukan dengan **Central Database** untuk identitas global dan orkestrasi tenant. |
| **Akun Global & Member Sync** | Data identitas utama (`users`) disimpan di Central DB dan dipetakan ke tenant lewat `tenant_user`. Di dalam masing-masing Tenant DB terdapat tabel `members` (berisi `global_user_id`, NIP/ID pegawai, `unit_id`, dan data lokal) agar seluruh relasi SQL di tenant tetap memiliki *Foreign Key* internal yang kuat. |
| **Centralized SSO Login** | Login terpusat di domain utama (`app.domain.com/login`) mendukung **Email + Password** dan **OTP Email** (6 digit hash, TTL 5 menit, rate-limited). |
| **Alur Redirect & Multi-Tenant Picker** | Punya 1 tenant aktif -> langsung terbitkan *One-Time SSO Token* (TTL 30–60 detik, single-use) dan redirect ke `{tenant}.domain.com/sso/callback`. Punya 2+ tenant -> tampilkan halaman **Pilih Instansi** (shadcn/ui card) terlebih dahulu, lalu redirect tanpa login ulang. |
| **Switch Instansi & Single Logout** | User dengan multi-tenant dapat berpindah instansi lewat *Instansi Switcher* di sidebar tanpa login ulang. Tombol Logout di subdomain mana pun memicu **Single Logout (SLO)** yang menghapus sesi lokal sekaligus sesi Central. |
| **White-Label UI** | Pengaturan logo, nama instansi, dan palet warna utama yang diinjeksi langsung ke CSS variables shadcn/ui (`--primary`, `--accent`, `--radius`). |
| **Kamus Terminologi** | Tenant dapat mengganti label bawaan aplikasi secara dinamis (contoh: *Pegawai / ASN / Karyawan / Siswa*, *Divisi / Bidang / Kelas*). |

## 3. Core RBAC, Custom Role Builder & Dynamic Menu

### 3.1 Peran Bawaan (Default Roles) & Custom Role Builder
Sistem menyediakan 5 peran bawaan yang dapat disesuaikan atau ditambah oleh Admin Tenant melalui **Custom Role Builder**:
1. **Superadmin SaaS (Central):** Mengelola daftar tenant, paket langganan, kuota token AI, dan monitoring kesehatan sistem.
2. **Admin Tenant (HRD / Operator Instansi):** Mengatur struktur unit kerja, *custom role & permission*, tata letak menu sidebar, *policy* presensi, alur persetujuan, *white-labeling*, reset perangkat, dan ekspor laporan.
3. **Atasan / Verifikator:** Menyetujui/menolak pengajuan izin, cuti, dinas luar, atau koreksi presensi dalam lingkup `unit_id` bawahannya (serta unit delegasi aktif).
4. **Pegawai / Anggota:** Melakukan presensi (online/offline), melihat riwayat kehadiran, serta mengajukan izin/cuti/dinas luar.
5. **Akun Kiosk (Anjungan):** Perangkat terotorisasi via *6-digit pairing code* (ditukar *scoped token*) khusus tablet/monitor lobi dengan akses terbatas (`kiosk.qr.display`, `kiosk.scan`).

### 3.2 Struktur 2-Tier Permission
1. **Menu-Bound Permission (`menu_id != null`):** Terikat langsung pada item menu tertentu, mencakup aksi granular seperti `view`, `create`, `edit`, `delete`, `import`, `export`, dan `download`. Aksi selain `view` otomatis mewajibkan permission `view` pada menu terkait aktif.
2. **Global Permission (`menu_id = null`):** Hak akses fungsional lintas modul atau sistem yang tidak terikat pada satu menu (misal: `attendance.override`, `attendance.bypass_geofence`, `device.reset`, `leave.approve_all_units`, `ai.command_bar.use`, `audit.view`).

### 3.3 Dynamic Sidebar Menu
* **Kunci Sistem:** Daftar route/URL dan fungsi bawaan dikelola oleh sistem (*master registry*). Sinkronisasi fitur baru bersifat *non-destructive upsert* (menambah menu baru tanpa menimpa pengaturan tenant).
* **Hak Edit Admin Tenant:** Admin Tenant hanya dapat mengubah **nama tampilan (`custom_label`)**, **ikon (`icon`)**, **pengelompokan hierarki (`parent_id`)**, **urutan (`sort_order`)**, dan **pemberian akses menu ke role**.

### 3.4 Hierarki Unit Kerja & Delegasi Sementara
* Setiap `member` memiliki **1 unit kerja utama (`unit_id`)**.
* Struktur organisasi mendukung kedalaman bertingkat dinamis menggunakan pola **Parent ID + Materialized Path** (`path`, `depth`) sehingga filter data bawahan berjalan dalam 1 query tanpa rekursi berat.
* Untuk kebutuhan pelaksana tugas (Plt.) atau cuti atasan, sistem menyediakan tabel **Delegasi Sementara** (`delegations` dengan rentang tanggal mulai–selesai) yang otomatis memperluas scope persetujuan penerima delegasi.

## 4. Spesifikasi Fitur Fungsional (Bertahap)

### Fase 1: Core System, Dynamic Engine & Workflow
* **F1.1 Core Auth, Central SSO & Multi-Tenant RBAC:** Login terpusat (Email/Password + OTP Email), *Workspace Picker*, *Instansi Switcher*, Single Logout, *Custom Role Builder* (matriks menu-bound + global permission), dan *Dynamic Sidebar Menu Editor*.
* **F1.2 Tenant Onboarding & White-Labeling:** Pengaturan profil instansi, upload logo, tema warna shadcn/ui, dan kamus istilah.
* **F1.3 Struktur Organisasi & Delegasi:** Pengelolaan unit kerja bertingkat (*Materialized Path*) dan penugasan delegasi wewenang sementara.
* **F1.4 Dynamic Policy & Rule Engine:**
  * Penentuan metode presensi berbeda per unit/jabatan (GPS + Selfie, WiFi/IP Kantor, QR Kiosk, atau Browser Check-in untuk *remote*).
  * Dukungan pola kerja: jam tetap, *shift*, *flexi-time* (durasi jam terpenuhi), dan toleransi keterlambatan akumulatif per bulan.
  * *Custom Field Builder* berbasis JSON Schema pada form izin/dinas luar.
* **F1.5 Dynamic Approval Workflow & Audit Trail:** Rantai persetujuan bertingkat, pencatatan log *immutable* setiap perubahan data, serta ekspor laporan kustom (CSV/PDF).

### Fase 2: Smart Integrity, Offline-First & Mode Kiosk
* **F2.1 Client-Side Liveness Detection:** Deteksi wajah dan gerakan sederhana (kedip mata / toleh kepala) langsung di browser React via WebRTC sebelum tombol presensi aktif.
* **F2.2 Deteksi Anomali Lokasi & Device Binding:** Pengecekan radius *geofence*, deteksi *impossible travel*, serta penguncian 1 akun ke 1 *device fingerprint* dengan alur *reset device* via admin.
* **F2.3 Offline-First Mode (PWA + IndexedDB):** Penyimpanan lokal terenkripsi (*signed payload* berisi koordinat, timestamp, dan foto) saat tanpa sinyal dengan auto-sync saat online.
* **F2.4 Mode Kiosk / Anjungan Bersama:** Perangkat lobi hasil *pairing* menampilkan **QR Code dinamis** (rotasi token TOTP setiap 15–30 detik) atau pemindaian bergantian.

### Fase 3: Integrasi AI Asinkron & Command Bar
* **F3.1 AI Fraud Scoring:** Job asinkron di Laravel Queue yang menilai risiko kecurangan dari pantulan layar (*screen moiré*), kesesuaian latar belakang lokasi (*scene check*), dan pola waktu absen mekanis.
* **F3.2 AI OCR + Validator Surat:** Ekstraksi otomatis data dari foto surat dokter, surat izin, atau SPT ke form React beserta validasi kecocokan tanggal surat.
* **F3.3 AI Command Bar (`Cmd+K`):** Integrasi komponen `Command` shadcn/ui untuk **Text-to-Query** (terisolasi per database tenant) dan **Text-to-Policy** (penyusunan draf JSON *policy* dari kalimat natural).

### Fase 4: Analitik Prediktif & Enterprise
* **F4.1 Early Warning System (*Burnout* & Kedisiplinan):** Analisis tren mingguan untuk mendeteksi perubahan pola mikro keterlambatan atau jam pulang tidak wajar.
* **F4.2 AI Shift & Roster Optimizer:** Rekomendasi penyusunan jadwal shift otomatis berbasis histori ketidakhadiran musiman.
* **F4.3 AI Briefing Pimpinan:** Ringkasan otomatis kondisi kehadiran per unit kerja dalam 2–3 kalimat melalui Laravel Scheduler/Queue.

## 5. Arsitektur Data Inti (Konseptual)

### 5.1 Central Database
| Tabel | Fungsi Utama | Kolom Kunci |
| :--- | :--- | :--- |
| `users` | Identitas akun global lintas instansi | `id` (ULID/UUID), `name`, `email`, `password`, `email_verified_at` |
| `otp_tokens` | Penyimpanan hash OTP email & TTL | `id`, `user_id`, `token_hash`, `expires_at`, `used_at` |
| `sso_tokens` | One-Time SSO token lintas subdomain | `token_hash`, `user_id`, `target_tenant_id`, `expires_at` |
| `tenants` | Profil instansi, koneksi DB, tema shadcn/ui, kamus | `id`, `slug`, `db_name`, `theme_config` (JSON), `terminology` (JSON) |
| `tenant_user` | Pemetaan keanggotaan user global ke tenant | `tenant_id`, `user_id`, `is_active`, `last_accessed_at` |

### 5.2 Tenant Database (Per Instansi)
| Tabel | Fungsi Utama | Kolom Kunci |
| :--- | :--- | :--- |
| `members` | Profil lokal pegawai/anggota & unit utama | `id`, `global_user_id`, `unit_id`, `nip`, `device_fingerprint`, `is_active` |
| `units` | Struktur organisasi bertingkat dinamis | `id`, `parent_id`, `path`, `depth`, `name`, `code` |
| `menus` | Konfigurasi tampilan & urutan menu sidebar | `id`, `parent_id`, `slug` (locked), `route_name` (locked), `custom_label`, `icon`, `sort_order` |
| `roles` & `permissions` | Custom role & 2-tier permission (menu-bound + global) | `id`, `name`, `menu_id` (nullable), `action`, `group` |
| `delegations` | Pelimpahan wewenang sementara antar atasan | `id`, `from_member_id`, `to_member_id`, `unit_id`, `starts_at`, `ends_at` |
| `attendance_policies` | Aturan metode absen, jam kerja, & custom fields | `id`, `rules_json`, `custom_fields_schema` |
| `attendances` | Catatan presensi, koordinat, fraud score, & sync | `id`, `member_id`, `check_in_at`, `geo_data`, `fraud_score`, `is_offline_sync` |
| `leave_requests` | Pengajuan izin/cuti/dinas luar + data OCR | `id`, `member_id`, `custom_data` (JSON), `ocr_payload`, `status` |
| `approval_workflows` | Rantai persetujuan bertingkat | `id`, `name`, `steps_json` |
| `audit_logs` | Jejak perubahan data *immutable* | `id`, `actor_member_id`, `action`, `before_json`, `after_json` |

## 6. Kebutuhan Non-Fungsional & Keamanan

1. **Isolasi Data Mutlak:** Setiap tenant memiliki database terpisah; eksekusi *AI Text-to-Query* wajib menggunakan koneksi tenant aktif dengan *read-only query whitelist*.
2. **Keamanan SSO & Kiosk:** *One-Time SSO Token* hanya berlaku 30–60 detik dan langsung dihapus setelah diverifikasi. Akun Kiosk memakai token perangkat terikat dengan cakupan izin minimal.
3. **Efisiensi Biaya AI:** Deteksi wajah dan *liveness* dijalankan di sisi klien (browser), sedangkan pemanggilan API LLM/Vision eksternal berjalan asinkron di Laravel Queue dengan batas kuota per tenant.
4. **Reliabilitas Offline:** Payload presensi offline ditandatangani (*HMAC/signature*) saat pengambilan data untuk mencegah manipulasi jam perangkat secara manual.

## 7. Roadmap Implementasi

| Fase | Estimasi Durasi | Fokus Deliverable |
| :--- | :--- | :--- |
| **Fase 1 (Core & Fondasi)** | Minggu 1–4 | Central SSO + OTP Email, Multi-DB Tenancy, Custom Role & Menu Builder, Unit Hierarchy + Delegasi, Dynamic Policy Engine |
| **Fase 2 (Diferensiasi Lapangan)** | Minggu 5–7 | Browser Liveness Detection, Anti-Fraud GPS/Device, PWA Offline Queue, Mode Kiosk |
| **Fase 3 (AI Praktis)** | Minggu 8–10 | Laravel Queue AI OCR Surat, AI Command Bar (`Cmd+K`), AI Fraud Scoring |
| **Fase 4 (Enterprise)** | Pasca-rilis (Data >= 1–2 bulan) | Early Warning Burnout, AI Roster Optimizer, AI Briefing Pimpinan |
