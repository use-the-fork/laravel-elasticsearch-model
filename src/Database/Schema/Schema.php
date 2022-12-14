<?php

namespace UseTheFork\LaravelElasticsearchModel\Database\Schema;

use Illuminate\Support\Facades\Schema as BaseSchema;

class Schema extends BaseSchema
{
    public static function connection($name)
    {
        return static::$app['db']
            ->connection('elasticsearch')
            ->getSchemaBuilder();
    }

    /**
     * Get a schema builder instance for a connection.
     *
     * @param  string|null  $name
     * @return Builder
     */
    /*
        public static function connection($name): Builder
        {

            $builder = static::$app['db']->connection($name)->getSchemaBuilder();
            $builder->blueprintResolver(static function ($table, $callback) {
                return new Blueprint($table, $callback);
            });

            return $builder;
        }
*/
    /**
     * Get a schema builder instance for the default connection.
     *
     * @return Builder
     */
    protected static function getFacadeAccessor(): Builder
    {
        /** @var \Illuminate\Database\Schema\Builder $builder */
        $builder = static::$app['db']
            ->connection('elasticsearch')
            ->getSchemaBuilder();
        $builder->blueprintResolver(static function ($table, $callback) {
            return new Blueprint($table, $callback);
        });

        return $builder;
    }
}
