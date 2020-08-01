<?php

namespace SocialiteProviders\AzureB2C;

use Exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Two\InvalidStateException;
use phpseclib\Crypt\RSA as Crypt_RSA;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider
{
    /**
     * Unique Provider Identifier.
     */
    const IDENTIFIER = 'azureb2c';

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(
            $this->getOpenIdConfiguration()->authorization_endpoint,
            $state
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return $this->getOpenIdConfiguration()->token_endpoint;
    }

    /**
     * {@inheritdoc}
     */
    public function user()
    {
        if ($this->hasInvalidState()) {
            throw new InvalidStateException();
        }

        //Get id_token (and possibly access token depending on scope)
        $response = $this->getAccessTokenResponse($this->getCode());
        $this->credentialsResponseBody = $response;

        $user = $this->mapUserToObject($this->getUserByToken(
            $token = $this->parseIdToken($response)
        ));

        if ($user instanceof User) {
            $user->setAccessTokenResponseBody($this->credentialsResponseBody);
        }

        session(['socialite_' . self::IDENTIFIER . '_idtoken' => $token]);

        return $user->setToken($token);
    }

    /**
     * Get the id token from the token response body.
     *
     * @param string $body
     *
     * @return string
     */
    protected function parseIdToken($body)
    {
        return Arr::get($body, 'id_token');
    }

    public function getAccessToken($code)
    {
        throw new InvalidArgumentException();
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'form_params' => $this->getTokenFields($code),
        ]);

        $this->credentialsResponseBody = json_decode($response->getBody(), true);

        return $this->parseAccessToken($response->getBody());
    }

    /**
     * Get the raw user for the given id token.
     *
     * @param  string  $token
     * @return array
     */
    protected function getUserByToken($token)
    {
        $keys = $this->getJWTKeys();
        return (array) JWT::decode($token, $keys, $this->getOpenIdConfiguration()->id_token_signing_alg_values_supported);
    }

    /**
     * Get the current JWT signing keys in an openssl supported format
     *
     * @return array
     */
    private function getJWTKeys() {
        return Cache::remember('socialite_' . self::IDENTIFIER . '_jwtpublickeys', intval($this->config['cache_time'] ?: 3600), function () {
            Log::debug('Grabbing JWT public keys');
            $response = $this->getHttpClient()->get($this->getOpenIdConfiguration()->jwks_uri);
            $jwks = json_decode($response->getBody(), true);
            $public_keys = array();
            foreach ($jwks['keys'] as $jwk) {
                $jwk['n'] = strtr($jwk['n'], '-_', '+/');
                $public_keys[$jwk['kid']] = new Crypt_RSA();
                $public_keys[$jwk['kid']]->loadKey(
                      "<RSAKeyPair>"
                    .   "<Modulus>" . $jwk['n'] . "</Modulus>"
                    .   "<Exponent>" . $jwk['e'] . "</Exponent>"
                    . "</RSAKeyPair>",
                    Crypt_RSA::PUBLIC_FORMAT_XML
                );
            }
            return $public_keys;
        });
    }

    /**
     *
     *
     */
    private function getOpenIdConfiguration() {
     return Cache::remember('socialite_' . self::IDENTIFIER . '_openidconfiguration', intval($this->config['cache_time'] ?: 3600), function () {
         Log::debug('Grabbing AzureB2C OpenID Configuration');
         try {
             $response = $this->getHttpClient()->get('https://' . $this->config['tenant_name'] . '.b2clogin.com/' . ($this->config['tenant'] ?: $this->config['tenant_name'] . ".onmicrosoft.com") . '/' . $this->config['flow_name'] . '/v2.0/.well-known/openid-configuration', ['http_errors' => true]);
         } catch(ClientException $e) {
             throw new Exception("The OpenID configuration could not be fetched. Please check whether 'tenant', 'tenant_name' and 'flow_name' were set correctly");
         }
         return json_decode($response->getBody());
     });
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        //We only return the common fields. All other fields can be found in 'user'
        return (new User())->setRaw($user)->map([
            'id'    => $user['sub'],
            'nickname' => $user['given_name'],
            'name' => $user['name'],
            'email' => $user['emails'][0],
            'avatar' => null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }

    /**
     * Add additional required config items
     *
     * @return array
     */
    public static function additionalConfigKeys()
    {
        return ['tenant', 'tenant_name', 'flow_name', 'cache_time', 'redirect_logout'];
    }

    public function logOut($guard, $user) {
        $idToken = session('socialite_' . self::IDENTIFIER . '_idtoken');
        if (!empty($idToken)) {
            abort(redirect($this->getOpenIdConfiguration()->end_session_endpoint . "?id_token_hint=" . $idToken . "&post_logout_redirect_uri=" . urlencode($this->config['redirect_logout'] ?: request()->fullUrl())));
        }
    }
}
