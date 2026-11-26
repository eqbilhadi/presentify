# Product Requirements Document (PRD): Sistem Presensi Dinamis Multi-Tenant

**Tech Stack:** Laravel 13, stancl/tenancy, spatie/laravel-permission, Inertia.js v3, React 19, shadcn/ui, Tailwind CSS v4, Wayfinder  
**Target Pengguna:** Instansi Pemerintah, Sekolah/Kampus, Klinik/RS, Perusahaan Swasta, & Bisnis Berbasis Shift  
**Status Dokumen:** Draft v1.2 (3-Layer Middleware Routing & Universal Spatie RBAC Locked)

## 1. Ringkasan Eksekutif & Visi Produk

### 1.1 Latar Belakang
Sebagian besar aplikasi presensi di pasar bersifat kaku (hanya mendukung jam masuk-pulang standar dan satu metode absen) sehingga sulit diterapkan lintas jenis instansi. Selain itu, instansi di Indonesia memiliki kebutuhan spesifik seperti alur birokrasi berjenjang, kondisi jaringan lapangan yang tidak stabil, serta potensi kecurangan presensi (*fake GPS*, titip absen, atau foto layar).

### 1.2 Visi Produk
Membangun platform SaaS presensi multi-tenant yang **dapat dikonfigurasi tanpa coding ulang** (*dynamic policy & workflow*), memiliki **tampilan white-label instan** per instansi melalui shadcn/ui, **tahan terhadap kecurangan serta kondisi offline**, dan dilengkapi **fitur AI praktis** untuk efisiensi admin maupun pimpinan.

## 2. Arsitektur Multi-Tenant, 3-Layer Routing, Central SSO & White-Label

| Aspek | Spesifikasi Teknis |
| :--- | :--- |
| **Model Isolasi Data** | **Database-per-Tenant** (satu database terpisah per instansi dengan prefix `presentify_tenant_`) dengan subdomain routing (`{tenant}.domain.com`), dipadukan dengan **Central Database** untuk identitas global (`users`) dan orkestrasi tenant (`sys_tenants`, `sys_domains`, `sys_tenant_user`). |
| **Arsitektur 3-Layer Routing (Berbasis Middleware)** | Route dipisah menjadi 3 konteks tanpa menggunakan `Route::domain()`, melainkan dikendalikan murni lewat middleware agar mendukung seluruh daftar `central_domains` (`presentify.test`, `app.presentify.test`, `localhost`, `127.0.0.1`):<br>1. **Central (`routes/central/*.php`):** Hanya bisa diakses di domain central (middleware `PreventAccessFromTenantDomains`).<br>2. **Tenant (`routes/tenant/*.php`):** Hanya bisa diakses di subdomain tenant (middleware `InitializeTenancyBySubdomain` + `PreventAccessFromCentralDomains`).<br>3. **Universal (`routes/universal/*.php`):** Bisa diakses di Central maupun Tenant; koneksi database otomatis mengikuti konteks aktif (middleware `InitializeTenancyBySubdomainIfApplicable` + `EnsureKnownDomain`).<br>Struktur file route dijaga datar maksimal **1 level folder** di bawah `routes/{context}/`. |
| **Akun Global & Member Lokal** | Data identitas utama (`users`, `CentralConnection`) disimpan di Central DB dan dipetakan ke tenant lewat `sys_tenant_user`. Relasi role/permission pada `User` bersifat *tenant-aware* sehingga otomatis membaca tabel RBAC di Central DB saat di Central dan di Tenant DB saat di Tenant. Di masing-masing Tenant DB terdapat `org_members` (`global_user_id`, NIP/ID pegawai, `unit_id`, `device_fingerprint`) untuk relasi operasional internal tenant. |
| **Centralized SSO Login** | Login terpusat di domain utama (`presentify.test/login` atau `app.presentify.test/login`) mendukung **Email + Password**, **Passkeys/2FA**, dan **OTP Email** (6 digit hash SHA-256, TTL 5 menit, rate-limited). |
| **Alur Redirect & Multi-Tenant Picker** | Punya 1 tenant aktif -> langsung terbitkan *One-Time SSO Token* (TTL 60 detik, single-use, `lockForUpdate()`) dan redirect ke `{tenant}.domain.com/sso/callback`. Punya 2+ tenant -> tampilkan halaman **Pilih Instansi** (shadcn/ui card) terlebih dahulu, lalu redirect tanpa login ulang. |
| **SP-Initiated SSO, Switch Instansi & Signed SLO** | Guest yang langsung membuka subdomain tenant otomatis diarahkan ke Central SSO (`redirectGuestsTo` -> `/sso/login` -> `/auth/sso/authorize`) dengan proteksi `state` anti-CSRF. User multi-tenant dapat berpindah instansi lewat *Instansi Switcher* di sidebar tanpa login ulang. Tombol Logout di subdomain mana pun memicu **Single Logout (SLO)** melalui *temporary signed URL* (berlaku 1 menit) yang menghapus sesi lokal sekaligus sesi Central. |
| **White-Label UI** | Pengaturan logo, nama instansi, dan palet warna utama yang diinjeksi langsung ke CSS variables shadcn/ui (`--primary`, `--accent`, `--radius`). |
| **Kamus Terminologi** | Tenant dapat mengganti label bawaan aplikasi secara dinamis (contoh: *Pegawai / ASN / Karyawan / Siswa*, *Divisi / Bidang / Kelas*). |

