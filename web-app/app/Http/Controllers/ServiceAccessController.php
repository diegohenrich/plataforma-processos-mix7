<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ServiceAccess;
use App\Models\ServiceAccessRequest;
use App\Services\ServiceAccessManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceAccessController extends Controller
{
    private const METHODS = [
        'individual_account' => 'Conta individual no serviço',
        'vendor_invitation' => 'Convite enviado pelo serviço',
        'company_sso' => 'Login corporativo',
        'external_secret_manager' => 'Gerenciador externo autorizado',
        'other' => 'Outro método sem senha compartilhada',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeInternal($user);
        $canManage = $user->role === UserRole::AgencyOwner;
        $services = ServiceAccess::query()
            ->where('organization_id', $user->organization_id)
            ->when(! $canManage, fn ($query) => $query->whereNull('archived_at'))
            ->with([
                'creator:id,name',
                'updater:id,name',
                'requests' => function ($query) use ($user, $canManage): void {
                    $query->when(! $canManage, fn ($requests) => $requests->where('user_id', $user->id))
                        ->with(['user:id,name', 'reviewer:id,name'])
                        ->orderByDesc('requested_at');
                },
                'events' => function ($query) use ($user, $canManage): void {
                    $query->when(! $canManage, fn ($events) => $events->where(function ($visible) use ($user): void {
                        $visible->whereNull('service_access_request_id')
                            ->orWhereHas('request', fn ($requests) => $requests->where('user_id', $user->id));
                    }))->with('actor:id,name')->latest('created_at')->limit(30);
                },
            ])
            ->orderBy('name')
            ->get();

        return view('team.service-access.index', [
            'services' => $services,
            'canManage' => $canManage,
            'methods' => self::METHODS,
        ]);
    }

    public function store(Request $request, ServiceAccessManager $manager): RedirectResponse
    {
        $this->authorizeOwner($request);
        $data = $this->validatedService($request);
        $service = DB::transaction(function () use ($request, $data, $manager): ServiceAccess {
            $service = ServiceAccess::create($this->serviceAttributes($data, $request->user()));
            $manager->record($service, $request->user(), 'service_created');

            return $service;
        });

        return to_route('service-access.index')->with('success', 'Serviço adicionado à matriz de acessos. Nenhuma senha foi armazenada.');
    }

    public function update(Request $request, ServiceAccess $service, ServiceAccessManager $manager): RedirectResponse
    {
        $this->authorizeOwner($request, $service);
        abort_if($service->archived_at, 404);
        $data = $this->validatedService($request);
        DB::transaction(function () use ($request, $service, $data, $manager): void {
            $service->update([...$this->serviceAttributes($data, $request->user()), 'created_by' => $service->created_by]);
            $manager->record($service, $request->user(), 'service_updated');
        });

        return to_route('service-access.index')->with('success', 'Instruções do serviço atualizadas.');
    }

    public function archive(Request $request, ServiceAccess $service, ServiceAccessManager $manager): RedirectResponse
    {
        $this->authorizeOwner($request, $service);
        abort_if($service->archived_at, 404);
        abort_if($service->requests()->whereIn('status', ['pending', 'granted'])->exists(), 409, 'Analise solicitações pendentes e revogue acessos concedidos antes de arquivar o serviço.');
        DB::transaction(function () use ($request, $service, $manager): void {
            $service->update(['archived_at' => now(), 'updated_by' => $request->user()->id]);
            $manager->record($service, $request->user(), 'service_archived');
        });

        return to_route('service-access.index')->with('success', 'Serviço arquivado; histórico preservado.');
    }

    public function restore(Request $request, ServiceAccess $service, ServiceAccessManager $manager): RedirectResponse
    {
        $this->authorizeOwner($request, $service);
        abort_unless($service->archived_at, 404);
        DB::transaction(function () use ($request, $service, $manager): void {
            $service->update(['archived_at' => null, 'updated_by' => $request->user()->id]);
            $manager->record($service, $request->user(), 'service_restored');
        });

        return to_route('service-access.index')->with('success', 'Serviço restaurado à matriz.');
    }

    public function requestAccess(Request $request, ServiceAccess $service, ServiceAccessManager $manager): RedirectResponse
    {
        $this->authorizeInternal($request->user());
        $manager->request($service, $request->user());

        return to_route('service-access.index')->with('success', 'Solicitação registrada para análise da direção.');
    }

    public function withdraw(Request $request, ServiceAccessRequest $accessRequest, ServiceAccessManager $manager): RedirectResponse
    {
        $manager->withdraw($accessRequest, $request->user());

        return to_route('service-access.index')->with('success', 'Solicitação retirada.');
    }

    public function decide(Request $request, ServiceAccessRequest $accessRequest, ServiceAccessManager $manager): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['granted', 'denied'])]]);
        $manager->decide($accessRequest, $request->user(), $data['status']);

        return to_route('service-access.index')->with('success', $data['status'] === 'granted' ? 'Concessão registrada. A pessoa ainda precisa ser autorizada no serviço externo.' : 'Solicitação negada.');
    }

    public function revoke(Request $request, ServiceAccessRequest $accessRequest, ServiceAccessManager $manager): RedirectResponse
    {
        $manager->revoke($accessRequest, $request->user());

        return to_route('service-access.index')->with('success', 'Revogação registrada. Remova também a autorização no serviço externo.');
    }

    private function validatedService(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'service_url' => ['nullable', 'url:http,https', 'max:2048'],
            'access_method' => ['required', Rule::in(array_keys(self::METHODS))],
            'instructions' => ['required', 'string', 'max:6000'],
            'review_due_on' => ['nullable', 'date'],
        ]);
    }

    private function serviceAttributes(array $data, $user): array
    {
        return [
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'name' => trim($data['name']),
            'service_url' => isset($data['service_url']) ? trim($data['service_url']) : null,
            'access_method' => $data['access_method'],
            'instructions' => trim($data['instructions']),
            'review_due_on' => $data['review_due_on'] ?? null,
        ];
    }

    private function authorizeInternal($user): void
    {
        abort_unless($user->is_active && $user->organization_id !== null && $user->role !== UserRole::Client, 403);
    }

    private function authorizeOwner(Request $request, ?ServiceAccess $service = null): void
    {
        abort_unless($request->user()->is_active && $request->user()->role === UserRole::AgencyOwner, 403);
        if ($service) {
            abort_unless($service->organization_id === $request->user()->organization_id, 404);
        }
    }
}
