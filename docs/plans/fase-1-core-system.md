# Rencana Implementasi Bertahap: Fase 1 (Core System, Dynamic Engine & Workflow)

Dokumen ini adalah rencana teknis pelaksanaan Fase 1 untuk proyek **Presentify (Sistem Presensi Dinamis Multi-Tenant)** berdasarkan spesifikasi pada `docs/prd.md` dan pedoman arsitektur operasional `AGENTS.md`.

---

## 1. Ringkasan & Tujuan Fase 1

Fase 1 meletakkan fondasi inti sistem Presensi Dinamis Multi-Tenant Presentify yang siap produksi, mencakup:
1. **Multi-Tenancy & Central SSO:** Pemisahan Central DB & Database-per-Tenant, login terpusat (Password + OTP Email 6-digit), SSO Token Handshake, Workspace Picker, dan Single Logout (SLO).
2. **3-Layer Middleware Routing & Universal Spatie RBAC:** Pemisahan route `central`, `tenant`, dan `universal` berbasis middleware (tanpa `Route::domain()`, struktur route maksimal 1 level folder), manajemen Menu + Sort, Permission (Menu Actions & Standalone), Custom Role Builder (tri-state tree matrix), dan Dynamic Sidebar berbasis `spatie/laravel-permission`.
3. **Tenant Onboarding & White-Labeling:** Profil instansi, upload logo, injeksi tema dinamis CSS variables shadcn/ui (`--primary`, `--accent`, `--radius`), dan kamus terminologi per instansi.
4. **Hierarki Organisasi & Delegasi:** Struktur unit kerja bertingkat dengan *Parent ID + Materialized Path* (`path`, `depth`) untuk query cepat tanpa rekursi, manajemen member lokal (`org_members`), serta delegasi wewenang verifikasi sementara (Plt.).
5. **Dynamic Policy & Rule Engine:** Metode absensi per unit/jabatan (GPS+Selfie, WiFi/IP, QR Kiosk, Browser Check-in), pola kerja (jam tetap, shift, flexi-time), dan JSON Schema form builder.
6. **Approval Workflow & Audit Trail:** Rantai persetujuan berjenjang, jejak audit *immutable*, dan ekspor laporan tabular (CSV/PDF).

---

## 2. Aturan Arsitektur & Operasional (Sesuai `AGENTS.md`)

### 2.1 Table Prefix Conventions
Seluruh tabel wajib memakai prefix domain dalam bentuk jamak (*plural* `snake_case`), kecuali pivot urut alfabet:
* `sys_` : Tabel orkestrasi SaaS di Central DB (`sys_tenants`, `sys_domains`, `sys_tenant_user`).
* `auth_` : Autentikasi, SSO & token (`auth_otp_tokens`, `auth_sso_tokens`, `auth_trusted_devices`). *Catatan:* Tabel bawaan autentikasi Laravel (`users`, `sessions`, `password_reset_tokens`) tetap standar.
* `rbac_` : Hak akses & menu navigasi di Central DB maupun Tenant DB (`rbac_roles`, `rbac_permissions`, `rbac_role_has_permissions`, `rbac_model_has_roles`, `rbac_model_has_permissions`, `rbac_menus`, `rbac_menu_roles`, `rbac_menu_actions`). Primary key tabel RBAC memakai **ULID**, sedangkan kolom `model_id` pada tabel pivot Spatie wajib **`unsignedBigInteger`** (menyesuaikan `users.id`).
* `org_` : Struktur organisasi & pegawai di Tenant DB (`org_units`, `org_members`, `org_delegations`).
* `att_` : Presensi, kebijakan & pengajuan di Tenant DB (`att_policies`, `att_attendances`, `att_leave_requests`).
* `wf_` : Workflow & persetujuan di Tenant DB (`wf_workflows`, `wf_approvals`).
* `log_` : Jejak audit aktivitas (`log_audit_trails`).
* Setiap Model Eloquent yang memakai prefix wajib mendefinisikan `protected $table = '...';`.

