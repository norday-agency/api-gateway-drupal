<?php

namespace IN10\ApiGatewayDrupal\Resources;

use IN10\ApiGatewayDrupal\Resources\Blocks\Storycards;

class Story extends BaseResource
{
    protected $type = 'story';

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function toArray($request)
    {
        $base_data = parent::toArray($request);

        $data = [
            'description' => isset($this->description) ? $this->description : '',
            'related_story' => isset($this->related_story) ? new Story($this->related_story) : null,
        ];

        if ($this->detail_view === true) {
            $data['storycards'] = isset($this->storycards) ? Storycards::collection($this->storycards) : [];
        }

        return array_merge($base_data, $data);
    }
}
