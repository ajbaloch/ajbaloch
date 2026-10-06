<?php

declare(strict_types=1);

/**
 * Fetch one public page and pull WhatsApp invite links out of it.
 * Private, loopback, and link-local addresses are refused before the request.
 */

final class ScanException extends RuntimeException
{
}

function is_public_ip(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return false;
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }
        $cgnatStart = ip2long('100.64.0.0');
        $cgnatEnd = ip2long('100.127.255.255');
        if ($long >= $cgnatStart && $long <= $cgnatEnd) {
            return false;
        }
        return true;
    }

    $packed = inet_pton($ip);
    if ($packed === false || strlen($packed) !== 16) {
        return false;
    }

    // IPv4-mapped IPv6 (::ffff:a.b.c.d) must be judged as the inner IPv4 address.
    $v4prefix = str_repeat("\x00", 10) . "\xff\xff";
    if (str_starts_with($packed, $v4prefix)) {
        $v4 = inet_ntop(substr($packed, 12));
        return is_string($v4) && is_public_ip($v4);
    }

    return true;
}

function blocked_scan_host(string $host): bool
{
    $host = strtolower(rtrim($host, '.'));
    if ($host === '' || $host === 'localhost' || $host === 'localhost.localdomain') {
        return true;
    }

    $suffixes = ['.localhost', '.local', '.internal', '.localdomain'];
    foreach ($suffixes as $suffix) {
        if (str_ends_with($host, $suffix)) {
            return true;
        }
    }

    return $host === 'metadata.google.internal' || str_ends_with($host, '.metadata.google.internal');
}

function resolve_public_ips(string $host): array
{
    $host = trim($host, "[] \t");
    if ($host === '' || blocked_scan_host($host)) {
        throw new ScanException('This is not a public http or https address.');
    }

    if (preg_match('/^[0-9]+$/', $host) || str_starts_with(strtolower($host), '0x')) {
        throw new ScanException('This is not a public http or https address.');
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (!is_public_ip($host)) {
            throw new ScanException('This is not a public http or https address.');
        }
        return [$host];
    }

    if (!preg_match('/^[A-Za-z0-9.-]+$/', $host)) {
        throw new ScanException('This is not a public http or https address.');
    }

    $ips = [];
    $records = @dns_get_record($host, DNS_A + DNS_AAAA);
    if (is_array($records)) {
        foreach ($records as $record) {
            if (!empty($record['ip'])) {
                $ips[] = (string) $record['ip'];
            }
            if (!empty($record['ipv6'])) {
                $ips[] = (string) $record['ipv6'];
            }
        }
    }
    if (!$ips) {
        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = $v4;
        }
    }

    $ips = array_values(array_unique($ips));
    if (!$ips) {
        throw new ScanException('The page could not be opened.');
    }
    foreach ($ips as $ip) {
        if (!is_public_ip($ip)) {
            throw new ScanException('This is not a public http or https address.');
        }
    }

    return $ips;
}

function inspect_public_url(string $url): array
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 2000 || preg_match('/[\s\x00-\x1F\\\\]/', $url)) {
        throw new ScanException('This is not a public http or https address.');
    }

    $parts = parse_url($url);
    if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
        throw new ScanException('This is not a public http or https address.');
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if ($scheme !== 'http' && $scheme !== 'https') {
        throw new ScanException('This is not a public http or https address.');
    }

    $host = (string) ($parts['host'] ?? '');
    $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
    $expected = $scheme === 'https' ? 443 : 80;
    if ($port !== $expected) {
        throw new ScanException('This is not a public http or https address.');
    }

    $ips = resolve_public_ips($host);
    return [
        'url' => $url,
        'scheme' => $scheme,
        'host' => trim($host, '[]'),
        'port' => $port,
        'ip' => $ips[0],
    ];
}

function resolve_redirect(string $base, string $location): string
{
    $location = trim($location);
    if ($location === '') {
        throw new ScanException('The page could not be opened.');
    }
    if (preg_match('#^https?://#i', $location)) {
        return $location;
    }

    $parts = parse_url($base);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        throw new ScanException('The page could not be opened.');
    }
    $origin = $parts['scheme'] . '://' . $parts['host'];
    if (isset($parts['port'])) {
        $origin .= ':' . $parts['port'];
    }
    if (str_starts_with($location, '//')) {
        return $parts['scheme'] . ':' . $location;
    }
    if (str_starts_with($location, '/')) {
        return $origin . $location;
    }
    if (str_starts_with($location, '?')) {
        $path = (string) ($parts['path'] ?? '/');
        return $origin . $path . $location;
    }

    $path = (string) ($parts['path'] ?? '/');
    $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
    return $origin . ($dir === '' ? '' : $dir) . '/' . $location;
}

