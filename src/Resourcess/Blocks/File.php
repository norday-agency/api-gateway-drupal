<?php

namespace IN10\ApiGatewayDrupal\Resources\Blocks;

use Illuminate\Http\Resources\Json\JsonResource;

class File extends JsonResource
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
            'url' => $this->file->uri['url'],
            'text' => $this->file->filename,
            'extension' => $this->formatExtension($this->file->filename),
            'filesize' => $this->formatFilesize($this->file->filesize),
        ];
    }

    private function formatExtension(string $filename): string
    {
        $option = [
            'txt' => 'TXT',
            'doc' => 'Word',
            'docx' => 'Word',
            'pdf' => 'PDF',
            'epub' => 'ePub',
            'jpg' => 'JPG',
            'ppt' => 'Powerpoint',
            'pptx' => 'Powerpoint',
        ];

        $arr_filename = explode('.', $filename);
        $extension = end($arr_filename);

        return $option[$extension];
    }

    private function formatFilesize($bytes)
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }
}
