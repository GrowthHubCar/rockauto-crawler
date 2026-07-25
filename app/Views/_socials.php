<?php
/* Footer social row — renders only the networks an admin filled in
   (Admin > Settings > Social links). Shared by home.php and layout.php. */
$sl = social_links();
if ($sl):
$ico = [
  'facebook'  => '<svg width="17" height="17" fill="currentColor" viewBox="0 0 24 24"><path d="M13 22v-8h3l1-4h-4V8c0-1 .5-2 2-2h2V2h-3c-3 0-5 2-5 5v3H6v4h4v8z"/></svg>',
  'instagram' => '<svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>',
  'youtube'   => '<svg width="17" height="17" fill="currentColor" viewBox="0 0 24 24"><path d="M22 8s-.2-1.5-.8-2.1c-.8-.8-1.7-.8-2.1-.9C16.3 4.8 12 4.8 12 4.8s-4.3 0-7.1.2c-.4.1-1.3.1-2.1.9C2.2 6.5 2 8 2 8s-.2 1.7-.2 3.5v1c0 1.8.2 3.5.2 3.5s.2 1.5.8 2.1c.8.8 1.9.8 2.3.9 1.7.2 6.9.2 6.9.2s4.3 0 7.1-.2c.4-.1 1.3-.1 2.1-.9.6-.6.8-2.1.8-2.1s.2-1.7.2-3.5v-1C22.2 9.7 22 8 22 8ZM10 14.6V9.4l4.5 2.6z"/></svg>',
  'x'         => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M17.5 3h3.2l-7 8 8.3 10h-6.5l-5-6.1-5.8 6.1H1.4l7.5-8.6L1 3h6.7l4.6 5.7zm-1.1 16h1.8L7.7 4.8H5.8z"/></svg>',
  'tiktok'    => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M16.5 2h-3v13.2a2.6 2.6 0 1 1-2-2.5V9.6a5.9 5.9 0 1 0 5 5.8V9a7 7 0 0 0 4 1.3V7.2a4 4 0 0 1-4-4z"/></svg>',
  'linkedin'  => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M6.9 8.5v11.6H3.2V8.5zM5 3a2.1 2.1 0 1 1 0 4.3A2.1 2.1 0 0 1 5 3zm4.2 5.5h3.5v1.6c.5-.9 1.7-1.9 3.6-1.9 3.8 0 4.5 2.4 4.5 5.6v6.3h-3.7v-5.6c0-1.3 0-3-1.9-3s-2.1 1.5-2.1 2.9v5.7H9.2z"/></svg>',
];
$labels = social_networks();
?>
<div class="fsocial">
  <?php foreach ($sl as $k => $url): ?>
    <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"
       aria-label="<?= e($labels[$k] ?? ucfirst($k)) ?>" title="<?= e($labels[$k] ?? ucfirst($k)) ?>">
      <?= $ico[$k] ?? '' ?>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
