<?php

namespace App\Services;

use App\Models\CustomerRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * Computes follow-up reminder / badge state for a customer request from
 * existing timestamps — no reminders are persisted, nothing is sent
 * automatically. Thresholds live in config('site.reminders').
 *
 * Single source of truth for "is this urgent / overdue / waiting" so the
 * request list badges, the dashboard widgets, and the notification center
 * all agree with each other.
 */
class ReminderService
{
    public const NEW = 'NEW';
    public const URGENT = 'URGENT';
    public const WAITING = 'WAITING';
    public const FOLLOW_UP = 'FOLLOW_UP';
    public const OVERDUE = 'OVERDUE';

    public static function isUrgent(CustomerRequest $request): bool
    {
        if (in_array($request->status, ['won', 'lost'], true)) {
            return false;
        }

        if (in_array($request->service_category, config('site.urgent_categories', []), true)) {
            return true;
        }

        return in_array($request->urgency_level, config('site.urgent_levels', []), true);
    }

    public static function isNewNotViewed(CustomerRequest $request): bool
    {
        return $request->status === 'new'
            && $request->created_at
            && $request->created_at->lt(now()->subHours(config('site.reminders.new_not_viewed_hours')));
    }

    public static function isWaitingContact(CustomerRequest $request): bool
    {
        return $request->status === 'viewed'
            && $request->viewed_at
            && $request->viewed_at->lt(now()->subHours(config('site.reminders.contact_not_contacted_hours')));
    }

    public static function isQuoteAwaitingReply(CustomerRequest $request): bool
    {
        return $request->status === 'quote_sent'
            && $request->quote_sent_at
            && $request->quote_sent_at->lt(now()->subDays(config('site.reminders.quote_awaiting_reply_days')));
    }

    public static function isOverdue(CustomerRequest $request): bool
    {
        return ! in_array($request->status, ['won', 'lost'], true)
            && $request->updated_at
            && $request->updated_at->lt(now()->subDays(config('site.reminders.lost_inactive_days')));
    }

    /**
     * All reminder codes that currently apply to this request (used for
     * dashboard counts and the notification center).
     */
    public static function activeReminders(CustomerRequest $request): array
    {
        $reminders = [];

        if (self::isUrgent($request)) {
            $reminders[] = self::URGENT;
        }
        if (self::isNewNotViewed($request)) {
            $reminders[] = self::NEW;
        }
        if (self::isWaitingContact($request)) {
            $reminders[] = self::WAITING;
        }
        if (self::isQuoteAwaitingReply($request)) {
            $reminders[] = self::FOLLOW_UP;
        }
        if (self::isOverdue($request)) {
            $reminders[] = self::OVERDUE;
        }

        return $reminders;
    }

    /**
     * Single highest-priority badge to show next to a request in the list
     * (URGENT > OVERDUE > FOLLOW_UP > WAITING > NEW).
     */
    public static function primaryBadge(CustomerRequest $request): ?array
    {
        $active = self::activeReminders($request);
        if (empty($active)) {
            return null;
        }

        $priority = [self::URGENT, self::OVERDUE, self::FOLLOW_UP, self::WAITING, self::NEW];
        $labels = [
            self::URGENT    => 'Dringend',
            self::OVERDUE   => 'Achterstallig',
            self::FOLLOW_UP => 'Opvolgen',
            self::WAITING   => 'Wachten op contact',
            self::NEW       => 'Nieuw',
        ];
        $classes = [
            self::URGENT    => 'admin-badge-urgent',
            self::OVERDUE   => 'admin-badge-overdue',
            self::FOLLOW_UP => 'admin-badge-followup',
            self::WAITING   => 'admin-badge-waiting',
            self::NEW       => 'admin-badge-new',
        ];

        foreach ($priority as $code) {
            if (in_array($code, $active, true)) {
                return ['code' => $code, 'label' => $labels[$code], 'class' => $classes[$code]];
            }
        }

        return null;
    }

    /**
     * Reusable "still open and urgent" query constraint — mirrors
     * isUrgent() so SQL-level counts and per-record checks never drift.
     */
    public static function scopeUrgentOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['won', 'lost'])
            ->where(function (Builder $q): void {
                $q->whereIn('service_category', config('site.urgent_categories', []))
                  ->orWhereIn('urgency_level', config('site.urgent_levels', []));
            });
    }
}
