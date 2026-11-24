<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $t) {
            $t->id();
            $t->uuid('edit_token')->unique();          // unguessable link = "login" for the owner (no auth in v1)
            $t->string('name');
            $t->string('city');
            // new | needs_candidate | needs_review | ai_failed | generated
            $t->string('status')->default('new');
            $t->text('status_message')->nullable();
            $t->json('candidates')->nullable();          // distinct businesses found sharing the name
            $t->json('selected_candidate')->nullable();  // the one the user picked
            $t->timestamps();
        });

        Schema::create('sources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('ref', 8);                        // S1, S2... (what the AI cites)
            $t->string('type', 30);                      // website|social|google_business|file|image|pasted|web_search
            $t->string('label')->nullable();
            $t->text('url')->nullable();
            $t->string('file_path')->nullable();
            $t->string('mime', 100)->nullable();
            $t->string('status', 20)->default('ok');     // ok|blocked|failed|empty
            $t->text('status_message')->nullable();
            $t->longText('content')->nullable();         // cleaned text (injection lines removed)
            $t->json('flags')->nullable();               // instruction-like text that was removed
            $t->timestamps();
        });

        Schema::create('facts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $t->string('key', 30);
            $t->text('value');
            $t->text('evidence')->nullable();            // verbatim quote from the source
            $t->json('meta')->nullable();                // service price/description
            $t->boolean('verified')->default(false);     // evidence found in the source text
            $t->boolean('confirmed')->default(false);    // user approved it
            $t->string('origin', 10)->default('ai');     // ai|user
            $t->timestamps();
            $t->index(['business_id', 'key']);
        });

        Schema::create('sites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('slug')->nullable()->unique();
            $t->string('status', 20)->default('draft'); // draft|published
            $t->string('accent', 7)->default('#2563eb');
            $t->text('notice')->nullable();             // e.g. "AI was down, basic template used"
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });

        Schema::create('site_sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('type', 20);                      // hero|about|services|gallery|contact
            $t->unsignedInteger('position');
            $t->json('content');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['site_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_sections');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('facts');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('businesses');
    }
};
