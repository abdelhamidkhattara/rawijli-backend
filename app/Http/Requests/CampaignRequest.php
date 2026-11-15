<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array
    {
        return [
            'workspaceId' => ['required', 'uuid'], 'connectionId' => ['nullable', 'uuid'], 'name' => ['required', 'string', 'max:160'],
            'platform' => ['required', Rule::in(['meta', 'tiktok'])], 'objective' => ['required', 'string', 'max:80'],
            'dailyBudget' => ['required', 'numeric', 'min:1', 'max:10000000'], 'lifetimeBudget' => ['nullable', 'numeric', 'min:1'],
            'startsAt' => ['nullable', 'date'], 'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'], 'publish' => ['sometimes', 'boolean'],
            'adSets' => ['required', 'array', 'min:1'], 'adSets.*.name' => ['required', 'string', 'max:160'],
            'adSets.*.targeting' => ['required', 'string', 'max:2000'], 'adSets.*.dailyBudget' => ['required', 'numeric', 'min:1'],
            'adSets.*.ads' => ['required', 'array', 'min:1'], 'adSets.*.ads.*.name' => ['required', 'string', 'max:160'],
            'adSets.*.ads.*.format' => ['required', Rule::in(['image', 'video', 'carousel'])], 'adSets.*.ads.*.headline' => ['required', 'string', 'max:190'],
            'adSets.*.ads.*.primaryText' => ['required', 'string', 'max:3000'], 'adSets.*.ads.*.destinationUrl' => ['nullable', 'url', 'max:2048'],
            'adSets.*.ads.*.creativeId' => ['nullable', 'uuid'],
        ];
    }
}
