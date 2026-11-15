<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\CalendarItem;
use App\Models\Campaign;
use App\Models\ChatMessage;
use App\Models\Connection;
use App\Models\Contract;
use App\Models\Creative;
use App\Models\Deliverable;
use App\Models\DirectMessage;
use App\Models\Dispute;
use App\Models\FreelancerProfile;
use App\Models\Grant;
use App\Models\Invitation;
use App\Models\JobBrief;
use App\Models\MetricPoint;
use App\Models\Milestone;
use App\Models\Notification;
use App\Models\Package;
use App\Models\PortfolioItem;
use App\Models\Proposal;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\Thread;
use App\Models\ThreadParticipant;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    private array $tables = [
        'reports', 'audit_logs', 'notifications', 'dispute_events', 'disputes', 'transactions', 'subscriptions',
        'deliverable_comments', 'deliverable_versions', 'deliverables', 'direct_messages', 'thread_participants',
        'threads', 'chat_messages', 'publishing_attempts', 'calendar_items', 'approvals', 'grants', 'milestones',
        'contracts', 'invitations', 'proposals', 'job_briefs', 'metric_points', 'ads', 'ad_sets', 'campaigns',
        'creatives', 'portfolio_items', 'packages', 'freelancer_profiles', 'connections', 'workspaces',
        'personal_access_tokens', 'users',
    ];

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ($this->tables as $table) {
            DB::table($table)->delete();
        }
        Schema::enableForeignKeyConstraints();

        $admin = $this->user('11111111-1111-4111-8111-111111111101', 'Priya Raman', 'admin@rawijli.com', 'admin', 'active', 'Platform Ops', null, null, 0, 0);
        $amelia = $this->user('11111111-1111-4111-8111-111111111102', 'Amelia Chen', 'owner@rawijli.com', 'owner', 'active', 'Founder', 'Lumina Skincare', 'growth', 184, 600);
        $marcus = $this->user('11111111-1111-4111-8111-111111111103', 'Marcus Hale', 'marcus@northpeak.fit', 'owner', 'active', 'CMO', 'NorthPeak Fitness', 'scale', 410, 2000);
        $newOwner = $this->user('11111111-1111-4111-8111-111111111104', 'Noah Bennett', 'noah@newbrand.test', 'owner', 'active', 'Owner', 'New Brand Co', 'starter', 0, 200);
        $leila = $this->user('11111111-1111-4111-8111-111111111111', 'Leila Okonkwo', 'freelancer@rawijli.com', 'freelancer', 'active', 'Meta Media Buyer', null, null, 12, 100);
        $jonas = $this->user('11111111-1111-4111-8111-111111111112', 'Jonas Park', 'jonas@rawijli.com', 'freelancer', 'active', 'TikTok Creative Strategist', null, null, 8, 100);
        $sofia = $this->user('11111111-1111-4111-8111-111111111113', 'Sofia Alvarez', 'sofia@rawijli.com', 'freelancer', 'active', 'Motion Designer', null, null, 4, 100);
        $maya = $this->user('11111111-1111-4111-8111-111111111114', 'Maya Trent', 'maya@rawijli.com', 'freelancer', 'active', 'Brand Designer', null, null, 0, 100);
        $ravi = $this->user('11111111-1111-4111-8111-111111111115', 'Ravi Kapoor', 'ravi@rawijli.com', 'freelancer', 'active', 'Performance Media Buyer', null, null, 0, 100);
        $elena = $this->user('11111111-1111-4111-8111-111111111116', 'Elena Voss', 'elena@rawijli.com', 'freelancer', 'active', 'UGC Video Editor', null, null, 0, 100);
        $chris = $this->user('11111111-1111-4111-8111-111111111117', 'Chris Ndiaye', 'chris@rawijli.com', 'freelancer', 'active', 'Full-funnel Ads Lead', null, null, 0, 100);
        $hana = $this->user('11111111-1111-4111-8111-111111111118', 'Hana Ito', 'hana@rawijli.com', 'freelancer', 'active', 'Product Photographer', null, null, 0, 100);
        $owen = $this->user('11111111-1111-4111-8111-111111111119', 'Owen Blake', 'owen@rawijli.com', 'freelancer', 'pending', 'Junior Media Buyer', null, null, 0, 100);

        $lumina = Workspace::create(['id' => '22222222-2222-4222-8222-222222222201', 'owner_id' => $amelia->id, 'name' => 'Lumina Skincare', 'industry' => 'Beauty / DTC', 'timezone' => 'America/Los_Angeles', 'currency' => 'USD']);
        $north = Workspace::create(['id' => '22222222-2222-4222-8222-222222222202', 'owner_id' => $marcus->id, 'name' => 'NorthPeak Fitness', 'industry' => 'Fitness app', 'timezone' => 'America/New_York', 'currency' => 'USD']);
        $newWs = Workspace::create(['id' => '22222222-2222-4222-8222-222222222203', 'owner_id' => $newOwner->id, 'name' => 'New Brand Co', 'industry' => 'Other', 'timezone' => 'UTC', 'currency' => 'USD']);

        $this->subscription($lumina->id, 'growth', 'active', 149);
        $this->subscription($north->id, 'scale', 'active', 399);
        $this->subscription($newWs->id, 'starter', 'trialing', 49);

        $profiles = [
            'leila' => $this->freelancer($leila, '33333333-3333-4333-8333-333333333301', 'Meta media buyer for DTC beauty — 4.2x blended ROAS last 12 months', 'I rebuild messy ad accounts: kill zombie campaigns, restack TOF/MOF/BOF, and only scale winners past $200/day. I work inside your Rawijli workspace — no Business Manager circus.', ['media_buying'], ['meta'], 95, 4.9, 38, 52, 'Lagos / remote', true, 'approved', ['Meta certified', 'DTC beauty', 'Top 2%']),
            'jonas' => $this->freelancer($jonas, '33333333-3333-4333-8333-333333333302', 'TikTok Spark + UGC iteration. I ship 12 variants a week.', 'Hooks, first 1.5 seconds, and Spark seeding with creators you already have. I edit in CapCut/Premiere and plug winners into Spark Ads.', ['video_editing', 'media_buying'], ['tiktok'], 80, 4.8, 29, 41, 'Seoul / remote', true, 'approved', ['TikTok Shop', 'UGC systems']),
            'sofia' => $this->freelancer($sofia, '33333333-3333-4333-8333-333333333303', 'Motion + statics that look like your brand, not a template pack.', 'I design ad systems: 9:16, 1:1, 4:5, with type that actually reads on mobile. 48h first round on retainers.', ['graphic_design', 'video_editing'], ['meta', 'tiktok'], 70, 5, 22, 33, 'Mexico City', true, 'approved', ['Brand systems', 'Fast turnaround']),
            'maya' => $this->freelancer($maya, '33333333-3333-4333-8333-333333333304', 'Packaging-aware brand designer for CPG.', 'I keep ads on-brand so paid does not fight your PDP.', ['graphic_design'], ['meta'], 85, 4.7, 18, 27, 'Austin', true, 'approved', ['Packaging']),
            'ravi' => $this->freelancer($ravi, '33333333-3333-4333-8333-333333333305', 'Full-funnel Meta + TikTok buyer for apps & info.', 'I obsess over MER, not vanity ROAS. Weekly creative sprints with your editors.', ['media_buying', 'copywriting'], ['meta', 'tiktok'], 110, 4.85, 44, 61, 'London', true, 'approved', ['Scale >$50k/mo']),
            'elena' => $this->freelancer($elena, '33333333-3333-4333-8333-333333333306', 'UGC editor who actually watches retention graphs.', 'Captions, pattern interrupts, and 7-second payoff cuts.', ['video_editing'], ['tiktok', 'meta'], 60, 4.6, 15, 24, 'Berlin', true, 'approved', ['Retention edits']),
            'chris' => $this->freelancer($chris, '33333333-3333-4333-8333-333333333307', 'Ads lead who can brief designers and buyers.', 'Fractional CMO energy without the slide theater.', ['media_buying', 'copywriting', 'graphic_design'], ['meta', 'tiktok'], 130, 4.95, 19, 21, 'Dakar / Paris', true, 'approved', ['Fractional CMO']),
            'hana' => $this->freelancer($hana, '33333333-3333-4333-8333-333333333308', 'Still-life product photography that converts on mobile.', 'I shoot for ads, not lookbooks. Crop-safe compositions.', ['graphic_design'], ['meta', 'tiktok'], 75, 4.8, 12, 16, 'Osaka', false, 'approved', ['Still life']),
            'owen' => $this->freelancer($owen, '33333333-3333-4333-8333-333333333309', 'Junior Meta buyer looking for first retainers.', 'I learned inside a $12k/day ecommerce desk. Ready for smaller brands.', ['media_buying'], ['meta'], 45, 0, 0, 0, 'Toronto', false, 'pending', []),
        ];

        $this->packages($profiles['leila']->id, [['Audit sprint', 650, '48h account teardown + 14-day test plan'], ['Monthly buying', 2800, 'Daily optimizations, weekly report, 3 new tests']]);
        $this->packages($profiles['jonas']->id, [['12-cut week', 1200, '12 edited UGC cuts + 3 Spark-ready masters']]);
        $this->packages($profiles['sofia']->id, [['Creative system', 1800, '20 statics + 6 motion loops']]);
        $this->packages($profiles['maya']->id, [['Ad kit', 1400, '16 statics from existing brand kit']]);
        $this->packages($profiles['ravi']->id, [['Scale desk', 4500, 'Hands-on buying + creative brief factory']]);
        $this->packages($profiles['elena']->id, [['Batch of 10', 900, '10 raw-to-final UGC edits']]);
        $this->packages($profiles['chris']->id, [['90-day rebuild', 9800, 'Structure, creative, hiring plan']]);
        $this->packages($profiles['hana']->id, [['Shoot day', 2200, '40 selects, retouched']]);
        $this->packages($profiles['owen']->id, [['Weekly ops', 900, 'Daily checks, 1 report']]);

        $portfolio = [
            'leila' => [['Serum launch — 3.6x', 'image', 280, 'Prospecting structure for a vitamin C launch'], ['Retarget stack', 'image', 330, 'ATC + viewers + IG engagers']],
            'jonas' => [['Routine Spark', 'video', 200, 'Bathroom UGC that held 2.5% CTR']],
            'sofia' => [['Texture close-ups', 'image', 18, 'Product stills with grain and glass']],
            'maya' => [['Kit refresh', 'image', 150, 'Type-led statics']],
            'ravi' => [['Challenge funnel', 'image', 240, 'Install to trial']],
            'elena' => [['Hook tests', 'video', 310, 'First-frame variants']],
            'chris' => [['Rebuild map', 'image', 265, 'Account architecture']],
            'hana' => [['Glass + serum', 'image', 40, 'Specular highlights']],
            'owen' => [['Sample structure', 'image', 200, 'CBO vs ABO']],
        ];
        foreach ($portfolio as $key => $items) {
            foreach ($items as $i => [$title, $kind, $hue, $caption]) {
                PortfolioItem::create(['freelancer_profile_id' => $profiles[$key]->id, 'title' => $title, 'kind' => $kind, 'hue' => $hue, 'caption' => $caption, 'sort_order' => $i]);
            }
        }

        $this->connections($lumina, $north);
        $campaigns = $this->campaigns($lumina, $north, $amelia, $marcus, $profiles);
        $this->creatives($lumina, $amelia, $leila);
        $this->calendar($lumina, $amelia, $leila);
        $this->jobs($lumina, $north, $amelia, $marcus, $profiles);
        $contracts = $this->contracts($lumina, $north, $amelia, $marcus, $profiles);
        $this->grants($lumina, $north, $contracts, $profiles);
        $this->approvals($lumina, $profiles, $campaigns);
        $this->threads($lumina, $north, $amelia, $leila, $profiles);
        $this->deliverables($contracts, $profiles);
        $this->disputes($contracts, $marcus);
        $this->notifications($amelia, $leila, $marcus);
        $this->chat($lumina, $amelia);
        $this->report($lumina, $amelia);
        $this->audit($admin, $amelia, $lumina, $profiles['owen']);
    }

    private function user(string $id, string $name, string $email, string $role, string $status, ?string $title = null, ?string $company = null, ?string $plan = null, int $used = 0, int $limit = 200): User
    {
        return User::create([
            'id' => $id, 'name' => $name, 'email' => $email, 'password' => 'password', 'role' => $role,
            'status' => $status, 'title' => $title, 'company' => $company, 'plan' => $plan,
            'avatar' => 'https://api.dicebear.com/7.x/notionists/svg?seed='.rawurlencode($name),
            'ai_credits_used' => $used, 'ai_credits_limit' => $limit, 'email_verified_at' => now(),
        ]);
    }

    private function subscription(string $workspaceId, string $plan, string $status, float $amount): void
    {
        Subscription::create(['workspace_id' => $workspaceId, 'plan' => $plan, 'status' => $status, 'amount' => $amount, 'current_period_ends_at' => now()->addDays(21)]);
        Transaction::create(['workspace_id' => $workspaceId, 'amount' => $amount, 'type' => 'subscription', 'status' => 'paid', 'provider_reference' => 'sandbox_subscription_'.$workspaceId, 'created_at' => now()->subDays(9)]);
    }

    private function freelancer(User $user, string $id, string $headline, string $bio, array $skills, array $platforms, float $rate, float $rating, int $reviews, int $jobs, string $location, bool $verified, string $vetting, array $badges): FreelancerProfile
    {
        return FreelancerProfile::create([
            'id' => $id, 'user_id' => $user->id, 'headline' => $headline, 'bio' => $bio, 'skills' => $skills,
            'platforms' => $platforms, 'hourly_rate' => $rate, 'rating' => $rating, 'reviews' => $reviews,
            'completed_jobs' => $jobs, 'location' => $location, 'availability' => 'Available', 'verified' => $verified,
            'vetting' => $vetting, 'public_visible' => $vetting === 'approved', 'badges' => $badges,
        ]);
    }

    private function packages(string $profileId, array $rows): void
    {
        foreach ($rows as [$name, $price, $detail]) {
            Package::create(['freelancer_profile_id' => $profileId, 'name' => $name, 'price' => $price, 'detail' => $detail]);
        }
    }

    private function connections(Workspace $lumina, Workspace $north): void
    {
        foreach ([
            ['44444444-4444-4444-8444-444444444401', $lumina->id, 'meta', 'page', 'Lumina Skincare', 'sandbox_meta_page', 'connected', 0],
            ['44444444-4444-4444-8444-444444444402', $lumina->id, 'meta', 'profile', '@luminaskin', 'sandbox_meta_profile', 'connected', 0],
            ['44444444-4444-4444-8444-444444444403', $lumina->id, 'meta', 'ad_account', 'Lumina Ads · ACT-20491', 'sandbox_meta_ad_account', 'connected', 8640],
            ['44444444-4444-4444-8444-444444444404', $lumina->id, 'tiktok', 'tiktok_account', '@lumina.skin', 'sandbox_tiktok_account', 'needs_reauth', 1290],
            ['44444444-4444-4444-8444-444444444405', $north->id, 'meta', 'ad_account', 'NorthPeak · ACT-771', 'sandbox_meta_ad_account', 'connected', 8420],
        ] as [$id, $ws, $platform, $kind, $name, $external, $status, $spend]) {
            Connection::create([
                'id' => $id, 'workspace_id' => $ws, 'platform' => $platform, 'kind' => $kind, 'name' => $name,
                'external_id' => $external, 'status' => $status, 'last_sync_at' => now()->subHours($status === 'needs_reauth' ? 144 : 1),
                'spend_30d' => $spend, 'scopes' => [$platform === 'tiktok' ? 'video.publish' : 'read_insights', 'publish_content'],
                'sync_history' => [['status' => $status, 'at' => now()->subHours(1)->toISOString(), 'driver' => 'sandbox']],
            ]);
        }
    }

    private function campaigns(Workspace $lumina, Workspace $north, User $amelia, User $marcus, array $profiles): array
    {
        $definition = [
            ['c_prospect' => ['55555555-5555-4555-8555-555555555501', $lumina, 'meta', 'Prospecting — Glow Serum Launch', 'Purchases', 'active', 180, 'owner', $amelia->id, 14, 4120, 14832, 910400, 18420, 312]],
            ['c_retarget' => ['55555555-5555-4555-8555-555555555502', $lumina, 'meta', 'Retargeting — 14d Viewers', 'Purchases', 'active', 90, 'freelancer', $profiles['leila']->user_id, 17, 1880, 8460, 240120, 9210, 198]],
            ['c_waste' => ['55555555-5555-4555-8555-555555555503', $lumina, 'meta', 'Broad Awareness — Brand Film', 'Reach', 'active', 120, 'owner', $amelia->id, 6, 2640, 792, 1840000, 4100, 11]],
            ['c_tiktok' => ['55555555-5555-4555-8555-555555555504', $lumina, 'tiktok', 'Spark Ads — UGC Routine', 'Conversions', 'active', 75, 'ai', null, 9, 1290, 3483, 620000, 15400, 87]],
            ['c_draft' => ['55555555-5555-4555-8555-555555555505', $lumina, 'meta', 'Lookalike 2% — Serum Buyers', 'Purchases', 'pending_approval', 95, 'freelancer', $profiles['leila']->user_id, 7, 0, 0, 0, 0, 0]],
            ['c_north' => ['55555555-5555-4555-8555-555555555506', $north, 'meta', 'App Installs — 8-week Challenge', 'App installs', 'active', 320, 'owner', $marcus->id, 10, 8420, 20100, 2100000, 41000, 1840]],
            ['c_north_draft' => ['55555555-5555-4555-8555-555555555507', $north, 'tiktok', 'TikTok Creative Test — Summer', 'Conversions', 'draft', 60, 'owner', $marcus->id, 5, 0, 0, 0, 0, 0]],
        ];
        $history = [
            'c_prospect' => [5, 160, 3.7, 2.0], 'c_retarget' => [6, 70, 4.6, 3.9], 'c_waste' => [6, 90, 0.3, 0.2],
            'c_tiktok' => [5, 60, 2.7, 2.5], 'c_draft' => [0, 0, 0, 0], 'c_north' => [6, 290, 2.4, 1.9], 'c_north_draft' => [0, 0, 0, 0],
        ];
        $campaigns = [];
        $today = now()->startOfDay();
        foreach ($definition as $entry) {
            [$key, $row] = [array_key_first($entry), array_values($entry)[0]];
            [$id, $workspace, $platform, $name, $objective, $status, $budget, $role, $creatorId, $seed, $spend, $revenue, $impressions, $clicks, $conversions] = $row;
            $campaign = Campaign::create([
                'id' => $id, 'workspace_id' => $workspace->id, 'connection_id' => $this->connectionFor($workspace, $platform),
                'created_by_id' => $creatorId, 'created_by_role' => $role, 'platform' => $platform, 'name' => $name,
                'objective' => $objective, 'status' => $status, 'daily_budget' => $budget,
                'lifetime_budget' => $budget * 30, 'starts_at' => $today->copy()->subDays(30)->toDateString(),
                'ends_at' => $today->copy()->addDays(30)->toDateString(),
                'spend' => $spend, 'revenue' => $revenue, 'impressions' => $impressions, 'clicks' => $clicks, 'conversions' => $conversions,
            ]);
            $campaigns[$key] = $campaign;

            if ($status !== 'draft' && $status !== 'pending_approval' && $spend > 0) {
                [$baseSpend, $baseRoas, $baseCtr] = $history[$key];
                for ($i = 59; $i >= 0; $i--) {
                    $date = $today->copy()->subDays($i);
                    $wave = 1 + (sin(($i + $seed) / 3.4) * 0.18) + (($seed % 5) / 100);
                    $daySpend = round($baseSpend * $wave, 2);
                    $dayRevenue = round($daySpend * ($baseRoas + (cos(($i + $seed) / 5) * 0.15)), 2);
                    $dayImpressions = (int) round($daySpend * 210);
                    $dayClicks = (int) round($dayImpressions * ($baseCtr + (sin($i / 4) * 0.08)) / 100);
                    MetricPoint::create([
                        'workspace_id' => $workspace->id, 'campaign_id' => $campaign->id, 'date' => $date->toDateString(),
                        'spend' => $daySpend, 'revenue' => $dayRevenue, 'impressions' => $dayImpressions,
                        'clicks' => $dayClicks, 'conversions' => (int) round($dayRevenue / 48),
                    ]);
                }
            }

            $this->hierarchy($campaign, $key, $role === 'freelancer' ? $profiles['leila'] : null);
        }
        return $campaigns;
    }

    private function connectionFor(Workspace $workspace, string $platform): ?string
    {
        $connection = Connection::where('workspace_id', $workspace->id)->where('platform', $platform)->where('kind', $platform === 'tiktok' ? 'tiktok_account' : 'ad_account')->first();
        return $connection?->id;
    }

    private function hierarchy(Campaign $campaign, string $key, ?FreelancerProfile $leila): void
    {
        $sets = [
            'c_prospect' => [['as1', 'Women 25–44 · US/UK · Interest: Skincare', 'Women 25-44, US+UK, skincare interests', 110], ['as2', 'Lookalike 1% purchasers', 'LAL 1% purchasers, 18+', 70]],
            'c_retarget' => [['as3', 'ATC 7d + IG engagers', 'Add-to-cart 7d, IG 14d', 90]],
            'c_waste' => [['as4', 'US 18+ broad', 'US 18+', 120]],
            'c_tiktok' => [['as5', 'Spark · creators 18–34', 'Spark ads, 18-34', 75]],
            'c_draft' => [['as6', 'LAL 2% purchasers', 'Lookalike 2% of 180-day purchasers', 95]],
            'c_north' => [['as7', 'US 18–44 · fitness interests', 'US 18-44, fitness app installers', 320]],
            'c_north_draft' => [['as8', 'TikTok test audience', 'US 18-34 broad', 60]],
        ][$key] ?? [];
        foreach ($sets as $setIndex => [$setKey, $name, $targeting, $budget]) {
            $setNumber = array_search($setKey, ['as1', 'as2', 'as3', 'as4', 'as5', 'as6', 'as7', 'as8']) + 1;
            $set = AdSet::create([
                'id' => '66666666-6666-4666-8666-'.str_pad((string) $setNumber, 12, '0', STR_PAD_LEFT),
                'campaign_id' => $campaign->id, 'name' => $name, 'targeting' => $targeting,
                'daily_budget' => $budget, 'status' => $campaign->status,
                'spend' => $campaign->status === 'active' ? round($campaign->spend / max(1, count($sets)), 2) : 0,
                'revenue' => $campaign->status === 'active' ? round($campaign->revenue / max(1, count($sets)), 2) : 0,
                'impressions' => $campaign->status === 'active' ? intdiv((int) $campaign->impressions, max(1, count($sets))) : 0,
                'clicks' => $campaign->status === 'active' ? intdiv((int) $campaign->clicks, max(1, count($sets))) : 0,
                'conversions' => $campaign->status === 'active' ? intdiv((int) $campaign->conversions, max(1, count($sets))) : 0,
            ]);
            $ads = match ($setKey) {
                'as1' => [['Serum droplet — studio', 'image', 'Glass skin in 12 nights', 'Vitamin C + peptides. 4.8★ from 2,140 reviews.'], ['Before/after carousel', 'carousel', 'See the 14-day difference', 'No filters. Real customers. Real texture.']],
                'as3' => [['Offer — 15% returning', 'image', 'Your cart missed this', 'Finish checkout. Free shipping tonight.']],
                'as4' => [['Brand film 30s', 'video', 'Lumina, for the long game', 'Slow beauty. Fast results? Not our story.']],
                'as5' => [['UGC bathroom routine', 'video', 'Night 1 vs Night 12', 'I swapped my $86 toner. Here’s the texture.']],
                'as6' => [['Lookalike static', 'image', 'Twice the glow, same routine', 'A proven winner for purchasers who already trust you.']],
                default => [['Primary creative', 'image', $campaign->name, 'Sandbox test creative attached to this campaign.']],
            };
            foreach ($ads as $index => [$adName, $format, $headline, $primary]) {
                Ad::create([
                    'id' => '77777777-7777-4777-8777-'.str_pad((string) ($setNumber * 10 + $index + 1), 12, '0', STR_PAD_LEFT),
                    'ad_set_id' => $set->id, 'name' => $adName, 'format' => $format, 'headline' => $headline,
                    'primary_text' => $primary, 'destination_url' => 'https://example.test/'.$campaign->id,
                    'status' => $set->status,
                    'spend' => $set->status === 'active' ? round($set->spend / count($ads), 2) : 0,
                    'revenue' => $set->status === 'active' ? round($set->revenue / count($ads), 2) : 0,
                    'impressions' => $set->status === 'active' ? intdiv((int) $set->impressions, count($ads)) : 0,
                    'clicks' => $set->status === 'active' ? intdiv((int) $set->clicks, count($ads)) : 0,
                    'conversions' => $set->status === 'active' ? intdiv((int) $set->conversions, count($ads)) : 0,
                ]);
            }
        }
    }

    private function creatives(Workspace $lumina, User $amelia, User $leila): void
    {
        foreach ([
            ['ffffffff-ffff-4fff-8fff-fffffffff001', 'Glass skin still', '12 nights. Visible texture change.', 'Glass skin in 12 nights', 300, 'instagram', '4:5', $amelia->id],
            ['ffffffff-ffff-4fff-8fff-fffffffff002', 'Cart nudge', 'Your glow is still in the bag.', 'Still thinking about it?', 330, 'facebook', '1:1', $leila->id],
            ['ffffffff-ffff-4fff-8fff-fffffffff003', 'TikTok night routine', 'Night 1 vs Night 12. No filter.', 'Texture in twelve nights', 200, 'tiktok', '9:16', $leila->id],
        ] as [$id, $title, $caption, $headline, $hue, $platform, $ratio, $creator]) {
            Creative::create(['id' => $id, 'workspace_id' => $lumina->id, 'created_by_id' => $creator, 'title' => $title, 'caption' => $caption, 'headline' => $headline, 'hue' => $hue, 'platform' => $platform, 'aspect_ratio' => $ratio, 'kind' => 'generated', 'processing_status' => 'ready']);
        }
    }

    private function calendar(Workspace $lumina, User $amelia, User $leila): void
    {
        $today = now()->startOfDay();
        foreach ([
            ['eeeeeeee-eeee-4eee-8eee-eeeeeeeeee01', $today, '10:00', 'instagram', 'organic', 'Texture reel — droplet', 'The serum that doesn’t pill under SPF.', 'scheduled', $amelia->id],
            ['eeeeeeee-eeee-4eee-8eee-eeeeeeeeee02', $today->copy()->addDay(), '18:00', 'tiktok', 'organic', 'Night 12 UGC', 'Night 1 vs 12. No filter.', 'draft', $amelia->id],
            ['eeeeeeee-eeee-4eee-8eee-eeeeeeeeee03', $today->copy()->addDays(2), '09:00', 'facebook', 'ad', 'Retarget — cart 15%', 'Finish what you started. Free shipping.', 'scheduled', $leila->id],
            ['eeeeeeee-eeee-4eee-8eee-eeeeeeeeee04', $today->copy()->addDays(4), '12:00', 'instagram', 'organic', 'Ingredient carousel', 'Niacinamide meets peptides. Here’s why.', 'published', $leila->id],
            ['eeeeeeee-eeee-4eee-8eee-eeeeeeeeee05', $today->copy()->subDay(), '08:00', 'tiktok', 'organic', 'Spark hook test', 'First frame test for the weekend push.', 'failed', $leila->id],
            ['eeeeeeee-eeee-4eee-8eee-eeeeeeeeee06', $today->copy()->addDays(5), '16:00', 'instagram', 'organic', 'Pending approval reel', 'Waiting for owner approval before scheduling.', 'pending_approval', $leila->id],
        ] as [$id, $date, $time, $channel, $kind, $title, $caption, $status, $creator]) {
            CalendarItem::create([
                'id' => $id, 'workspace_id' => $lumina->id, 'date' => $date->toDateString(), 'time' => $time,
                'timezone' => $lumina->timezone, 'channel' => $channel, 'kind' => $kind, 'title' => $title,
                'caption' => $caption, 'status' => $status, 'created_by_id' => $creator,
                'published_at' => $status === 'published' ? now()->subDay() : null,
                'last_error' => $status === 'failed' ? 'Sandbox connection needs re-authentication.' : null,
            ]);
        }
    }

    private function jobs(Workspace $lumina, Workspace $north, User $amelia, User $marcus, array $profiles): void
    {
        $hired = JobBrief::create(['id' => '88888888-8888-4888-8888-888888888801', 'workspace_id' => $lumina->id, 'owner_id' => $amelia->id, 'title' => 'Meta buyer to kill wasted spend', 'description' => 'We have a $120/day awareness campaign dragging blended ROAS. Need a buyer who can rebuild prospecting and keep retargeting intact.', 'skills' => ['media_buying'], 'platforms' => ['meta'], 'budget' => 2800, 'duration_days' => 30, 'status' => 'hired', 'ai_generated' => true]);
        $ugc = JobBrief::create(['id' => '88888888-8888-4888-8888-888888888802', 'workspace_id' => $lumina->id, 'owner_id' => $amelia->id, 'title' => '12 TikTok UGC cuts for serum routine', 'description' => 'Raw creator footage is in the folder. Need hooks in the first 1.5s and captions burned in.', 'skills' => ['video_editing'], 'platforms' => ['tiktok'], 'budget' => 1200, 'duration_days' => 5, 'status' => 'open', 'ai_generated' => true]);
        $northJob = JobBrief::create(['id' => '88888888-8888-4888-8888-888888888803', 'workspace_id' => $north->id, 'owner_id' => $marcus->id, 'title' => 'Creative system for 8-week challenge', 'description' => 'Need 20 statics + 6 motion loops matching our charcoal/volt palette.', 'skills' => ['graphic_design'], 'platforms' => ['meta', 'tiktok'], 'budget' => 1800, 'duration_days' => 14, 'status' => 'open', 'ai_generated' => false]);

        Proposal::create(['id' => '99999999-9999-4999-8999-999999999901', 'job_id' => $ugc->id, 'freelancer_id' => $profiles['jonas']->id, 'cover' => 'I will cut 12 Spark-ready versions from your raw, with 3 hook families. Turnaround 5 days.', 'bid' => 1200, 'days' => 5, 'status' => 'sent']);
        Proposal::create(['id' => '99999999-9999-4999-8999-999999999902', 'job_id' => $ugc->id, 'freelancer_id' => $profiles['elena']->id, 'cover' => 'Retention-first edits. I will deliver a hook test matrix with the files.', 'bid' => 980, 'days' => 6, 'status' => 'sent']);
        Proposal::create(['id' => '99999999-9999-4999-8999-999999999903', 'job_id' => $hired->id, 'freelancer_id' => $profiles['leila']->id, 'cover' => 'I already audited the account. Kill the brand film, restack retargeting, and rebuild prospecting around the lookalike draft.', 'bid' => 2800, 'days' => 30, 'status' => 'accepted']);
        Proposal::create(['id' => '99999999-9999-4999-8999-999999999904', 'job_id' => $northJob->id, 'freelancer_id' => $profiles['sofia']->id, 'cover' => 'I can deliver the full system in two rounds with editable source files.', 'bid' => 1750, 'days' => 12, 'status' => 'sent']);

        Invitation::create(['id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa01', 'workspace_id' => $lumina->id, 'freelancer_id' => $profiles['jonas']->id, 'job_id' => $ugc->id, 'reason' => 'TikTok Spark CTR is healthy but volume is thin. Rawijli recommends a UGC editor.', 'status' => 'open']);
        Invitation::create(['id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa02', 'workspace_id' => $lumina->id, 'freelancer_id' => $profiles['elena']->id, 'job_id' => $ugc->id, 'reason' => 'Backup editor with retention-focused edits and a 6-day turnaround.', 'status' => 'open']);
    }

    private function contracts(Workspace $lumina, Workspace $north, User $amelia, User $marcus, array $profiles): array
    {
        $monthly = Contract::create(['id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbb01', 'workspace_id' => $lumina->id, 'freelancer_id' => $profiles['leila']->id, 'job_id' => '88888888-8888-4888-8888-888888888801', 'title' => 'Monthly Meta buying — Lumina', 'amount' => 2800, 'status' => 'active', 'started_at' => now()->subDays(34), 'terms' => 'Monthly retainer. All campaign changes require owner approval.']);
        Milestone::create(['id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccc01', 'contract_id' => $monthly->id, 'title' => 'Account teardown', 'amount' => 650, 'due_date' => now()->subDays(20)->toDateString(), 'status' => 'paid']);
        Milestone::create(['id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccc02', 'contract_id' => $monthly->id, 'title' => 'Month 1 optimizations', 'amount' => 2150, 'due_date' => now()->addDays(4)->toDateString(), 'status' => 'submitted']);

        $northContract = Contract::create(['id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbb02', 'workspace_id' => $north->id, 'freelancer_id' => $profiles['leila']->id, 'title' => 'NorthPeak paid social audit', 'amount' => 1800, 'status' => 'active', 'started_at' => now()->subDays(12), 'terms' => 'Two-week audit with weekly check-ins.']);
        Milestone::create(['id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccc03', 'contract_id' => $northContract->id, 'title' => 'Audit delivery', 'amount' => 900, 'due_date' => now()->addDays(2)->toDateString(), 'status' => 'pending']);
        Milestone::create(['id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccc04', 'contract_id' => $northContract->id, 'title' => 'Optimization roadmap', 'amount' => 900, 'due_date' => now()->addDays(10)->toDateString(), 'status' => 'pending']);

        $completed = Contract::create(['id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbb03', 'workspace_id' => $lumina->id, 'freelancer_id' => $profiles['jonas']->id, 'title' => 'UGC hook sprint — completed', 'amount' => 1200, 'status' => 'completed', 'started_at' => now()->subDays(64), 'terms' => 'Paid in full after approved delivery.']);
        Milestone::create(['id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccc05', 'contract_id' => $completed->id, 'title' => 'Delivery', 'amount' => 1200, 'due_date' => now()->subDays(50)->toDateString(), 'status' => 'paid']);

        Transaction::create(['workspace_id' => $lumina->id, 'contract_id' => $monthly->id, 'amount' => 2800, 'type' => 'escrow_hold', 'status' => 'held', 'provider_reference' => 'sandbox_hold_'.$monthly->id, 'created_at' => now()->subDays(34)]);
        Transaction::create(['workspace_id' => $lumina->id, 'contract_id' => $monthly->id, 'amount' => 650, 'type' => 'escrow_release', 'status' => 'released', 'provider_reference' => 'sandbox_release_'.$monthly->id.'_1', 'created_at' => now()->subDays(20)]);
        Transaction::create(['workspace_id' => $north->id, 'contract_id' => $northContract->id, 'amount' => 1800, 'type' => 'escrow_hold', 'status' => 'held', 'provider_reference' => 'sandbox_hold_'.$northContract->id, 'created_at' => now()->subDays(12)]);
        Transaction::create(['workspace_id' => $lumina->id, 'contract_id' => $completed->id, 'amount' => 1200, 'type' => 'escrow_release', 'status' => 'released', 'provider_reference' => 'sandbox_release_'.$completed->id, 'created_at' => now()->subDays(50)]);

        return ['monthly' => $monthly, 'north' => $northContract, 'completed' => $completed];
    }

    private function grants(Workspace $lumina, Workspace $north, array $contracts, array $profiles): void
    {
        Grant::create(['id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddd01', 'workspace_id' => $lumina->id, 'freelancer_id' => $profiles['leila']->id, 'contract_id' => $contracts['monthly']->id, 'updated_by_id' => $lumina->owner_id, 'access_enabled' => true, 'permissions' => ['view_analytics' => true, 'draft_campaigns' => true, 'schedule_posts' => true, 'upload_creatives' => true, 'adjust_budgets' => true]]);
        Grant::create(['id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddd02', 'workspace_id' => $north->id, 'freelancer_id' => $profiles['leila']->id, 'contract_id' => $contracts['north']->id, 'updated_by_id' => $north->owner_id, 'access_enabled' => true, 'permissions' => ['view_analytics' => true, 'draft_campaigns' => false, 'schedule_posts' => false, 'upload_creatives' => false, 'adjust_budgets' => false]]);
        Grant::create(['id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddd03', 'workspace_id' => $lumina->id, 'freelancer_id' => $profiles['jonas']->id, 'contract_id' => $contracts['completed']->id, 'updated_by_id' => $lumina->owner_id, 'access_enabled' => false, 'permissions' => ['view_analytics' => false, 'draft_campaigns' => false, 'schedule_posts' => false, 'upload_creatives' => false, 'adjust_budgets' => false]]);
    }

    private function approvals(Workspace $lumina, array $profiles, array $campaigns): void
    {
        Approval::create([
            'id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeee11', 'workspace_id' => $lumina->id, 'freelancer_id' => $profiles['leila']->id,
            'created_by_id' => $profiles['leila']->user_id, 'type' => 'new_campaign', 'title' => 'Lookalike 2% — Serum Buyers',
            'summary' => 'Leila drafted a $95/day Meta purchases campaign targeting 2% lookalikes of 180-day buyers. No spend until you approve.',
            'after_payload' => ['workspaceId' => $lumina->id, 'connectionId' => $this->connectionFor($lumina, 'meta'), 'name' => 'Lookalike 2% — Serum Buyers', 'platform' => 'meta', 'objective' => 'Purchases', 'dailyBudget' => 95, 'lifetimeBudget' => 2850, 'startsAt' => now()->addDay()->toDateString(), 'endsAt' => now()->addDays(31)->toDateString(), 'publish' => true, 'adSets' => [['name' => 'LAL 2% purchasers', 'targeting' => 'Lookalike 2% of 180-day purchasers', 'dailyBudget' => 95, 'ads' => [['name' => 'Lookalike static', 'format' => 'image', 'headline' => 'Twice the glow, same routine', 'primaryText' => 'A proven winner for purchasers who already trust you.', 'destinationUrl' => 'https://example.test/lookalike']]]]],
            'payload' => ['campaignId' => $campaigns['c_draft']->id], 'status' => 'pending',
        ]);
        Approval::create([
            'id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeee12', 'workspace_id' => $lumina->id, 'freelancer_id' => $profiles['leila']->id,
            'created_by_id' => $profiles['leila']->user_id, 'type' => 'budget_change', 'title' => 'Raise prospecting daily budget $180 → $240',
            'summary' => 'ROAS 3.6x over 14 days. Leila wants +$60/day on Glow Serum Launch.',
            'before_payload' => ['dailyBudget' => 180], 'after_payload' => ['dailyBudget' => 240],
            'payload' => ['campaignId' => $campaigns['c_prospect']->id, 'from' => 180, 'to' => 240, 'reason' => 'ROAS 3.6x over 14 days.'], 'status' => 'pending',
        ]);
        Approval::create([
            'id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeee13', 'workspace_id' => $lumina->id, 'freelancer_id' => $profiles['leila']->id,
            'created_by_id' => $profiles['leila']->user_id, 'decided_by_id' => $lumina->owner_id, 'type' => 'schedule_post',
            'title' => 'Schedule: Ingredient carousel', 'summary' => 'Leila scheduled an Instagram organic post.',
            'after_payload' => ['workspaceId' => $lumina->id, 'date' => now()->addDays(4)->toDateString(), 'time' => '12:00', 'channel' => 'instagram', 'kind' => 'organic', 'title' => 'Ingredient carousel', 'caption' => 'Niacinamide meets peptides. Here’s why.'],
            'payload' => ['itemId' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeee04'], 'status' => 'approved', 'decided_at' => now()->subDays(3),
        ]);
        Approval::create([
            'id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeee14', 'workspace_id' => $lumina->id, 'freelancer_id' => $profiles['leila']->id,
            'created_by_id' => $profiles['leila']->user_id, 'decided_by_id' => $lumina->owner_id, 'type' => 'budget_change',
            'title' => 'Lower awareness budget to $60/day', 'summary' => 'Test reducing wasted spend on the brand film.',
            'before_payload' => ['dailyBudget' => 120], 'after_payload' => ['dailyBudget' => 60],
            'payload' => ['campaignId' => $campaigns['c_waste']->id, 'from' => 120, 'to' => 60, 'reason' => 'Cut waste.'], 'status' => 'rejected', 'decided_at' => now()->subDays(2),
        ]);
    }

    private function threads(Workspace $lumina, Workspace $north, User $amelia, User $leila, array $profiles): void
    {
        $thread = Thread::create(['id' => '12121212-1212-4212-8212-121212121201', 'workspace_id' => $lumina->id, 'job_id' => '88888888-8888-4888-8888-888888888801', 'contract_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbb01', 'last_message' => 'Drafted the lookalike campaign — sitting in your approval queue.', 'updated_at' => now()]);
        ThreadParticipant::create(['thread_id' => $thread->id, 'user_id' => $amelia->id]);
        ThreadParticipant::create(['thread_id' => $thread->id, 'user_id' => $leila->id]);
        $messages = [
            [$leila->id, 'I paused nothing yet. Awareness is the leak — draft lookalike is in your queue.', 2],
            [$amelia->id, 'I will review after standup. Do not touch daily budgets until I approve.', 1],
            [$leila->id, 'Drafted the lookalike campaign — sitting in your approval queue.', 0],
        ];
        foreach ($messages as $i => [$from, $body, $hours]) {
            DirectMessage::create(['id' => '13131313-1313-4313-8313-13131313130'.$i, 'thread_id' => $thread->id, 'from_id' => $from, 'body' => $body, 'created_at' => now()->subHours($hours + 1)]);
        }

        $northThread = Thread::create(['id' => '12121212-1212-4212-8212-121212121202', 'workspace_id' => $north->id, 'contract_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbb02', 'last_message' => 'Audit scope is clear — first milestone due in two days.', 'updated_at' => now()->subDay()]);
        ThreadParticipant::create(['thread_id' => $northThread->id, 'user_id' => $north->owner_id]);
        ThreadParticipant::create(['thread_id' => $northThread->id, 'user_id' => $leila->id]);
        DirectMessage::create(['id' => '13131313-1313-4313-8313-131313131310', 'thread_id' => $northThread->id, 'from_id' => $north->owner_id, 'body' => 'Audit scope is clear — first milestone due in two days.', 'created_at' => now()->subDay()]);

        $preHire = Thread::create(['id' => '12121212-1212-4212-8212-121212121203', 'workspace_id' => $lumina->id, 'job_id' => '88888888-8888-4888-8888-888888888802', 'last_message' => 'Can you share the raw footage folder?', 'updated_at' => now()->subHours(5)]);
        ThreadParticipant::create(['thread_id' => $preHire->id, 'user_id' => $amelia->id]);
        ThreadParticipant::create(['thread_id' => $preHire->id, 'user_id' => $profiles['jonas']->user_id]);
        DirectMessage::create(['id' => '13131313-1313-4313-8313-131313131311', 'thread_id' => $preHire->id, 'from_id' => $profiles['jonas']->user_id, 'body' => 'Can you share the raw footage folder?', 'created_at' => now()->subHours(5)]);
    }

    private function deliverables(array $contracts, array $profiles): void
    {
        $submitted = Deliverable::create(['id' => '14141414-1414-4414-8414-141414141401', 'contract_id' => $contracts['monthly']->id, 'workspace_id' => $contracts['monthly']->workspace_id, 'freelancer_id' => $profiles['leila']->id, 'title' => 'Week 4 performance memo', 'notes' => 'Kill brand film or cut to $20/day. Scale retargeting +$30. Lookalike draft ready.', 'status' => 'submitted', 'current_version' => 1, 'submitted_at' => now()->subDay()]);
        $submitted->versions()->create(['version' => 1, 'notes' => 'Initial memo with recommendations.', 'link' => 'https://example.test/reports/week-4', 'created_at' => now()->subDay()]);

        $revision = Deliverable::create(['id' => '14141414-1414-4414-8414-141414141402', 'contract_id' => $contracts['monthly']->id, 'workspace_id' => $contracts['monthly']->workspace_id, 'freelancer_id' => $profiles['leila']->id, 'title' => 'Creative test matrix', 'notes' => 'Second pass with retention benchmarks.', 'status' => 'revision', 'current_version' => 2, 'submitted_at' => now()->subHours(8)]);
        $revision->versions()->create(['version' => 1, 'notes' => 'Initial matrix.', 'link' => 'https://example.test/matrix/v1', 'created_at' => now()->subDays(3)]);
        $revision->versions()->create(['version' => 2, 'notes' => 'Added retention benchmarks and hook families.', 'link' => 'https://example.test/matrix/v2', 'created_at' => now()->subHours(8)]);
        \App\Models\DeliverableComment::create(['deliverable_id' => $revision->id, 'user_id' => $contracts['monthly']->workspace->owner_id, 'body' => 'Please add the 3-second retention benchmark for each hook family.', 'created_at' => now()->subHours(7)]);
    }

    private function disputes(array $contracts, User $marcus): void
    {
        $dispute = Dispute::create(['id' => '15151515-1515-4515-8515-151515151501', 'contract_id' => $contracts['north']->id, 'opened_by' => $marcus->id, 'reason' => 'Milestone 2 scope is unclear after the audit kickoff call.', 'status' => 'open']);
        $dispute->events()->create(['actor_id' => $marcus->id, 'status' => 'opened', 'comment' => 'Requesting a written scope confirmation before the next milestone.', 'created_at' => now()->subDay()]);
    }

    private function notifications(User $amelia, User $leila, User $marcus): void
    {
        foreach ([
            ['16161616-1616-4616-8616-161616161601', $amelia->id, 'Approval needed', 'Leila submitted Lookalike 2% — Serum Buyers', '/app/approvals', null],
            ['16161616-1616-4616-8616-161616161602', $amelia->id, 'TikTok needs re-auth', '@lumina.skin last synced 6 days ago', '/app/connections', null],
            ['16161616-1616-4616-8616-161616161603', $leila->id, 'Milestone submitted', 'Month 1 optimizations is awaiting owner review.', '/pro/contracts', null],
            ['16161616-1616-4616-8616-161616161604', $leila->id, 'Revision requested', 'Creative test matrix needs retention benchmarks.', '/pro/deliverables', null],
            ['16161616-1616-4616-8616-161616161605', $marcus->id, 'Dispute opened', 'NorthPeak paid social audit has an open scope question.', '/admin/disputes', null],
        ] as [$id, $userId, $title, $body, $href, $readAt]) {
            Notification::create(['id' => $id, 'user_id' => $userId, 'title' => $title, 'body' => $body, 'href' => $href, 'read_at' => $readAt, 'created_at' => now()->subHours(6)]);
        }
    }

    private function chat(Workspace $lumina, User $amelia): void
    {
        ChatMessage::create(['id' => '17171717-1717-4717-8717-171717171701', 'workspace_id' => $lumina->id, 'user_id' => $amelia->id, 'role' => 'assistant', 'content' => 'I am Rawijli Copilot for Lumina Skincare. I already pulled the last 14 days of Meta + TikTok. Ask me what is wasting money, or tell me to draft a 2-week plan.', 'cards' => [], 'created_at' => now()->subDays(2)]);
        ChatMessage::create(['id' => '17171717-1717-4717-8717-171717171702', 'workspace_id' => $lumina->id, 'user_id' => $amelia->id, 'role' => 'user', 'content' => 'Where is our wasted spend?', 'cards' => [], 'created_at' => now()->subDay()]);
        ChatMessage::create(['id' => '17171717-1717-4717-8717-171717171703', 'workspace_id' => $lumina->id, 'user_id' => $amelia->id, 'role' => 'assistant', 'content' => 'Broad Awareness — Brand Film is the weakest active campaign. It has spent $2,640 at 0.30x ROAS and 0.22% CTR. Cut it and test a smaller retargeting expansion before moving budget.', 'cards' => [['kind' => 'campaign', 'title' => 'Broad Awareness — Brand Film', 'body' => 'ROAS 0.3x · Spend $2,640 · CTR 0.22%', 'href' => '/app/ads'], ['kind' => 'freelancer', 'title' => 'Leila Okonkwo', 'body' => 'Meta media buyer already authorized on this workspace.', 'freelancerId' => '33333333-3333-4333-8333-333333333301', 'href' => '/app/marketplace/33333333-3333-4333-8333-333333333301']], 'created_at' => now()->subDay()]);
    }

    private function report(Workspace $lumina, User $amelia): void
    {
        Report::create([
            'id' => '18181818-1818-4818-8818-181818181801', 'workspace_id' => $lumina->id, 'user_id' => $amelia->id,
            'date_from' => now()->subDays(13)->toDateString(), 'date_to' => now()->toDateString(), 'status' => 'ready',
            'payload' => ['format' => 'csv', 'workspace' => $lumina->name, 'analytics' => ['campaigns' => [], 'kpis' => ['spend' => 0, 'revenue' => 0, 'conversions' => 0, 'roas' => 0, 'ctr' => 0, 'cpc' => 0, 'cpm' => 0, 'impressions' => 0, 'clicks' => 0], 'series' => []]],
        ]);
    }

    private function audit(User $admin, User $owner, Workspace $workspace, FreelancerProfile $owen): void
    {
        AuditLog::create(['actor_id' => $admin->id, 'workspace_id' => $workspace->id, 'action' => 'freelancer.vetting_reviewed', 'subject_type' => FreelancerProfile::class, 'subject_id' => $owen->id, 'before' => ['vetting' => 'pending'], 'after' => ['vetting' => 'pending'], 'ip_address' => '127.0.0.1', 'created_at' => now()->subDay()]);
        AuditLog::create(['actor_id' => $owner->id, 'workspace_id' => $workspace->id, 'action' => 'grant.updated', 'subject_type' => Grant::class, 'subject_id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddd01', 'before' => ['access_enabled' => false], 'after' => ['access_enabled' => true], 'ip_address' => '127.0.0.1', 'created_at' => now()->subDays(3)]);
    }
}