## 3. Core RBAC (Universal Spatie), Custom Role Builder & Dynamic Menu

### 3.1 Peran Bawaan (Default Roles) & Custom Role Builder
Modul RBAC bersifat **Universal** (berjalan di Central DB untuk pengelola SaaS maupun di masing-masing Tenant DB secara terisolasi untuk pengelola instansi) menggunakan **`spatie/laravel-permission`** dengan tabel berprefix `rbac_`:
1. **Superadmin SaaS (Central DB):** Mengelola daftar tenant, paket langganan, kuota token AI, menu/role central, dan monitoring kesehatan sistem.
2. **Admin Tenant (Tenant DB — HRD / Operator Instansi):** Mengatur struktur unit kerja, *custom role & permission*, tata letak menu sidebar tenant, *policy* presensi, alur persetujuan, *white-labeling*, reset perangkat, dan ekspor laporan.
3. **Atasan / Verifikator (Tenant DB):** Menyetujui/menolak pengajuan izin, cuti, dinas luar, atau koreksi presensi dalam lingkup `unit_id` bawahannya (serta unit delegasi aktif).
4. **Pegawai / Anggota (Tenant DB):** Melakukan presensi (online/offline), melihat riwayat kehadiran, serta mengajukan izin/cuti/dinas luar.
5. **Akun Kiosk (Tenant DB — Anjungan):** Perangkat terotorisasi via *6-digit pairing code* (ditukar *scoped token*) khusus tablet/monitor lobi dengan akses terbatas (`kiosk.qr.display`, `kiosk.scan`).

### 3.2 Struktur 2-Tier Permission (`rbac_permissions` & `rbac_menu_actions`)
1. **Menu-Bound Permission (terhubung ke `rbac_menu_actions`):** Setiap menu dengan `permission_prefix` (misal `menus`, `roles`, `permissions`, `units`, `attendances`) memiliki daftar aksi di `rbac_menu_actions` (`action`, `label`, `permission_name` berbentuk `{prefix}.{action}`, `route_name`, `order`). Saat aksi dibuat/diubah/dihapus, permission terkait di `rbac_permissions` ikut disinkronkan secara otomatis.
2. **Standalone / Global Permission (`doesntHave('menuAction')`):** Hak akses fungsional lintas modul atau sistem yang tidak terikat pada satu menu (misal: `attendance.override`, `attendance.bypass_geofence`, `device.reset`, `leave.approve_all_units`, `ai.command_bar.use`, `audit.view`), dikelompokkan berdasarkan kolom `group`.
3. **Proteksi Route Eksplisit:** Setiap endpoint dilindungi langsung oleh middleware Spatie (`permission:{name}`), bukan pemetaan implisit per nama controller.

