<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePushAppRequest;
use App\Http\Requests\UpdatePushAppRequest;
use App\Models\PushApp;
use App\Push\PushAppRegistry;
use App\Support\Origins;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PushAppController extends Controller
{
    public function index(): JsonResponse
    {
        $apps = PushApp::query()->withCount('devices')->orderBy('name')->get();

        return response()->json([
            'apps' => $apps->map(fn (PushApp $app) => $app->toPublicArray())->all(),
        ]);
    }

    public function store(StorePushAppRequest $request, PushAppRegistry $registry): JsonResponse
    {
        try {
            ['app' => $app, 'token' => $token] = $registry->create(
                $request->string('name')->toString(),
                $request->input('origins'),
            );
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'An app with that name is already registered.'], 422);
        }

        return response()->json([
            ...$app->toPublicArray(),
            'token' => $token,
        ], 201);
    }

    public function update(UpdatePushAppRequest $request, PushApp $pushApp): JsonResponse
    {
        if ($request->exists('name')) {
            $pushApp->name = $request->string('name')->toString();
        }

        if ($request->exists('origins')) {
            $pushApp->origins = Origins::normalizeAll($request->input('origins'));
        }

        try {
            $pushApp->save();
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'An app with that name is already registered.'], 422);
        }

        $pushApp->loadCount('devices');

        return response()->json($pushApp->toPublicArray());
    }

    public function rotate(PushApp $pushApp, PushAppRegistry $registry): JsonResponse
    {
        return response()->json([
            'token' => $registry->rotate($pushApp),
        ]);
    }

    public function destroy(PushApp $pushApp): Response
    {
        $pushApp->delete();

        return response()->noContent();
    }
}
