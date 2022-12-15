<?php

namespace IN10\ApiGatewayDrupal\Resources\Blocks;

use Illuminate\Http\Resources\Json\JsonResource;

class Link extends JsonResource
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
        // Todo: get default locale from env for removing default locale in urls
        $url = $this->link['options']['url'];
        if (substr($url, 0, 3) === '/nl') {
            $url = str_replace('/nl', '', $url);
        }

        return [
            'url' => $url,
            'text' => ($this->link['title']) ?: null,
            // Todo: add target (external bool)
        ];
    }
}
