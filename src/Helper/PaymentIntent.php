<?php

namespace Eltrino\StripePayments\Helper;

use Magento\Framework\App\CacheInterface;
use Magento\Quote\Api\Data\CartInterface;
use Psr\Log\LoggerInterface;
use StripeIntegration\Payments\Helper\Generic;
use StripeIntegration\Payments\Model\Config;

class PaymentIntent
{
    /**
     * @var Generic
     */
    private $helper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CacheInterface
     */
    private $cache;

    public function __construct(
        LoggerInterface $logger,
        Generic $helper,
        CacheInterface $cache,
        Config $config
    ) {
        $this->logger = $logger;
        $this->helper = $helper;
        $this->cache = $cache;
        $this->config = $config;
    }

    public function create(): ?\Stripe\PaymentIntent
    {
        $quote = $this->helper->getQuote();
        if ($quote && $quote->getId()) {

            $key = $this->getCacheKey($quote);
            $cached = $this->cache->load($key);

            $amount = $quote->getBaseGrandTotal() * 100;
            $currency = $quote->getBaseCurrencyCode();

            if ($cached) {
                $paymentIntent = \Stripe\PaymentIntent::retrieve($cached);
                if ($paymentIntent->status !== \Stripe\PaymentIntent::STATUS_CANCELED
                    && $paymentIntent->status !== \Stripe\PaymentIntent::STATUS_SUCCEEDED
                    && $paymentIntent->amount === $amount
                    && $paymentIntent->currency === $currency
                ) {
                    return $paymentIntent;
                }
            }

            $customer = \Stripe\Customer::create(['email' => $quote->getEmail()]);
            $captureMethod = \StripeIntegration\Payments\Model\PaymentIntent::CAPTURE_METHOD_AUTOMATIC;
            if ($this->config->isAuthorizeOnly()) {
                $captureMethod = \StripeIntegration\Payments\Model\PaymentIntent::CAPTURE_METHOD_MANUAL;
            }

            $params = [
                'capture_method' => $captureMethod,
                'customer' => $customer->id,
                'amount' => $amount,
                'currency' => $currency,
                'setup_future_usage' => 'off_session',
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ];

            $paymentIntent = \Stripe\PaymentIntent::create($params);
            $tags = ['stripe_payment_intents'];
            $lifetime = 12 * 60 * 60; // 12 hours
            $this->cache->save($paymentIntent->id, $key, $tags, $lifetime);
            return $paymentIntent;
        }

        return null;
    }

    public function refresh(): void
    {
        $quote = $this->helper->getQuote();
        if ($quote && $quote->getId()) {
            $id = $this->cache->load($this->getCacheKey($quote));

            if ($id) {
                $quote->reserveOrderId();

                \Stripe\PaymentIntent::update($id, [
                    'amount' => $quote->getBaseGrandTotal() * 100,
                    'currency' => $quote->getBaseCurrencyCode(),
                    'description' => sprintf("Order #%s by %s %s", $quote->getReservedOrderId(),
                        $quote->getCustomerFirstname(), $quote->getCustomerLastname())
                ]);
            }
        }
    }

    /**
     * @note such cache key used in \StripeIntegration\Payments\Model\PaymentIntent::preloadFromCache
     * @param CartInterface $quote
     * @return string
     */
    private function getCacheKey(CartInterface $quote): string
    {
        return sprintf('payment_intent_%s', $quote->getId());
    }
}
