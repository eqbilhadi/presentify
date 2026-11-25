# Rencana Implementasi Bertahap: Fase 1 (Core System, Dynamic Engine & Workflow)

Dokumen ini adalah rencana teknis pelaksanaan Fase 1 untuk proyek **Presentify (Sistem Presensi Dinamis Multi-Tenant)** berdasarkan spesifikasi pada `docs/PRD.md` dan pedoman arsitektur operasional `AGENTS.md`.

---

## 1. Ringkasan & Tujuan Fase 1

Fase 1 meletakkan fondasi inti sistem Presensi Dinamis Multi-Tenant Presentify yang siap produksi, mencakup:
1. **Multi-Tenancy & Central SSO:** Pemisahan Central DB & Database-per-Tenant, login terpusat (Password + OTP Email 6-digit), SSO Token Handshake, Workspace Picker, dan Single Logout (SLO).
2. **Dynamic Roles & Menu Builder:** 5 Role standar, 2-tier permission (menu-bound + global), master registry menu dengan kemampuan modifikasi kustom oleh Admin Tenant.
3. **Tenant Onboarding & White-Labeling:** Profil instansi, upload logo, injeksi tema dinamis CSS variables shadcn/ui (`--primary`, `--accent`, `--radius`), dan kamus terminologi per instansi.
4. **Hierarki Organisasi & Delegasi:** Struktur unit kerja bertingkat dengan *Parent ID + Materialized Path* (`path`, `depth`) untuk query cepat tanpa rekursi, serta delegasi wewenang verifikasi sementara (Plt.).
5. **Dynamic Policy & Rule Engine:** Metode absensi per unit/jabatan (GPS+Selfie, WiFi/IP, QR Kiosk, Browser Check-in), pola kerja (jam tetap, shift, flexi-time), dan JSON Schema form builder.
6. **Approval Workflow & Audit Trail:** Rantai persetujuan berjenjang, jejak audit *immutable*, dan ekspor laporan tabular (CSV/PDF).

---

## 2. Aturan Arsitektur & Operasional (Sesuai AGENTS.md)

### 2.1 Table Prefix Conventions
Seluruh tabel wajib memakai prefix domain dalam bentuk jamak (*plural* `snake_case`), kecuali pivot urut alfabet:
* `sys_` : Tabel orkestrasi SaaS di Central DB (`sys_tenants`, `sys_domains`, `sys_tenant_user`).
* `auth_` : Autentikasi, SSO & token (`auth_otp_tokens`, `auth_sso_tokens`, `auth_trusted_devices`). *Catatan:* Tabel bawaan autentikasi Laravel (`users`, `sessions`, `password_reset_tokens`) tetap standar.
* `rbac_` : Hak akses & menu navigasi (`rbac_roles`, `rbac_permissions`, `rbac_menus`, `rbac_role_has_permissions`, `rbac_model_has_roles`).
* `org_` : Struktur organisasi & pegawai (`org_units`, `org_members`, `org_delegations`).
* `att_` : Presensi, kebijakan & pengajuan (`att_policies`, `att_attendances`, `att_leave_requests`).
* `wf_` : Workflow & persetujuan (`wf_workflows`, `wf_approvals`).
* `log_` : Jejak audit aktivitas (`log_audit_trails`).
* Setiap Model Eloquent yang memakai prefix wajib mendefinisikan `protected $table = '...';`.

### 2.2 Thin Controller & OOP Service Layer
* **Thin Controller:** Controller di `app/Http/Controllers/<Domain>/` hanya melakukan 3 tugas: validasi via `FormRequest`, memanggil class `Service`, dan mengembalikan response (`Inertia::render` atau `redirect`). Dilarang meletakkan query Eloquent kompleks, `DB::transaction`, atau logika bisnis di Controller.
* **OOP Service Layer:** Seluruh logika bisnis, transaksi database, kalkulasi, dan orkestrasi model ditempatkan di `app/Services/<Domain>/<Name>Service.php` dengan prinsip *Single Responsibility*.
* **FormRequest:** Seluruh validasi input dipisahkan ke class FormRequest di `app/Http/Requests/<Domain>/<Action>Request.php`.

