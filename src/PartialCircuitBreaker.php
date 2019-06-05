<?php

namespace PrestaShop\CircuitBreaker;

use PrestaShop\CircuitBreaker\Transactions\SimpleTransaction;
use PrestaShop\CircuitBreaker\Contracts\CircuitBreakerInterface;
use PrestaShop\CircuitBreaker\Contracts\TransactionInterface;
use PrestaShop\CircuitBreaker\Contracts\StorageInterface;
use PrestaShop\CircuitBreaker\Contracts\SystemInterface;
use PrestaShop\CircuitBreaker\Contracts\ClientInterface;
use PrestaShop\CircuitBreaker\Contracts\PlaceInterface;
use DateTime;

abstract class PartialCircuitBreaker implements CircuitBreakerInterface
{
    /**
     * @param SystemInterface $system
     * @param ClientInterface $client
     * @param StorageInterface $storage
     */
    public function __construct(
        SystemInterface $system,
        ClientInterface $client,
        StorageInterface $storage
    ) {
        $this->currentPlace = $system->getInitialPlace();
        $this->places = $system->getPlaces();
        $this->client = $client;
        $this->storage = $storage;
    }

    /**
     * @var ClientInterface the Client that consumes the service URI
     */
    protected $client;

    /**
     * @var PlaceInterface the current Place of the Circuit Breaker
     */
    protected $currentPlace;

    /**
     * @var PlaceInterface[] the Circuit Breaker places
     */
    protected $places = [];

    /**
     * @var StorageInterface the Circuit Breaker storage
     */
    protected $storage;

    /**
     * {@inheritdoc}
     */
    abstract public function call($service, callable $fallback, array $serviceParameters = []);

    /**
     * {@inheritdoc}
     */
    public function getState()
    {
        return $this->currentPlace->getState();
    }

    /**
     * {@inheritdoc}
     */
    public function isOpened()
    {
        return States::OPEN_STATE === $this->currentPlace->getState();
    }

    /**
     * {@inheritdoc}
     */
    public function isHalfOpened()
    {
        return States::HALF_OPEN_STATE === $this->currentPlace->getState();
    }

    /**
     * {@inheritdoc}
     */
    public function isClosed()
    {
        return States::CLOSED_STATE === $this->currentPlace->getState();
    }

    /**
     * @param string $state the Place state
     * @param string $service the service URI
     *
     * @return bool
     */
    protected function moveStateTo($state, $service)
    {
        $this->currentPlace = $this->places[$state];
        $transaction = SimpleTransaction::createFromPlace(
            $this->currentPlace,
            $service
        );

        return $this->storage->saveTransaction($service, $transaction);
    }

    /**
     * @param string $service the service URI
     *
     * @return TransactionInterface
     */
    protected function initTransaction($service)
    {
        if ($this->storage->hasTransaction($service)) {
            $transaction = $this->storage->getTransaction($service);
            // CircuitBreaker needs to be in the same state as its last transaction
            if ($this->getState() !== $transaction->getState()) {
                $this->currentPlace = $this->places[$transaction->getState()];
            }
        } else {
            $transaction = SimpleTransaction::createFromPlace(
                $this->currentPlace,
                $service
            );

            $this->storage->saveTransaction($service, $transaction);
        }

        return $transaction;
    }

    /**
     * @param TransactionInterface $transaction the Transaction
     *
     * @return bool
     */
    protected function isAllowedToRetry(TransactionInterface $transaction)
    {
        return $transaction->getFailures() < $this->currentPlace->getFailures();
    }

    /**
     * @param TransactionInterface $transaction the Transaction
     *
     * @return bool
     */
    protected function canAccessService(TransactionInterface $transaction)
    {
        return $transaction->getThresholdDateTime() < new DateTime();
    }

    /**
     * Calls the client with the right information.
     *
     * @param string $service the service URI
     * @param array $parameters the service URI parameters
     *
     * @return string
     */
    protected function request($service, array $parameters = [])
    {
        return $this->client->request(
            $service,
            array_merge($parameters, [
                'connect_timeout' => $this->currentPlace->getTimeout(),
                'timeout' => $this->currentPlace->getTimeout(),
            ])
        );
    }
}
