<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FreelancerProfile;
use App\Support\ApiMapper;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PublicController extends Controller
{
    public function directory(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:120', 'skill' => 'nullable|string', 'platform' => 'nullable|in:meta,tiktok', 'min_rating' => 'nullable|numeric|min:0|max:5', 'max_rate' => 'nullable|numeric|min:0', 'location' => 'nullable|string|max:100', 'sort' => 'nullable|in:rating,rate_asc,rate_desc,jobs', 'page' => 'nullable|integer|min:1']);
        $items = FreelancerProfile::with(['user', 'packages', 'portfolio'])->where('vetting', 'approved')->where('public_visible', true)
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))->get()
            ->filter(function ($f) use ($data) {
                $q = mb_strtolower($data['q'] ?? '');
                return (! $q || str_contains(mb_strtolower($f->user->name.' '.$f->headline.' '.$f->bio), $q))
                    && (empty($data['skill']) || in_array($data['skill'], $f->skills ?? []))
                    && (empty($data['platform']) || in_array($data['platform'], $f->platforms ?? []))
                    && (! isset($data['min_rating']) || $f->rating >= (float) $data['min_rating'])
                    && (! isset($data['max_rate']) || $f->hourly_rate <= (float) $data['max_rate'])
                    && (empty($data['location']) || str_contains(mb_strtolower($f->location), mb_strtolower($data['location'])));
            });
        $items = (match ($data['sort'] ?? 'rating') {
            'rate_asc' => $items->sortBy('hourly_rate'), 'rate_desc' => $items->sortByDesc('hourly_rate'),
            'jobs' => $items->sortByDesc('completed_jobs'), default => $items->sortByDesc('rating'),
        })->values();
        $page = (int) ($data['page'] ?? 1);
        $paginator = new LengthAwarePaginator($items->forPage($page, 9)->map(fn ($f) => $this->profileArray($f))->values(), $items->count(), 9, $page);
        return response()->json(ApiMapper::camel($paginator->toArray()));
    }

    public function profile(string $id)
    {
        $profile = FreelancerProfile::with(['user', 'packages', 'portfolio'])->where('vetting', 'approved')->where('public_visible', true)->findOrFail($id);
        return response()->json(['data' => ApiMapper::camel($this->profileArray($profile))]);
    }

    public function profileArray(FreelancerProfile $profile): array
    {
        $row = $profile->toArray();
        $row['name'] = $profile->user->name;
        $row['avatar'] = $profile->user->avatar;
        $row['packages'] = $profile->packages->toArray();
        $row['portfolio'] = $profile->portfolio->toArray();
        return $row;
    }
}