### 2.3 Aturan Operasional Eksekusi
* **Eksekusi 1 Langkah per Giliran:** Agen hanya mengerjakan 1 langkah checklist per turn, melaporkan hasil kerja, dan menunggu konfirmasi/instruksi pengguna sebelum lanjut.
* **Verifikasi Mutlak Sebelum Catat ke CHANGELOG.md:** Wajib menjalankan dan memastikan lulus:
  1. `php artisan test` (backend unit & feature tests)
  2. `npm run build` (frontend compilation & TypeScript check)
  Dilarang mencatat ke `docs/CHANGELOG.md` jika salah satu dari perintah di atas gagal.

---

## 3. Rencana Checklist Langkah-Langkah Kecil

### Milestone 1: Multi-Tenant Foundation & Central Database (PRD F1.1)
Fokus: Menyiapkan Central DB, mekanisme multi-koneksi database, autentikasi terpusat, dan SSO handshake.

- [x] **Langkah 1.1: Migrasi & Model Central Database (`sys_*`, `auth_*`, `sys_domains`)** ✅ 2026-11-24 (diperbarui: migrasi ke stancl/tenancy)
  * Package: `stancl/tenancy` v3.10.1 diinstall dan dikonfigurasi.
  * Migrasi Central DB:
    - `2019_09_15_000010_create_sys_tenants_table.php` — `sys_tenants` (UUID PK, `data` JSON untuk virtual columns)
    - `2019_09_15_000020_create_sys_domains_table.php` — `sys_domains` (FK ke `sys_tenants.id`)
    - `2026_11_24_000002_create_sys_tenant_user_table.php` — `sys_tenant_user` (FK ke `sys_tenants.id` string UUID)
    - `2026_11_24_000003_create_auth_otp_tokens_table.php` — `auth_otp_tokens`
    - `2026_11_24_000004_create_auth_sso_tokens_table.php` — `auth_sso_tokens`
  * Model Eloquent di `app/Models/`:
    - `Tenant` extends `BaseTenant` + `HasDatabase` + `HasDomains` (`protected $table = 'sys_tenants';`); virtual columns (`theme_config`, `terminology`, `logo_path`, `favicon_path`) disimpan di JSON `data`.
    - `Domain` extends `BaseDomain` (`protected $table = 'sys_domains';`)
    - `TenantUser` (`protected $table = 'sys_tenant_user';`)
    - `OtpToken` (`protected $table = 'auth_otp_tokens';`)
    - `SsoToken` (`protected $table = 'auth_sso_tokens';`)
    - `User` — relasi ke `Tenant`, `TenantUser`, `OtpToken`, `SsoToken`.
  * Config: `config/tenancy.php` dikonfigurasi dengan model kustom, `central_domains`, dan prefix DB `presentify_tenant_`.
  * Provider: `app/Providers/TenancyServiceProvider.php` terdaftar, mapRoutes ke `routes/tenant.php`.
  * Routes: `routes/tenant.php` menggunakan `InitializeTenancyBySubdomain` + `PreventAccessFromCentralDomains`.
  * Seeder: `CentralDatabaseSeeder` membuat 2 tenant dengan domain subdomain dan data tema/terminologi.

- [x] **Langkah 1.2: Central Auth dengan OTP Email 6-Digit** ✅ 2026-11-24
  * FormRequest: `App\Http\Requests\Auth\SendOtpRequest` & `App\Http\Requests\Auth\VerifyOtpRequest`. [x]
  * OOP Service: `App\Services\Auth\OtpService` (generasi OTP kriptografis 6-digit, hashing SHA-256 di `auth_otp_tokens`, TTL 5 menit, rate limiting 3 percobaan/menit via `RateLimiter`, invalidasi token lama saat generate baru). [x]
  * Notification: `App\Notifications\Auth\OtpCodeNotification` (mail notification dengan plain code). [x]
  * Thin Controller: `App\Http\Controllers\Auth\OtpVerificationController` (3 method: `show`, `send`, `store` — delegasi ke `OtpService`, otomatis login ke sesi via `Auth::login()`). [x]
  * Routes (`routes/web.php`): `GET /auth/otp`, `POST /auth/otp`, `POST /auth/otp/send` (semua middleware `guest`). [x]
  * Frontend:
    - [x] `resources/js/pages/auth/login.tsx`: Integrasi switcher metode login ("Kata Sandi" & "Kode OTP Email" dengan email input tersinkronisasi dan tombol kirim OTP).
    - [x] `resources/js/pages/auth/verify-otp.tsx`: Form input OTP 6-digit dengan shadcn `InputOTP`, form kirim ulang kode (`send.form`), dan tautan navigasi kembali ke login.
  * Feature test: `tests/Feature/Auth/OtpVerificationTest.php` — 10 test cases (generate, verify, expired, used, rate limit, send route notification, show route accessible, no-session rejection, invalidate previous token, authenticates user & session regeneration). [x]

