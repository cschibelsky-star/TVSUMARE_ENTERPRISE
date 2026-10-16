<?php
/**
 * TV Sumaré Premium: shared server-side limits for commercial placements.
 * Every MP4 must be inspected with ffprobe before publication.
 */
const TVS_PREMIUM_MAX_VIDEO_SECONDS = 50.0;
const TVS_PREMIUM_BANNER_SECONDS = 10;

function tvs_premium_video_duration_valid($duration): bool {
    return is_numeric($duration)
        && is_finite((float)$duration)
        && (float)$duration > 0
        && (float)$duration <= TVS_PREMIUM_MAX_VIDEO_SECONDS;
}
