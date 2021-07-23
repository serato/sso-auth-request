<?php

declare(strict_types=1);

namespace Serato\SsoRequest;

use Serato\SsoRequest\Exception\InvalidAuthRequestIdException;
use Serato\SsoRequest\Exception\AuthRequestExpiredException;
use Serato\SsoRequest\Exception\TokenExchangeException;
use Serato\SsoRequest\AuthRequestStorageInterface;
use Serato\SwsSdk\Sdk as SwsSdk;
use Serato\SwsSdk\Exception\BadRequestException;
use Serato\SwsSdk\Result;
use Ramsey\Uuid\Uuid;
use Exception;
use DateTime;

class AuthRequest
{
    const EXPIRES_IN = 3600; // seconds

    /* @var AuthRequestStorageInterface */
    private $storage;

    /**
     * Constructs the object
     *
     * @param AuthRequestStorageInterface   $storage  Storage interface instance
     */
    private function __construct(AuthRequestStorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Creates a new instance
     *
     * @param string                        $clientAppId    Client App ID
     * @param string                        $redirectUri    Redirect URI
     * @param AuthRequestStorageInterface   $storage        Storage interface
     *
     * @return self
     */
    public static function create(
        string $clientAppId,
        string $redirectUri,
        AuthRequestStorageInterface $storage
    ): self {
        $id = Uuid::uuid4()->toString();
        $bSuccess = $storage
                        ->setId($id)
                        ->setClientAppId($clientAppId)
                        ->setUri($redirectUri)
                        ->setCreatedAt(new DateTime())
                        ->setCompleted(false)
                        ->save();
        if ($bSuccess) {
            return new self($storage);
        } else {
            throw new Exception('Unable to persist AuthRequest to storage');
        }
    }

    /**
     * Creates a new instance from the provided storage mechanism
     *
     * @param string                        $id             Auth request ID
     * @param string                        $clientAppId    Client App ID
     * @param AuthRequestStorageInterface   $storage        Storage interface
     *
     * @return self
     */
    public static function createFromStorage(
        string $id,
        string $clientAppId,
        AuthRequestStorageInterface $storage
    ): self {
        if (!$storage->load($id)) {
            throw new InvalidAuthRequestIdException();
        }

        // Check that the request was issued for provided client app id
        if ($storage->getClientAppId() !== $clientAppId) {
            throw new InvalidAuthRequestIdException();
        }

        // Check that the request hasn't already been completed
        if ($storage->getCompleted()) {
            throw new InvalidAuthRequestIdException();
        }

        // Check that the request has not expired
        $createdAt = $storage->getCreatedAt();
        $now = new DateTime();
        if (
            $createdAt === null ||
            ($now->getTimestamp() - self::EXPIRES_IN) > $createdAt->getTimestamp()
        ) {
            throw new AuthRequestExpiredException();
        }

        return new self($storage);
    }

    /**
     * Returns a `Serato\SwsSdk\Result` from the SWS Identity service after
     * exchanging an authorization code for Access and Refresh tokens
     *
     * See: http://docs.serato.net/serato/id-serato-com/master/rest-api.html#tokens_exchange_post
     *
     * @param SwsSdk    $swsSdk     An SWS SDK object
     * @param string    $authCode   Authorization code issued by the SWS Identity service
     *
     * @return Result
     *
     * @throws TokenExchangeException
     */
    public function getTokens(SwsSdk $swsSdk, string $authCode): Result
    {
        try {
            $result = $swsSdk
                        ->createIdentityClient()
                        ->tokenExchange([
                            'grant_type'    => 'authorization_code',
                            'code'          => $authCode,
                            'redirect_uri'  => $this->storage->getUri()
                        ]);
            $this->storage->setCompleted(true)->save();
            return $result;
        } catch (BadRequestException $e) {
            // 400 response from ID service.
            throw new TokenExchangeException(
                'Error exchanging authorisation code from tokens. ' .
                "The Identity server returned the following message:\n\n" .
                $e->getMessage(),
                $e->getCode()
            );
        }
    }

    /**
     * Returns the request ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->storage->getId();
    }

    /**
     * Returns the client app id
     *
     * @return string
     */
    public function getClientAppId(): string
    {
        return $this->storage->getClientAppId();
    }

    /**
     * Returns the redirect URI
     *
     * @return string
     */
    public function getRedirectUri(): string
    {
        return $this->storage->getUri();
    }
}
