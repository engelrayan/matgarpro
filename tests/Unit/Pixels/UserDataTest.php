<?php

namespace Tests\Unit\Pixels;

use App\Services\Pixels\UserData;
use PHPUnit\Framework\TestCase;

/**
 * Match quality is entirely this class's correctness.
 *
 * Meta matches on the hash, so a normalisation bug does not throw, does not
 * log, and does not show up anywhere in the product — it silently matches
 * nobody, and the merchant only ever sees it as "ads are expensive". These
 * tests assert exact digests against hand-computed values for that reason.
 */
class UserDataTest extends TestCase
{
    private function sha(string $value): string
    {
        return hash('sha256', $value);
    }

    public function test_email_is_lowercased_and_trimmed_before_hashing(): void
    {
        $data = UserData::build(email: '  Mahmoud@Example.COM  ');

        $this->assertSame($this->sha('mahmoud@example.com'), $data['em']);
    }

    public function test_an_invalid_email_is_dropped_rather_than_hashed(): void
    {
        // Hashing junk produces a digest that matches nobody and drags the
        // event's match score down; sending nothing is strictly better.
        $this->assertArrayNotHasKey('em', UserData::build(email: 'not-an-email'));
        $this->assertArrayNotHasKey('em', UserData::build(email: ''));
    }

    /**
     * Egyptian mobiles are stored as 01xxxxxxxxx but Meta matches on the
     * international form. Sending the local one matches nobody.
     */
    public function test_egyptian_mobile_is_converted_to_international_form(): void
    {
        $expected = $this->sha('201006262330');

        foreach (['01006262330', '0100 626 2330', '+201006262330', '00201006262330', '201006262330'] as $input) {
            $this->assertSame($expected, UserData::build(phone: $input)['ph'], "failed for: {$input}");
        }
    }

    public function test_arabic_indic_digits_survive_normalisation(): void
    {
        // Arabic keyboards produce ٠١٢…; stripped as non-digits they leave
        // nothing behind and the order goes out with no phone at all.
        $data = UserData::build(phone: '٠١٠٠٦٢٦٢٣٣٠');

        $this->assertArrayHasKey('ph', $data);
        $this->assertSame($this->sha('201006262330'), $data['ph']);
    }

    public function test_a_too_short_phone_is_dropped(): void
    {
        $this->assertArrayNotHasKey('ph', UserData::build(phone: '123'));
    }

    public function test_name_is_split_into_first_and_last(): void
    {
        $data = UserData::build(name: 'محمود ممدوح عبد الخالق');

        $this->assertSame($this->sha('محمود'), $data['fn']);
        $this->assertSame($this->sha('ممدوحعبدالخالق'), $data['ln']);
    }

    public function test_a_single_word_name_produces_a_first_name_only(): void
    {
        $data = UserData::build(name: 'محمود');

        $this->assertSame($this->sha('محمود'), $data['fn']);
        $this->assertArrayNotHasKey('ln', $data);
    }

    public function test_city_and_state_are_lowercased_and_stripped_of_spaces(): void
    {
        $data = UserData::build(city: 'Nasr City', state: '  القاهرة  ');

        $this->assertSame($this->sha('nasrcity'), $data['ct']);
        $this->assertSame($this->sha('القاهرة'), $data['st']);
    }

    /**
     * The single most important assertion here. `fbp` and `fbc` identify the
     * exact ad click; hashing them makes the event unmatchable, and nothing
     * in the response would tell us.
     */
    public function test_fbp_and_fbc_are_sent_raw_and_never_hashed(): void
    {
        $fbp = 'fb.1.1700000000.1234567890';
        $fbc = 'fb.1.1700000000.IwAR0abc';

        $data = UserData::build(fbp: $fbp, fbc: $fbc);

        $this->assertSame($fbp, $data['fbp']);
        $this->assertSame($fbc, $data['fbc']);
    }

    public function test_ip_and_user_agent_pass_through_unhashed(): void
    {
        $data = UserData::build(ip: '197.54.1.1', userAgent: 'Mozilla/5.0');

        $this->assertSame('197.54.1.1', $data['client_ip_address']);
        $this->assertSame('Mozilla/5.0', $data['client_user_agent']);
    }

    public function test_empty_identifiers_are_omitted_entirely(): void
    {
        $data = UserData::build(email: null, phone: null, name: null, fbp: null);

        // `country` is a constant for an Egypt-only storefront and always ships.
        $this->assertSame(['country'], array_keys($data));
    }

    /** No raw personal data may appear in the payload, under any key. */
    public function test_no_raw_identifier_leaks_into_the_payload(): void
    {
        $data = UserData::build(
            email: 'mahmoud@example.com',
            phone: '01006262330',
            name: 'محمود ممدوح',
            city: 'القاهرة',
        );

        $serialised = json_encode($data, JSON_UNESCAPED_UNICODE);

        foreach (['mahmoud@example.com', '01006262330', '201006262330', 'محمود', 'القاهرة'] as $raw) {
            $this->assertStringNotContainsString($raw, $serialised, "raw value leaked: {$raw}");
        }
    }

    /** Every hash must be a 64-character lowercase hex digest. */
    public function test_every_hashed_field_is_a_valid_sha256_digest(): void
    {
        $data = UserData::build(
            email: 'a@b.com', phone: '01006262330', name: 'محمود ممدوح',
            city: 'القاهرة', state: 'الجيزة',
        );

        foreach (['em', 'ph', 'fn', 'ln', 'ct', 'st', 'country'] as $key) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $data[$key], "bad digest for {$key}");
        }
    }
}
