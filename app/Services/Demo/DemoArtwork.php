<?php

namespace App\Services\Demo;

/**
 * Draws product artwork for the theme showrooms.
 *
 * Vector art generated here rather than stock photography: we have no licence
 * to any, and a showroom full of grey placeholder boxes reads as a broken
 * store, which is worse than no showroom at all.
 *
 * Every piece is built from the product's own hue so a catalogue looks like a
 * range rather than a colour test, and each silhouette actually resembles the
 * thing it sells — a watch face on a watch, a dropper on a serum.
 */
class DemoArtwork
{
    private const SIZE = 900;

    /** Which silhouette to draw, chosen from the product's name. */
    public function kindFor(string $name): string
    {
        $map = [
            'ساعة' => 'watch',
            'محفظة' => 'wallet',
            'سيروم' => 'dropper',
            'كريم' => 'jar',
            'واقي' => 'tube',
            'زيت' => 'dropper',
            'شامبو' => 'bottle',
            'عطر' => 'perfume',
            'قميص' => 'shirt',
            'بلوزة' => 'shirt',
            'بنطلون' => 'trousers',
            'جاكيت' => 'jacket',
            'فستان' => 'dress',
            'حذاء' => 'sneaker',
            'حلل' => 'pan',
            'خلاط' => 'blender',
            'أكواب' => 'glass',
            'مرآة' => 'mirror',
            'سجادة' => 'rug',
            'مفارش' => 'bedding',
        ];

        foreach ($map as $needle => $kind) {
            if (str_contains($name, $needle)) {
                return $kind;
            }
        }

        return 'box';
    }

    /**
     * @param  string|null  $class  Set when inlining into a page. A standalone
     *   file needs its intrinsic `width`/`height`, but those same attributes
     *   pin an inline <svg> to 900px and blow the layout apart — so a caller
     *   that inlines passes a class instead and gets no fixed size.
     */
    public function render(string $kind, int $hue, string $label = '', ?string $class = null): string
    {
        $s = self::SIZE;

        // A tight, low-saturation backdrop. Loud backgrounds fight the product
        // and make six of them side by side unreadable.
        $bgFrom = "hsl({$hue} 32% 94%)";
        $bgTo = "hsl(" . (($hue + 24) % 360) . " 26% 87%)";

        $ink = "hsl({$hue} 42% 24%)";
        $mid = "hsl({$hue} 34% 46%)";
        $light = "hsl({$hue} 40% 78%)";
        $metal = "hsl(" . (($hue + 200) % 360) . " 12% 62%)";

        $body = $this->shape($kind, $ink, $mid, $light, $metal);

        $sizing = $class === null
            ? "width=\"{$s}\" height=\"{$s}\""
            : 'class="' . e($class) . '"';

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$s} {$s}" {$sizing} role="img" aria-label="{$label}">
          <defs>
            <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="{$bgFrom}"/>
              <stop offset="1" stop-color="{$bgTo}"/>
            </linearGradient>
            <radialGradient id="glow" cx="0.5" cy="0.42" r="0.55">
              <stop offset="0" stop-color="#fff" stop-opacity=".85"/>
              <stop offset="1" stop-color="#fff" stop-opacity="0"/>
            </radialGradient>
            <filter id="soft" x="-25%" y="-25%" width="150%" height="150%">
              <feDropShadow dx="0" dy="18" stdDeviation="26" flood-color="{$ink}" flood-opacity=".22"/>
            </filter>
          </defs>

          <rect width="{$s}" height="{$s}" fill="url(#bg)"/>
          <rect width="{$s}" height="{$s}" fill="url(#glow)"/>

          <!-- Ground shadow: without it the product floats and the whole
               composition reads as clip-art. -->
          <ellipse cx="450" cy="712" rx="215" ry="30" fill="{$ink}" opacity=".13"/>

