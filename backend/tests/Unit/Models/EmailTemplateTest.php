<?php

declare(strict_types=1);

use App\Models\EmailTemplate;
use App\Models\EmailTemplateVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EmailTemplate - Template Rendering', function () {
    it('renders HTML body with simple variables', function () {
        $template = EmailTemplate::factory()->create([
            'html_body' => '<p>Hello {{name}}</p>',
        ]);

        $rendered = $template->render(['name' => 'John']);

        expect($rendered)->toBe('<p>Hello John</p>');
    });

    it('renders HTML body with nested variables', function () {
        $template = EmailTemplate::factory()->create([
            'html_body' => '<p>Welcome {{user.name}} to {{class.title}}</p>',
        ]);

        $rendered = $template->render([
            'user' => ['name' => 'Jane Doe'],
            'class' => ['title' => 'Yoga Class'],
        ]);

        expect($rendered)->toBe('<p>Welcome Jane Doe to Yoga Class</p>');
    });

    it('renders subject with variables', function () {
        $template = EmailTemplate::factory()->create([
            'subject' => 'Welcome {{user.name}}!',
        ]);

        $rendered = $template->renderSubject([
            'user' => ['name' => 'Sarah'],
        ]);

        expect($rendered)->toBe('Welcome Sarah!');
    });

    it('renders fallback body with variables', function () {
        $template = EmailTemplate::factory()->create([
            'fallback_body' => 'Hello {{user.name}}',
        ]);

        $rendered = $template->renderFallback([
            'user' => ['name' => 'Bob'],
        ]);

        expect($rendered)->toBe('Hello Bob');
    });

    it('returns null when rendering fallback without fallback_body', function () {
        $template = EmailTemplate::factory()->create([
            'fallback_body' => null,
        ]);

        $rendered = $template->renderFallback(['user' => ['name' => 'Test']]);

        expect($rendered)->toBeNull();
    });

    it('leaves unmatched placeholders unchanged', function () {
        $template = EmailTemplate::factory()->create([
            'html_body' => '<p>Hello {{user.name}} and {{missing.variable}}</p>',
        ]);

        $rendered = $template->render([
            'user' => ['name' => 'Alice'],
        ]);

        expect($rendered)->toBe('<p>Hello Alice and {{missing.variable}}</p>');
    });

    it('handles multiple instances of same variable', function () {
        $template = EmailTemplate::factory()->create([
            'html_body' => '<p>{{name}}, welcome! Yes, {{name}}, you!</p>',
        ]);

        $rendered = $template->render(['name' => 'Charlie']);

        expect($rendered)->toBe('<p>Charlie, welcome! Yes, Charlie, you!</p>');
    });

    it('handles deeply nested variables', function () {
        $template = EmailTemplate::factory()->create([
            'html_body' => '<p>{{a.b.c}}</p>',
        ]);

        $rendered = $template->render([
            'a' => [
                'b' => [
                    'c' => 'Deep Value'
                ]
            ]
        ]);

        expect($rendered)->toBe('<p>Deep Value</p>');
    });

    it('converts array values to comma-separated string', function () {
        $template = EmailTemplate::factory()->create([
            'html_body' => '<p>Changes: {{changes}}</p>',
        ]);

        $rendered = $template->render([
            'changes' => ['time', 'room', 'trainer'],
        ]);

        expect($rendered)->toBe('<p>Changes: time, room, trainer</p>');
    });
});

describe('EmailTemplate - Versioning System', function () {
    it('creates version snapshot with current state', function () {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $template = EmailTemplate::factory()->create([
            'subject' => 'Original Subject',
            'html_body' => '<p>Original</p>',
            'fallback_body' => 'Original',
            'version' => 1,
        ]);

        $template->createVersion();

        $this->assertDatabaseHas('email_template_versions', [
            'email_template_id' => $template->id,
            'version' => 1,
            'subject' => 'Original Subject',
            'html_body' => '<p>Original</p>',
            'fallback_body' => 'Original',
            'created_by' => $user->id,
        ]);
    });

    it('prunes old versions keeping only last 2', function () {
        $template = EmailTemplate::factory()->create(['version' => 1]);

        // Create 5 versions
        for ($i = 1; $i <= 5; $i++) {
            $template->createVersion();
            $template->update(['version' => $i + 1]);
        }

        $versions = $template->versions()->get();

        expect($versions)->toHaveCount(2);
        expect($versions->pluck('version')->toArray())->toBe([5, 4]);
    });

    it('restores template from specific version', function () {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $template = EmailTemplate::factory()->create([
            'subject' => 'Version 1',
            'html_body' => '<p>V1</p>',
            'version' => 1,
        ]);

        $template->createVersion();

        $template->update([
            'subject' => 'Version 2',
            'html_body' => '<p>V2</p>',
            'version' => 2,
        ]);

        $oldVersion = $template->versions()->where('version', 1)->first();

        $template->restoreFromVersion($oldVersion);

        $template->refresh();

        expect($template->subject)->toBe('Version 1');
        expect($template->html_body)->toBe('<p>V1</p>');
        expect($template->version)->toBe(3);
        expect($template->updated_by)->toBe($user->id);
    });

    it('creates snapshot before restoring version', function () {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $template = EmailTemplate::factory()->create([
            'subject' => 'V1',
            'version' => 1,
        ]);

        $template->createVersion();
        $template->update(['subject' => 'V2', 'version' => 2]);

        $oldVersion = $template->versions()->where('version', 1)->first();

        $versionCountBefore = $template->versions()->count();

        $template->restoreFromVersion($oldVersion);

        $versionCountAfter = $template->versions()->count();

        // One more version should be created (snapshot of V2 before restore)
        expect($versionCountAfter)->toBeGreaterThan($versionCountBefore);
    });

    it('increments version number', function () {
        $template = EmailTemplate::factory()->create(['version' => 5]);

        $template->incrementVersion();

        $template->refresh();
        expect($template->version)->toBe(6);
    });
});

