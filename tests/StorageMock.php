<?php

declare(strict_types=1);

namespace Serato\SsoRequest\Test;

use Serato\SsoRequest\AuthRequestStorageInterface;
use DateTime;

/**
 * A mocked implementation of `Serato\SsoRequest\AuthRequestStorageInterface`
 * suitable for use in tests.
 */
class StorageMock implements AuthRequestStorageInterface
{
    private const ID = 'id';
    private const CLIENT_APP_ID = 'client_app_id';
    private const URI = 'uri';
    private const CREATED_AT = 'created_at';
    private const COMPLETED = 'completed';

    /* @var array */
    private $data = [];

    /* @var bool */
    private $errorOnLoad = false;

    /* @var bool */
    private $errorOnSave = false;

    public function __construct($errorOnLoad = false, $errorOnSave = false)
    {
        $this->errorOnLoad = $errorOnLoad;
        $this->errorOnSave = $errorOnSave;
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): ?string
    {
        return isset($this->data[self::ID]) ? $this->data[self::ID] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientAppId(): ?string
    {
        return isset($this->data[self::CLIENT_APP_ID]) ? $this->data[self::CLIENT_APP_ID] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getUri(): ?string
    {
        return isset($this->data[self::URI]) ? $this->data[self::URI] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt(): ?DateTime
    {
        return isset($this->data[self::CREATED_AT]) ? $this->data[self::CREATED_AT] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompleted(): bool
    {
        return isset($this->data[self::COMPLETED]) ? $this->data[self::COMPLETED] : false;
    }

    /**
     * {@inheritdoc}
     */
    public function setId(string $id)
    {
        $this->data[self::ID] = $id;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setClientAppId(string $clientAppId)
    {
        $this->data[self::CLIENT_APP_ID] = $clientAppId;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setUri(string $uri)
    {
        $this->data[self::URI] = $uri;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedAt(DateTime $createdAt)
    {
        $this->data[self::CREATED_AT] = $createdAt;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCompleted(bool $bComplete)
    {
        $this->data[self::COMPLETED] = $bComplete;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function save(): bool
    {
        return !$this->errorOnSave;
    }

    /**
     * {@inheritdoc}
     */
    public function load(string $id): bool
    {
        return !$this->errorOnLoad;
    }
}
