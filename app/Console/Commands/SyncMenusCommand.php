<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Rbac\MenuSyncService;
use Illuminate\Console\Command;

/**
 * Artisan command: tenants:sync-menus
 *
 * Syncs the master menu registry (config/presentify-menus.php) into
 * every tenant database (or a specific one) in a non-destructive manner.
 *
 * Usage:
 *   php artisan tenants:sync-menus                   # all tenants
 *   php artisan tenants:sync-menus --tenant=abc-uuid # single tenant
 */
class SyncMenusCommand extends Command
{
    protected $signature = 'tenants:sync-menus
                            {--tenant= : UUID of a specific tenant to sync (omit for all)}';

    protected $description = 'Non-destructively sync system menus from master registry into tenant database(s)';

    public function __construct(private readonly MenuSyncService $menuSyncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');
            return self::FAILURE;
        }

        $this->info("Syncing menus for {$tenants->count()} tenant(s)...");

        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            try {
                $this->menuSyncService->sync();
            } finally {
                tenancy()->end();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Menu sync completed successfully.');

        return self::SUCCESS;
    }
}
