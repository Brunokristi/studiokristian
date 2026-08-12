<?php

namespace App\Http\Requests\Admin\ClientPortal;

class UploadProjectFilesRequest extends AdminClientPortalRequest
{
    public function rules(): array
    {
        return [
            'folder_id' => ['nullable', 'integer', 'exists:project_folders,id'],
            'files' => ['required', 'array', 'min:1', 'max:50'],
            'files.*' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,svg,doc,docx,xls,xlsx,csv,txt,zip'],
            'relative_paths' => ['nullable', 'array'],
            'relative_paths.*' => ['nullable', 'string', 'max:2000'],
            'client_visible' => ['required', 'boolean'],
        ];
    }
}