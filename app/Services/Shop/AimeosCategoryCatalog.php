<?php

namespace App\Services\Shop;

class AimeosCategoryCatalog
{
    public function __construct(private readonly AimeosProductCatalog $products)
    {
    }

    public function list(): array
    {
        $categories = [];

        foreach ($this->products->list() as $product) {
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

    public function update(string $category, array $data): array
    {
        $name = trim($data['name'] ?? $category) ?: $category;
        $updated = 0;

        foreach ($this->products->list() as $product) {
            if (mb_strtolower($product['category'] ?? 'General') !== mb_strtolower($category)) {
                continue;
            }

            $this->products->save([
                ...$product,
                'category' => $name,
            ], $product['sku']);
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
