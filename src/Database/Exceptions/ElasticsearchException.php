<?php

namespace UseTheFork\LaravelElasticsearchModel\Database\Exceptions;

use Exception;

class ElasticsearchException extends Exception
{
    /** @var array */
    private $raw = [];

    /**
     * ElasticsearchException constructor.
     *
     * @param  Exception  $exception
     */
    public function __construct(Exception $exception)
    {
        $this->parseException($exception);
    }

    /**
     * @param  Exception  $exception
     */
    private function parseException(Exception $exception): void
    {
        $body = json_decode($exception->getMessage(), true);

        $this->message = $body['error']['reason'];
        $this->code = $body['error']['type'];

        $this->raw = $body;
    }

    /**
     * @return array
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "{$this->getCode()}: {$this->getMessage()}";
    }
}
