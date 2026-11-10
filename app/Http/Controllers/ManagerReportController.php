<?php

namespace App\Http\Controllers;

use App\Models\DriverIssueRequest;
use App\Services\DriverReportReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ManagerReportController extends Controller
{
    public function show(Request $request, DriverIssueRequest $issue)
    {
        abort_unless((int) $issue->organization_id === (int) $request->user()->organization_id, 404);
        $issue->load(['driver', 'queueEntry', 'reviewer']);

        return view('managers.portal.report', compact('issue'));
    }

    public function review(Request $request, DriverIssueRequest $issue, DriverReportReviewService $reviews)
    {
        abort_unless((int) $issue->organization_id === (int) $request->user()->organization_id, 404);
        $attributes = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'decision_reason' => ['required', 'string', 'max:500'],
            'confirm_removal' => $issue->queue_entry_id !== null
                ? ['required_if:decision,approved', 'boolean', 'accepted_if:decision,approved']
                : ['sometimes', 'boolean'],
        ]);
        $reviews->review($request->user(), $issue, $attributes);

        return redirect()->route('manager.reports.show', $issue)->with('status', 'Report reviewed successfully. The driver can now see your decision.');
    }

    // Short-lived signed links allow private previews without exposing API tokens.
    public function attachment(DriverIssueRequest $issue, string $attachment)
    {
        abort_unless(ctype_digit($attachment), 404);
        $file = ($issue->attachments ?? [])[(int) $attachment] ?? null;
        abort_unless($file && Storage::disk('local')->exists($file['path']), 404);

        $mime = Storage::disk('local')->mimeType($file['path']);
        $image = in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);

        return Storage::disk('local')->response($file['path'], $file['name'], [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ], $image ? 'inline' : 'attachment');
    }
}
