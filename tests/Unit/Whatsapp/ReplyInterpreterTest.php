<?php

namespace Tests\Unit\Whatsapp;

use App\Models\WhatsappMessage;
use App\Services\Whatsapp\ReplyInterpreter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The dictionary that decides whether a parcel ships.
 *
 * Every case here is how somebody actually answers a WhatsApp message in
 * Egyptian Arabic — not how a form would like them to.
 */
class ReplyInterpreterTest extends TestCase
{
    private ReplyInterpreter $interpreter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->interpreter = new ReplyInterpreter;
    }

    /** @return array<string,array{0:string}> */
    public static function confirmations(): array
    {
        return [
            'the digit we asked for' => ['1'],
            'the same digit, Arabic' => ['١'],
            'تمام' => ['تمام'],
            'with a title after it' => ['تمام يا فندم'],
            'اه' => ['اه'],
            'with a hamza' => ['أه'],
            'ايوه' => ['ايوه'],
            'the other spelling' => ['أيوة'],
            'اكد' => ['اكد الطلب'],
            'with diacritics pasted in' => ['تَمام'],
            'with an emoji' => ['تمام 👍'],
            'english' => ['ok'],
            'english, shouted' => ['YES'],
            'حاضر' => ['حاضر يا باشا'],
        ];
    }

    /** @return array<string,array{0:string}> */
    public static function cancellations(): array
    {
        return [
            'the digit we asked for' => ['2'],
            'the same digit, Arabic' => ['٢'],
            'لا' => ['لا'],
            'لأ' => ['لأ'],
            'الغاء' => ['الغاء'],
            'with a hamza' => ['إلغاء'],
            'the phrase that contains a yes' => ['مش عايز'],
            'and its other spelling' => ['مش عاوز الطلب ده'],
            'changed their mind' => ['غيرت رأيي'],
            'english' => ['cancel'],
            'no' => ['no thanks'],
        ];
    }

    /** @return array<string,array{0:string}> */
    public static function ambiguous(): array
    {
        return [
            'a question' => ['هيوصل امتى؟'],
            'both answers in one message' => ['تمام بس لا'],
            'nothing at all' => ['   '],
            'only an emoji' => ['🙂'],
            'a different subject entirely' => ['ممكن اغير المقاس'],
            'the address, sent unprompted' => ['١٢ شارع عباس العقاد'],
        ];
    }

    #[DataProvider('confirmations')]
    public function test_it_reads_a_yes(string $reply): void
    {
        $this->assertSame(WhatsappMessage::INTENT_CONFIRM, $this->interpreter->read($reply));
    }

    #[DataProvider('cancellations')]
    public function test_it_reads_a_no(string $reply): void
    {
        $this->assertSame(WhatsappMessage::INTENT_CANCEL, $this->interpreter->read($reply));
    }

    /**
     * The rule that matters most.
     *
     * An unknown reply moves nothing. A wrongly confirmed order ships to
     * somebody who said no, and the merchant pays the shipping twice to find
     * out — so "not sure" has to cost a phone call, never a parcel.
     */
    #[DataProvider('ambiguous')]
    public function test_it_refuses_to_guess(string $reply): void
    {
        $this->assertSame(WhatsappMessage::INTENT_UNKNOWN, $this->interpreter->read($reply));
    }

    /** The button titles Meta sends back when the customer taps rather than types. */
    public function test_it_reads_the_quick_reply_buttons(): void
    {
        $this->assertSame(WhatsappMessage::INTENT_CONFIRM, $this->interpreter->read('أكّد الطلب'));
        $this->assertSame(WhatsappMessage::INTENT_CANCEL, $this->interpreter->read('ألغِ الطلب'));
    }
}
