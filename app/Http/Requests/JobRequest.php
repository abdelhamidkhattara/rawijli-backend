<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'owner'; }
    public function rules(): array
    {
        return ['workspaceId' => ['required', 'uuid'], 'title' => ['required', 'string', 'max:180'], 'description' => ['required', 'string', 'max:10000'], 'skills' => ['required', 'array', 'min:1'], 'skills.*' => [Rule::in(['media_buying', 'graphic_design', 'video_editing', 'copywriting'])], 'platforms' => ['nullable', 'array'], 'platforms.*' => [Rule::in(['meta', 'tiktok'])], 'budget' => ['required', 'numeric', 'min:1'], 'durationDays' => ['required', 'integer', 'min:1', 'max:365'], 'aiGenerated' => ['sometimes', 'boolean']];
    }
}