### 2.2 Arsitektur 3-Layer Routing (Berbasis Middleware, Maksimal 1 Level Folder)
* **Tanpa `Route::domain()`:** Isolasi domain central dan tenant dipertahankan sepenuhnya menggunakan middleware agar kompatibel dengan semua entri `config('tenancy.central_domains')` (`presentify.test`, `app.presentify.test`, `localhost`, `127.0.0.1`) dan lingkungan testing Pest.
* **Pembagian 3 Konteks & Batas Kedalaman Folder (Flat 1-Level):**
  - `routes/central/*.php` — hanya bisa diakses di Central domain (`PreventAccessFromTenantDomains`). Contoh: `home.php`, `auth.php`, `workspace.php`, `tenancy.php`.
  - `routes/tenant/*.php` — hanya bisa diakses di subdomain Tenant (`InitializeTenancyBySubdomain`, `PreventAccessFromCentralDomains`). Contoh: `auth.php`, `organization.php`, `attendance.php`, `workflow.php`.
  - `routes/universal/*.php` — bisa diakses di Central **dan** Tenant (`InitializeTenancyBySubdomainIfApplicable`, `EnsureKnownDomain`, `auth`). Contoh: `dashboard.php`, `settings.php`, `rbac.php`.
  - **Aturan Flat Folder:** Dilarang membuat subfolder bertingkat di dalam `routes/central/`, `routes/tenant/`, atau `routes/universal/` (maksimal 1 level folder). `App\Support\RouteLoader::load($context)` hanya memuat file `*.php` langsung pada level pertama folder konteks tersebut.
* **Anti Bentrok URI:** Karena tidak memakai `Route::domain()`, route dengan URI yang sama untuk Central dan Tenant (seperti `/dashboard`, `/settings/*`, `/rbac/*`) wajib ditempatkan di `routes/universal/`.

### 2.3 Thin Controller & OOP Service Layer
* **Thin Controller:** Controller di `app/Http/Controllers/{Central|Tenant|Universal}/<Domain>/` hanya melakukan 3 tugas: validasi via `FormRequest`, memanggil class `Service`, dan mengembalikan response (`Inertia::render` atau `redirect`). Dilarang meletakkan query Eloquent kompleks, `DB::transaction`, atau logika bisnis di Controller.
* **OOP Service Layer:** Seluruh logika bisnis, transaksi database, kalkulasi, dan orkestrasi model ditempatkan di `app/Services/<Domain>/<Name>Service.php` dengan prinsip *Single Responsibility*.
* **FormRequest:** Seluruh validasi input dipisahkan ke class FormRequest di `app/Http/Requests/{Central|Tenant|Universal}/<Domain>/<Action>Request.php`.

### 2.4 Aturan Operasional Eksekusi
* **Eksekusi 1 Langkah per Giliran:** Agen hanya mengerjakan 1 langkah checklist per turn, melaporkan hasil kerja, dan menunggu konfirmasi/instruksi pengguna sebelum lanjut.
* **Anti-Slop UI (Mode 1):** Setiap halaman React + TypeScript dibangun dengan komponen **shadcn/ui**, Wayfinder route helpers, hierarki visual yang bersih, *tabular-nums*, serta state lengkap (*empty*, *loading*, *validation*).
* **Verifikasi Mutlak Sebelum Catat ke CHANGELOG.md:** Wajib menjalankan dan memastikan lulus:
  1. `php artisan test` (backend unit & feature tests)
  2. `npm run build` (frontend compilation & TypeScript check)
  Jika dikerjakan di lingkungan sandbox tanpa PHP/Composer/internet, status verifikasi dicatat secara jujur di `docs/CHANGELOG.md` agar pengguna dapat menjalankan verifikasi akhir di lokal setelah mengekstrak overlay `.zip`.

---

## 3. Rencana Checklist Langkah-Langkah Kecil

### Milestone 1: Multi-Tenant Foundation & Central Database (PRD F1.1)
Fokus: Menyiapkan Central DB, mekanisme multi-koneksi database, autentikasi terpusat, dan SSO handshake.

