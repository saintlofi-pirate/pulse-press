# aggregation

Daily reaction aggregation. Walks moonfarmer_reactions_lead_capture_reactions for a target site-local date and upserts grouped counts into moonfarmer_reactions_lead_capture_daily_agg via ON DUPLICATE KEY UPDATE. WP-Cron-first behind a QueueScheduler abstraction. Idempotent and timezone-correct.
