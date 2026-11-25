<?php

declare(strict_types=1);

/**
 * Master registry of all system menus for Presentify.
 *
 * Rules:
 * - Each entry MUST have a unique `key` (used as the system identifier — never change once deployed).
 * - `parent` refers to another menu's `key` to build hierarchy.
 * - `sort_order` controls display order within the same parent level.
 * - `permissions` lists the actions available on this menu (used by MenuSyncService
 *   to auto-create menu-bound permissions in `rbac_permissions`).
 * - Admin Tenant can override `custom_label`, `icon`, and `sort_order` via the UI
 *   without touching this file — MenuSyncService will NOT overwrite those fields.
 */
return [

    // ----------------------------------------------------------------
    // Top-level: Dashboard
    // ----------------------------------------------------------------
    [
        'key'         => 'dashboard',
        'label'       => 'Dashboard',
        'icon'        => 'layout-dashboard',
        'route_name'  => 'dashboard',
        'parent'      => null,
        'sort_order'  => 10,
        'permissions' => ['view'],
    ],

    // ----------------------------------------------------------------
    // Group: Presensi
    // ----------------------------------------------------------------
    [
        'key'         => 'attendance',
        'label'       => 'Presensi',
        'icon'        => 'clock',
        'route_name'  => null,
        'parent'      => null,
        'sort_order'  => 20,
        'permissions' => [],
    ],
    [
        'key'         => 'attendance.checkin',
        'label'       => 'Catat Kehadiran',
        'icon'        => 'fingerprint',
        'route_name'  => 'attendance.checkin',
        'parent'      => 'attendance',
        'sort_order'  => 10,
        'permissions' => ['view', 'create'],
    ],
    [
        'key'         => 'attendance.history',
        'label'       => 'Riwayat Kehadiran',
        'icon'        => 'calendar-clock',
        'route_name'  => 'attendance.history',
        'parent'      => 'attendance',
        'sort_order'  => 20,
        'permissions' => ['view', 'export'],
    ],
    [
        'key'         => 'attendance.leave',
        'label'       => 'Pengajuan Izin / Cuti',
        'icon'        => 'file-text',
        'route_name'  => 'attendance.leave',
        'parent'      => 'attendance',
        'sort_order'  => 30,
        'permissions' => ['view', 'create', 'edit', 'delete'],
    ],

    // ----------------------------------------------------------------
    // Group: Persetujuan (Atasan / Verifikator)
    // ----------------------------------------------------------------
    [
        'key'         => 'approvals',
        'label'       => 'Persetujuan',
        'icon'        => 'check-square',
        'route_name'  => null,
        'parent'      => null,
        'sort_order'  => 30,
        'permissions' => [],
    ],
    [
        'key'         => 'approvals.inbox',
        'label'       => 'Kotak Masuk Persetujuan',
        'icon'        => 'inbox',
        'route_name'  => 'approvals.inbox',
        'parent'      => 'approvals',
        'sort_order'  => 10,
        'permissions' => ['view', 'create'],
    ],

    // ----------------------------------------------------------------
    // Group: Laporan
    // ----------------------------------------------------------------
    [
        'key'         => 'reports',
        'label'       => 'Laporan',
        'icon'        => 'bar-chart-2',
        'route_name'  => null,
        'parent'      => null,
        'sort_order'  => 40,
        'permissions' => [],
    ],
    [
        'key'         => 'reports.attendance',
        'label'       => 'Laporan Kehadiran',
        'icon'        => 'table',
        'route_name'  => 'reports.attendance',
        'parent'      => 'reports',
        'sort_order'  => 10,
        'permissions' => ['view', 'export'],
    ],

    // ----------------------------------------------------------------
    // Group: Pengaturan Instansi
    // ----------------------------------------------------------------
    [
        'key'         => 'settings',
        'label'       => 'Pengaturan',
        'icon'        => 'settings',
        'route_name'  => null,
        'parent'      => null,
        'sort_order'  => 90,
        'permissions' => [],
    ],
    [
        'key'         => 'settings.organization',
        'label'       => 'Organisasi & Anggota',
        'icon'        => 'users',
        'route_name'  => 'settings.organization',
        'parent'      => 'settings',
        'sort_order'  => 10,
        'permissions' => ['view', 'create', 'edit', 'delete'],
    ],
    [
        'key'         => 'settings.roles',
        'label'       => 'Peran & Hak Akses',
        'icon'        => 'shield',
        'route_name'  => 'settings.roles',
        'parent'      => 'settings',
        'sort_order'  => 20,
        'permissions' => ['view', 'create', 'edit', 'delete'],
    ],
    [
        'key'         => 'settings.menus',
        'label'       => 'Tampilan Menu',
        'icon'        => 'menu',
        'route_name'  => 'settings.menus',
        'parent'      => 'settings',
        'sort_order'  => 30,
        'permissions' => ['view', 'edit'],
    ],
    [
        'key'         => 'settings.policies',
        'label'       => 'Kebijakan Kehadiran',
        'icon'        => 'book-open',
        'route_name'  => 'settings.policies',
        'parent'      => 'settings',
        'sort_order'  => 40,
        'permissions' => ['view', 'create', 'edit', 'delete'],
    ],
    [
        'key'         => 'settings.profile',
        'label'       => 'Profil Instansi',
        'icon'        => 'building-2',
        'route_name'  => 'settings.profile',
        'parent'      => 'settings',
        'sort_order'  => 50,
        'permissions' => ['view', 'edit'],
    ],
    [
        'key'         => 'settings.theme',
        'label'       => 'Tampilan & Tema',
        'icon'        => 'palette',
        'route_name'  => 'settings.theme',
        'parent'      => 'settings',
        'sort_order'  => 60,
        'permissions' => ['view', 'edit'],
    ],
    [
        'key'         => 'settings.workflows',
        'label'       => 'Alur Persetujuan',
        'icon'        => 'git-merge',
        'route_name'  => 'settings.workflows',
        'parent'      => 'settings',
        'sort_order'  => 70,
        'permissions' => ['view', 'create', 'edit', 'delete'],
    ],
    [
        'key'         => 'settings.audit',
        'label'       => 'Jejak Aktivitas',
        'icon'        => 'activity',
        'route_name'  => 'settings.audit',
        'parent'      => 'settings',
        'sort_order'  => 80,
        'permissions' => ['view', 'export'],
    ],
];
