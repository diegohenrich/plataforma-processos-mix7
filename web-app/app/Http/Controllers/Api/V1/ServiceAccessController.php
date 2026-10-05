<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ServiceAccess;
use App\Models\ServiceAccessRequest;
use App\Services\ServiceAccessManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServiceAccessController extends Controller
{
    private const METHODS = [
        'individual_account', 'vendor_invitation', 'company_sso', 'external_secret_manager', 'other',
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeInternal($user);
        $isOwner = $user->role === UserRole::AgencyOwner;
        $services = ServiceAccess::query()
            ->where('organization_id', $user->organization_id)
            ->whereNull('archived_at')
            ->with(['requests' => function ($query) use ($user, $isOwner): void {
                $query->when(! $isOwner, fn ($requests) => $requests->where('user_id', $user->id))
                    ->with(['user:id,name', 'reviewer:id,name'])
                    ->orderByDesc('requested_at');
            }])
            ->orderBy('name')->limit(100)->get();

        return response()->json(['data' => $services->map(fn (ServiceAccess $service) => $this->serializeService($service, $isOwner))]);
    }

    public function store(Request $request, ServiceAccessManager $manager): JsonResponse
    {
        $this->authorizeOwner($request);
        $data = $this->validateService($request);
        $service = DB::transaction(function () use ($request, $data, $manager): ServiceAccess {
            $service = ServiceAccess::create($this->attributes($data, $request->user()));
            $manager->record($service, $request->user(), 'service_created');

            return $service;
        });

        return response()->json(['data' => $this->serializeService($service, true)], 201);
    }

    public function update(Request $request, ServiceAccess $service, ServiceAccessManager $manager): JsonResponse
    {
        $this->authorizeOwner($request, $service);
        abort_if($service->archived_at, 404);
        $data = $this->validateService($request);
        DB::transaction(function () use ($request, $service, $data, $manager): void {
            $service->update([...$this->attributes($data, $request->user()), 'created_by' => $service->created_by]);
            $manager->record($service, $request->user(), 'service_updated');
        });

        return response()->json(['data' => $this->serializeService($service->fresh(), true)]);
    }

    public function archive(Request $request, ServiceAccess $service, ServiceAccessManager $manager): JsonResponse
    {
        $this->authorizeOwner($request, $service);
        abort_if($service->archived_at, 404);
        abort_if($service->requests()->whereIn('status', ['pending', 'granted'])->exists(), 409, 'Analise solicitações pendentes e revogue acessos concedidos antes de arquivar.');
        DB::transaction(function () use ($request, $service, $manager): void {
            $service->update(['archived_at' => now(), 'updated_by' => $request->user()->id]);
            $manager->record($service, $request->user(), 'service_archived');
        });

        return response()->json(['data' => ['id' => $service->id, 'archived_at' => $service->fresh()->archived_at?->toISOString()]]);
    }

    public function restore(Request $request, ServiceAccess $service, ServiceAccessManager $manager): JsonResponse
    {
        $this->authorizeOwner($request, $service);
        abort_unless($service->archived_at, 404);
        DB::transaction(function () use ($request, $service, $manager): void {
            $service->update(['archived_at' => null, 'updated_by' => $request->user()->id]);
            $manager->record($service, $request->user(), 'service_restored');
        });

        return response()->json(['data' => $this->serializeService($service->fresh(), true)]);
    }

    public function requestAccess(Request $request, ServiceAccess $service, ServiceAccessManager $manager): JsonResponse
    {
        $this->authorizeInternal($request->user());
        $accessRequest = $manager->request($service, $request->user());

        return response()->json(['data' => $this->serializeRequest($accessRequest->load('service:id,name'))], 201);
    }

    public function withdraw(Request $request, ServiceAccessRequest $accessRequest, ServiceAccessManager $manager): JsonResponse
    {
        $withdrawn = $manager->withdraw($accessRequest, $request->user());

        return response()->json(['data' => $this->serializeRequest($withdrawn->load('service:id,name'))]);
    }

    public function decide(Request $request, ServiceAccessRequest $accessRequest, ServiceAccessManager $manager): JsonResponse
    {
        $this->authorizeOwner($request, $accessRequest->service);
        $data = $request->validate(['status' => ['required', Rule::in(['granted', 'denied'])]]);
        $decided = $manager->decide($accessRequest, $request->user(), $data['status']);

        return response()->json(['data' => $this->serializeRequest($decided->load(['service:id,name', 'user:id,name', 'reviewer:id,name']))]);
    }

    public function revoke(Request $request, ServiceAccessRequest $accessRequest, ServiceAccessManager $manager): JsonResponse
    {
        $this->authorizeOwner($request, $accessRequest->service);
        $revoked = $manager->revoke($accessRequest, $request->user());

        return response()->json(['data' => $this->serializeRequest($revoked->load(['service:id,name', 'user:id,name', 'reviewer:id,name']))]);
    }

    private function validateService(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'service_url' => ['nullable', 'url:http,https', 'max:2048'],
            'access_method' => ['required', Rule::in(self::METHODS)],
            'instructions' => ['required', 'string', 'max:6000'],
            'review_due_on' => ['nullable', 'date'],
        ]);
    }

    private function attributes(array $data, $user): array
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

    private function serializeService(ServiceAccess $service, bool $isOwner): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'service_url' => $service->service_url,
            'access_method' => $service->access_method,
            'instructions' => $service->instructions,
            'review_due_on' => $service->review_due_on?->toDateString(),
            'requests' => $service->requests->map(fn (ServiceAccessRequest $request) => $this->serializeRequest($request, $isOwner)),
        ];
    }

    private function serializeRequest(ServiceAccessRequest $request, bool $showUser = true): array
    {
        return [
            'id' => $request->id,
            'service_id' => $request->service_access_id,
            'service_name' => $request->service?->name,
            'user_id' => $showUser ? $request->user_id : null,
            'user_name' => $showUser ? $request->user?->name : null,
            'status' => $request->status,
            'requested_at' => $request->requested_at?->toISOString(),
            'reviewed_by' => $request->reviewed_by,
            'reviewer_name' => $request->reviewer?->name,
            'reviewed_at' => $request->reviewed_at?->toISOString(),
        ];
    }

    private function authorizeOwner(Request $request, ?ServiceAccess $service = null): void
    {
        abort_unless($request->user()->is_active && $request->user()->role === UserRole::AgencyOwner, 403);
        if ($service) {
            abort_unless($service->organization_id === $request->user()->organization_id, 404);
        }
    }

    private function authorizeInternal($user): void
    {
        abort_unless($user->is_active && $user->organization_id !== null && $user->role !== UserRole::Client, 403);
    }
}
