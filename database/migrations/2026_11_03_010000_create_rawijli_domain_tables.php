<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createTable('workspaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('industry')->default('Other');
            $table->string('timezone')->default('UTC');
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
        });

        $this->createTable('connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->enum('platform', ['meta', 'tiktok']);
            $table->enum('kind', ['page', 'profile', 'ad_account', 'tiktok_account']);
            $table->string('name');
            $table->string('external_id');
            $table->enum('status', ['connected', 'needs_reauth', 'disconnected'])->default('connected');
            $table->timestamp('last_sync_at')->nullable();
            $table->decimal('spend_30d', 14, 2)->default(0);
            $table->json('scopes')->nullable();
            $table->text('error')->nullable();
            $table->json('sync_history')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'kind', 'external_id']);
        });

        $this->createTable('freelancer_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline')->default('');
            $table->text('bio')->nullable();
            $table->json('skills');
            $table->json('platforms');
            $table->decimal('hourly_rate', 10, 2)->default(50);
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedInteger('reviews')->default(0);
            $table->unsignedInteger('completed_jobs')->default(0);
            $table->string('location')->default('Remote');
            $table->string('availability')->default('Available');
            $table->boolean('verified')->default(false);
            $table->enum('vetting', ['approved', 'pending', 'rejected'])->default('pending')->index();
            $table->boolean('public_visible')->default(true);
            $table->text('vetting_reason')->nullable();
            $table->json('badges')->nullable();
            $table->timestamps();
        });

        $this->createTable('packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->text('detail');
            $table->timestamps();
        });

        $this->createTable('portfolio_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('caption')->nullable();
            $table->enum('kind', ['image', 'video']);
            $table->string('path')->nullable();
            $table->unsignedInteger('hue')->default(260);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $this->createTable('creatives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('caption')->nullable();
            $table->string('headline')->nullable();
            $table->unsignedInteger('hue')->default(280);
            $table->enum('platform', ['instagram', 'facebook', 'tiktok']);
            $table->string('aspect_ratio')->default('4:5');
            $table->enum('kind', ['generated', 'upload']);
            $table->string('path')->nullable();
            $table->enum('processing_status', ['ready', 'processing', 'failed'])->default('ready');
            $table->timestamps();
        });

        $this->createTable('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('connection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('created_by_role', ['owner', 'freelancer', 'ai']);
            $table->enum('platform', ['meta', 'tiktok']);
            $table->string('name');
            $table->string('objective');
            $table->enum('status', ['active', 'paused', 'draft', 'pending_approval', 'archived'])->default('draft')->index();
            $table->decimal('daily_budget', 12, 2)->default(0);
            $table->decimal('lifetime_budget', 12, 2)->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->decimal('spend', 14, 2)->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->timestamps();
            $table->index(['workspace_id', 'platform', 'status']);
        });

        $this->createTable('ad_sets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('targeting');
            $table->decimal('daily_budget', 12, 2)->default(0);
            $table->enum('status', ['active', 'paused', 'draft', 'pending_approval', 'archived'])->default('draft');
            $table->decimal('spend', 14, 2)->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->timestamps();
        });

        $this->createTable('ads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ad_set_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('creative_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->enum('format', ['image', 'video', 'carousel']);
            $table->string('headline');
            $table->text('primary_text');
            $table->string('destination_url')->nullable();
            $table->enum('status', ['active', 'paused', 'draft', 'pending_approval', 'archived'])->default('draft');
            $table->decimal('spend', 14, 2)->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->timestamps();
        });

        $this->createTable('metric_points', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('spend', 14, 2)->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->timestamps();
            $table->unique(['workspace_id', 'campaign_id', 'date']);
            $table->index(['workspace_id', 'date']);
        });

        $this->createTable('job_briefs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->json('skills');
            $table->json('platforms')->nullable();
            $table->decimal('budget', 12, 2);
            $table->unsignedInteger('duration_days')->default(7);
            $table->enum('status', ['open', 'reviewing', 'hired', 'closed'])->default('open')->index();
            $table->boolean('ai_generated')->default(false);
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        $this->createTable('proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')->constrained('job_briefs')->cascadeOnDelete();
            $table->foreignUuid('freelancer_id')->constrained('freelancer_profiles')->cascadeOnDelete();
            $table->text('cover');
            $table->decimal('bid', 12, 2);
            $table->unsignedInteger('days');
            $table->enum('status', ['sent', 'shortlisted', 'accepted', 'declined', 'withdrawn'])->default('sent');
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->unique(['job_id', 'freelancer_id']);
        });

        $this->createTable('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('freelancer_id')->constrained('freelancer_profiles')->cascadeOnDelete();
            $table->foreignUuid('job_id')->nullable()->constrained('job_briefs')->nullOnDelete();
            $table->text('reason');
            $table->enum('status', ['open', 'accepted', 'declined'])->default('open');
            $table->timestamps();
        });

        $this->createTable('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('freelancer_id')->constrained('freelancer_profiles')->cascadeOnDelete();
            $table->foreignUuid('job_id')->nullable()->constrained('job_briefs')->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['active', 'completed', 'disputed', 'cancelled'])->default('active');
            $table->timestamp('started_at');
            $table->text('terms')->nullable();
            $table->timestamps();
        });

        $this->createTable('milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contract_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'submitted', 'approved', 'paid'])->default('pending');
            $table->timestamps();
        });

        $this->createTable('grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('freelancer_id')->constrained('freelancer_profiles')->cascadeOnDelete();
            $table->foreignUuid('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('access_enabled')->default(false);
            $table->json('permissions');
            $table->timestamps();
            $table->unique(['workspace_id', 'freelancer_id', 'contract_id']);
        });

        $this->createTable('approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('freelancer_id')->constrained('freelancer_profiles')->cascadeOnDelete();
            $table->foreignUuid('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['new_campaign', 'campaign_edit', 'budget_change', 'schedule_post']);
            $table->string('title');
            $table->text('summary');
            $table->json('before_payload')->nullable();
            $table->json('after_payload');
            $table->json('payload');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        $this->createTable('calendar_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('creative_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->time('time');
            $table->string('timezone')->default('UTC');
            $table->enum('channel', ['instagram', 'facebook', 'tiktok']);
            $table->enum('kind', ['organic', 'ad']);
            $table->string('title');
            $table->text('caption');
            $table->enum('status', ['draft', 'scheduled', 'published', 'pending_approval', 'failed', 'cancelled'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'date', 'status']);
        });

        $this->createTable('publishing_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('calendar_item_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key')->unique();
            $table->enum('status', ['processing', 'published', 'failed']);
            $table->text('error')->nullable();
            $table->timestamps();
        });

        $this->createTable('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->text('content');
            $table->json('cards')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['workspace_id', 'user_id', 'created_at']);
        });

        $this->createTable('threads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('job_id')->nullable()->constrained('job_briefs')->nullOnDelete();
            $table->foreignUuid('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->text('last_message')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        $this->createTable('thread_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->unique(['thread_id', 'user_id']);
        });

        $this->createTable('direct_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('from_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['thread_id', 'created_at']);
        });

        $this->createTable('deliverables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('freelancer_id')->constrained('freelancer_profiles')->cascadeOnDelete();
            $table->string('title');
            $table->text('notes');
            $table->enum('status', ['submitted', 'revision', 'approved'])->default('submitted');
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamp('submitted_at');
            $table->timestamps();
        });

        $this->createTable('deliverable_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('deliverable_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('notes');
            $table->string('file_path')->nullable();
            $table->string('link')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['deliverable_id', 'version']);
        });

        $this->createTable('deliverable_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('deliverable_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->nullable();
        });

        $this->createTable('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->enum('plan', ['starter', 'growth', 'scale']);
            $table->enum('status', ['trialing', 'active', 'cancelled'])->default('trialing');
            $table->decimal('amount', 10, 2);
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamps();
        });

        $this->createTable('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('type', ['subscription', 'escrow_hold', 'escrow_release', 'refund', 'marketplace_fee']);
            $table->enum('status', ['pending', 'paid', 'held', 'released', 'refunded', 'failed']);
            $table->string('provider_reference')->unique();
            $table->timestamp('created_at')->nullable();
        });

        $this->createTable('disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('opened_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->enum('status', ['open', 'resolved', 'escalated'])->default('open');
            $table->text('resolution')->nullable();
            $table->timestamps();
        });

        $this->createTable('dispute_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('status');
            $table->text('comment');
            $table->timestamp('created_at')->nullable();
        });

        $this->createTable('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('href');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'read_at']);
        });

        $this->createTable('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->string('subject_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['workspace_id', 'created_at']);
        });

        $this->createTable('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->enum('status', ['processing', 'ready', 'failed'])->default('processing');
            $table->string('path')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    private function createTable(string $table, \Closure $callback): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $callback);
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['reports', 'audit_logs', 'notifications', 'dispute_events', 'disputes', 'transactions', 'subscriptions', 'deliverable_comments', 'deliverable_versions', 'deliverables', 'direct_messages', 'thread_participants', 'threads', 'chat_messages', 'publishing_attempts', 'calendar_items', 'approvals', 'grants', 'milestones', 'contracts', 'invitations', 'proposals', 'job_briefs', 'metric_points', 'ads', 'ad_sets', 'campaigns', 'creatives', 'portfolio_items', 'packages', 'freelancer_profiles', 'connections', 'workspaces'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
    }
};
