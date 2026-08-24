<?php

namespace App\Services\Shop;

use Aimeos\MShop\Exception as MShopException;
use Aimeos\MShop;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AimeosProductCatalog
{
    private const REFS = ['price', 'text', 'media'];

    public function list(string $site = 'default', bool $publicOnly = false): array
    {
        $manager = MShop::create($this->context($site), 'product');
        $filter = $manager->filter($publicOnly)
            ->add('product.code', '=~', '')
            ->slice(0, 200);

        if ($publicOnly) {
            $filter->add('product.status', '==', 1);
        }

        return $manager->search($filter, self::REFS)
            ->map(fn ($item) => $this->toArray($item))
            ->values()
            ->toArray();
    }

    public function get(string $code, string $site = 'default'): array
    {
        return $this->toArray($this->find($code, $site));
    }

    public function save(array $data, ?string $previousCode = null, string $site = 'default'): array
    {
        $context = $this->context($site);
        $manager = MShop::create($context, 'product');
        $code = $this->cleanCode($data['sku'] ?? '', $data['name'] ?? '');

        try {
            $product = $previousCode ? $manager->find($previousCode, self::REFS) : $manager->find($code, self::REFS);
        } catch (MShopException $exception) {
            if ((int) $exception->getCode() !== 404) {
                throw $exception;
            }

            $product = $manager->create()->setType('default');
        }

        $status = ($data['status'] ?? 'Borrador') === 'Publicado' ? 1 : 0;

        $product
            ->setType('default')
            ->setCode($code)
            ->setLabel(trim($data['name']))
            ->setUrl(Str::slug($data['name'] ?: $code))
            ->setStatus($status)
            ->setInStock((int) (($data['stock'] ?? 0) > 0));

        $this->replaceRefs($product, 'price');
        $this->replaceRefs($product, 'text');
        $this->replaceRefs($product, 'media');

        $this->addPrice($context, $manager, $product, $data);
        $this->addTexts($context, $manager, $product, $data);
        $this->addMedia($context, $manager, $product, $data);

        try {
            $saved = $manager->save($product);
            $this->indexProduct($context, $saved);

            return $this->toArray($manager->find($saved->getCode(), self::REFS));
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No se pudo guardar el producto en la base de datos: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    public function delete(string $code, string $site = 'default'): void
    {
        $context = $this->context($site);
        $manager = MShop::create($context, 'product');
        $product = $manager->find($code, self::REFS);

        try {
            $this->replaceRefs($product, 'price');
            $this->replaceRefs($product, 'text');
            $this->replaceRefs($product, 'media');
            $manager->save($product);
            $manager->delete($product->getId());
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No se pudo eliminar el producto de la base de datos: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    private function find(string $code, string $site)
    {
        return MShop::create($this->context($site), 'product')->find($code, self::REFS);
    }

    private function context(string $site)
    {
        $context = app('aimeos.context')->get(false, 'command');
        $context->setEditor(auth()->user()?->email ?: 'custom-api');
        $context->setLocale(MShop::create($context, 'locale')->bootstrap($site, '', '', false, null, true));

        return $context;
    }

    private function addPrice($context, $productManager, $product, array $data): void
    {
        $price = MShop::create($context, 'price')->create()
            ->setType('default')
            ->setLabel($product->getLabel())
            ->setCurrencyId(strtoupper($data['currency'] ?? 'USD'))
            ->setValue($this->decimal($data['price'] ?? 0))
            ->setCosts($this->decimal($data['cost'] ?? 0))
            ->setRebate('0.00')
            ->setTaxRate('0.00')
            ->setQuantity(1)
            ->setStatus(1);

        $product->addListItem('price', $productManager->createListItem()->setType('default')->setPosition(0)->setStatus(1), $price);
    }

    private function addTexts($context, $productManager, $product, array $data): void
    {
        $textManager = MShop::create($context, 'text');

        $name = $textManager->create()
            ->setType('name')
            ->setDomain('product')
            ->setLanguageId('es')
            ->setLabel($product->getLabel())
            ->setContent($product->getLabel())
            ->setStatus(1);

        $product->addListItem('text', $productManager->createListItem()->setType('default')->setPosition(0)->setStatus(1), $name);

        if (trim($data['description'] ?? '') !== '') {
            $description = $textManager->create()
                ->setType('long')
                ->setDomain('product')
                ->setLanguageId('es')
                ->setLabel($product->getLabel() . ' descripcion')
                ->setContent(trim($data['description']))
                ->setStatus(1);

            $product->addListItem('text', $productManager->createListItem()->setType('default')->setPosition(1)->setStatus(1), $description);
        }

        $variants = array_values(array_filter($data['variants'] ?? [], fn ($variant) => ($variant['color'] ?? '') || ($variant['size'] ?? '') || ($variant['stock'] ?? 0)));
        $metadata = [
            'barcode' => $data['barcode'] ?? '',
            'category' => $data['category'] ?? 'General',
            'vendor' => $data['vendor'] ?? '',
            'variants' => $variants,
        ];

        if ($metadata['barcode'] || $metadata['category'] !== 'General' || $metadata['vendor'] || $variants) {
            $variantText = $textManager->create()
                ->setType('meta-description')
                ->setDomain('product')
                ->setLanguageId('es')
                ->setLabel($product->getLabel() . ' metadata')
                ->setContent(json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                ->setStatus(1);

            $product->addListItem('text', $productManager->createListItem()->setType('hidden')->setPosition(2)->setStatus(1), $variantText);
        }
    }

    private function addMedia($context, $productManager, $product, array $data): void
    {
        $mediaManager = MShop::create($context, 'media');

        foreach (array_values($data['images'] ?? []) as $position => $image) {
            $url = $image['url'] ?? null;

            if (!$url) {
                continue;
            }

            $media = $mediaManager->create()
                ->setType('default')
                ->setDomain('product')
                ->setLanguageId(null)
                ->setLabel($image['id'] ?? basename($url))
                ->setUrl($url)
                ->setPreview($url)
                ->setMimeType('image/jpeg')
                ->setStatus(1);

            $product->addListItem('media', $productManager->createListItem()->setType('default')->setPosition($position)->setStatus(1), $media);
        }
    }

    private function indexProduct($context, $product): void
    {
        $manager = MShop::create($context, 'index');
        $manager->begin();

        try {
            $manager->save(clone $product);
            $manager->commit();
        } catch (Throwable $exception) {
            $manager->rollback();

            throw $exception;
        }
    }

    private function replaceRefs($product, string $domain): void
    {
        $product->deleteListItems($product->getListItems($domain, null, null, false), true);
    }

    private function toArray($product): array
    {
        $prices = $product->getRefItems('price', null, null, false)->values();
        $texts = $product->getRefItems('text', null, null, false)->values();
        $mediaItems = [];

        foreach ($product->getListItems('media', null, null, false) as $listItem) {
            if ($refItem = $listItem->getRefItem()) {
                $mediaItems[] = [
                    'position' => $listItem->getPosition(),
                    'item' => $refItem,
                ];
            }
        }

        usort($mediaItems, fn ($left, $right) => $left['position'] <=> $right['position']);

        $description = '';
        $metadata = [];

        foreach ($texts as $text) {
            if ($text->getType() === 'long') {
                $description = $text->getContent();
            }

            if ($text->getType() === 'meta-description') {
                $decoded = json_decode($text->getContent(), true);
                $metadata = is_array($decoded) ? $decoded : [];
            }
        }

        $price = $prices->first();
        $variants = is_array($metadata['variants'] ?? null) ? $metadata['variants'] : [];
        $images = array_map(fn ($entry, $index) => [
            'id' => basename($entry['item']->getUrl(false)),
            'url' => $entry['item']->getUrl(false),
            'isPrimary' => $index === 0,
        ], $mediaItems, array_keys($mediaItems));

        return [
            'id' => $product->getCode(),
            'sku' => $product->getCode(),
            'barcode' => $metadata['barcode'] ?? '',
            'name' => $product->getLabel(),
            'category' => $metadata['category'] ?? 'General',
            'vendor' => $metadata['vendor'] ?? 'Aimeos',
            'price' => $price ? $price->getValue() : '0.00',
            'cost' => $price ? $price->getCosts() : '0.00',
            'currency' => $price ? $price->getCurrencyId() : 'USD',
            'stock' => array_sum(array_map(fn ($variant) => (int) ($variant['stock'] ?? 0), $variants)),
            'status' => $product->getStatus() === 1 ? 'Publicado' : 'Borrador',
            'summary' => Str::limit($description ?: $product->getLabel(), 90),
            'description' => $description,
            'variants' => $variants,
            'colors' => array_values(array_unique(array_filter(array_column($variants, 'color')))) ?: ['Unico'],
            'sizes' => array_values(array_unique(array_filter(array_column($variants, 'size')))) ?: ['Unico'],
            'images' => $images,
            'imageCount' => count($images),
        ];
    }

    private function cleanCode(string $code, string $name): string
    {
        $code = trim($code);

        if ($code !== '') {
            return strtoupper($code);
        }

        return strtoupper(Str::slug($name ?: 'producto') . '-' . Str::random(6));
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) str_replace(',', '.', (string) $value), 2, '.', '');
    }
}
