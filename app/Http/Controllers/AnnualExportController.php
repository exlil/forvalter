<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\TaxExport\AnnualReport;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Year-end exports (brief §6): a PDF summary for the skattemelding, a spreadsheet
 * of the figures, and a ZIP of every bilag for the year so the owner can hand
 * over complete documentation. All read-only.
 */
class AnnualExportController extends Controller
{
    public function pdf(int $year): Response
    {
        $report = (new AnnualReport($year))->build();

        $pdf = Pdf::loadView('exports.annual-report-pdf', ['r' => $report])
            ->setPaper('a4', 'portrait');

        return $pdf->download("arsoppgjor-{$year}.pdf");
    }

    public function spreadsheet(int $year): StreamedResponse
    {
        $report = (new AnnualReport($year))->build();
        $kr = fn (Money $m) => number_format($m->ore / 100, 2, ',', '');

        $rows = [];
        $rows[] = ["Årsoppgjør {$year} — Forvalter"];
        $rows[] = [];
        $rows[] = ['Eiendom', 'Leieinntekt', 'Vedlikehold', 'Drift', 'Finans', 'Kjøring', 'Avskrivning', 'Sum fradrag', 'Netto resultat', 'Påkostning (aktivert)'];

        foreach ($report['properties'] as $p) {
            $rows[] = [
                $p['name'],
                $kr($p['income']),
                $kr($p['costs']['maintenance']),
                $kr($p['costs']['operating']),
                $kr($p['costs']['finance']),
                $kr($p['mileage']['deduction']),
                $kr($p['depreciation']),
                $kr($p['deductible_total']),
                $kr($p['net']),
                $kr($p['improvement']),
            ];
        }

        $felles = $report['felles_mileage'];
        if ($felles['deduction']->ore > 0) {
            $rows[] = ['Felles kjøring', '0,00', '', '', '', $kr($felles['deduction']), '', $kr($felles['deduction']), '-'.$kr($felles['deduction']), ''];
        }

        $t = $report['totals'];
        $rows[] = [];
        $rows[] = ['Sum', $kr($t['income']), '', '', '', '', '', $kr($t['deductible']), $kr($t['net']), $kr($t['improvement'])];
        $rows[] = ['Per eier (50 %)', '', '', '', '', '', '', '', $kr($t['per_owner']), ''];

        $rows[] = [];
        $doc = $report['documentation'];
        $rows[] = ['Dokumentasjon', "{$doc['with_doc']} av {$doc['total_expenses']} utgifter har bilag", "{$doc['missing']} mangler"];

        $filename = "arsoppgjor-{$year}.csv";

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders æøå
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function bilag(int $year): StreamedResponse|Response
    {
        $expenses = Expense::with('property', 'document')
            ->where('income_year', $year)
            ->whereNotNull('document_id')
            ->get()
            ->filter(fn (Expense $e) => $e->document !== null);

        if ($expenses->isEmpty()) {
            return back()->with('error', "Ingen bilag å eksportere for {$year}.");
        }

        $tmp = tempnam(sys_get_temp_dir(), 'bilag');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $manifest = ["Eiendom;Dato;Leverandør;Beløp;Kostnadstype;Filnavn"];
        $used = [];

        foreach ($expenses as $e) {
            $doc = $e->document;
            $bytes = Storage::disk($doc->disk)->get($doc->path);
            if ($bytes === null) {
                continue;
            }

            $folder = $this->slug($e->property?->name ?? 'Eiendom');
            $date = $e->date?->format('Y-m-d') ?? 'udatert';
            $who = $this->slug($e->vendor ?: ($e->category ?: 'bilag'));
            $kr = number_format($e->amount_ore->ore / 100, 0, '', '');
            $ext = pathinfo($doc->original_filename ?? $doc->path, PATHINFO_EXTENSION) ?: 'pdf';

            $name = "{$folder}/{$date}_{$who}_kr{$kr}.{$ext}";
            // Avoid collisions when two bilag share date+vendor+amount.
            $base = $name;
            $i = 2;
            while (isset($used[$name])) {
                $name = preg_replace('/\.'.preg_quote($ext, '/').'$/', "-{$i}.{$ext}", $base);
                $i++;
            }
            $used[$name] = true;

            $zip->addFromString($name, $bytes);
            $manifest[] = implode(';', [
                $e->property?->name ?? '—',
                $e->date?->format('d.m.Y') ?? '',
                $e->vendor ?: '',
                number_format($e->amount_ore->ore / 100, 2, ',', ''),
                $e->type->label(),
                $name,
            ]);
        }

        $zip->addFromString('_innhold.csv', "\xEF\xBB\xBF".implode("\n", $manifest));
        $zip->close();

        return response()->streamDownload(function () use ($tmp) {
            readfile($tmp);
            @unlink($tmp);
        }, "bilag-{$year}.zip", ['Content-Type' => 'application/zip']);
    }

    private function slug(string $value): string
    {
        return Str::of($value)->ascii()->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-')->limit(40, '')->value() ?: 'bilag';
    }
}
