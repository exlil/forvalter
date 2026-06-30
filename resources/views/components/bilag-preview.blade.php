@props(['document', 'height' => 'h-40'])
{{--
    Clickable bilag thumbnail. Images render directly; PDFs show their first page
    in a (non-interactive) iframe. Clicking sets `preview` on the nearest Alpine
    scope — the Innboks provides a page-level lightbox bound to it.
--}}
@php
    $url = route('documents.show', $document);
    $isImg = str_starts_with($document->mime_type ?? '', 'image/');
@endphp
<button type="button"
    @click="preview = { url: @js($url), img: {{ $isImg ? 'true' : 'false' }}, name: @js($document->original_filename) }"
    {{ $attributes->merge(['class' => "group relative block w-full {$height} overflow-hidden rounded-xl border border-line bg-panel"]) }}>
    @if ($isImg)
        <img src="{{ $url }}" class="h-full w-full object-cover object-top" alt="Forhåndsvisning av bilag">
    @else
        <iframe src="{{ $url }}#toolbar=0&navpanes=0&view=FitH" class="pointer-events-none h-full w-full" loading="lazy" title="Forhåndsvisning av bilag"></iframe>
    @endif
    <span class="pointer-events-none absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-gradient-to-t from-ink/60 to-transparent px-3 pb-2 pt-7">
        <span class="truncate text-[11.5px] font-medium text-white">{{ $document->original_filename }}</span>
        <span class="shrink-0 rounded-full bg-canvas/95 px-2 py-0.5 text-[11px] font-semibold text-ink opacity-0 transition-opacity group-hover:opacity-100">Forstørr</span>
    </span>
</button>
