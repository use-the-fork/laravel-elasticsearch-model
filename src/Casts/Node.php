<?php

namespace UseTheFork\LaravelElasticsearchModel\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Node implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function get($model, $key, $value, $attributes)
    {
        return collect($value);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function set($model, $key, $value, $attributes)
    {
        if (! $value instanceof \Illuminate\Support\Collection) {
            throw new \InvalidArgumentException(
                sprintf(
                    'value must be of type %s',
                    \Illuminate\Support\Collection::class
                )
            );
        }

        return [$key => $value->toArray()];
    }
}
