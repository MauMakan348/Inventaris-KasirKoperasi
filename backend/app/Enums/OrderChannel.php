<?php

namespace App\Enums;

enum OrderChannel: string
{
    case Pos = 'POS';
    case Online = 'ONLINE';
}
