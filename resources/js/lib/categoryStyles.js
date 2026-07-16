import {
    Book,
    Car,
    CircleDashed,
    Coffee,
    Dumbbell,
    Fuel,
    Gift,
    Heart,
    House,
    PiggyBank,
    Plane,
    Receipt,
    ShoppingBag,
    Smartphone,
    Utensils,
    Zap,
} from 'lucide-vue-next';

/**
 * Class strings are spelled out in full on purpose: Tailwind scans source text,
 * so a template-built name like `bg-${color}-500` would never be generated.
 * Keys mirror App\Enums\CategoryColor.
 */
export const CATEGORY_COLORS = {
    slate: { dot: 'bg-slate-500', badge: 'bg-slate-100 text-slate-700', bar: 'bg-slate-500' },
    red: { dot: 'bg-red-500', badge: 'bg-red-100 text-red-700', bar: 'bg-red-500' },
    orange: { dot: 'bg-orange-500', badge: 'bg-orange-100 text-orange-700', bar: 'bg-orange-500' },
    amber: { dot: 'bg-amber-500', badge: 'bg-amber-100 text-amber-700', bar: 'bg-amber-500' },
    green: { dot: 'bg-green-500', badge: 'bg-green-100 text-green-700', bar: 'bg-green-500' },
    teal: { dot: 'bg-teal-500', badge: 'bg-teal-100 text-teal-700', bar: 'bg-teal-500' },
    blue: { dot: 'bg-blue-500', badge: 'bg-blue-100 text-blue-700', bar: 'bg-blue-500' },
    indigo: { dot: 'bg-indigo-500', badge: 'bg-indigo-100 text-indigo-700', bar: 'bg-indigo-500' },
    purple: { dot: 'bg-purple-500', badge: 'bg-purple-100 text-purple-700', bar: 'bg-purple-500' },
    pink: { dot: 'bg-pink-500', badge: 'bg-pink-100 text-pink-700', bar: 'bg-pink-500' },
};

export const COLOR_NAMES = Object.keys(CATEGORY_COLORS);

/** Keys mirror App\Enums\CategoryIcon. */
export const CATEGORY_ICONS = {
    utensils: Utensils,
    car: Car,
    receipt: Receipt,
    'shopping-bag': ShoppingBag,
    'circle-dashed': CircleDashed,
    house: House,
    coffee: Coffee,
    plane: Plane,
    gift: Gift,
    heart: Heart,
    book: Book,
    dumbbell: Dumbbell,
    smartphone: Smartphone,
    zap: Zap,
    'piggy-bank': PiggyBank,
    fuel: Fuel,
};

export const ICON_NAMES = Object.keys(CATEGORY_ICONS);

export function colorClasses(color) {
    return CATEGORY_COLORS[color] ?? CATEGORY_COLORS.slate;
}

export function iconComponent(icon) {
    return CATEGORY_ICONS[icon] ?? null;
}
