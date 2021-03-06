<?php

namespace PrestaShop\CircuitBreaker\Systems;

use PrestaShop\CircuitBreaker\Contracts\Place;
use PrestaShop\CircuitBreaker\Contracts\System;
use PrestaShop\CircuitBreaker\States;

/**
 * Implement the system described by the documentation.
 * The main system is built with 3 places:
 * - A Closed place
 * - A Half Open Place
 * - An Open Place
 */
final class MainSystem implements System
{
    /**
     * @var Place[]
     */
    private $places;

    public function __construct(
        Place $closedPlace,
        Place $halfOpenPlace,
        Place $openPlace
    ) {
        $this->places = [
            $closedPlace->getState() => $closedPlace,
            $halfOpenPlace->getState() => $halfOpenPlace,
            $openPlace->getState() => $openPlace,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getInitialPlace()
    {
        return $this->places[States::CLOSED_STATE];
    }

    /**
     * {@inheritdoc}
     */
    public function getPlaces()
    {
        return $this->places;
    }
}
