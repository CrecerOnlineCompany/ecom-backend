<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ManageProductImageUploadTest extends TestCase
{
    public function test_authenticated_admin_can_upload_product_image(): void
    {
        $directory = public_path('uploads/products');
        File::deleteDirectory($directory);

        $user = User::factory()->make([
            'id' => 1,
            'email' => 'admin@test.com',
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/admin/uploads/product-images', [
                'file' => UploadedFile::fake()->image('product.jpg', 1024, 1024),
            ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['id', 'url']);

        $this->assertFileExists(public_path(ltrim($response->json('url'), '/')));
    }
}