- [x] **Langkah 1.1: Migrasi & Model Central Database (`sys_*`, `auth_*`, `sys_domains`)** ✅ 2026-11-24 (diperbarui: migrasi ke stancl/tenancy)
  * Package: `stancl/tenancy` v3.10.1 diinstall dan dikonfigurasi.
  * Migrasi Central DB: `sys_tenants`, `sys_domains`, `sys_tenant_user`, `auth_otp_tokens`, `auth_sso_tokens`.
  * Model Eloquent: `Tenant`, `Domain`, `TenantUser`, `OtpToken`, `SsoToken`, `User`.
  * Config & Provider: `config/tenancy.php`, `app/Providers/TenancyServiceProvider.php`, `routes/tenant.php`, `CentralDatabaseSeeder`.

- [x] **Langkah 1.2: Central Auth dengan OTP Email 6-Digit** ✅ 2026-11-24
  * FormRequest: `SendOtpRequest`, `VerifyOtpRequest`.
  * OOP Service: `OtpService` (6-digit SHA-256, TTL 5 menit, rate limit 3/menit, invalidasi token lama).
  * Notification & Controller: `OtpCodeNotification`, `OtpVerificationController`.
  * Frontend: `auth/login.tsx` (tab switcher Password/OTP) & `auth/verify-otp.tsx`.
  * Feature test: `tests/Feature/Auth/OtpVerificationTest.php` (10 test cases).

- [x] **Langkah 1.3: One-Time SSO Token & Handshake Subdomain** ✅ 2026-11-25
  * FormRequest & Service: `SsoCallbackRequest`, `SsoService` (64-hex SHA-256, TTL 60 detik, transaksi atomik `lockForUpdate()`, replay protection, `last_accessed_at`).
  * Controller & Response: `SsoCallbackController`, `LoginResponse` (terikat ke Fortify `LoginResponse` & `TwoFactorLoginResponse`).
  * Middleware & Tenancy Alignment: `TenancyServiceProvider`, `CentralConnection` pada model Central DB, `InitializeTenancyBySubdomainIfApplicable` + `ScopeSessions`.
  * Feature test: `tests/Feature/Auth/SsoHandshakeTest.php` (13 test cases).

- [x] **Langkah 1.4: Multi-Tenant Workspace Picker, SP-Initiated SSO (`state`) & Signed Single Logout (SLO)** ✅ 2026-11-26
  * **SP-Initiated SSO (saat guest langsung membuka subdomain tenant):**
    - Konfigurasi `$middleware->redirectGuestsTo(...)` di `bootstrap/app.php` agar guest di subdomain tenant otomatis diarahkan ke `tenant.sso.login` (bukan `/login` lokal).
    - Endpoint Tenant `GET /sso/login` (`SsoClientController@login` / `SsoCallbackController`): buat `state` random 40 karakter di sesi tenant lalu redirect penuh via `Inertia::location()` ke Central `GET /auth/sso/authorize?domain=...&state=...`.
    - Endpoint Central `GET /auth/sso/authorize` (`auth` + `throttle:30,1`): validasi domain & keanggotaan aktif `sys_tenant_user`, terbitkan one-time SSO token, lalu redirect ke `{tenant}/sso/callback?token=...&state=...`.
    - Validasi `hash_equals($expectedState, $state)` pada `SsoCallbackController` untuk alur SP-initiated (mencegah *login CSRF*) sembari tetap mendukung alur IdP-initiated dari Central login/Workspace Picker.
  * **Multi-Tenant Workspace Picker & Switcher:**
    - FormRequest: `App\Http\Requests\Central\Auth\SwitchTenantRequest`.
    - OOP Service: `App\Services\Auth\WorkspaceService` (mengambil daftar instansi aktif user dari `sys_tenant_user` dan inisiasi perpindahan tenant via `SsoService`).
    - Thin Controller: `App\Http\Controllers\Central\Auth\WorkspaceController` (render halaman pemilih instansi `workspace.picker` dan handle switch tenant).
    - Frontend: Halaman Inertia React `auth/tenant-picker.tsx` (kartu instansi shadcn/ui) dan komponen dropdown *Instansi Switcher* pada `app-sidebar.tsx`.
  * **Signed Single Logout (SLO):**
    - Endpoint Tenant `POST /sso/logout` (`auth`): hapus sesi lokal tenant, lalu `Inertia::location(URL::temporarySignedRoute('central.sso.logout', now()->addMinute()))`.
    - Endpoint Central `GET /auth/sso/logout` (`signed`): hapus sesi central (`Auth::guard('web')->logout()`, invalidate & regenerate CSRF token), lalu redirect ke `login`.
  * Feature test: SP-initiated redirect & validasi `state`, Workspace Picker (2+ tenant), switch instansi, dan Signed SLO (termasuk penolakan unsigned/expired SLO URL).

