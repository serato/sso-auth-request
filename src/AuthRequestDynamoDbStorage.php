<?php

declare(strict_types=1);

namespace Serato\SsoRequest;

use Serato\SsoRequest\AuthRequestStorageInterface;
use DateTime;
use Exception;
use Aws\Sdk as AwsSdk;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;

class AuthRequestDynamoDbStorage implements AuthRequestStorageInterface
{
    /* @var AwsSdk */
    private $awsSdk;

    /* @var array */
    private $data = [];

    /* @var boolean */
    private $completed = false;

    private const DYNAMO_DB_TABLE_NAME = 'client.app.sso.auth.requests';

    private const ITEM_TIME_TO_LIVE = 7200; # 120 mins

    public function __construct(AwsSdk $awsSdk)
    {
        $this->awsSdk = $awsSdk;
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): ?string
    {
        return isset($this->data['id']) ? $this->data['id'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientAppId(): ?string
    {
        return isset($this->data['client_app_id']) ? $this->data['client_app_id'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getUri(): ?string
    {
        return isset($this->data['uri']) ? $this->data['uri'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt(): ?DateTime
    {
        return isset($this->data['created_at']) ? $this->data['created_at'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompleted(): bool
    {
        return $this->completed;
    }

    /**
     * {@inheritdoc}
     */
    public function setId(string $id)
    {
        $this->data['id'] = $id;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setClientAppId(string $clientAppId)
    {
        $this->data['client_app_id'] = $clientAppId;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setUri(string $uri)
    {
        $this->data['uri'] = $uri;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedAt(DateTime $createdAt)
    {
        $this->data['created_at'] = $createdAt;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCompleted(bool $bComplete)
    {
        if ($bComplete && ($this->getId() !== null)) {
            $result = $this->getDynamoDbClient()->deleteItem([
                'Key' => [
                    'id' => ['S' => $this->getId()]
                ],
                'TableName' => self::DYNAMO_DB_TABLE_NAME,
                'ReturnValues' => 'ALL_OLD'
            ]);
            // FYI
            // $result['Attributes'] will be set if item existed and was deleted
            // $result['Attributes'] will not be set if item didn't exist
        }
        $this->completed = $bComplete;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function save(): bool
    {
        try {
            // Don't save if it's "completed"
            // The row gets deleted when "completed", so we don't want
            // to store it again :-)
            if (!$this->completed) {
                $this->getDynamoDbClient()->putItem([
                    'Item'      => $this->getDynamoDbItem(),
                    'TableName' => self::DYNAMO_DB_TABLE_NAME
                ]);
            }
            return true;
        } catch (DynamoDbException $e) {
            if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                $this->getDynamoDbClient()->createTable([
                    'TableName' => self::DYNAMO_DB_TABLE_NAME,
                    'AttributeDefinitions' => [
                        ['AttributeName' => 'id', 'AttributeType' => 'S']
                    ],
                    'KeySchema' => [
                        ['AttributeName' => 'id', 'KeyType' => 'HASH']
                    ],
                    'ProvisionedThroughput' => [
                        'ReadCapacityUnits' => 100,
                        'WriteCapacityUnits' => 100
                    ]
                ]);
                sleep(10);
                $this->getDynamoDbClient()->putItem([
                    'Item'      => $this->getDynamoDbItem(),
                    'TableName' => self::DYNAMO_DB_TABLE_NAME
                ]);
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function load(string $id): bool
    {
        $result = $this->getDynamoDbClient()->getItem([
            'Key' => [
                'id' => ['S' => $id]
            ],
            'ConsistentRead' => true,
            'TableName' => self::DYNAMO_DB_TABLE_NAME
        ]);

        if (isset($result['Item'])) {
            $this->data['id'] = $result['Item']['id']['S'];
            $this->data['client_app_id'] = $result['Item']['client_app_id']['S'];
            $this->data['uri'] = $result['Item']['uri']['S'];
            $dt = new DateTime();
            $dt->setTimestamp((int)$result['Item']['created_at']['N']);
            $this->data['created_at'] = $dt;
            return true;
        } else {
            return false;
        }
    }

    private function getDynamoDbItem(): array
    {
        return [
            'id'            => ['S' => $this->data['id']],
            'client_app_id' => ['S' => $this->data['client_app_id']],
            'uri'           => ['S' => $this->data['uri']],
            'created_at'    => ['N' => (string)$this->data['created_at']->getTimestamp()],
            'ttl'           => ['N' => (string)($this->data['created_at']->getTimestamp() + self::ITEM_TIME_TO_LIVE)]
        ];
    }

    private function getDynamoDbClient(): DynamoDbClient
    {
        return $this->awsSdk->createDynamoDb(['version' => '2012-08-10', 'region' => 'us-east-1']);
    }
}
