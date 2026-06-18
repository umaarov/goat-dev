<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\RegisterDeviceRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registration of push-notification device tokens (FCM).
 */
class DeviceController extends ApiController
{
    /**
     * GET /me/devices
     */
    public function index(Request $request): JsonResponse
    {
        $devices = $request->user()->deviceTokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn (DeviceToken $d) => [
                'id' => $d->id,
                'platform' => $d->platform,
                'last_used_at' => $d->last_used_at?->toIso8601String(),
                'created_at' => $d->created_at?->toIso8601String(),
            ]);

        return $this->ok($devices);
    }

    /**
     * POST /me/devices — register or refresh a device token.
     *
     * Tokens are globally unique, so a token previously bound to another
     * account (shared device, account switch) is re-assigned to the caller.
     */
    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        $device = DeviceToken::updateOrCreate(
            ['token' => $request->token],
            [
                'user_id' => $request->user()->id,
                'platform' => $request->platform,
                'last_used_at' => now(),
            ],
        );

        return $this->created([
            'id' => $device->id,
            'platform' => $device->platform,
        ]);
    }

    /**
     * DELETE /me/devices — unregister a device token (e.g. on logout).
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        $request->user()->deviceTokens()->where('token', $request->token)->delete();

        return $this->message('Device unregistered.');
    }
}
