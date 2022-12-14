<?php

namespace UseTheFork\LaravelElasticsearchModel\Database\Exceptions;

use Exception;

class BulkInsertQueryException extends Exception
{
    private $errorLimit = 10;

    /**
     * BulkInsertQueryException constructor.
     *
     * @param  array  $queryResult
     */
    public function __construct(array $queryResult)
    {
        parent::__construct($this->formatMessage($queryResult), 400);
    }

    /**
     * Format the error message.
     *
     * Takes the first {$this->errorLimit} bulk issues and concatenates them to a single string message
     *
     * @param  array  $result
     * @return string
     */
    private function formatMessage(array $result): string
    {
        $message = [];

        $items = array_filter($result['items'] ?? [], function (
            array $item
        ): bool {
            return $item['index'] && ! empty($item['index']['error']);
        });

        $items = array_values($items);

        $totalErrors = count($items);

        // reduce to max limit
        array_splice($items, $this->errorLimit);

        $message[] =
            'Bulk Insert Errors ('.
            'Showing '.
            count($items).
            ' of '.
            $totalErrors.
            '):';

        foreach ($items as $item) {
            $itemError = array_merge(
                [
                    '_id' => $item['index']['_id'],
                    'reason' => $item['index']['error']['reason'],
                ],
                [$item['index']['error']['caused_by']['reason']] ?? []
            );

            $message[] = implode(': ', $itemError);
        }

        return implode(PHP_EOL, $message);
    }
}
