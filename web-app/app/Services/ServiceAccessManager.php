<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ServiceAccess;
use App\Models\ServiceAccessEvent;
use App\Models\ServiceAccessRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceAccessManager
{
    public function request(ServiceAccess $service, User $user): ServiceAccessRequest
    {
        abort_unless($user->is_active && $user->organization_id === $service->organization_id && $user->role !== UserRole::Client, 404);
        abort_if($service->archived_at, 404);

        return DB::transaction(function () use ($service, $user): ServiceAccessRequest {
            $existing = $service->requests()
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending', 'granted'])
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                throw ValidationException::withMessages(['access' => 'Já existe uma solicitação aguardando análise ou um acesso concedido.']);
            }

            $request = $service->requests()->create([
                'organization_id' => $service->organization_id,
                'user_id' => $user->id,
                'status' => 'pending',
                'active_slot' => $service->id.':'.$user->id,
                'requested_at' => now(),
            ]);
            $this->record($service, $user, 'access_requested', $request);

            return $request;
        });
    }

    public function decide(ServiceAccessRequest $accessRequest, User $actor, string $status): ServiceAccessRequest
    {
        $this->authorizeOwner($actor, $accessRequest->organization_id);
        abort_unless($status === 'granted' || $status === 'denied', 422);

        return DB::transaction(function () use ($accessRequest, $actor, $status): ServiceAccessRequest {
            $locked = ServiceAccessRequest::query()->whereKey($accessRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409);
            $locked->update(['status' => $status, 'active_slot' => $status === 'granted' ? $locked->active_slot : null, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
            $this->record($locked->service, $actor, $status === 'granted' ? 'access_granted' : 'access_denied', $locked);

            return $locked->fresh();
        });
    }

    public function revoke(ServiceAccessRequest $accessRequest, User $actor): ServiceAccessRequest
    {
        $this->authorizeOwner($actor, $accessRequest->organization_id);

        return DB::transaction(function () use ($accessRequest, $actor): ServiceAccessRequest {
            $locked = ServiceAccessRequest::query()->whereKey($accessRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'granted', 409);
            $locked->update(['status' => 'revoked', 'active_slot' => null, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
            $this->record($locked->service, $actor, 'access_revoked', $locked);

            return $locked->fresh();
        });
    }

    public function withdraw(ServiceAccessRequest $accessRequest, User $actor): ServiceAccessRequest
    {
        abort_unless($accessRequest->organization_id === $actor->organization_id && $accessRequest->user_id === $actor->id, 404);

        return DB::transaction(function () use ($accessRequest, $actor): ServiceAccessRequest {
            $locked = ServiceAccessRequest::query()->whereKey($accessRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409);
            $locked->update(['status' => 'withdrawn', 'active_slot' => null, 'reviewed_at' => now()]);
            $this->record($locked->service, $actor, 'request_withdrawn', $locked);

            return $locked->fresh();
        });
    }

    public function record(ServiceAccess $service, User $actor, string $eventType, ?ServiceAccessRequest $request = null): void
    {
        ServiceAccessEvent::create([
            'organization_id' => $service->organization_id,
            'service_access_id' => $service->id,
            'service_access_request_id' => $request?->id,
            'actor_id' => $actor->id,
            'event_type' => $eventType,
            'created_at' => now(),
        ]);
    }

    private function authorizeOwner(User $actor, int $organizationId): void
    {
        abort_unless($actor->is_active && $actor->role === UserRole::AgencyOwner && $actor->organization_id === $organizationId, 403);
    }
}
