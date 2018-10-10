<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\LimitScope;
use App\Notifications\ResetPassword as ResetPasswordNotification;

class User extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [

        'name', 'first_name', 'last_name', 'email', 'phone', 'password', 'business_name', 'avatar', 'credit_balance','is_get_started','terms_conditions','special_offers','billing_type','business_address','business_country','business_postal_code','business_vat','default_prefix','country',

    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];
    
    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new LimitScope);
    }
    
    public function billingDetail()
    {
        return $this->hasOne(BillingDetail::class);
    }
    
    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }
    
    public function lists()
    {
        return $this->hasMany(Lists::class);
    }
    
    public function messages()
    { 
        return $this->hasMany(Message::class);
    }

    
    public function contactMessages()
    {
        return $this->hasMany(ContactMessage::class);
    }
    
    public function socialAccounts()
    {
        return $this->hasMany(\App\Models\SocialAccount::class);
    }
    /**
    *Get the record associated with SmsBroadcast
    */
    public function broadcasts()
    {
        return $this->hasMany('App\Models\SmsBroadcast','user_id');
    }
    /**
    *Get the record associated with SmsBroadcastMessages
    */
    public function broadcastMessages()
    {
        return $this->hasMany('App\Models\SmsBroadcastMessage');
    }
    /**
    *Get the record associated with templates
    */
    public function templates()
    {
        return $this->hasMany(\App\Models\Template::class);
    }
    /**
    *Get the avatar url attribute
    */
    public function getAvatarUrlAttribute()
    {
       if($this->avatar) { 
              return statelessAssetUrl('/storage/avatar/'.$this->avatar);
       }
    }
    /**
    *Get the record associated with verify_users table
    */
     public function verifyUser()
    { 
        return $this->hasOne('App\Models\VerifyUser');
    }

    public function sendPasswordResetNotification($token)
    {
        // Your your own implementation.
        $this->notify(new ResetPasswordNotification($token));
    }

    public function deleteRequest()
    { 
        return $this->hasOne('App\Models\deleteRequest');
    }
}
