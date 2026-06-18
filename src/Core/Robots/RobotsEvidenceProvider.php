<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Robots;

/**
 * Supplies robots.txt evidence for a URL.
 *
 * Lives in Core as an abstraction so the analyzer stays decoupled from how the
 * evidence is acquired — mirroring the {@see \VisibilityDetector\Core\Page\PageFetcher}
 * seam. The concrete HTTP implementation lives in the Adapters layer.
 */
interface RobotsEvidenceProvider
{
    /**
     * Return robots evidence for the given URL. Implementations must never throw
     * for a missing or unfetchable robots.txt — they return "allowed" evidence
     * with a warning instead.
     */
    public function evidenceFor(string $url): RobotsEvidence;
}
