<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AdminMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener el modelo Admin\Menu si existe
        $menuModel = config('admin.database.menu_model');
        
        if (!class_exists($menuModel)) {
            $this->command->warn('Admin menu model not found. Skipping menu seeding.');
            return;
        }

        // Limpiar menús existentes
        //$menuModel::query()->delete();

        // Crear menú raíz
        $this->createMenus();
    }

    private function createMenus(): void
    {
        $menuModel = config('admin.database.menu_model');

        // Menú principal - Dashboard
        $menuModel::create([
            'title' => 'Dashboard',
            'icon' => 'fa-bar-chart',
            'uri' => '/',
            'parent_id' => 0,
            'order' => 1,
        ]);

        // Menú - Cines
        $cinemaMenu = $menuModel::create([
            'title' => 'Gestión de Cines',
            'icon' => 'fa-building',
            'uri' => null,
            'parent_id' => 0,
            'order' => 2,
        ]);

        $menuModel::create([
            'title' => 'Cines',
            'icon' => 'fa-th-list',
            'uri' => 'cinemas',
            'parent_id' => $cinemaMenu->id,
            'order' => 1,
        ]);

        // Menú - Películas
        $movieMenu = $menuModel::create([
            'title' => 'Gestión de Películas',
            'icon' => 'fa-film',
            'uri' => null,
            'parent_id' => 0,
            'order' => 3,
        ]);

        $menuModel::create([
            'title' => 'Películas',
            'icon' => 'fa-th-list',
            'uri' => 'movies',
            'parent_id' => $movieMenu->id,
            'order' => 1,
        ]);

        // Menú - Salas
        $roomMenu = $menuModel::create([
            'title' => 'Gestión de Salas',
            'icon' => 'fa-chair',
            'uri' => null,
            'parent_id' => 0,
            'order' => 4,
        ]);

        $menuModel::create([
            'title' => 'Salas',
            'icon' => 'fa-th-list',
            'uri' => 'rooms',
            'parent_id' => $roomMenu->id,
            'order' => 1,
        ]);

        // Menú - Funciones
        $screeningMenu = $menuModel::create([
            'title' => 'Gestión de Funciones',
            'icon' => 'fa-clock-o',
            'uri' => null,
            'parent_id' => 0,
            'order' => 5,
        ]);

        $menuModel::create([
            'title' => 'Funciones',
            'icon' => 'fa-th-list',
            'uri' => 'screenings',
            'parent_id' => $screeningMenu->id,
            'order' => 1,
        ]);

        // Menú - Entradas
        $ticketMenu = $menuModel::create([
            'title' => 'Gestión de Entradas',
            'icon' => 'fa-ticket',
            'uri' => null,
            'parent_id' => 0,
            'order' => 6,
        ]);

        $menuModel::create([
            'title' => 'Entradas',
            'icon' => 'fa-th-list',
            'uri' => 'tickets',
            'parent_id' => $ticketMenu->id,
            'order' => 1,
        ]);
$menuModel::create([
            'title' => 'Proveedores de Pago',
            'icon' => 'fa-th-list',
            'uri' => 'payment-providers',
            'parent_id' => $ticketMenu->id,
            'order' => 2,
        ]);
        $menuModel::create([
            'title' => 'Promociones',
            'icon' => 'fa-tags',
            'uri' => 'promotions',
            'parent_id' => $ticketMenu->id,
            'order' => 3,
        ]);
        $menuModel::create([
            'title' => 'Productos',
            'icon' => 'fa-shopping-bag',
            'uri' => 'products',
            'parent_id' => $ticketMenu->id,
            'order' => 4,
        ]);
        $this->command->info('Admin menus seeded successfully.');
    }
}
