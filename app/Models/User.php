<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Library\Bookshelf;
use App\Notifications\Auth\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property Role $role
 * @property Carbon|null $password_set_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => Role::User->value,
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
            'password_set_at' => 'datetime',
            'role' => Role::class,
        ];
    }

    /**
     * False for a member created by an administrator who has not chosen a
     * password yet: only then may the invitation link be used.
     */
    public function hasSetPassword(): bool
    {
        return $this->password_set_at !== null;
    }

    /**
     * The library of the member: their bookshelves, in their order.
     *
     * @return HasMany<Bookshelf, $this>
     */
    public function bookshelves(): HasMany
    {
        return $this->hasMany(Bookshelf::class)->orderBy('position')->orderBy('id');
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
