<?php

namespace Kriptiko\VueTable;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VueTableRequest
{
    /**
     * @var array
     */
    protected $columns;

    /**
     * @var array
     */
    protected $filters;

    /**
     * @var int
     */
    protected $perPage;

    /**
     * @var Builder
     */
    protected $query;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var string
     */
    protected $search;

    /**
     * VueTableRequest constructor.
     *
     * @param  Builder  $query
     */
    public function __construct(Builder $query)
    {
        $this->request = app('request');

        $this->query = $query;

        $this->runQuery();
    }

    /**
     * Get the query builder instance.
     *
     * @return Builder
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Runs the query applying filters, sorting and search.
     */
    public function runQuery()
    {
        $this->columns = $this->request->get('columns') ?? [];
        $this->filters = $this->request->get('filters') ?? [];
        $this->perPage = $this->request->get('perPage') ?? 15;
        $this->search = $this->request->get('search') ?? '';

        $this->filterColumns();
        $this->sortColumns();
        $this->searchColumns();

        $this->query->select($this->extractColumnNames());
    }

    /**
     * Add subselect queries to count the relations.
     *
     * @param  mixed  $relations
     */
    public function withCount($relations)
    {
        $this->query->withCount(is_array($relations) ? $relations : func_get_args());
    }

    /**
     * Get the query result.
     *
     * @return LengthAwarePaginator
     */
    public function paginated()
    {
        return $this->query->paginate($this->perPage);
    }

    /**
     * Returns an array containing the columns' names.
     *
     * @return array
     */
    private function extractColumnNames(): array
    {
        $names = [];

        foreach ($this->columns as $column => $columnData) {
            if (! Str::contains($column, '.')) {
                $names[] = $column;
            }
        }

        return count($names) ? $names : ['*'];
    }

    /**
     * Filters the columns.
     */
    private function filterColumns()
    {
        foreach ($this->columns as $name => $settings) {
            if (! isset($settings['value'])) {
                continue;
            }

            $value = $settings['value'] ?? null;
            $modifiers = $settings['modifiers'] ?? [];

            if (Str::contains($name, '.')) {
                $relationBits = explode('.', $name);

                $attribute = array_pop($relationBits);

                $relation = implode('.', $relationBits);

                $this->query->whereHas($relation, function (Builder $query) use ($modifiers, $attribute, $value) {
                    $this->applyFilter($query, $modifiers, $attribute, $value);
                });

                continue;
            }

            $this->applyFilter($this->query, $modifiers, $name, $value);
        }
    }

    /**
     * Apply a filter by searching a column and applying the modifiers.
     *
     * @param  Builder  $query
     * @param  array  $modifiers
     * @param  string  $attribute
     * @param $values
     */
    protected function applyFilter(Builder $query, array $modifiers, string $attribute, $values)
    {
        if (is_array($values)) {
            if (isset($modifiers['range'])) {
                $query->whereBetween($attribute, $values);
            } else {
                $query->whereIn($attribute, $values);
            }
        } else {
            $query->where($attribute, $values);
        }
    }

    /**
     * Sorts the columns.
     */
    private function sortColumns()
    {
        $sortTypes = ['asc', 'desc'];

        foreach ($this->columns as $name => $settings) {
            if (isset($settings['sort'])) {
                $sort = strtolower($settings['sort']);

                if (in_array($sort, $sortTypes)) {
                    $this->query->orderBy($name, $sort);
                }
            }
        }
    }

    /**
     * Search the columns.
     */
    private function searchColumns()
    {
        $relations = $this->query->getEagerLoads();

        $this->query->where(function ($query) use (&$relations) {
            foreach ($this->columns as $name => $settings) {
                $isSearchable = filter_var($settings['searchable'], FILTER_VALIDATE_BOOLEAN);

                if ($isSearchable) {
                    if (Str::contains($name, '.')) {
                        $relationBits = explode('.', $name);

                        $attribute = array_pop($relationBits);

                        $relation = implode('.', $relationBits);

                        $relations[] = $relation;

                        $query->orWhereHas($relation, function (Builder $query) use ($attribute) {
                            $query->where($attribute, 'LIKE', sprintf('%%%s%%', $this->search));
                        });

                        continue;
                    }

                    $query->orWhere($name, 'LIKE', sprintf('%%%s%%', $this->search));
                }
            }
        });

        // Eager load relations
        $this->query->with($relations);
    }
}
