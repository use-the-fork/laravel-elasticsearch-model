<?php

namespace UseTheFork\LaravelElasticsearchModel\Database;

use Exception;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use UseTheFork\LaravelCast\Cast;

class Document
{
    protected $token = '';

    protected $url = '';

    protected $retry = 5;

    protected $retrySleep = 100;

    public function __construct($url, $token)
    {
        $this->url = "https://{$url}";
        $this->token = "ApiKey {$token}";
    }

    public function delete($request)
    {
        $client = new Guzzle();

        $response = $client->request('DELETE', $this->url, [
            'headers' => [
                'Authorization' => $this->token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([$request]),
        ]);

        return json_decode($response->getBody()->getContents());
    }

    public function get($doc)
    {
        try {
            $response = Http::retry($this->retry, $this->retrySleep)
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ])
                ->get(
                    sprintf(
                        '%s/%s/_doc/%s',
                        $this->url,
                        $doc['index'],
                        $doc['id']
                    )
                );

            return $response->json();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function delete_by_query($index, $payload)
    {
        $response = Http::retry($this->retry, $this->retrySleep)
            ->timeout(30)
            ->withHeaders([
                'Authorization' => $this->token,
                'Content-Type' => 'application/json',
            ])
            ->post(
                sprintf('%s/%s/_delete_by_query', $this->url, $index),
                $payload
            );

        return $response->json();
    }

    public function search($payload, $withScroll = false)
    {
        $index = $payload['index'];

        try {
            $response = Http::retry($this->retry, $this->retrySleep)
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ])
                ->post(
                    sprintf('%s/%s/_search', $this->url, $index),
                    $payload['body']
                );

            return $response->json();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            var_dump($e->response->body());

            return [];
        }
    }

    public function scroll($index, $scrollId)
    {
        try {
            $response = Http::retry($this->retry, $this->retrySleep)
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ])
                ->post(sprintf('%s/_search/scroll', $this->url), [
                    'scroll' => '1m',
                    'scroll_id' => $scrollId,
                ]);

            return $response->json();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            var_dump($e->response->body());

            return [];
        }
    }

    public function update($data)
    {
        return $this->index($data);
    }

    public function index($data)
    {
        $data['body'] = $this->addTimestamps($data['body']);
        //$data['body'] = Arr::undot($data['body']);

        try {
            $response = Http::retry($this->retry, $this->retrySleep)
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ])
                ->put(
                    sprintf(
                        '%s/%s/_doc/%s',
                        $this->url,
                        $data['index'],
                        $data['id']
                    ),
                    $data['body']
                );

            return $response->json();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            var_dump($e->response->body());
            throw new Exception($e->response->body());
        }
    }

    public function list($page = 1)
    {
        $client = new Guzzle();
        try {
            $response = $client->request(
                'GET',
                $this->url."/list?page[current]={$page}",
                [
                    'headers' => [
                        'Authorization' => $this->token,
                        'Content-Type' => 'application/json',
                    ],
                ]
            );

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function bulk($data)
    {
        $data = trim(
            collect($data)
                ->map(function ($item) {
                    return json_encode($item);
                })
                ->implode("\r\n")."\r\n"
        );

        dd($data);
        try {
            $response = Http::retry($this->retry, $this->retrySleep)
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/x-ndjson',
                ])
                ->post(sprintf('%s/_bulk', $this->url), $data);

            return $response->json();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            var_dump($e->response->body());

            return [];
        }
    }

    private function addTimestamps($request)
    {
        if (! isset($request['created_at'])) {
            $request['created_at'] = Cast::date('now', 'c');
        }

        $request['updated_at'] = Cast::date('now', 'c');

        return $request;
    }
}
