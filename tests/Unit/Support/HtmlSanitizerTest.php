<?php

namespace Tests\Unit\Support;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * The product description is rendered unescaped on a page customers hand over
 * a phone number on. This class is the only thing between a pasted `<script>`
 * and their browser, so it is tested as a security boundary rather than as a
 * formatter.
 */
class HtmlSanitizerTest extends TestCase
{
    public function test_ordinary_formatting_survives(): void
    {
        $html = '<p>قميص <strong>قطن</strong> مصري</p><ul><li>مقاسات كتير</li></ul>';

        $this->assertSame($html, HtmlSanitizer::clean($html));
    }

    public function test_arabic_is_not_mangled(): void
    {
        // libxml assumes Latin-1 without an encoding hint and turns every
        // Arabic character into mojibake.
        $clean = HtmlSanitizer::clean('<p>قميص قطن مصري ١٠٠٪</p>');

        $this->assertStringContainsString('قميص قطن مصري ١٠٠٪', $clean);
    }

    public function test_script_tags_are_removed_with_their_contents(): void
    {
        $clean = HtmlSanitizer::clean('<p>قميص</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('alert', $clean);
        $this->assertStringContainsString('قميص', $clean);
    }

    public function test_event_handler_attributes_are_stripped(): void
    {
        $clean = HtmlSanitizer::clean('<p onclick="steal()" onmouseover="x()">قميص</p>');

        $this->assertSame('<p>قميص</p>', $clean);
    }

    public function test_javascript_urls_are_removed_from_links(): void
    {
        $clean = HtmlSanitizer::clean('<a href="javascript:alert(1)">اضغط</a>');

        $this->assertStringNotContainsString('javascript', $clean);
        $this->assertStringContainsString('اضغط', $clean);
    }

    public function test_data_urls_are_removed_from_links(): void
    {
        $clean = HtmlSanitizer::clean('<a href="data:text/html;base64,PHNjcmlwdD4=">اضغط</a>');

        $this->assertStringNotContainsString('data:', $clean);
    }

    public function test_http_links_survive_and_are_made_safe(): void
    {
        $clean = HtmlSanitizer::clean('<a href="https://example.com">الموقع</a>');

        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('rel="noopener nofollow"', $clean);
        $this->assertStringContainsString('target="_blank"', $clean);
    }

    public function test_iframes_and_embeds_are_removed(): void
    {
        foreach (['<iframe src="https://evil.test"></iframe>', '<object data="x"></object>', '<embed src="x">'] as $payload) {
            $clean = (string) HtmlSanitizer::clean("<p>قميص</p>{$payload}");

            $this->assertStringNotContainsString('iframe', $clean);
            $this->assertStringNotContainsString('object', $clean);
            $this->assertStringNotContainsString('embed', $clean);
        }
    }

    public function test_style_attributes_and_tags_are_stripped(): void
    {
        $clean = HtmlSanitizer::clean('<style>body{display:none}</style><p style="position:fixed">قميص</p>');

        $this->assertSame('<p>قميص</p>', $clean);
    }

    /**
     * A stray wrapper should cost the tag, not the merchant's words.
     */
    public function test_disallowed_tags_are_unwrapped_not_deleted(): void
    {
        $clean = HtmlSanitizer::clean('<font color="red"><p>قميص قطن</p></font>');

        $this->assertStringNotContainsString('font', $clean);
        $this->assertStringContainsString('قميص قطن', $clean);
    }

    public function test_nested_payloads_are_still_caught(): void
    {
        $clean = (string) HtmlSanitizer::clean('<div><p><span onclick="x()"><script>bad()</script>قميص</span></p></div>');

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringContainsString('قميص', $clean);
    }

    public function test_images_are_not_allowed(): void
    {
        // Images belong in the gallery, where they are uploaded and served by
        // us. An arbitrary remote <img> is a tracking pixel someone else owns.
        $clean = (string) HtmlSanitizer::clean('<p>قميص</p><img src="https://evil.test/track.gif">');

        $this->assertStringNotContainsString('<img', $clean);
    }

    public function test_empty_input_becomes_null(): void
    {
        $this->assertNull(HtmlSanitizer::clean(null));
        $this->assertNull(HtmlSanitizer::clean(''));
        $this->assertNull(HtmlSanitizer::clean('   '));
    }

    public function test_malformed_html_does_not_throw(): void
    {
        $clean = HtmlSanitizer::clean('<p>قميص<strong>غير مقفول');

        $this->assertStringContainsString('قميص', (string) $clean);
    }
}