          <g filter="url(#soft)">{$body}</g>
        </svg>
        SVG;
    }

    private function shape(string $kind, string $ink, string $mid, string $light, string $metal): string
    {
        return match ($kind) {
            'watch' => <<<S
                <rect x="408" y="170" width="84" height="150" rx="26" fill="{$ink}"/>
                <rect x="408" y="580" width="84" height="150" rx="26" fill="{$ink}"/>
                <circle cx="450" cy="450" r="172" fill="{$metal}"/>
                <circle cx="450" cy="450" r="146" fill="{$ink}"/>
                <circle cx="450" cy="450" r="120" fill="{$mid}" opacity=".35"/>
                <g stroke="{$light}" stroke-width="9" stroke-linecap="round">
                  <path d="M450 450V352"/><path d="M450 450l74 46"/>
                </g>
                <circle cx="450" cy="450" r="14" fill="{$light}"/>
                <rect x="596" y="424" width="26" height="52" rx="10" fill="{$metal}"/>
            S,

            'dropper' => <<<S
                <rect x="378" y="196" width="144" height="70" rx="18" fill="{$ink}"/>
                <rect x="404" y="256" width="92" height="60" fill="{$metal}"/>
                <path d="M356 316h188a34 34 0 0 1 34 34v312a48 48 0 0 1-48 48H370a48 48 0 0 1-48-48V350a34 34 0 0 1 34-34z" fill="{$mid}"/>
                <rect x="368" y="384" width="164" height="212" rx="18" fill="{$light}" opacity=".55"/>
                <rect x="398" y="424" width="42" height="140" rx="14" fill="#fff" opacity=".45"/>
            S,

            'jar' => <<<S
                <ellipse cx="450" cy="318" rx="196" ry="52" fill="{$metal}"/>
                <path d="M254 318h392v300a86 86 0 0 1-86 86H340a86 86 0 0 1-86-86z" fill="{$mid}"/>
                <ellipse cx="450" cy="318" rx="150" ry="38" fill="{$ink}" opacity=".35"/>
                <rect x="300" y="404" width="106" height="196" rx="34" fill="#fff" opacity=".3"/>
            S,

            'tube' => <<<S
                <rect x="386" y="182" width="128" height="66" rx="16" fill="{$metal}"/>
                <path d="M356 248h188v382a80 80 0 0 1-80 80h-28a80 80 0 0 1-80-80z" fill="{$mid}"/>
                <path d="M356 248h188v70H356z" fill="{$ink}" opacity=".3"/>
                <rect x="392" y="352" width="46" height="216" rx="16" fill="#fff" opacity=".4"/>
            S,

            'bottle', 'perfume' => <<<S
                <rect x="404" y="164" width="92" height="72" rx="14" fill="{$metal}"/>
                <rect x="420" y="236" width="60" height="58" fill="{$ink}" opacity=".55"/>
                <path d="M330 294h240a56 56 0 0 1 56 56v260a94 94 0 0 1-94 94H368a94 94 0 0 1-94-94V350a56 56 0 0 1 56-56z" fill="{$mid}"/>
                <rect x="326" y="376" width="88" height="230" rx="30" fill="#fff" opacity=".32"/>
                <rect x="360" y="452" width="180" height="96" rx="14" fill="{$light}" opacity=".7"/>
            S,

            'shirt' => <<<S
                <path d="M348 214l102-38 102 38 128 66-56 122-64-28v334a30 30 0 0 1-30 30H370a30 30 0 0 1-30-30V374l-64 28-56-122z" fill="{$mid}"/>
                <path d="M450 176l58 22-58 84-58-84z" fill="{$light}"/>
                <path d="M450 282v454" stroke="{$ink}" stroke-width="7" opacity=".3"/>
            S,

            'trousers' => <<<S
                <path d="M320 200h260l24 156-14 400h-98l-42-330-42 330h-98l-14-400z" fill="{$mid}"/>
                <rect x="320" y="200" width="260" height="58" fill="{$ink}" opacity=".35"/>
                <path d="M450 258v198" stroke="{$ink}" stroke-width="6" opacity=".25"/>
            S,

            'jacket', 'dress' => <<<S
                <path d="M340 206l110-34 110 34 122 74-54 128-66-30v346a26 26 0 0 1-26 26H366a26 26 0 0 1-26-26V378l-66 30-54-128z" fill="{$mid}"/>
                <path d="M450 172l52 24-52 66-52-66z" fill="{$light}"/>
                <circle cx="450" cy="380" r="12" fill="{$ink}" opacity=".45"/>
                <circle cx="450" cy="470" r="12" fill="{$ink}" opacity=".45"/>
                <circle cx="450" cy="560" r="12" fill="{$ink}" opacity=".45"/>
            S,

            'sneaker' => <<<S
                <path d="M206 520c72-14 128-52 176-108 30-36 74-44 106-18l30 24c44 36 100 58 158 62 34 2 58 28 58 60v34a44 44 0 0 1-44 44H244a38 38 0 0 1-38-38z" fill="{$mid}"/>
                <path d="M206 596h484v42a44 44 0 0 1-44 44H244a38 38 0 0 1-38-38z" fill="{$ink}"/>
                <g stroke="{$light}" stroke-width="12" stroke-linecap="round">
                  <path d="M368 452l72 42"/><path d="M410 408l72 42"/>
                </g>
            S,

            'wallet' => <<<S
                <rect x="196" y="272" width="508" height="330" rx="42" fill="{$mid}"/>
                <rect x="196" y="272" width="508" height="110" rx="42" fill="{$ink}" opacity=".3"/>
                <rect x="470" y="392" width="234" height="106" rx="26" fill="{$ink}"/>
                <circle cx="546" cy="445" r="24" fill="{$metal}"/>
            S,

            'pan' => <<<S
                <rect x="596" y="386" width="240" height="46" rx="23" fill="{$ink}"/>
                <path d="M136 372h472v154a136 136 0 0 1-136 136H272a136 136 0 0 1-136-136z" fill="{$metal}"/>
                <ellipse cx="372" cy="372" rx="236" ry="52" fill="{$mid}"/>
                <ellipse cx="372" cy="372" rx="188" ry="38" fill="{$ink}" opacity=".4"/>
            S,

            'blender' => <<<S
                <path d="M330 178h240l-26 300H356z" fill="{$light}" opacity=".75"/>
                <path d="M330 178h240l-8 92H338z" fill="{$metal}"/>
                <path d="M336 478h228a48 48 0 0 1 48 48v112a80 80 0 0 1-80 80H368a80 80 0 0 1-80-80V526a48 48 0 0 1 48-48z" fill="{$ink}"/>
                <circle cx="450" cy="596" r="42" fill="{$mid}"/>
            S,

            'glass' => <<<S
                <path d="M318 218h264l-30 336a92 92 0 0 1-92 84h-20a92 92 0 0 1-92-84z" fill="{$light}" opacity=".8"/>
                <path d="M318 218h264l-8 88H326z" fill="{$mid}"/>
                <rect x="424" y="638" width="52" height="66" fill="{$metal}"/>
                <rect x="352" y="694" width="196" height="30" rx="15" fill="{$ink}"/>
            S,

            'mirror' => <<<S
                <circle cx="450" cy="424" r="230" fill="{$metal}"/>
                <circle cx="450" cy="424" r="192" fill="{$light}" opacity=".85"/>
                <path d="M330 320a170 170 0 0 1 120-52" stroke="#fff" stroke-width="26" stroke-linecap="round" fill="none" opacity=".6"/>
                <rect x="424" y="650" width="52" height="90" fill="{$ink}"/>
                <rect x="352" y="726" width="196" height="34" rx="17" fill="{$ink}"/>
            S,

            'rug' => <<<S
                <rect x="140" y="266" width="620" height="376" rx="20" fill="{$mid}"/>
                <rect x="182" y="308" width="536" height="292" rx="12" fill="{$light}" opacity=".7"/>
                <rect x="230" y="352" width="440" height="204" rx="8" fill="{$ink}" opacity=".3"/>
                <g stroke="{$metal}" stroke-width="10" stroke-linecap="round">
                  <path d="M300 424h300"/><path d="M300 484h300"/>
                </g>
            S,

            'bedding' => <<<S
                <rect x="150" y="392" width="600" height="240" rx="36" fill="{$mid}"/>
                <rect x="196" y="288" width="228" height="140" rx="34" fill="{$light}"/>
                <rect x="452" y="288" width="228" height="140" rx="34" fill="{$light}" opacity=".8"/>
                <rect x="150" y="500" width="600" height="40" fill="{$ink}" opacity=".22"/>
            S,

            default => <<<S
                <rect x="220" y="286" width="460" height="392" rx="30" fill="{$mid}"/>
                <path d="M220 396h460" stroke="{$ink}" stroke-width="10" opacity=".3"/>
                <rect x="404" y="286" width="92" height="392" fill="{$light}" opacity=".5"/>
            S,
        };
    }
}
