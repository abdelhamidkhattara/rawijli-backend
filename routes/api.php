<?php

use App\Http\Controllers\Api\AccessController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\ConnectionOAuthController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CopilotController;
use App\Http\Controllers\Api\CreativeController;
use App\Http\Controllers\Api\DeliverableController;
use App\Http\Controllers\Api\DisputeController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function () {
    Route::get('/freelancers', [PublicController::class, 'directory']);
    Route::get('/freelancers/{id}', [PublicController::class, 'profile']);
});

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['token.cookie', 'auth:sanctum'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/bootstrap', BootstrapController::class);

    Route::get('/workspaces/clients', [WorkspaceController::class, 'clients']);
    Route::get('/connections/status', [ConnectionOAuthController::class, 'status']);
    Route::get('/connections/oauth/{platform}', [ConnectionOAuthController::class, 'redirect'])->whereIn('platform', ['meta', 'tiktok']);
    Route::get('/connections/oauth/{platform}/callback', [ConnectionOAuthController::class, 'callback'])->whereIn('platform', ['meta', 'tiktok']);
    Route::get('/connections/accounts', [WorkspaceController::class, 'accounts']);
    Route::post('/connections', [WorkspaceController::class, 'connect']);
    Route::post('/connections/{connection}/action', [WorkspaceController::class, 'connectionAction']);

    Route::get('/workspaces/{workspace}/analytics', [AnalyticsController::class, 'index']);
    Route::get('/workspaces/{workspace}/analytics/export', [AnalyticsController::class, 'export']);

    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::patch('/campaigns/{campaign}', [CampaignController::class, 'update']);
    Route::post('/campaigns/{campaign}/status', [CampaignController::class, 'status']);
    Route::post('/campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate']);
    Route::post('/campaigns/{campaign}/budget', [CampaignController::class, 'budget']);

    Route::post('/creatives', [CreativeController::class, 'store']);
    Route::post('/creatives/upload', [CreativeController::class, 'upload']);
    Route::patch('/creatives/{creative}', [CreativeController::class, 'update']);
    Route::post('/creatives/{creative}/duplicate', [CreativeController::class, 'duplicate']);
    Route::delete('/creatives/{creative}', [CreativeController::class, 'destroy']);

    Route::post('/calendar', [CalendarController::class, 'store']);
    Route::patch('/calendar/{item}', [CalendarController::class, 'update']);
    Route::post('/calendar/{item}/cancel', [CalendarController::class, 'cancel']);

    Route::post('/copilot/ask', [CopilotController::class, 'ask']);

    Route::post('/marketplace/brief', [MarketplaceController::class, 'aiBrief']);
    Route::post('/marketplace/jobs', [MarketplaceController::class, 'storeJob']);
    Route::patch('/marketplace/jobs/{job}', [MarketplaceController::class, 'updateJob']);
    Route::post('/marketplace/jobs/{job}/status', [MarketplaceController::class, 'jobStatus']);
    Route::post('/marketplace/jobs/{job}/propose', [MarketplaceController::class, 'propose']);
    Route::patch('/marketplace/proposals/{proposal}', [MarketplaceController::class, 'proposalStatus']);
    Route::post('/marketplace/hire', [MarketplaceController::class, 'hire']);
    Route::post('/marketplace/invitations', [MarketplaceController::class, 'invite']);
    Route::patch('/marketplace/invitations/{invitation}', [MarketplaceController::class, 'respondInvitation']);
    Route::patch('/marketplace/milestones/{milestone}', [MarketplaceController::class, 'milestone']);

    Route::patch('/grants/{grant}', [AccessController::class, 'update']);
    Route::patch('/approvals/{approval}', [ApprovalController::class, 'decide']);

    Route::post('/threads', [MessageController::class, 'storeThread']);
    Route::post('/threads/{thread}/messages', [MessageController::class, 'send']);
    Route::post('/threads/{thread}/read', [MessageController::class, 'read']);

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);

    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/packages', [ProfileController::class, 'addPackage']);
    Route::delete('/profile/packages/{package}', [ProfileController::class, 'removePackage']);
    Route::post('/profile/portfolio', [ProfileController::class, 'addPortfolio']);
    Route::patch('/profile/portfolio/{item}', [ProfileController::class, 'updatePortfolio']);
    Route::delete('/profile/portfolio/{item}', [ProfileController::class, 'removePortfolio']);
    Route::post('/profile/avatar', [ProfileController::class, 'avatar']);
    Route::post('/profile/vetting', [ProfileController::class, 'submitVetting']);

    Route::post('/deliverables', [DeliverableController::class, 'store']);
    Route::post('/deliverables/{deliverable}/revisions', [DeliverableController::class, 'resubmit']);
    Route::post('/deliverables/{deliverable}/review', [DeliverableController::class, 'review']);
    Route::post('/deliverables/{deliverable}/comments', [DeliverableController::class, 'comment']);

    Route::post('/contracts/{contract}/disputes', [DisputeController::class, 'store']);

    Route::patch('/admin/users/{user}', [AdminController::class, 'updateUser']);
    Route::post('/admin/freelancers/{freelancer}/vetting', [AdminController::class, 'vet']);
    Route::post('/admin/disputes/{dispute}/resolve', [AdminController::class, 'resolveDispute']);

    Route::get('/reports', [ReportController::class, 'index']);
    Route::post('/reports', [ReportController::class, 'store']);
    Route::get('/reports/{report}/download', [ReportController::class, 'download']);
});
