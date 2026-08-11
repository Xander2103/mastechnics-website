<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Hvac\HvacCompatibilityCsvImporter;
use App\Services\Hvac\HvacCsvImporter;
use App\Services\Hvac\Import\TabularFileReader;
use App\Services\Hvac\Import\XlsxReadException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class HvacImportController extends Controller
{
    private const CACHE_TTL = 3600;

    /**
     * Application-level upload limit in kilobytes, from
     * config('hvac.import.max_upload_mb') (HVAC_IMPORT_MAX_MB). The effective
     * limit is the LOWEST of this value, PHP's upload_max_filesize /
     * post_max_size and the web server's body limit — see
     * docs/hvac/import-deployment.md.
     */
    public static function maxUploadKb(): int
    {
        return max(1, (int) config('hvac.import.max_upload_mb', 25)) * 1024;
    }

    /** @return array<string, string> */
    private static function uploadMessages(): array
    {
        $maxMb = max(1, (int) config('hvac.import.max_upload_mb', 25));

        return [
            'file.max'        => "Het bestand is groter dan de maximale bestandsgrootte van {$maxMb} MB.",
            'file.extensions' => 'Alleen .csv, .txt of .xlsx-bestanden worden aanvaard.',
        ];
    }

    /**
     * File contents as CSV text. XLSX files (template layout: headers on the
     * first filled row of the first visible sheet) are converted through the
     * safe workbook reader — formulas are never evaluated, macro files are
     * rejected. Files with a genuinely different layout belong in the guided
     * mapping import instead.
     *
     * @throws XlsxReadException
     */
    private function contentsAsCsv(Request $request): string
    {
        $file = $request->file('file');

        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
            return (string) file_get_contents($file->getRealPath());
        }

        $data = (new TabularFileReader())->rows($file->getRealPath(), 'xlsx');

        $stream = fopen('php://temp', 'r+');
        foreach ($data['rows'] as $cells) {
            $values = array_map(fn ($c) => (string) ($c ?? ''), $cells);
            if (array_filter($values, fn ($v) => trim($v) !== '') === []) {
                continue; // skip fully empty spacer rows
            }
            fputcsv($stream, $values, ';', '"', '\\');
        }
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    public function index(): View
    {
        return view('admin.hvac.imports.index');
    }

    public function preview(Request $request, HvacCsvImporter $importer): View|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:' . self::maxUploadKb()],
            'mode' => ['required', 'in:create_and_update,create_only,update_only'],
        ], self::uploadMessages());

        try {
            $contents = $this->contentsAsCsv($request);
        } catch (XlsxReadException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $parsed = $importer->parse($contents);

        if ($parsed['rows'] === []) {
            return back()->withErrors(['file' => implode(' ', $parsed['global_errors']) ?: 'Het bestand kon niet gelezen worden.']);
        }

        $token = Str::random(40);
        Cache::put("hvac-import:{$token}", [
            'rows' => $parsed['rows'],
            'mode' => $request->string('mode')->toString(),
        ], self::CACHE_TTL);

        $rows = collect($parsed['rows']);

        return view('admin.hvac.imports.preview', [
            'token'        => $token,
            'mode'         => $request->string('mode')->toString(),
            'rows'         => $rows->take(100),
            'totalRows'    => $rows->count(),
            'createCount'  => $rows->where('action', 'create')->whereStrict('errors', [])->count(),
            'updateCount'  => $rows->where('action', 'update')->whereStrict('errors', [])->count(),
            'errorCount'   => $rows->filter(fn ($r) => $r['errors'] !== [])->count(),
            'globalErrors' => $parsed['global_errors'],
        ]);
    }

    public function confirm(Request $request, HvacCsvImporter $importer): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string', 'size:40']]);
        $token = $request->string('token')->toString();

        $payload = Cache::pull("hvac-import:{$token}");
        if ($payload === null) {
            return redirect()->route('admin.hvac.import.index')
                ->withErrors(['file' => 'De voorbereide import is verlopen. Upload het bestand opnieuw.']);
        }

        $result = $importer->import($payload['rows'], $payload['mode']);

        $errorRows = array_values(array_filter($payload['rows'], fn ($r) => $r['errors'] !== []));
        $reportToken = null;
        if ($errorRows !== []) {
            $reportToken = Str::random(40);
            Cache::put("hvac-import-errors:{$reportToken}", $errorRows, self::CACHE_TTL);
        }

        return redirect()->route('admin.hvac.import.index')->with([
            'success'             => 'hvac_import_done',
            'import_result'       => $result + ['errors' => count($errorRows)],
            'import_error_token'  => $reportToken,
        ]);
    }

    public function errorReport(string $token): Response
    {
        $rows = Cache::get("hvac-import-errors:{$token}");
        abort_if($rows === null, 404);

        return response(HvacCsvImporter::errorReport($rows), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="hvac-import-fouten.csv"',
        ]);
    }

    public function template(): Response
    {
        return response(HvacCsvImporter::template(), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="hvac-producten-sjabloon.csv"',
        ]);
    }

    // ── Compatibility import ──────────────────────────────────────────────────

    public function compatPreview(Request $request, HvacCompatibilityCsvImporter $importer): View|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:' . self::maxUploadKb()],
        ], self::uploadMessages());

        try {
            $contents = $this->contentsAsCsv($request);
        } catch (XlsxReadException $e) {
            return back()->withErrors(['compat_file' => $e->getMessage()]);
        }

        $parsed = $importer->parse($contents);

        if ($parsed['rows'] === []) {
            return back()->withErrors(['compat_file' => implode(' ', $parsed['global_errors']) ?: 'Het bestand kon niet gelezen worden.']);
        }

        $token = Str::random(40);
        Cache::put("hvac-compat-import:{$token}", ['rows' => $parsed['rows']], self::CACHE_TTL);

        $rows = collect($parsed['rows']);

        return view('admin.hvac.imports.compat-preview', [
            'token'        => $token,
            'rows'         => $rows->take(100),
            'totalRows'    => $rows->count(),
            'validCount'   => $rows->filter(fn ($r) => $r['errors'] === [])->count(),
            'errorCount'   => $rows->filter(fn ($r) => $r['errors'] !== [])->count(),
            'globalErrors' => $parsed['global_errors'],
        ]);
    }

    public function compatConfirm(Request $request, HvacCompatibilityCsvImporter $importer): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string', 'size:40']]);

        $payload = Cache::pull('hvac-compat-import:' . $request->string('token')->toString());
        if ($payload === null) {
            return redirect()->route('admin.hvac.import.index')
                ->withErrors(['compat_file' => 'De voorbereide import is verlopen. Upload het bestand opnieuw.']);
        }

        $result = $importer->import($payload['rows']);

        return redirect()->route('admin.hvac.import.index')->with([
            'success'              => 'hvac_compat_import_done',
            'compat_import_result' => $result,
        ]);
    }

    public function compatTemplate(): Response
    {
        return response(HvacCompatibilityCsvImporter::template(), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="hvac-compatibiliteit-sjabloon.csv"',
        ]);
    }
}
