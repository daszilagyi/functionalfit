<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmailTemplateRequest;
use App\Http\Requests\Admin\UpdateEmailTemplateRequest;
use App\Http\Requests\Admin\SendTestEmailRequest;
use App\Http\Responses\ApiResponse;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateVersion;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailTemplateController extends Controller
{
    public function __construct(
        private readonly MailService $mailService
    ) {}

    /**
     * List all email templates with pagination.
     *
     * GET /api/v1/admin/email-templates
     */
    public function index(Request $request): JsonResponse
    {
        $query = EmailTemplate::with('updatedBy');

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by slug or subject
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('slug', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $templates = $query->orderBy('slug')->paginate(20);

        return ApiResponse::success($templates);
    }

    /**
     * Show a specific email template with versions.
     *
     * GET /api/v1/admin/email-templates/{id}
     */
    public function show(int $id): JsonResponse
    {
        $template = EmailTemplate::with(['updatedBy', 'versions.createdBy'])
            ->findOrFail($id);

        return ApiResponse::success([
            'template' => $template,
            'available_variables' => EmailTemplate::getSupportedVariables(),
        ]);
    }

    /**
     * Create a new email template.
     *
     * POST /api/v1/admin/email-templates
     */
    public function store(StoreEmailTemplateRequest $request): JsonResponse
    {
        $template = EmailTemplate::create([
            'slug' => $request->input('slug'),
            'subject' => $request->input('subject'),
            'html_body' => $request->input('html_body'),
            'fallback_body' => $request->input('fallback_body'),
            'is_active' => $request->boolean('is_active', true),
            'updated_by' => auth()->id(),
            'version' => 1,
        ]);

        return ApiResponse::created(
            $template->load('updatedBy'),
            'Email template created successfully'
        );
    }

    /**
     * Update an email template.
     *
     * PUT /api/v1/admin/email-templates/{id}
     */
    public function update(UpdateEmailTemplateRequest $request, int $id): JsonResponse
    {
        $template = EmailTemplate::findOrFail($id);

        return DB::transaction(function () use ($request, $template) {
            // Create version snapshot before update
            $template->createVersion();

            $template->update([
                'subject' => $request->input('subject', $template->subject),
                'html_body' => $request->input('html_body', $template->html_body),
                'fallback_body' => $request->input('fallback_body', $template->fallback_body),
                'is_active' => $request->has('is_active')
                    ? $request->boolean('is_active')
                    : $template->is_active,
                'updated_by' => auth()->id(),
                'version' => $template->version + 1,
            ]);

            return ApiResponse::success(
                $template->fresh(['updatedBy', 'versions.createdBy']),
                'Email template updated successfully'
            );
        });
    }

    /**
     * Soft delete an email template.
     *
     * DELETE /api/v1/admin/email-templates/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $template = EmailTemplate::findOrFail($id);
        $template->delete();

        return ApiResponse::noContent();
    }

    /**
     * Preview a template with sample data.
     *
     * POST /api/v1/admin/email-templates/{id}/preview
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $template = EmailTemplate::findOrFail($id);

        $variables = $request->input('variables', $this->getSampleVariables());

        $preview = $this->mailService->preview($template, $variables);

        return ApiResponse::success([
            'preview' => $preview['html_body'],  // Return only the HTML string for frontend iframe
            'subject' => $preview['subject'],
            'fallback' => $preview['fallback_body'],
            'variables_used' => $variables,
        ]);
    }

    /**
     * Send a test email to a specified address.
     *
     * POST /api/v1/admin/email-templates/{id}/send-test
     */
    public function sendTest(SendTestEmailRequest $request, int $id): JsonResponse
    {
        $template = EmailTemplate::findOrFail($id);

        $testEmail = $request->input('email');
        $variables = $request->input('variables', $this->getSampleVariables());

        $this->mailService->sendTestEmail($template, $testEmail, $variables);

        return ApiResponse::success(
            ['recipient' => $testEmail],
            'Test email queued successfully'
        );
    }

    /**
     * Get all versions of a template.
     *
     * GET /api/v1/admin/email-templates/{id}/versions
     */
    public function getVersions(int $id): JsonResponse
    {
        $template = EmailTemplate::findOrFail($id);

        $versions = $template->versions()
            ->with('createdBy')
            ->orderBy('version', 'desc')
            ->get();

        return ApiResponse::success($versions);
    }

    /**
     * Restore a template from a specific version.
     *
     * POST /api/v1/admin/email-templates/{id}/restore/{versionId}
     */
    public function restore(int $id, int $versionId): JsonResponse
    {
        $template = EmailTemplate::findOrFail($id);
        $version = EmailTemplateVersion::where('email_template_id', $id)
            ->findOrFail($versionId);

        return DB::transaction(function () use ($template, $version) {
            $template->restoreFromVersion($version);

            return ApiResponse::success(
                $template->fresh(['updatedBy', 'versions.createdBy']),
                "Template restored to version {$version->version}"
            );
        });
    }

    /**
     * Get available template variables.
     *
     * GET /api/v1/admin/email-templates/variables
     */
    public function getVariables(): JsonResponse
    {
        return ApiResponse::success([
            'variables' => $this->mailService->getAvailableVariables(),
        ]);
    }

    /**
     * Get sample variables for template preview/testing.
     *
     * @return array<string, mixed>
     */
    private function getSampleVariables(): array
    {
        return [
            'user' => [
                'name' => 'Teszt Felhasznalo',
                'email' => 'teszt@example.com',
            ],
            'class' => [
                'title' => 'Morning Yoga',
                'starts_at' => '2024-12-01 09:00',
                'ends_at' => '2024-12-01 10:00',
                'room' => 'Studio A',
            ],
            'trainer' => [
                'name' => 'Kovacs Anna',
            ],
            'cancel_url' => 'https://functionalfit.hu/cancel/abc123',
            'confirm_url' => 'https://functionalfit.hu/confirm/abc123',
            'password_reset_url' => 'https://functionalfit.hu/reset/abc123',
            'status' => 'booked',
            'deleted_by' => 'Admin User',
            'modified_by' => 'Admin User',
            'old' => [
                'starts_at' => '2024-12-01 08:00',
            ],
            'new' => [
                'starts_at' => '2024-12-01 09:00',
            ],
            // Daily schedule template variables
            'date' => date('Y. F j.'),
            'events_count' => 3,
            'individual_count' => 2,
            'group_count' => 1,
            'events_table' => '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                <thead><tr style="background: #3b82f6; color: white;">
                <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Időpont</th>
                <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Típus</th>
                <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Vendég / Óra neve</th>
                <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Terem</th>
                </tr></thead><tbody>
                <tr style="background: #f8fafc;"><td style="padding: 8px; border: 1px solid #ddd;">09:00 - 10:00</td><td style="padding: 8px; border: 1px solid #ddd;">Személyi</td><td style="padding: 8px; border: 1px solid #ddd;">Teszt Ügyfél 1</td><td style="padding: 8px; border: 1px solid #ddd;">Kis Terem</td></tr>
                <tr style="background: #ffffff;"><td style="padding: 8px; border: 1px solid #ddd;">11:00 - 12:00</td><td style="padding: 8px; border: 1px solid #ddd;">Személyi</td><td style="padding: 8px; border: 1px solid #ddd;">Teszt Ügyfél 2</td><td style="padding: 8px; border: 1px solid #ddd;">Nagy Terem</td></tr>
                <tr style="background: #f8fafc;"><td style="padding: 8px; border: 1px solid #ddd;">14:00 - 15:00</td><td style="padding: 8px; border: 1px solid #ddd;">Csoportos</td><td style="padding: 8px; border: 1px solid #ddd;">CrossFit (5 fő)</td><td style="padding: 8px; border: 1px solid #ddd;">Főterem</td></tr>
                </tbody></table>',
            'events_list' => "- 09:00 - 10:00 | Személyi | Teszt Ügyfél 1 | Kis Terem\n- 11:00 - 12:00 | Személyi | Teszt Ügyfél 2 | Nagy Terem\n- 14:00 - 15:00 | Csoportos | CrossFit (5 fő) | Főterem",
        ];
    }
}
