<?php

namespace App\Services\AI\LandingPage;

use App\Models\Product;
use App\Models\Store;
use App\Services\Builder\PageBuilder;
use App\Services\Builder\SectionRegistry;

/**
 * Puts a written landing plan into the product's landing page — as a draft.
 *
 * The live page, if there is one, does not change: the merchant reads the
 * result in the builder's preview and presses نشر themselves. Every section
 * goes through PageBuilder::saveDraft, so the sanitiser rebuilds it from the
 * registry exactly as it would a merchant's own edit.
 *
 * The parts the model has nothing to say about are left out rather than
 * left empty, and the product's own form always is in — that is what the
 * page is for.
 */
class LandingPlanApplier
{
    /** The one reassurance every shop on the platform can truthfully make. */
    private const CASH_ON_DELIVERY = ['icon' => 'wallet', 'title' => 'الدفع عند الاستلام', 'body' => 'تدفع لما الطلب يوصلك وتشوفه بنفسك'];

    public function __construct(
        private readonly PageBuilder $builder,
        private readonly SectionRegistry $registry,
    ) {}

    /** @param array<string,mixed> $plan a LandingPageWriter result */
    public function apply(Store $store, Product $product, array $plan): void
    {
        $sections = [
            $this->section('landing_hero', [...(array) $plan['hero'], 'style' => 'soft']),
            // Never the registry's default items: those promise a delivery time
            // and an exchange window this merchant may not offer.
            $this->section('trust_bar', ['items' => $plan['trust_items'] ?: [self::CASH_ON_DELIVERY]]),
        ];

        if (! empty($plan['benefits']['items'])) {
            $sections[] = $this->section('benefits', $plan['benefits']);
        }

        // Without its gallery: the hero above already shows the product.
        $sections[] = $this->section('product_main', ['gallery' => false]);
        $sections[] = $this->section('live_orders');

        if (! empty($plan['comparison']['rows'])) {
            $sections[] = $this->section('comparison', $plan['comparison']);
        }

        if (! empty($plan['guarantee']['title'])) {
            $sections[] = $this->section('guarantee', [...$plan['guarantee'], 'button' => $plan['hero']['button'] ?? '']);
        }

        if (! empty($plan['faq'])) {
            $sections[] = $this->section('faq', ['items' => $plan['faq']]);
        }

        $this->builder->saveDraft($store, SectionRegistry::landingKey($product->id), $sections);
    }

    /**
     * A section with the registry's defaults under whatever the plan says.
     *
     * @param  array<string,mixed>  $settings
     * @return array<string,mixed>
     */
    private function section(string $type, array $settings = []): array
    {
        return [
            'type' => $type,
            'visible' => true,
            'settings' => [...$this->registry->defaultSettings($type), ...$settings],
        ];
    }
}