describe('EmailTemplate - Scopes', function () {
    it('filters active templates using scope', function () {
        EmailTemplate::factory()->create(['is_active' => true, 'slug' => 'active-1']);
        EmailTemplate::factory()->create(['is_active' => true, 'slug' => 'active-2']);
        EmailTemplate::factory()->create(['is_active' => false, 'slug' => 'inactive']);

        $activeTemplates = EmailTemplate::active()->get();

        expect($activeTemplates)->toHaveCount(2);
        expect($activeTemplates->pluck('slug')->toArray())
            ->toBe(['active-1', 'active-2']);
    });

    it('finds template by slug using scope', function () {
        EmailTemplate::factory()->create(['slug' => 'registration-confirmation']);
        EmailTemplate::factory()->create(['slug' => 'password-reset']);

        $template = EmailTemplate::bySlug('registration-confirmation')->first();

        expect($template)->not->toBeNull();
        expect($template->slug)->toBe('registration-confirmation');
    });

    it('returns null when slug not found', function () {
        $template = EmailTemplate::bySlug('non-existent')->first();

        expect($template)->toBeNull();
    });
});

describe('EmailTemplate - Relationships', function () {
    it('belongs to user who updated it', function () {
        $user = User::factory()->create(['name' => 'Admin User']);
        $template = EmailTemplate::factory()->create(['updated_by' => $user->id]);

        $updater = $template->updatedBy;

        expect($updater)->not->toBeNull();
        expect($updater->id)->toBe($user->id);
        expect($updater->name)->toBe('Admin User');
    });

    it('has many versions ordered by version desc', function () {
        $template = EmailTemplate::factory()->create(['version' => 1]);

        EmailTemplateVersion::factory()->create([
            'email_template_id' => $template->id,
            'version' => 1,
        ]);

        EmailTemplateVersion::factory()->create([
            'email_template_id' => $template->id,
            'version' => 3,
        ]);

        EmailTemplateVersion::factory()->create([
            'email_template_id' => $template->id,
            'version' => 2,
        ]);

        $versions = $template->versions;

        expect($versions)->toHaveCount(3);
        expect($versions->pluck('version')->toArray())->toBe([3, 2, 1]);
    });
});

describe('EmailTemplate - Supported Variables', function () {
    it('returns array of supported variables', function () {
        $variables = EmailTemplate::getSupportedVariables();

        expect($variables)->toBeArray();
        expect($variables)->not->toBeEmpty();
    });

    it('includes user variables', function () {
        $variables = EmailTemplate::getSupportedVariables();

        expect($variables)->toHaveKey('{{user.name}}');
        expect($variables)->toHaveKey('{{user.email}}');
    });

    it('includes class variables', function () {
        $variables = EmailTemplate::getSupportedVariables();

        expect($variables)->toHaveKey('{{class.title}}');
        expect($variables)->toHaveKey('{{class.starts_at}}');
        expect($variables)->toHaveKey('{{class.room}}');
    });

    it('includes URL variables', function () {
        $variables = EmailTemplate::getSupportedVariables();

        expect($variables)->toHaveKey('{{cancel_url}}');
        expect($variables)->toHaveKey('{{confirm_url}}');
        expect($variables)->toHaveKey('{{password_reset_url}}');
    });

    it('provides descriptions for variables', function () {
        $variables = EmailTemplate::getSupportedVariables();

        foreach ($variables as $description) {
            expect($description)->toBeString();
            expect($description)->not->toBeEmpty();
        }
    });
});

describe('EmailTemplate - Edge Cases', function () {
    it('handles empty template body', function () {
        $template = EmailTemplate::factory()->create([
            'html_body' => '',
        ]);

        $rendered = $template->render(['user' => ['name' => 'Test']]);

        expect($rendered)->toBe('');
    });

    it('handles template with no variables', function () {
        $template = EmailTemplate::factory()->create([
            'html_body' => '<p>Static content only</p>',
        ]);

        $rendered = $template->render([]);

        expect($rendered)->toBe('<p>Static content only</p>');
    });

    it('handles special characters in variable values', function () {
        $template = EmailTemplate::factory()->create([
            'html_body' => '<p>Message: {{message}}</p>',
        ]);

        $rendered = $template->render([
            'message' => 'Hello & goodbye <script>alert("xss")</script>',
        ]);

        expect($rendered)->toContain('Hello & goodbye');
    });

    it('handles numeric variable values', function () {
        $template = EmailTemplate::factory()->create([
            'html_body' => '<p>Price: {{price}}</p>',
        ]);

        $rendered = $template->render(['price' => 1999]);

        expect($rendered)->toBe('<p>Price: 1999</p>');
    });

    it('handles boolean variable values', function () {
        $template = EmailTemplate::factory()->create([
            'html_body' => '<p>Active: {{is_active}}</p>',
        ]);

        $rendered = $template->render(['is_active' => true]);

        expect($rendered)->toBe('<p>Active: 1</p>');
    });

    it('soft deletes template without deleting versions', function () {
        $template = EmailTemplate::factory()->create();
        $template->createVersion();

        $versionId = $template->versions()->first()->id;

        $template->delete();

        $this->assertSoftDeleted('email_templates', ['id' => $template->id]);

        $this->assertDatabaseHas('email_template_versions', [
            'id' => $versionId,
            'email_template_id' => $template->id,
        ]);
    });
});
