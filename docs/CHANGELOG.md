# Changelog

Semua perubahan penting pada proyek Presentify dicatat di sini.

---

## [Unreleased] — Fase 1: Core System, Dynamic Engine & Workflow

### 2026-11-25

#### Ditambah — Langkah 1.4: Multi-Tenant Workspace Picker & Single Logout (SLO)

**Verifikasi:** `phpstan analyse` (file Langkah 1.4, level 7) → 0 errors ✓ | `php artisan test` → 77 passed, 277 assertions ✓ | `tsc --noEmit` → 0 errors ✓ | `npm.cmd run build` → 2333 modules ✓

**Backend (FormRequest, Service, Controller & Routes)**
- `app/Http/Requests/Auth/SwitchTenantRequest.php` — validasi `tenant_id` terhadap daftar tenant aktif milik pengguna pada `sys_tenant_user`.
- `app/Services/Auth/WorkspaceService.php` — `getActiveTenants()`, `switchTenant()` (menerbitkan token SSO melalui `SsoService` dan mengarahkan ke callback subdomain), serta `singleLogout()` (logout guard `web`, invalidasi & regenerasi token sesi, redirect ke login).
- `app/Http/Controllers/Auth/WorkspaceController.php` — Thin Controller (`index`, `switch`, `logout`) yang mendelegasikan proses ke `WorkspaceService` dan merender `auth/tenant-picker`.
- `routes/web.php` — mendaftarkan `GET /workspace` (`workspace.picker`), `POST /workspace/switch` (`workspace.switch`) dengan middleware `auth` + `PreventAccessFromTenantDomains`, serta `POST /logout-all` (`logout.slo`).

**Frontend & Test**
- `resources/js/pages/auth/tenant-picker.tsx` — halaman pemilih instansi menggunakan komponen shadcn/ui `Card`, form switch instansi via Wayfinder (`switchMethod.url()`), state kosong, dan tombol Single Logout (`logoutSlo.url()`).
- `tests/Feature/Auth/WorkspacePickerTest.php` — 11 test cases (tampilan picker multi-tenant, atribut tenant, redirect guest, isolasi 404 di subdomain tenant, switch tenant ke SSO callback, penolakan tenant tidak sah/non-aktif, dan Single Logout), serta penyesuaian `tests/Feature/Auth/SsoHandshakeTest.php`.

#### Diperbarui — Isolasi Route Central dari Subdomain Tenant

**Verifikasi:** `pint --test` ✓ | `phpstan analyse` → 0 errors ✓ | `php artisan test` → 66 passed, 231 assertions ✓ | `npm run build` → 2330 modules ✓

- `app/Http/Middleware/PreventAccessFromTenantDomains.php` *(baru)* — mengembalikan `404` jika route central-only diakses melalui subdomain tenant (dengan pengecualian route `logout` agar pengguna tenant tetap dapat keluar sesi).
- `routes/web.php` & `config/fortify.php` — memasang `PreventAccessFromTenantDomains` pada landing page `/`, endpoint OTP `/auth/otp*`, serta route autentikasi Fortify (`/login`, `/register`, `/forgot-password`, `/reset-password`).
- `tests/Feature/Auth/SsoHandshakeTest.php` — menambah verifikasi bahwa akses ke `/`, `/login`, `/register`, dan `/auth/otp` melalui subdomain tenant mengembalikan `404`.

#### Ditambah — Langkah 1.3: One-Time SSO Token & Handshake Subdomain

**Verifikasi:** `pint --test` (file Langkah 1.3) ✓ | `phpstan analyse` (file terkait, level 7) → 0 errors ✓ | `php artisan test` → 65 passed, 227 assertions ✓ | `npm run build` → 2330 modules ✓

**Service, Request & Controller**
- `app/Services/Auth/SsoService.php` — penerbitan one-time SSO token 64-hex (hash SHA-256 di `auth_sso_tokens`, TTL 60 detik), builder URL subdomain (mendukung host `app.*` dan port non-standar), `validateAndLogin()` dalam transaksi Central DB (`lockForUpdate()`) yang langsung menghapus token setelah digunakan/invalid, memperbarui `sys_tenant_user.last_accessed_at`, mengautentikasi sesi lokal, serta `resolvePostLoginResponse()` (auto-redirect via `Inertia::location` bagi user 1 tenant aktif).
- `app/Http/Requests/Auth/SsoCallbackRequest.php` — validasi parameter `token` (`required`, `string`, `size:64`).
- `app/Http/Controllers/Auth/SsoCallbackController.php` — Thin Controller invokable menerima callback `/sso/callback` pada route tenant, memanggil `SsoService::validateAndLogin()`, dan mengarahkan ke dashboard tenant.
- `app/Http/Responses/LoginResponse.php` & `app/Providers/FortifyServiceProvider.php` — mengikat kontrak `LoginResponse` & `TwoFactorLoginResponse` Fortify serta memperbarui `OtpVerificationController@store` agar seluruh alur login terpusat (Password, 2FA, dan OTP Email) memakai `SsoService::resolvePostLoginResponse()`.

