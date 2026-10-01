<?php

declare(strict_types=1);

namespace Admin\Domain\Admin\Support;

/**
 * Best-effort reading of a `User-Agent` header.
 *
 * Deliberately hand-written rather than pulled in as a dependency. This is the
 * console's security view, not an analytics pipeline: the answer only has to be
 * good enough for a human deciding whether a sign-in is theirs, and a UA parser
 * package is a large recurring dependency (and its own update stream) for what
 * amounts to a dozen ordered substring checks.
 *
 * The matching order is the whole trick, and it is not arbitrary. Every browser
 * lies about being something else:
 *  - Every Chrome, Edge, Opera, Samsung Internet and Brave advertises
 *    `Safari/537.36` and `Chrome/` in the same string, because they all use
 *    WebKit. Check Safari first and the entire modern browser fleet reports as
 *    Safari.
 *  - Edge says `Edg/`, Chrome says `Chrome/`, and Chromium-based Opera says
 *    `OPR/` while older Opera says `Opera/`. Both Opera generations have to be
 *    checked, and the newer one first, or `Opera/` matches text that is not there
 *    and the old label wins.
 *  - Bots carry no OS. Reporting "Unknown" for them is right; guessing Windows
 *    from a crawler would put a fake location in a security table.
 *
 * Every method returns null rather than a guess when it cannot tell, because
 * "Unknown" in the UI is an honest answer and a wrong browser name is not.
 */
final class UserAgentParser
{
    /**
     * @return array{
     *     browser: string|null,
     *     os: string|null,
     *     device_type: string,
     *     is_bot: bool
     * }
     */
    public static function parse(?string $userAgent): array
    {
        $ua = trim((string) $userAgent);

        if ($ua === '') {
            return self::empty();
        }

        // Order matters; see the class docblock.
        $browser = self::firstMatch($ua, [
            // Newer Opera, Chrome-based, advertises OPR/.
            'OPR/' => 'Opera',
            'Opera' => 'Opera',
            'SamsungBrowser' => 'Samsung Internet',
            'YaBrowser' => 'Yandex',
            'Vivaldi' => 'Vivaldi',
            'Brave' => 'Brave',
            'Edg/' => 'Edge',
            'EdgA/' => 'Edge',
            'Edge/' => 'Edge',
            'CriOS' => 'Chrome',
            'Chrome/' => 'Chrome',
            'Firefox/' => 'Firefox',
            'FxiOS' => 'Firefox',
            'Safari/' => 'Safari',
        ]);

        $os = self::detectOs($ua);

        $isBot = (bool) preg_match('/bot|crawler|spider|slurp|curl|wget|httpie|python-requests|headlesschrome/i', $ua);

        // A crawler is not a desktop. Reporting a device type for it would invent
        // a machine that does not exist.
        if ($isBot) {
            return [
                'browser' => $browser ?? 'Bot',
                'os' => null,
                'device_type' => 'bot',
                'is_bot' => true,
            ];
        }

        return [
            'browser' => $browser,
            'os' => $os,
            'device_type' => self::detectDeviceType($ua),
            'is_bot' => false,
        ];
    }

    /**
     * Windows before Android, and Android before Linux. Every Android UA also
     * contains "Linux", and every Windows 10+ one contains "Windows NT 10.0" -
     * so checking Linux first would report a phone as a Linux desktop.
     */
    private static function detectOs(string $ua): ?string
    {
        $map = [
            'Windows' => 'Windows',
            'Android' => 'Android',
            // iPadOS reports as a Mac, so the iPad check has to come first and
            // cannot rely on "iPhone" alone.
            'iPhone' => 'iOS',
            'iPad' => 'iOS',
            'iPod' => 'iOS',
            'Mac OS X' => 'macOS',
            'Macintosh' => 'macOS',
            'CrOS' => 'ChromeOS',
            'Ubuntu' => 'Linux',
            'Linux' => 'Linux',
        ];

        return self::firstMatch($ua, $map);
    }

    /**
     * Tablet before mobile: an iPad's UA also contains "Mobile" on iPadOS, and
     * the Android tablet UAs carry "Android" without "Mobile". Checking mobile
     * first would file every tablet as a phone.
     */
    private static function detectDeviceType(string $ua): string
    {
        if (preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $ua) === 1) {
            return 'tablet';
        }

        if (preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone/i', $ua) === 1) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * @param array<string, string> $needles
     */
    private static function firstMatch(string $haystack, array $needles): ?string
    {
        foreach ($needles as $needle => $label) {
            if (stripos($haystack, (string) $needle) !== false) {
                return $label;
            }
        }

        return null;
    }

    /**
     * @return array{browser: string|null, os: string|null, device_type: string, is_bot: bool}
     */
    private static function empty(): array
    {
        return [
            'browser' => null,
            'os' => null,
            'device_type' => 'unknown',
            'is_bot' => false,
        ];
    }
}
