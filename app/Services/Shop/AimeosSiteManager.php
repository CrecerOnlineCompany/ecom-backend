<?php

namespace App\Services\Shop;

use Aimeos\MShop;
use Aimeos\MShop\Exception as MShopException;
use Illuminate\Support\Facades\DB;

class AimeosSiteManager
{
    public function list(): array
    {
        return DB::table('mshop_locale_site')
            ->select(['code', 'label', 'status', 'ctime'])
            ->where('level', 0)
            ->orderBy('label')
            ->get()
            ->map(fn ($site) => [
                'code' => $site->code,
                'label' => $site->label,
                'status' => (int) $site->status === 1 ? 'Activo' : 'Inactivo',
                'createdAt' => $site->ctime,
            ])
            ->all();
    }

    public function exists(string $code): bool
    {
        return DB::table('mshop_locale_site')->where('code', $code)->exists();
    }

    public function find(string $code): ?array
    {
        return collect($this->list())->firstWhere('code', $code);
    }

    public function siteId(string $code): ?string
    {
        return DB::table('mshop_locale_site')->where('code', $code)->value('siteid');
    }

    public function create(string $code, string $label, string $language, string $currency): array
    {
        $context = $this->context();
        $manager = MShop::create($context, 'locale/site');

        $manager->begin();
        $site = $manager->insert($manager->create()->setCode($code)->setLabel($label)->setStatus(1));
        $manager->commit();

        $localeManager = MShop::create($context, 'locale');
        $context->setLocale($localeManager->create()->setSiteId($site->getSiteId()));
        $localeManager->save(
            $localeManager->create()->setLanguageId($language)->setCurrencyId($currency)->setStatus(1)
        );

        return $this->find($code);
    }

    public function update(string $code, string $label, bool $active): array
    {
        $manager = MShop::create($this->context(), 'locale/site');

        try {
            $site = $manager->find($code);
        } catch (MShopException $exception) {
            abort(404, 'La tienda no existe.');
        }

        $manager->save($site->setLabel($label)->setStatus($active ? 1 : 0));

        return $this->find($code);
    }

    private function context()
    {
        $context = app('aimeos.context')->get(false, 'command');
        $context->setEditor(auth()->user()?->email ?: 'custom-api');

        return $context;
    }
}
