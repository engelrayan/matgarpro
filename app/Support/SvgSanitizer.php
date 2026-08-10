<?php

namespace App\Support;

/**
 * Strips everything executable out of an uploaded SVG.
 *
 * An SVG is not a picture — it is an XML document the browser will run. A logo
 * carrying `<script>` or an `onload=` handler, opened straight from
 * `/storage/…`, executes on **matgarpro.com's own origin**: the merchant
 * dashboard, and the platform panel. Session cookies are same-site to that
 * host. This is the one upload on the platform that can do that, because it is
 * the only one that accepts SVG at all — the others are raster-only, and a
 * malicious PNG is just a PNG.
 *
 * Allow-list, not deny-list. A blocklist of `<script>` and `on*` misses
 * `<foreignObject>`, `<animate attributeName="href">`, namespaced handlers and
 * whatever the next browser adds. Anything not named below is removed, so a
 * new attack surface has to be explicitly let in rather than explicitly
 * blocked.
 */
class SvgSanitizer
{
    /** Elements that draw. Nothing here can load or run anything. */
    private const ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc', 'metadata',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textpath',
        'lineargradient', 'radialgradient', 'stop', 'pattern',
        'clippath', 'mask', 'filter',
        'fegaussianblur', 'feoffset', 'feblend', 'feflood', 'femerge',
        'femergenode', 'fecolormatrix', 'fecomposite', 'fedropshadow',
        'style',
    ];

    /**
     * Attributes that describe geometry or paint.
     *
     * `href` and `xlink:href` are absent on purpose: they are how `<use>` and
     * `<image>` pull in a remote document, and the only safe form — a local
     * `#fragment` — is not worth the parsing to keep.
     */
    private const ATTRIBUTES = [
        'id', 'class', 'style', 'transform', 'viewbox', 'width', 'height',
        'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'd', 'points', 'fill', 'fill-opacity', 'fill-rule', 'stroke',
        'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray',
        'stroke-dashoffset', 'stroke-opacity', 'stroke-miterlimit',
        'opacity', 'offset', 'stop-color', 'stop-opacity', 'gradientunits',
        'gradienttransform', 'spreadmethod', 'clip-path', 'clip-rule', 'mask',
        'filter', 'font-family', 'font-size', 'font-weight', 'font-style',
        'text-anchor', 'letter-spacing', 'dominant-baseline', 'dx', 'dy',
        'preserveaspectratio', 'xmlns', 'version', 'stddeviation', 'result', 'in',
    ];

    /**
     * @return string|null  the cleaned document, or null when the file is not
     *                      parseable SVG at all — in which case the caller
     *                      should refuse it rather than store something it
     *                      could not read.
     */
    public static function clean(string $svg): ?string
    {
        if (trim($svg) === '') {
            return null;
        }

        /*
         | Entities are disabled before anything is parsed. An SVG can declare
         | a DTD that reads /etc/passwd into a variable and paints it into the
         | document — the file is then a perfectly valid image, and a copy of a
         | server file. libxml has had this off by default since 2.9, and it is
         | asserted here anyway because the default is not something to inherit
         | on an upload path.
         */
        $previous = libxml_use_internal_errors(true);

        $document = new \DOMDocument();
        $document->preserveWhiteSpace = false;

        // LIBXML_NONET blocks network fetches. `LIBXML_NOENT` is deliberately
        // NOT passed: despite the name it *enables* entity substitution, which
        // is the whole attack.
        $loaded = $document->loadXML($svg, LIBXML_NONET);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || ! $document->documentElement) {
            return null;
        }

        if (strtolower($document->documentElement->nodeName) !== 'svg') {
            return null;
        }

        // A DTD is never needed to draw and is the whole entity-expansion
        // attack surface, so any doctype is dropped outright.
        foreach (iterator_to_array($document->childNodes) as $node) {
            if ($node instanceof \DOMDocumentType) {
                $document->removeChild($node);
            }
        }

        self::scrub($document->documentElement);

        return $document->saveXML($document->documentElement) ?: null;
    }

    private static function scrub(\DOMElement $element): void
    {
        // Snapshotted: removing a child while iterating the live NodeList
        // silently skips the element that shuffles into its place — which is
        // how a sanitiser leaves every second `<script>` behind.
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $element->removeChild($child);

                continue;
            }

            if (! $child instanceof \DOMElement) {
                continue;
            }

            if (! in_array(strtolower($child->localName), self::ELEMENTS, true)) {
                $element->removeChild($child);

                continue;
            }

            self::scrub($child);
        }

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, self::ATTRIBUTES, true)) {
                $element->removeAttributeNode($attribute);

                continue;
            }

            // `style` survives the allow-list because a logo without its fills
            // is not a logo — but it is the one attribute that can still carry
            // a URL, and `url(javascript:…)` is old but not extinct.
            if ($name === 'style' && preg_match('/url\s*\(|expression\s*\(|@import|javascript:/i', $attribute->nodeValue)) {
                $element->removeAttributeNode($attribute);
            }
        }

        // Same reasoning for a `<style>` element's contents.
        if (strtolower($element->localName) === 'style'
            && preg_match('/@import|url\s*\(|expression\s*\(|javascript:/i', $element->textContent)) {
            $element->textContent = '';
        }
    }
}
