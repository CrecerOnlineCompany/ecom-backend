<?php

namespace App\Admin\Extensions\Form\Field;

use OpenAdmin\Admin\Form\Field\Table;

class VirtualTable extends Table
{
    /**
     * For virtual builder inputs, avoid turning missing input into [false].
     */
    public function prepare($input)
    {
        if ($input === false || $input === null || $input === '') {
            return false;
        }

        return parent::prepare($input);
    }
}

