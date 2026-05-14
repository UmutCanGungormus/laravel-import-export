<?php

namespace Umutcangungormus\LaravelImportExport\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Umutcangungormus\LaravelImportExport\Http\Requests\UpdateMappingRequest;
use Umutcangungormus\LaravelImportExport\Http\Resources\ImportColumnMappingResource;
use Umutcangungormus\LaravelImportExport\Services\ImportExportService;

class ImportMappingController extends Controller
{
    public function __construct(
        private ImportExportService $importExportService,
    ) {}

    public function update(int $id, UpdateMappingRequest $request): JsonResponse
    {
        $mapping = $this->importExportService->updateMapping($id, $request->toDto());

        return response()->json([
            'data' => (new ImportColumnMappingResource($mapping))->toArray($request),
        ]);
    }

    public function suggestions(Request $request, int $id): JsonResponse
    {
        $sourceColumn = (string) $request->query('source_column', '');
        $suggestions = $this->importExportService->getMappingSuggestions($id, $sourceColumn);

        return response()->json(['data' => $suggestions]);
    }
}
