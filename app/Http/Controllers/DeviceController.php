<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Models\Device;
use App\Models\PushApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $devices = $this->pushApp($request)->devices()->orderBy('created_at')->get();

        return response()->json([
            'devices' => $devices->map(fn (Device $device) => $device->toPublicArray())->all(),
        ]);
    }

    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $app = $this->pushApp($request);
        $endpoint = $request->string('subscription.endpoint')->toString();
        $device = Device::query()->where('endpoint', $endpoint)->first();

        if ($device !== null && $device->push_app_id !== $app->id) {
            return response()->json([
                'message' => 'This phone is already registered to another app.',
            ], 409);
        }

        $created = $device === null;

        if ($device === null) {
            $device = new Device([
                'push_app_id' => $app->id,
                'endpoint' => $endpoint,
            ]);
        }

        $device->public_key = $request->string('subscription.keys.p256dh')->toString();
        $device->auth_token = $request->string('subscription.keys.auth')->toString();
        $device->content_encoding = $request->input('subscription.contentEncoding') ?: 'aes128gcm';
        $device->save();

        return response()->json($device->toPublicArray(), $created ? 201 : 200);
    }

    public function destroy(Request $request, string $device): Response
    {
        $this->device($request, $device)->delete();

        return response()->noContent();
    }

    private function pushApp(Request $request): PushApp
    {
        $app = $request->attributes->get('pushApp');

        if (! $app instanceof PushApp) {
            abort(401, 'Unknown app token.');
        }

        return $app;
    }

    private function device(Request $request, string $id): Device
    {
        return $this->pushApp($request)->devices()->whereKey($id)->firstOrFail();
    }
}
