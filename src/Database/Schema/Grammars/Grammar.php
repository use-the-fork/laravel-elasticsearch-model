<?php

namespace UseTheFork\LaravelElasticsearchModel\Database\Schema\Grammars;

use Closure;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Database\Schema\Grammars\Grammar as BaseGrammar;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use InvalidArgumentException;
use UseTheFork\LaravelElasticsearchModel\Database\Connection;
use UseTheFork\LaravelElasticsearchModel\Database\Schema\Blueprint;

/**
 * Class Grammar
 */
class Grammar extends BaseGrammar
{
    /** @var array */
    protected $modifiers = [
        'Boost',
        'Dynamic',
        'Fields',
        'Format',
        'Index',
        'Properties',
        //'Default',
    ];

    /**
     * @param  Blueprint  $blueprint
     * @param  Fluent  $command
     * @param  Connection  $connection
     * @return Closure
     */
    public function compileCreate(
        Blueprint $blueprint,
        Fluent $command,
        Connection $connection
    ): Closure {
        return function (Blueprint $blueprint, Connection $connection): void {
            $body = [
                'mappings' => array_merge(
                    ['properties' => $this->getColumns($blueprint)],
                    $blueprint->getMeta()
                ),
            ];

            if ($settings = $blueprint->getIndexSettings()) {
                $body['settings'] = $settings;
            }
            //dd($body);

            $connection->createIndex($index = $blueprint->getIndex(), $body);

            $alias = $blueprint->getAlias();

            if (! $connection->indices()->existsAlias(['name' => $alias])) {
                $connection->createAlias($index, $alias);
            }
        };
    }

    /**
     * @param  BaseBlueprint  $blueprint
     * @return array
     */
    protected function getColumns(BaseBlueprint $blueprint)
    {
        $columns = [];

        foreach ($blueprint->getAddedColumns() as $property) {
            // Pass empty string as we only need to modify the property and return it.
            $column = $this->addModifiers('', $blueprint, $property);
            $key = Str::snake($column->name);
            unset($column->name);

            $columns[$key] = $column->toArray();
        }

        return $columns;
    }

    /**
     * {@inheritDoc}
     */
    protected function addModifiers(
        $sql,
        BaseBlueprint $blueprint,
        Fluent $property
    ) {
        foreach ($this->modifiers as $modifier) {
            if (method_exists($this, $method = "modify{$modifier}")) {
                $property = $this->{$method}($blueprint, $property);
            }
        }

        return $property;
    }

    /**
     * @param  Blueprint  $blueprint
     * @param  Fluent  $command
     * @param  Connection  $connection
     * @return Closure
     */
    public function compileDrop(
        Blueprint $blueprint,
        Fluent $command,
        Connection $connection
    ): Closure {
        return function (Blueprint $blueprint, Connection $connection): void {
            $connection->dropIndex(
                collect($connection->cat()->indices())
                    ->sort()
                    ->last()['index']
            );
        };
    }

    /**
     * @param  Blueprint  $blueprint
     * @param  Fluent  $command
     * @param  Connection  $connection
     * @return Closure
     */
    public function compileDropIfExists(
        Blueprint $blueprint,
        Fluent $command,
        Connection $connection
    ): Closure {
        return function (Blueprint $blueprint, Connection $connection): void {
            $index = collect($connection->cat()->indices())
                ->sort()
                ->last();

            if (
                $index &&
                Str::contains($index['index'], $blueprint->getTable())
            ) {
                $connection->dropIndex($index['index']);
            }
        };
    }

    /**
     * @param  Blueprint  $blueprint
     * @param  Fluent  $command
     * @param  Connection  $connection
     * @return Closure
     */
    public function compileUpdate(
        Blueprint $blueprint,
        Fluent $command,
        Connection $connection
    ): Closure {
        return function (Blueprint $blueprint, Connection $connection): void {
            $connection->updateIndex(
                $blueprint->getAlias(),
                array_merge(
                    ['properties' => $this->getColumns($blueprint)],
                    $blueprint->getMeta()
                )
            );
        };
    }

    /**
     * @param  Blueprint  $blueprint
     * @param  Fluent  $property
     * @return Fluent
     */
    protected function format(Blueprint $blueprint, Fluent $property)
    {
        if (! is_string($property->format)) {
            throw new InvalidArgumentException(
                'Format modifier must be a string',
                400
            );
        }

        return $property;
    }

    /**
     * @param  Blueprint  $blueprint
     * @param  Fluent  $property
     * @return Fluent
     */
    protected function modifyBoost(Blueprint $blueprint, Fluent $property)
    {
        if (! is_null($property->boost) && ! is_numeric($property->boost)) {
            throw new InvalidArgumentException(
                'Boost modifier must be numeric',
                400
            );
        }

        return $property;
    }

    /**
     * @param  Blueprint  $blueprint
     * @param  Fluent  $property
     * @return Fluent
     */
    protected function modifyDynamic(Blueprint $blueprint, Fluent $property)
    {
        if (! is_null($property->dynamic) && ! is_bool($property->dynamic)) {
            throw new InvalidArgumentException(
                'Dynamic modifier must be a boolean',
                400
            );
        }

        return $property;
    }

    /**
     * @param  Blueprint  $blueprint
     * @param  Fluent  $property
     * @return Fluent
     */
    protected function modifyFields(Blueprint $blueprint, Fluent $property)
    {
        if (! is_null($property->fields) && ! is_array($property->fields)) {
            $fields = $property->fields;
            $fields($blueprint = $this->createBlueprint());
            $property->fields = $this->getColumns($blueprint);
        }

        return $property;
    }

    /**
     * @return Blueprint
     */
    private function createBlueprint(): Blueprint
    {
        return new Blueprint('');
    }

    /**
     * @param  Blueprint  $blueprint
     * @param  Fluent  $property
     * @return Fluent
     */
    protected function modifyProperties(Blueprint $blueprint, Fluent $property)
    {
        if (! is_null($property->properties)) {
            $properties = $property->properties;
            $properties($blueprint = $this->createBlueprint());

            $property->properties = $this->getColumns($blueprint);
        }

        return $property;
    }
}
