<?php

namespace App\Http\Controllers;

use App\Enums\DemandModule;
use App\Models\Demand;
use App\Models\DemandModuleDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DemandModuleController extends Controller
{
    public function indexApi(Request $request): JsonResponse
    {
        $this->authorize('create', Demand::class);
        $builtInModules = collect(DemandModule::cases())->map(fn (DemandModule $module): array => [
            'key' => $module->value,
            'label' => $module->label(),
            'version' => $module->version(),
            'description' => null,
        ]);
        $customModules = DemandModuleDefinition::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('is_active', true)
            ->orderBy('label')
            ->get(['key', 'label', 'description'])
            ->map(fn (DemandModuleDefinition $module): array => [
                'key' => $module->key,
                'label' => $module->label,
                'version' => 1,
                'description' => $module->description,
            ]);

        return response()->json(['data' => $builtInModules->concat($customModules)->values()]);
    }

    public function index(Request $request): View
    {
        $this->authorize('create', Demand::class);
        $builtInModules = collect(DemandModule::cases())->map(fn (DemandModule $module): array => [
            'key' => $module->value,
            'label' => $module->label(),
            'description' => 'Tipo padrão da plataforma.',
            'is_active' => true,
            'built_in' => true,
        ]);
        $customModules = DemandModuleDefinition::query()
            ->where('organization_id', $request->user()->organization_id)
            ->orderBy('label')
            ->with(['creator:id,name', 'updater:id,name'])
            ->get()
            ->map(fn (DemandModuleDefinition $module): array => [
                'key' => $module->key,
                'label' => $module->label,
                'description' => $module->description,
                'is_active' => $module->is_active,
                'created_by_name' => $module->creator->name,
                'updated_by_name' => $module->updater?->name,
                'built_in' => false,
                'id' => $module->id,
            ]);

        return view('approval-modules.index', ['modules' => $builtInModules->concat($customModules)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Demand::class);
        $request->merge([
            'label' => trim((string) $request->input('label')),
            'description' => trim((string) $request->input('description')),
            'key' => $request->filled('key') ? trim((string) $request->input('key')) : null,
        ]);
        $reservedKeys = array_map(fn (DemandModule $module): string => $module->value, DemandModule::cases());
        $data = $request->validate([
            'label' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['required', 'string', 'min:3', 'max:500'],
            'key' => [
                'nullable', 'string', 'min:2', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::notIn($reservedKeys),
                Rule::unique('demand_module_definitions', 'key')->where(fn ($query) => $query->where('organization_id', $request->user()->organization_id)),
            ],
        ]);

        $key = $data['key'] ?? $this->makeKey($data['label'], (int) $request->user()->organization_id);
        DemandModuleDefinition::create([
            'organization_id' => $request->user()->organization_id,
            'created_by' => $request->user()->id,
            'key' => $key,
            'label' => trim($data['label']),
            'description' => trim($data['description']),
            'is_active' => true,
        ]);

        return to_route('approval-modules.index')->with('success', 'Tipo de aprovação criado. Ele já pode ser usado em novas demandas pelo fluxo compartilhado.');
    }

    public function toggle(Request $request, DemandModuleDefinition $module): RedirectResponse
    {
        $this->authorize('create', Demand::class);
        abort_unless($module->organization_id === $request->user()->organization_id, 404);
        $module->update(['is_active' => ! $module->is_active, 'updated_by' => $request->user()->id]);

        return to_route('approval-modules.index')->with('success', $module->is_active
            ? 'Tipo reativado para novas demandas.'
            : 'Tipo desativado para novas demandas. Demandas existentes preservam o tipo registrado.');
    }

    private function makeKey(string $label, int $organizationId): string
    {
        $base = str($label)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->limit(45, '');
        $base = $base !== '' ? $base->toString() : 'modulo';
        $key = $base;
        $suffix = 2;
        while (in_array($key, array_map(fn (DemandModule $module): string => $module->value, DemandModule::cases()), true)
            || DemandModuleDefinition::query()->where('organization_id', $organizationId)->where('key', $key)->exists()) {
            $key = $base.'_'.($suffix++);
        }

        return $key;
    }
}