---

### Milestone 2: 3-Layer Middleware Routing, Universal Spatie RBAC & Dynamic Sidebar (PRD F1.1 Lanjutan)
Fokus: Memisahkan route menjadi 3 konteks berbasis middleware (flat 1-level folder) dan membangun skema RBAC Universal berbasis `spatie/laravel-permission` + `Menu`/`MenuAction` + React shadcn/ui.

- [x] **Langkah 2.1: Arsitektur 3-Layer Routing Berbasis Middleware (Flat 1-Level Folder)** ✅ 2026-11-26
  * Helper `App\Support\RouteLoader` untuk memuat file `*.php` langsung di level pertama `routes/{context}/` (tanpa rekursi subfolder).
  * Middleware `App\Http\Middleware\Universal\EnsureKnownDomain` (memastikan host adalah `central_domains` atau tenant terinisialisasi, selain itu `404`).
  * Penataan ulang route tanpa `Route::domain()`:
    - `routes/web.php`: grup Central (`PreventAccessFromTenantDomains` -> `RouteLoader::load('central')`) dan grup Universal (`EnsureKnownDomain` -> `RouteLoader::load('universal')`).
    - `routes/tenant.php`: grup Tenant (`web`, `InitializeTenancyBySubdomain`, `PreventAccessFromCentralDomains` -> `RouteLoader::load('tenant')`).
    - File 1-level: `routes/central/home.php`, `routes/central/auth.php`, `routes/tenant/auth.php`, `routes/universal/dashboard.php`, `routes/universal/settings.php`, `routes/universal/rbac.php`.
  * Registrasi alias middleware Spatie (`role`, `permission`, `role_or_permission`) di `bootstrap/app.php`.
  * Feature test isolasi 3-layer route (Central ditolak di subdomain tenant, Tenant ditolak di central, Universal berjalan di keduanya dan menolak subdomain tak dikenal).

- [x] **Langkah 2.2: Fondasi Spatie RBAC, Migrasi Central & Tenant (`rbac_*`), Model & Seeder** ✅ 2026-11-26
  * Dependensi `spatie/laravel-permission` (^6.x / ^8.x sesuai Laravel 13) di `composer.json` dan publikasi `config/permission.php` dengan nama tabel `rbac_*` (`rbac_roles`, `rbac_permissions`, `rbac_model_has_roles`, `rbac_model_has_permissions`, `rbac_role_has_permissions`) dan model kustom `App\Models\Role` & `App\Models\Permission`.
  * Migrasi identik di **Central DB** (`database/migrations/`) dan **Tenant DB** (`database/migrations/tenant/`):
    - Tabel Spatie `rbac_*` (PK ULID untuk `rbac_roles` & `rbac_permissions`, `group` nullable pada `rbac_permissions`, serta `model_id` bertipe `unsignedBigInteger` menyesuaikan `users.id`).
    - Tabel menu `rbac_menus` (ULID, `parent_id`, `sort_num`, `icon`, `label_name`, `controller_name`, `route_name`, `url`, `permission_prefix`, `is_active`, `is_divider`), `rbac_menu_roles`, dan `rbac_menu_actions` (ULID, `menu_id`, `action`, `label`, `permission_name`, `route_name`, `order`).
  * Model Eloquent (tanpa `CentralConnection` agar otomatis mengikuti koneksi aktif): `Role`, `Permission`, `Menu`, `MenuAction`.
  * Trait `App\Concerns\HasTenantAwareRoles` pada `App\Models\User` (membungkus Spatie `HasRoles` agar relasi `roles()` & `permissions()` otomatis query ke koneksi DB aktif meskipun `User` memakai `CentralConnection`).
  * OOP Service `App\Services\Rbac\MenuService` (cache per konteks & user: `menus:{tenant_id|central}:{user_id}`, invalidasi per user & per role).
  * Seeder: trait `Database\Seeders\Concerns\SeedsCrudMenus`, `CentralRolesAndPermissionsSeeder`, `CentralMenuSeeder`, `TenantRolesAndPermissionsSeeder` (5 role bawaan: Admin Tenant, Atasan/Verifikator, Pegawai/Anggota, Akun Kiosk + Superadmin), dan `TenantMenuSeeder`, serta aktifkan `Jobs\SeedDatabase` di `TenancyServiceProvider` dengan `seeder_parameters` menunjuk `TenantDatabaseSeeder`.

