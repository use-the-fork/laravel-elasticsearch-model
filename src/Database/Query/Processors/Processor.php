<?php

namespace UseTheFork\LaravelElasticsearchModel\Database\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;

class Processor extends BaseProcessor
{
    protected $rawResponse;

    protected $aggregations;

    /**
     * Process the results of a "select" query.
     *
     * @param  Builder  $query
     * @param  array  $results
     * @return array
     */
    public function processSelect(Builder $query, $results)
    {
        $this->rawResponse = $results;

        $this->aggregations = $results['aggregations'] ?? [];

        $this->query = $query;

        $documents = [];

        //If this is mulitple documents then make it a document collection
        if (isset($results['hits']['hits'])) {
            foreach ($results['hits']['hits'] as $result) {
                $documents[] = $this->documentFromResult($query, $result);
            }
        }

        //if this is a single document then look at the source
        if (isset($results['found']) && $results['found'] == true) {
            $documents[] = $this->documentFromResult($query, $results);
        }

        return $documents;
    }

    /**
     * Create a document from the given result
     *
     * @param  Builder  $query
     * @param  array  $result
     * @return array
     */
    public function documentFromResult(Builder $query, array $result): array
    {
        if (! $result) {
            return [];
        }

        $document = $result['_source'];
        $document['id'] = $result['_id'];

        if ($query->includeInnerHits && isset($result['inner_hits'])) {
            $document = $this->addInnerHitsToDocument(
                $document,
                $result['inner_hits']
            );
        }

        return $document;
    }

    /**
     * Add inner hits to a document
     *
     * @param  array  $document
     * @param  array  $innerHits
     * @return array
     */
    protected function addInnerHitsToDocument($document, $innerHits): array
    {
        foreach ($innerHits as $documentType => $hitResults) {
            foreach ($hitResults['hits']['hits'] as $result) {
                $document['inner_hits'][$documentType][] = array_merge(
                    ['_id' => $result['_id']],
                    $result['_source']
                );
            }
        }

        return $document;
    }

    /**
     * Get the raw Elasticsearch response
     *
     * @param array
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    /**
     * Get the raw aggregation results
     *
     * @param array
     */
    public function getAggregationResults(): array
    {
        return $this->aggregations;
    }

    public function processInsertGetId(
        Builder $query,
        $sql,
        $values,
        $sequence = null
    ) {
        $result = $query->getConnection()->insert($sql, $values);
        $last = collect($result['items'])->last();

        return $last['index']['_id'] ?? null;
    }
}
