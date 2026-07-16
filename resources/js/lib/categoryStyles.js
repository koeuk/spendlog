import {
    Book,
    Briefcase,
    Bus,
    Car,
    CircleDashed,
    Coffee,
    CreditCard,
    Dumbbell,
    FileText,
    Film,
    Fuel,
    Gamepad2,
    Gift,
    Heart,
    Hotel,
    House,
    Landmark,
    Music,
    PawPrint,
    PiggyBank,
    Pill,
    Plane,
    Receipt,
    ShoppingBag,
    ShoppingCart,
    Smartphone,
    Stethoscope,
    TrainFront,
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
    slate: { dot: 'bg-slate-500', badge: 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-400/15 dark:text-slate-300 dark:ring-slate-400/25', bar: 'bg-slate-500' },
    red: { dot: 'bg-red-500', badge: 'bg-red-100 text-red-700 ring-red-600/20 dark:bg-red-400/15 dark:text-red-300 dark:ring-red-400/25', bar: 'bg-red-500' },
    orange: { dot: 'bg-orange-500', badge: 'bg-orange-100 text-orange-700 ring-orange-600/20 dark:bg-orange-400/15 dark:text-orange-300 dark:ring-orange-400/25', bar: 'bg-orange-500' },
    amber: { dot: 'bg-amber-500', badge: 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-400/15 dark:text-amber-300 dark:ring-amber-400/25', bar: 'bg-amber-500' },
    green: { dot: 'bg-green-500', badge: 'bg-green-100 text-green-700 ring-green-600/20 dark:bg-green-400/15 dark:text-green-300 dark:ring-green-400/25', bar: 'bg-green-500' },
    teal: { dot: 'bg-teal-500', badge: 'bg-teal-100 text-teal-700 ring-teal-600/20 dark:bg-teal-400/15 dark:text-teal-300 dark:ring-teal-400/25', bar: 'bg-teal-500' },
    blue: { dot: 'bg-blue-500', badge: 'bg-blue-100 text-blue-700 ring-blue-600/20 dark:bg-blue-400/15 dark:text-blue-300 dark:ring-blue-400/25', bar: 'bg-blue-500' },
    indigo: { dot: 'bg-indigo-500', badge: 'bg-indigo-100 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-400/15 dark:text-indigo-300 dark:ring-indigo-400/25', bar: 'bg-indigo-500' },
    purple: { dot: 'bg-purple-500', badge: 'bg-purple-100 text-purple-700 ring-purple-600/20 dark:bg-purple-400/15 dark:text-purple-300 dark:ring-purple-400/25', bar: 'bg-purple-500' },
    pink: { dot: 'bg-pink-500', badge: 'bg-pink-100 text-pink-700 ring-pink-600/20 dark:bg-pink-400/15 dark:text-pink-300 dark:ring-pink-400/25', bar: 'bg-pink-500' },
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
    'shopping-cart': ShoppingCart,
    pill: Pill,
    stethoscope: Stethoscope,
    'file-text': FileText,
    film: Film,
    music: Music,
    'gamepad-2': Gamepad2,
    'paw-print': PawPrint,
    bus: Bus,
    'train-front': TrainFront,
    hotel: Hotel,
    briefcase: Briefcase,
    landmark: Landmark,
    'credit-card': CreditCard,
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
