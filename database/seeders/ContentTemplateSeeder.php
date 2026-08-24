<?php

namespace Database\Seeders;

use App\Models\CmsContentTemplate;
use Illuminate\Database\Seeder;

class ContentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'type' => 'product_landing',
                'name' => 'Default Product Landing',
                'handle' => 'default-product-landing',
                'description' => 'Template base estilo Shopify para ficha de producto.',
                'template_json' => [
                    'version' => 1,
                    'blocks' => [
                        ['type' => 'hero', 'settings' => ['title' => '{{ product.name }}', 'subtitle' => '{{ product.description }}']],
                        ['type' => 'media-gallery', 'settings' => ['source' => 'product.images']],
                        ['type' => 'price-box', 'settings' => ['show_compare_at' => true]],
                        ['type' => 'buy-box', 'settings' => ['show_quantity_selector' => true]],
                        ['type' => 'specs', 'settings' => ['source' => 'product.metadata.specs']],
                    ],
                ],
                'is_active' => true,
            ],
            [
                'type' => 'product_landing',
                'name' => 'Featured Product Landing',
                'handle' => 'featured-product-landing',
                'description' => 'Template de conversión con bloque destacado y prueba social.',
                'template_json' => [
                    'version' => 1,
                    'blocks' => [
                        ['type' => 'announcement', 'settings' => ['text' => 'Envío gratis en compras superiores a ARS 50.000']],
                        ['type' => 'hero', 'settings' => ['title' => '{{ product.name }}', 'layout' => 'split']],
                        ['type' => 'price-box', 'settings' => ['highlight_discount' => true]],
                        ['type' => 'reviews', 'settings' => ['source' => 'product.metadata.reviews']],
                        ['type' => 'faq', 'settings' => ['source' => 'product.metadata.faq']],
                    ],
                ],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            CmsContentTemplate::query()->updateOrCreate(
                [
                    'site' => 'default',
                    'type' => $template['type'],
                    'handle' => $template['handle'],
                ],
                ['site' => 'default'] + $template
            );
        }
    }
}
