# SSO Auth Requests

A PHP library for handling web application authorisation requests to the Serato SSO service.

## SSO authorisation request lifecycle

The SSO authorisation request lifecycle for a web application is as follows:

1. The web application creates a new authorisation request using the `\Serato\SsoRequest\AuthRequest` class:
    * The web application provides a return URL that the SSO service will redirect to after the sign on process.
    * A storage mechanism is provided to persist the authorisation request details during redirection to the SSO website. 
2. The new authorisation request returns an ID.
3. The browser is redirected to the SSO website providing the authorisation request ID in the `state` URI parameter.
4. The browser is returned to the web application from the SSO service, with the SSO service providing back the `state` parameter as well as a `code` parameter.
5. The web application creates an `\Serato\SsoRequest\AuthRequest` instance by providing the authorisation id passed via the `state` URI parameter.
6. The web application receives access and refresh tokens from the SSO service by using the `\Serato\SsoRequest\AuthRequest` instance and the value provided in the `code` URI parameter.

## Storing authorisation requests during SSO redirection

A `Serato\SsoRequest\AuthRequestStorageInterface` storage interface is defined for storing authorisation requests during SSO redirection.

A `AuthRequestStorageInterface` implementation stores the application ID, request ID and redirect URL values used during the authorisation lifecyle, as well as timestamps and a means of indication that the authorisation process is complete.

The `Serato\SsoRequest\AuthRequestDynamoDbStorage` class provides an implementation of `Serato\SsoRequest\AuthRequestStorageInterface` using a DynamoDB table as the storage mechanism.

## Using the `\Serato\SsoRequest\AuthRequest` class in the request lifecycle

Note: All examples use `Serato\SsoRequest\AuthRequestDynamoDbStorage` as the storage mechanism.

**Create a new authorisation request (Step 1. above)**

```php
use Serato\SsoRequest\AuthRequest;
use Serato\SsoRequest\AuthRequestDynamoDbStorage;

// Application ID of the web application
$appId = 'my-app-id';

// URI that the SSO service will redirect to after sign on
$redirectUri = 'http://my.server.com/uri/after/soo';

// Create a new AuthRequest
// Assumes `$awsSdk` is a correctly configured `Aws\Sdk` instance
$authRequest = AuthRequest::create(
    $appId,
    $redirectUri,
    new AuthRequestDynamoDbStorage($awsSdk)
);

// Get the new request ID
$requestId = $authRequest->getId();

 // Construct the SSO service URI to redirect the browser to
$ssoStartUri = 'http://sso.service.com?' . http_build_query([
    'app_id' => $appId,
    'state' => $authRequest->getId(),
    'redirect_uri' => $redirectUri
]);

## Redirect the browser to the SSO service
```

**Create a `AuthRequest` instance after returning to the web applicaton after sign on (Step 5. above), and use it to fetch refresh and access tokens from the SSO service (Step 6. above)**

```php
use Serato\SsoRequest\AuthRequest;
use Serato\SsoRequest\AuthRequestDynamoDbStorage;

// Application ID of the web application
$appId = 'my-app-id';

// Create a new AuthRequest
// Assumes `$awsSdk` is a correctly configured `Aws\Sdk` instance
// Assumes `$requestId` is obtained from the `state` URI parameter
$authRequest = AuthRequest::createFromStorage(
    $requestId,
    $appId,
    new AuthRequestDynamoDbStorage($awsSdk)
);

// Now fetch refresh and access tokens from the SSO service
// Assumes `$swsSdk` a configured `Serato\SwsSdk\Sdk` instance;
// Assumes `$code` is obtained from the `code` URI parameter
$result = $authRequest->getTokens($swsSdk, $code);

## $result is a `Serato\SwsSdk\Result` instance
## Use array access syntax to access result data
```
