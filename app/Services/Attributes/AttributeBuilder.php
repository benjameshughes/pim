<?php

namespace App\Services\Attributes;

use App\Services\Attributes\Concerns\ConfiguresLogging;
use App\Services\Attributes\Concerns\LogsActivity;
use App\Services\Attributes\Concerns\ReadsAttributes;
use App\Services\Attributes\Concerns\TargetsAttributes;
use App\Services\Attributes\Concerns\WritesAttributes;

/**
 * AttributeBuilder
 *
 * Simple, powerful attribute API.
 * ->key('color')->value('red')  // Set
 * ->get('color')                // Get  
 * ->keys(['a','b'])->unset()    // Bulk operations
 */
class AttributeBuilder
{
    use TargetsAttributes;
    use ReadsAttributes;
    use WritesAttributes;
    use ConfiguresLogging;
    use LogsActivity;

    /** The attributable model (Product, Variant, etc.). */
    protected object $model;

    public function __construct(object $model)
    {
        $this->model = $model;
    }
}