### 3.3 Dynamic Sidebar Menu (`rbac_menus` & `MenuService`)
* **Manajemen Menu Universal:** Admin (Central maupun Tenant) dapat mengelola label (`label_name`), ikon (`icon`), hierarki (`parent_id`), urutan (`sort_num` lewat halaman drag/reorder), status aktif (`is_active`), divider (`is_divider`), serta penugasan menu & aksinya ke setiap role melalui matriks pohon checkbox (*tri-state* parent-child-action).
* **Caching Per Konteks & User:** `MenuService` menyusun pohon menu aktif sesuai role user dengan cache key terisolasi `menus:{tenant_id|central}:{user_id}`, lalu membagikannya ke React via `HandleInertiaRequests` (`auth.menus` & `auth.permissions`).
* **Non-Destructive Seeding:** Trait `SeedsCrudMenus` menanamkan menu dan aksi bawaan secara idempoten (`updateOrCreate` & `syncWithoutDetaching`) untuk Central maupun Tenant baru.

### 3.4 Hierarki Unit Kerja & Delegasi Sementara
* Setiap `member` memiliki **1 unit kerja utama (`unit_id`)**.
* Struktur organisasi mendukung kedalaman bertingkat dinamis menggunakan pola **Parent ID + Materialized Path** (`path`, `depth`) sehingga filter data bawahan berjalan dalam 1 query tanpa rekursi berat.
* Untuk kebutuhan pelaksana tugas (Plt.) atau cuti atasan, sistem menyediakan tabel **Delegasi Sementara** (`org_delegations` dengan rentang tanggal mulai–selesai) yang otomatis memperluas scope persetujuan penerima delegasi.

## 4. Spesifikasi Fitur Fungsional (Bertahap)

### Fase 1: Core System, Dynamic Engine & Workflow
* **F1.1 Core Auth, Central SSO, 3-Layer Routing & Universal RBAC:** Login terpusat (Email/Password + OTP Email), *Workspace Picker*, *Instansi Switcher*, Single Logout, 3-layer middleware routing (`central`, `tenant`, `universal`), serta modul Universal RBAC (*Menu Management + Sort*, *Permission Management: Menu Actions & Standalone*, *Role Management + Tri-State Tree Matrix*, dan *Dynamic Sidebar*).
* **F1.2 Tenant Onboarding & White-Labeling:** Pengaturan profil instansi, upload logo, tema warna shadcn/ui, dan kamus istilah.
* **F1.3 Struktur Organisasi & Delegasi:** Pengelolaan unit kerja bertingkat (*Materialized Path*), pemetaan member lokal (`org_members`), dan penugasan delegasi wewenang sementara.
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
| `users` | Identitas akun global lintas instansi | `id` (`bigIncrements`), `name`, `email`, `password`, `email_verified_at` |
| `auth_otp_tokens` | Penyimpanan hash OTP email & TTL | `id`, `user_id`, `token_hash`, `expires_at`, `used_at` |
| `auth_sso_tokens` | One-Time SSO token lintas subdomain | `token_hash`, `user_id`, `target_tenant_id`, `expires_at` |
| `sys_tenants` | Profil instansi, koneksi DB, tema shadcn/ui, kamus (`data` JSON) | `id` (UUID), `name`, `slug`, `is_active`, `contact_email`, `data` |
| `sys_domains` | Subdomain/domain milik tenant | `id`, `domain`, `tenant_id` |
| `sys_tenant_user` | Pemetaan keanggotaan user global ke tenant | `tenant_id`, `user_id`, `is_active`, `last_accessed_at` |
| `rbac_*` | Skema RBAC Central (Superadmin SaaS) | `rbac_roles`, `rbac_permissions`, `rbac_model_has_roles`, `rbac_model_has_permissions`, `rbac_role_has_permissions`, `rbac_menus`, `rbac_menu_roles`, `rbac_menu_actions` |

