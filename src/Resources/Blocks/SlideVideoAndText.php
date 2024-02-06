<?php

namespace IN10\ApiGatewayDrupal\Resources\Blocks;

use Illuminate\Http\Resources\Json\JsonResource;

class SlideVideoAndText extends JsonResource
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
            'title' => isset($this->title) ? $this->title : null,
            'title_size' => isset($this->title_size) ? $this->title_size : 'small',
            'content' => isset($this->content) ? $this->content['value'] : null,
            'source_url' => isset($this->source_url) ? new Link((object) ['link' => $this->source_url]) : null,
            'theme' => isset($this->theme) ? $this->theme : null,
        ];
    }
}
