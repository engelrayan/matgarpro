@if (filled($settings['text']))
<{{ $settings['link'] ? 'a' : 'div' }}
    @if ($settings['link']) href="{{ $settings['link'] }}" @endif
    @class([
        'block py-2 text-center text-xs font-medium',
        'bg-primary text-primary-foreground' => $settings['style'] === 'primary',
        'bg-foreground text-background' => $settings['style'] === 'dark',
        'bg-accent text-white' => $settings['style'] === 'accent',
    ])>{{ $settings['text'] }}</{{ $settings['link'] ? 'a' : 'div' }}>
@endif
