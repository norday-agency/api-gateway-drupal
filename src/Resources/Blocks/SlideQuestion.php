<?php

namespace IN10\ApiGatewayDrupal\Resources\Blocks;

use Illuminate\Http\Resources\Json\JsonResource;

class SlideQuestion extends JsonResource
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
            'question' => isset($this->question) ? $this->question : null,
            'answers' => isset($this->answers) ? SlideQuestionAnswer::collection($this->answers) : [],
            'result' => isset($this->result) ? $this->result : null,
            'theme' => isset($this->theme) ? $this->theme : null,
        ];
    }
}
