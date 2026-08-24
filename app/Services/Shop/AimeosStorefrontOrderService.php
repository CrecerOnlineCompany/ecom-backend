<?php

namespace App\Services\Shop;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AimeosStorefrontOrderService
{
    public function create(string $store, array $data): array
    {
        $items = array_values($data['items'] ?? []);

        if ($items === []) {
            throw new RuntimeException('El carrito esta vacio.');
        }

        $now = CarbonImmutable::now();
        $siteId = $this->siteId($store);
        $invoice = 'ORD-' . strtoupper($store) . '-' . $now->format('His');
        $total = array_reduce($items, fn ($sum, $item) => $sum + ((float) ($item['price'] ?? 0) * (float) ($item['quantity'] ?? 1)), 0.0);

        return DB::transaction(function () use ($data, $invoice, $items, $now, $siteId, $store, $total) {
            $orderId = DB::table('mshop_order')->insertGetId([
                'siteid' => $siteId,
                'sitecode' => $store === 'demo' ? 'default' : $store,
                'customerid' => '',
                'relatedid' => '',
                'channel' => 'web',
                'invoiceno' => $invoice,
                'datepayment' => null,
                'datedelivery' => null,
                'statuspayment' => 4,
                'statusdelivery' => 1,
                'cdate' => $now->format('Y-m-d'),
                'cmonth' => $now->format('Y-m'),
                'cweek' => $now->format('Y-W'),
                'cwday' => (string) $now->dayOfWeekIso,
                'chour' => $now->format('H'),
                'langid' => 'es',
                'currencyid' => strtoupper($data['currency'] ?? 'USD'),
                'price' => number_format($total, 2, '.', ''),
                'costs' => '0.00',
                'rebate' => '0.00',
                'tax' => '0.0000',
                'taxflag' => 1,
                'customerref' => $data['customer']['email'] ?? null,
                'comment' => '',
                'mtime' => $now,
                'ctime' => $now,
                'editor' => 'storefront',
            ]);

            $this->insertAddress($orderId, $siteId, $data['customer'] ?? [], $now);
            $this->insertProducts($orderId, $siteId, $items, $data['currency'] ?? 'USD', $now);

            return [
                'id' => (string) $orderId,
                'order_number' => $invoice,
                'status' => 'Pendiente',
                'total' => number_format($total, 2, '.', ''),
            ];
        });
    }

    private function insertAddress(int $orderId, string $siteId, array $customer, CarbonImmutable $now): void
    {
        $name = trim($customer['name'] ?? '');
        [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, '');

        DB::table('mshop_order_address')->insert([
            'siteid' => $siteId,
            'parentid' => $orderId,
            'addrid' => '',
            'type' => 'payment',
            'salutation' => '',
            'company' => '',
            'vatid' => '',
            'title' => '',
            'firstname' => $firstName,
            'lastname' => $lastName,
            'address1' => trim($customer['address'] ?? ''),
            'address2' => '',
            'address3' => '',
            'postal' => '',
            'city' => '',
            'state' => '',
            'langid' => 'es',
            'countryid' => null,
            'mobile' => '',
            'telephone' => '',
            'telefax' => '',
            'email' => trim($customer['email'] ?? ''),
            'website' => '',
            'longitude' => null,
            'latitude' => null,
            'birthday' => null,
            'pos' => 0,
            'mtime' => $now,
            'ctime' => $now,
            'editor' => 'storefront',
        ]);
    }

    private function insertProducts(int $orderId, string $siteId, array $items, string $currency, CarbonImmutable $now): void
    {
        foreach ($items as $position => $item) {
            DB::table('mshop_order_product')->insert([
                'siteid' => $siteId,
                'parentid' => $orderId,
                'ordprodid' => null,
                'ordaddrid' => null,
                'type' => 'default',
                'prodid' => (string) ($item['id'] ?? ''),
                'parentprodid' => '',
                'prodcode' => (string) ($item['sku'] ?? $item['id'] ?? ''),
                'stocktype' => 'default',
                'vendor' => (string) ($item['vendor'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'mediaurl' => (string) ($item['image'] ?? ''),
                'target' => '',
                'timeframe' => '',
                'quantity' => (float) ($item['quantity'] ?? 1),
                'qtyopen' => 0,
                'scale' => 1,
                'currencyid' => strtoupper($currency),
                'price' => number_format((float) ($item['price'] ?? 0), 2, '.', ''),
                'costs' => '0.00',
                'rebate' => '0.00',
                'tax' => '0.0000',
                'taxrate' => '{}',
                'taxflag' => 1,
                'flags' => 0,
                'pos' => $position,
                'statuspayment' => 4,
                'statusdelivery' => 1,
                'notes' => '',
                'mtime' => $now,
                'ctime' => $now,
                'editor' => 'storefront',
            ]);
        }
    }

    private function siteId(string $store): string
    {
        try {
            $code = $store === 'demo' ? 'default' : $store;
            $siteId = DB::table('mshop_locale_site')->where('code', $code)->value('siteid');

            return $siteId ?: '1.';
        } catch (Throwable) {
            return '1.';
        }
    }
}
