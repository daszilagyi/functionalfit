<?php

declare(strict_types=1);

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->staff = User::factory()->create(['role' => 'staff']);
    $this->client = User::factory()->create(['role' => 'client']);
});

describe('GET /api/v1/admin/email-templates - List Templates', function () {
    it('returns paginated list of email templates for admin', function () {
        EmailTemplate::factory()->count(5)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/email-templates');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'slug',
                            'subject',
                            'html_body',
                            'fallback_body',
                            'is_active',
                            'version',
                            'updated_by',
                            'created_at',
                            'updated_at',
                        ]
                    ],
                    'current_page',
                    'per_page',
                    'total',
                ]
            ]);

        expect($response->json('data.data'))->toHaveCount(5);
    });

    it('filters templates by active status', function () {
        EmailTemplate::factory()->create(['is_active' => true]);
        EmailTemplate::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/email-templates?is_active=1');

        $response->assertOk();
        expect($response->json('data.data'))->toHaveCount(1);
        expect($response->json('data.data.0.is_active'))->toBe(true);
    });

    it('searches templates by slug', function () {
        EmailTemplate::factory()->create(['slug' => 'registration-confirmation']);
        EmailTemplate::factory()->create(['slug' => 'password-reset']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/email-templates?search=registration');

        $response->assertOk();
        expect($response->json('data.data'))->toHaveCount(1);
        expect($response->json('data.data.0.slug'))->toBe('registration-confirmation');
    });

    it('searches templates by subject', function () {
        EmailTemplate::factory()->create(['subject' => 'Welcome to FunctionalFit']);
        EmailTemplate::factory()->create(['subject' => 'Password Reset Request']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/email-templates?search=Welcome');

        $response->assertOk();
        expect($response->json('data.data'))->toHaveCount(1);
    });

    it('orders templates by slug', function () {
        EmailTemplate::factory()->create(['slug' => 'zulu-template']);
        EmailTemplate::factory()->create(['slug' => 'alpha-template']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/email-templates');

        $response->assertOk();
        $slugs = collect($response->json('data.data'))->pluck('slug');
        expect($slugs->first())->toBe('alpha-template');
        expect($slugs->last())->toBe('zulu-template');
    });

    it('denies access to staff', function () {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/admin/email-templates');

        $response->assertForbidden();
    });

    it('denies access to clients', function () {
        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v1/admin/email-templates');

        $response->assertForbidden();
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/admin/email-templates');

        $response->assertUnauthorized();
    });
});

describe('GET /api/v1/admin/email-templates/{id} - Show Template', function () {
    it('returns specific template with available variables', function () {
        $template = EmailTemplate::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/email-templates/{$template->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'template' => [
                        'id',
                        'slug',
                        'subject',
                        'html_body',
                        'fallback_body',
                        'is_active',
                        'version',
                    ],
                    'available_variables' => [
                        '*' => [],
                    ],
                ]
            ]);

        expect($response->json('data.template.id'))->toBe($template->id);
    });

    it('includes template versions in response', function () {
        $template = EmailTemplate::factory()->create();
        $template->createVersion();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/email-templates/{$template->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'template' => [
                        'versions' => [
                            '*' => [
                                'id',
                                'version',
                                'created_at',
                            ]
                        ]
                    ]
                ]
            ]);
    });

    it('returns 404 for non-existent template', function () {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/email-templates/99999');

        $response->assertNotFound();
    });

    it('denies access to non-admin users', function () {
        $template = EmailTemplate::factory()->create();

        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/v1/admin/email-templates/{$template->id}");

        $response->assertForbidden();
    });
});

