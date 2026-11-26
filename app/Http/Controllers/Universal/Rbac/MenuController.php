<?php

declare(strict_types=1);

namespace App\Http\Controllers\Universal\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Universal\Rbac\MenuReorderRequest;
use App\Http\Requests\Universal\Rbac\MenuRequest;
use App\Models\Menu;
use App\Services\Rbac\MenuManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function __construct(private readonly MenuManagementService $menuManagementService) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');

        return Inertia::render('universal/rbac/menu/index', [
            'menus' => $this->menuManagementService->listForIndex(
                is_string($search) ? $search : null,
                is_string($status) ? $status : null,
            ),
            'filters' => [
                'search' => is_string($search) ? $search : '',
                'status' => is_string($status) ? $status : 'all',
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('universal/rbac/menu/form', [
            'menu' => null,
            'parentOptions' => $this->menuManagementService->getParentOptions(),
        ]);
    }

    public function store(MenuRequest $request): RedirectResponse
    {
        $menu = $this->menuManagementService->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Menu \"{$menu->label_name}\" berhasil ditambahkan.",
        ]);

        return to_route('rbac.menus.index');
    }

    public function edit(Menu $menu): Response
    {
        return Inertia::render('universal/rbac/menu/form', [
            'menu' => $menu,
            'parentOptions' => $this->menuManagementService->getParentOptions($menu->id),
        ]);
    }

    public function update(MenuRequest $request, Menu $menu): RedirectResponse
    {
        $updated = $this->menuManagementService->update($menu, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Menu \"{$updated->label_name}\" berhasil diperbarui.",
        ]);

        return to_route('rbac.menus.index');
    }

    public function toggleStatus(Menu $menu): RedirectResponse
    {
        $updated = $this->menuManagementService->toggleStatus($menu);
        $stateText = $updated->is_active ? 'diaktifkan' : 'dinonaktifkan';

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Menu \"{$updated->label_name}\" berhasil {$stateText}.",
        ]);

        return back();
    }

    public function sort(): Response
    {
        return Inertia::render('universal/rbac/menu/sort', [
            'tree' => $this->menuManagementService->getSortTree(),
        ]);
    }

    public function reorder(MenuReorderRequest $request): RedirectResponse
    {
        /** @var array<int, array{id: string, parent_id: string|null, sort_num: int}> $items */
        $items = $request->validated('items');
        $this->menuManagementService->reorder($items);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Urutan menu berhasil disimpan.',
        ]);

        return to_route('rbac.menus.sort');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $label = $menu->label_name;
        $this->menuManagementService->delete($menu);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Menu \"{$label}\" berhasil dihapus.",
        ]);

        return to_route('rbac.menus.index');
    }
}
