<?php

namespace App\Http\Controllers;

use App\Http\Requests\QrScanRequest;
use App\Services\QrScanService;
use Illuminate\Http\JsonResponse;

class QrScanController extends Controller
{
    public function __construct(protected QrScanService $service)
    {
    }

    public function scan(QrScanRequest $request): JsonResponse
    {
        $result = $this->service->handle($request->validated());

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}