### 5.2 Tenant Database (Per Instansi)
| Tabel | Fungsi Utama | Kolom Kunci |
| :--- | :--- | :--- |
| `rbac_menus` | Konfigurasi tampilan, hierarki & urutan menu sidebar | `id` (ULID), `parent_id`, `sort_num`, `icon`, `label_name`, `route_name`, `url`, `permission_prefix`, `is_active`, `is_divider` |
| `rbac_menu_actions` | Aksi granular terikat menu (`{prefix}.{action}`) | `id` (ULID), `menu_id`, `action`, `label`, `permission_name`, `route_name`, `order` |
| `rbac_menu_roles` | Pivot akses menu per role | `menu_id`, `role_id` |
| `rbac_roles` & `rbac_permissions` | Spatie RBAC (`guard_name`, `group`) + pivot (`rbac_model_has_roles`, `rbac_model_has_permissions`, `rbac_role_has_permissions`) | `id` (ULID), `name`, `guard_name`, `group` (nullable); `model_id` (`unsignedBigInteger`) |
| `org_members` | Profil lokal pegawai/anggota & unit utama | `id`, `global_user_id`, `unit_id`, `nip`, `device_fingerprint`, `is_active` |
| `org_units` | Struktur organisasi bertingkat dinamis | `id`, `parent_id`, `path`, `depth`, `name`, `code`, `leader_member_id` |
| `org_delegations` | Pelimpahan wewenang sementara antar atasan | `id`, `from_member_id`, `to_member_id`, `unit_id`, `starts_at`, `ends_at` |
| `att_policies` | Aturan metode absen, jam kerja, & custom fields | `id`, `rules_json`, `custom_fields_schema` |
| `att_attendances` | Catatan presensi, koordinat, fraud score, & sync | `id`, `member_id`, `check_in_at`, `geo_data`, `fraud_score`, `is_offline_sync` |
| `att_leave_requests` | Pengajuan izin/cuti/dinas luar + data OCR | `id`, `member_id`, `custom_data` (JSON), `ocr_payload`, `status` |
| `wf_workflows` & `wf_approvals` | Rantai persetujuan bertingkat & eksekusi step | `id`, `name`, `steps_json` |
| `log_audit_trails` | Jejak perubahan data *immutable* | `id`, `actor_member_id`, `action`, `before_json`, `after_json` |

## 6. Kebutuhan Non-Fungsional & Keamanan

1. **Isolasi Data & Domain Mutlak:** Setiap tenant memiliki database terpisah; route Central, Tenant, dan Universal dipisahkan ketat lewat middleware tanpa `Route::domain()`, dan eksekusi *AI Text-to-Query* wajib menggunakan koneksi tenant aktif dengan *read-only query whitelist*.
2. **Keamanan SSO, RBAC & Kiosk:** *One-Time SSO Token* hanya berlaku 60 detik dan langsung dihapus setelah diverifikasi. Seluruh endpoint terlindungi middleware `permission:*` Spatie, dan relasi `User` bersifat *tenant-aware* agar izin tidak pernah bocor antar Central dan Tenant. Akun Kiosk memakai token perangkat terikat dengan cakupan izin minimal.
3. **Efisiensi Biaya AI:** Deteksi wajah dan *liveness* dijalankan di sisi klien (browser), sedangkan pemanggilan API LLM/Vision eksternal berjalan asinkron di Laravel Queue dengan batas kuota per tenant.
4. **Reliabilitas Offline:** Payload presensi offline ditandatangani (*HMAC/signature*) saat pengambilan data untuk mencegah manipulasi jam perangkat secara manual.

## 7. Roadmap Implementasi

| Fase | Estimasi Durasi | Fokus Deliverable |
| :--- | :--- | :--- |
| **Fase 1 (Core & Fondasi)** | Minggu 1–4 | Central SSO + OTP Email, Multi-DB Tenancy, 3-Layer Middleware Routing, Universal Spatie RBAC & Dynamic Menu, Unit Hierarchy + Delegasi, Dynamic Policy Engine |
| **Fase 2 (Diferensiasi Lapangan)** | Minggu 5–7 | Browser Liveness Detection, Anti-Fraud GPS/Device, PWA Offline Queue, Mode Kiosk |
| **Fase 3 (AI Praktis)** | Minggu 8–10 | Laravel Queue AI OCR Surat, AI Command Bar (`Cmd+K`), AI Fraud Scoring |
| **Fase 4 (Enterprise)** | Pasca-rilis (Data >= 1–2 bulan) | Early Warning Burnout, AI Roster Optimizer, AI Briefing Pimpinan |
