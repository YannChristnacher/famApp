<?php

namespace App\Services\Core\Search;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The text typed in the search palette, and the LIKE conditions built from it.
 *
 * Laravel's whereLike() has no ESCAPE clause and SQLite has no default escape
 * character, so the condition is written here, once, with bound values:
 * "<column> like ? escape '!'" ("!" rather than a backslash, whose escaping
 * differs between engines). "%", "_" and "!" of the term are taken literally.
 *
 * Case and accents follow the collation of the column: MySQL
 * (utf8mb4_unicode_ci, production and CI) ignores both; SQLite (local tests)
 * ignores the case of ASCII letters only.
 */
final readonly class SearchTerm
{
    private const ESCAPE = '!';

    public string $value;

    public function __construct(string $value)
    {
        $this->value = trim($value);
    }

    public function startsWithPattern(): string
    {
        return $this->escaped().'%';
    }

    public function containsPattern(): string
    {
        return '%'.$this->escaped().'%';
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function whereStartsWith(Builder $query, string $column, string $boolean = 'and'): Builder
    {
        return $this->whereLike($query, $column, $this->startsWithPattern(), $boolean);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function whereContains(Builder $query, string $column, string $boolean = 'and'): Builder
    {
        return $this->whereLike($query, $column, $this->containsPattern(), $boolean);
    }

    /**
     * At most $limit models of $query matching the term, the most relevant
     * first: those whose $labelColumn starts with the term (first query),
     * then those matched by $matches (second query, only if there is room),
     * each rank ordered by label then id. Eager loads of $query are kept.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  Closure(Builder<TModel>): mixed  $matches  Adds the "or" conditions of every searched field.
     * @return Collection<int, TModel>
     */
    public function ranked(Builder $query, string $labelColumn, Closure $matches, int $limit): Collection
    {
        $starting = $this->orderedByLabel($this->whereStartsWith($query->clone(), $labelColumn), $labelColumn)
            ->limit($limit)
            ->get();
        if ($starting->count() >= $limit) {
            return $starting;
        }

        $others = $this->orderedByLabel($query->clone(), $labelColumn)
            ->whereKeyNot($starting->modelKeys())
            ->where(fn (Builder $any) => $matches($any))
            ->limit($limit - $starting->count())
            ->get();

        return $starting->concat($others);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function orderedByLabel(Builder $query, string $labelColumn): Builder
    {
        return $query->orderBy($labelColumn)->orderBy($query->getModel()->getQualifiedKeyName());
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function whereLike(Builder $query, string $column, string $pattern, string $boolean): Builder
    {
        $wrapped = $query->getQuery()->getGrammar()->wrap($column);

        return $query->whereRaw("{$wrapped} like ? escape '".self::ESCAPE."'", [$pattern], $boolean);
    }

    private function escaped(): string
    {
        return str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            $this->value,
        );
    }
}
