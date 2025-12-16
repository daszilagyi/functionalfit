<?php

declare(strict_types=1);

/**
 * Email Template Save & Preview Debug Tests
 *
 * Purpose: Detailed backend testing to identify issues with save and preview functionality
 * Bug Reports:
 * - Template save doesn't work
 * - Preview doesn't work
 *
 * These tests provide extensive debugging output to pinpoint exact failure points.
 */

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

describe('DEBUG: Email Template Save Functionality', function () {
    it('saves template with complete payload debugging', function () {
        $template = EmailTemplate::factory()->create([
            'slug' => 'registration-confirmation',
            'subject' => 'Original Subject',
            'html_body' => '<p>Original HTML content</p>',
            'fallback_body' => 'Original fallback text',
            'is_active' => true,
            'version' => 1,
        ]);

        echo "\n🔍 PHASE 1: Initial Template State\n";
        echo "  ID: {$template->id}\n";
        echo "  Slug: {$template->slug}\n";
        echo "  Subject: {$template->subject}\n";
        echo "  Version: {$template->version}\n";
        echo "  Active: " . ($template->is_active ? 'true' : 'false') . "\n";
        echo "  HTML Length: " . strlen($template->html_body) . " chars\n";
        echo "  Fallback Length: " . strlen($template->fallback_body) . " chars\n";

        $updateData = [
            'subject' => 'Updated Subject - Test',
            'html_body' => '<p><strong>Bold test text</strong></p><h2>Test Heading</h2>',
            'fallback_body' => 'Updated fallback text for testing',
            'is_active' => true,
        ];

        echo "\n🔍 PHASE 2: Update Payload\n";
        echo "  Subject: {$updateData['subject']}\n";
        echo "  HTML Length: " . strlen($updateData['html_body']) . " chars\n";
        echo "  Fallback Length: " . strlen($updateData['fallback_body']) . " chars\n";
        echo "  Is Active: " . ($updateData['is_active'] ? 'true' : 'false') . "\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/email-templates/{$template->id}", $updateData);

        echo "\n🔍 PHASE 3: API Response\n";
        echo "  Status Code: {$response->status()}\n";
        echo "  Response Body:\n";
        echo json_encode($response->json(), JSON_PRETTY_PRINT) . "\n";

        // Assert response is successful
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'subject',
                    'html_body',
                    'fallback_body',
                    'is_active',
                    'version',
                ],
            ]);

        echo "\n✅ PHASE 3: Response structure validated\n";

        // Verify response data
        expect($response->json('data.subject'))->toBe($updateData['subject']);
        expect($response->json('data.html_body'))->toBe($updateData['html_body']);
        expect($response->json('data.fallback_body'))->toBe($updateData['fallback_body']);
        expect($response->json('data.version'))->toBe(2);

        echo "\n✅ PHASE 3: Response data validated\n";

        echo "\n🔍 PHASE 4: Database State After Update\n";
        $template->refresh();
        echo "  Subject: {$template->subject}\n";
        echo "  Version: {$template->version}\n";
        echo "  HTML Length: " . strlen($template->html_body) . " chars\n";
        echo "  Fallback Length: " . strlen($template->fallback_body) . " chars\n";
        echo "  Updated By: {$template->updated_by}\n";

        // Assert database was updated
        $this->assertDatabaseHas('email_templates', [
            'id' => $template->id,
            'subject' => $updateData['subject'],
            'html_body' => $updateData['html_body'],
            'fallback_body' => $updateData['fallback_body'],
            'version' => 2,
            'updated_by' => $this->admin->id,
        ]);

        echo "\n✅ PHASE 4: Database updated correctly\n";

        echo "\n🔍 PHASE 5: Version Snapshot Verification\n";
        $versions = $template->versions()->get();
        echo "  Total Versions: {$versions->count()}\n";

        foreach ($versions as $version) {
            echo "  Version {$version->version}: {$version->subject} (created: {$version->created_at})\n";
        }

        // Assert version was created
        $this->assertDatabaseHas('email_template_versions', [
            'email_template_id' => $template->id,
            'version' => 1,
            'subject' => 'Original Subject',
        ]);

        echo "\n✅ PHASE 5: Version snapshot created\n";

        echo "\n🎉 ALL SAVE TESTS PASSED\n\n";
    });

    it('handles validation errors correctly', function () {
        $template = EmailTemplate::factory()->create();

        echo "\n🔍 Testing Validation: Empty subject\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/email-templates/{$template->id}", [
                'subject' => '',
                'html_body' => '<p>Content</p>',
            ]);

        echo "  Status Code: {$response->status()}\n";
        echo "  Validation Errors:\n";
        echo json_encode($response->json('errors'), JSON_PRETTY_PRINT) . "\n";

        $response->assertUnprocessable();

        echo "\n✅ Validation working correctly\n\n";
    });

    it('handles partial updates correctly', function () {
        $template = EmailTemplate::factory()->create([
            'subject' => 'Original',
            'html_body' => '<p>Original HTML</p>',
            'fallback_body' => 'Original fallback',
            'is_active' => true,
        ]);

        echo "\n🔍 Testing Partial Update: Only is_active\n";
        echo "  Original is_active: true\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/email-templates/{$template->id}", [
                'is_active' => false,
            ]);

        echo "  Response Status: {$response->status()}\n";
        echo "  Response is_active: " . ($response->json('data.is_active') ? 'true' : 'false') . "\n";

        $response->assertOk();

        $template->refresh();
        echo "  Database is_active: " . ($template->is_active ? 'true' : 'false') . "\n";
        echo "  Subject unchanged: {$template->subject}\n";

        expect($template->is_active)->toBe(false);
        expect($template->subject)->toBe('Original');

        echo "\n✅ Partial update working correctly\n\n";
    });

    it('preserves HTML content integrity', function () {
        $template = EmailTemplate::factory()->create();

        $complexHtml = '<div style="font-family: Arial;">
            <h1>Welcome {{user.name}}!</h1>
            <p>Your class <strong>{{class.title}}</strong> starts at {{class.starts_at}}.</p>
            <table>
                <tr><td>Trainer:</td><td>{{trainer.name}}</td></tr>
                <tr><td>Room:</td><td>{{class.room}}</td></tr>
            </table>
            <a href="{{cancel_url}}">Cancel Booking</a>
        </div>';

        echo "\n🔍 Testing HTML Integrity Preservation\n";
        echo "  HTML Length: " . strlen($complexHtml) . " chars\n";
        echo "  Contains variables: " . (strpos($complexHtml, '{{user.name}}') !== false ? 'yes' : 'no') . "\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/email-templates/{$template->id}", [
                'html_body' => $complexHtml,
            ]);

        $response->assertOk();

        $template->refresh();
        echo "  Stored HTML Length: " . strlen($template->html_body) . " chars\n";
        echo "  HTML matches: " . ($template->html_body === $complexHtml ? 'yes' : 'no') . "\n";

        expect($template->html_body)->toBe($complexHtml);

        echo "\n✅ HTML content preserved perfectly\n\n";
    });
});

