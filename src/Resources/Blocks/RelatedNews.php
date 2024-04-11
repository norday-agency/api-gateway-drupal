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
        if (isset($this->related_items)) {
            return [
                'items' => News::collection($this->related_items),
            ];
        }

        return [
            'fetch' => 'true', // Todo: check if this good practice
        ];
    }
}
