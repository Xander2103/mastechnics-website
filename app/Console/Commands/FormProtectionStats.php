<?php

namespace App\Console\Commands;

use App\Services\Spam\FormProtectionLog;
use App\Services\Spam\MailBudget;
use Illuminate\Console\Command;

class FormProtectionStats extends Command
{
    protected $signature = 'forms:protection-stats {--days=7 : Number of days to include (today included)}';

    protected $description = 'Show how many public-form submissions were stopped by the anti-spam layers and how much mail budget is left';

    public function handle(FormProtectionLog $log, MailBudget $budget): int
    {
        $days = max(1, (int) $this->option('days'));
        $summary = $log->summary($days);

        $this->info("Spam tegengehouden — vandaag: {$summary['today_total']}, laatste {$days} dagen: {$summary['period_total']}");
        $this->newLine();

        $rows = [];

        foreach (FormProtectionLog::REASONS as $reason) {
            $rows[] = [
                FormProtectionLog::LABELS[$reason] ?? $reason,
                $reason,
                $summary['today'][$reason],
                $summary['period'][$reason],
            ];
        }

        $this->table(['Reden', 'Sleutel', 'Vandaag', "Laatste {$days} d"], $rows);

        $circuit = $budget->circuitState();
        $this->newLine();
        $this->line('Externe-mailnoodrem (admin + klant samen): ' . $circuit['daily_used'] . '/' . $circuit['daily_limit']
            . ' vandaag, ' . $circuit['burst_used'] . '/' . $circuit['burst_limit'] . ' per 10 min — '
            . ($circuit['open']
                ? 'ACTIEF: geen verdere formuliermails (' . (FormProtectionLog::LABELS[$circuit['reason']] ?? $circuit['reason']) . ')'
                : 'gesloten, mail mogelijk'));

        try {
            $decisions = FormSecurityEvent::query()->today()
                ->selectRaw('decision, count(*) as total')
                ->groupBy('decision')
                ->pluck('total', 'decision');

            $this->line('Beslissingen vandaag: vertrouwd ' . ($decisions['trusted'] ?? 0)
                . ', te controleren ' . ($decisions['needs_review'] ?? 0)
                . ', geblokkeerd ' . ($decisions['blocked'] ?? 0));
        } catch (\Throwable $e) {
            $this->line('Beslissingen vandaag: beveiligingslog niet beschikbaar (' . $e->getMessage() . ')');
        }

        $remaining = $budget->remaining();
        $this->line('Resterend mailbudget: klantbevestigingen ' . $remaining[MailBudget::KIND_CUSTOMER]
            . ' / adminmeldingen ' . $remaining[MailBudget::KIND_ADMIN]
            . ' (dag), burst ' . $remaining['burst'] . ' (uur)'
            . ($budget->enabled() ? '' : ' — MAIL_GUARD_ENABLED=false, budget niet actief'));

        return self::SUCCESS;
    }
}
