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
    case ShoppingCart = 'shopping-cart';
    case Pill = 'pill';
    case Stethoscope = 'stethoscope';
    case FileText = 'file-text';
    case Film = 'film';
    case Music = 'music';
    case Gamepad2 = 'gamepad-2';
    case PawPrint = 'paw-print';
    case Bus = 'bus';
    case TrainFront = 'train-front';
    case Hotel = 'hotel';
    case Briefcase = 'briefcase';
    case Landmark = 'landmark';
    case CreditCard = 'credit-card';
}
