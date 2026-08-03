<?php

namespace App\Http\Controllers\Api;

use App\CommercialFactory\Services\CommercialFactoryIntakeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SiteFactoryIntakeRequest;
use Illuminate\Http\JsonResponse;
use Throwable;

class SiteFactoryIntakeController extends Controller
{
    public function __invoke(
        SiteFactoryIntakeRequest $request,
        CommercialFactoryIntakeService $service
    ): JsonResponse {
        try {
            $report = $service->intake([
                'product' => $request->string('product')->toString(),
                'client' => $request->string('client')->toString(),
                'plan' => $request->string('plan')->toString(),
                'email' => $request->input('email'),
                'domain' => $request->input('domain'),
                'phone' => $request->input('phone'),
                'source' => $request->string('source')->toString(),
                'notes' => $request->input('notes'),
            ], (bool) config('site_factory.dry_run_default', true));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => 'Não foi possível processar o pedido comercial.',
                'error' => app()->hasDebugModeEnabled() ? $exception->getMessage() : null,
            ], 500);
        }

        return response()->json([
            'ok' => $report['status'] === 'finished',
            'status' => $report['status'],
            'commercial_status' => $report['commercial_status'] ?? null,
            'project_slug' => $report['project_slug'] ?? null,
            'report_path' => $report['path'] ?? null,
            'message' => $report['status'] === 'finished'
                ? 'Pedido recebido e enviado para homologação da Factory.'
                : 'Pedido recebido, mas a Factory retornou falha.',
        ], $report['status'] === 'finished' ? 201 : 422);
    }
}
