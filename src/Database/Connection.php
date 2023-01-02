<?php

namespace UseTheFork\LaravelElasticsearchModel\Database;

use Closure;
use Elasticsearch\Client;
use Exception;
use Generator;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Grammar as BaseGrammar;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;
use UseTheFork\LaravelElasticsearchModel\Database\Exceptions\BulkInsertQueryException;
use UseTheFork\LaravelElasticsearchModel\Database\Exceptions\QueryException;
use UseTheFork\LaravelElasticsearchModel\Database\Query\Builder as QueryBuilder;
use UseTheFork\LaravelElasticsearchModel\Database\Query\Grammars\Grammar as QueryGrammar;
use UseTheFork\LaravelElasticsearchModel\Database\Query\Processors\Processor as QueryProcessor;
use UseTheFork\LaravelElasticsearchModel\Database\Schema\Builder as ElasticsearchBuilder;
use UseTheFork\LaravelElasticsearchModel\Database\Schema\Grammars\Grammar as ElasticsearchGrammar;

class Connection extends BaseConnection
{
    /**
     * Map configuration array keys with ES ClientBuilder setters
     *
     * @var array
     */
    protected $configMappings = [
        'sslVerification' => 'setSSLVerification',
        'sniffOnStart' => 'setSniffOnStart',
        'retries' => 'setRetries',
        'httpHandler' => 'setHandler',
        'connectionPool' => 'setConnectionPool',
        'connectionSelector' => 'setSelector',
        'serializer' => 'setSerializer',
        'connectionFactory' => 'setConnectionFactory',
        'endpoint' => 'setEndpoint',
        'namespaces' => 'registerNamespace',
    ];

    /**
     * The Elasticsearch client.
     *
     * @var Client
     */
    protected $connection;

    protected $hosts;

    protected $indexSuffix = '';

    protected $options;

    protected $requestTimeout;

    /**
     * Create a new Elasticsearch connection instance.
     *
     * @param  array  $config
     */
    public function __construct(
    $pdo = null,
    $database = '',
    $tablePrefix = '',
    array $config = []
  ) {
        //$credentials = json_decode(env($pdo['varible']), true);
        //$elastic = (new Document($credentials['host'], $credentials['api_key']));
        $this->config = $config;

        // You can pass options directly to the client
        $this->options = Arr::get($pdo, 'options', []);

        // Create the connection
        $this->connection = $this->createConnection($this->config, $this->options);

        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();
    }

    /**
     * Create a new Elasticsearch connection.
     *
     * @param  array  $hosts
     * @param  array  $config
     * @return Client
     */
    protected function createConnection(array $config, array $options = [])
    {
        return new Document($config['host'], $config['api_key']);
    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (! isset($this->connection) || empty($this->connection)) {
            $this->connection = $this->createConnection(
                $this->config,
                $this->options
            );
        }

        return call_user_func_array([$this->connection, $method], $parameters);
    }

    /**
     * @param  string  $index
     * @param  string  $name
     */
    public function createAlias(string $index, string $name): void
    {
        $this->indices()->putAlias(compact('index', 'name'));
    }

    /**
     * @param  string  $index
     * @param  array  $body
     */
    public function createIndex(string $index, array $body): void
    {
        $this->indices()->create(compact('index', 'body'));
    }

    /**
     * Run a select statement against the database and return a generator.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return Generator
     */
    public function cursor($query, $bindings = [], $useReadPdo = false)
    {
        dd($query, __FUNCTION__);
        $scrollTimeout = '30s';
        $limit = $query['size'] ?? 0;

        $scrollParams = [
            'scroll' => $scrollTimeout,
            'size' => 100,
            // Number of results per shard
            'index' => $query['index'],
            'body' => $query['body'],
        ];

        $results = $this->select($scrollParams);

        $scrollId = $results['_scroll_id'];

        $numResults = count($results['hits']['hits']);

        foreach ($results['hits']['hits'] as $result) {
            yield $result;
        }

        if (! $limit || $limit > $numResults) {
            $limit = $limit ? $limit - $numResults : $limit;

            foreach ($this->scroll($scrollId, $scrollTimeout, $limit) as $result) {
                yield $result;
            }
        }
    }

    /**
     * Run a select statement against the database.
     *
     * @param  array  $params
     * @param  array  $bindings
     * @return array
     */
    public function select($params, $bindings = [], $useReadPdo = true)
    {
        return $this->run($this->addClientParams($params), $bindings, function (
            $params,
            $bindings
        ) {
            if ($this->pretending()) {
                return [];
            }

            //TODO: This is likley becuase of upgrade may need to remove
            //$results = json_decode(gzdecode($this->connection->search($params)->asString()), TRUE);
            //dd($this->connection->search($params)->asArray());

            return $this->connection->search($params);
        });
    }

