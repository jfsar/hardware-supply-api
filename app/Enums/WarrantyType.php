<?php

namespace App\Enums;

enum WarrantyType: string
{
    case None = 'none';
    case Store = 'store';
    case Manufacturer = 'manufacturer';
    case Other = 'other';
}
