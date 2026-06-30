<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AnalysisStatus;
use App\Models\DocumentAnalysis;
use App\Models\Expense;

/**
 * Flags a freshly-analysed bilag that looks like one you've ALREADY booked —
 * e.g. you forgot and snapped a second photo of the same receipt. Advisory only
 * (the user still decides); it never blocks booking.
 *
 * Signals, strongest first: identical file bytes, same invoice number / KID,
 * then same amount + date (the catch-all for a re-photographed receipt).
 */
class DuplicateFinder
{
    /** @return array{expense: Expense, reason: string}|null */
    public function forAnalysis(DocumentAnalysis $analysis): ?array
    {
        $s = $analysis->suggested ?? [];

        // 1. Exact same file already attached to a booked expense.
        if ($hash = $analysis->document?->hash) {
            $expense = Expense::whereHas('document', fn ($q) => $q->where('hash', $hash))
                ->where('document_analysis_id', '!=', $analysis->id)
                ->latest('date')->first();
            if ($expense) {
                return ['expense' => $expense, 'reason' => 'samme fil'];
            }
        }

        // 2. Same invoice number or KID on an already-confirmed analysis.
        foreach (['invoice_number', 'kid'] as $key) {
            $value = $s[$key] ?? null;
            if (! $value) {
                continue;
            }
            $match = DocumentAnalysis::where('status', AnalysisStatus::Confirmed->value)
                ->where('id', '!=', $analysis->id)
                ->where("suggested->{$key}", $value)
                ->whereNotNull('confirmed_expense_id')
                ->first();
            if ($match && ($expense = Expense::find($match->confirmed_expense_id))) {
                return ['expense' => $expense, 'reason' => $key === 'kid' ? 'samme KID' : 'samme fakturanr.'];
            }
        }

        // 3. Same amount + date (the re-photographed-receipt catch-all).
        $total = (int) ($s['total_ore'] ?? 0);
        $date = $s['date'] ?? null;
        if ($total > 0 && $date) {
            $query = Expense::where('amount_ore', $total)
                ->whereDate('date', $date)
                ->where(fn ($w) => $w->whereNull('document_analysis_id')->orWhere('document_analysis_id', '!=', $analysis->id));

            // Prefer a same-vendor hit, but a bare amount+date match still counts.
            $vendor = trim((string) ($s['vendor'] ?? ''));
            $expense = ($vendor !== '' ? (clone $query)->where('vendor', $vendor)->first() : null) ?? $query->latest('date')->first();
            if ($expense) {
                return ['expense' => $expense, 'reason' => 'samme beløp og dato'];
            }
        }

        return null;
    }
}
