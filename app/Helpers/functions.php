<?php
declare(strict_types=1);



if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('moonfarmer_reactions_lead_capture')) {
    function moonfarmer_reactions_lead_capture(): \Moonfarmer\ReactionsLeadCapture\Core\Application
    {
        $app = \Moonfarmer\ReactionsLeadCapture\Core\Application::getInstance();
        if ($app === null) {
            throw new RuntimeException('Moonfarmer Reactions Lead Capture application has not booted yet.');
        }
        return $app;
    }
}
