<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;


Route::match(['GET', 'POST'], '/v1/debug/ngrok-test', function (Request $request) {
    Log::info('NGROK TEST ROUTE HIT', [
        'method' => $request->method(),
        'full_url' => $request->fullUrl(),
        'body' => $request->all(),
        'raw' => $request->getContent(),
        'app_env' => app()->environment(),
    ]);

    return response()->json([
        'ok' => true,
        'app_env' => app()->environment(),
        'marker' => 'gottanew-debug-route',
    ]);
});

Route::prefix('v1')->group(function () {
    require __DIR__ . '/api/auth.php';
    require __DIR__ . '/api/coach.php';
    require __DIR__ . '/api/client.php';
    require __DIR__ . '/api/admin.php';
    require __DIR__ . '/api/analytics.php';
    require __DIR__ . '/api/finance.php';
    require __DIR__ . '/api/goals.php';
    require __DIR__ . '/api/questions.php';
    require __DIR__ . '/api/responses.php';
    require __DIR__ . '/api/personality.php';
    require __DIR__ . '/api/ai.php';
    require __DIR__ . '/api/session.php';
    require __DIR__ . '/api/notification.php';
    require __DIR__ . '/api/webhook.php';
});
