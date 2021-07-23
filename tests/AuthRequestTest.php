<?php

declare(strict_types=1);

namespace Serato\SsoRequest\Test;

use Serato\SsoRequest\Test\AbstractTestCase;
use Serato\SsoRequest\AuthRequest;
use Serato\SsoRequest\Test\StorageMock;
use Serato\SsoRequest\AuthRequestStorageInterface;
use Ramsey\Uuid\Uuid;
use DateTime;
use DateInterval;
use Serato\SwsSdk\Sdk as SwsSdk;
use GuzzleHttp\Psr7\Response;
use Exception;

class AuthRequestTest extends AbstractTestCase
{
    private const CLIENT_APP_ID = 'my-client-app-id';
    private const REDIRECT_URI = 'my://test/redirect/uri';
    private const ACCESS_TOKEN = 'NgCXRKdjsLksdKKJjslPQmxMzYjw';
    private const REFRESH_TOKEN = 'NgAagAAYqJQjdkEkjkjSkkseKSKaweplOeklUm_SHo';

    /**
     * @expectedException \Exception
     */
    public function testExceptionOnStorageSaveError()
    {
        $storageMock = new StorageMock(false, true);
        $auth = AuthRequest::create('my-app-id', 'my://redirect', $storageMock);
    }

    public function testCreate()
    {
        $clientId = '';
        $redirectUri = 'my://redirect';

        $storageMock = new StorageMock();

        $this->assertEquals(null, $storageMock->getId());
        $this->assertEquals(null, $storageMock->getClientAppId());
        $this->assertEquals(null, $storageMock->getUri());
        $this->assertEquals(null, $storageMock->getCreatedAt());
        $this->assertEquals(null, $storageMock->getCompleted());

        $auth = AuthRequest::create($clientId, $redirectUri, $storageMock);

        $this->assertTrue(null !== $storageMock->getId());
        $this->assertEquals($clientId, $storageMock->getClientAppId());
        $this->assertEquals($redirectUri, $storageMock->getUri());
        $this->assertTrue(null !== $storageMock->getCreatedAt());
        $this->assertTrue(null !== $storageMock->getCompleted());
    }

    /**
     * @expectedException \Serato\SsoRequest\Exception\InvalidAuthRequestIdException
     */
    public function testCreateFromStorageBadRequestId()
    {
        $storageMock = $this->getStorageMock(true);
        if ($storageMock->getId() === null || $storageMock->getClientAppId() === null) {
            # This can't happen. It's just here to maintain type safety for phpstan
            throw new Exception();
        }
        $auth = AuthRequest::createFromStorage($storageMock->getId(), $storageMock->getClientAppId(), $storageMock);
    }

    /**
     * @expectedException \Serato\SsoRequest\Exception\InvalidAuthRequestIdException
     */
    public function testCreateFromStorageBadClientAppId()
    {
        $storageMock = $this->getStorageMock();
        if ($storageMock->getId() === null) {
            # This can't happen. It's just here to maintain type safety for phpstan
            throw new Exception();
        }
        $auth = AuthRequest::createFromStorage($storageMock->getId(), self::CLIENT_APP_ID . '+extra', $storageMock);
    }

    /**
     * @expectedException \Serato\SsoRequest\Exception\InvalidAuthRequestIdException
     */
    public function testCreateFromStorageBadCompletedState()
    {
        $storageMock = $this->getStorageMock();
        $storageMock->setCompleted(true);
        if ($storageMock->getId() === null || $storageMock->getClientAppId() === null) {
            # This can't happen. It's just here to maintain type safety for phpstan
            throw new Exception();
        }
        $auth = AuthRequest::createFromStorage($storageMock->getId(), $storageMock->getClientAppId(), $storageMock);
    }

    /**
     * @expectedException \Serato\SsoRequest\Exception\AuthRequestExpiredException
     */
    public function testCreateFromStorageBadCreatedAt()
    {
        $storageMock = $this->getStorageMock();
        $createdAt = new DateTime();
        $createdAt->sub(new DateInterval('PT' . (AuthRequest::EXPIRES_IN + 1) . 'S'));
        $storageMock->setCreatedAt($createdAt);
        if ($storageMock->getId() === null || $storageMock->getClientAppId() === null) {
            # This can't happen. It's just here to maintain type safety for phpstan
            throw new Exception();
        }
        $auth = AuthRequest::createFromStorage($storageMock->getId(), $storageMock->getClientAppId(), $storageMock);
    }

