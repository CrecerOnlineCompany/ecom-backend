<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\ShopSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopSettingController extends Controller
{
    private const DEFAULTS = [
        'commercial_name' => 'Default',
        'site_code' => 'default',
        'contact_email' => 'admin@test.com',
        'country' => 'US',
        'primary_currency' => 'USD',
        'secondary_currency' => 'EUR',
        'primary_language' => 'es',
        'timezone' => 'America/Argentina/Buenos_Aires',
    ];

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->settings(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'commercial_name' => ['required', 'string', 'max:255'],
            'site_code' => ['required', 'string', 'max:64'],
            'contact_email' => ['required', 'email', 'max:255'],
            'country' => ['required', 'string', 'max:2'],
            'primary_currency' => ['required', 'string', 'max:3'],
            'secondary_currency' => ['nullable', 'string', 'max:3'],
            'primary_language' => ['required', 'string', 'max:8'],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        foreach ($data as $key => $value) {
            ShopSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return response()->json([
            'data' => $this->settings(),
            'message' => 'Configuracion guardada.',
        ]);
    }

    private function settings(): array
    {
        $stored = ShopSetting::query()->pluck('value', 'key')->all();

        return array_replace(self::DEFAULTS, $stored);
    }
}
