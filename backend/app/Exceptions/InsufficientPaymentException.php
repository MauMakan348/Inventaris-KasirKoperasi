<?php

namespace App\Exceptions;

use Exception;

class InsufficientPaymentException extends Exception
{
    public function __construct(
        public readonly int $totalAmount,
        public readonly int $amountPaid,
    ) {
        parent::__construct('Jumlah pembayaran kurang dari total transaksi.');
    }
}
