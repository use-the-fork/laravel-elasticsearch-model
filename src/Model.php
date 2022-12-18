<?php

namespace UseTheFork\LaravelElasticsearchModel;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use UseTheFork\LaravelElasticsearchModel\Database\Eloquent\Concerns\HasAttributes;
use UseTheFork\LaravelElasticsearchModel\Database\Eloquent\Builder;

abstract class Model extends BaseModel
{
    use HasAttributes;

    protected $connection = "elasticsearch";

    /**
     * {@inheritDoc}
     */
    //Nothing should be guarded in elastic
    protected $guarded = [];

    /**
     * {@inheritDoc}
     */
    public $incrementing = false;

    /**
     * {@inheritdoc}
     */
    public function __call($method, $parameters)
    {
        return parent::__call($method, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function qualifyColumn($column)
    {
        return $column;
    }

    /**
     * {@inheritdoc}
     */
    protected function removeTableFromKey($key)
    {
        return $key;
    }

    /**
     * {@inheritdoc}
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * {@inheritdoc}
     */
    public function getDateFormat()
    {
        return $this->dateFormat ?: "c";
    }

    /**
     * {@inheritdoc}
     */
    protected function getAttributeFromArray($key)
    {
        // Support keys in dot notation.
        if (Str::contains($key, ".")) {
            return Arr::get($this->attributes, $key);
        }

        return parent::getAttributeFromArray($key);
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute($key, $value)
    {
        // Convert _id to ObjectID.
        if (Str::contains($key, ".")) {
            if (in_array($key, $this->getDates()) && $value) {
                $value = $this->fromDateTime($value);
            }

            Arr::set($this->attributes, $key, $value);

            return $this;
        }

        return parent::setAttribute($key, $value);
    }
}
