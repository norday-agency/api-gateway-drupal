<?php

namespace IN10\ApiGatewayDrupal\Resources\Blocks;

use Illuminate\Http\Resources\Json\JsonResource;

class SlideQuestionAnswer extends JsonResource
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
            'title' => isset($this->answer) ? $this->answer : null,
            'is_correct_answer' => isset($this->is_correct_answer) ? $this->is_correct_answer : null,
        ];
    }
}
