<!DOCTYPE html>
<html lang="nb">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        @page { margin: 28px 34px; }
        body { color: #2b2622; font-size: 11px; }
        h1 { font-size: 20px; margin: 0; }
        .muted { color: #8a8178; }
        .terra { color: #b5683f; }
        .right { text-align: right; }
        .head { border-bottom: 2px solid #2b2622; padding-bottom: 10px; margin-bottom: 16px; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .summary td { padding: 9px 12px; background: #f7f3ee; border: 2px solid #fff; width: 25%; }
        .summary .label { font-size: 9px; text-transform: uppercase; letter-spacing: .4px; color: #8a8178; }
        .summary .value { font-size: 16px; font-weight: bold; padding-top: 2px; }
        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; table-layout: fixed; font-size: 9.5px; }
        table.grid th { text-align: left; font-size: 7.5px; text-transform: uppercase; letter-spacing: .3px; color: #8a8178; border-bottom: 1px solid #ddd6cc; padding: 6px 4px; }
        table.grid td { padding: 6px 4px; border-bottom: 1px solid #efeae2; }
        table.grid td.right, table.grid th.right { text-align: right; white-space: nowrap; }
        table.grid tr.total td { border-top: 2px solid #2b2622; border-bottom: none; font-weight: bold; }
        .name { font-weight: bold; }
        .sub { font-size: 9px; color: #8a8178; }
        .section { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #8a8178; margin: 22px 0 6px; }
        .note { background: #fbf6f1; border: 1px solid #ead9cb; padding: 10px 12px; font-size: 10px; margin-top: 6px; }
        .foot { margin-top: 26px; padding-top: 10px; border-top: 1px solid #ddd6cc; font-size: 9px; color: #8a8178; }
    </style>
</head>
<body>
    @php
        $t = $r['totals'];
        $fmt = fn ($m) => $m->format(decimals: true);
        $num = fn ($m) => $m->format(symbol: false, decimals: true);
    @endphp

    <div class="head">
        <table style="width:100%"><tr>
            <td><h1>Årsoppgjør {{ $r['year'] }}</h1><div class="muted" style="margin-top:3px">Skattemelding – utleie av bolig (kapitalinntekt)</div></td>
            <td class="right"><strong class="terra">Forvalter</strong><div class="muted">Underlag, ikke innsendt</div></td>
        </tr></table>
    </div>

    <table class="summary"><tr>
        <td><div class="label">Leieinntekt</div><div class="value">{{ $fmt($t['income']) }}</div></td>
        <td><div class="label">Sum fradrag</div><div class="value">{{ $fmt($t['deductible']) }}</div></td>
        <td><div class="label">Netto resultat</div><div class="value">{{ $fmt($t['net']) }}</div></td>
        <td><div class="label">Per eier (50 %)</div><div class="value">{{ $fmt($t['per_owner']) }}</div></td>
    </tr></table>

    <div class="section">Resultat per eiendom <span style="text-transform:none;letter-spacing:0">(beløp i NOK)</span></div>
    <table class="grid">
        <thead><tr>
            <th style="width:22%">Eiendom</th>
            <th class="right" style="width:12%">Leieinntekt</th>
            <th class="right" style="width:11%">Vedlikehold</th>
            <th class="right" style="width:10%">Drift</th>
            <th class="right" style="width:10%">Finans</th>
            <th class="right" style="width:11%">Kjøring</th>
            <th class="right" style="width:11%">Avskrivning</th>
            <th class="right" style="width:13%">Netto</th>
        </tr></thead>
        <tbody>
        @foreach ($r['properties'] as $p)
            <tr>
                <td><span class="name">{{ $p['name'] }}</span>
                    @if ($p['mileage']['km'] > 0)<div class="sub">{{ $p['mileage']['km'] }} km kjøring</div>@endif
                </td>
                <td class="right">{{ $num($p['income']) }}</td>
                <td class="right">{{ $num($p['costs']['maintenance']) }}</td>
                <td class="right">{{ $num($p['costs']['operating']) }}</td>
                <td class="right">{{ $num($p['costs']['finance']) }}</td>
                <td class="right">{{ $num($p['mileage']['deduction']) }}</td>
                <td class="right">{{ $num($p['depreciation']) }}</td>
                <td class="right"><strong>{{ $num($p['net']) }}</strong></td>
            </tr>
        @endforeach
        @if ($r['felles_mileage']['deduction']->ore > 0)
            <tr>
                <td><span class="name">Felles kjøring</span><div class="sub">{{ $r['felles_mileage']['km'] }} km, ikke knyttet til eiendom</div></td>
                <td class="right">–</td><td class="right">–</td><td class="right">–</td><td class="right">–</td>
                <td class="right">{{ $num($r['felles_mileage']['deduction']) }}</td>
                <td class="right">–</td>
                <td class="right"><strong>−{{ $num($r['felles_mileage']['deduction']) }}</strong></td>
            </tr>
        @endif
            <tr class="total">
                <td>Sum</td>
                <td class="right">{{ $num($t['income']) }}</td>
                <td class="right">–</td>
                <td class="right">–</td>
                <td class="right">–</td>
                <td class="right" colspan="2">Fradrag {{ $num($t['deductible']) }}</td>
                <td class="right">{{ $num($t['net']) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($t['improvement']->ore > 0 || $t['depreciation']->ore > 0)
        <div class="note">
            <strong>Påkostning {{ $fmt($t['improvement']) }}</strong> er ikke fradragsført i år – den aktiveres på eiendommens inngangsverdi og reduserer gevinst ved et senere salg.
            @if ($t['depreciation']->ore > 0) Avskrivning på driftsmidler i år: {{ $fmt($t['depreciation']) }}. @endif
        </div>
    @endif

    <div class="section">Dokumentasjon</div>
    @php $doc = $r['documentation']; @endphp
    <p style="font-size:10px;margin:0 0 6px">
        {{ $doc['with_doc'] }} av {{ $doc['total_expenses'] }} utgifter har vedlagt bilag.
        @if ($doc['missing'] > 0) <span class="terra">{{ $doc['missing'] }} mangler bilag (se under).</span> @else Alle utgifter har bilag. @endif
    </p>
    @if ($doc['missing'] > 0)
        <table class="grid">
            <thead><tr><th style="width:23%">Eiendom</th><th style="width:13%">Dato</th><th style="width:34%">Beskrivelse</th><th style="width:16%">Type</th><th class="right" style="width:14%">Beløp</th></tr></thead>
            <tbody>
            @foreach ($doc['missing_list'] as $m)
                <tr>
                    <td>{{ $m['property'] }}</td>
                    <td>{{ $m['date'] }}</td>
                    <td>{{ $m['vendor'] }}</td>
                    <td>{{ $m['type'] }}</td>
                    <td class="right">{{ $fmt($m['amount']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <div class="foot">
        Forvalter er et hjelpeverktøy for å forberede skattemeldingen – ikke skatterådgivning. Kontrollér mot Skatteetaten før innsending.
        Beløp i NOK. Eiendomsporteføljen eies 50/50.
    </div>
</body>
</html>
