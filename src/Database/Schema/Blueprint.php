<?php

namespace UseTheFork\LaravelElasticsearchModel\Database\Schema;

use Carbon\Carbon;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

/**
 * Class Blueprint
 */
class Blueprint extends \Illuminate\Database\Schema\Blueprint
{
    /** @var string */
    protected string $alias;

    /** @var string */
    protected string $document;

    /** @var array */
    protected array $indexSettings = [];

    /** @var array */
    protected array $meta = [];

    /**
     * @param  string  $key
     * @param  array  $value
     */
    public function addIndexSettings(string $key, array $value): void
    {
        $this->indexSettings[$key] = $value;
    }

    /**
     * @param  string  $alias
     */
    public function alias(string $alias): void
    {
        $this->alias = $alias;
    }

    /**
     * {@inheritDoc}
     */
    public function bigInteger(
        $column,
        $autoIncrement = false,
        $unsigned = false
    ): PropertyDefinition {
        return $this->addColumn('long', $column);
    }

    /**
     * {@inheritDoc}
     */
    public function addColumn($type, $name, array $parameters = [])
    {
        $attributes = ['name'];

        if (isset($type)) {
            $attributes[] = 'type';
        }

        $this->columns[] = $column = new PropertyDefinition(
            array_merge(compact(...$attributes), $parameters)
        );

        return $column;
    }

    /**
     * Create a new binary column on the table.
     *
     * @param  string  $column
     * @return PropertyDefinition
     */
    public function binary($column): PropertyDefinition
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Create a new boolean column on the table.
     *
     * @param  string  $column
     * @return PropertyDefinition
     */
    public function boolean($column): PropertyDefinition
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * Execute the blueprint against the database.
     *
     * @param  Connection  $connection
     * @param  Grammar  $grammar
     * @return void
     */
    public function build(Connection $connection, Grammar $grammar)
    {
        foreach ($this->toSql($connection, $grammar) as $statement) {
            if ($connection->pretending()) {
                return;
            }

            $statement($this, $connection);
        }
    }

    /**
     * @param  Connection  $connection
     * @param  Grammar  $grammar
     * @return Closure[]
     */
    public function toSql(Connection $connection, Grammar $grammar)
    {
        $this->addImpliedCommands($grammar);

        $statements = [];

        // Each type of command has a corresponding compiler function on the schema
        // grammar which is used to build the necessary SQL statements to build
        // the blueprint element, so we'll just call that compilers function.
        $this->ensureCommandsAreValid($connection);

        foreach ($this->commands as $command) {
            $method = 'compile'.ucfirst($command->name);

            if (method_exists($grammar, $method)) {
                if (
                    ! is_null(
                        $statement = $grammar->$method(
                            $this,
                            $command,
                            $connection
                        )
                    )
                ) {
                    $statements[] = $statement;
                }
            }
        }

        return $statements;
    }

    /**
     * {@inheritDoc}
     */
    public function char(
        $column,
        $length = null,
        array $parameters = []
    ): PropertyDefinition {
        return $this->string($column, null, $parameters);
    }

    /**
     * @param  string  $column
     * @param  array  $length
     * @return PropertyDefinition
     */
    public function string(
        $column,
        $length = null,
        array $parameters = []
    ): PropertyDefinition {
        return $this->addColumn('keyword', $column, $parameters);
    }

    /**
     * @param  string  $column
     * @return PropertyDefinition
     */
    public function date($column): PropertyDefinition
    {
        $parameters = [
            'format' => 'yyyy-MM-dd||strict_date||basic_date||epoch_millis',
        ];

        return $this->dateTime('date', $column, $parameters);
    }

    /**
     * @param string column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return PropertyDefinition
     */
    public function dateTime(
        $column,
        $precision = 0,
        array $parameters = []
    ): PropertyDefinition {
        return $this->addColumn('date', $column, $parameters);
    }

    /**
     * @param  string  $column
     * @param  array  $parameters
     * @return PropertyDefinition
     */
    public function dateRange(
        string $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->range('date_range', $column, $parameters);
    }

