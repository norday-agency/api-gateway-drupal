<?php

namespace IN10\ApiGatewayDrupal\Resources\Blocks;

use Illuminate\Http\Resources\Json\JsonResource;

class ImageCarousel extends JsonResource
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
            'items' => ImageCarouselItem::collection($this->image_carousel_images),
        ];
    }
}
