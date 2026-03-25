<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Api\BaseController;
use App\Services\Communication\MessageOutboxService;
use App\Services\Communication\TwilioWebhookValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TwilioMessagingWebhookController extends BaseController
{
    public function __construct(
        private TwilioWebhookValidator $validator,
        private MessageOutboxService $messageOutboxService,
    ) {
    }

    public function status(Request $request)
    {
        if (!$this->validator->isValid($request, (string) config('services.twilio.status_callback_url', ''))) {
            Log::warning('Rejected Twilio messaging webhook because signature validation failed.', [
                'ip' => $request->ip(),
                'sid' => $request->input('MessageSid'),
            ]);

            return response()->json(['message' => 'Invalid Twilio signature.'], 403);
        }

        $payload = $request->all();
        $message = $this->messageOutboxService->handleProviderStatusCallback($payload);

        Log::info('Processed Twilio messaging status callback.', [
            'sid' => $payload['MessageSid'] ?? null,
            'status' => $payload['MessageStatus'] ?? null,
            'matched_message_outbox_id' => $message?->id,
        ]);

        return response()->json(['ok' => true]);
    }
}