- [x] **Langkah 2.3: Backend Universal RBAC (FormRequest, Service, Thin Controller) & Shared Inertia Props** ✅ 2026-11-26
  * FormRequest di `app/Http/Requests/Universal/Rbac/`: `MenuRequest`, `MenuReorderRequest`, `MenuActionRequest`, `PermissionRequest`, `RoleRequest`.
  * OOP Service di `app/Services/Rbac/`: `MenuManagementService` (CRUD, `toggleStatus`, `reorder` atomik, invalidasi cache), `MenuActionManagementService` (CRUD + sinkronisasi otomatis ke `rbac_permissions`), `PermissionManagementService` (CRUD standalone permissions + clear cache Spatie), `RoleManagementService` (CRUD + sync `rbac_menus`, `rbac_menu_actions`, dan standalone `rbac_permissions` + invalidasi cache).
  * Thin Controller di `app/Http/Controllers/Universal/Rbac/`: `MenuController`, `MenuActionController`, `PermissionController`, `RoleController` — semua route di `routes/universal/rbac.php` dilindungi middleware eksplisit `permission:menus.*`, `permission:permissions.*`, `permission:roles.*`.
  * Pembaruan `HandleInertiaRequests`: men-share `auth.permissions` (daftar nama izin) dan `auth.menus` (pohon menu aktif dari `MenuService`) ke seluruh halaman React.
  * Feature test RBAC lengkap di konteks Central dan Tenant (isolasi izin antar koneksi, CRUD menu/action/permission/role, proteksi 403).

- [x] **Langkah 2.4: Frontend React Universal RBAC (shadcn/ui) & Integrasi Dynamic Sidebar** ✅ 2026-11-26
  * Helper/hook React `resources/js/hooks/use-permission.ts` (`can(permission)`, `canAny(...)`) dan tipe TypeScript eksplisit di `resources/js/types/rbac.ts`.
  * Halaman Inertia React di `resources/js/pages/universal/rbac/` (memakai komponen **shadcn/ui**, Wayfinder, dan standar Anti-Slop UI):
    - `menu/index.tsx` (tabel hierarki menu, filter pencarian & status, toggle aktif, hapus dengan dialog konfirmasi), `menu/form.tsx` (create/edit menu), `menu/sort.tsx` (pengurutan drag/move parent-child & simpan urutan).
    - `permission/index.tsx` (2 tab: *Menu Actions* dengan accordion per menu + dialog tambah/edit aksi beserta live preview `{prefix}.{action}`, dan *Standalone Permissions* dengan tabel paginasi + dialog CRUD).
    - `role/index.tsx` (daftar role + jumlah user) dan `role/form.tsx` (matriks pohon menu dengan checkbox *tri-state/indeterminate* parent ↔ child ↔ all actions ↔ per-action + grup standalone permissions).
  * Integrasi `AppSidebar` (`resources/js/components/app-sidebar.tsx`): merender `auth.menus` dinamis (termasuk parent-child collapsible & divider) dengan pemetaan ikon Lucide.

