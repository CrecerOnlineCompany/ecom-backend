<?php

namespace App\Admin\Actions\Movies;

use OpenAdmin\Admin\Actions\RowAction;

class WeeklyScreeningsForm extends RowAction
{
    public $name = 'Crear funciones semanales';

    public $icon = 'icon-calendar';

    public function href()
    {
        return route('admin.movies.weekly-screenings.form', ['movie' => $this->getKey()]);
    }
}