- [x] **Langkah 1.3: One-Time SSO Token & Handshake Subdomain** ✅ 2026-11-25
  * FormRequest: `App\Http\Requests\Auth\SsoCallbackRequest`. [x]
  * OOP Service: `App\Services\Auth\SsoService` (penerbitan one-time token 64-hex / SHA-256 di `auth_sso_tokens` dengan TTL 60 detik, transaksi atomik dengan `lockForUpdate()`, penghapusan token instan setelah verifikasi/gagal, pembaruan `last_accessed_at` di `sys_tenant_user`, pembuatan sesi lokal, dan resolver post-login). [x]
  * Thin Controller & Response: `App\Http\Controllers\Auth\SsoCallbackController` (menerima callback `/sso/callback` di subdomain, memanggil `SsoService::validateAndLogin()`, redirect ke dashboard tenant) dan `App\Http\Responses\LoginResponse` (terikat ke Fortify `LoginResponse` & `TwoFactorLoginResponse`) serta pembaruan `OtpVerificationController@store`. [x]
  * Middleware & Tenancy Alignment: registrasi `App\Providers\TenancyServiceProvider` di `bootstrap/providers.php`, trait `CentralConnection` pada model Central DB (`User`, `TenantUser`, `OtpToken`, `SsoToken`), `InitializeTenancyBySubdomainIfApplicable` + `ScopeSessions` di pipeline `web`, dan penyesuaian format domain subdomain di `CentralDatabaseSeeder`. [x]
  * Feature test: `tests/Feature/Auth/SsoHandshakeTest.php` — 13 test cases (redirect otomatis 1 tenant pada Password & OTP login, 409 `X-Inertia-Location`, replay protection, expired token, cross-tenant rejection, revoked membership, central domain 404 isolation, callback validation, `app.*` subdomain URL resolution). [x]

- [x] **Langkah 1.4: Multi-Tenant Workspace Picker & Single Logout (SLO)** ✅ 2026-11-25
  * FormRequest: `App\Http\Requests\Auth\SwitchTenantRequest`. [x]
  * OOP Service: `App\Services\Auth\WorkspaceService` (mengambil daftar instansi aktif user dari `sys_tenant_user`, inisiasi perpindahan tenant via `SsoService`, dan Single Logout / SLO). [x]
  * Thin Controller: `App\Http\Controllers\Auth\WorkspaceController` (render halaman pemilih instansi, switch tenant, dan handle SLO). [x]
  * Frontend: Halaman Inertia React `auth/tenant-picker.tsx` (kartu instansi shadcn/ui & tombol SLO). [x]
  * Feature test: `tests/Feature/Auth/WorkspacePickerTest.php` (11 test cases). [x]

---

### Milestone 2: Dynamic Roles, 2-Tier Permission & Sidebar Menu (PRD F1.1 Lanjutan)
Fokus: Arsitektur otorisasi modular di level database tenant.

- [ ] **Langkah 2.1: Migrasi & Model Tenant RBAC (`org_members` & `rbac_*`)**
  * Migrasi Tenant DB: `org_members`, `rbac_menus`, `rbac_roles`, `rbac_permissions`, `rbac_role_has_permissions`.
  * Model Eloquent di `app/Models/`:
    - `Member` (`protected $table = 'org_members';`)
    - `Menu` (`protected $table = 'rbac_menus';`)
    - `Role` (`protected $table = 'rbac_roles';`)
    - `Permission` (`protected $table = 'rbac_permissions';`)
  * Seeder 5 role bawaan: Superadmin SaaS, Admin Tenant, Atasan/Verifikator, Pegawai/Anggota, Akun Kiosk beserta matriks izin default di `rbac_role_has_permissions`.

