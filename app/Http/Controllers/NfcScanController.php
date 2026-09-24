<?php

namespace App\Http\Controllers;

use App\Http\Requests\NfcScanRequest;
use App\Services\NfcScanService;
use Illuminate\Http\JsonResponse;

class NfcScanController extends Controller
{
    public function __construct(protected NfcScanService $service)
    {
    }

    /**
     * Receive an NFC scan payload from a mobile client.
     */
    public function scan(NfcScanRequest $request): JsonResponse
    {
        $result = $this->service->handle($request->validated());

        return response()->json($result, 200);
    }
}
