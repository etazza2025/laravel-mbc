<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TurnCollection extends ResourceCollection
{
    public $collects = TurnResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
