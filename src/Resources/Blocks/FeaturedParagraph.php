<?php

namespace IN10\ApiGatewayDrupal\Resources\Blocks;

use Illuminate\Http\Resources\Json\JsonResource;

class FeaturedParagraph extends JsonResource
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
            'content' => $this->content['value'],
            'image' => isset($this->image) ? new DefaultImage($this->image) : null,
            'image_align' => $this->image_align,
            'links' => (isset($this->links)) ? Link::collection($this->links) : null,
        ];
    }
}
