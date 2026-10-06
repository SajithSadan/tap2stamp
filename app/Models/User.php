<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, RecordsActivity;

    protected array $activityIgnore = ['onboarding_draft', 'email_verified_at'];

    protected array $activitySecret = ['password'];

    public function activityLabel(): string
    {
        return $this->email;
    }

    public function activityShopId(): ?int
    {
        // Not $this->shop - that would cache the relation (null right after sign-up).
        return Shop::where('user_id', $this->id)->value('id');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'google_id',
        'password',
        'remember_token',
        'onboarding_draft',
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
            'role' => UserRole::class,
            'onboarding_draft' => 'array',
        ];
    }

    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Where this user belongs after logging in or signing up: the admin panel,
     * or the owner dashboard - via shop setup first if they haven't got one.
     */
    public function homeUrl(): string
    {
        if ($this->isAdmin()) {
            return route('admin.index');
        }

        return $this->shop()->exists() ? route('dashboard.index') : route('onboarding.create');
    }
}
