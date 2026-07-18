<?php

namespace App\Enums;

/**
 * The unit a weight was *entered* in.
 *
 * Deliberately mirrors App\Enums\Currency: every weight is stored in one
 * canonical unit (kilograms, as every amount is stored in USD), and the unit is
 * a property of what the person typed, resolved on the way in and re-applied on
 * the way out. Nothing downstream — the volume chart, the PR list — has to know
 * a unit exists.
 */
enum WeightUnit: string
{
    case Kg = 'kg';
    case Lb = 'lb';

    /**
     * Decimal places weights are stored at.
     *
     * Three, not two, for the same reason expenses store four: one pound is
     * 0.45359237 kg, so at two places a plate-loaded barbell entered in pounds
     * rounds visibly and reads back wrong. Three puts the floor at one gram.
     *
     * The round trip is still lossy at the last place — 225 lb stores as
     * 102.058 kg and reads back as 224.999 lb — which is inherent to keeping a
     * single canonical unit, as it is for money here. Display rounds to whole
     * or half units, so it never surfaces.
     */
    public const SCALE = 3;

    /** Kilograms in one of this unit. */
    private const PER_KG = [
        'kg' => 1.0,
        'lb' => 0.45359237,
    ];

    public function label(): string
    {
        return match ($this) {
            self::Kg => __('Kilograms (kg)'),
            self::Lb => __('Pounds (lb)'),
        };
    }

    public function symbol(): string
    {
        return $this->value;
    }

    /** Convert a value typed in this unit to the stored canonical kilograms. */
    public function toKg(int|float|string $value): string
    {
        return number_format(
            (float) $value * self::PER_KG[$this->value],
            self::SCALE,
            '.',
            ''
        );
    }

    /** Convert stored kilograms back into this unit, for display and for edit forms. */
    public function fromKg(int|float|string $kilograms): string
    {
        return number_format(
            (float) $kilograms / self::PER_KG[$this->value],
            self::SCALE,
            '.',
            ''
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $unit) => [
            'value' => $unit->value,
            'label' => $unit->label(),
        ], self::cases());
    }
}
