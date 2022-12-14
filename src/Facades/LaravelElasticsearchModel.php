<?php

namespace UseTheFork\LaravelElasticsearchModel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \UseTheFork\LaravelElasticsearchModel\LaravelElasticsearchModel
 */
class LaravelElasticsearchModel extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \UseTheFork\LaravelElasticsearchModel\LaravelElasticsearchModel::class;
    }
}
