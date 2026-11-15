<?php

namespace App\Http\Controllers\Api;

use App\Contracts\ImageGenerator;
use App\Contracts\MediaStorage;
use App\Http\Controllers\Controller;
use App\Models\Creative;
use App\Services\WorkspaceAccess;
use App\Support\ApiMapper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CreativeController extends Controller
{
    public function store(Request $request, WorkspaceAccess $access, ImageGenerator $generator)
    {
        $data = $this->validateData($request);
        $workspace = $access->workspace($request->user(), $data['workspaceId'], 'upload_creatives');
        if (($data['kind'] ?? 'generated') === 'generated') {
            $image = $generator->generate($workspace->id, $data['headline'], $data['aspectRatio'] ?? '4:5');
            $data['path'] = $image['path'];
            $data['hue'] = $image['hue'];
        }
        $creative = Creative::create($this->attributes($data, $request->user()->id));
        return response()->json(['data' => ApiMapper::camel($creative->toArray())], 201);
    }

    public function upload(Request $request, WorkspaceAccess $access, MediaStorage $storage)
    {
        $data = $this->validateData($request, true);
        $workspace = $access->workspace($request->user(), $data['workspaceId'], 'upload_creatives');
        $path = $storage->store($request->file('file'), 'creatives/'.$workspace->id);
        $data['path'] = $path;
        $data['kind'] = 'upload';
        $creative = Creative::create($this->attributes($data, $request->user()->id));
        return response()->json(['data' => ApiMapper::camel($creative->toArray())], 201);
    }

    public function update(Request $request, Creative $creative, WorkspaceAccess $access)
    {
        $data = $this->validateData($request);
        $access->workspace($request->user(), $creative->workspace_id, 'upload_creatives');
        abort_if($data['workspaceId'] !== $creative->workspace_id, 422, 'A creative cannot be moved between workspaces.');
        $creative->update($this->attributes($data, $creative->created_by_id));
        return response()->json(['data' => ApiMapper::camel($creative->fresh()->toArray())]);
    }

    public function duplicate(Request $request, Creative $creative, WorkspaceAccess $access)
    {
        $access->workspace($request->user(), $creative->workspace_id, 'upload_creatives');
        $copy = $creative->replicate();
        $copy->title = $creative->title.' (Copy)';
        $copy->created_by_id = $request->user()->id;
        $copy->push();
        return response()->json(['data' => ApiMapper::camel($copy->toArray())], 201);
    }

    public function destroy(Request $request, Creative $creative, WorkspaceAccess $access, MediaStorage $storage)
    {
        $access->workspace($request->user(), $creative->workspace_id, 'upload_creatives');
        abort_if($creative->calendarItems()->exists() || $creative->ads()->exists(), 409, 'Remove this creative from scheduled posts and ads before deleting it.');
        $storage->delete($creative->path);
        $creative->delete();
        return response()->json(['data' => ['deleted' => true]]);
    }

    private function validateData(Request $request, bool $file = false): array
    {
        return $request->validate(['workspaceId' => 'required|uuid', 'title' => 'required|string|max:180', 'caption' => 'required|string|max:5000', 'headline' => 'required|string|max:190', 'platform' => ['required', Rule::in(['instagram', 'facebook', 'tiktok'])], 'aspectRatio' => 'nullable|in:4:5,1:1,9:16', 'hue' => 'nullable|integer|min:0|max:360', 'kind' => 'nullable|in:generated,upload', 'file' => [$file ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg,mp4,webm,mov', 'max:51200']]);
    }

    private function attributes(array $data, ?string $userId): array
    {
        return ['workspace_id' => $data['workspaceId'], 'created_by_id' => $userId, 'title' => $data['title'], 'caption' => $data['caption'], 'headline' => $data['headline'], 'platform' => $data['platform'], 'aspect_ratio' => $data['aspectRatio'] ?? '4:5', 'kind' => $data['kind'] ?? 'generated', 'path' => $data['path'] ?? null, 'hue' => $data['hue'] ?? 280, 'processing_status' => 'ready'];
    }
}
