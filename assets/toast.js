// Strips one-time flash-message query params (?saved=1, ?deleted=1, ?done=1 —
// the Post/Redirect/Get pattern used by settings.php/menu.php/categories.php/
// manage-articles.php to show a toast once) from the address bar right after
// load, without reloading the page. Otherwise the param sits in the URL
// forever — refreshing would look harmless but silently keep re-showing an
// already-served "saved!" toast, and a bookmarked/shared link would carry a
// stale flag along with it.
(function () {
  var flashParams = ['saved', 'deleted', 'done'];
  var url = new URL(window.location.href);
  var changed = false;

  flashParams.forEach(function (key) {
    if (url.searchParams.has(key)) {
      url.searchParams.delete(key);
      changed = true;
    }
  });

  if (changed && window.history.replaceState) {
    window.history.replaceState(null, '', url.pathname + url.search + url.hash);
  }
})();
