<?php
declare(strict_types=1);

namespace Serato\SsoRequest;

use DateTime;

/**
 * Representation of a storage mechanism for storing authorization requests
 */
interface AuthRequestStorageInterface
{
    /**
     * Returns the request ID
     *
     * @return string
     */
    public function getId(): ?string;

    /**
     * Returns the client app ID
     *
     * @return string
     */
    public function getClientAppId(): ?string;

    /**
     * Returns the source URI for the auth request
     *
     * @return void
     */
    public function getUri(): ?string;

    /**
     * Returns the creation date of the auth request
     *
     * @return DateTime
     */
    public function getCreatedAt(): ?DateTime;

    /**
     * Returns whether or not the auth request has been completed
     *
     * @return bool
     */
    public function getCompleted(): bool;

    /**
     * Sets the request ID
     *
     * @param string $id  A unique identifier for the auth request
     * @return self
     */
    public function setId(string $id);

    /**
     * Sets the client app ID
     *
     * @param string $clientAppId  Client application making the auth request
     * @return self
     */
    public function setClientAppId(string $clientAppId);

    /**
     * Sets the source URI for the auth request
     *
     * @param string $uri    Source URI for the auth request
     * @return self
     */
    public function setUri(string $uri);

    /**
     * Sets the creation date of the auth request
     *
     * @param DateTime $createdAt    Creation date of auth request
     * @return self
     */
    public function setCreatedAt(DateTime $createdAt);

    /**
     * Sets whether or not the auth request has been completed
     *
     * @param bool  $bComplete    Completed state
     * @return self
     */
    public function setCompleted(bool $bComplete);

    /**
     * Save an auth request to the storage mechanism
     *
     * @return bool Success
     */
    public function save(): bool;

    /**
     * Load an auth request from the storage mechanism
     *
     * @param string $id  The unique identifier for the auth request
     *
     * @return bool Success
     */
    public function load(string $id): bool;
}
