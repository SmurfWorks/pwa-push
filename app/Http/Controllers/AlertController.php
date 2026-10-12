<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertAlertRequest;
use App\Models\Alert;
use App\Models\Device;
use App\Models\PushApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AlertController extends Controller
{
    public function index(Request $request, string $device): JsonResponse
    {
        $alerts = $this->device($request, $device)->alerts()->orderBy('deliver_at')->get();

        return response()->json([
            'notifications' => $alerts->map(fn (Alert $alert) => $alert->toPublicArray())->all(),
        ]);
    }

    public function upsert(UpsertAlertRequest $request, string $device, string $key): JsonResponse
    {
        $invalid = $this->invalidKey($key);

        if ($invalid !== null) {
            return $invalid;
        }

        $alert = Alert::schedule($this->device($request, $device), $key, [
            'deliver_at' => $request->input('deliver_at'),
            'title' => $request->string('title')->toString(),
            'body' => $request->string('body')->toString(),
            'url' => $request->input('url'),
            'icon' => $request->input('icon'),
            'tag' => $request->input('tag'),
            'grace_minutes' => $request->input('grace_minutes', 30),
        ]);

        return response()->json($alert->toPublicArray(), $alert->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, string $device, string $key): JsonResponse|Response
    {
        $invalid = $this->invalidKey($key);

        if ($invalid !== null) {
            return $invalid;
        }

        $alert = $this->device($request, $device)->alerts()->where('key', $key)->firstOrFail();
        $alert->delete();

        return response()->noContent();
    }

    public function clear(Request $request, string $device): JsonResponse
    {
        $deleted = $this->device($request, $device)->alerts()->delete();

        return response()->json(['deleted' => $deleted]);
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

    private function invalidKey(string $key): ?JsonResponse
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9:._-]{0,119}$/', $key) === 1) {
            return null;
        }

        return response()->json([
            'message' => 'Use a key made of letters, numbers, and : . _ -',
        ], 422);
    }
}