- [ ] **Langkah 2.2: Master Registry Menu & Non-Destructive Sync**
  * File konfigurasi master menu sistem `config/presentify-menus.php`.
  * OOP Service: `App\Services\Rbac\MenuSyncService` (sinkronisasi menu bawaan sistem ke tabel `rbac_menus` pada DB tenant secara *non-destructive upsert* tanpa mengubah `custom_label`, `icon`, atau `sort_order` yang telah diatur tenant).
  * Artisan Command `tenants:sync-menus` yang memanggil `MenuSyncService`.

- [ ] **Langkah 2.3: Custom Role Builder UI & 2-Tier Permission Engine**
  * FormRequest: `App\Http\Requests\Rbac\StoreRoleRequest` & `App\Http\Requests\Rbac\UpdateRolePermissionsRequest`.
  * OOP Service: `App\Services\Rbac\RoleBuilderService` (transaksi penyimpanan custom role, sinkronisasi relasi `rbac_role_has_permissions`, dan kalkulasi permission 2-tier: menu-bound `menu_id != null` vs global `menu_id = null`).
  * Thin Controller: `App\Http\Controllers\Rbac\RoleController` (delegasi ke `RoleBuilderService`, render `pages/settings/roles/index.tsx` & `role-editor.tsx`).
  * Middleware & Gate otorisasi untuk pengecekan hak akses menu-bound dan global permission.

- [ ] **Langkah 2.4: Dynamic Sidebar Menu Editor & Layout Integration**
  * FormRequest: `App\Http\Requests\Rbac\UpdateMenuLayoutRequest`.
  * OOP Service: `App\Services\Rbac\SidebarMenuService` (reordering menu, hierarki parent-child, dan penyaringan menu yang boleh dilihat berdasarkan role user aktif).
  * Thin Controller: `App\Http\Controllers\Rbac\MenuEditorController` (panggil `SidebarMenuService`, render halaman editor menu di `pages/settings/menus/index.tsx`).
  * Frontend: Integrasi shared props Inertia ke `app-sidebar.tsx` agar sidebar merender menu dinamis sesuai konfigurasi tenant dan permission user.

---

### Milestone 3: Tenant Onboarding, White-Labeling & Kamus Terminologi (PRD F1.2)
Fokus: Fleksibilitas identitas visual dan bahasa instansi.

- [ ] **Langkah 3.1: Profil Instansi & Pengaturan Identitas**
  * FormRequest: `App\Http\Requests\Tenant\UpdateTenantProfileRequest` & `App\Http\Requests\Tenant\UploadLogoRequest`.
  * OOP Service: `App\Services\Tenant\TenantProfileService` (validasi dan pembaruan data profil di `sys_tenants`, upload & resize logo/favicon instansi ke storage disk).
  * Thin Controller: `App\Http\Controllers\Tenant\TenantProfileController` (validasi input via FormRequest, panggil `TenantProfileService`, redirect dengan pesan sukses).

- [ ] **Langkah 3.2: Dynamic White-Label CSS Theme Engine**
  * FormRequest: `App\Http\Requests\Tenant\UpdateThemeConfigRequest`.
  * OOP Service: `App\Services\Tenant\ThemeCustomizerService` (validasi palet warna hex/hsl, pembaruan JSON `theme_config` di `sys_tenants`, dan formatting variabel CSS shadcn/ui `--primary`, `--accent`, `--radius`).
  * Thin Controller: `App\Http\Controllers\Tenant\ThemeController` (delegasi ke `ThemeCustomizerService`).
  * Frontend: Injeksi variabel CSS warna ke root document via `app-shell.tsx` / `AppLayout` React.

- [ ] **Langkah 3.3: Kamus Terminologi Dinamis (Terminology Engine)**
  * FormRequest: `App\Http\Requests\Tenant\UpdateTerminologyRequest`.
  * OOP Service: `App\Services\Tenant\TerminologyService` (manajemen kamus istilah JSON `terminology` pada `sys_tenants`, sinkronisasi istilah default, dan penyedia kamus ke frontend).
  * Thin Controller: `App\Http\Controllers\Tenant\TerminologyController` (render view pengaturan kamus di settings, simpan perubahan via `TerminologyService`).
  * Frontend: Custom hook React `useTerminology(key, defaultText)` untuk merender teks antarmuka sesuai kamus instansi.

---

