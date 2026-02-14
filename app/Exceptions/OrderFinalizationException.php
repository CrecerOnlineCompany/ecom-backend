<?php

namespace App\Exceptions;

class OrderFinalizationException extends \Exception
{
    protected $order_id;

    public function __construct($message = "Order finalization failed", $order_id = null, \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->order_id = $order_id;
    }

    public function getOrderId()
    {
        return $this->order_id;
    }
}
