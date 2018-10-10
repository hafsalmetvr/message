<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Socialite;
use App\Services\SocialAccountService;
use Illuminate\Http\Request;

class SocialAuthController extends Controller
{
    public $redirectTo = '/app';
    
    public function __construct()
    {
        
    }
    
    public function redirectFaceBook()
    {
        return $this->redirect('facebook');
    }   

    public function callbackFaceBook(SocialAccountService $service)
    {
        return $this->callback($service, 'facebook');
    }
    
    public function redirectGoogle()
    {
        config(['services.google.redirect' => config('services.google.sign_redirect')]);
        return $this->redirect('google');
    }   

    public function callbackGoogle(SocialAccountService $service)
    {
        config(['services.google.redirect' => config('services.google.sign_redirect')]);
        return $this->callback($service, 'google');
    }
    
    public function redirectTwitter()
    {
        return $this->redirect('twitter');
    }   

    public function callbackTwitter(SocialAccountService $service)
    {
        return $this->callback($service, 'twitter');
    }

    public function redirect($driver)
    {
        return Socialite::driver($driver)->redirect();
    }   

    public function callback(SocialAccountService $service, $driver)
    {
        $user = $service->createOrGetUser(Socialite::driver($driver)->user(), $driver);
        
        if ($user == null) {
            return 'We cant find your email address.';
        }

        auth()->login($user);

        return redirect()->to($this->redirectTo);
    }
}