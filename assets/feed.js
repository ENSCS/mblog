(function () {
  var list = document.getElementById('feed-list');
  if (!list) {
    return;
  }

  var lastId = parseInt(list.dataset.lastId || '0', 10);
  var POLL_INTERVAL_MS = 20000;

  // api/feed-poll.php renders the whole current list server-side (same
  // renderFeedItemHtml() feed.php's own initial load uses) — this just
  // swaps it in wholesale. No client-side HTML building/escaping to keep in
  // sync with the server anymore, and edits/deletes show up automatically
  // since every poll reflects the real current state, not just "what's new".
  function poll() {
    fetch('api/feed-poll.php?last_seen_id=' + lastId)
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (typeof data.html === 'string') {
          list.innerHTML = data.html;
        }
        if (typeof data.last_id === 'number') {
          lastId = data.last_id;
        }
      })
      .catch(function () {
        // Silent — the next interval just tries again, no need to surface
        // a transient network hiccup on a page nobody's actively watching
        // for errors.
      });
  }

  setInterval(poll, POLL_INTERVAL_MS);
})();
