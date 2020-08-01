<?php

namespace SocialiteProviders\AzureB2C;

use Socialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AzureB2CExtendSocialite
{
    const IDENTIFIER = 'azureb2c';

    /**
     * Execute the provider.
     */
    public function handle(SocialiteWasCalled $socialiteWasCalled)
    {
        $socialiteWasCalled->extendSocialite(
            self::IDENTIFIER,
            __NAMESPACE__.'\Provider'
        );
    }

    /**
     *  Sign out the current user, if sign in was using this provider
     */
     public function logOut($data) {
         Socialite::driver(self::IDENTIFIER)->logOut($data->guard, $data->user);
     }
}
