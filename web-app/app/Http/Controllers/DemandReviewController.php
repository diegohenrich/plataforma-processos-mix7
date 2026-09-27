<?php

namespace App\Http\Controllers;

use App\Enums\DemandStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandEvent;
use App\Models\DemandReviewLink;
use App\Models\DemandReviewResponse;
use App\Models\DemandTask;
use App\Models\User;
use App\Notifications\ClientReviewLinkNotification;
use App\Notifications\DemandReviewActivityNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DemandReviewController extends Controller
{
    public function store(Request $request, Demand $demand): RedirectResponse|JsonResponse
    {
        $this->authorize('manage', $demand);
        abort_unless($demand->status === DemandStatus::ClientApproval, 409, 'A demanda precisa estar em aprovação do cliente.');

        $data = $request->validate([
            'material_url' => ['nullable', 'required_without:material_file', 'url:http,https', 'max:2048'],
            'material_file' => ['nullable', 'required_without:material_url', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,mp4,webm'],
            'expires_at' => ['required', 'date', 'after:now'],
            'send_to_email' => ['nullable', 'email', 'max:254'],
        ]);
        if (! empty($data['send_to_email']) && $request->expectsJson()) {
            throw ValidationException::withMessages(['send_to_email' => 'O envio por e-mail deve ser iniciado pela interface web, com confirmação da pessoa responsável.']);
        }
        if (! empty($data['send_to_email']) && in_array(config('mail.default'), ['log', 'array'], true)) {
            throw ValidationException::withMessages(['send_to_email' => 'Configure um transporte real de e-mail antes de enviar o link. O link ainda pode ser copiado e compartilhado por um canal aprovado.']);
        }
        if ($request->filled('material_url') && $request->hasFile('material_file')) {
            throw ValidationException::withMessages(['material_file' => 'Envie um link ou um arquivo por versão, não os dois.']);
        }

        $plainToken = Str::random(64);
        $uploadedFile = $request->file('material_file');
        $storedPath = $uploadedFile?->store("review-materials/{$demand->organization_id}/{$demand->id}", 'local');
        if ($uploadedFile && ! $storedPath) {
            throw new RuntimeException('Não foi possível salvar o arquivo de revisão no armazenamento privado.');
        }

        try {
            $link = DB::transaction(function () use ($demand, $request, $data, $plainToken, $uploadedFile, $storedPath): DemandReviewLink {
                $lockedDemand = Demand::query()->whereKey($demand->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedDemand->status === DemandStatus::ClientApproval, 409);

                $lockedDemand->reviewLinks()->whereNull('revoked_at')->update(['revoked_at' => now()]);
                $version = ((int) $lockedDemand->reviewLinks()->max('version')) + 1;

                return $lockedDemand->reviewLinks()->create([
                    'organization_id' => $lockedDemand->organization_id,
                    'created_by' => $request->user()->id,
                    'version' => $version,
                    'token_hash' => hash('sha256', $plainToken),
                    'material_url' => $storedPath ? null : $data['material_url'],
                    'material_file_path' => $storedPath,
                    'material_file_name' => $uploadedFile ? basename(str_replace('\\', '/', $uploadedFile->getClientOriginalName())) : null,
                    'material_mime' => $uploadedFile?->getMimeType(),
                    'material_file_size' => $uploadedFile?->getSize(),
                    'expires_at' => $data['expires_at'],
                ]);
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }

        $reviewUrl = route('client-reviews.show', ['token' => $plainToken]);
        $emailSent = false;
        if (! empty($data['send_to_email'])) {
            try {
                Notification::route('mail', $data['send_to_email'])->notify(new ClientReviewLinkNotification($demand, $link, $reviewUrl));
                $emailSent = true;
            } catch (Throwable $exception) {
                report($exception);
            }

            if ($emailSent) {
                try {
                    DemandEvent::create([
                        'organization_id' => $demand->organization_id,
                        'demand_id' => $demand->id,
                        'actor_id' => $request->user()->id,
                        'event_type' => 'client_review_link_emailed',
                        'summary' => $request->user()->name.' enviou o link da versão '.$link->version.' por e-mail.',
                        'to_status' => DemandStatus::ClientApproval->value,
                    ]);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Link da versão criado. Envie-o ao cliente por um canal aprovado.',
                'data' => [
                    'id' => $link->id,
                    'demand_id' => $link->demand_id,
                    'version' => $link->version,
                    'review_url' => $reviewUrl,
                    'expires_at' => $link->expires_at->toISOString(),
                ],
            ], 201)->header('Cache-Control', 'private, no-store')
                ->header('Referrer-Policy', 'no-referrer');
        }

        $flash = $emailSent
            ? ['success', 'Link da versão '.$link->version.' criado e enviado por e-mail.']
            : (! empty($data['send_to_email'])
                ? ['warning', 'O link da versão '.$link->version.' foi criado, mas o e-mail não foi enviado. Copie o link abaixo ou tente novamente com o transporte configurado.']
                : ['success', 'Link da versão '.$link->version.' criado. Copie-o agora para enviar ao cliente.']);

        return back()->with('review_link_emailed', $emailSent)
            ->with('review_link_url', $reviewUrl)
            ->with('review_link_id', $link->id)
            ->with($flash[0], $flash[1]);
    }

    public function revoke(Request $request, Demand $demand, DemandReviewLink $reviewLink): RedirectResponse|JsonResponse
    {
        $this->authorize('manage', $demand);
        abort_unless($reviewLink->demand_id === $demand->id && $reviewLink->organization_id === $request->user()->organization_id, 404);
        if ($reviewLink->revoked_at === null) {
            $reviewLink->update(['revoked_at' => now()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Link de revisão revogado.',
                'data' => ['id' => $reviewLink->id, 'revoked_at' => $reviewLink->fresh()->revoked_at->toISOString()],
            ])->header('Cache-Control', 'private, no-store');
        }

        return back()->with('success', 'Link revogado.');
    }

    public function show(string $token): View|Response
    {
        $reviewLink = $this->findLink($token);
        if ($reviewLink->revoked_at !== null || $reviewLink->expires_at->isPast()) {
            return response()->view('client-reviews.unavailable', status: 410);
        }
        $reviewLink->load('responses');
        $hasDecision = $reviewLink->responses->contains(fn (DemandReviewResponse $response): bool => in_array($response->type, ['approved', 'changes_requested'], true));
        if ($reviewLink->demand->status !== DemandStatus::ClientApproval && ! $hasDecision) {
            return response()->view('client-reviews.unavailable', status: 410);
        }

        $materialUrl = $reviewLink->material_file_path
            ? route('client-reviews.material', ['token' => $token])
            : $reviewLink->material_url;

        return view('client-reviews.show', compact('reviewLink', 'hasDecision', 'materialUrl'));
    }

    public function showApi(string $token): JsonResponse
    {
        $reviewLink = $this->findLink($token);
        abort_unless($reviewLink->revoked_at === null && $reviewLink->expires_at->isFuture(), 410);
        $reviewLink->load('responses');
        $hasDecision = $reviewLink->responses->contains(fn (DemandReviewResponse $response): bool => in_array($response->type, ['approved', 'changes_requested'], true));
        abort_unless($reviewLink->demand->status === DemandStatus::ClientApproval || $hasDecision, 410);

        return response()->json([
            'data' => [
                'demand_title' => $reviewLink->demand->title,
                'version' => $reviewLink->version,
                'expires_at' => $reviewLink->expires_at->toISOString(),
                'decision_recorded' => $hasDecision,
                'material_url' => $reviewLink->material_file_path
                    ? route('client-reviews.material', ['token' => $token])
                    : $reviewLink->material_url,
                'responses' => $reviewLink->responses->map(fn (DemandReviewResponse $response): array => [
                    'reviewer_name' => $response->reviewer_name,
                    'type' => $response->type,
                    'comment' => $response->comment,
                    'anchor_type' => $response->anchor_type,
                    'anchor_data' => $response->anchor_data,
                    'created_at' => $response->created_at?->toISOString(),
                ])->values(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function material(Request $request, string $token): BinaryFileResponse
    {
        $reviewLink = $this->findLink($token);
        $hasDecision = $reviewLink->responses()->whereIn('type', ['approved', 'changes_requested'])->exists();
        abort_unless($reviewLink->revoked_at === null && $reviewLink->expires_at->isFuture()
            && ($reviewLink->demand->status === DemandStatus::ClientApproval || $hasDecision), 410);

        return $this->streamPrivateMaterial($reviewLink, $request->boolean('download'));
    }

    public function teamMaterial(Request $request, Demand $demand, DemandReviewLink $reviewLink): BinaryFileResponse
    {
        abort_unless($reviewLink->demand_id === $demand->id && $reviewLink->organization_id === $request->user()->organization_id, 404);
        $this->authorize('manage', $demand);

        return $this->streamPrivateMaterial($reviewLink, $request->boolean('download'));
    }

    public function respond(Request $request, string $token): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'reviewer_name' => ['required', 'string', 'min:2', 'max:120'],
            'type' => ['required', 'in:comment,annotation,approved,changes_requested'],
            'comment' => ['nullable', 'string', 'max:5000', 'required_if:type,comment,annotation,changes_requested'],
            'anchor_type' => ['required_if:type,annotation', 'nullable', 'in:text,area,time,page'],
            'anchor_text' => ['nullable', 'string', 'max:1000', 'required_if:anchor_type,text'],
            'anchor_x' => ['nullable', 'numeric', 'between:0,100', 'required_if:anchor_type,area'],
            'anchor_y' => ['nullable', 'numeric', 'between:0,100', 'required_if:anchor_type,area'],
            'anchor_width' => ['exclude_unless:anchor_type,area', 'nullable', 'numeric', 'between:0,100', 'required_with:anchor_height'],
            'anchor_height' => ['exclude_unless:anchor_type,area', 'nullable', 'numeric', 'between:0,100', 'required_with:anchor_width'],
            'anchor_path' => [
                'exclude_unless:anchor_type,area',
                'nullable',
                'string',
                'max:16000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $points = json_decode($value, true);
                    if (! is_array($points) || count($points) < 2 || count($points) > 256) {
                        $fail('O rabisco precisa conter entre 2 e 256 pontos.');

                        return;
                    }

                    foreach ($points as $point) {
                        if (! is_array($point) || ! isset($point['x'], $point['y'])
                            || ! is_numeric($point['x']) || ! is_numeric($point['y'])
                            || ! is_finite((float) $point['x']) || ! is_finite((float) $point['y'])
                            || $point['x'] < 0 || $point['x'] > 100 || $point['y'] < 0 || $point['y'] > 100) {
                            $fail('O rabisco contém coordenadas inválidas.');

                            return;
                        }
                    }
                },
            ],
            'anchor_time' => ['nullable', 'date_format:H:i:s', 'required_if:anchor_type,time'],
            'anchor_page' => ['nullable', 'integer', 'min:1', 'required_if:anchor_type,page'],
        ]);

        $anchorPath = isset($data['anchor_path']) && $data['anchor_path'] !== ''
            ? array_map(fn (array $point): array => [
                'x' => round((float) $point['x'], 1),
                'y' => round((float) $point['y'], 1),
            ], json_decode($data['anchor_path'], true))
            : null;
        $hasAreaAnchor = $data['type'] === 'annotation' && ($data['anchor_type'] ?? null) === 'area';
        $anchorWidth = $hasAreaAnchor && $request->filled('anchor_width')
            ? (float) $request->input('anchor_width')
            : null;
        $anchorHeight = $hasAreaAnchor && $request->filled('anchor_height')
            ? (float) $request->input('anchor_height')
            : null;

        $response = DB::transaction(function () use ($data, $token, $anchorPath, $anchorWidth, $anchorHeight): DemandReviewResponse {
            $reviewLink = $this->findLink($token, lock: true);
            abort_unless($reviewLink->isAvailable(), 410, 'Este link expirou ou não está mais disponível.');
            $hasDecision = $reviewLink->responses()->whereIn('type', ['approved', 'changes_requested'])->exists();
            if ($hasDecision) {
                throw ValidationException::withMessages(['type' => 'Esta versão já recebeu uma decisão final.']);
            }

            $response = $reviewLink->responses()->create([
                'reviewer_name' => $data['reviewer_name'],
                'type' => $data['type'],
                'comment' => $data['comment'] ?? null,
                'anchor_type' => $data['type'] === 'annotation' ? $data['anchor_type'] : null,
                'anchor_data' => $data['type'] === 'annotation' ? array_filter([
                    'text' => $data['anchor_text'] ?? null,
                    'url' => $reviewLink->material_url ?? 'arquivo privado da versão '.$reviewLink->version,
                    'x' => isset($data['anchor_x']) ? (float) $data['anchor_x'] : null,
                    'y' => isset($data['anchor_y']) ? (float) $data['anchor_y'] : null,
                    'width' => $anchorWidth,
                    'height' => $anchorHeight,
                    'path' => $anchorPath,
                    'time' => $data['anchor_time'] ?? null,
                    'page' => isset($data['anchor_page']) ? (int) $data['anchor_page'] : null,
                ], fn ($value) => $value !== null) : null,
            ]);

            if (in_array($data['type'], ['approved', 'changes_requested'], true)) {
                $demand = Demand::query()->whereKey($reviewLink->demand_id)->lockForUpdate()->firstOrFail();
                abort_unless($demand->status === DemandStatus::ClientApproval, 410);
                $next = $data['type'] === 'approved' ? DemandStatus::Delivery : DemandStatus::Adjustments;
                $demand->update(['status' => $next]);
                DemandEvent::create([
                    'organization_id' => $demand->organization_id,
                    'demand_id' => $demand->id,
                    'actor_id' => null,
                    'event_type' => 'client_review_'.$data['type'],
                    'summary' => 'O cliente '.$data['reviewer_name'].' respondeu à versão '.$reviewLink->version.'; etapa atualizada para '.$next->label().'.',
                    'from_status' => DemandStatus::ClientApproval->value,
                    'to_status' => $next->value,
                ]);
            }

            $this->notifyDemandTeam($reviewLink->demand, $reviewLink, $response);

            return $response;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Sua resposta foi registrada. Obrigado pela revisão.',
                'data' => [
                    'type' => $response->type,
                    'comment' => $response->comment,
                    'anchor_type' => $response->anchor_type,
                    'anchor_data' => $response->anchor_data,
                    'created_at' => $response->created_at?->toISOString(),
                ],
            ], 201)->header('Cache-Control', 'private, no-store');
        }

        return back()->with('success', 'Sua resposta foi registrada. Obrigado pela revisão.');
    }

    private function findLink(string $token, bool $lock = false): DemandReviewLink
    {
        $query = DemandReviewLink::query()->with('demand')->where('token_hash', hash('sha256', $token));
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function notifyDemandTeam(Demand $demand, DemandReviewLink $reviewLink, DemandReviewResponse $response): void
    {
        $professionalIds = DemandTask::query()->where('demand_id', $demand->id)->distinct()->pluck('assigned_to');
        $recipients = User::query()
            ->where('organization_id', $demand->organization_id)
            ->where('is_active', true)
            ->where(function ($query) use ($demand, $professionalIds): void {
                $query->whereKey($demand->created_by)
                    ->orWhereIn('role', [UserRole::AgencyOwner->value, UserRole::MarketingManager->value])
                    ->orWhereIn('id', $professionalIds);
            })
            ->get();

        $notification = new DemandReviewActivityNotification(
            $demand->id,
            $demand->title,
            $reviewLink->version,
            $response->reviewer_name,
            $response->type,
            $response->comment,
        );

        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
        }
    }

    private function streamPrivateMaterial(DemandReviewLink $reviewLink, bool $download = false): BinaryFileResponse
    {
        $path = $reviewLink->material_file_path;
        $prefix = "review-materials/{$reviewLink->organization_id}/{$reviewLink->demand_id}/";
        abort_unless($path && Str::startsWith($path, $prefix) && ! in_array('..', explode('/', $path), true), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        $types = [
            'application/pdf' => ['review.pdf', 'application/pdf'],
            'image/jpeg' => ['review.jpg', 'image/jpeg'],
            'image/png' => ['review.png', 'image/png'],
            'image/webp' => ['review.webp', 'image/webp'],
            'video/mp4' => ['review.mp4', 'video/mp4'],
            'video/webm' => ['review.webm', 'video/webm'],
        ];
        abort_unless(isset($types[$reviewLink->material_mime]), 415);
        [$fileName, $mimeType] = $types[$reviewLink->material_mime];

        $headers = [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'no-referrer',
        ];
        $response = $download
            ? response()->download(Storage::disk('local')->path($path), $fileName, $headers)
            : response()->file(Storage::disk('local')->path($path), $headers + ['Content-Disposition' => 'inline; filename="'.$fileName.'"']);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
