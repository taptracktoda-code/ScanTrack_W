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
        $data = $request->validated();
        $data['admin_id'] = $request->user()->id;
        $result = $this->service->handle($data);

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}