function http_get_pinned(array $target, float $timeout): array
{
    if (!function_exists('curl_init')) {
        throw new ScanException('The page could not be opened.');
    }

    $host = $target['host'];
    $port = (int) $target['port'];
    $ip = $target['ip'];
    $resolveIp = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
    $body = '';
    $status = 0;
    $location = null;

    $ch = curl_init($target['url']);
    if ($ch === false) {
        throw new ScanException('The page could not be opened.');
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT => max(1, (int) ceil($timeout)),
        CURLOPT_CONNECTTIMEOUT => min(5, max(1, (int) ceil($timeout))),
        CURLOPT_USERAGENT => 'WhatsGrolinkBot/1.0',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $resolveIp],
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
            $body .= $chunk;
            if (strlen($body) > 1000000) {
                return 0;
            }
            return strlen($chunk);
        },
        CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$status, &$location): int {
            $trim = trim($header);
            if (preg_match('#^HTTP/\d(?:\.\d)?\s+(\d+)#', $trim, $match)) {
                $status = (int) $match[1];
                $location = null;
            } elseif (stripos($trim, 'Location:') === 0) {
                $location = trim(substr($trim, 9));
            }
            return strlen($header);
        },
    ];
    curl_setopt_array($ch, $options);

    $ok = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($ok === false && strlen($body) > 1000000) {
        $body = substr($body, 0, 1000000);
    } elseif ($ok === false && $errno !== 0 && $status === 0) {
        throw new ScanException('The page could not be opened.');
    }

    if (in_array($status, [301, 302, 303, 307, 308], true)) {
        if ($location === null || $location === '') {
            throw new ScanException('The page could not be opened.');
        }
        return ['redirect' => $location, 'body' => ''];
    }

    if ($status < 200 || $status >= 300) {
        throw new ScanException('The page could not be opened.');
    }

    return ['redirect' => null, 'body' => $body];
}

function fetch_public_html(string $url): string
{
    $deadline = microtime(true) + 10.0;
    $current = $url;

    for ($hop = 0; $hop <= 3; $hop++) {
        $remaining = $deadline - microtime(true);
        if ($remaining < 0.4) {
            throw new ScanException('The page took too long to open.');
        }

        $target = inspect_public_url($current);
        $result = http_get_pinned($target, $remaining);
        if ($result['redirect'] === null) {
            return $result['body'];
        }
        if ($hop === 3) {
            throw new ScanException('This page has too many redirects.');
        }
        $current = resolve_redirect($current, $result['redirect']);
    }

    throw new ScanException('The page could not be opened.');
}

function clean_scan_name(string $value): string
{
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    $value = trim($value, " \t\n\r\0\x0B-–—|:•");
    if (mb_strlen($value) > 120) {
        $value = mb_substr($value, 0, 120);
    }
    return trim($value);
}

function generic_scan_name(string $name): bool
{
    $key = mb_strtolower(trim($name));
    $generic = [
        'join', 'join group', 'join chat', 'click', 'click here', 'here', 'link',
        'whatsapp', 'whatsapp group', 'chat', 'group', 'open', 'share', 'tap here',
        'شامل ہوں', 'جوائن', 'یہاں کلک کریں', 'لنک', 'گروپ', 'واٹس ایپ',
    ];
    return $key === '' || mb_strlen($key) < 2 || in_array($key, $generic, true);
}

function generic_scan_category(string $name): bool
{
    $key = mb_strtolower(trim($name));
    $generic = [
        'home', 'homepage', 'main', 'menu', 'navigation', 'breadcrumb', 'breadcrumbs',
        'categories', 'category', 'search', 'more', 'links', 'directory', 'all',
        'whatsgrolink', 'whatsgrolink.com', 'untitled',
    ];
    return generic_scan_name($name) || in_array($key, $generic, true);
}

function pick_scan_category(string $heading, string $sectionTitle, string $breadcrumb, string $pageTitle, string $groupName): string
{
    $groupKey = mb_strtolower(trim($groupName));
    $fallback = '';
    foreach ([$heading, $sectionTitle, $breadcrumb, $pageTitle] as $candidate) {
        $candidate = clean_scan_name($candidate);
        if (generic_scan_category($candidate)) {
            continue;
        }
        if (mb_strlen($candidate) > 80) {
            continue;
        }
        if ($fallback === '') {
            $fallback = $candidate;
        }
        if (mb_strtolower($candidate) !== $groupKey) {
            return $candidate;
        }
    }
    return $fallback !== '' ? $fallback : 'Imported';
}

function nearest_heading(string $htmlBefore): string
{
    if (!preg_match_all('/<h[1-3]\b[^>]*>(.*?)<\/h[1-3]>/is', $htmlBefore, $matches)) {
        return '';
    }
    $last = (string) end($matches[1]);
    return clean_scan_name($last);
}

