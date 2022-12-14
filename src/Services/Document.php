<?php

namespace App\Domains\Elasticsearch\Services;

use App\Domains\DevSupport\Services\EnvironmentService;
use App\Services\Cast;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Support\Facades\Http;

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

    public function deleteIndex($index)
    {
        try {
            $response = Http::retry($this->retry, $this->retrySleep)
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ])
                ->delete(sprintf('%s/%s', $this->url, $index))->status();

            return $response;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function exists($index)
    {
        try {
            $response = Http::retry($this->retry, $this->retrySleep)
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ])
                ->get(sprintf('%s/%s', $this->url, $index))->status();

            return $response;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function get($index, $id)
    {
        try {
            $response = Http::retry($this->retry, $this->retrySleep)
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ])
                ->get(sprintf('%s/%s/_doc/%s', $this->url, $index, $id));

            return $response->json();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function mget($index, $ids = [])
    {
        $allId = [];
        foreach ($ids as $id) {
            $allId[] = [
                '_id' => $id,
            ];
        }

        $return = [];

        $chunks = collect($allId)->chunk(1000);
        foreach ($chunks as $chunk) {
            $response = Http::retry($this->retry, $this->retrySleep)
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => $this->token,
                ])
                ->withBody(json_encode(['docs' => $chunk->values()->all()]), 'application/json')
                ->get(sprintf('%s/%s/_mget', $this->url, $index));

            $r = $response->json();
            foreach ($r['docs'] as $f) {
                $return[] = $f;
            }
        }

        return $return;
    }

    public function putMapping($index, $mapping)
    {
        $response = Http::retry($this->retry, $this->retrySleep)
            ->timeout(30)
            ->withHeaders([
                'Authorization' => $this->token,
                'Content-Type' => 'application/json',
            ])
            ->put(sprintf('%s/%s/_mapping', $this->url, $index), $mapping);

        return $response->json();
    }

    public function delete_by_query($index, $payload)
    {
        $response = Http::retry($this->retry, $this->retrySleep)
            ->timeout(30)
            ->withHeaders([
                'Authorization' => $this->token,
                'Content-Type' => 'application/json',
            ])
            ->post(sprintf('%s/%s/_delete_by_query', $this->url, $index), $payload);

        return $response->json();
    }

    public function search($index, $payload, $withScroll = false)
    {
        try {
            $response = Http::retry($this->retry, $this->retrySleep)
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ])
                ->post(sprintf('%s/%s/_search', $this->url, $index).($withScroll ? '?scroll=1m' : ''), $payload);

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
                ->post(
                    sprintf('%s/_search/scroll', $this->url),
                    [
                        'scroll' => '1m',
                        'scroll_id' => $scrollId,
                    ]
                );

            return $response->json();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            var_dump($e->response->body());

            return [];
        }
    }

    public function index($index, $id, $request, $withTimeStamp = true)
    {
        if ($withTimeStamp) {
            $request = $this->addTimestamps($request);
        }

        try {
            $response = Http::retry($this->retry, $this->retrySleep)
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ]);

            if (empty($id)) {
                $response = $response->post(
                    sprintf('%s/%s/_doc/', $this->url, $index),
                    $request
                );
            } else {
                $response = $response->put(
                    sprintf('%s/%s/_doc/%s', $this->url, $index, $id),
                    $request
                );
            }

            if (
                EnvironmentService::isLocalConsole()
            ) {
                echo 'index: '.sprintf('%s/_doc/%s', $index, $id)."\n";
            }

            return $response->json();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            throw new \Exception($e->response->body());
        }
    }

    public function list($page = 1)
    {
        $client = new Guzzle();
        try {
            $response = $client->request('GET', $this->url."/list?page[current]={$page}", [
                'headers' => [
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function bulk($data)
    {
        $data = trim(collect($data)->map(function ($item) {
            return json_encode($item);
        })->implode("\r\n")."\r\n");

        dd($data);
        try {
            $response = Http::retry($this->retry, $this->retrySleep)
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/x-ndjson',
                ])
                ->post(
                    sprintf('%s/_bulk', $this->url),
                    $data
                );

            return $response->json();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            var_dump($e->response->body());

            return [];
        }
    }

    private function addTimestamps($request)
    {
        if (
            ! isset($request['created_at'])
        ) {
            $request['created_at'] = Cast::date('now', 'c');
        }

        $request['updated_at'] = Cast::date('now', 'c');

        return $request;
    }
}
