<?php

namespace IN10\ApiGatewayDrupal\Resources;

use IN10\ApiGatewayDrupal\Resources\Blocks\Blocks;

class News extends BaseResource
{
    protected $type = 'news';

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
        ];

        if ($this->detail_view === true) {
            $data['blocks'] = isset($this->components) ? Blocks::collection($this->components) : [];
        }

        return array_merge($base_data, $data);
    }
}