function fallback_group_name(string $inviteUrl): string
{
    $path = (string) (parse_url($inviteUrl, PHP_URL_PATH) ?? '');
    $code = substr(preg_replace('/[^A-Za-z0-9]/', '', $path) ?? '', -4);
    if ($code === '') {
        return 'WhatsApp group';
    }
    return 'WhatsApp group ' . $code;
}

function raw_image_src(DOMElement $img): string
{
    foreach (['src', 'data-src', 'data-original'] as $attr) {
        $src = trim($img->getAttribute($attr));
        if ($src !== '') {
            return html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    $srcset = trim($img->getAttribute('srcset'));
    if ($srcset !== '' && preg_match('/\s*(\S+)/', $srcset, $match)) {
        return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return '';
}

function resolve_scan_image(string $src, string $pageUrl): string
{
    $src = trim($src);
    $lower = strtolower($src);
    if ($src === '' || str_starts_with($lower, 'data:') || str_starts_with($lower, 'javascript:')) {
        return '';
    }
    if (str_starts_with($src, '//')) {
        $src = 'https:' . $src;
    } elseif (!preg_match('#^https?://#i', $src)) {
        if ($pageUrl === '') {
            return '';
        }
        try {
            $src = resolve_redirect($pageUrl, $src);
        } catch (ScanException) {
            return '';
        }
    }
    $normalized = normalize_image_url($src);
    return is_string($normalized) ? $normalized : '';
}

function image_from_element(DOMElement $img, string $pageUrl): string
{
    $width = (int) $img->getAttribute('width');
    $height = (int) $img->getAttribute('height');
    if (($width > 0 && $width < 32) || ($height > 0 && $height < 32)) {
        return '';
    }
    return resolve_scan_image(raw_image_src($img), $pageUrl);
}

function first_image_in(DOMNode $node, string $pageUrl): string
{
    if ($node instanceof DOMElement && strtolower($node->tagName) === 'img') {
        return image_from_element($node, $pageUrl);
    }
    if (!$node instanceof DOMElement) {
        return '';
    }
    foreach ($node->getElementsByTagName('img') as $img) {
        if ($img instanceof DOMElement) {
            $url = image_from_element($img, $pageUrl);
            if ($url !== '') {
                return $url;
            }
        }
    }
    return '';
}

function parent_has_other_invites(DOMElement $parent, DOMElement $anchor): bool
{
    foreach ($parent->getElementsByTagName('a') as $link) {
        if (!$link instanceof DOMElement || $link->isSameNode($anchor)) {
            continue;
        }
        if (normalize_invite_url($link->getAttribute('href')) !== null) {
            return true;
        }
    }
    return false;
}

function nearby_image_url(DOMElement $anchor, string $pageUrl): string
{
    $inside = first_image_in($anchor, $pageUrl);
    if ($inside !== '') {
        return $inside;
    }

    $sibling = $anchor->previousSibling;
    while ($sibling !== null && !$sibling instanceof DOMElement) {
        $sibling = $sibling->previousSibling;
    }
    if ($sibling instanceof DOMElement) {
        $url = first_image_in($sibling, $pageUrl);
        if ($url !== '') {
            return $url;
        }
    }

    $parent = $anchor->parentNode;
    if ($parent instanceof DOMElement && !parent_has_other_invites($parent, $anchor)) {
        $url = first_image_in($parent, $pageUrl);
        if ($url !== '') {
            return $url;
        }
        $grand = $parent->parentNode;
        if (
            $grand instanceof DOMElement
            && !in_array(strtolower($grand->tagName), ['body', 'html', 'main'], true)
            && !parent_has_other_invites($grand, $anchor)
        ) {
            return first_image_in($grand, $pageUrl);
        }
    }

    return '';
}

function nearest_image_from_html(string $htmlAround, string $pageUrl): string
{
    if (!preg_match_all('#<img\b[^>]*\b(?:src|data-src)\s*=\s*["\']([^"\']+)#i', $htmlAround, $matches)) {
        return '';
    }
    return resolve_scan_image((string) end($matches[1]), $pageUrl);
}

function consider_invite(
    array &$found,
    string $rawUrl,
    string $linkText,
    string $heading,
    string $sectionTitle = '',
    string $breadcrumb = '',
    string $pageTitle = '',
    string $imageUrl = ''
): void {
    $rawUrl = rtrim($rawUrl, '.,);]>"\'');
    $normalized = normalize_invite_url($rawUrl);
    if ($normalized === null || isset($found[$normalized]) || count($found) >= 100) {
        return;
    }

    $name = clean_scan_name($linkText);
    if (generic_scan_name($name)) {
        $name = clean_scan_name($heading);
    }
    if (generic_scan_name($name)) {
        $name = fallback_group_name($normalized);
    }

    $image = normalize_image_url($imageUrl);
    $found[$normalized] = [
        'name' => $name,
        'invite_url' => $normalized,
        'category' => pick_scan_category($heading, $sectionTitle, $breadcrumb, $pageTitle, $name),
        'image_url' => is_string($image) ? $image : '',
    ];
}

function is_breadcrumb_element(DOMElement $node): bool
{
    $label = strtolower($node->getAttribute('aria-label'));
    $class = strtolower($node->getAttribute('class'));
    $itemtype = strtolower($node->getAttribute('itemtype'));
    return str_contains($label, 'breadcrumb')
        || str_contains($class, 'breadcrumb')
        || str_contains($itemtype, 'breadcrumblist');
}

function breadcrumb_label(DOMElement $node): string
{
    $items = [];
    foreach ($node->getElementsByTagName('li') as $li) {
        if (!$li instanceof DOMElement) {
            continue;
        }
        $text = clean_scan_name($li->textContent);
        if (!generic_scan_category($text)) {
            $items[] = $text;
        }
    }
    if (!$items) {
        foreach ($node->getElementsByTagName('a') as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }
            $text = clean_scan_name($anchor->textContent);
            if (!generic_scan_category($text)) {
                $items[] = $text;
            }
        }
    }
    if (!$items) {
        return '';
    }
    return (string) $items[count($items) - 1];
}

function find_breadcrumb(DOMNode $node): string
{
    if ($node instanceof DOMElement && is_breadcrumb_element($node)) {
        return breadcrumb_label($node);
    }
    foreach ($node->childNodes as $child) {
        $found = find_breadcrumb($child);
        if ($found !== '') {
            return $found;
        }
    }
    return '';
}

function page_title_text(DOMDocument $dom): string
{
    $titles = $dom->getElementsByTagName('title');
    if ($titles->length === 0) {
        return '';
    }
    $title = clean_scan_name($titles->item(0)->textContent ?? '');
    return mb_strlen($title) > 80 ? '' : $title;
}

function extract_invites_from_html(string $html, string $pageUrl = ''): array
{
    $found = [];
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $pageTitle = '';
    $breadcrumb = '';
    if ($loaded && $dom->documentElement instanceof DOMNode) {
        $pageTitle = page_title_text($dom);
        $breadcrumb = find_breadcrumb($dom->documentElement);
        $heading = '';
        $h1 = '';
        $sectionStack = [];
        $walk = static function (DOMNode $node) use (&$walk, &$heading, &$h1, &$sectionStack, &$found, $breadcrumb, $pageTitle, $pageUrl): void {
            if (count($found) >= 100) {
                return;
            }
            $pushed = false;
            $skipChildren = false;
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (is_breadcrumb_element($node)) {
                    $skipChildren = true;
                }
                if (in_array($tag, ['section', 'article'], true)) {
                    $sectionStack[] = '';
                    $pushed = true;
                }
                if (in_array($tag, ['h1', 'h2', 'h3'], true)) {
                    $text = clean_scan_name($node->textContent);
                    $heading = $text;
                    if ($tag === 'h1' && $h1 === '') {
                        $h1 = $text;
                    }
                    if ($sectionStack !== []) {
                        $index = count($sectionStack) - 1;
                        if ($sectionStack[$index] === '') {
                            $sectionStack[$index] = $text;
                        }
                    }
                }
                if ($tag === 'a' && !$skipChildren) {
                    $sectionTitle = '';
                    for ($i = count($sectionStack) - 1; $i >= 0; $i--) {
                        if ($sectionStack[$i] !== '') {
                            $sectionTitle = $sectionStack[$i];
                            break;
                        }
                    }
                    consider_invite(
                        $found,
                        $node->getAttribute('href'),
                        $node->textContent,
                        $heading,
                        $sectionTitle,
                        $breadcrumb,
                        $h1 !== '' ? $h1 : $pageTitle,
                        nearby_image_url($node, $pageUrl)
                    );
                }
            }
            if (!$skipChildren) {
                foreach ($node->childNodes as $child) {
                    $walk($child);
                }
            }
            if ($pushed) {
                array_pop($sectionStack);
            }
        };
        $walk($dom->documentElement);
    }

    if (preg_match_all('#https?://(?:chat\.whatsapp\.com|wa\.me)/[^\s<>"\']+#i', $html, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            if (count($found) >= 100) {
                break;
            }
            $raw = (string) $match[0];
            $offset = (int) $match[1];
            $before = substr($html, max(0, $offset - 1500), min(1500, $offset));
            $heading = nearest_heading($before);
            consider_invite($found, $raw, '', $heading, '', $breadcrumb, $pageTitle, nearest_image_from_html($before, $pageUrl));
        }
    }

    return array_values($found);
}