    public function testCreateFromStorage()
    {
        $storageMock = $this->getStorageMock();
        if ($storageMock->getId() === null || $storageMock->getClientAppId() === null) {
            # This can't happen. It's just here to maintain type safety for phpstan
            throw new Exception();
        }
        $auth = AuthRequest::createFromStorage($storageMock->getId(), $storageMock->getClientAppId(), $storageMock);

        $this->assertEquals($auth->getId(), $storageMock->getId());
        $this->assertEquals($auth->getClientAppId(), $storageMock->getClientAppId());
        $this->assertEquals($auth->getRedirectUri(), $storageMock->getUri());
    }

    public function testGetTokensHappyPath()
    {
        $requestBody = json_encode($this->getTokenExchangeBody());
        if ($requestBody === false) {
            # This can't happen. It's just here to maintain type safety for phpstan
            throw new Exception();
        }
        $response = new Response(
            200,
            ['Content-Type' => 'application/json'],
            $requestBody
        );

        $storageMock = $this->getStorageMock();
        $storageMock->setCompleted(false);

        $auth = AuthRequest::createFromStorage('my-request-id', 'my-client-app-id', $storageMock);

        $swsSdk = $this->getMockedSwsSdk([$response]);

        $result = $auth->getTokens($swsSdk, 'my-auth-code');

        $this->assertEquals($result['tokens']['access']['token'], self::ACCESS_TOKEN);
        $this->assertEquals($result['tokens']['refresh']['token'], self::REFRESH_TOKEN);
        $this->assertTrue($storageMock->getCompleted());
    }

    /**
     * @expectedException \Serato\SsoRequest\Exception\TokenExchangeException
     */
    public function testGetTokens400Response()
    {
        $requestBody = json_encode([
            'code' => 1013,
            'error' => 'Invalid `redirect_uri` value. URI does not match with issued authorization code.'
        ]);
        if ($requestBody === false) {
            # This can't happen. It's just here to maintain type safety for phpstan
            throw new Exception();
        }
        $response = new Response(
            400,
            ['Content-Type' => 'application/json'],
            $requestBody
        );

        $storageMock = $this->getStorageMock();
        $storageMock->setCompleted(false);

        $auth = AuthRequest::createFromStorage('my-request-id', 'my-client-app-id', $storageMock);

        $swsSdk = $this->getMockedSwsSdk([$response]);

        $auth->getTokens($swsSdk, 'my-auth-code');
    }

    /**
     * Returns a `Serato\SsoRequest\Test\StorageMock` populated with mock data
     *
     * @param bool $errorOnLoad
     * @param bool $errorOnSave
     * @return AuthRequestStorageInterface
     */
    private function getStorageMock($errorOnLoad = false, $errorOnSave = false): AuthRequestStorageInterface
    {
        $storageMock = new StorageMock($errorOnLoad, $errorOnSave);

        return $storageMock
                    ->setId(Uuid::uuid4()->toString())
                    ->setClientAppId(self::CLIENT_APP_ID)
                    ->setUri(self::REDIRECT_URI)
                    ->setCreatedAt(new DateTime())
                    ->setCompleted(false);
    }

    private function getTokenExchangeBody(): array
    {
        return [
            'user' => [
                'id' => 12345,
                'email_address' => 'example@example.com',
                'first_name' => 'Billy',
                'last_name' => 'Bob',
                'date_created' => '2016-11-05T08:15:30Z',
                'locale' => 'en_US.UTF-8'
            ],
            'tokens' => [
                'access' => [
                    'token' => self::ACCESS_TOKEN,
                    'expires_at' => 1489142529,
                    'scopes' => [
                        'license.serato.io' => ['user-license', 'user-license-admin']
                    ],
                    'type' => 'Bearer'
                ],
                'refresh' => [
                    'token' => self::REFRESH_TOKEN,
                    'expires_at' => 1489174529,
                    'type' => 'Bearer'
                ]
            ]
        ];
    }
}
