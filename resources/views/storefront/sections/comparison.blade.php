{{-- Us against the alternatives the customer is already weighing.

     A table of ticks and crosses because that is what a person comparing
     things draws for themselves — read in a second, no prose to wade through.
     The product's own column is lifted and coloured; it is the one the eye
     should land on. --}}
@php
    $rows = collect($settings['rows'])->filter(fn ($row) => filled($row['feature']));
    $us = $settings['us_label'] ?: $product->name;
    $columns = collect([['key' => 'us', 'label' => $us]])
        ->push(['key' => 'them_1', 'label' => $settings['them_1']])
        ->when(filled($settings['them_2']), fn ($c) => $c->push(['key' => 'them_2', 'label' => $settings['them_2']]))
        ->filter(fn ($column) => filled($column['label']));
@endphp

@if ($rows->isNotEmpty() && $columns->count() >= 2)
<section class="px-4 py-10 md:px-5 md:py-14">
    <div class="mx-auto max-w-2xl">
        <h2 class="mb-6 text-center text-balance text-[24px] font-extrabold leading-snug tracking-tight md:text-[32px]">{{ $settings['title'] }}</h2>

        <div class="ios-group overflow-hidden">
            <div class="grid items-end gap-2 px-3 pt-4 text-center text-[13px] font-semibold"
                 style="grid-template-columns: minmax(0, 1.6fr) repeat({{ $columns->count() }}, minmax(0, 1fr))">
                <span></span>
                @foreach ($columns as $column)
                    <span @class([
                        'truncate rounded-t-xl px-1 py-2',
                        'bg-primary text-primary-foreground' => $column['key'] === 'us',
                        'text-muted-foreground' => $column['key'] !== 'us',
                    ])>{{ $column['label'] }}</span>
                @endforeach
            </div>

            @foreach ($rows as $row)
                <div class="grid items-center gap-2 px-3 text-[14px] hairline-t"
                     style="grid-template-columns: minmax(0, 1.6fr) repeat({{ $columns->count() }}, minmax(0, 1fr))">
                    <span class="py-3.5 font-medium leading-snug">{{ $row['feature'] }}</span>
                    @foreach ($columns as $column)
                        <span @class(['flex h-full items-center justify-center py-3.5', 'bg-primary/[0.07]' => $column['key'] === 'us'])>
                            @if ($row[$column['key']] ?? false)
                                <span class="flex size-6 items-center justify-center rounded-full bg-success text-white" aria-label="نعم">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                </span>
                            @else
                                <span class="flex size-6 items-center justify-center rounded-full bg-muted text-muted-foreground" aria-label="لا">
                                    <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                                </span>
                            @endif
                        </span>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
