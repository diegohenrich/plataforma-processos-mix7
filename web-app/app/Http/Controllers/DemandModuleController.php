<?php

namespace App\Http\Controllers;

use App\Enums\DemandModule;
use App\Models\Demand;
use App\Models\DemandModuleDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'fields' => [],
        ]);
        $customModules = DemandModuleDefinition::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('is_active', true)
            ->orderBy('label')
            ->get(['key', 'label', 'description', 'config_version', 'fields'])
            ->map(fn (DemandModuleDefinition $module): array => [
                'key' => $module->key,
                'label' => $module->label,
                'version' => $module->config_version,
                'description' => $module->description,
                'fields' => $module->fields ?? [],
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
            'version' => $module->version(),
            'fields' => [],
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
                'version' => $module->config_version,
                'fields' => $module->fields ?? [],
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
            'fields' => ['sometimes', 'array', 'max:20'],
            'fields.*.key' => ['required', 'string', 'min:2', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct:strict'],
            'fields.*.label' => ['required', 'string', 'min:2', 'max:80'],
            'fields.*.type' => ['required', 'string', Rule::in(['text', 'textarea', 'date', 'url', 'select'])],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'string', 'max:1000', 'required_if:fields.*.type,select'],
            'key' => [
                'nullable', 'string', 'min:2', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::notIn($reservedKeys),
                Rule::unique('demand_module_definitions', 'key')->where(fn ($query) => $query->where('organization_id', $request->user()->organization_id)),
            ],
        ]);

        $key = $data['key'] ?? $this->makeKey($data['label'], (int) $request->user()->organization_id);
        $fields = $this->normalizeFields($data['fields'] ?? []);
        DemandModuleDefinition::create([
            'organization_id' => $request->user()->organization_id,
            'created_by' => $request->user()->id,
            'key' => $key,
            'label' => trim($data['label']),
            'description' => trim($data['description']),
            'config_version' => 1,
            'fields' => $fields,
            'is_active' => true,
        ]);

        return to_route('approval-modules.index')->with('success', 'Tipo de aprovação criado com seus campos internos. Novas demandas guardarão a versão de configuração usada.');
    }

    public function updateFields(Request $request, DemandModuleDefinition $module): RedirectResponse
    {
        $this->authorize('create', Demand::class);
        abort_unless($module->organization_id === $request->user()->organization_id, 404);
        $data = $request->validate([
            'fields' => ['present', 'array', 'max:20'],
            'fields.*.key' => ['required', 'string', 'min:2', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct:strict'],
            'fields.*.label' => ['required', 'string', 'min:2', 'max:80'],
            'fields.*.type' => ['required', 'string', Rule::in(['text', 'textarea', 'date', 'url', 'select'])],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'string', 'max:1000', 'required_if:fields.*.type,select'],
        ]);

        $module->update([
            'fields' => $this->normalizeFields($data['fields']),
            'config_version' => $module->config_version + 1,
            'updated_by' => $request->user()->id,
        ]);

        return to_route('approval-modules.index')->with('success', 'Campos atualizados. Demandas novas usarão a versão '.$module->fresh()->config_version.'; as antigas mantêm o formulário original.');
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

    private function normalizeFields(array $fields): array
    {
        return collect($fields)->map(function (array $field, int $index): array {
            $options = ($field['type'] ?? null) === 'select'
                ? collect(preg_split('/\r\n|\r|\n/', (string) ($field['options'] ?? '')))->map(fn ($option) => trim($option))->filter()->unique()->values()->all()
                : [];

            if (($field['type'] ?? null) === 'select' && count($options) < 1) {
                throw ValidationException::withMessages(["fields.$index.options" => 'Inclua ao menos uma opção para este campo de seleção.']);
            }

            return [
                'key' => $field['key'],
                'label' => trim($field['label']),
                'type' => $field['type'],
                'required' => filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOL),
                'options' => $options,
            ];
        })->values()->all();
    }
}
