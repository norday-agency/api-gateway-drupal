<?php

namespace IN10\ApiGatewayDrupal\Resources\Taxonomies;

use Illuminate\Http\Resources\Json\JsonResource;

class BaseTaxonomy extends JsonResource
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
            'id' => $this->drupal_internal__tid,
            'title' => $this->name,
        ];
    }
}
