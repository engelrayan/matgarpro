<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Actions\Stores\CreateStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * A merchant may run more than one store on a single login. Most run one,
     * but modelling it as a collection now costs nothing and avoids a painful
     * migration the first time somebody launches a second brand.
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /**
     * The store every dashboard screen works against.
     *
     * Registration always creates one, so this normally just reads it back.
     * The fallback exists because the alternative is worse: a merchant whose
     * store row is missing for any reason would hit a 404 on every screen with
     * no way to recover, and there is no "create a store" flow to send them to.
     * Making one is the repair.
     */
    public function currentStore(): Store
    {
        return $this->stores()->oldest('id')->first()
            ?? app(CreateStore::class)->handle($this, $this->name);
    }
}
