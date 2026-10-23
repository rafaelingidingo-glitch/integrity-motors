<?php
/**
 * GET /sitemap.xml — static pages + every published AVAILABLE vehicle.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
}

header('Content-Type: application/xml; charset=utf-8');

$vehicles = db_all("SELECT slug, updated_at FROM vehicles WHERE published = 1 AND status = 'AVAILABLE'");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= e(app_url('/')) ?></loc>
    <changefreq>weekly</changefreq>
  </url>
<?php foreach (['/inventory', '/about', '/why-us', '/contact', '/enquire'] as $staticPath): ?>
  <url>
    <loc><?= e(app_url($staticPath)) ?></loc>
  </url>
<?php endforeach; ?>
<?php foreach ($vehicles as $v): ?>
  <url>
    <loc><?= e(app_url('/vehicle/' . $v['slug'])) ?></loc>
    <lastmod><?= e(date('Y-m-d', (int) strtotime((string) $v['updated_at']))) ?></lastmod>
  </url>
<?php endforeach; ?>
</urlset>
<?php
exit;
