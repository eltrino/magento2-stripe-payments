<?php

namespace Eltrino\StripePayments\Api;

use Eltrino\StripePayments\Helper\PaymentIntent;
use StripeIntegration\Payments\Model\Config;

class Service implements ServiceInterface
{
    /**
     * @var PaymentIntent
     */
    private $paymentIntent;

    /**
     * @var Config
     */
    private $config;

    /**
     * Service constructor.
     */
    public function __construct(
        PaymentIntent $paymentIntent,
        Config $config
    ) {
        $this->paymentIntent = $paymentIntent;
        $this->config = $config;
    }

    /**
     * Creates a fresh PaymentIntent and returns the client secret
     *
     * @return null|array
     * @api
     */
    public function get_client_secret(): ?string
    {
        $this->config->initStripe();
        $instance = $this->paymentIntent->create();
        if ($instance) {
            return $instance->client_secret;
        }
        return null;
    }

    /**
     * Refresh payment intent with data from quote
     *
     * @return void
     * @api
     */
    public function refresh_payment_intent(): void
    {
        $this->config->initStripe();
        $this->paymentIntent->refresh();
    }
}
