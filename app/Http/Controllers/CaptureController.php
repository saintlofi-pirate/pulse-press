<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Http\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use Moonfarmer\ReactionsLeadCapture\Captures\CaptureInput;
use Moonfarmer\ReactionsLeadCapture\Captures\CaptureRecord;
use Moonfarmer\ReactionsLeadCapture\Captures\CaptureRepository;
use Moonfarmer\ReactionsLeadCapture\Captures\Captures;
use Moonfarmer\ReactionsLeadCapture\Http\RestException;
use Moonfarmer\ReactionsLeadCapture\Reactions\Reactions;
use Moonfarmer\ReactionsLeadCapture\Reactions\UserHash;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;


if (!defined('ABSPATH')) {
    exit;
}

final class CaptureController
{
    private CaptureRepository $repository;

    public function __construct(CaptureRepository $repository)
    {
        $this->repository = $repository;
    }

    public function capture(WP_REST_Request $request)
    {
        $postId       = (int) $request->get_param('post_id');
        $rawEmail     = (string) $request->get_param('email');
        $reactionType = (string) $request->get_param('reaction_type');
        $consent      = $request->get_param('consent');
        $source       = (string) ($request->get_param('source') ?: 'inline');

        if (!$this->postIsPublic($postId)) {
            return new WP_Error(
                'moonfarmer_reactions_lead_capture_post_not_found',
                __('That post is not available for capturing right now.', 'moonfarmer-reactions-lead-capture'),
                ['status' => 404]
            );
        }

        if (!Reactions::isValid($reactionType)) {
            return new WP_Error(
                'moonfarmer_reactions_lead_capture_invalid_reaction_type',
                __('That reaction type is not available on this site.', 'moonfarmer-reactions-lead-capture'),
                ['status' => 422]
            );
        }

        if ($consent !== true) {
            return new WP_Error(
                'moonfarmer_reactions_lead_capture_consent_required',
                __('You must agree to the consent statement before submitting your email.', 'moonfarmer-reactions-lead-capture'),
                ['status' => 422]
            );
        }

        if (!Captures::isValidSource($source)) {
            return new WP_Error(
                'moonfarmer_reactions_lead_capture_invalid_source',
                __('That capture source is not allowed.', 'moonfarmer-reactions-lead-capture'),
                ['status' => 422]
            );
        }

        $email = strtolower(trim($rawEmail));
        $email = (string) apply_filters('moonfarmer_reactions_lead_capture_capture_email', $email, $request);

        if ($email === '' || strlen($email) > Captures::EMAIL_MAX_LENGTH || !is_email($email)) {
            return new WP_Error(
                'moonfarmer_reactions_lead_capture_invalid_email',
                __('Please enter a valid email address.', 'moonfarmer-reactions-lead-capture'),
                ['status' => 422]
            );
        }

        $fingerprint = UserHash::captureFingerprintFromRequest($request);

        try {
            do_action('moonfarmer_reactions_lead_capture_before_capture', $postId, $email, $reactionType, $request);
        } catch (RestException $e) {
            return $e->getError();
        }

        $now      = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $purgeAt  = $now->modify('+' . Captures::PURGE_DAYS . ' days');
        $version  = Captures::consentTextVersion();

        $input = new CaptureInput(
            $postId,
            $email,
            $reactionType,
            $source,
            $version,
            $now,
            $fingerprint['ipHash'],
            $fingerprint['userAgentHash'],
            $purgeAt
        );

        $record = $this->repository->store($input);

        if ($record->status === CaptureRecord::STATUS_ALREADY_EXISTS) {
            return new WP_Error(
                'moonfarmer_reactions_lead_capture_capture_already_exists',
                __('We already have your email saved for this post.', 'moonfarmer-reactions-lead-capture'),
                ['status' => 409]
            );
        }

        do_action('moonfarmer_reactions_lead_capture_after_capture', $record->id, $postId, $email, $reactionType, $version);

        return new WP_REST_Response([
            'post_id'       => $postId,
            'reaction_type' => $reactionType,
            'email'         => $email,
            'status'        => $record->status,
            'capture_id'    => $record->id,
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
