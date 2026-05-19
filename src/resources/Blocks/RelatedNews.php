<?php

namespace IN10\ApiGatewayDrupal\Resources\Blocks;

use Illuminate\Http\Resources\Json\JsonResource;
use IN10\ApiGatewayDrupal\Resources\News;

class RelatedNews extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'items' => (isset($this->related_items)) ? News::collection($this->related_items) : [],
        ];
    }
}