describe('POST /api/v1/admin/email-templates - Create Template', function () {
    it('creates new email template successfully', function () {
        $data = [
            'slug' => 'test-template',
            'subject' => 'Test Email',
            'html_body' => '<p>Hello {{user.name}}</p>',
            'fallback_body' => 'Hello {{user.name}}',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/email-templates', $data);

        $response->assertCreated()
            ->assertJsonFragment([
                'slug' => 'test-template',
                'subject' => 'Test Email',
            ]);

        $this->assertDatabaseHas('email_templates', [
            'slug' => 'test-template',
            'version' => 1,
            'updated_by' => $this->admin->id,
        ]);
    });

    it('creates template with default is_active true', function () {
        $data = [
            'slug' => 'test-template',
            'subject' => 'Test Email',
            'html_body' => '<p>Test</p>',
            'fallback_body' => 'Test',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/email-templates', $data);

        $response->assertCreated();
        expect($response->json('data.is_active'))->toBe(true);
    });

    it('validates required fields', function () {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/email-templates', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slug', 'subject', 'html_body']);
    });

    it('validates slug uniqueness', function () {
        EmailTemplate::factory()->create(['slug' => 'existing-template']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/email-templates', [
                'slug' => 'existing-template',
                'subject' => 'Test',
                'html_body' => '<p>Test</p>',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    });

    it('validates slug format', function () {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/email-templates', [
                'slug' => 'Invalid Slug!',
                'subject' => 'Test',
                'html_body' => '<p>Test</p>',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    });

    it('denies access to non-admin users', function () {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/admin/email-templates', [
                'slug' => 'test',
                'subject' => 'Test',
                'html_body' => '<p>Test</p>',
            ]);

        $response->assertForbidden();
    });
});

describe('PUT /api/v1/admin/email-templates/{id} - Update Template', function () {
    it('updates template successfully', function () {
        $template = EmailTemplate::factory()->create([
            'subject' => 'Old Subject',
            'version' => 1,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/email-templates/{$template->id}", [
                'subject' => 'New Subject',
                'html_body' => '<p>Updated content</p>',
                'fallback_body' => 'Updated content',
            ]);

        $response->assertOk()
            ->assertJsonFragment([
                'subject' => 'New Subject',
            ]);

        $template->refresh();
        expect($template->subject)->toBe('New Subject');
        expect($template->version)->toBe(2);
        expect($template->updated_by)->toBe($this->admin->id);
    });

    it('creates version snapshot before updating', function () {
        $template = EmailTemplate::factory()->create([
            'subject' => 'Original Subject',
            'html_body' => '<p>Original</p>',
            'version' => 1,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/email-templates/{$template->id}", [
                'subject' => 'Updated Subject',
                'html_body' => '<p>Updated</p>',
                'fallback_body' => 'Updated',
            ]);

        $this->assertDatabaseHas('email_template_versions', [
            'email_template_id' => $template->id,
            'version' => 1,
            'subject' => 'Original Subject',
        ]);
    });

    it('increments version number on update', function () {
        $template = EmailTemplate::factory()->create(['version' => 5]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/email-templates/{$template->id}", [
                'subject' => 'Updated',
                'html_body' => '<p>Updated</p>',
                'fallback_body' => 'Updated',
            ]);

        $template->refresh();
        expect($template->version)->toBe(6);
    });

    it('updates is_active status', function () {
        $template = EmailTemplate::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/email-templates/{$template->id}", [
                'is_active' => false,
            ]);

        $response->assertOk();
        $template->refresh();
        expect($template->is_active)->toBe(false);
    });

    it('denies access to non-admin users', function () {
        $template = EmailTemplate::factory()->create();

        $response = $this->actingAs($this->client, 'sanctum')
            ->putJson("/api/v1/admin/email-templates/{$template->id}", [
                'subject' => 'Hacked',
            ]);

        $response->assertForbidden();
    });
});

