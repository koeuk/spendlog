<?php

namespace App\Enums;

/**
 * Values are lucide icon names. The frontend maps each to an imported
 * component — icons cannot be resolved dynamically from a string alone.
 */
enum CategoryIcon: string
{
    case Utensils = 'utensils';
    case Car = 'car';
    case Receipt = 'receipt';
    case ShoppingBag = 'shopping-bag';
    case CircleDashed = 'circle-dashed';
    case House = 'house';
    case Coffee = 'coffee';
    case Plane = 'plane';
    case Gift = 'gift';
    case Heart = 'heart';
    case Book = 'book';
    case Dumbbell = 'dumbbell';
    case Smartphone = 'smartphone';
    case Zap = 'zap';
    case PiggyBank = 'piggy-bank';
    case Fuel = 'fuel';
}
