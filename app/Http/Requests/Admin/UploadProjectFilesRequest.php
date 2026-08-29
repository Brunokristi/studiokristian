<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UploadProjectFilesRequest extends AdminClientPortalRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        $project = $this->route('project');

        return $project && $project->members()->whereKey($user->id)->exists();
    }

    public function rules(): array
    {
        $project = $this->route('project');
        $projectId = is_object($project) ? $project->id : (int) $project;

        return [
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('project_folders', 'id')->where(fn ($query) => $query->where('project_id', $projectId)),
            ],
            'file' => ['nullable', 'file', 'required_without:files'],
            'files' => ['nullable', 'array', 'required_without:file', 'min:1', 'max:1000'],
            'files.*' => ['file'],
            'relative_path' => ['nullable', 'string', 'max:2000'],
            'relative_paths' => ['nullable', 'array'],
            'relative_paths.*' => ['nullable', 'string', 'max:2000'],
            'client_visible' => ['required', 'boolean'],
        ];
    }
}