describe('DELETE /api/v1/admin/email-templates/{id} - Delete Template', function () {
    it('soft deletes template successfully', function () {
        $template = EmailTemplate::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/email-templates/{$template->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('email_templates', [
            'id' => $template->id,
        ]);
    });

    it('returns 404 for non-existent template', function () {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/v1/admin/email-templates/99999');

        $response->assertNotFound();
    });

    it('denies access to non-admin users', function () {
        $template = EmailTemplate::factory()->create();

        $response = $this->actingAs($this->staff, 'sanctum')
            ->deleteJson("/api/v1/admin/email-templates/{$template->id}");

        $response->assertForbidden();
    });
});

describe('POST /api/v1/admin/email-templates/{id}/preview - Preview Template', function () {
    it('renders template preview with custom variables', function () {
        $template = EmailTemplate::factory()->create([
            'subject' => 'Hello {{user.name}}',
            'html_body' => '<p>Welcome {{user.name}} to {{class.title}}</p>',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/email-templates/{$template->id}/preview", [
                'variables' => [
                    'user' => ['name' => 'John Doe'],
                    'class' => ['title' => 'Yoga Class'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'preview',
                    'variables_used',
                ]
            ]);
    });

    it('uses sample variables when none provided', function () {
        $template = EmailTemplate::factory()->create([
            'html_body' => '<p>Hello {{user.name}}</p>',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/email-templates/{$template->id}/preview");

        $response->assertOk()
            ->assertJsonPath('data.variables_used.user.name', 'Teszt Felhasznalo');
    });
});

describe('POST /api/v1/admin/email-templates/{id}/send-test - Send Test Email', function () {
    it('queues test email successfully', function () {
        Queue::fake();

        $template = EmailTemplate::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/email-templates/{$template->id}/send-test", [
                'email' => 'test@example.com',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['recipient' => 'test@example.com']);
    });

    it('validates email address', function () {
        $template = EmailTemplate::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/email-templates/{$template->id}/send-test", [
                'email' => 'invalid-email',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('uses custom variables when provided', function () {
        Queue::fake();

        $template = EmailTemplate::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/email-templates/{$template->id}/send-test", [
                'email' => 'test@example.com',
                'variables' => [
                    'user' => ['name' => 'Test User'],
                ],
            ]);

        $response->assertOk();
    });
});

describe('GET /api/v1/admin/email-templates/{id}/versions - Get Versions', function () {
    it('returns all versions ordered by version desc', function () {
        $template = EmailTemplate::factory()->create(['version' => 1]);
        $template->createVersion();
        $template->update(['subject' => 'V2', 'version' => 2]);
        $template->createVersion();
        $template->update(['subject' => 'V3', 'version' => 3]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/email-templates/{$template->id}/versions");

        $response->assertOk();
        $versions = $response->json('data');

        expect($versions)->toHaveCount(2);
        expect($versions[0]['version'])->toBeGreaterThan($versions[1]['version']);
    });

    it('includes created_by relationship', function () {
        $template = EmailTemplate::factory()->create();
        $template->createVersion();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/email-templates/{$template->id}/versions");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'created_by',
                    ]
                ]
            ]);
    });
});

describe('POST /api/v1/admin/email-templates/{id}/restore/{versionId} - Restore Version', function () {
    it('restores template to specific version', function () {
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

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/email-templates/{$template->id}/restore/{$oldVersion->id}");

        $response->assertOk();

        $template->refresh();
        expect($template->subject)->toBe('Version 1');
        expect($template->html_body)->toBe('<p>V1</p>');
        expect($template->version)->toBe(3); // Version incremented
    });

    it('creates version snapshot before restore', function () {
        $template = EmailTemplate::factory()->create(['version' => 1]);
        $template->createVersion();
        $template->update(['subject' => 'Current', 'version' => 2]);

        $version = $template->versions()->first();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/email-templates/{$template->id}/restore/{$version->id}");

        $this->assertDatabaseHas('email_template_versions', [
            'email_template_id' => $template->id,
            'subject' => 'Current',
        ]);
    });

    it('returns 404 for invalid version', function () {
        $template = EmailTemplate::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/email-templates/{$template->id}/restore/99999");

        $response->assertNotFound();
    });
});

describe('GET /api/v1/admin/email-templates-variables - Get Variables', function () {
    it('returns available template variables', function () {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/email-templates-variables');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'variables' => []
                ]
            ]);

        $variables = $response->json('data.variables');
        expect($variables)->toBeArray();
        expect($variables)->not->toBeEmpty();
    });
});
