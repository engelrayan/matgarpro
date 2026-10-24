<?php

namespace App\Services\AI;

use App\Models\AiGeneration;

/**
 * Something that turns an AiGeneration's input into a draft.
 *
 * Implemented once per on-screen AI feature and named by AiFeature::generator(),
 * so the queued job, the polling endpoint and the expiry rules are shared and a
 * new feature only writes the part that is actually new: its prompt and what
 * it does with the answer.
 *
 * A draft is always returned, never applied. Whatever writes it into the store
 * runs later, on the merchant's own click.
 */
interface GeneratesDraft
{
    /**
     * @return array<string,mixed> stored as the generation's result
     *
     * @throws AiException
     */
    public function generate(AiGeneration $generation): array;
}
