<?php

namespace UseTheFork\LaravelElasticsearchModel\Database\Eloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Builder extends EloquentBuilder
{
    protected $type;

    /**
     * The methods that should be returned from query builder.
     *
     * @var array
     */
    protected $passthru = [
        'average',
        'avg',
        'count',
        'dd',
        'doesntExist',
        'dump',
        'exists',
        'getBindings',
        'getConnection',
        'getGrammar',
        'insert',
        'insertGetId',
        'insertOrIgnore',
        'insertUsing',
        'max',
        'min',
        'pluck',
        'pull',
        'push',
        'raw',
        'sum',
        'toSql',
    ];

    /**
     * {@inheritdoc}
     */
    protected function addUpdatedAtColumn(array $values)
    {
        if (
            ! $this->model->usesTimestamps() ||
            is_null($this->model->getUpdatedAtColumn())
        ) {
            return $values;
        }

        $column = $this->model->getUpdatedAtColumn();

        $values = array_merge(
            [$column => $this->model->freshTimestampString()],
            $values
        );

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate(array $items)
    {
        $instance = $this->newModelInstance();

        return $instance->newCollection(
            array_map(function ($item) use ($instance) {
                return $instance->newFromBuilder(
                    $item,
                    $this->getConnection()->getName()
                );
            }, $items)
        );
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return Collection|static[]
     */
    public function get($columns = ['*'])
    {
        $builder = $this->applyScopes();

        $models = $builder->getModels($columns);

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded, which will solve the
        // n+1 query issue for the developers to avoid running a lot of queries.
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $builder->getModel()->newCollection($models);
    }

    public function getDocumentFromQuery($id)
    {
        $builder = $this->applyScopes();
        $result = $builder->query->document($id);

        if (empty($result)) {
            return $this->hydrate([])->first();
        }

        return $this->hydrate([$result])->first();
    }

    /**
     * @param $id
     * @return mixed
     */
    public function documentOrNew($id)
    {
        if (! is_null($model = $this->getDocumentFromQuery($id))) {
            return $model;
        }

        $instance = $this->newModelInstance([]);
        $instance['id'] = $id;

        return $instance;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function documentOrFail($id)
    {
        if (! is_null($model = $this->getDocumentFromQuery($id))) {
            return $model;
        }

        throw (new ModelNotFoundException())->setModel(
            get_class($this->model),
            $id
        );
    }
}