    /**
     * Run a select statement against the database.
     *
     * @param  array  $params
     * @param  array  $bindings
     * @return array
     */
    public function get($params, $bindings = [], $useReadPdo = true)
    {
        return $this->run($this->addClientParams($params), $bindings, function (
            $params,
            $bindings
        ) {
            if ($this->pretending()) {
                return [];
            }

            //TODO: This is likley becuase of upgrade may need to remove
            //$results = json_decode(gzdecode($this->connection->search($params)->asString()), TRUE);
            //dd($this->connection->search($params)->asArray());

            return $this->connection->get($params);
        });
    }

    /**
     * Add client-specific parameters to the request params
     *
     * @param  array  $params
     * @return array
     */
    protected function addClientParams(array $params): array
    {
        if ($this->requestTimeout) {
            $params['client']['timeout'] = $this->requestTimeout;
        }

        return $params;
    }

    /**
     * Run a select statement against the database using an Elasticsearch scroll cursor.
     *
     * @param  string  $scrollId
     * @param  string  $scrollTimeout
     * @param  int  $limit
     * @return Generator
     */
    public function scroll(
    string $scrollId,
    string $scrollTimeout = '30s',
    int $limit = 0
  ) {
        dd(__FUNCTION__);
        $numResults = 0;

        // Loop until the scroll 'cursors' are exhausted or we have enough results
        while (! $limit || $numResults < $limit) {
            // Execute a Scroll request
            $results = $this->createConnection(
                $this->hosts,
                $this->config,
                $this->options
            )->scroll([
                'scroll_id' => $scrollId,
                'scroll' => $scrollTimeout,
            ]);

            // Get new scroll ID in case it's changed
            $scrollId = $results['_scroll_id'];

            // Break if no results
            if (empty($results['hits']['hits'])) {
                break;
            }

            foreach ($results['hits']['hits'] as $result) {
                $numResults++;

                if ($limit && $numResults > $limit) {
                    break;
                }

                yield $result;
            }
        }
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return array
     */
    public function delete($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            $index = $query['index'];
            $body = $query['body'];

            $result = $this->connection->delete_by_query($index, $body);

            Log::info("deleted >> {$result['deleted']}\n");
            if (App::environment('local') && App::runningInConsole()) {
                echo "deleted >> {$result['deleted']}\n";
            }

            return $result;
        });
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return array
     */
    public function deleteDocument($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            $result = $this->connection->delete($query)->asArray();

            Log::info(
                "deleted >> {$result['_index']}/_doc/{$result['_id']} >> {$result['result']}\n"
            );
            if (App::environment('local') && App::runningInConsole()) {
                echo "deleted >> {$result['_index']}/_doc/{$result['_id']} >> {$result['result']}\n";
            }

            return $result;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect()
    {
        unset($this->connection);
    }

    /**
     * @param  string  $index
     */
    public function dropIndex(string $index): void
    {
        $this->indices()->delete(compact('index'));
    }

    /**
     * @param  string  $index
     */
    public function exists(string $index)
    {
        return $this->indices()->exists(['index' => $index]);
    }

    /**
     * return ElasticSearch Connection object.
     *
     * @return Connection
     */
    public function getClient()
    {
        return $this->connection;
    }

    /**
     * @return ElasticsearchGrammar|\Illuminate\Database\Schema\Grammars\Grammar
     */
    public function getDefaultSchemaGrammar()
    {
        return new ElasticsearchGrammar();
    }

    /**
     * {@inheritdoc}
     */
    public function getDriverName()
    {
        return 'elasticsearch';
    }

    /**
     * Get the timeout for the entire Elasticsearch request
     *
     * @return float
     */
    public function getRequestTimeout(): float
    {
        return $this->requestTimeout;
    }

    /**
     * @return ElasticsearchBuilder|\Illuminate\Database\Schema\Builder
     */
    public function getSchemaBuilder()
    {
        return new ElasticsearchBuilder($this);
    }

    public function getSchemaGrammar()
    {
        return new ElasticsearchGrammar();
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  array  $params
     * @param  array  $bindings
     * @return bool
     *
     * @throws BulkInsertQueryException
     */
    public function insert($params, $bindings = [])
    {
        $params['body'] = Arr::undot($params['body']);

        return $this->run($this->addClientParams($params), $bindings, function (
            $params,
            $bindings
        ) {
            if ($this->pretending()) {
                return [];
            }

            //Check if this is a single insert of bulk insert we know this based on the counting of the keys
            //Single insert documents are always composed of 3 attributes
            if (
                isset($params['index']) &&
                isset($params['id']) &&
                isset($params['body'])
            ) {
                $result = $this->connection->index($params);
            } else {
                $result = $this->connection->bulk($params);
            }

            if (! empty($result['errors'])) {
                throw new BulkInsertQueryException($result);
            }

            Log::info(
                "insert >> {$result['_index']}/_doc/{$result['_id']} >> {$result['result']}\n"
            );
            if (App::environment('local') && App::runningInConsole()) {
                echo "insert >> {$result['_index']}/_doc/{$result['_id']} >> {$result['result']}\n";
            }

            return true;
        });
    }

    /**
     * Log a query in the connection's query log.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  float|null  $time
     * @return void
     */
    public function logQuery($query, $bindings, $time = null)
    {
        $this->event(
            new QueryExecuted(json_encode($query), $bindings, $time, $this)
        );

        if ($this->loggingQueries) {
            $this->queryLog[] = compact('query', 'bindings', 'time');
        }
    }

    /**
     * Get a new query builder instance.
     *
     * @return
     */
    public function query()
    {
        return new QueryBuilder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }

    /**
     * Set the table prefix in use by the connection.
     *
     * @param  string  $prefix
     * @return void
     */
    public function setIndexSuffix($suffix)
    {
        $this->indexSuffix = $suffix;
        $this->getQueryGrammar()->setIndexSuffix($suffix);
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function statement($query, $bindings = [], $type = null)
    {
        dd($query, __FUNCTION__);

        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $this->recordsHaveBeenModified();

            return $statement->execute();
        });
    }

    /**
     * Prepare the query bindings for execution.
     *
     * @param  array  $bindings
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        return $bindings;
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param  string  $table
     * @return Builder
     */
    public function table($table, $as = null)
    {
    //
    }

    /**
     * Execute a Closure within a transaction.
     *
     * @param  Closure  $callback
     * @param  int  $attempts
     * @return mixed
     *
     * @throws Throwable
     */
    public function transaction(Closure $callback, $attempts = 1)
    {
    //
    }

    /**
     * Get the number of active transactions.
     *
     * @return int
     */
    public function transactionLevel()
    {
    //
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param  string  $query
     * @return bool
     */
    public function unprepared($query)
    {
    //
    }

    /**
     * Run an update statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return array
     */
    public function update($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            $result = $this->connection->update($query);

            Log::info(
                "update >> {$result['_index']}/_doc/{$result['_id']} >> {$result['result']}\n"
            );
            if (App::environment('local') && App::runningInConsole()) {
                echo "update >> {$result['_index']}/_doc/{$result['_id']} >> {$result['result']}\n";
            }

            return $result;
        });
    }