### Milestone 4: Struktur Organisasi (Materialized Path) & Delegasi Wewenang (PRD F1.3)
Fokus: Efisiensi query hierarki organisasi dan mekanisme Plt.

- [ ] **Langkah 4.1: Migrasi Unit Kerja Materialized Path & Service Path (`org_units`)**
  * Migrasi tabel `org_units` (`id`, `parent_id`, `path`, `depth`, `name`, `code`, `leader_member_id`).
  * Model Eloquent `Unit` (`protected $table = 'org_units';`).
  * OOP Service: `App\Services\Organization\UnitHierarchyService` (kalkulasi dan regenerasi otomatis kolom `path` misal `/1/4/12/` dan `depth`, serta penyediaan query scope `descendantsOf()` dan `ancestorsOf()` berbasis `LIKE 'path%'` dalam 1 query SQL tanpa rekursi).
  * Observer `UnitObserver` yang memicu `UnitHierarchyService` saat create/update/move unit.

- [ ] **Langkah 4.2: Unit Hierarchy Management UI (Tree View)**
  * FormRequest: `App\Http\Requests\Organization\StoreUnitRequest` & `App\Http\Requests\Organization\MoveUnitRequest`.
  * OOP Service: `App\Services\Organization\UnitManagementService` (transaksi CRUD unit, validasi circular dependency hierarki, dan mutasi unit).
  * Thin Controller: `App\Http\Controllers\Organization\UnitController` (render tree view di `pages/settings/units/index.tsx`, delegasi aksi ke `UnitManagementService`).
  * Frontend: Komponen visualisasi pohon organisasi (*Collapsible Tree View*) dengan shadcn/ui.

- [ ] **Langkah 4.3: Manajemen Member Instansi & Penempatan Unit (`org_members`)**
  * FormRequest: `App\Http\Requests\Organization\StoreMemberRequest` & `App\Http\Requests\Organization\UpdateMemberUnitRequest`.
  * OOP Service: `App\Services\Organization\MemberManagementService` (pemetaan `global_user_id` ke `org_members`, validasi NIP unik lokal, penempatan `unit_id`, dan filter anggota per subtree unit).
  * Thin Controller: `App\Http\Controllers\Organization\MemberController` (panggil `MemberManagementService`, render daftar pegawai).

- [ ] **Langkah 4.4: Modul Delegasi Sementara (Plt. / Wewenang Sementara) (`org_delegations`)**
  * Migrasi tabel `org_delegations` (`from_member_id`, `to_member_id`, `unit_id`, `starts_at`, `ends_at`, `notes`, `is_active`).
  * Model Eloquent `Delegation` (`protected $table = 'org_delegations';`).
  * FormRequest: `App\Http\Requests\Organization\StoreDelegationRequest`.
  * OOP Service: `App\Services\Organization\DelegationService` (validasi rentang tanggal, penetapan delegasi, dan evaluator scope wewenang aktif atasan yang menggabungkan unit asli dan unit delegasi aktif).
  * Thin Controller: `App\Http\Controllers\Organization\DelegationController` (render UI delegasi, delegasi aksi simpan/cabut delegasi ke service).

---

### Milestone 5: Dynamic Policy & Rule Engine (PRD F1.4)
Fokus: Konfigurasi metode dan jam kerja adaptif tanpa hardcode.

- [ ] **Langkah 5.1: Migrasi & Model Attendance Policy (`att_policies`)**
  * Migrasi tabel `att_policies` (`name`, `unit_ids_json`, `work_pattern`, `rules_json`, `custom_fields_schema`).
  * Model Eloquent `AttendancePolicy` (`protected $table = 'att_policies';`).
  * FormRequest: `App\Http\Requests\Attendance\StorePolicyRequest`.
  * OOP Service: `App\Services\Attendance\PolicyManagementService` (CRUD konfigurasi policy, pemetaan policy ke unit, dan seeder template standar: Jam Tetap, Shift, Flexi-Time).
  * Thin Controller: `App\Http\Controllers\Attendance\PolicyController` (delegasi ke `PolicyManagementService`).

- [ ] **Langkah 5.2: Engine Evaluator Jam Kerja & Toleransi Keterlambatan**
  * OOP Service: `App\Services\Attendance\PolicyEvaluatorService` (kalkulasi status presensi: Tepat Waktu, Terlambat dalam toleransi, Terlambat potong poin, Pulang Awal, atau Jam Kurang, serta kalkulasi akumulasi batas toleransi bulanan).
  * Unit tests menyeluruh untuk berbagai skenario jam kerja dan toleransi.

