<?php

namespace IN10\ApiGatewayDrupal\Resources\Blocks;

use Illuminate\Http\Resources\Json\JsonResource;

class DefaultImage extends JsonResource
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
        if (isset($this->image)) {
            return [
                'image' => (isset($this->image->image)) ? [
                    'alt' => $this->image->alt ?? '',
                    'src' => $this->image->image->uri['url'],
                    'sizes' => !empty($this->image->image->images) ? $this->image->image->images : null,
                    'srcset' => !empty($this->image->image->images) ? collect($this->image->image->images)->map(function ($image, $key) {
                        return $image . ' ' . $key;
                    })->flatten() : null,
                ] : null,
                'source' => $this->source,
                'source_link' => ($this->source_url) ? new Link((object) ['link' => $this->source_url]) : null,
            ];
        }
    }
}
