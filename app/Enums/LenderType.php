<?php

namespace App\Enums;

/**
 * Who money was borrowed from.
 *
 * A small fixed set rather than free text like an income source: the point of
 * the type is to group and filter ("what do I owe family?"), and a picker of
 * five reads faster than a list of everyone ever typed. The lender's *name*
 * stays free text beside it — that is where "Mom" and "ABA Bank" go.
 */
enum LenderType: string
{
    case Friend = 'friend';
    case Family = 'family';
    case Bank = 'bank';
    case Employer = 'employer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Friend => __('Friend'),
            self::Family => __('Family'),
            self::Bank => __('Bank'),
            self::Employer => __('Employer'),
            self::Other => __('Other'),
        };
    }

    /**
     * Every type as {value, label}, for a picker. Labels are resolved
     * server-side so they follow the app locale.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ], self::cases());
    }
}
