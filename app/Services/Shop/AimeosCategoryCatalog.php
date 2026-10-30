<?php

namespace App\Services\Shop;

class AimeosCategoryCatalog
{
    public function __construct(private readonly AimeosProductCatalog $products)
    {
    }

    public function list(string $site = 'default'): array
    {
        $categories = [];

        foreach ($this->products->list($site) as $product) {
            $name = trim($product['category'] ?? '') ?: 'General';
            $key = mb_strtolower($name);

            if (!isset($categories[$key])) {
                $categories[$key] = [
                    'id' => $name,
                    'name' => $name,
                    'meta' => $name,
                    'products' => 0,
                    'status' => 'Activo',
                    'updatedAt' => null,
                ];
            }

            $categories[$key]['products']++;
        }

        return array_values($categories);
    }

    public function update(string $category, array $data, string $site = 'default'): array
    {
        $name = trim($data['name'] ?? $category) ?: $category;
        $updated = 0;

        foreach ($this->products->list($site) as $product) {
            if (mb_strtolower($product['category'] ?? 'General') !== mb_strtolower($category)) {
                continue;
            }

            $this->products->save([
                ...$product,
                'category' => $name,
            ], $product['sku'], $site);
            $updated++;
        }

        return [
            'id' => $name,
            'name' => $name,
            'meta' => $name,
            'products' => $updated,
            'status' => $data['status'] ?? 'Activo',
            'updatedAt' => now()->toDateTimeString(),
        ];
    }
}
