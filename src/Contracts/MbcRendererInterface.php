<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Contracts;

interface MbcRendererInterface
{
    /**
     * Capture screenshots of the given URL at specified viewports.
     *
     * @param string $url The URL to capture
     * @param array $viewports Viewport configurations e.g. ['desktop' => [1440, 900], 'mobile' => [375, 812]]
     * @return array<string, string> Viewport name => base64-encoded PNG screenshot
     */
    public function capture(string $url, array $viewports): array;
}
