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
 * so a name built at runtime like `bg-${color}-500` would never be generated.
 *
 * Keys mirror App\Enums\CategoryColor.
 */
const COLORS = {
    slate: { dot: 'bg-slate-500', badge: 'bg-slate-100 text-slate-700 ring-slate-600/20', bar: 'bg-slate-500' },
    red: { dot: 'bg-red-500', badge: 'bg-red-100 text-red-700 ring-red-600/20', bar: 'bg-red-500' },
    orange: { dot: 'bg-orange-500', badge: 'bg-orange-100 text-orange-700 ring-orange-600/20', bar: 'bg-orange-500' },
    amber: { dot: 'bg-amber-500', badge: 'bg-amber-100 text-amber-700 ring-amber-600/20', bar: 'bg-amber-500' },
    green: { dot: 'bg-green-500', badge: 'bg-green-100 text-green-700 ring-green-600/20', bar: 'bg-green-500' },
    teal: { dot: 'bg-teal-500', badge: 'bg-teal-100 text-teal-700 ring-teal-600/20', bar: 'bg-teal-500' },
    blue: { dot: 'bg-blue-500', badge: 'bg-blue-100 text-blue-700 ring-blue-600/20', bar: 'bg-blue-500' },
    indigo: { dot: 'bg-indigo-500', badge: 'bg-indigo-100 text-indigo-700 ring-indigo-600/20', bar: 'bg-indigo-500' },
    purple: { dot: 'bg-purple-500', badge: 'bg-purple-100 text-purple-700 ring-purple-600/20', bar: 'bg-purple-500' },
    pink: { dot: 'bg-pink-500', badge: 'bg-pink-100 text-pink-700 ring-pink-600/20', bar: 'bg-pink-500' },
};

/** Keys mirror App\Enums\CategoryIcon. */
const ICONS = {
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

export const CATEGORY_COLOR_NAMES = Object.keys(COLORS);
export const CATEGORY_ICON_NAMES = Object.keys(ICONS);

/**
 * Always returns a class set, falling back to slate, so an unknown colour
 * renders plainly instead of throwing.
 */
export function categoryColor(color) {
    return COLORS[color] ?? COLORS.slate;
}

/** Returns null when there is no icon, which templates can v-if on. */
export function categoryIcon(icon) {
    return ICONS[icon] ?? null;
}
