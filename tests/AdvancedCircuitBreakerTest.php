<?php
/**
 * 2007-2019 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace Tests\PrestaShop\CircuitBreaker;

use PHPUnit_Framework_MockObject_Matcher_AnyInvokedCount;
use PrestaShop\CircuitBreaker\AdvancedCircuitBreaker;
use PrestaShop\CircuitBreaker\Places\ClosedPlace;
use PrestaShop\CircuitBreaker\Places\HalfOpenPlace;
use PrestaShop\CircuitBreaker\Places\OpenPlace;
use PrestaShop\CircuitBreaker\Storages\SymfonyCache;
use PrestaShop\CircuitBreaker\SymfonyCircuitBreaker;
use PrestaShop\CircuitBreaker\Systems\MainSystem;
use PrestaShop\CircuitBreaker\Transitions\EventDispatcher;
use Symfony\Component\Cache\Simple\ArrayCache;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;

class AdvancedCircuitBreakerTest extends CircuitBreakerTestCase
{
    /**
     * Used to track the dispatched events.
     *
     * @var PHPUnit_Framework_MockObject_Matcher_AnyInvokedCount
     */
    private $spy;

    /**
     * We should see the circuit breaker initialized,
     * a call being done and then the circuit breaker closed.
     */
    public function testCircuitBreakerEventsOnFirstFailedCall()
    {
        $circuitBreaker = $this->createCircuitBreaker();

        $circuitBreaker->callWithParameters(
            'https://httpbin.org/get/foo',
            function () {
                return '{}';
            },
            ['toto' => 'titi']
        );

        /**
         * The circuit breaker is initiated
         * the 2 failed trials are done
         * then the conditions are met to open the circuit breaker
         */
        $invocations = $this->spy->getInvocations();
        $this->assertCount(4, $invocations);
        $this->assertSame('INITIATING', $invocations[0]->parameters[0]);
        $this->assertSame('TRIAL', $invocations[1]->parameters[0]);
        $this->assertSame('TRIAL', $invocations[2]->parameters[0]);
        $this->assertSame('OPENING', $invocations[3]->parameters[0]);
    }

    /**
     * @return SymfonyCircuitBreaker the circuit breaker for testing purposes
     */
    private function createCircuitBreaker()
    {
        $system = new MainSystem(
            new ClosedPlace(2, 0.2, 0),
            new HalfOpenPlace(0, 0.2, 0),
            new OpenPlace(0, 0, 1)
        );

        $symfonyCache = new SymfonyCache(new ArrayCache());
        $eventDispatcherS = $this->createMock(SymfonyEventDispatcher::class);
        $eventDispatcherS->expects($this->spy = $this->any())
            ->method('dispatch')
        ;
        $transitioner = new EventDispatcher($eventDispatcherS);

        return new AdvancedCircuitBreaker(
            $system,
            $this->getTestClient(),
            $symfonyCache,
            $transitioner
        );
    }
}