<?php

namespace IN10\ApiGatewayDrupal\Resources\Blocks;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class Storycards extends JsonResource
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
        $type = str_replace('paragraph--sc_', '', $this->type);

        $component = [
            'type' => $type,
            'id' => $this->id,
        ];

        $block = Str::studly($type);
        $resource = '\\App\\Http\\Resources\\Blocks\\' . $block;
        if (!class_exists($resource, true)) {
            $resource = '\IN10\ApiGatewayDrupal\Resources\Blocks\\' . $block;
        }

        $component['content'] = new $resource($this);

        return $component;
    }
}
