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
        Schema::create('changelog_entries', function (Blueprint $table) {
            $table->id();

            // Links back to the repository this entry came from
            $table->foreignId('changelog_repository_id')
                  ->constrained('changelog_repositories')
                  ->cascadeOnDelete();

            // The product owner can override the title; initially set from the commit message
            $table->string('title');

            // Rich-text body — the product owner can expand on the commit message.
            // Nullable because a raw commit may only have a one-line message.
            $table->text('body')->nullable();

            // The original, unedited commit message — preserved so you can always
            // diff what the developer wrote vs. what the product owner published.
            $table->text('original_commit_message');

            // The full 40-char SHA — useful for linking back to GitHub
            $table->string('commit_sha', 40);

            // The commit author's name and email (from the Git payload, not GitHub user)
            $table->string('author_name');
            $table->string('author_email');

            // Categorisation: "added", "changed", "fixed", "removed", "security", etc.
            // Nullable so entries can exist uncategorised until the product owner curates them.
            $table->string('type')->nullable();

            // Controls visibility on the public changelog page
            $table->boolean('is_published')->default(false);

            // When the entry was made public — separate from created_at because the
            // product owner may curate and publish days after the commit landed.
            $table->timestamp('published_at')->nullable();

            // The timestamp of the commit itself (from the Git payload)
            $table->timestamp('committed_at')->nullable();

            $table->timestamps();

            // Prevent duplicate entries if the same webhook fires twice
            $table->unique(['changelog_repository_id', 'commit_sha']);

            // The public page queries published entries in reverse chronological order
            $table->index(['is_published', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('changelog_entries');
    }
};
