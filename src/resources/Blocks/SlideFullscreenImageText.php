<?php

namespace IN10\ApiGatewayDrupal\Resources\Blocks;

use Illuminate\Http\Resources\Json\JsonResource;

class SlideFullscreenImageText extends JsonResource
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
            'content' => isset($this->content) ? $this->content : null,
            'image' => isset($this->image) ? new DefaultImage($this->image) : null,
            'is_left' => isset($this->is_left) ? $this->is_left : false,
            'text_top' => isset($this->text_top) ? $this->text_top : false,
            'is_single' => isset($this->is_single) ? $this->is_single : false,
            'title_size' => isset($this->title_size) ? $this->title_size : null,
            'theme' => isset($this->theme) ? $this->theme : null,
        ];
    }
}
