<?php
declare(strict_types=1);

use Moonfarmer\ReactionsLeadCapture\Captures\FraudPurger;
use Tests\Stubs\WpdbStub;

it('issues the expected UPDATE statement with the NOW() and NULL guard', function () {
    $wpdb = new WpdbStub();
    $wpdb->rows_affected = 3;
    $purger = new FraudPurger($wpdb);

    $affected = $purger->run();

    expect($affected)->toBe(3);
    expect($wpdb->last_query)
        ->toContain('UPDATE wp_moonfarmer_reactions_lead_capture_captures')
        ->toContain('SET ip_hash = NULL')
        ->toContain('user_agent_hash = NULL')
        ->toContain('fraud_metadata_purge_at <= NOW()')
        ->toContain('ip_hash IS NOT NULL OR user_agent_hash IS NOT NULL');
});

it('returns zero on a no-op rerun', function () {
    $wpdb = new WpdbStub();
    $wpdb->rows_affected = 0;
    $purger = new FraudPurger($wpdb);

    expect($purger->run())->toBe(0);
});
