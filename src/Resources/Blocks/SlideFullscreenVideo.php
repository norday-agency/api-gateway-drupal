<?php

namespace IN10\ApiGatewayDrupal\Resources\Blocks;

use Illuminate\Http\Resources\Json\JsonResource;

class SlideFullscreenVideo extends JsonResource
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
            'source_url' => isset($this->source_url) ? new Link((object) ['link' => $this->source_url]) : null,
            'should_autoplay' => isset($this->should_autoplay) ? $this->should_autoplay : null,
            'theme' => isset($this->theme) ? $this->theme : null,
        ];
    }
}
