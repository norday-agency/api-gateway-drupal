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
        if (isset($this->link['options']['url'])) {
            $url = $this->link['options']['url'];
        } elseif (isset($this->link['url'])) {
            $url = $this->link['url'];
        } else {
            $url = $this->link['uri'];
        }
        if (substr($url, 0, 3) === '/' . config('app.base_locale', 'nl')) {
            $url = str_replace('/' . config('app.base_locale', 'nl'), '', $url);
        }

        return [
            'url' => $url,
            'text' => ($this->link['title']) ?: null,
            // Todo: add target (external bool)
        ];
    }
}
