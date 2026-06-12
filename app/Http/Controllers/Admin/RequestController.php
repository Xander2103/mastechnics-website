<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestController extends Controller
{
    public function index(Request $request): View
    {
        $statuses = $this->getStatuses();

        $services = collect(config('services'))
            ->filter(fn (array $service): bool => $service['is_active'] ?? false)
            ->map(function (array $service): array {
                return $service['translations']['nl'] ?? reset($service['translations']);
            })
            ->values();

        $requestTypes = collect(config('request-flow.request_types', []))
            ->mapWithKeys(function (array $requestType): array {
                return [
                    $requestType['value'] => $requestType['labels']['nl'] ?? $requestType['value'],
                ];
            })
            ->toArray();

        $urgencies = [
            'urgent' => 'Dringend',
            'within_days' => 'Binnen enkele dagen',
            'not_urgent' => 'Niet dringend',
        ];

        $customerTypes = [
            'residential' => 'Particulier',
            'business' => 'Bedrijf',
        ];

        $serviceCategoryLabels = collect(config('request-flow.service_categories', []))
            ->mapWithKeys(fn (array $cat): array => [
                $cat['value'] => $cat['labels']['nl'] ?? $cat['value'],
            ])
            ->toArray();

        $statusCounts = CustomerRequest::query()
            ->selectRaw('status, count(*) as total')
            ->whereIn('status', ['new', 'contacted', 'quote_sent'])
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'new'        => $statusCounts->get('new', 0),
            'contacted'  => $statusCounts->get('contacted', 0),
            'quote_sent' => $statusCounts->get('quote_sent', 0),
            'urgent'     => CustomerRequest::whereNotIn('status', ['won', 'lost'])
                                ->where(function ($q): void {
                                    $q->where('service_category', 'dringend_lek')
                                      ->orWhereIn('urgency_level', ['water_leaking', 'small_leak', 'no_heating', 'no_hot_water', 'urgent']);
                                })->count(),
        ];

        $customerRequests = $this->buildFilteredQuery($request)->get();

        return view('admin.requests.index', [
            'stats'            => $stats,
            'customerRequests' => $customerRequests,
            'statuses' => $statuses,
            'services' => $services,
            'requestTypes' => $requestTypes,
            'urgencies' => $urgencies,
            'customerTypes' => $customerTypes,
            'serviceCategoryLabels' => $serviceCategoryLabels,
            'filters' => [
                'search'           => $request->string('search')->toString(),
                'status'           => $request->string('status')->toString(),
                'service_slug'     => $request->string('service_slug')->toString(),
                'service_category' => $request->string('service_category')->toString(),
                'request_type'     => $request->string('request_type')->toString(),
                'urgency'          => $request->string('urgency')->toString(),
                'customer_type'    => $request->string('customer_type')->toString(),
                'date_from'        => $request->string('date_from')->toString(),
                'date_to'          => $request->string('date_to')->toString(),
            ],
        ]);
    }

    public function show(CustomerRequest $customerRequest): View
    {
        $customerRequest->load(['attachments', 'notes']);

        $serviceCategoryLabels = collect(config('request-flow.service_categories', []))
            ->mapWithKeys(fn (array $cat): array => [
                $cat['value'] => $cat['labels']['nl'] ?? $cat['value'],
            ])
            ->toArray();

        return view('admin.requests.show', [
            'customerRequest'       => $customerRequest,
            'statuses'              => $this->getStatuses(),
            'serviceCategoryLabels' => $serviceCategoryLabels,
        ]);
    }

    public function updateStatus(Request $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:new,viewed,contacted,quote_sent,won,lost,planned,done,cancelled'],
        ]);

        $customerRequest->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'status_updated');
    }

    public function storeNote(Request $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:3000'],
        ]);

        $customerRequest->notes()->create([
            'author_email' => session('admin_user_email'),
            'body' => $validated['body'],
        ]);

        return back()->with('success', 'note_created');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $statuses = $this->getStatuses();

        $serviceCategoryLabels = collect(config('request-flow.service_categories', []))
            ->mapWithKeys(fn (array $cat): array => [
                $cat['value'] => $cat['labels']['nl'] ?? $cat['value'],
            ])
            ->toArray();

        $urgencyLevelLabels = [
            'water_leaking' => 'Er staat water / ernstig lek',
            'small_leak'    => 'Klein lek',
            'no_heating'    => 'Geen verwarming',
            'no_hot_water'  => 'Geen warm water',
            'other'         => 'Andere urgentie',
            'urgent'        => 'Dringend (algemeen)',
            'within_days'   => 'Binnen enkele dagen',
            'not_urgent'    => 'Niet dringend',
        ];

        $filename = 'mastechnics-aanvragen-' . now()->format('Y-m-d') . '.csv';

        $requests = $this->buildFilteredQuery($request)->get();

        return response()->streamDownload(function () use ($requests, $statuses, $serviceCategoryLabels, $urgencyLevelLabels): void {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens the file correctly
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, [
                'Datum',
                'Naam',
                'E-mail',
                'Telefoon',
                'Categorie',
                'Status',
                'Urgentie',
                'Gewenste timing',
                'Gemeente',
                'Postcode',
                'Bron',
            ], ';');

            foreach ($requests as $req) {
                $answers  = ($req->metadata['answers'] ?? []);
                $urgency  = $req->urgency_level ?? ($answers['urgency'] ?? null);
                $category = $serviceCategoryLabels[$req->service_category] ?? $req->service_slug;

                fputcsv($handle, [
                    $req->created_at?->format('d/m/Y H:i') ?? '',
                    $this->sanitizeCsvCell($req->customer_name),
                    $this->sanitizeCsvCell($req->customer_email),
                    $this->sanitizeCsvCell($req->customer_phone),
                    $this->sanitizeCsvCell($category),
                    $statuses[$req->status] ?? $req->status,
                    $this->sanitizeCsvCell($urgencyLevelLabels[$urgency] ?? ($urgency ?? '')),
                    $this->sanitizeCsvCell($req->preferred_time),
                    $this->sanitizeCsvCell($answers['city'] ?? ''),
                    $this->sanitizeCsvCell($answers['postal_code'] ?? ''),
                    $this->sanitizeCsvCell($req->source),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function performAction(Request $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:mark_viewed,mark_contacted,mark_quote_sent,mark_won,mark_lost'],
        ]);

        match ($validated['action']) {
            'mark_viewed'     => $this->applyMarkViewed($customerRequest),
            'mark_contacted'  => $this->applyMarkContacted($customerRequest),
            'mark_quote_sent' => $this->applyMarkQuoteSent($customerRequest),
            'mark_won'        => $this->applyMarkWon($customerRequest),
            'mark_lost'       => $this->applyMarkLost($customerRequest),
        };

        return back()->with('success', 'action_applied');
    }

    private function applyMarkViewed(CustomerRequest $customerRequest): void
    {
        if ($customerRequest->status === 'new') {
            $customerRequest->update(['status' => 'viewed']);
        }
    }

    private function applyMarkContacted(CustomerRequest $customerRequest): void
    {
        $customerRequest->update([
            'status'       => 'contacted',
            'contacted_at' => $customerRequest->contacted_at ?? now(),
        ]);
    }

    private function applyMarkQuoteSent(CustomerRequest $customerRequest): void
    {
        $customerRequest->update([
            'status'        => 'quote_sent',
            'quote_sent_at' => $customerRequest->quote_sent_at ?? now(),
        ]);
    }

    private function applyMarkWon(CustomerRequest $customerRequest): void
    {
        $customerRequest->update([
            'status' => 'won',
            'won_at' => $customerRequest->won_at ?? now(),
        ]);
    }

    private function applyMarkLost(CustomerRequest $customerRequest): void
    {
        $customerRequest->update([
            'status'  => 'lost',
            'lost_at' => $customerRequest->lost_at ?? now(),
        ]);
    }

    private function buildFilteredQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return CustomerRequest::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($q) use ($search): void {
                    $q->where('customer_name', 'LIKE', "%{$search}%")
                      ->orWhere('customer_email', 'LIKE', "%{$search}%")
                      ->orWhere('customer_phone', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('service_slug'), function ($query) use ($request): void {
                $query->where('service_slug', $request->string('service_slug')->toString());
            })
            ->when($request->filled('service_category'), function ($query) use ($request): void {
                $query->where('service_category', $request->string('service_category')->toString());
            })
            ->when($request->filled('request_type'), function ($query) use ($request): void {
                $query->where('request_type', $request->string('request_type')->toString());
            })
            ->when($request->filled('urgency'), function ($query) use ($request): void {
                $query->where('metadata->answers->urgency', $request->string('urgency')->toString());
            })
            ->when($request->filled('customer_type'), function ($query) use ($request): void {
                $query->where('metadata->answers->customer_type', $request->string('customer_type')->toString());
            })
            ->when($request->filled('date_from'), function ($query) use ($request): void {
                $query->whereDate('created_at', '>=', $request->string('date_from')->toString());
            })
            ->when($request->filled('date_to'), function ($query) use ($request): void {
                $query->whereDate('created_at', '<=', $request->string('date_to')->toString());
            })
            ->latest();
    }

    private function sanitizeCsvCell(mixed $value): string
    {
        $str = (string) ($value ?? '');
        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $str = "'" . $str;
        }
        return $str;
    }

    private function getStatuses(): array
    {
        return [
            'new'        => 'Nieuw',
            'viewed'     => 'Bekeken',
            'contacted'  => 'Gecontacteerd',
            'quote_sent' => 'Offerte verstuurd',
            'won'        => 'Gewonnen',
            'lost'       => 'Verloren',
            // backwards compat — display-only, not offered as new UI actions
            'planned'    => 'Ingepland (oud)',
            'done'       => 'Afgewerkt (oud)',
            'cancelled'  => 'Geannuleerd (oud)',
        ];
    }
}