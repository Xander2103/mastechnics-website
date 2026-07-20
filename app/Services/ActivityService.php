<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\CustomerRequestNote;

/**
 * Builds an activity timeline from data that already exists (status
 * timestamps, notes, quote timestamps, mail logs) instead of a separate
 * event-log table — there is nothing to keep in sync, it just reads the
 * truthful columns that already record when each thing happened.
 */
class ActivityService
{
    /**
     * Newest-first timeline for a single request.
     */
    public static function timelineFor(CustomerRequest $request): array
    {
        $events = [];

        $events[] = self::event($request->created_at, 'Aanvraag aangemaakt');

        if ($request->viewed_at) {
            $events[] = self::event($request->viewed_at, 'Bekeken');
        }
        if ($request->contacted_at) {
            $events[] = self::event($request->contacted_at, 'Gecontacteerd');
        }
        if ($request->quote_sent_at) {
            $events[] = self::event($request->quote_sent_at, 'Offerte verstuurd');
        }
        if ($request->won_at) {
            $events[] = self::event($request->won_at, 'Gewonnen');
        }
        if ($request->lost_at) {
            $events[] = self::event($request->lost_at, 'Verloren');
        }

        $quote = $request->relationLoaded('quote') ? $request->quote : $request->quote()->first();
        if ($quote) {
            $events[] = self::event($quote->created_at, 'Offerte aangemaakt', $quote->quote_number);
            if ($quote->accepted_at) {
                $events[] = self::event($quote->accepted_at, 'Offerte aanvaard', $quote->quote_number);
            }
            if ($quote->rejected_at) {
                $events[] = self::event($quote->rejected_at, 'Offerte afgewezen', $quote->quote_number);
            }
        }

        $notes = $request->relationLoaded('notes') ? $request->notes : $request->notes()->get();
        foreach ($notes as $note) {
            $events[] = self::event($note->created_at, 'Interne notitie', $note->body, $note->author_email);
        }

        $mailLogs = $request->relationLoaded('mailLogs') ? $request->mailLogs : $request->mailLogs()->get();
        foreach ($mailLogs as $log) {
            $statusLabel = $log->status === 'sent' ? 'verzonden' : 'mislukt';
            $events[] = self::event($log->created_at, "E-mail {$statusLabel}: {$log->subject}", $log->recipient);
        }

        usort($events, fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return $events;
    }

    /**
     * Latest internal notes across all requests, for the dashboard's
     * "recent activity" widget.
     */
    public static function recentActivity(int $limit = 8): array
    {
        return CustomerRequestNote::with('customerRequest')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (CustomerRequestNote $note): array => [
                'date'    => $note->created_at,
                'action'  => 'Notitie bij ' . ($note->customerRequest->customer_name ?? '—'),
                'note'    => $note->body,
                'user'    => $note->author_email,
                'request' => $note->customerRequest,
            ])
            ->all();
    }

    private static function event($date, string $action, ?string $note = null, ?string $user = null): array
    {
        return ['date' => $date, 'action' => $action, 'note' => $note, 'user' => $user];
    }
}
