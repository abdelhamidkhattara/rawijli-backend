<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array
    {
        return ['workspaceId' => ['required', 'uuid'], 'creativeId' => ['nullable', 'uuid'], 'date' => ['required', 'date'], 'time' => ['required', 'date_format:H:i'], 'channel' => ['required', Rule::in(['instagram', 'facebook', 'tiktok'])], 'kind' => ['required', Rule::in(['organic', 'ad'])], 'title' => ['required', 'string', 'max:180'], 'caption' => ['required', 'string', 'max:5000'], 'status' => ['nullable', Rule::in(['draft', 'scheduled'])]];
    }
}
