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
     * @var array
     */
    protected $sorting;

    /**
     * VueTableRequest constructor.
     *
     * @param Builder $query
     */
    public function __construct(Builder $query)
    {
        $this->request = app('request');

        $this->query = $query;

        $this->runQuery();
    }

    /**
     * Runs the query applying filters, sorting and search.
     */
    public function runQuery()
    {
        $this->columns = $this->request->get('columns') ?? [];
        $this->filters = $this->request->get('filters') ?? [];
        $this->perPage = $this->request->get('perPage') ?? 15;
        $this->search  = $this->request->get('search') ?? '';
        $this->sorting = $this->request->get('sorting') ?? [];

        $this->filterColumns();
        $this->sortColumns();
        $this->searchColumns();
    }

    /**
     * Get the query result.
     *
     * @return LengthAwarePaginator
     */
    public function paginated()
    {
        return $this->query->paginate($this->perPage, $this->extractColumnNames());
    }

    /**
     * Returns an array containing the columns' names.
     *
     * @return array
     */
    private function extractColumnNames(): array
    {
        $names = [];

        foreach ($this->columns as $column) {
            if (isset($column['name']) && !Str::contains($column['name'], '.')) {
                $names[] = $column['name'];
            }
        }

        return count($names) ? $names : ['*'];
    }

    /**
     * Filters the columns.
     */
    private function filterColumns()
    {
        foreach ($this->filters as $filter) {
            $values = $filter['values'];

            if (Str::contains($filter['column'], '.')) {
                $relationBits = explode('.', $filter['column']);

                $attribute = array_pop($relationBits);

                $relation = implode('.', $relationBits);

                $this->query->whereHas($relation, function (Builder $query) use ($attribute, $values) {
                    if (is_array($values)) {
                        $query->whereIn($attribute, $values);
                    } else {
                        $this->where($attribute, $values);
                    }
                });

                continue;
            }

            if (is_array($values)) {
                $this->query->whereIn($filter['column'], $values);
            } else {
                $this->query->where($filter['column'], $values);
            }
        }
    }

    /**
     * Sorts the columns.
     */
    private function sortColumns()
    {
        foreach ($this->sorting as $sort) {
            if (!isset($sort['column']) || !isset($sort['direction'])) {
                continue;
            }

            if (!in_array($sort['direction'], ['asc', 'desc'])) {
                continue;
            }

            $this->query->orderBy($sort['column'], $sort['direction']);
        }
    }

    /**
     * Search the columns.
     */
    private function searchColumns()
    {
        $relations = [];

        $this->query->where(function ($query) use (&$relations) {
            foreach ($this->columns as $column) {
                $isSearchable = $column['searchable'] ?? false;

                if (isset($column['name']) && filter_var($isSearchable, FILTER_VALIDATE_BOOLEAN)) {
                    if (Str::contains($column['name'], '.')) {
                        $relationBits = explode('.', $column['name']);

                        $attribute = array_pop($relationBits);

                        $relation = implode('.', $relationBits);

                        $relations[] = $relation;

                        $query->orWhereHas($relation, function (Builder $query) use ($attribute) {
                            $query->where($attribute, 'LIKE', sprintf('%%%s%%', $this->search));
                        });

                        continue;
                    }

                    $query->orWhere($column['name'], 'LIKE', sprintf('%%%s%%', $this->search));
                }
            }
        });

        // Eager load relations
        $this->query->with($relations);
    }
}
