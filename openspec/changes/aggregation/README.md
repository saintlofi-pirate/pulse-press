# aggregation

Daily reaction aggregation. Walks pulsepress_reactions for a target site-local date and upserts grouped counts into pulsepress_daily_agg via ON DUPLICATE KEY UPDATE. WP-Cron-first behind a QueueScheduler abstraction. Idempotent and timezone-correct.
