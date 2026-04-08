<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Candy Bar / Concessions Catalog
    |--------------------------------------------------------------------------
    |
    | Catálogo simple para productos adicionales en checkout.
    | Se identifica por "code" y se usa para validar/preciar items en backend.
    |
    */
    'products' => [
        'POCHO_SMALL' => [
            'name' => 'Pochoclo chico',
            'type' => 'product',
            'unit_price' => 350.00,
            'currency' => 'ARS',
            'is_active' => true,
        ],
        'POCHO_MEDIUM' => [
            'name' => 'Pochoclo mediano',
            'type' => 'product',
            'unit_price' => 500.00,
            'currency' => 'ARS',
            'is_active' => true,
        ],
        'GASEOSA_500' => [
            'name' => 'Gaseosa 500ml',
            'type' => 'product',
            'unit_price' => 450.00,
            'currency' => 'ARS',
            'is_active' => true,
        ],
        'COMBO_POCHO_GASEOSA_MEDIUM' => [
            'name' => 'Combo pochoclo mediano + gaseosa',
            'type' => 'combo',
            'unit_price' => 850.00,
            'currency' => 'ARS',
            'is_active' => true,
        ],
    ],
];
