<?php

namespace Tigress;

use Google\Client;
use Google\Exception;

/**
 * Class GoogleApiAuth (PHP version 8.3)
 *
 * @author Rudy Mas <rudy.mas@rudymas.be>
 * @copyright 2024, rudymas.be. (http://www.rudymas.be/)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version 1.1.3
 * @lastmodified 2024-10-11
 * @package Tigress\GoogleApiAuth
 */
class GoogleApiAuth
{
    private string $authConfigPath;
    private string $credentialsPath;
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * Get the version of the class.
     *
     * @return string
     */
    public static function version(): string
    {
        return '1.1.3';
    }

    /**
     * Set up the connection with the Google API.
     *
     * @param string $applicationName
     * @param array $scopes
     *      - https://www.googleapis.com/auth/drive
     *      - https://www.googleapis.com/auth/script.[projects|deployments|...]
     *      - https://www.googleapis.com/auth/calendar
     * @param string|null $subject
     *      - The email address of the user for which the application is requesting delegated access.
     * @param string|null $accessType
     *      - offline: (default) the refresh token may be used at any time to obtain a new access token.
     *      - online: the refresh token may be used only once to obtain a new access token.
     * @param string $prompt
     * @return void
     * @throws Exception
     */
    public function createConnection(
        string  $applicationName,
        array   $scopes = ['https://www.googleapis.com/auth/drive'],
        ?string $subject = null,
        ?string $accessType = 'offline',
        string  $prompt = 'select_account consent'
    ): void
    {
        $this->client->setApplicationName($applicationName);
        $this->client->setAuthConfig($this->getAuthConfigPath());
        $this->client->setScopes($scopes);

        if (!is_null($subject)) {
            $this->client->setSubject($subject);
        }

        if (!is_null($accessType)) {
            $this->client->setAccessType($accessType);
        }

        if (!is_null($prompt)) {
            $this->client->setPrompt($prompt);
        }
    }

    /**
     * Set ip the OAuth2 service.
     *
     * @param string $applicationName
     * @param string $clientId
     *      - The client ID of the OAuth client.
     * @param string $clientSecret
     *      - The client secret of the OAuth client.
     * @param string $redirectUri
     *      - The URI to redirect to after the user grants/denies permission.
     * @param array $scopes
     *      - email
     *      - profile
     *      - openid
     * @return void
     */
    public function createOauth2Service(
        string $applicationName,
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        array  $scopes = ['email', 'profile', 'openid'],
    ): void
    {
        $this->client->setApplicationName($applicationName);
        $this->client->setClientId($clientId);
        $this->client->setClientSecret($clientSecret);
        $this->client->setRedirectUri($redirectUri);
        $this->client->addScope($scopes);
    }

    /**
     * Create the authorization URL.
     *
     * @return string
     */
    public function createAuthUrl(): string
    {
        return filter_var($this->client->createAuthUrl(), FILTER_SANITIZE_URL);
    }

    /**
     * Fetch the access token with the authorization code.
     *
     * @param string $authCode
     * @return void
     */
    public function fetchAccessTokenWithAuthCode(string $authCode): void
    {
        $this->client->fetchAccessTokenWithAuthCode($authCode);
    }

    /**
     * Get the access token.
     *
     * @return array
     */
    public function getAccessToken(): array
    {
        return $this->client->getAccessToken();
    }

    /**
     * Set the access token.
     *
     * @param array $accessToken
     * @return void
     */
    public function setAccessToken(array $accessToken): void
    {
        $this->client->setAccessToken($accessToken);
    }

    /**
     * Check if the access token is expired.
     *
     * @return bool
     */
    public function isAccessTokenExpired(): bool
    {
        return $this->client->isAccessTokenExpired();
    }

    /**
     * Check if the token is expired, if so, refresh it.
     *
     * @return array
     */
    public function checkToken(): array
    {
        if (!file_exists($this->getCredentialsPath())) {
            $result = $this->createNewTokenFile();
            if ($result['status'] == 401) {
                return $result;
            }
        }
        $credentials = json_decode(file_get_contents($this->getCredentialsPath()), true);
        $this->client->setAccessToken($credentials);

        if ($this->client->isAccessTokenExpired()) {
            $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            file_put_contents($this->getCredentialsPath(), json_encode($this->client->getAccessToken()));
        }
        return [
            'status' => 200,
            'message' => 'Token is valid',
        ];
    }

    /**
     * Create a new token-file on the server.
     *
     * @return array
     */
    private function createNewTokenFile(): array
    {
        // Request authorization from the user.
        $authUrl = $this->client->createAuthUrl();
        return [
            'status' => 401,
            'authUrl' => $authUrl,
        ];
    }

    /**
     * @return string
     */
    public function getCredentialsPath(): string
    {
        return $this->credentialsPath;
    }

    /**
     * @param string $credentialsPath
     * @return void
     */
    public function setCredentialsPath(string $credentialsPath): void
    {
        $this->credentialsPath = $credentialsPath;
    }

    /**
     * @return string
     */
    public function getAuthConfigPath(): string
    {
        return $this->authConfigPath;
    }

    /**
     * @param string $authConfigPath
     * @return void
     */
    public function setAuthConfigPath(string $authConfigPath): void
    {
        $this->authConfigPath = $authConfigPath;
    }
}