**Tenancy, Middleware & Model Central DB**
- `bootstrap/providers.php` — mendaftarkan `App\Providers\TenancyServiceProvider::class`.
- `app/Http/Middleware/InitializeTenancyBySubdomainIfApplicable.php` & `bootstrap/app.php` — middleware pada group `web` yang menginisialisasi tenancy + `ScopeSessions` ketika request datang melalui subdomain tenant, dan memastikan tenancy kembali berakhir pada request domain central.
- `routes/tenant.php` — mendaftarkan `GET /sso/callback` (`tenant.sso.callback`) dengan middleware `InitializeTenancyBySubdomain` + `PreventAccessFromCentralDomains`.
- `app/Models/User.php`, `app/Models/TenantUser.php`, `app/Models/OtpToken.php`, `app/Models/SsoToken.php`, `app/Models/Tenant.php`, `app/Models/Domain.php` — menambahkan trait `CentralConnection` agar tabel Central DB tetap terbaca saat koneksi default beralih ke DB tenant, memperbaiki relasi generic Larastan (termasuk `Tenant::domains()` dan `Tenant::getCustomColumns()`), serta menyelaraskan tipe UUID string `target_tenant_id`.
- `config/tenancy.php`, `database/seeders/CentralDatabaseSeeder.php`, `.gitignore` — menambahkan `app.presentify.test` ke `central_domains`, menyelaraskan domain seeder menjadi label subdomain (`disdukcapil`, `sman1contoh`) untuk `InitializeTenancyBySubdomain`, dan mengabaikan SQLite tenant DB testing/lokal di `.gitignore`.

**Test**
- `tests/Feature/Auth/SsoHandshakeTest.php` — 13 test cases mencakup: user 0 tenant tetap di dashboard central, redirect otomatis user 1 tenant via Password & OTP login, respons Inertia `409 X-Inertia-Location`, penolakan tenant/keanggotaan non-aktif, fallback user multi-tenant, handshake token valid & pembersihan token instan, proteksi replay, token kedaluwarsa, token lintas tenant, keanggotaan dicabut, isolasi `/sso/callback` dari domain central (404), validasi token, dan resolusi URL `app.*`.

### 2026-11-24

#### Ditambah — Langkah 1.2: Central Auth dengan OTP Email 6-Digit (Tuntas)

**Verifikasi:** `php artisan test` → 52 passed, 173 assertions ✓ | `npm run build` → 2330 modules ✓

**Service & Requests**
- `app/Services/Auth/OtpService.php` — generasi kode 6-digit kriptografis (`random_int`), hashing SHA-256, TTL 5 menit, rate limiting 3 percobaan/menit via `RateLimiter`, invalidasi token aktif sebelumnya saat generate baru.
- `app/Http/Requests/Auth/SendOtpRequest.php` — validasi `email` wajib & exists di tabel `users`.
- `app/Http/Requests/Auth/VerifyOtpRequest.php` — validasi `code` wajib, `digits:6`.

**Notification**
- `app/Notifications/Auth/OtpCodeNotification.php` — mail notification dengan subject, greeting, kode OTP, dan TTL.

**Controller**
- `app/Http/Controllers/Auth/OtpVerificationController.php` — Thin Controller: 3 method (`show`, `send`, `store`). Delegasi ke `OtpService`, otomatis login ke sesi aplikasi via `Auth::login($user)` dan regenerasi session ID setelah OTP tervalidasi.

**Routes**
- `routes/web.php` — 3 route di middleware `guest`: `GET /auth/otp`, `POST /auth/otp`, `POST /auth/otp/send`.

**Frontend**
- `resources/js/pages/auth/login.tsx` — integrasi switcher metode masuk ("Kata Sandi" dan "Kode OTP Email") dengan sinkronisasi nilai email saat berpindah mode, submit form OTP ke `send.form`, tombol outline peralihan, dan tata letak responsif bebas slop.
- `resources/js/pages/auth/verify-otp.tsx` — halaman verifikasi Inertia React dengan shadcn `InputOTP` (6 slot), toast notifikasi `sonner`, form kirim ulang kode terintegrasi, dan tautan kembali ke login.

