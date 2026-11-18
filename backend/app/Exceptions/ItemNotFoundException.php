<?php

namespace App\Exceptions;

use Exception;

class ItemNotFoundException extends Exception
{
    /**
     * @param  list<int>  $itemIds
     */
    public function __construct(public readonly array $itemIds)
    {
        parent::__construct('Barang tidak ditemukan.');
    }
}
