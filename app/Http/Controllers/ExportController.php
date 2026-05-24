<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Http\Controllers;

use DateTimeImmutable;
use Moonfarmer\ReactionsLeadCapture\Captures\CaptureExporter;
use Moonfarmer\ReactionsLeadCapture\Http\RestException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;


if (!defined('ABSPATH')) {
    exit;
}

final class ExportController
{
    private CaptureExporter $exporter;

    public function __construct(CaptureExporter $exporter)
    {
        $this->exporter = $exporter;
    }

    public function download(WP_REST_Request $request)
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error('rest_forbidden', __('You do not have permission to export captures.', 'moonfarmer-reactions-lead-capture'), ['status' => 403]);
        }

        $timestamp = (new DateTimeImmutable('now', wp_timezone()))->format('Ymd\THis\Z');
        $filename  = sprintf('moonfarmer-reactions-lead-capture-captures-%s.csv', $timestamp);

        nocache_headers();
        header_remove('Content-Type');
        header('Content-Type: text/csv; charset=utf-8');
        header(sprintf('Content-Disposition: attachment; filename=%s', $filename));
        header('X-Content-Type-Options: nosniff');

        // UTF-8 BOM for Excel compatibility.
        echo "\xEF\xBB\xBF";

        try {
            $this->exporter->stream(static function (string $line): void {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV rows are escaped by CaptureExporter::packLine().
                echo $line;
            }, ['request' => $request]);
        } catch (RestException $e) {
            // Reset output buffer attempt for the error path — but we've already sent headers.
            // Return a WP_Error so callers see something sensible if they intercept the response.
            return $e->getError();
        }

        if (!defined('MOONFARMER_REACTIONS_LEAD_CAPTURE_EXPORT_NO_DIE') || MOONFARMER_REACTIONS_LEAD_CAPTURE_EXPORT_NO_DIE !== true) {
            wp_die('', '', ['response' => 200]);
        }

        return new WP_REST_Response(null, 200);
    }
}
