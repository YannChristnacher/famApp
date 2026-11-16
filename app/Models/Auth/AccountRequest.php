<?php

namespace App\Models\Auth;

use App\Enums\AccountRequestStatus;
use Database\Factories\Auth\AccountRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Account requested by a visitor from the public form; the administrator
 * creates the account or rejects the request (F04).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $message
 * @property AccountRequestStatus $status
 */
#[Fillable(['name', 'email', 'message'])]
#[UseFactory(AccountRequestFactory::class)]
class AccountRequest extends Model
{
    /** @use HasFactory<AccountRequestFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => AccountRequestStatus::Pending->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AccountRequestStatus::class,
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', AccountRequestStatus::Pending);
    }
}
