<?php

namespace UseTheFork\LaravelElasticsearchModel\Database\Schema;

use Closure;
use Illuminate\Database\Schema\Builder as BaseBuilder;

/**
 * Class Builder
 */
class Builder extends BaseBuilder
{
    /**
     * @param    $table
     * @param  Closure  $callback
     */
    public function index($table, Closure $callback)
    {
        $this->table($table, $callback);
    }

    /**
     * @param  string  $table
     * @param  Closure  $callback
     */
    public function table($table, Closure $callback)
    {
        $this->build(
            tap($this->createBlueprint($table), function (
                Blueprint $blueprint
            ) use ($callback) {
                $blueprint->update();

                $callback($blueprint);
            })
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function createBlueprint($table, Closure $callback = null)
    {
        return new Blueprint($table, $callback);
    }
}
