<?php

namespace Umutcangungormus\LaravelImportExport\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Umutcangungormus\LaravelImportExport\Http\Requests\InitializeImportRequest;
use Umutcangungormus\LaravelImportExport\Http\Resources\ImportColumnMappingResource;
use Umutcangungormus\LaravelImportExport\Http\Resources\ImportSessionResource;
use Umutcangungormus\LaravelImportExport\Services\ImportExportService;

class ImportSessionController extends Controller
{
    public function __construct(
        private ImportExportService $importExportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sessions = $this->importExportService->listSessions(
            userId: $request->user()?->getAuthIdentifier(),
            status: $request->query('status'),
            perPage: (int) $request->query('per_page', 15),
        );

        return response()->json([
            'data' => ImportSessionResource::collection($sessions)->toArray($request),
            'meta' => [
                'total' => $sessions->total(),
                'per_page' => $sessions->perPage(),
                'current_page' => $sessions->currentPage(),
            ],
        ]);
    }

    public function store(InitializeImportRequest $request): JsonResponse
    {
        $session = $this->importExportService->initialize(
            $request->toDto(tenantId: $this->importExportService->currentTenantId()),
            userId: $request->user()?->getAuthIdentifier(),
        );

        return response()->json(
            ['data' => (new ImportSessionResource($session))->toArray($request)],
            201,
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $session = $this->importExportService->show($id);

        return response()->json([
            'data' => (new ImportSessionResource($session->load('columnMappings')))->toArray($request),
        ]);
    }

    public function start(Request $request, int $id): JsonResponse
    {
        $this->importExportService->start($id);

        return response()->json(null, 202);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->importExportService->cancel($id);

        return response()->json(null, 204);
    }

    public function progress(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->importExportService->getProgress($id)]);
    }

    public function failuresSummary(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->importExportService->getFailuresSummary($id)]);
    }

    public function exportFailures(Request $request, int $id)
    {
        return $this->importExportService->exportFailures($id);
    }

    public function mappings(Request $request, int $id): JsonResponse
    {
        $mappings = $this->importExportService->listMappings($id);

        return response()->json([
            'data' => ImportColumnMappingResource::collection($mappings)->toArray($request),
        ]);
    }
}
