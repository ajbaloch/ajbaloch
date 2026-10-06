<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/scan.php';
require_admin();

$errors = [];
$preview = $_SESSION['scan_preview'] ?? null;
if (!is_array($preview)) {
    $preview = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'scan') {
        $pageUrl = trim((string) ($_POST['page_url'] ?? ''));
        try {
            $html = fetch_public_html($pageUrl);
            $items = extract_invites_from_html($html, $pageUrl);
            if (!$items) {
                $errors[] = 'No invite links were found on this page.';
                unset($_SESSION['scan_preview']);
                $preview = null;
            } else {
                $urls = array_column($items, 'invite_url');
                $placeholders = implode(',', array_fill(0, count($urls), '?'));
                $stmt = db()->prepare('SELECT invite_url FROM groups WHERE invite_url IN (' . $placeholders . ')');
                $stmt->execute($urls);
                $existing = [];
                foreach ($stmt->fetchAll() as $row) {
                    $existing[(string) $row['invite_url']] = true;
                }
                foreach ($items as &$item) {
                    $categoryName = clean_text((string) ($item['category'] ?? ''), 120);
                    if (mb_strlen($categoryName) < 2) {
                        $categoryName = 'Imported';
                    }
                    $item['category'] = $categoryName;
                    $item['exists'] = isset($existing[$item['invite_url']]);
                    $item['category_new'] = lookup_category_id($categoryName) === null;
                }
                unset($item);
                $preview = [
                    'page_url' => $pageUrl,
                    'capped' => count($items) >= 100,
                    'items' => $items,
                ];
                $_SESSION['scan_preview'] = $preview;
            }
        } catch (ScanException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($action === 'import') {
        $stored = $_SESSION['scan_preview'] ?? null;
        if (!is_array($stored) || empty($stored['items'])) {
            $errors[] = 'Scan a page first.';
        } else {
            $added = 0;
            $skipped = 0;
            $categoriesAdded = 0;
            $description = 'Imported from a public page on WhatsGrolink.com.';
            $exists = db()->prepare('SELECT id FROM groups WHERE invite_url = ?');
            foreach ($stored['items'] as $item) {
                $invite = normalize_invite_url((string) ($item['invite_url'] ?? '')) ?? '';
                $name = clean_text((string) ($item['name'] ?? ''), 120);
                if ($invite === '' || $name === '') {
                    $skipped++;
                    continue;
                }
                $exists->execute([$invite]);
                if ($exists->fetch()) {
                    $skipped++;
                    continue;
                }
                $category = find_or_create_category((string) ($item['category'] ?? ''));
                if (!empty($category['created'])) {
                    $categoriesAdded++;
                }
                $image = normalize_image_url(trim((string) ($item['image_url'] ?? '')));
                if (!is_string($image) || $image === '') {
                    $image = null;
                }
                $saved = create_group($name, $invite, (int) $category['id'], $description, $image, 'approved');
                if ($saved) {
                    $added++;
                } else {
                    $skipped++;
                }
            }
            unset($_SESSION['scan_preview']);
            set_flash('New groups saved: ' . $added . '. Skipped: ' . $skipped . '. Categories added: ' . $categoriesAdded . '.');
            redirect(href('admin/scan.php'));
        }
    }
}

$pageTitle = 'Scan website';
$noindex = true;
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<h1>Scan website</h1>
<p class="lede">Paste one public page URL. The site fetches it once, reads invite links, and takes each category from a nearby heading, breadcrumb, or section title. A missing category is created on import. Private or internal addresses are refused.</p>
<?php if ($errors): ?>
    <ul class="errors"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<form class="stack-form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="scan">
    <div>
        <label for="page_url">Page URL</label>
        <input id="page_url" name="page_url" class="ltr" maxlength="2000" required placeholder="https://example.com/groups" value="<?= e((string) ($preview['page_url'] ?? ($_POST['page_url'] ?? ''))) ?>">
    </div>
    <div class="form-actions"><button type="submit">Scan</button></div>
</form>

<?php if ($preview && !empty($preview['items'])): ?>
    <h2>Preview</h2>
    <?php if (!empty($preview['capped'])): ?>
        <p class="help">Showing the first 100 links.</p>
    <?php endif; ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Image</th>
                    <th>Link</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($preview['items'] as $item): ?>
                <tr>
                    <td><?= e((string) $item['name']) ?></td>
                    <td><?= e((string) ($item['category'] ?? 'Imported')) ?><?= !empty($item['category_new']) ? ' (new)' : '' ?></td>
                    <td><?= !empty($item['image_url']) ? 'Has image' : 'No image' ?></td>
                    <td class="ltr"><?= e((string) $item['invite_url']) ?></td>
                    <td><?= !empty($item['exists']) ? 'Already listed' : 'New' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <form method="post" class="form-actions" style="margin-top:12px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import">
        <button type="submit">Import new groups</button>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
