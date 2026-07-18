<?php

namespace App\Enums;

/**
 * Values are lucide icon names, mapped to imported components in
 * resources/js/lib/exerciseStyles.js — icons cannot be resolved dynamically
 * from a string alone.
 *
 * Separate from CategoryIcon rather than an extension of it: that enum is the
 * spending taxonomy's vocabulary (receipts, fuel, groceries) and this one is
 * movements. Sharing a list would offer 'pizza' when naming a lift.
 */
enum ExerciseIcon: string
{
    case Dumbbell = 'dumbbell';
    case Weight = 'weight';
    case Activity = 'activity';
    case HeartPulse = 'heart-pulse';
    case Footprints = 'footprints';
    case Bike = 'bike';
    case Waves = 'waves';
    case Mountain = 'mountain';
    case Timer = 'timer';
    case Flame = 'flame';
    case Zap = 'zap';
    case TrendingUp = 'trending-up';
    case Target = 'target';
    case Anchor = 'anchor';
    case ArrowUp = 'arrow-up';
    case ArrowDown = 'arrow-down';
    case MoveHorizontal = 'move-horizontal';
    case MoveVertical = 'move-vertical';
    case CircleDashed = 'circle-dashed';
    case Accessibility = 'accessibility';
}