---

### Milestone 3: Tenant Onboarding, White-Labeling & Kamus Terminologi (PRD F1.2)
Fokus: Fleksibilitas identitas visual dan bahasa instansi.

- [ ] **Langkah 3.1: Profil Instansi & Pengaturan Identitas**
  * FormRequest: `App\Http\Requests\Tenant\UpdateTenantProfileRequest` & `App\Http\Requests\Tenant\UploadLogoRequest`.
  * OOP Service: `App\Services\Tenant\TenantProfileService` (validasi dan pembaruan data profil di `sys_tenants`, upload & resize logo/favicon instansi ke storage disk).
  * Thin Controller: `App\Http\Controllers\Tenant\TenantProfileController`.

- [ ] **Langkah 3.2: Dynamic White-Label CSS Theme Engine**
  * FormRequest: `App\Http\Requests\Tenant\UpdateThemeConfigRequest`.
  * OOP Service: `App\Services\Tenant\ThemeCustomizerService` (validasi palet warna hex/hsl, pembaruan JSON `theme_config` di `sys_tenants`, dan formatting variabel CSS shadcn/ui `--primary`, `--accent`, `--radius`).
  * Thin Controller: `App\Http\Controllers\Tenant\ThemeController`.
  * Frontend: Injeksi variabel CSS warna ke root document via `app-shell.tsx` / `AppLayout` React.

- [ ] **Langkah 3.3: Kamus Terminologi Dinamis (Terminology Engine)**
  * FormRequest: `App\Http\Requests\Tenant\UpdateTerminologyRequest`.
  * OOP Service: `App\Services\Tenant\TerminologyService` (manajemen kamus istilah JSON `terminology` pada `sys_tenants`, sinkronisasi istilah default, dan penyedia kamus ke frontend).
  * Thin Controller: `App\Http\Controllers\Tenant\TerminologyController`.
  * Frontend: Custom hook React `useTerminology(key, defaultText)`.

---

### Milestone 4: Struktur Organisasi (Materialized Path) & Delegasi Wewenang (PRD F1.3)
Fokus: Efisiensi query hierarki organisasi dan mekanisme Plt.

- [ ] **Langkah 4.1: Migrasi Unit Kerja Materialized Path & Service Path (`org_units`)**
  * Migrasi tabel `org_units` (`id`, `parent_id`, `path`, `depth`, `name`, `code`, `leader_member_id`).
  * Model Eloquent `Unit` (`protected $table = 'org_units';`).
  * OOP Service: `App\Services\Organization\UnitHierarchyService` (kalkulasi otomatis `path` & `depth`, query scope `descendantsOf()` & `ancestorsOf()` dalam 1 query SQL).
  * Observer `UnitObserver` yang memicu `UnitHierarchyService` saat create/update/move unit.

- [ ] **Langkah 4.2: Unit Hierarchy Management UI (Tree View)**
  * FormRequest: `StoreUnitRequest`, `MoveUnitRequest`.
  * OOP Service: `UnitManagementService` (CRUD unit, validasi circular dependency).
  * Thin Controller: `App\Http\Controllers\Tenant\Organization\UnitController`.
  * Frontend: Komponen visualisasi pohon organisasi (*Collapsible Tree View*) dengan shadcn/ui.

- [ ] **Langkah 4.3: Manajemen Member Instansi & Penempatan Unit (`org_members`)**
  * Migrasi tabel `org_members` (`id`, `global_user_id`, `unit_id`, `nip`, `device_fingerprint`, `is_active`).
  * Model Eloquent `Member` (`protected $table = 'org_members';`).
  * FormRequest: `StoreMemberRequest`, `UpdateMemberUnitRequest`.
  * OOP Service: `MemberManagementService` (pemetaan `global_user_id`, validasi NIP unik lokal, penempatan `unit_id`, penugasan role tenant).
  * Thin Controller: `App\Http\Controllers\Tenant\Organization\MemberController`.

