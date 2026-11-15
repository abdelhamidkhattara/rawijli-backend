<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\CalendarItem;
use App\Models\Campaign;
use App\Models\Contract;
use App\Models\FreelancerProfile;
use App\Models\Grant;
use App\Models\MetricPoint;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RawijliApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_owner_can_login_and_restore_session(): void
    {
        $response = $this->postJson('/api/auth/login', ['email' => 'owner@rawijli.com', 'password' => 'password']);
        $response->assertOk()->assertJsonPath('data.email', 'owner@rawijli.com')->assertJsonMissingPath('data.password');

        $header = (string) $response->headers->get('set-cookie');
        $this->assertStringContainsString('rawijli_token=', $header);
        preg_match('/rawijli_token=([^;]+)/', $header, $matches);
        $token = urldecode($matches[1]);
        $this->assertNotEmpty($token);

        $this->withCredentials()
            ->withUnencryptedCookie('rawijli_token', $token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'owner@rawijli.com')
            ->assertJsonPath('data.workspace.name', 'Lumina Skincare');
    }

    public function test_registration_carries_selected_plan_into_subscription(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Plan Owner', 'email' => 'plan@example.test', 'password' => 'password123',
            'role' => 'owner', 'company' => 'Plan Co', 'plan' => 'scale',
        ]);

        $response->assertCreated()->assertJsonPath('data.plan', 'scale');
        $user = User::where('email', 'plan@example.test')->firstOrFail();
        $this->assertSame('scale', $user->plan);
        $this->assertDatabaseHas('subscriptions', ['workspace_id' => $user->workspace->id, 'plan' => 'scale', 'status' => 'trialing']);
    }

    public function test_owner_cannot_read_another_owners_workspace_analytics(): void
    {
        $marcus = User::where('email', 'marcus@northpeak.fit')->firstOrFail();
        $lumina = Workspace::where('name', 'Lumina Skincare')->firstOrFail();
        Sanctum::actingAs($marcus);

        $this->getJson('/api/workspaces/'.$lumina->id.'/analytics')->assertForbidden();
    }

    public function test_freelancer_without_grant_cannot_access_client_workspace(): void
    {
        $jonas = User::where('email', 'jonas@rawijli.com')->firstOrFail();
        $lumina = Workspace::where('name', 'Lumina Skincare')->firstOrFail();
        Sanctum::actingAs($jonas);

        $this->getJson('/api/workspaces/'.$lumina->id.'/analytics')->assertForbidden();
    }

    public function test_freelancer_permission_is_enforced_independently(): void
    {
        $leila = User::where('email', 'freelancer@rawijli.com')->firstOrFail();
        $north = Workspace::where('name', 'NorthPeak Fitness')->firstOrFail();
        Sanctum::actingAs($leila);

        $this->getJson('/api/workspaces/'.$north->id.'/analytics')->assertOk();

        $this->postJson('/api/campaigns', $this->campaignPayload($north->id))->assertForbidden();
    }

    public function test_revoking_access_immediately_blocks_client_data_and_mutations(): void
    {
        $leila = User::where('email', 'freelancer@rawijli.com')->firstOrFail();
        $lumina = Workspace::where('name', 'Lumina Skincare')->firstOrFail();
        Sanctum::actingAs($leila);

        $this->getJson('/api/workspaces/'.$lumina->id.'/analytics')->assertOk();

        $grant = Grant::where('workspace_id', $lumina->id)->where('freelancer_id', $leila->freelancerProfile->id)->firstOrFail();
        $grant->update(['access_enabled' => false]);

        $this->getJson('/api/workspaces/'.$lumina->id.'/analytics')->assertForbidden();
        $this->postJson('/api/campaigns', $this->campaignPayload($lumina->id))->assertForbidden();
    }

    public function test_freelancer_budget_change_requires_owner_approval_and_applies_once(): void
    {
        $leila = User::where('email', 'freelancer@rawijli.com')->firstOrFail();
        $owner = User::where('email', 'owner@rawijli.com')->firstOrFail();
        $campaign = Campaign::where('name', 'Prospecting — Glow Serum Launch')->firstOrFail();
        Sanctum::actingAs($leila);

        $approval = $this->postJson('/api/campaigns/'.$campaign->id.'/budget', ['dailyBudget' => 240, 'reason' => 'ROAS is above target.'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data');

        Sanctum::actingAs($owner);
        $this->patchJson('/api/approvals/'.$approval['id'], ['decision' => 'approved'])->assertOk();
        $this->assertSame(240.0, $campaign->fresh()->daily_budget);

        $this->patchJson('/api/approvals/'.$approval['id'], ['decision' => 'approved'])->assertStatus(409);
        $this->assertSame(240.0, $campaign->fresh()->daily_budget);
    }

    public function test_freelancer_schedule_enters_approval_queue_and_owner_can_schedule_it(): void
    {
        $leila = User::where('email', 'freelancer@rawijli.com')->firstOrFail();
        $owner = User::where('email', 'owner@rawijli.com')->firstOrFail();
        $lumina = Workspace::where('name', 'Lumina Skincare')->firstOrFail();
        Sanctum::actingAs($leila);

        $item = $this->postJson('/api/calendar', [
            'workspaceId' => $lumina->id,
            'date' => now()->addDays(3)->toDateString(),
            'time' => '18:30',
            'channel' => 'instagram',
            'kind' => 'organic',
            'title' => 'Approval queue smoke post',
            'caption' => 'A scheduled post that needs owner approval.',
        ])->assertCreated()->assertJsonPath('data.status', 'pending_approval')->json('data');

        $approval = Approval::where('payload->itemId', $item['id'])->firstOrFail();
        Sanctum::actingAs($owner);
        $this->patchJson('/api/approvals/'.$approval->id, ['decision' => 'approved'])->assertOk();

        $this->assertSame('scheduled', CalendarItem::findOrFail($item['id'])->status);
        $this->assertSame('approved', $approval->fresh()->status);
    }

    public function test_analytics_aggregates_totals_and_previous_period(): void
    {
        $owner = User::where('email', 'owner@rawijli.com')->firstOrFail();
        $lumina = Workspace::where('name', 'Lumina Skincare')->firstOrFail();
        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/workspaces/'.$lumina->id.'/analytics?from='.now()->subDays(13)->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('previous', $response['kpis']);
        $this->assertArrayHasKey('series', $response);
        $this->assertCount(14, $response['series']);
        $this->assertGreaterThan(0, $response['kpis']['spend']);
        $this->assertEqualsWithDelta($response['kpis']['revenue'] / $response['kpis']['spend'], $response['kpis']['roas'], 0.02);
    }

    public function test_public_directory_only_lists_approved_public_freelancers(): void
    {
        $response = $this->getJson('/api/public/freelancers?skill=media_buying&sort=rating')->assertOk();
        $emails = collect($response->json('data'))->pluck('userId');

        $approved = FreelancerProfile::where('vetting', 'approved')->where('public_visible', true)->pluck('user_id');
        $this->assertTrue(collect($emails)->every(fn ($id) => $approved->contains($id)));
        $this->assertFalse(collect($emails)->contains(User::where('email', 'owen@rawijli.com')->value('id')));
    }

    public function test_copilot_enforces_workspace_credit_limit(): void
    {
        $owner = User::where('email', 'owner@rawijli.com')->firstOrFail();
        $owner->update(['ai_credits_limit' => $owner->ai_credits_used]);
        $lumina = Workspace::where('name', 'Lumina Skincare')->firstOrFail();
        Sanctum::actingAs($owner);

        $this->postJson('/api/copilot/ask', ['workspaceId' => $lumina->id, 'content' => 'Where is the waste?'])
            ->assertStatus(429);
    }

    public function test_freelancer_can_switch_between_two_authorized_client_workspaces(): void
    {
        $leila = User::where('email', 'freelancer@rawijli.com')->firstOrFail();
        Sanctum::actingAs($leila);

        $clients = $this->getJson('/api/workspaces/clients')->assertOk()->json('data');
        $names = collect($clients)->pluck('name')->all();

        $this->assertContains('Lumina Skincare', $names);
        $this->assertContains('NorthPeak Fitness', $names);
    }

    private function campaignPayload(string $workspaceId): array
    {
        return [
            'workspaceId' => $workspaceId, 'name' => 'Sandbox test', 'platform' => 'meta', 'objective' => 'Purchases',
            'dailyBudget' => 50, 'adSets' => [['name' => 'Test set', 'targeting' => 'US 18+', 'dailyBudget' => 50, 'ads' => [['name' => 'Test ad', 'format' => 'image', 'headline' => 'Test', 'primaryText' => 'Test']]]],
        ];
    }
}
