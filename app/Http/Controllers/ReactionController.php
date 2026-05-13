<?php
declare(strict_types=1);

namespace PulsePress\Http\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use PulsePress\Http\RestException;
use PulsePress\Reactions\ReactionRepository;
use PulsePress\Reactions\Reactions;
use PulsePress\Reactions\UserHash;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ReactionController
{
    public function __construct(private ReactionRepository $repository)
    {
    }

    public function react(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $postId       = (int) $request->get_param('post_id');
        $reactionType = (string) $request->get_param('reaction_type');

        if (!$this->postIsPublic($postId)) {
            return new WP_Error(
                'pulsepress_post_not_found',
                __('Post not found or not publicly viewable.', 'pulsepress'),
                ['status' => 404]
            );
        }

        if (!Reactions::isValid($reactionType)) {
            return new WP_Error(
                'pulsepress_invalid_reaction_type',
                __('Reaction type is not in the allowlist.', 'pulsepress'),
                ['status' => 422]
            );
        }

        $userHash = UserHash::fromRequest($request);

        try {
            do_action('pulsepress_before_react', $postId, $reactionType, $userHash, $request);
        } catch (RestException $e) {
            return $e->getError();
        }

        $now    = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $status = $this->repository->replace($postId, $reactionType, $userHash, $now);

        $this->repository->invalidateCounts($postId);
        do_action('pulsepress_after_react', $postId, $reactionType, $userHash, $status);

        return new WP_REST_Response([
            'post_id'       => $postId,
            'reaction_type' => $reactionType,
            'status'        => $status,
            'counts'        => (object) $this->repository->countsForPost($postId),
        ], 200);
    }

    public function counts(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $postId = (int) $request->get_param('post_id');

        if (!$this->postIsPublic($postId)) {
            return new WP_Error(
                'pulsepress_post_not_found',
                __('Post not found or not publicly viewable.', 'pulsepress'),
                ['status' => 404]
            );
        }

        $counts = (object) $this->repository->countsForPost($postId);

        return new WP_REST_Response([
            'post_id' => $postId,
            'counts'  => $counts,
            'cached'  => $this->repository->lastReadWasCached(),
        ], 200);
    }

    private function postIsPublic(int $postId): bool
    {
        if ($postId <= 0) {
            return false;
        }
        $status = get_post_status($postId);
        if ($status === false) {
            return false;
        }
        return is_post_publicly_viewable($postId);
    }
}
