<?php

namespace IN10\ApiGatewayDrupal\Resources\Blocks;

use Illuminate\Http\Resources\Json\JsonResource;

class SlideImageAndText extends JsonResource
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
            'subtitle' => isset($this->subtitle) ? $this->subtitle : null,
            'content' => isset($this->content) ? $this->content['value'] : null,
            'image' => isset($this->image) ? new DefaultImage($this->image) : null,
            'image_padding' => isset($this->image_padding) ? !$this->image_padding : true,
            'align_image_bottom' => isset($this->align_image_bottom) ? $this->align_image_bottom : false,
            'is_icon' => isset($this->is_icon) ? $this->is_icon : false,
            'theme' => isset($this->theme) ? $this->theme : null,
        ];
    }
}
