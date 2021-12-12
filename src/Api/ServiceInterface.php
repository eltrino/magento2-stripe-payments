<?php

namespace Eltrino\StripePayments\Api;

interface ServiceInterface
{
    /**
     * Creates a fresh PaymentIntent and returns the client secret
     *
     * @return null|string
     * @api
     */
    public function get_client_secret(): ?string;

    /**
     * Refresh payment intent with data from quote
     *
     * @return void
     * @api
     */
    public function refresh_payment_intent(): void;

}
