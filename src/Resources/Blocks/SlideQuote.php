<?php

namespace IN10\ApiGatewayDrupal\Resources\Blocks;

use Illuminate\Http\Resources\Json\JsonResource;

class SlideQuote extends JsonResource
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
            'quote' => isset($this->quote) ? $this->quote : null,
            'author' => isset($this->author) ? $this->author : null,
            'theme' => isset($this->theme) ? $this->theme : null,
        ];
    }
}
