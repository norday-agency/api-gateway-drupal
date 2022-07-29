<?php

namespace IN10\ApiGatewayDrupal\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use IN10\ApiGatewayDrupal\Resources\Blocks\DefaultImage;

class BaseResource extends JsonResource
{
    protected $detail_view;

    /**
     * Create a new resource instance.
     *
     * @param mixed     $resource
     * @param bool|null $load_components
     *
     * @return void
     */
    public function __construct($resource, $detail_view = false)
    {
        // Ensure you call the parent constructor
        parent::__construct($resource);
        $this->resource = $resource;

        $this->detail_view = $detail_view;
    }

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function toArray($request)
    {
        $resource = $this->resource;

        $data = [
            'type' => $this->formatType($resource->type),
            'nid' => $resource->drupal_internal__nid,
            'slug' => $resource->slug,
            'endpoint' => '/' . $resource->langcode . $resource->slug,
            'title' => $resource->title,
            'content' => html_entity_decode($resource->content['value'] ?? ''),
            'meta' => [
                'title' => ($resource->meta_title) ?: $resource->title,
                'description' => ($resource->meta_description) ?: (isset($resource->description) ? $resource->description : ''),
                'image' => isset($this->meta_image) ? new DefaultImage($this->meta_image) : null,
            ],
            'publish_date' => $this->formatDate($resource->publish_date),
            'published' => $resource->published,
            'sticky' => $resource->sticky,
            'header_image' => isset($this->header_image) ? new DefaultImage($this->header_image) : null,
        ];

        return $data;
    }

    public function formatType(string $type): string
    {
        $type = str_replace('node--ct_', '', $type);

        return $type;
    }

    /**
     * Transform Drupal date string to array containing timestamp, human and machine date
     */
    public function formatDate(string $date): array
    {
        return [
            'timestamp' => Carbon::parse($date)->getTimestamp(),
            // 'human' => Carbon::parse($date)->formatLocalized('%d %B %Y'),
            'machine' => $date,
        ];
    }
}
