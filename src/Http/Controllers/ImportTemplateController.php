<?php

namespace Umutcangungormus\LaravelImportExport\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Umutcangungormus\LaravelImportExport\Http\Requests\CreateTemplateRequest;
use Umutcangungormus\LaravelImportExport\Http\Requests\UpdateTemplateRequest;
use Umutcangungormus\LaravelImportExport\Http\Resources\ImportMappingTemplateResource;
use Umutcangungormus\LaravelImportExport\Services\MappingTemplateService;
use Umutcangungormus\LaravelImportExport\Tenancy\TenantResolverContract;

class ImportTemplateController extends Controller
{
    public function __construct(
        private MappingTemplateService $templateService,
        private TenantResolverContract $tenantResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $templates = $this->templateService->list(
            userId: $request->user()?->getAuthIdentifier(),
            importableType: $request->query('model'),
        );

        return response()->json([
            'data' => ImportMappingTemplateResource::collection($templates)->toArray($request),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $template = $this->templateService->show($id);

        return response()->json([
            'data' => (new ImportMappingTemplateResource($template))->toArray($request),
        ]);
    }

    public function store(CreateTemplateRequest $request): JsonResponse
    {
        $template = $this->templateService->create(
            $request->toDto(),
            userId: $request->user()?->getAuthIdentifier(),
            tenantId: $this->tenantResolver->currentTenantId(),
        );

        return response()->json([
            'data' => (new ImportMappingTemplateResource($template))->toArray($request),
        ], 201);
    }

    public function update(int $id, UpdateTemplateRequest $request): JsonResponse
    {
        $template = $this->templateService->update($id, [
            'template_name' => $request->input('template_name'),
            'description' => $request->input('description'),
            'is_default' => $request->has('is_default') ? (bool) $request->input('is_default') : null,
            'is_company_wide' => $request->has('is_company_wide') ? (bool) $request->input('is_company_wide') : null,
        ]);

        return response()->json([
            'data' => (new ImportMappingTemplateResource($template))->toArray($request),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->templateService->delete($id);

        return response()->json(null, 204);
    }
}
