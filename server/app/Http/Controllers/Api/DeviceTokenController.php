<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceToken\DestroyDeviceTokenRequest;
use App\Http\Requests\DeviceToken\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends Controller
{
    /**
     * Register or update a device token for push notifications.
     */
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $token = DeviceToken::updateOrCreate(
            ['token' => $request->input('token')],
            ['user_id' => $request->user()->id]
        );

        return response()->json([
            'message' => 'Device token registered successfully.',
            'device_token' => [
                'id' => $token->id,
                'token' => (string) $token->token,
                'user_id' => $token->user_id,
            ],
        ]);
    }

    /**
     * Unregister a device token.
     */
    public function destroy(DestroyDeviceTokenRequest $request): JsonResponse
    {
        $request->user()->deviceTokens()
            ->where('token', $request->input('token'))
            ->delete();

        return response()->json([
            'message' => 'Device token unregistered successfully.',
        ]);
    }
}
