<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\LeaveLog;

/**
 * Leave duration is always inclusive of the first and last day and is always
 * computed server-side from the two dates.
 */
final class LeaveLogObserver
{
    public function saving(LeaveLog $leave): void
    {
        if ($leave->start_date && $leave->end_date) {
            $leave->total_days = LeaveLog::inclusiveDays($leave->start_date, $leave->end_date);
        }

        if ($leave->status?->qualifiesForFoodDeduction() && $leave->approved_at === null) {
            $leave->approved_at = now();
        }
    }
}
