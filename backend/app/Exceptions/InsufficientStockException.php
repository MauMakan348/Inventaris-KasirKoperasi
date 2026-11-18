<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    /**
     * @param  list<array{item_id: int, name: string, requested: int, available: int}>  $details
     */
    public function __construct(public readonly array $details)
    {
        parent::__construct('Stok tidak mencukupi untuk satu atau lebih barang.');
    }
}