    /**
     * @param  string  $index
     * @param  array  $body
     */
    public function updateIndexupdateIndex(string $index, array $body): void
    {
        $this->indices()->putMapping(compact('index', 'body'));
    }

    /**
     * Get the default post processor instance.
     *
     * @return QueryProcessor
     */
    protected function getDefaultPostProcessor()
    {
        return new QueryProcessor();
    }

    /**
     * Get the default query grammar instance.
     *
     * @return Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withIndexSuffix(new QueryGrammar());
    }

    /**
     * Set the table prefix and return the grammar.
     *
     * @param  BaseGrammar  $grammar
     * @return BaseGrammar
     */
    public function withIndexSuffix(BaseGrammar $grammar)
    {
        $grammar->setIndexSuffix($this->indexSuffix);

        return $grammar;
    }

    /**
     * Reconnect to the database if a PDO connection is missing.
     *
     * @return void
     */
    protected function reconnectIfMissingConnection()
    {
        if (@is_null($this->connection)) {
            $this->reconnect();
        }
    }

    /**
     * Reconnect to the database.
     *
     * @return void
     *
     * @throws LogicException
     */
    public function reconnect()
    {
        $this->doctrineConnection = null;

        if (@empty($this->config)) {
            $this->config = json_decode(env('ELASTICSEARCH_PURGATORY'), true);
        }

        $this->connection = $this->createConnection($this->config);
    }

    /**
     * Run a search query.
     *
     * @param  array  $query
     * @param  array  $bindings
     * @param  Closure  $callback
     * @return mixed
     *
     * @throws \DesignMyNight\Elasticsearch\QueryException
     */
    protected function runQueryCallback($query, $bindings, Closure $callback)
    {
        try {
            $result = $callback($query, $bindings);
        } catch (Exception $e) {
            throw new QueryException($query, $bindings, $e);
        }

        return $result;
    }
}
