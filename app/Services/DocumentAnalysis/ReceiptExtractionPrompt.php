<?php

declare(strict_types=1);

namespace App\Services\DocumentAnalysis;

use App\Enums\ExpenseType;

/**
 * Versioned prompt + output contract for bilag extraction. Both versions are
 * stamped onto every document_analyses row so classification quality is
 * traceable and the prompt/schema can evolve without ambiguity (brief §7).
 *
 * Bump PROMPT_VERSION when the instructions change; bump SCHEMA_VERSION when
 * the shape of the expected JSON changes.
 */
final class ReceiptExtractionPrompt
{
    public const PROMPT_VERSION = '2026-06-29.2';

    public const SCHEMA_VERSION = '3';

    /** System prompt — domain rules in Norwegian; the model reasons in NOK and bokmål. */
    public static function system(): string
    {
        $types = collect(ExpenseType::cases())
            ->map(fn (ExpenseType $t) => "- {$t->value}: {$t->label()} ({$t->taxTreatment()})")
            ->implode("\n");

        $categories = collect(config('forvalter.categories'))->map(fn ($c) => "\"{$c}\"")->implode(', ');

        return <<<PROMPT
        Du er en nøyaktig norsk regnskapsassistent for en privat utleier. Du leser et bilag
        (kvittering eller faktura) og trekker ut strukturerte data. Du gir KUN et forslag som
        et menneske skal bekrefte – du tar aldri en endelig avgjørelse.

        Regler:
        - Beløp er i norske kroner. Tolk norsk tallformat (mellomrom som tusenskille, komma som desimal).
        - Datoer på formatet yyyy-mm-dd. MVA oppgis hvis synlig, ellers null.
        - Forslå kostnadstype (kostnadstype avgjør skattemessig behandling):
        {$types}
        - Forslå én kategori fra denne listen når det passer: {$categories}.
        - **Eiendom bilaget gjelder:** hvis bilaget tydelig gjelder en bestemt eiendom (f.eks.
          kommunale avgifter, eiendomsskatt, felleskostnader/sameie, håndverker), oppgi
          eiendommens **gateadresse** i `property_hint` (f.eks. "Blekenberg 36",
          "Damsgårdsveien 88"). Dette er eiendommen kostnaden hører til — IKKE mottakerens
          eller eierens egen bostedsadresse, og ikke avsenderens/leverandørens adresse. Let
          gjerne etter felt som «Eiendom», «Gjelder eiendom», gnr/bnr, eller seksjonsnummer.
          Hvis ingen bestemt eiendom er angitt (f.eks. bompenger), sett property_hint = null.
        - Skriv en kort, konkret begrunnelse på norsk for valg av kostnadstype.
        - Sett confidence mellom 0 og 1 etter hvor sikker du er. Ved dårlig bildekvalitet eller
          uleselige felter: returner det du kan og sett lav confidence – ikke gjett.

        Bompenger / ferje (viktig særtilfelle):
        - Hvis bilaget er en fakturasammenstilling fra en bompengeoperatør eller ferjeoperatør
          (f.eks. AutoSync, Fjellinjen, Ferde, Skyttel, BroBizz – kjennetegn: «bompassering»,
          «bomstasjon», «passeringsoversikt», brikkenummer, liste over passeringer), sett
          document_type = "bompenger" og kategori = "Bompenger".
        - Trekk ut HVER enkelt passering fra den detaljerte passeringsoversikten i feltet
          "toll_passings": dato, klokkeslett, bomstasjon og beløp per passering – også
          passeringer med beløp 0,00. Ikke slå dem sammen. Behold rekkefølgen.
        - total_kroner skal fortsatt være hele fakturabeløpet. Hvilke passeringer som er
          fradragsberettiget avgjøres senere ved å matche mot kjøreboka – ikke gjør det selv.

        Svar med KUN ett gyldig JSON-objekt, uten markdown, uten forklarende tekst utenfor JSON-en.
        PROMPT;
    }

    /** The user-turn instruction that accompanies the document/image blocks. */
    public static function instruction(): string
    {
        return <<<PROMPT
        Analyser vedlagte bilag og returner KUN dette JSON-objektet:

        {
          "document_type": "kvittering | faktura | annet",
          "vendor": "leverandørnavn eller null",
          "vendor_orgnr": "9-sifret orgnr eller null",
          "date": "yyyy-mm-dd eller null",
          "due_date": "yyyy-mm-dd eller null",
          "total_kroner": tall (hele beløpet inkl. mva) eller null,
          "vat_kroner": tall (mva-beløpet) eller null,
          "currency": "NOK",
          "invoice_number": "fakturanummer eller null",
          "kid": "KID-nummer eller null",
          "property_hint": "gateadressen til eiendommen bilaget gjelder, eller null",
          "line_items": [{"description": "tekst", "amount_kroner": tall}],
          "toll_passings": [{"date": "yyyy-mm-dd", "time": "tt:mm eller null", "station": "bomstasjon", "amount_kroner": tall}],
          "suggested_category": "kategori eller null",
          "suggested_type": "maintenance | operating | finance | improvement | capital_asset",
          "rationale": "kort begrunnelse på norsk",
          "confidence": 0.0
        }

        Sett "toll_passings" til [] for alt som ikke er et bompenge-/ferjebilag.
        PROMPT;
    }
}
