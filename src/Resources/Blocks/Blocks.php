<?php

namespace IN10\ApiGatewayDrupal\Resources\Blocks;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class Blocks extends JsonResource
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
        $type = str_replace('paragraph--component_', '', $this->type);

        $component = [
            'type' => $type,
            'id' => $this->id,
            'header' => [
                'title' => $this->title,
            ],
        ];

        if (property_exists($this->resource, 'description')) {
            $component['header']['description'] = ($this->description) ? $this->description['value'] : null;
        }

        $block = Str::studly($type);
        $resource = '\IN10\ApiGatewayDrupal\Resources\Blocks\\' . $block;
        if (!class_exists($resource, true)) {
            $resource = '\\App\\Http\\Resources\\Blocks\\' . $block;
        }

        $component['content'] = new $resource($this);

        return $component;
    }
}
