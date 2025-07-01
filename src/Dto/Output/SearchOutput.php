<?php

declare(strict_types=1);

namespace App\Dto\Output;

class SearchOutput
{
    public SearchMetaOutput $meta;

    public SearchDataOutput $data;

    public function __construct(SearchMetaOutput $meta, SearchDataOutput $data)
    {
        $this->meta = $meta;
        $this->data = $data;
    }
}
