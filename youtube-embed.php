<?php
// Bare-bones YouTube embed — no site header/nav/footer, just the video full-
// bleed with no border/margin. Made for use as a sidebar item's iframe_src
// (sidebar-item-editor.php's "iframe embed" type) so it doesn't drag in
// YouTube's own page chrome, only the player.
// แก้ video ID ตรงนี้ (ส่วนหลัง /watch?v= หรือ youtu.be/ ของลิงก์ YouTube):
$videoId = '6cLzSKG-2ts';
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; background: #000; }
  iframe { display: block; width: 100%; height: 100%; border: 0; }
</style>
</head>
<body>
<iframe
  src="https://www.youtube.com/embed/<?= urlencode($videoId) ?>"
  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
  allowfullscreen
></iframe>
</body>
</html>
