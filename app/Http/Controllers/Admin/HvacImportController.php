<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Hvac\HvacCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class HvacImportController extends Controller
{
    private const CACHE_TTL = 3600;

    public function index(): View
    {
        return view('admin.hvac.imports.index');
    }

    public function preview(Request $request, HvacCsvImporter $importer): View|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
            'mode' => ['required', 'in:create_and_update,create_only,update_only'],
        ]);

        $parsed = $importer->parse((string) file_get_contents($request->file('file')->getRealPath()));

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
}
