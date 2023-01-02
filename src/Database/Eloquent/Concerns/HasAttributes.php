<?php

namespace UseTheFork\LaravelElasticsearchModel\Database\Eloquent\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

trait HasAttributes
{
    /**
     * {@inheritDoc}
     */
    public function getDirty()
    {
        //Since all queryies are upserts we dont actually need dirty attributes so get it all
        $dirty = $this->getAttributes();
        //Add a hook and add ID to use in the update query
        $dirty['id'] = $this->getKey();

        return $dirty;
    }

    /**
     * {@inheritDoc}
     */
    public function getDirtyForLogging()
    {
        $dirty = [];
        foreach (Arr::dot($this->getFilthy()) as $key => $item) {
            if ($item != null && $item != []) {
                $dirty[$key] = $item;
            }
        }

        return $dirty;
    }

    /**
     * {@inheritDoc}
     */
    public function getAttributesForLogging()
    {
        $dirty = [];
        foreach (Arr::dot($this->getAttributes()) as $key => $item) {
            if (! empty($item)) {
                $dirty[$key] = $item;
            }
        }

        return $dirty;
    }

    /**
     * {@inheritDoc}
     */
    public function getFilthy()
    {
        $dirty = [];
        $copy = $this;

        foreach ($copy->getAttributes() as $key => $value) {
            if ($value instanceof Collection) {
                $copy[$key] = $value->all();
            }

            if (! $copy->originalIsEquivalent($key)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }
}
