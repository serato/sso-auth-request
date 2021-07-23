<?php

declare(strict_types=1);

namespace Serato\SsoRequest\Test;

use PHPUnit\Framework\TestCase;
use Aws\Sdk as AwsSdk;
use Aws\MockHandler as AwsMockHandler;
use Serato\SwsSdk\Sdk as SwsSdk;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

abstract class AbstractTestCase extends TestCase
{
    /* @var MockHandler */
    private $swsMockHandler;

    /* @var AwsMockHandler */
    private $awsMockHandler;

    /**
     * Returns an `SwsSdk` bootstrapped with a mock response handler and the
     * provided mock Response objects added to the handler stack.
     *
     * @param array $mockResponses  An array of PSR-7 Response objects
     * @return SwsSdk
     */
    protected function getMockedSwsSdk(array $mockResponses = []): SwsSdk
    {
        $this->swsMockHandler = new MockHandler($mockResponses);
        $args = [
            SwsSdk::BASE_URI => [
                SwsSdk::BASE_URI_ID            => 'http://id.server.com',
                SwsSdk::BASE_URI_LICENSE       => 'http://license.server.com',
                SwsSdk::BASE_URI_PROFILE       => 'https://profile.server.com',
                SwsSdk::BASE_URI_ECOM          => 'http://ecom.server.com',
                SwsSdk::BASE_URI_DA            => 'http://da.server.com',
                SwsSdk::BASE_URI_NOTIFICATIONS => 'http://notifications.serato.com'
            ],
            'handler' => HandlerStack::create($this->swsMockHandler)
        ];
        return new SwsSdk($args);
    }

    /**
     * Returns the number of remaining items in the SWS mock handler queue.
     *
     * @return int
     */
    protected function getSwsMockHandlerStackCount()
    {
        return $this->swsMockHandler->count();
    }

    /**
     * Returns an `Aws\Sdk` instance configured with the `Aws\MockHandler` and the
     * provided mock results
     *
     * @param array $mockResults    An array of mock results to return from SDK clients
     * @return AwsSdk
     */
    protected function getMockedAwsSdk(array $mockResults = []): AwsSdk
    {
        $this->awsMockHandler = new AwsMockHandler();
        foreach ($mockResults as $result) {
            $this->awsMockHandler->append($result);
        }
        return new AwsSdk([
            'region' => 'us-east-1',
            'version' => '2014-11-01',
            'credentials' => [
                'key' => 'my-access-key-id',
                'secret' => 'my-secret-access-key'
            ],
            'handler' => $this->awsMockHandler
        ]);
    }

    /**
     * Returns the number of remaining items in the AWS mock handler queue.
     *
     * @return int
     */
    protected function getAwsMockHandlerStackCount()
    {
        return $this->awsMockHandler->count();
    }
}
