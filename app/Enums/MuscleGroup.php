<?php

namespace App\Enums;

/**
 * What an exercise trains.
 *
 * This is the grouping axis the exercise dashboard breaks down by — the
 * counterpart to Category on the spending side, but a fixed enum rather than a
 * table: the set of muscle groups is anatomy, not taxonomy, so there is nothing
 * for a user to add.
 *
 * Cardio is in the list on purpose. It is not a muscle, but it is how people
 * group their training, and leaving it out would force every run to be filed
 * under FullBody where it would distort the strength breakdown.
 */
enum MuscleGroup: string
{
    case Chest = 'chest';
    case Back = 'back';
    case Legs = 'legs';
    case Shoulders = 'shoulders';
    case Arms = 'arms';
    case Core = 'core';
    case FullBody = 'full_body';
    case Cardio = 'cardio';

    public function label(): string
    {
        return match ($this) {
            self::Chest => __('Chest'),
            self::Back => __('Back'),
            self::Legs => __('Legs'),
            self::Shoulders => __('Shoulders'),
            self::Arms => __('Arms'),
            self::Core => __('Core'),
            self::FullBody => __('Full body'),
            self::Cardio => __('Cardio'),
        };
    }

    /**
     * The palette colour this group reads as on the dashboard.
     *
     * Fixed per group rather than per exercise type: the breakdown chart is
     * grouped by muscle, so colouring it from the individual lift would make
     * one bar into confetti. Mirrors how CategorySeeder colours by theme.
     */
    public function color(): CategoryColor
    {
        return match ($this) {
            self::Chest => CategoryColor::Red,
            self::Back => CategoryColor::Blue,
            self::Legs => CategoryColor::Purple,
            self::Shoulders => CategoryColor::Amber,
            self::Arms => CategoryColor::Orange,
            self::Core => CategoryColor::Teal,
            self::FullBody => CategoryColor::Indigo,
            self::Cardio => CategoryColor::Green,
        };
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $group) => [
            'value' => $group->value,
            'label' => $group->label(),
            'color' => $group->color()->value,
        ], self::cases());
    }
}
