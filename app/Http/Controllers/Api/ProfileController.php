<?php

namespace App\Http\Controllers\Api;

use App\Contracts\MediaStorage;
use App\Http\Controllers\Controller;
use App\Models\FreelancerProfile;
use App\Models\Package;
use App\Models\PortfolioItem;
use App\Support\ApiMapper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request, MediaStorage $storage)
    {
        $profile = $this->profile($request);
        $data = $request->validate([
            'headline' => ['sometimes', 'string', 'max:190'],
            'bio' => ['sometimes', 'string', 'max:5000'],
            'hourlyRate' => ['sometimes', 'numeric', 'min:1', 'max:100000'],
            'location' => ['sometimes', 'string', 'max:120'],
            'availability' => ['sometimes', 'string', 'max:120'],
            'skills' => ['sometimes', 'array'],
            'skills.*' => [Rule::in(['media_buying', 'graphic_design', 'video_editing', 'copywriting'])],
            'platforms' => ['sometimes', 'array'],
            'platforms.*' => [Rule::in(['meta', 'tiktok'])],
            'publicVisible' => ['sometimes', 'boolean'],
            'avatar' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
        unset($data['avatar']);
        $profile->update($data);

        if ($request->hasFile('avatar')) {
            $path = $storage->store($request->file('avatar'), 'avatars');
            $request->user()->update(['avatar' => $storage->url($path)]);
        }

        return response()->json(['data' => ApiMapper::camel($this->withRelations($profile))]);
    }

    public function addPackage(Request $request)
    {
        $profile = $this->profile($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'price' => ['required', 'numeric', 'min:1'], 'detail' => ['required', 'string', 'max:3000']]);
        $package = $profile->packages()->create($data);
        return response()->json(['data' => ApiMapper::camel($package->toArray())], 201);
    }

    public function removePackage(Request $request, Package $package)
    {
        $this->ownsPackage($request, $package);
        $package->delete();
        return response()->json(['data' => ['deleted' => true]]);
    }

    public function addPortfolio(Request $request, MediaStorage $storage)
    {
        $profile = $this->profile($request);
        $data = $this->portfolioData($request);
        unset($data['file']);
        $data['kind'] = $data['kind'] ?? 'image';
        if ($request->hasFile('file')) {
            $data['path'] = $storage->store($request->file('file'), 'portfolio/'.$profile->id);
            $data['kind'] = str_starts_with((string) $request->file('file')->getMimeType(), 'video/') ? 'video' : 'image';
        }
        $data['sort_order'] = ($profile->portfolio()->max('sort_order') ?? 0) + 1;
        $item = $profile->portfolio()->create($data);
        return response()->json(['data' => ApiMapper::camel($item->toArray())], 201);
    }

    public function updatePortfolio(Request $request, PortfolioItem $item)
    {
        $this->ownsPortfolio($request, $item);
        $data = $this->portfolioData($request);
        unset($data['file']);
        $item->update($data);
        return response()->json(['data' => ApiMapper::camel($item->fresh()->toArray())]);
    }

    public function removePortfolio(Request $request, PortfolioItem $item, MediaStorage $storage)
    {
        $this->ownsPortfolio($request, $item);
        $storage->delete($item->path);
        $item->delete();
        return response()->json(['data' => ['deleted' => true]]);
    }

    public function avatar(Request $request, MediaStorage $storage)
    {
        $this->profile($request);
        $request->validate(['avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240']]);
        $path = $storage->store($request->file('avatar'), 'avatars');
        $request->user()->update(['avatar' => $storage->url($path)]);

        return response()->json(['data' => ['avatar' => $storage->url($path)]]);
    }

    public function submitVetting(Request $request)
    {
        $profile = $this->profile($request);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:3000']]);
        $profile->update(['vetting' => 'pending', 'public_visible' => false, 'vetting_reason' => $data['note'] ?? $profile->vetting_reason]);
        $request->user()->update(['status' => 'pending']);
        return response()->json(['data' => ApiMapper::camel($this->withRelations($profile))]);
    }

    private function profile(Request $request): FreelancerProfile
    {
        $profile = $request->user()->freelancerProfile;
        abort_unless($request->user()->role === 'freelancer' && $profile, 403, 'A freelancer profile is required.');
        return $profile;
    }

    private function ownsPackage(Request $request, Package $package): void
    {
        abort_unless($package->freelancer_profile_id === $request->user()->freelancerProfile?->id, 403);
    }

    private function ownsPortfolio(Request $request, PortfolioItem $item): void
    {
        abort_unless($item->freelancer_profile_id === $request->user()->freelancerProfile?->id, 403);
    }

    private function portfolioData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'kind' => ['nullable', Rule::in(['image', 'video'])],
            'hue' => ['nullable', 'integer', 'min:0', 'max:360'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm,mov', 'max:51200'],
        ]);
    }

    private function withRelations(FreelancerProfile $profile): array
    {
        $profile->load(['user', 'packages', 'portfolio']);
        $row = $profile->toArray();
        $row['name'] = $profile->user->name;
        $row['avatar'] = $profile->user->avatar;
        return $row;
    }
}
