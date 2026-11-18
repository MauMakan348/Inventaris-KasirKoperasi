<?php

namespace App\Enums;

enum StockMovementType: string
{
    case SalePos = 'SALE_POS';
    case SaleOnline = 'SALE_ONLINE';
    case Restock = 'RESTOCK';
    case Opname = 'OPNAME';
}