**Test**
- `tests/Feature/Auth/OtpVerificationTest.php` — 10 test case: generate hash, verify valid OTP, expired OTP, used OTP, rate limiting, send route notification, show route accessible, no-session rejection, invalidate previous token, serta autentikasi sesi user saat verifikasi berhasil.

#### Diperbarui — Migrasi ke stancl/tenancy (penyesuaian Langkah 1.1)

**Package**
- `stancl/tenancy` v3.10.1 diinstall (`stancl/virtualcolumn`, `stancl/jobpipeline` sebagai dependency).

**Migrasi — dimodifikasi / ditambah**
- `2019_09_15_000010_create_sys_tenants_table.php` — menggantikan migrasi manual lama; `sys_tenants` kini memakai UUID string PK dan kolom `data` JSON untuk virtual columns (theme_config, terminology, logo_path, favicon_path).
- `2019_09_15_000020_create_sys_domains_table.php` — tabel `sys_domains` (FK ke `sys_tenants.id` string UUID); menggantikan tabel `domains` default stancl.
- `2026_11_24_000002_create_sys_tenant_user_table.php` — FK `tenant_id` diubah dari `bigint` menjadi `string` agar kompatibel dengan UUID `sys_tenants.id`.

**Model Eloquent — diperbarui / ditambah**
- `app/Models/Tenant.php` — kini extends `BaseTenant` (stancl) + traits `HasDatabase` + `HasDomains`; virtual columns `theme_config`, `terminology`, `logo_path`, `favicon_path` disimpan di kolom `data` JSON via VirtualColumn.
- `app/Models/Domain.php` *(baru)* — extends `BaseDomain` (stancl), `protected $table = 'sys_domains';`.
- `app/Models/TenantUser.php` — `tenant_id` diubah ke `string`.

**Konfigurasi & Provider**
- `config/tenancy.php` — dikonfigurasi dengan model `Tenant` & `Domain` kustom, `central_domains`, dan prefix DB `presentify_tenant_`.
- `app/Providers/TenancyServiceProvider.php` — dirapikan (typed return, komentar slop dihapus).
- `routes/tenant.php` — middleware diubah ke `InitializeTenancyBySubdomain` + `PreventAccessFromCentralDomains`.
- `database/seeders/CentralDatabaseSeeder.php` — seeder menggunakan `$tenant->domains()->create()` via stancl HasDomains.

**Verifikasi**
- `php artisan test`: 42 passed, 147 assertions ✓
- `npm run build`: built in 18.48s, 2328 modules ✓

**Migrasi (Central DB)**
- `database/migrations/2026_11_24_000001_create_sys_tenants_table.php` — tabel `sys_tenants` menyimpan profil instansi, konfigurasi tema, dan kamus terminologi.
- `database/migrations/2026_11_24_000002_create_sys_tenant_user_table.php` — pivot `sys_tenant_user` memetakan user global ke tenant.
- `database/migrations/2026_11_24_000003_create_auth_otp_tokens_table.php` — tabel `auth_otp_tokens` menyimpan hash SHA-256 token OTP email (TTL 5 menit).
- `database/migrations/2026_11_24_000004_create_auth_sso_tokens_table.php` — tabel `auth_sso_tokens` menyimpan one-time SSO token (TTL 30–60 detik, single-use).

**Model Eloquent**
- `app/Models/Tenant.php` — model `sys_tenants` dengan casting JSON untuk `theme_config` dan `terminology`, relasi ke `User` dan `TenantUser`.
- `app/Models/TenantUser.php` — model `sys_tenant_user` dengan relasi `tenant()` dan `user()`.
- `app/Models/OtpToken.php` — model `auth_otp_tokens` dengan method `isValid()` untuk validasi TTL dan status penggunaan.
- `app/Models/SsoToken.php` — model `auth_sso_tokens` dengan method `isValid()` dan relasi ke `Tenant` via `targetTenant()`.
- `app/Models/User.php` — ditambah relasi `tenants()`, `activeTenants()`, `tenantUsers()`, `otpTokens()`, `ssoTokens()` ke model-model Central DB.

**Seeder**
- `database/seeders/CentralDatabaseSeeder.php` — seeder 2 user (Superadmin, Demo User), 2 tenant contoh (Dinas Kependudukan & SMA Negeri 1) lengkap dengan konfigurasi tema dan terminologi awal.
- `database/seeders/DatabaseSeeder.php` — diperbarui untuk memanggil `CentralDatabaseSeeder`.

**Verifikasi**
- `php artisan test`: 42 passed, 147 assertions ✓
- `npm run build`: built in 20.18s, 2328 modules ✓
