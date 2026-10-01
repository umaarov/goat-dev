<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CspReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $body = substr((string) $request->getContent(), 0, 4096);
        $report = json_decode($body, true);

        if (is_array($report)) {
            $r = $report['csp-report'] ?? $report;
            Log::channel('audit_trail')->notice('[CSP] violation', [
                'blocked' => substr((string) ($r['blocked-uri'] ?? $r['blockedURL'] ?? ''), 0, 200),
                'directive' => substr((string) ($r['effective-directive'] ?? $r['violated-directive'] ?? ''), 0, 80),
                'document' => substr((string) ($r['document-uri'] ?? $r['documentURL'] ?? ''), 0, 200),
            ]);
        }

        return response()->noContent();
    }
}