    /**
     * @param  string  $type
     * @param  string  $column
     * @param  array  $parameters
     * @return PropertyDefinition
     */
    public function range(
        string $type,
        string $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->addColumn($type, $column, $parameters);
    }

    /**
     * @param string column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return PropertyDefinition
     */
    public function dateTimeTz(
        $column,
        $precision = 0,
        array $parameters = []
    ): PropertyDefinition {
        return $this->dateTime($column, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function decimal(
        $column,
        $total = 8,
        $places = 2,
        $unsigned = false
    ): PropertyDefinition {
        return $this->addColumn('float', $column);
    }

    /**
     * @param  string  $name
     */
    public function document(string $name): void
    {
        $this->document = $name;
    }

    /**
     * {@inheritDoc}
     */
    public function double(
        $column,
        $total = 8,
        $places = 2,
        $unsigned = false
    ): PropertyDefinition {
        return $this->addColumn('double', $column);
    }

    /**
     * @param  string  $column
     * @param  array  $parameters
     * @return PropertyDefinition
     */
    public function doubleRange(
        string $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->range('double_range', $column, $parameters);
    }

    /**
     * @param  bool|string  $value
     */
    public function dynamic(bool|string $value): void
    {
        $this->addMetaField('dynamic', $value);
    }

    /**
     * @param  string  $key
     * @param    $value
     */
    public function addMetaField(string $key, $value): void
    {
        $this->meta[$key] = $value;
    }

    /**
     * @return void
     */
    public function enableAll(): void
    {
        $this->addMetaField('_all', ['enabled' => true]);
    }

    /**
     * @return void
     */
    public function enableFieldNames(): void
    {
        $this->addMetaField('_field_names', ['enabled' => true]);
    }

    /**
     * {@inheritDoc}
     */
    public function enum(
        $column,
        array $allowed = [],
        array $parameters = []
    ): PropertyDefinition {
        return $this->keyword($column, $parameters);
    }

    /**
     * @param  string  $column
     * @param  array  $parameters
     * @return PropertyDefinition
     */
    public function keyword(
        string $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->addColumn('keyword', $column, $parameters);
    }

    /**
     * @param  string  $column
     * @param  int  $total
     * @param  int  $places
     * @param  bool  $unsigned
     * @return PropertyDefinition
     */
    public function float(
        $column,
        $total = 8,
        $places = 2,
        $unsigned = false
    ): PropertyDefinition {
        return $this->addColumn('float', $column);
    }

    /**
     * @param  string  $column
     * @param  array  $parameters
     * @return PropertyDefinition
     */
    public function floatRange(
        $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->range('float_range', $column, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function foreignId(
        $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->addColumn('unsigned_long', $column, $parameters);
    }

    /**
     * @param  string  $column
     * @param  array  $parameters
     * @return PropertyDefinition
     */
    public function geoPoint(
        string $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->addColumn('geo_point', $column, $parameters);
    }

    /**
     * @param  string  $column
     * @param  array  $parameters
     * @return PropertyDefinition
     */
    public function geoShape(
        string $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->addColumn('geo_shape', $column, $parameters);
    }

    /**
     * @return string
     */
    public function getAlias(): string
    {
        return ($this->alias ?? $this->getTable()).
            Config::get('database.connections.elasticsearch.suffix');
    }

    /**
     * @return string
     */
    public function getDocumentType(): string
    {
        return $this->document ?? Str::singular($this->getTable());
    }

    /**
     * @return string
     */
    public function getIndex(): string
    {
        $suffix = Config::get('database.connections.elasticsearch.suffix');
        $timestamp = Carbon::now()->format('Y_m_d_His');

        return "{$timestamp}_{$this->getTable()}".$suffix;
    }

    /**
     * @return array
     */
    public function getIndexSettings(): array
    {
        return $this->indexSettings;
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @param  string  $column
     * @param  array  $parameters
     * @return PropertyDefinition
     */
    public function integerRange(
        string $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->range('integer_range', $column, $parameters);
    }

    /**
     * @param  string  $column
     * @return PropertyDefinition
     */
    public function ip(string $column): PropertyDefinition
    {
        return $this->ipAddress($column);
    }

    /**
     * @param  string  $column
     * @return PropertyDefinition
     */
    public function ipAddress($column = 'ip_address'): PropertyDefinition
    {
        return $this->addColumn('ipAddress', $column);
    }

    /**
     * @param  string  $column
     * @param  array  $parameters
     * @return PropertyDefinition
     */
    public function ipRange(
        string $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->range('ip_range', $column, $parameters);
    }

    /**
     * @param  string  $column
     * @param  array  $relations
     * @return PropertyDefinition
     */
    public function join(string $column, array $relations): PropertyDefinition
    {
        return $this->addColumn('join', $column, compact('relations'));
    }

    /**
     * {@inheritDoc}
     */
    public function json($column): PropertyDefinition
    {
        return $this->addColumn('text', $column);
    }

    /**
     * {@inheritDoc}
     */
    public function jsonb($column): PropertyDefinition
    {
        return $this->addColumn('text', $column);
    }

    /**
     * @param  string  $column
     * @return PropertyDefinition
     */
    public function long(string $column): PropertyDefinition
    {
        return $this->addColumn('long', $column);
    }

    /**
     * @param  string  $column
     * @param  array  $parameters
     * @return PropertyDefinition
     */
    public function longRange(
        string $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->range('long_range', $column, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function longText(
        $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->text($column, $parameters);
    }

    /**
     * @param  string  $column
     * @return PropertyDefinition
     */
    public function text($column, array $parameters = []): PropertyDefinition
    {
        //Add multifeild mapping
        $parameters = array_merge($parameters, [
            'fields' => [
                'keyword' => [
                    'ignore_above' => 256,
                    'type' => 'keyword',
                ],
            ],
        ]);

        return $this->addColumn('text', $column, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function macAddress(
        $column,
        $length = null,
        array $parameters = []
    ): PropertyDefinition {
        return $this->string($column, null, $parameters);
    }

    public function mediumInteger(
        $column,
        $autoIncrement = false,
        $unsigned = false,
        array $parameters = []
    ): PropertyDefinition {
        return $this->integer($column, false, false, $parameters);
    }

    /**
     * @param string column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return PropertyDefinition
     */
    public function integer(
        $column,
        $autoIncrement = false,
        $unsigned = false,
        array $parameters = []
    ): PropertyDefinition {
        return $this->addColumn('integer', $column, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function mediumText(
        $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->text($column, $parameters);
    }

    /**
     * @param  array  $meta
     */
    public function meta(array $meta): void
    {
        $this->addMetaField('_meta', $meta);
    }

    /**
     * {@inheritDoc}
     */
    public function multiPoint($column)
    {
        return $this->addColumn('multipoint', $column);
    }

    /**
     * {@inheritDoc}
     */
    public function multiPolygon($column)
    {
        return $this->addColumn('multipolygon', $column);
    }

    /**
     * @param  string  $column
     * @param  Closure  $parameters
     * @return PropertyDefinition
     */
    public function nested(string $column): PropertyDefinition
    {
        return $this->addColumn('nested', $column);
    }

    /**
     * {@inheritDoc}
     */
    public function nullableMorphs($name, $indexName = null)
    {
        $this->morphs($name, $indexName);
    }

    /**
     * {@inheritDoc}
     */
    public function morphs($name, $indexName = null)
    {
        $this->uuidMorphs($name, $indexName);
    }

    /**
     * {@inheritDoc}
     */
    public function uuidMorphs($name, $indexName = null)
    {
        $this->string("{$name}_type");
        $this->uuid("{$name}_id");
    }

    /**
     * {@inheritDoc}
     */
    public function uuid($column)
    {
        return $this->keyword('uuid', $column);
    }

    /**
     * {@inheritDoc}
     */
    public function nullableUuidMorphs($name, $indexName = null)
    {
        $this->uuidMorphs("{$name}_type");
    }

    /**
     * @param  string  $column
     * @return PropertyDefinition
     */
    public function object(string $column)
    {
        return $this->addColumn('object', $column);
    }

    /**
     * @param  string  $column
     * @param  array  $parameters
     * @return PropertyDefinition
     */
    public function percolator(
        string $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->addColumn('percolator', $column, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function point(
        $column,
        $srid = null,
        array $parameters = []
    ): PropertyDefinition {
        return $this->addColumn('point', $column, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function polygon($column)
    {
        return $this->addColumn('polygon', $column);
    }

    /**
     * @return void
     */
    public function routingRequired(): void
    {
        $this->addMetaField('_routing', ['required' => true]);
    }

    public function smallInteger(
        $column,
        $autoIncrement = false,
        $unsigned = false,
        array $parameters = []
    ): PropertyDefinition {
        return $this->short($column, false, false, $parameters);
    }

    /**
     * @param string column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return PropertyDefinition
     */
    public function short(
        $column,
        $autoIncrement = false,
        $unsigned = false,
        array $parameters = []
    ): PropertyDefinition {
        return $this->addColumn('short', $column, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function softDeletes($column = 'deleted_at', $precision = 0)
    {
        $this->timestamp($column, $precision);
    }

    /**
     * @param string column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return PropertyDefinition
     */
    public function timestamp(
        $column,
        $precision = 0,
        array $parameters = []
    ): PropertyDefinition {
        return $this->addColumn('date', $column, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function softDeletesTz(
        $column = 'deleted_at',
        $precision = 0
    ): PropertyDefinition {
        return $this->timestamp($column, $precision);
    }

    /**
     * {@inheritDoc}
     */
    public function time($column, $precision = 0): PropertyDefinition
    {
        $parameters = [
            'format' => 'hour_minute_second||basic_time||basic_time_no_millis||epoch_millis',
        ];

        return $this->timestamp($column, $precision, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function timestampTz(
        $column,
        $precision = 0,
        array $parameters = []
    ): PropertyDefinition {
        return $this->timestamp($column, 0, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function timestamps($precision = 0)
    {
        $this->timestamp('created_at', $precision);
        $this->timestamp('updated_at', $precision);
    }

    public function tinyInteger(
        $column,
        $autoIncrement = false,
        $unsigned = false,
        array $parameters = []
    ): PropertyDefinition {
        return $this->byte($column, $parameters);
    }

    public function byte($column, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('byte', $column, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function tinyText(
        $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->text($column, $parameters);
    }

    /**
     * @param  string  $column
     * @param  array  $parameters
     * @return PropertyDefinition
     */
    public function tokenCount(
        $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->addColumn('token_count', $column, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function unsignedBigInteger(
        $column,
        $autoIncrement = false,
        array $parameters = []
    ): PropertyDefinition {
        return $this->unsignedLong($column, $parameters);
    }

    /**
     * @param  string  $column
     * @param  array  $parameters
     * @return PropertyDefinition
     */
    public function unsignedLong(
        $column,
        array $parameters = []
    ): PropertyDefinition {
        return $this->addColumn('unsigned_long', $column, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function unsignedInteger(
        $column,
        $autoIncrement = false,
        array $parameters = []
    ): PropertyDefinition {
        return $this->unsignedLong($column, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function unsignedMediumInteger(
        $column,
        $autoIncrement = false,
        array $parameters = []
    ): PropertyDefinition {
        return $this->unsignedLong($column, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function unsignedSmallInteger(
        $column,
        $autoIncrement = false,
        array $parameters = []
    ): PropertyDefinition {
        return $this->unsignedLong($column, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function unsignedTinyInteger(
        $column,
        $autoIncrement = false,
        array $parameters = []
    ): PropertyDefinition {
        return $this->unsignedLong($column, $parameters);
    }

    /**
     * @return Fluent
     */
    public function update()
    {
        return $this->addCommand('update');
    }

    /**
     * {@inheritDoc}
     */
    public function year($column)
    {
        $parameters = [
            'format' => 'year||epoch_millis',
        ];

        return $this->timestamp($column, $parameters);
    }

    /**
     * Add a new column definition to the blueprint.
     *
     * @param  PropertyDefinition  $definition
     * @return PropertyDefinition
     */
    protected function addColumnDefinition($definition): PropertyDefinition
    {
        $this->columns[] = $definition;

        if ($this->after) {
            $definition->after($this->after);

            $this->after = $definition->name;
        }

        return $definition;
    }
}
