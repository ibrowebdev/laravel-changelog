<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('changelog_repositories', function (Blueprint $table) {
            $table->id();

            // The full GitHub repository name, e.g. "ibrohim/laravel-changelog"
            $table->string('name');

            // The GitHub-provided numeric repository ID for fast webhook matching
            $table->unsignedBigInteger('github_id')->unique();

            // The owner (user or org) extracted for convenience
            $table->string('owner');

            // The repository slug (without the owner prefix)
            $table->string('repo');

            // The branch to listen to for commits (defaults to "main")
            $table->string('default_branch')->default('main');

            // The webhook secret used to verify X-Hub-Signature-256 headers.
            // Stored encrypted — the accessor on the model will handle decryption.
            $table->text('webhook_secret');

            // Quick toggle: stop processing webhooks without deleting the repo
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // You'll often query by owner/repo combo from the webhook payload
            $table->index(['owner', 'repo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('changelog_repositories');
    }
};
