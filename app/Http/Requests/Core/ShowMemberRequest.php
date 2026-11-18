<?php

namespace App\Http\Requests\Core;

use App\Models\Garage\ToolShelf;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Optional parameters of the page of a member: `?bookshelf=<id>` and
 * `?shelf=<id>`, one of the bookshelves / tool shelves of that member
 * (anything else answers 404, as on « Mes livres » and « Mes outils »), and
 * `?tab=garage` to open the garage tab. The member must be activated.
 */
class ShowMemberRequest extends FormRequest
{
    public const TAB_LIBRARY = 'library';

    public const TAB_GARAGE = 'garage';

    /**
     * An account that is not activated (pending invitation) is not a member
     * of the family yet: its page answers 404.
     */
    public function authorize(): Response
    {
        return $this->user() !== null && $this->member()->hasSetPassword()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function member(): User
    {
        /** @var User */
        return $this->route('member');
    }

    public function bookshelfFilter(): ?Bookshelf
    {
        $id = $this->filterId('bookshelf', Bookshelf::class);

        return $id === null ? null : $this->member()->bookshelves()->whereKey($id)->firstOrFail();
    }

    public function shelfFilter(): ?ToolShelf
    {
        $id = $this->filterId('shelf', ToolShelf::class);

        return $id === null ? null : $this->member()->toolShelves()->whereKey($id)->firstOrFail();
    }

    /**
     * @return self::TAB_*
     */
    public function tab(): string
    {
        return $this->query('tab') === self::TAB_GARAGE ? self::TAB_GARAGE : self::TAB_LIBRARY;
    }

    /**
     * @param  class-string<Bookshelf|ToolShelf>  $model
     */
    private function filterId(string $parameter, string $model): ?int
    {
        if (! $this->has($parameter)) {
            return null;
        }

        $id = $this->query($parameter);
        if (! is_string($id) || ! ctype_digit($id)) {
            throw (new ModelNotFoundException)->setModel($model);
        }

        return (int) $id;
    }
}
