<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'ADMIN';
    case Cashier = 'CASHIER';
    case Member = 'MEMBER';
}
