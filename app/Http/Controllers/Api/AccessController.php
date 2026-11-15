<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grant;
use App\Services\AuditTrail;
use App\Services\WorkspaceAccess;
use App\Support\ApiMapper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccessController extends Controller
{
    public function update(Request $request, Grant $grant, WorkspaceAccess $access, AuditTrail $audit)
    {
        $access->ownedWorkspace($request->user(), $grant->workspace_id);
        $data = $request->validate(['accessEnabled' => 'sometimes|boolean', 'permission' => ['nullable', Rule::in(WorkspaceAccess::PERMISSIONS)], 'value' => 'required_with:permission|boolean']);
        abort_if(($data['accessEnabled'] ?? $grant->access_enabled) && $grant->contract->status !== 'active', 409, 'Access requires an active contract.');
        $before = $grant->toArray();
        if (array_key_exists('accessEnabled', $data)) $grant->access_enabled = $data['accessEnabled'];
        if (! empty($data['permission'])) {
            $permissions = $grant->permissions;
            $permissions[$data['permission']] = $data['value'];
            $grant->permissions = $permissions;
        }
        $grant->updated_by_id = $request->user()->id;
        $grant->save();
        $audit->record($request->user(), 'access.updated', Grant::class, $grant->id, $grant->workspace_id, $before, $grant->toArray());
        return response()->json(['data' => ApiMapper::camel($grant->toArray())]);
    }
}
