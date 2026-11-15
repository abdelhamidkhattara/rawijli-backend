<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Contract;
use App\Models\Milestone;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntegrationScaffoldTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_connection_tokens_are_never_exposed_in_bootstrap(): void
    {
        $lumina = Workspace::where('name', 'Lumina Skincare')->firstOrFail();
        Connection::create([
            'workspace_id' => $lumina->id,
            'platform' => 'meta',
            'kind' => 'ad_account',
            'name' => 'Live ad account',
            'external_id' => 'act_123',
            'status' => 'connected',
            'driver' => 'live',
            'access_token' => 'super-secret-token',
            'refresh_token' => null,
        ]);

        $owner = User::where('email', 'owner@rawijli.com')->firstOrFail();
        Sanctum::actingAs($owner);

        $connections = $this->getJson('/api/bootstrap')->json('data.connections');
        $this->assertNotEmpty($connections);
        foreach ($connections as $connection) {
            $this->assertArrayNotHasKey('accessToken', $connection);
            $this->assertArrayNotHasKey('access_token', $connection);
            $this->assertArrayNotHasKey('refreshToken', $connection);
            $this->assertArrayNotHasKey('refresh_token', $connection);
        }
    }

    public function test_admin_can_adjust_plan_and_credits_but_not_suspend_self(): void
    {
        $admin = User::where('email', 'admin@rawijli.com')->firstOrFail();
        $owner = User::where('email', 'owner@rawijli.com')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/users/'.$owner->id, ['plan' => 'scale', 'aiCreditsLimit' => 5000, 'aiCreditsUsed' => 12])
            ->assertOk()
            ->assertJsonPath('data.plan', 'scale')
            ->assertJsonPath('data.aiCreditsLimit', 5000);

        $this->assertSame('scale', $owner->fresh()->plan);
        $this->assertSame(5000, $owner->fresh()->ai_credits_limit);

        $this->patchJson('/api/admin/users/'.$admin->id, ['status' => 'suspended'])->assertStatus(422);
    }

    public function test_freelancer_can_open_a_dispute_on_an_active_contract(): void
    {
        $leila = User::where('email', 'freelancer@rawijli.com')->firstOrFail();
        $contract = Contract::find('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbb01');
        Sanctum::actingAs($leila);

        $this->postJson('/api/contracts/'.$contract->id.'/disputes', ['reason' => 'Deliverable was rejected but no revision notes were shared.'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');

        $this->assertSame('disputed', $contract->fresh()->status);
        $this->assertDatabaseHas('disputes', ['contract_id' => $contract->id, 'status' => 'open', 'opened_by' => $leila->id]);
    }

    public function test_milestone_payment_releases_partial_escrow_and_records_fee(): void
    {
        $owner = User::where('email', 'owner@rawijli.com')->firstOrFail();
        $milestone = Milestone::find('cccccccc-cccc-4ccc-8ccc-cccccccccc02');
        $contract = Contract::find($milestone->contract_id);
        Sanctum::actingAs($owner);

        $this->patchJson('/api/marketplace/milestones/'.$milestone->id, ['action' => 'pay'])
            ->assertOk();

        $this->assertSame('paid', $milestone->fresh()->status);
        $this->assertSame('completed', $contract->fresh()->status);
        $this->assertDatabaseHas('transactions', ['contract_id' => $contract->id, 'type' => 'marketplace_fee']);
        $this->assertSame('released', Transaction::where('contract_id', $contract->id)->where('type', 'escrow_hold')->value('status'));
    }

    public function test_reports_can_be_created_and_listed(): void
    {
        $owner = User::where('email', 'owner@rawijli.com')->firstOrFail();
        $lumina = Workspace::where('name', 'Lumina Skincare')->firstOrFail();
        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/reports', [
            'workspaceId' => $lumina->id,
            'dateFrom' => now()->subDays(7)->toDateString(),
            'dateTo' => now()->toDateString(),
            'format' => 'pdf',
        ])->assertCreated()->json('data');

        $this->assertSame('ready', $created['status']);

        $list = $this->getJson('/api/reports')->assertOk()->json('data');
        $this->assertNotEmpty($list);
    }

    public function test_connection_status_and_oauth_guard_behaviour(): void
    {
        $owner = User::where('email', 'owner@rawijli.com')->firstOrFail();
        $lumina = Workspace::where('name', 'Lumina Skincare')->firstOrFail();
        Sanctum::actingAs($owner);

        $status = $this->getJson('/api/connections/status')->assertOk()->json('data');
        $this->assertFalse($status['meta']);
        $this->assertFalse($status['tiktok']);

        $redirect = $this->get('/api/connections/oauth/meta?workspaceId='.$lumina->id.'&kind=ad_account');
        $redirect->assertRedirect();
        $this->assertStringContainsString('error=', $redirect->headers->get('Location'));
    }
}
