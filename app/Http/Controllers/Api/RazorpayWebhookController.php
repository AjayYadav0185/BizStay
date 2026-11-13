<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Razorpay events. Only signed requests are accepted; anything
 * already posted is acknowledged with 200 so Razorpay stops retrying.
 */
final class RazorpayWebhookController
{
    public function __invoke(Request $request, RazorpayService $razorpay): JsonResponse
    {
        if (! $razorpay->verifySignature($request->getContent(), $request->header('X-Razorpay-Signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payment = $razorpay->handleWebhook($request->json()->all());

        return response()->json(['posted' => $payment !== null]);
    }
}
