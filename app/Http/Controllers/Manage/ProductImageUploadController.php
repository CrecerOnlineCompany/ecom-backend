<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProductImageUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $file = $validated['file'];
        $directory = public_path('uploads/products');

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $extension = $file->guessExtension() ?: $file->extension() ?: 'jpg';
        $name = Str::uuid()->toString() . '.' . $extension;
        $file->move($directory, $name);

        return response()->json([
            'id' => $name,
            'url' => "/uploads/products/{$name}",
        ]);
    }
}