- [ ] **Langkah 5.3: Form Builder Izin Dinamis (JSON Schema)**
  * FormRequest: `App\Http\Requests\Attendance\SaveCustomFieldsSchemaRequest`.
  * OOP Service: `App\Services\Attendance\CustomFieldBuilderService` (validasi format JSON Schema, sanitasi konfigurasi field kustom, dan parsing data izin).
  * Thin Controller: `App\Http\Controllers\Attendance\CustomFieldSchemaController` (delegasi ke `CustomFieldBuilderService`).
  * Frontend: Dynamic form renderer di React yang membaca JSON Schema dan merender input teks, tanggal, select, atau upload lampiran.

---

### Milestone 6: Dynamic Approval Workflow & Audit Trail (PRD F1.5)
Fokus: Alur birokrasi persetujuan fleksibel dan akuntabilitas data.

- [ ] **Langkah 6.1: Migrasi & Model Approval Workflow (`wf_*` & `att_leave_requests`)**
  * Migrasi tabel: `wf_workflows`, `wf_approvals`, dan `att_leave_requests`.
  * Model Eloquent di `app/Models/`:
    - `Workflow` (`protected $table = 'wf_workflows';`)
    - `Approval` (`protected $table = 'wf_approvals';`)
    - `LeaveRequest` (`protected $table = 'att_leave_requests';`)
  * FormRequest: `App\Http\Requests\Workflow\StoreWorkflowRequest` & `App\Http\Requests\Attendance\SubmitLeaveRequest`.
  * OOP Service: `App\Services\Workflow\WorkflowDefinitionService` (definisi rantai step persetujuan bertingkat berdasarkan hierarki unit atau role).
  * Thin Controller: `App\Http\Controllers\Workflow\WorkflowController` (delegasi ke `WorkflowDefinitionService`).

- [ ] **Langkah 6.2: Workflow Execution Engine & In-App Approvals**
  * FormRequest: `App\Http\Requests\Workflow\ProcessApprovalRequest` (aksi Setujui / Tolak beserta catatan).
  * OOP Service: `App\Services\Workflow\ApprovalExecutionService` (transisi status pengajuan, validasi wewenang verifikator dengan memperhitungkan hierarki `org_units` dan delegasi aktif di `org_delegations`, serta auto-routing ke step berikutnya).
  * Thin Controller: `App\Http\Controllers\Workflow\ApprovalInboxController` (render inbox atasan di `pages/approvals/index.tsx`, eksekusi persetujuan via service).

- [ ] **Langkah 6.3: Immutable Audit Trail & Activity Logger (`log_audit_trails`)**
  * Migrasi tabel `log_audit_trails` (`actor_member_id`, `action`, `model_type`, `model_id`, `before_json`, `after_json`, `ip_address`, `user_agent`).
  * Model Eloquent `AuditTrail` (`protected $table = 'log_audit_trails';`).
  * OOP Service: `App\Services\Audit\AuditTrailService` (perekaman jejak aktivitas secara asinkron/aman tanpa mengganggu transaksi utama).
  * Trait/Observer `Auditable` yang memanggil `AuditTrailService` saat model krusial dimutasi.
  * Thin Controller: `App\Http\Controllers\Audit\AuditLogController` (render viewer log aktivitas bagi Admin Tenant).

- [ ] **Langkah 6.4: Laporan Kehadiran Kustom & Ekspor Data (`att_attendances`)**
  * Migrasi tabel `att_attendances` (`member_id`, `policy_id`, `check_in_at`, `check_out_at`, `status`, `geo_data`, `fraud_score`, `is_offline_sync`).
  * Model Eloquent `Attendance` (`protected $table = 'att_attendances';`).
  * FormRequest: `App\Http\Requests\Attendance\AttendanceReportFilterRequest`.
  * OOP Service: `App\Services\Attendance\AttendanceReportService` (query agregasi kehadiran & izin dengan filter unit/periode/status, serta generator ekspor format CSV dan PDF).
  * Thin Controller: `App\Http\Controllers\Attendance\AttendanceReportController` (render view laporan tabular dan stream file ekspor).

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
