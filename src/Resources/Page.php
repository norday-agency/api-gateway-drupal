<?php

namespace IN10\ApiGatewayDrupal\Resources;

use IN10\ApiGatewayDrupal\Resources\Blocks\Blocks;

class Page extends BaseResource
{
    protected $type = 'pages';

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
            'breadcrumb' => (isset($this->parent_menu_item)) ? [
                'slug' => $this->parent_menu_item->slug,
                'endpoint' => '/' . $this->langcode . $this->parent_menu_item->slug,
                'title' => $this->parent_menu_item->title,
            ] : null,
        ];

        if ($this->detail_view === true) {
            $data['blocks'] = isset($this->components) ? Blocks::collection($this->components) : [];
        }

        return array_merge($base_data, $data);
    }
}