- [ ] **Langkah 4.4: Modul Delegasi Sementara (Plt. / Wewenang Sementara) (`org_delegations`)**
  * Migrasi tabel `org_delegations` (`from_member_id`, `to_member_id`, `unit_id`, `starts_at`, `ends_at`, `notes`, `is_active`).
  * Model Eloquent `Delegation` (`protected $table = 'org_delegations';`).
  * FormRequest: `StoreDelegationRequest`.
  * OOP Service: `DelegationService` (validasi rentang tanggal, evaluator scope wewenang aktif atasan).
  * Thin Controller: `App\Http\Controllers\Tenant\Organization\DelegationController`.

---

### Milestone 5: Dynamic Policy & Rule Engine (PRD F1.4)
Fokus: Konfigurasi metode dan jam kerja adaptif tanpa hardcode.

- [ ] **Langkah 5.1: Migrasi & Model Attendance Policy (`att_policies`)**
  * Migrasi `att_policies`, Model `AttendancePolicy`, FormRequest `StorePolicyRequest`, Service `PolicyManagementService`, Controller `PolicyController`.

- [ ] **Langkah 5.2: Engine Evaluator Jam Kerja & Toleransi Keterlambatan**
  * OOP Service `PolicyEvaluatorService` + unit tests komprehensif untuk jam tetap, shift, flexi-time, dan akumulasi toleransi keterlambatan.

- [ ] **Langkah 5.3: Form Builder Izin Dinamis (JSON Schema)**
  * FormRequest `SaveCustomFieldsSchemaRequest`, Service `CustomFieldBuilderService`, Controller `CustomFieldSchemaController`, dan Dynamic Form Renderer di React.

---

### Milestone 6: Dynamic Approval Workflow & Audit Trail (PRD F1.5)
Fokus: Alur birokrasi persetujuan fleksibel dan akuntabilitas data.

- [ ] **Langkah 6.1: Migrasi & Model Approval Workflow (`wf_*` & `att_leave_requests`)**
  * Migrasi `wf_workflows`, `wf_approvals`, `att_leave_requests`; Model `Workflow`, `Approval`, `LeaveRequest`; Service `WorkflowDefinitionService`; Controller `WorkflowController`.

- [ ] **Langkah 6.2: Workflow Execution Engine & In-App Approvals**
  * FormRequest `ProcessApprovalRequest`, Service `ApprovalExecutionService` (validasi wewenang unit + delegasi aktif), Controller `ApprovalInboxController`.

- [ ] **Langkah 6.3: Immutable Audit Trail & Activity Logger (`log_audit_trails`)**
  * Migrasi `log_audit_trails`, Model `AuditTrail`, Service `AuditTrailService`, Trait/Observer `Auditable`, Controller `AuditLogController`.

- [ ] **Langkah 6.4: Laporan Kehadiran Kustom & Ekspor Data (`att_attendances`)**
  * Migrasi `att_attendances`, Model `Attendance`, FormRequest `AttendanceReportFilterRequest`, Service `AttendanceReportService` (agregasi + ekspor CSV/PDF), Controller `AttendanceReportController`.

---

## 4. Strategi Pengujian & Verifikasi

Untuk setiap langkah yang dikerjakan:
1. **Pengujian Unit & Fitur Backend:**
   ```bash
   php artisan test
   ```
   Seluruh test suite Pest harus lulus 100% tanpa error sebelum langkah dinyatakan selesai.
2. **Pengujian Frontend & Kompilasi Aset:**
   ```bash
   npm run build
   ```
   Kompilasi TypeScript, Tailwind CSS, dan bundling Vite harus lulus tanpa error kompilasi.
3. **Pencatatan Progres:**
   Setelah lulus uji, barulah checklist di dokumen ini diperbarui (`[x]`) dan kemajuan dicatat secara rapi ke `docs/CHANGELOG.md`.
