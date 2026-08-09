<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A checkout that cannot complete for a reason the customer can act on —
 * sold out, a missing selection, a store that is not taking orders.
 *
 * The message is Arabic and shown as-is on the storefront, so it must never
 * carry anything internal: a customer standing in a product page should read a
 * plain sentence, not a stack trace or a store's billing state.
 */
class CheckoutException extends RuntimeException
{
}
