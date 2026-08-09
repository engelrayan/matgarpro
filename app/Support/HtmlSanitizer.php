<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list sanitiser for merchant-written product descriptions.
 *
 * The description is rendered unescaped on a page customers buy from, so this
 * is the only thing between a pasted `<script>` and a customer's browser. It
 * is an allow-list, not a block-list: block-lists are a list of the attacks
 * somebody already thought of.
 *
 * A merchant attacking their own storefront is mostly attacking themselves —
 * but a compromised merchant account is not, and neither is a description
 * pasted from a supplier's site.
 */
class HtmlSanitizer
{
    /** Tags a description may contain. Everything else is unwrapped. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'h3', 'h4', 'ul', 'ol', 'li', 'a', 'span', 'div',
    ];

    /** Attributes, per tag. Nothing else survives — including every `on*`. */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title'],
    ];

    public static function clean(?string $html): ?string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        $document = new DOMDocument();

        // Suppress warnings from the merchant's imperfect markup, and force
        // UTF-8: without the meta hint libxml assumes Latin-1 and mangles
        // every Arabic character in the description.
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('root');

        if (! $root) {
            return null;
        }

        self::cleanNode($root, $document);

        $out = '';

        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out) ?: null;
    }

    private static function cleanNode(DOMNode $node, DOMDocument $document): void
    {
        // Snapshot first: unwrapping mutates the live child list mid-loop.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->nodeName);

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    self::unwrap($child, $document);

                    continue;
                }

                self::cleanAttributes($child, $tag);
                self::cleanNode($child, $document);
            }
        }
    }

    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            // `javascript:` and `data:` in an href are a script the customer
            // runs by clicking a link in a product description.
            if ($name === 'href' && ! preg_match('#^(https?://|mailto:|tel:)#i', trim($attribute->nodeValue))) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        if ($tag === 'a' && $element->hasAttribute('href')) {
            // Outbound links from a store page open away from the checkout.
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener nofollow');
        }
    }

    /**
     * Replace a disallowed element with its children.
     *
     * Unwrapping rather than deleting keeps the merchant's words: a stray
     * `<font>` around a paragraph should cost the tag, not the paragraph.
     * `<script>` and `<style>` are the exception — their children ARE the
     * payload.
     */
    private static function unwrap(DOMElement $element, DOMDocument $document): void
    {
        $tag = strtolower($element->nodeName);

        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
            $element->parentNode?->removeChild($element);

            return;
        }

        self::cleanNode($element, $document);

        while ($element->firstChild) {
            $element->parentNode?->insertBefore($element->firstChild, $element);
        }

        $element->parentNode?->removeChild($element);
    }
}
