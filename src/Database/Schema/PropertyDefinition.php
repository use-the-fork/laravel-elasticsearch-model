<?php

namespace UseTheFork\LaravelElasticsearchModel\Database\Schema;

use Closure;
use Illuminate\Support\Fluent;

/**
 * Class ColumnDefinition
 *
 * @method PropertyDefinition boost(int $boost)
 * @method PropertyDefinition dynamic(bool $dynamic = TRUE)
 * @method PropertyDefinition fields(Closure $field)
 * @method PropertyDefinition format(string $format)
 * @method PropertyDefinition index(bool $index = TRUE)
 * @method PropertyDefinition properties(Closure $field)
 */
class PropertyDefinition extends Fluent
{
    //
    public function default($value): static
    {
        return $this->null_value($value);
    }

    public function null_value($value): static
    {
        $this->attributes['null_value'] = $value;

        return $this;
    }
}
