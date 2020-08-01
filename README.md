# Microsoft Azure B2C OAuth2 Provider for Laravel Socialite

## 1. Installation
```sh
// This assumes that you have composer installed globally
composer require rinkp/socialite-azureb2c
```

## 2. Service provider
* Remove `Laravel\Socialite\SocialiteServiceProvider` from your providers[] array in `config\app.php` if you have added it already.
* Add `\SocialiteProviders\Manager\ServiceProvider::class` to your providers[] array in `config\app.php`.

For example:
```php
'providers' => [
    // a whole bunch of providers
    // remove 'Laravel\Socialite\SocialiteServiceProvider',
    \SocialiteProviders\Manager\ServiceProvider::class, // add
];
```

## 3. Event listener
* For signing in (required):
  * Add the `\SocialiteProviders\Manager\SocialiteWasCalled::class` event to your listen array, if it wasn't added already.
  * Add the `SocialiteProviders\\AzureB2C\\AzureB2CExtendSocialite@handle` listener event to the `SocialiteWasCalled` event.
* For single sign out (optional):
  * Add the `Illuminate\Auth\Events\Logout::class` event to your listen array, if it wasn't added already.
  * Add the `SocialiteProviders\\AzureB2C\\AzureB2CExtendSocialite@logOut` listener event to the `Logout` event.

```php
class EventServiceProvider extends ServiceProvider
{
    /**
     * ...
     */
    protected $listen = [
        /* ... */

        \SocialiteProviders\Manager\SocialiteWasCalled::class => [
            'SocialiteProviders\\AzureB2C\\AzureB2CExtendSocialite@handle',
        ],

        Illuminate\Auth\Events\Logout::class => [
            'SocialiteProviders\\AzureB2C\\AzureB2CExtendSocialite@logOut',
        ],

        /* ... */
    ];
}
```

See also the [SocialiteProviders/Manager](https://github.com/SocialiteProviders/Manager) documentation.

## 4. Configuration setup
In `config/services.php` you can set the required configuration.

| Setting         |                                                                             |
|-----------------|-----------------------------------------------------------------------------|
| client_id       | Azure App Registration Client ID                                            |
| client_secret   | Azure App Registration Client Secret                                        |
| redirect        | Callback URL                                                                |
| redirect_logout | Where to redirect after a successful single sign-out                        |
| tenant          | A verified domain associated with the tenant                                |
| tenant_name     | The name of your tenant (the part before .onmicrosoft.com or .b2clogin.com) |
| flow_name       | The name of the flow registered in the directory                            |
| cache_time      | For how long the OpenID configuration and JWT keys need to be cached        |

```php
return [
    /* ... */

    'azureb2c' => [
        'client_id' => env('AZUREB2C_CLIENT_ID'),
        'client_secret' => env("AZUREB2C_CLIENT_SECRET"),
        'redirect' => env("AZUREB2C_REDIRECT_URL"),
        'redirect_logout' => env("AZUREB2C_LOGOUT_URL"),
        'tenant' => env("AZUREB2C_TENANT", env("AZUREB2C_TENANT_NAME") . ".onmicrosoft.com"),
        'tenant_name' => env("AZUREB2C_TENANT_NAME"),
        'flow_name' => env("AUZREB2C_FLOW_NAME"),
        'cache_time' => env("AUZREB2C_CACHE_TIME")
    ],

    /* ... */
];
```
