<?php
declare(strict_types=1);

namespace Serato\SsoRequest\Test;

use Serato\SsoRequest\Test\AbstractTestCase;
use Serato\SsoRequest\AuthRequestDynamoDbStorage;
use DateTime;
use DateInterval;
use Aws\Sdk;
use Aws\Result;

class AuthRequestDynamoDbStorageTest extends AbstractTestCase
{
    /**
     * @group aws-integration
     */
    public function testSaveAndLoadAndDelete()
    {
        $id = 'my-id';
        $clientAppId = 'my-client-app';
        $redirectUri = '/redirect/uri';
        $dt = new DateTime;

        $storage = new AuthRequestDynamoDbStorage(new Sdk());
        $bVal = $storage
            ->setId($id)
            ->setClientAppId($clientAppId)
            ->setUri($redirectUri)
            ->setCreatedAt($dt)
            ->setCompleted(false)
            ->save();

        $this->assertTrue($bVal, 'Save to DynamoDB');

        $storage = new AuthRequestDynamoDbStorage(new Sdk());

        $this->assertTrue($storage->load($id), 'Load from DynamoDB');

        $this->assertEquals($storage->getId(), $id);
        $this->assertEquals($storage->getClientAppId(), $clientAppId);
        $this->assertEquals($storage->getUri(), $redirectUri);
        $this->assertEquals($storage->getCreatedAt()->getTimestamp(), $dt->getTimestamp());

        $storage->setCompleted(true);

        $storage = new AuthRequestDynamoDbStorage(new Sdk());

        $this->assertFalse($storage->load($id), 'Load from DynamoDB after previously marking as complete');
    }

    /**
     * @group aws-integration
     */
    public function testDeleteNonExistentItem()
    {
        $storage = new AuthRequestDynamoDbStorage(new Sdk());
        $storage
            ->setId('my-new-id')
            ->setCompleted(true);
        
        $this->assertTrue(true);
    }

    public function testSaveNotCompleted()
    {
        $id = 'my-id';
        $clientAppId = 'my-client-app';
        $redirectUri = '/redirect/uri';
        $dt = new DateTime;

        $results = [
            // DynamoDB `putItem`
            new Result()
        ];

        $storage = new AuthRequestDynamoDbStorage($this->getMockedAwsSdk($results));

        $bVal = $storage
            ->setId($id)
            ->setClientAppId($clientAppId)
            ->setUri($redirectUri)
            ->setCreatedAt($dt)
            ->setCompleted(false)
            ->save();

        $this->assertEquals(0, $this->getAwsMockHandlerStackCount());
    }

    public function testSaveCompleted()
    {
        $id = 'my-id';
        $clientAppId = 'my-client-app';
        $redirectUri = '/redirect/uri';
        $dt = new DateTime;

        $results = [
            // DynamoDB `deleteItem`
            new Result()
        ];

        $storage = new AuthRequestDynamoDbStorage($this->getMockedAwsSdk($results));

        $bVal = $storage
            ->setId($id)
            ->setClientAppId($clientAppId)
            ->setUri($redirectUri)
            ->setCreatedAt($dt)
            ->setCompleted(true)
            ->save();

        $this->assertEquals(0, $this->getAwsMockHandlerStackCount());
    }
}
