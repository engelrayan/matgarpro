<?php

namespace App\Services\Builder;

use Illuminate\Support\Str;

/**
 * Ready-made landing pages a merchant starts from instead of a blank page.
 *
 * Each is a layout that sells in a different way — a short page for impulse
 * buys straight off an ad, a long one for a product that needs explaining,
 * and one built on the designer images most Egyptian landing pages are made
 * of. They differ in order and emphasis, not just colour.
 *
 * The words they come with are prompts, not claims: "اكتب هنا أهم ميزة" can
 * sit on a draft for a week without promising a customer anything, while a
 * sample "ضمان ٣٠ يوم" left in by mistake is a promise the shop never made. The
 * only fact any template states is cash on delivery, true of every shop here.
 */
class LandingTemplates
{
    private const CASH_ON_DELIVERY = ['icon' => 'wallet', 'title' => 'الدفع عند الاستلام', 'body' => 'تدفع لما الطلب يوصلك وتشوفه بنفسك'];

    public function __construct(private readonly SectionRegistry $registry) {}

    /**
     * Name, one line on when to use it, and the parts it is made of — for the
     * builder's chooser.
     *
     * @return array<int,array{key:string,name:string,description:string,best_for:string,parts:array<int,string>}>
     */
    public function catalogue(): array
    {
        return collect($this->definitions())
            ->map(fn (array $template, string $key) => [
                'key' => $key,
                'name' => $template['name'],
                'description' => $template['description'],
                'best_for' => $template['best_for'],
                'parts' => array_map(
                    fn (array $section) => $this->registry->find($section[0])['name'] ?? $section[0],
                    $template['sections'],
                ),
            ])
            ->values()
            ->all();
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->definitions());
    }

    /**
     * The template as builder sections, ready for PageBuilder::saveDraft —
     * which still puts every one through the sanitiser.
     *
     * @return array<int,array<string,mixed>>
     */
    public function sections(string $key): array
    {
        return array_map(fn (array $section) => [
            'id' => Str::lower(Str::random(12)),
            'type' => $section[0],
            'visible' => true,
            'settings' => [...$this->registry->defaultSettings($section[0]), ...($section[1] ?? [])],
        ], $this->definitions()[$key]['sections'] ?? []);
    }

    /** @return array<string,array<string,mixed>> */
    private function definitions(): array
    {
        $form = ['product_main', ['gallery' => false]];
        $trust = ['trust_bar', ['items' => [self::CASH_ON_DELIVERY]]];
        $faq = ['faq', ['items' => [
            ['question' => 'أدفع إزاي؟', 'answer' => 'كاش للمندوب لما الطلب يوصلك. مش هتدفع أي حاجة دلوقتي.'],
            ['question' => 'اكتب هنا سؤال الزباين بيسألوه كتير', 'answer' => 'واكتب إجابته هنا.'],
        ]]];

        return [
            /*
             | For the impulse buy: the promise, the price and the form all on
             | the first screen or two of a phone, nothing between the ad and
             | the order. Bold hero, because there is only one chance to stop
             | the thumb.
             */
            'quick' => [
                'name' => 'بيع سريع',
                'description' => 'صفحة قصيرة: العنوان والسعر وفورم الطلب على طول.',
                'best_for' => 'منتج سعره بسيط والزبون بيقرر بسرعة من الإعلان',
                'sections' => [
                    ['landing_hero', ['style' => 'bold', 'points' => [
                        ['text' => 'اكتب أهم ميزة'], ['text' => 'وميزة تانية'], ['text' => 'الدفع عند الاستلام'],
                    ]]],
                    $form,
                    ['live_orders'],
                    $trust,
                    $faq,
                ],
            ],

            /*
             | For the product that needs convincing: the reasons, the
             | comparison and the answers come before the form, the way a
             | salesman explains before he asks for the money.
             */
            'story' => [
                'name' => 'صفحة كاملة',
                'description' => 'عنوان، مميزات، مقارنة بالبدائل، ضمان وأسئلة — وبعدين الفورم.',
                'best_for' => 'منتج محتاج شرح أو سعره عالي شوية',
                'sections' => [
                    ['landing_hero', ['style' => 'soft']],
                    $trust,
                    ['benefits', ['items' => [
                        ['image' => null, 'icon' => 'bolt', 'title' => 'أهم ميزة', 'body' => 'اكتب هنا الفايدة اللي الزبون هيحسها.'],
                        ['image' => null, 'icon' => 'heart', 'title' => 'ميزة تانية', 'body' => 'وهنا ليه المنتج ده أحسن من غيره.'],
                        ['image' => null, 'icon' => 'shield', 'title' => 'ميزة تالتة', 'body' => 'خامة، سهولة، توفير — اللي يميّزه.'],
                        ['image' => null, 'icon' => 'clock', 'title' => 'ميزة رابعة', 'body' => 'أو امسح الكارت ده لو مش محتاجه.'],
                    ]]],
                    ['comparison', ['them_1' => 'البدائل التانية', 'rows' => [
                        ['feature' => 'اكتب ميزة منتجك فيها', 'us' => true, 'them_1' => false, 'them_2' => false],
                        ['feature' => 'وميزة تانية', 'us' => true, 'them_1' => false, 'them_2' => false],
                        ['feature' => 'حاجة الاتنين فيها', 'us' => true, 'them_1' => true, 'them_2' => false],
                    ]]],
                    $form,
                    ['live_orders'],
                    ['guarantee'],
                    $faq,
                ],
            ],

            /*
             | Built on the designer's images: they carry the pitch, the page
             | only adds the form and the proof. The image part starts empty
             | and shows nothing until the merchant uploads.
             */
            'designed' => [
                'name' => 'صور مصمّمة',
                'description' => 'الصور اللي المصمم عملها ورا بعض، وتحتها الفورم.',
                'best_for' => 'لو عندك تصميمات جاهزة زي بوستات الإعلان',
                'sections' => [
                    ['image_stack'],
                    $form,
                    ['live_orders'],
                    $trust,
                    $faq,
                ],
            ],
        ];
    }
}
