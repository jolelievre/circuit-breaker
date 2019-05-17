<?php

namespace PrestaShop\CircuitBreaker;

use PrestaShop\CircuitBreaker\Contracts\Place;
use PrestaShop\CircuitBreaker\Contracts\Client;
use PrestaShop\CircuitBreaker\Systems\MainSystem;
use PrestaShop\CircuitBreaker\Storages\SimpleArray;
use PrestaShop\CircuitBreaker\Exceptions\UnavailableServiceException;

/**
 * Main implementation of Circuit Breaker.
 */
final class SimpleCircuitBreaker extends PartialCircuitBreaker
{
    public function __construct(
        Place $openPlace,
        Place $halfOpenPlace,
        Place $closedPlace,
        Client $client
    ) {
        $system = new MainSystem($closedPlace, $halfOpenPlace, $openPlace);

        parent::__construct($system, $client, new SimpleArray());
    }

    /**
     * {@inheritdoc}
     */
    public function call(
        $service,
        callable $fallback,
        array $serviceParameters = []
    ) {
        $transaction = $this->initTransaction($service);
        try {
            if ($this->isOpened()) {
                if (!$this->canAccessService($transaction)) {
                    return call_user_func($fallback);
                }

                $this->moveStateTo(States::HALF_OPEN_STATE, $service);
            }
            $response = $this->request($service, $serviceParameters);
            $this->moveStateTo(States::CLOSED_STATE, $service);

            return $response;
        } catch (UnavailableServiceException $exception) {
            $transaction->incrementFailures();
            $this->storage->saveTransaction($service, $transaction);
            if (!$this->isAllowedToRetry($transaction)) {
                $this->moveStateTo(States::OPEN_STATE, $service);

                return call_user_func($fallback);
            }

            return $this->call($service, $fallback);
        }
    }
}
