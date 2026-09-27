<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandAttachment;
use App\Models\DemandEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Throwable;

class DemandAttachmentController extends Controller
{
    private const ALLOWED_MIMES = 'pdf,jpg,jpeg,png,webp,gif,mp4,webm,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip';

    public function indexApi(Request $request, Demand $demand): JsonResponse
    {
        $this->authorizeTeamAccess($request, $demand);

        return response()->json([
            'data' => $demand->attachments()->with('uploader:id,name')->get()
                ->map(fn (DemandAttachment $attachment) => $this->attachmentData($demand, $attachment)),
        ]);
    }

    public function storeApi(Request $request, Demand $demand): JsonResponse
    {
        $this->authorizeTeamAccess($request, $demand);
        $data = $this->validatedFiles($request);
        $attachments = $this->storeFiles($request, $demand, $data['files']);

        return response()->json([
            'data' => $attachments->map(fn (DemandAttachment $attachment) => $this->attachmentData($demand, $attachment)),
        ], 201);
    }

    public function store(Request $request, Demand $demand): RedirectResponse
    {
        $this->authorizeTeamAccess($request, $demand);
        $data = $this->validatedFiles($request);
        $this->storeFiles($request, $demand, $data['files']);

        return back()->with('success', count($data['files']).' arquivo(s) anexado(s) à demanda. Eles ficam visíveis somente para a equipe autorizada.');
    }

    public function showApi(Request $request, Demand $demand, DemandAttachment $attachment)
    {
        return $this->serveFile($request, $demand, $attachment);
    }

    public function show(Request $request, Demand $demand, DemandAttachment $attachment)
    {
        return $this->serveFile($request, $demand, $attachment);
    }

    private function validatedFiles(Request $request): array
    {
        return $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'file', 'max:20480', 'mimes:'.self::ALLOWED_MIMES],
        ], [
            'files.required' => 'Escolha pelo menos um arquivo.',
            'files.*.max' => 'Cada arquivo pode ter no máximo 20 MB.',
            'files.*.mimes' => 'Use PDF, imagem, vídeo, documento, planilha, apresentação, texto, CSV ou ZIP.',
        ]);
    }

    /** @param array<int, UploadedFile> $files */
    private function storeFiles(Request $request, Demand $demand, array $files): Collection
    {
        $storedPaths = [];
        $attachments = collect();

        try {
            DB::transaction(function () use ($files, $demand, $request, &$storedPaths, $attachments): void {
                foreach ($files as $file) {
                    $directory = "demand-attachments/{$demand->organization_id}/{$demand->id}";
                    $path = $file->store($directory, 'local');
                    if (! $path) {
                        throw ValidationException::withMessages(['files' => 'Não foi possível guardar um dos arquivos. Tente novamente.']);
                    }
                    $storedPaths[] = $path;

                    $attachments->push($demand->attachments()->create([
                        'organization_id' => $demand->organization_id,
                        'uploaded_by' => $request->user()->id,
                        'file_path' => $path,
                        'original_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'file_size' => $file->getSize(),
                    ]));
                }

                DemandEvent::create([
                    'organization_id' => $demand->organization_id,
                    'demand_id' => $demand->id,
                    'actor_id' => $request->user()->id,
                    'event_type' => 'demand_attachments_added',
                    'summary' => $request->user()->name.' anexou '.count($files).' arquivo(s) à demanda.',
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

        return $attachments;
    }

    private function serveFile(Request $request, Demand $demand, DemandAttachment $attachment)
    {
        $this->authorizeTeamAccess($request, $demand);
        abort_unless($attachment->demand_id === $demand->id && $attachment->organization_id === $request->user()->organization_id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        $safeName = str_replace(["\r", "\n"], '', $attachment->original_name);

        $path = Storage::disk('local')->path($attachment->file_path);
        $response = $request->boolean('download')
            ? response()->download($path, $safeName)
            : response()->file($path, ['Content-Disposition' => HeaderUtils::makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $safeName)]);

        $response->headers->set('Content-Type', $attachment->mime_type);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function attachmentData(Demand $demand, DemandAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->file_size,
            'uploaded_by' => ['id' => $attachment->uploader->id, 'name' => $attachment->uploader->name],
            'created_at' => $attachment->created_at?->toISOString(),
            'preview_url' => route('api.v1.demand-attachments.show', [$demand, $attachment]),
            'download_url' => route('api.v1.demand-attachments.show', [$demand, $attachment, 'download' => 1]),
        ];
    }

    private function authorizeTeamAccess(Request $request, Demand $demand): void
    {
        $this->authorize('view', $demand);
        abort_if($request->user()->role === UserRole::Client, 404);
    }
}