describe('DEBUG: Email Template Preview Functionality', function () {
    it('generates preview with sample variables', function () {
        $template = EmailTemplate::factory()->create([
            'subject' => 'Hello {{user.name}}',
            'html_body' => '<p>Welcome {{user.name}} to {{class.title}}</p>',
        ]);

        echo "\n🔍 PHASE 1: Template Setup\n";
        echo "  Subject: {$template->subject}\n";
        echo "  HTML: {$template->html_body}\n";

        echo "\n🔍 PHASE 2: Sending Preview Request\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/email-templates/{$template->id}/preview");

        echo "  Status Code: {$response->status()}\n";
        echo "  Response Structure:\n";
        $data = $response->json();
        echo "  - Has 'success': " . (isset($data['success']) ? 'yes' : 'no') . "\n";
        echo "  - Has 'data': " . (isset($data['data']) ? 'yes' : 'no') . "\n";

        if (isset($data['data'])) {
            echo "  - data.preview exists: " . (isset($data['data']['preview']) ? 'yes' : 'no') . "\n";
            echo "  - data.variables_used exists: " . (isset($data['data']['variables_used']) ? 'yes' : 'no') . "\n";
        }

        echo "\n🔍 PHASE 3: Response Data Analysis\n";

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'preview',
                    'variables_used',
                ]
            ]);

        echo "  ✅ Response structure validated\n";

        $preview = $response->json('data.preview');
        $variables = $response->json('data.variables_used');

        echo "\n🔍 PHASE 4: Preview Content Analysis\n";
        echo "  Preview Type: " . gettype($preview) . "\n";
        echo "  Preview Length: " . strlen($preview) . " chars\n";
        echo "  Contains HTML tags: " . (strpos($preview, '<') !== false ? 'yes' : 'no') . "\n";
        echo "  Contains variables ({{}}): " . (strpos($preview, '{{') !== false ? 'NO (replaced)' : 'yes (NOT replaced)') . "\n";
        echo "  First 200 chars:\n";
        echo "    " . substr($preview, 0, 200) . "\n";

        echo "\n🔍 PHASE 5: Variables Used Analysis\n";
        echo "  Variables Type: " . gettype($variables) . "\n";
        echo "  Variables:\n";
        echo json_encode($variables, JSON_PRETTY_PRINT) . "\n";

        // Assert preview has HTML content
        expect($preview)->toBeString();
        expect(strlen($preview))->toBeGreaterThan(0);

        // Assert variables were replaced (no {{}} should remain)
        expect($preview)->not->toContain('{{user.name}}');
        expect($preview)->toContain('Teszt Felhasznalo'); // Default sample name

        echo "\n✅ PHASE 5: Variable replacement validated\n";

        echo "\n🎉 ALL PREVIEW TESTS PASSED\n\n";
    });

    it('generates preview with custom variables', function () {
        $template = EmailTemplate::factory()->create([
            'html_body' => '<p>Hello {{user.name}}, welcome to {{class.title}}</p>',
        ]);

        echo "\n🔍 Testing Custom Variables\n";

        $customVariables = [
            'user' => ['name' => 'John Doe'],
            'class' => ['title' => 'Advanced Yoga'],
        ];

        echo "  Custom Variables:\n";
        echo json_encode($customVariables, JSON_PRETTY_PRINT) . "\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/email-templates/{$template->id}/preview", [
                'variables' => $customVariables,
            ]);

        echo "\n  Status Code: {$response->status()}\n";

        $response->assertOk();

        $preview = $response->json('data.preview');
        echo "  Preview Content:\n    {$preview}\n";

        expect($preview)->toContain('John Doe');
        expect($preview)->toContain('Advanced Yoga');
        expect($preview)->not->toContain('{{');

        echo "\n✅ Custom variables working correctly\n\n";
    });

    it('handles missing variables gracefully', function () {
        $template = EmailTemplate::factory()->create([
            'html_body' => '<p>Hello {{user.name}}, {{undefined_variable}}</p>',
        ]);

        echo "\n🔍 Testing Undefined Variable Handling\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/email-templates/{$template->id}/preview");

        echo "  Status Code: {$response->status()}\n";

        $response->assertOk();

        $preview = $response->json('data.preview');
        echo "  Preview Content:\n    {$preview}\n";
        echo "  Contains undefined placeholder: " . (strpos($preview, 'undefined_variable') !== false ? 'yes' : 'no') . "\n";

        echo "\n✅ Missing variables handled\n\n";
    });

    it('returns correct response format for frontend', function () {
        $template = EmailTemplate::factory()->create([
            'html_body' => '<p>Test content</p>',
        ]);

        echo "\n🔍 Testing Response Format for Frontend Compatibility\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/email-templates/{$template->id}/preview");

        $json = $response->json();

        echo "\n  Full Response Structure:\n";
        echo json_encode($json, JSON_PRETTY_PRINT) . "\n";

        echo "\n  Checking Frontend Extraction Patterns:\n";

        // Pattern 1: Direct string
        if (is_string($json)) {
            echo "  ✅ Response is direct string (Pattern 1)\n";
        }

        // Pattern 2: { data: { preview: string } }
        if (isset($json['data']['preview']) && is_string($json['data']['preview'])) {
            echo "  ✅ Response format: { data: { preview: string } } (Pattern 2)\n";
        }

        // Pattern 3: { preview: string }
        if (isset($json['preview']) && is_string($json['preview'])) {
            echo "  ✅ Response format: { preview: string } (Pattern 3)\n";
        }

        // Pattern 4: { data: { html: string } }
        if (isset($json['data']['html']) && is_string($json['data']['html'])) {
            echo "  ✅ Response format: { data: { html: string } } (Pattern 4)\n";
        }

        echo "\n  Frontend should check:\n";
        echo "  - data.preview: " . (isset($json['data']['preview']) ? 'EXISTS' : 'MISSING') . "\n";
        echo "  - data.html: " . (isset($json['data']['html']) ? 'EXISTS' : 'MISSING') . "\n";
        echo "  - preview: " . (isset($json['preview']) ? 'EXISTS' : 'MISSING') . "\n";

        $response->assertOk();
        expect($json['data']['preview'])->toBeString();

        echo "\n✅ Response format verified\n\n";
    });
});

