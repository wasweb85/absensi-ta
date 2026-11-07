<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FonnteWebhookController extends ChatbotController
{
    /**
     * Alias handler utama webhook Fonnte (Menerima pesan masuk & callback delivery status).
     */
    public function handle(Request $request): JsonResponse
    {
        return $this->webhook($request);
    }
}