describe('DEBUG: Authorization & Error Handling', function () {
    it('requires admin role for save', function () {
        $staff = User::factory()->create(['role' => 'staff']);
        $template = EmailTemplate::factory()->create();

        echo "\n🔍 Testing Authorization: Staff attempting save\n";

        $response = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/v1/admin/email-templates/{$template->id}", [
                'subject' => 'Unauthorized attempt',
            ]);

        echo "  Status Code: {$response->status()}\n";

        $response->assertForbidden();

        echo "\n✅ Authorization working correctly\n\n";
    });

    it('requires authentication for preview', function () {
        $template = EmailTemplate::factory()->create();

        echo "\n🔍 Testing Authentication: Unauthenticated preview request\n";

        $response = $this->postJson("/api/v1/admin/email-templates/{$template->id}/preview");

        echo "  Status Code: {$response->status()}\n";

        $response->assertUnauthorized();

        echo "\n✅ Authentication working correctly\n\n";
    });

    it('handles non-existent template for save', function () {
        echo "\n🔍 Testing Error Handling: Non-existent template\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/admin/email-templates/99999', [
                'subject' => 'Test',
            ]);

        echo "  Status Code: {$response->status()}\n";

        $response->assertNotFound();

        echo "\n✅ Error handling working correctly\n\n";
    });
});
