// --- custom "divider" (hr) block, registered once when this script loads ---
(function registerDividerFormat() {
  const BlockEmbed = Quill.import('blots/block/embed');
  class DividerBlot extends BlockEmbed {}
  DividerBlot.blotName = 'divider';
  DividerBlot.tagName = 'hr';
  Quill.register(DividerBlot, true);

  const icons = Quill.import('ui/icons');
  icons['divider'] = '<svg viewBox="0 0 18 18"><line class="ql-stroke" x1="2" y1="9" x2="16" y2="9"></line></svg>';
})();

function initArticleEditor(existingContent) {
  const quill = new Quill('#editor-container', {
    theme: 'snow',
    placeholder: 'เริ่มเขียนบทความของคุณที่นี่...',
    modules: {
      syntax: true,
      toolbar: {
        container: [
          [{ header: [1, 2, 3, false] }],
          ['bold', 'italic', 'underline'],
          [{ color: [] }],
          [{ align: [] }],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['blockquote', 'code-block', 'divider'],
          ['link', 'image', 'video'],
          ['clean']
        ]
      }
    }
  });

  if (existingContent) {
    quill.root.innerHTML = collapseImageCaptions(existingContent);
  }

  // --- custom image upload handler ---
  quill.getModule('toolbar').addHandler('image', function () {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.click();
    input.onchange = () => {
      const file = input.files[0];
      if (!file) return;
      const formData = new FormData();
      formData.append('image', file);
      fetch('api/upload.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
          if (data.url) {
            const range = quill.getSelection(true);
            quill.insertEmbed(range.index, 'image', data.url, 'user');
            quill.setSelection(range.index + 1);
          } else {
            alert('อัปโหลดรูปไม่สำเร็จ: ' + (data.error || 'unknown error'));
          }
        })
        .catch(() => alert('อัปโหลดรูปไม่สำเร็จ'));
    };
  });

  // --- custom divider (horizontal rule) handler ---
  quill.getModule('toolbar').addHandler('divider', function () {
    const range = quill.getSelection(true);
    quill.insertEmbed(range.index, 'divider', true, 'user');
    quill.insertText(range.index + 1, '\n', 'user');
    quill.setSelection(range.index + 2, 'user');
  });

  // --- custom video-embed handler (YouTube only, see youtubeEmbedUrl()) ---
  quill.getModule('toolbar').addHandler('video', function () {
    const input = window.prompt('วางลิงก์ YouTube ที่นี่:');
    if (!input) return;
    const embedUrl = youtubeEmbedUrl(input.trim());
    if (!embedUrl) {
      alert('ลิงก์นี้ไม่ใช่ YouTube ที่รองรับ (youtube.com/watch, youtu.be, youtube.com/shorts)');
      return;
    }
    const range = quill.getSelection(true);
    quill.insertEmbed(range.index, 'video', embedUrl, 'user');
    quill.insertText(range.index + 1, '\n', 'user');
    quill.setSelection(range.index + 2, 'user');
  });

  enableImageResize(quill);
  enableAutoLink(quill);
  return quill;
}

// --- turns plain-text URLs into clickable links as the user types ---
// Triggers right after a space/newline is typed following something that
// looks like a URL (http://, https://, or www.).
function enableAutoLink(quill) {
  const urlRegex = /^(https?:\/\/|www\.)\S+$/i;

  quill.on('text-change', function (delta, oldDelta, source) {
    if (source !== 'user') return;

    // Compute the position right after this change from the delta itself
    // (rather than quill.getSelection(), whose timing/focus can be unreliable),
    // so this doesn't depend on the editor being focused.
    let index = 0;
    let insertedText = '';
    for (const op of (delta.ops || [])) {
      if (op.retain !== undefined) {
        if (insertedText) break; // retain after an insert shouldn't happen mid-typing; stop just in case
        index += typeof op.retain === 'number' ? op.retain : 0;
      } else if (typeof op.insert === 'string') {
        insertedText += op.insert;
      } else if (op.insert !== undefined) {
        return; // an embed was inserted (e.g. image) — nothing to linkify
      }
    }
    if (!insertedText) return;
    const insertedChar = insertedText.slice(-1);
    if (insertedChar !== ' ' && insertedChar !== '\n') return;

    const cursor = index + insertedText.length;

    const lookbackLimit = Math.max(0, cursor - 500);
    const chunkLen = (cursor - 1) - lookbackLimit;
    if (chunkLen <= 0) return;
    const chunk = quill.getText(lookbackLimit, chunkLen);

    const words = chunk.split(/[\s\n]+/);
    let word = words[words.length - 1];
    if (!word) return;

    const trailMatch = word.match(/[).,!?;:'"\]]+$/);
    let trailing = '';
    if (trailMatch) {
      trailing = trailMatch[0];
      word = word.slice(0, -trailing.length);
    }
    if (!word || !urlRegex.test(word)) return;

    const wordStart = cursor - 1 - trailing.length - word.length;
    const existingFormat = quill.getFormat(wordStart, word.length);
    if (existingFormat.link) return;

    const href = /^www\./i.test(word) ? 'https://' + word : word;
    quill.formatText(wordStart, word.length, 'link', href, 'silent');
  });
}

// --- safety-net linkifier: catches URLs that arrived via paste, or that
// never got a trailing space (e.g. the last word in the document). Runs on
// a detached clone right before saving, so it never touches Quill's live
// editing DOM/model.
function linkifyPlainTextInDom(root) {
  const urlRegex = /((?:https?:\/\/|www\.)[^\s<>"']+)/gi;
  const skipTags = new Set(['A', 'CODE', 'PRE', 'SCRIPT', 'STYLE']);

  function isInsideSkippedAncestor(node) {
    let p = node.parentNode;
    while (p && p !== root) {
      if (skipTags.has(p.tagName)) return true;
      p = p.parentNode;
    }
    return false;
  }

  function walk(node) {
    if (node.nodeType === Node.TEXT_NODE) {
      if (isInsideSkippedAncestor(node)) return;
      const text = node.nodeValue;
      urlRegex.lastIndex = 0;
      if (!urlRegex.test(text)) return;
      urlRegex.lastIndex = 0;

      const frag = document.createDocumentFragment();
      let lastIndex = 0;
      let m;
      while ((m = urlRegex.exec(text)) !== null) {
        const raw = m[0];
        const trailMatch = raw.match(/[).,!?;:'"\]]+$/);
        let url = raw;
        let trailing = '';
        if (trailMatch) {
          trailing = trailMatch[0];
          url = raw.slice(0, raw.length - trailing.length);
        }
        if (!url) continue;

        if (m.index > lastIndex) {
          frag.appendChild(document.createTextNode(text.slice(lastIndex, m.index)));
        }
        const a = document.createElement('a');
        a.setAttribute('href', /^www\./i.test(url) ? 'https://' + url : url);
        a.setAttribute('target', '_blank');
        a.setAttribute('rel', 'noopener noreferrer');
        a.textContent = url;
        frag.appendChild(a);
        if (trailing) {
          frag.appendChild(document.createTextNode(trailing));
        }
        lastIndex = m.index + raw.length;
      }
      if (lastIndex < text.length) {
        frag.appendChild(document.createTextNode(text.slice(lastIndex)));
      }
      node.parentNode.replaceChild(frag, node);
      return;
    }
    if (node.nodeType === Node.ELEMENT_NODE) {
      if (skipTags.has(node.tagName)) return;
      Array.from(node.childNodes).forEach(walk);
    }
  }

  Array.from(root.childNodes).forEach(walk);
}

// --- YouTube URL -> embeddable URL ---
// A youtube.com/watch or youtu.be link isn't embeddable as-is (YouTube
// blocks framing the watch page itself) — this pulls the video ID out of
// every URL shape YouTube hands out (watch/shorts/youtu.be/already-embed)
// and rebuilds the one URL shape that actually plays in an <iframe>.
// Hostname is checked exactly (after stripping www./m.) rather than just
// pattern-matching the string, so a lookalike domain can't sneak an
// arbitrary iframe src in under a "youtube" label.
function youtubeEmbedUrl(rawUrl) {
  let url;
  try {
    url = new URL(rawUrl.trim());
  } catch {
    return null;
  }

  const host = url.hostname.replace(/^www\.|^m\./, '');
  let videoId = null;

  if (host === 'youtu.be') {
    videoId = url.pathname.split('/').filter(Boolean)[0];
  } else if (host === 'youtube.com') {
    if (url.pathname === '/watch') {
      videoId = url.searchParams.get('v');
    } else {
      const parts = url.pathname.split('/').filter(Boolean);
      if (parts[0] === 'embed' || parts[0] === 'shorts') {
        videoId = parts[1];
      }
    }
  } else {
    return null;
  }

  if (!videoId || !/^[\w-]{11}$/.test(videoId)) return null;
  return `https://www.youtube.com/embed/${videoId}`;
}

// --- auto-embed a bare YouTube link pasted on its own line ---
// Runs on the same detached save-time clone as linkifyPlainTextInDom (which
// runs first and turns a plain-text URL into an <a> — this then picks that
// up too). Only swaps a link that is the *entire* content of its block, so
// a YouTube link mentioned inline mid-sentence is left as a plain link —
// only a link sitting alone on its own line is treated as "embed this".
function embedYoutubeLinks(root) {
  Array.from(root.children).forEach((block) => {
    if (typeof block.querySelectorAll !== 'function') return;
    const links = block.querySelectorAll('a');
    if (links.length !== 1) return;
    const a = links[0];
    if (a.querySelector('img')) return; // a linked image, not a bare text link

    const embedUrl = youtubeEmbedUrl(a.getAttribute('href') || '');
    if (!embedUrl) return;
    if (block.textContent.trim() !== a.textContent.trim()) return;

    const iframe = document.createElement('iframe');
    iframe.className = 'ql-video';
    iframe.setAttribute('src', embedUrl);
    iframe.setAttribute('frameborder', '0');
    iframe.setAttribute('allowfullscreen', 'true');
    block.replaceWith(iframe);
  });
}

// --- image captions ---
// Quill's editor strips any DOM element it doesn't recognize as a registered
// format (e.g. a plain <span class="mblog-caption"> sibling), reconciling it
// away the moment its MutationObserver runs. To survive being edited, the
// caption is instead kept as a `data-caption` attribute on the <img> itself
// (a plain attribute, which Quill leaves alone) while live in the editor, and
// only expanded into a real <span class="mblog-caption"> sibling in the HTML
// that gets saved/rendered. collapseImageCaptions() does the reverse when
// re-opening a saved article for editing.
function expandImageCaptions(root) {
  root.querySelectorAll('img[data-caption]').forEach((img) => {
    const text = img.getAttribute('data-caption');
    img.removeAttribute('data-caption');
    if (!text) return;

    const span = document.createElement('span');
    span.className = 'mblog-caption';
    if (img.classList.contains('align-left')) span.classList.add('cap-left');
    else if (img.classList.contains('align-right')) span.classList.add('cap-right');
    else span.classList.add('cap-center');
    span.style.width = img.style.width || (img.getBoundingClientRect().width + 'px');
    span.textContent = text;

    const wrapperEl = img.closest('a') || img;
    wrapperEl.parentNode.insertBefore(span, wrapperEl.nextSibling);
  });
}

function collapseImageCaptions(html) {
  const container = document.createElement('div');
  container.innerHTML = html;
  container.querySelectorAll('.mblog-caption').forEach((span) => {
    const prev = span.previousElementSibling;
    const img = prev && (prev.tagName === 'IMG' ? prev : prev.querySelector('img'));
    if (img) {
      img.setAttribute('data-caption', span.textContent);
    }
    span.remove();
  });
  return container.innerHTML;
}

// --- image selection: drag-to-resize handle + mini toolbar (align, link, caption) ---
function enableImageResize(quill) {
  const editorEl = quill.root;
  let selectedImg = null;

  const handle = document.createElement('div');
  handle.className = 'img-resize-handle';
  document.body.appendChild(handle);

  const miniToolbar = document.createElement('div');
  miniToolbar.className = 'img-mini-toolbar';
  miniToolbar.innerHTML = `
    <button type="button" data-align="left" title="ชิดซ้าย">⟸</button>
    <button type="button" data-align="center" title="กึ่งกลาง">≡</button>
    <button type="button" data-align="right" title="ชิดขวา">⟹</button>
    <span class="img-toolbar-sep"></span>
    <button type="button" data-action="link" title="ใส่ลิงก์ให้รูปนี้">🔗</button>
    <button type="button" data-action="unlink" title="ลบลิงก์">✕</button>
    <span class="img-toolbar-sep"></span>
    <button type="button" data-action="caption" title="คำบรรยายใต้ภาพ">Aa</button>
  `;
  document.body.appendChild(miniToolbar);

  // Shows the caption as a floating overlay (outside Quill's DOM) while the
  // image is selected — see the comment above expandImageCaptions() for why
  // it can't live in Quill's tree directly during editing.
  const captionPreview = document.createElement('div');
  captionPreview.className = 'img-caption-preview';
  document.body.appendChild(captionPreview);

  function positionOverlays() {
    if (!selectedImg) {
      handle.style.display = 'none';
      miniToolbar.style.display = 'none';
      captionPreview.style.display = 'none';
      return;
    }
    const rect = selectedImg.getBoundingClientRect();
    handle.style.left = (rect.right - 6) + 'px';
    handle.style.top = (rect.bottom - 6) + 'px';
    handle.style.display = 'block';

    const toolbarHeight = 40;
    const toolbarBelowImage = rect.bottom + 8;
    const quillToolbarBottom = quill.getModule('toolbar').container.getBoundingClientRect().bottom;
    const toolbarIsAbove = (rect.top - toolbarHeight) > (quillToolbarBottom + 4);
    const top = toolbarIsAbove ? rect.top - toolbarHeight : toolbarBelowImage;
    miniToolbar.style.left = rect.left + 'px';
    miniToolbar.style.top = top + 'px';
    miniToolbar.style.display = 'flex';

    const caption = selectedImg.getAttribute('data-caption');
    if (caption) {
      // keep the preview clear of the mini toolbar, whichever side it's on
      const previewTop = toolbarIsAbove ? rect.bottom + 4 : toolbarBelowImage + toolbarHeight + 4;
      captionPreview.textContent = caption;
      captionPreview.style.left = rect.left + 'px';
      captionPreview.style.top = previewTop + 'px';
      captionPreview.style.width = rect.width + 'px';
      captionPreview.style.display = 'block';
    } else {
      captionPreview.style.display = 'none';
    }
  }

  function selectImage(img) {
    selectedImg = img;
    img.classList.add('img-selected');
    positionOverlays();
  }

  function deselect() {
    if (selectedImg) selectedImg.classList.remove('img-selected');
    selectedImg = null;
    handle.style.display = 'none';
    miniToolbar.style.display = 'none';
    captionPreview.style.display = 'none';
  }

  editorEl.addEventListener('click', (e) => {
    if (e.target.tagName === 'IMG') {
      deselect();
      selectImage(e.target);
    } else {
      deselect();
    }
  });

  document.addEventListener('click', (e) => {
    if (selectedImg && e.target !== selectedImg && !miniToolbar.contains(e.target) && !handle.contains(e.target) && !editorEl.contains(e.target)) {
      deselect();
    }
  });

  window.addEventListener('scroll', positionOverlays, true);
  window.addEventListener('resize', positionOverlays);

  // prevent the editor from losing selection focus when clicking toolbar buttons
  miniToolbar.addEventListener('mousedown', (e) => e.preventDefault());

  miniToolbar.addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (!btn || !selectedImg) return;

    if (btn.dataset.align) {
      selectedImg.classList.remove('align-left', 'align-center', 'align-right');
      selectedImg.classList.add('align-' + btn.dataset.align);
      positionOverlays();
      return;
    }

    if (btn.dataset.action === 'link') {
      const existingLink = selectedImg.closest('a');
      const url = window.prompt('ใส่ URL ที่ต้องการให้รูปนี้ลิงก์ไป:', existingLink ? existingLink.getAttribute('href') : 'https://');
      if (url === null) return;
      applyImageLink(quill, selectedImg, url.trim());
    } else if (btn.dataset.action === 'unlink') {
      applyImageLink(quill, selectedImg, '');
    } else if (btn.dataset.action === 'caption') {
      applyImageCaption(selectedImg);
      positionOverlays();
    }
  });

  let startX, startWidth;
  handle.addEventListener('mousedown', (e) => {
    e.preventDefault();
    startResize(e.clientX);
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
  });

  function onMouseMove(e) {
    resizeTo(e.clientX);
  }

  function onMouseUp() {
    document.removeEventListener('mousemove', onMouseMove);
    document.removeEventListener('mouseup', onMouseUp);
  }

  // touch support, so dragging to resize also works on phones/tablets
  handle.addEventListener('touchstart', (e) => {
    if (!e.touches[0]) return;
    e.preventDefault();
    startResize(e.touches[0].clientX);
    document.addEventListener('touchmove', onTouchMove, { passive: false });
    document.addEventListener('touchend', onTouchEnd);
  });

  function onTouchMove(e) {
    if (!e.touches[0]) return;
    e.preventDefault(); // don't let the page scroll while dragging
    resizeTo(e.touches[0].clientX);
  }

  function onTouchEnd() {
    document.removeEventListener('touchmove', onTouchMove);
    document.removeEventListener('touchend', onTouchEnd);
  }

  function startResize(clientX) {
    startX = clientX;
    startWidth = selectedImg.offsetWidth;
  }

  function resizeTo(clientX) {
    const newWidth = Math.max(30, startWidth + (clientX - startX));
    selectedImg.style.width = newWidth + 'px';
    positionOverlays();
  }
}

// applies (or removes) a link on the image at the given DOM node via Quill's
// native link format, so it stays in sync with Quill's internal document model
function applyImageLink(quill, imgEl, url) {
  const blot = Quill.find(imgEl);
  if (!blot) return;
  const index = quill.getIndex(blot);
  quill.formatText(index, 1, 'link', url ? url : false, 'user');
}

// adds/edits/removes a caption for the image, stored as a data-caption
// attribute (see the comment above expandImageCaptions() for why)
function applyImageCaption(imgEl) {
  const current = imgEl.getAttribute('data-caption') || '';
  const text = window.prompt('ใส่คำบรรยายใต้ภาพ (เว้นว่างเพื่อลบ):', current);
  if (text === null) return;

  if (text.trim() === '') {
    imgEl.removeAttribute('data-caption');
  } else {
    imgEl.setAttribute('data-caption', text.trim());
  }
}

// --- featured image picker (separate from images inserted in the content) ---
function setupFeaturedImagePicker() {
  const input = document.getElementById('featured-image-input');
  const hidden = document.getElementById('featured-image');
  const preview = document.getElementById('featured-image-preview');
  const thumb = document.getElementById('featured-image-thumb');
  const removeBtn = document.getElementById('remove-featured-image-btn');
  const uploadMode = document.getElementById('featured-image-upload-mode');
  const urlMode = document.getElementById('featured-image-url-mode');
  const urlToggle = document.getElementById('featured-image-url-toggle');
  const urlCancel = document.getElementById('featured-image-url-cancel');
  const urlInput = document.getElementById('featured-image-url-input');
  const urlConfirmBtn = document.getElementById('featured-image-url-confirm-btn');

  function showPreview(url) {
    hidden.value = url;
    thumb.src = url;
    preview.style.display = 'flex';
    uploadMode.style.display = 'none';
    urlMode.style.display = 'none';
  }

  input.addEventListener('change', () => {
    const file = input.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('image', file);
    fetch('api/upload.php', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.url) {
          showPreview(data.url);
        } else {
          alert('อัปโหลดรูปไม่สำเร็จ: ' + (data.error || 'unknown error'));
        }
      })
      .catch(() => alert('อัปโหลดรูปไม่สำเร็จ'));
  });

  urlToggle.addEventListener('click', (e) => {
    e.preventDefault();
    uploadMode.style.display = 'none';
    urlMode.style.display = 'block';
    urlInput.value = '';
    urlInput.focus();
  });

  urlCancel.addEventListener('click', (e) => {
    e.preventDefault();
    urlMode.style.display = 'none';
    uploadMode.style.display = 'block';
  });

  urlConfirmBtn.addEventListener('click', () => {
    const url = urlInput.value.trim();
    if (!url) return;
    // Confirm it actually loads as an image before committing it — a typo'd
    // or dead URL would otherwise save silently and only show as a broken
    // image once the article is already published.
    urlConfirmBtn.disabled = true;
    const probe = new Image();
    probe.onload = () => {
      urlConfirmBtn.disabled = false;
      showPreview(url);
    };
    probe.onerror = () => {
      urlConfirmBtn.disabled = false;
      alert('โหลดรูปจาก URL นี้ไม่ได้ ตรวจสอบ URL อีกครั้ง');
    };
    probe.src = url;
  });

  removeBtn.addEventListener('click', () => {
    hidden.value = '';
    thumb.src = '';
    preview.style.display = 'none';
    input.value = '';
    urlInput.value = '';
    urlMode.style.display = 'none';
    uploadMode.style.display = 'block';
  });
}

// --- tag chip input (separate from category — freeform, many per article) ---
// existingTags: names already on the article (editing) or [] (new article).
// allTagNames: every tag name in the DB, used to drive the autocomplete list.
function setupTagInput(existingTags, allTagNames) {
  const hidden = document.getElementById('tags');
  const chipsEl = document.getElementById('tag-chips');
  const input = document.getElementById('tag-input');
  const suggestionsEl = document.getElementById('tag-suggestions');
  let tags = existingTags.slice();

  function render() {
    chipsEl.innerHTML = '';
    tags.forEach((tag, i) => {
      const chip = document.createElement('span');
      chip.className = 'tag-chip';
      chip.textContent = tag;
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.textContent = '×';
      remove.addEventListener('click', () => {
        tags.splice(i, 1);
        render();
      });
      chip.appendChild(remove);
      chipsEl.appendChild(chip);
    });
    hidden.value = tags.join(',');
  }

  function addTag(name) {
    name = name.trim();
    if (!name || tags.some(t => t.toLowerCase() === name.toLowerCase())) return;
    tags.push(name);
    render();
  }

  function showSuggestions() {
    const q = input.value.trim().toLowerCase();
    suggestionsEl.innerHTML = '';
    if (!q) {
      suggestionsEl.style.display = 'none';
      return;
    }
    const matches = allTagNames
      .filter(name => name.toLowerCase().includes(q) && !tags.some(t => t.toLowerCase() === name.toLowerCase()))
      .slice(0, 8);
    if (!matches.length) {
      suggestionsEl.style.display = 'none';
      return;
    }
    matches.forEach((name) => {
      const item = document.createElement('div');
      item.className = 'tag-suggestion-item';
      item.textContent = name;
      // mousedown (not click) fires before the input's blur handler hides the list
      item.addEventListener('mousedown', (e) => {
        e.preventDefault();
        addTag(name);
        input.value = '';
        suggestionsEl.style.display = 'none';
      });
      suggestionsEl.appendChild(item);
    });
    suggestionsEl.style.display = 'block';
  }

  input.addEventListener('input', showSuggestions);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      addTag(input.value.replace(/,$/, ''));
      input.value = '';
      suggestionsEl.style.display = 'none';
    } else if (e.key === 'Backspace' && input.value === '' && tags.length) {
      tags.pop();
      render();
    }
  });
  input.addEventListener('blur', () => {
    setTimeout(() => { suggestionsEl.style.display = 'none'; }, 150);
  });

  render();
}

function saveArticle(quill, articleId, slug, status) {
  const title = document.getElementById('title').value.trim();
  const type = document.getElementById('type').value;
  const category = document.getElementById('category').value;
  const excerpt = document.getElementById('excerpt').value.trim();
  const featuredImage = document.getElementById('featured-image').value;
  const showSidebar = document.getElementById('show-sidebar').value;
  const tags = document.getElementById('tags').value.split(',').map(t => t.trim()).filter(Boolean);
  const statusEl = document.getElementById('save-status');
  if (!title) {
    alert('กรุณาใส่ชื่อบทความ');
    return;
  }

  const clone = quill.root.cloneNode(true);
  clone.querySelectorAll('img.img-selected').forEach((img) => img.classList.remove('img-selected'));
  clone.querySelectorAll('select.ql-ui').forEach((el) => el.remove());
  linkifyPlainTextInDom(clone);
  embedYoutubeLinks(clone);
  expandImageCaptions(clone);

  statusEl.textContent = status === 'published' ? 'กำลังเผยแพร่...' : 'กำลังบันทึกร่าง...';
  fetch('api/save.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      id: articleId || '',
      slug: slug || '',
      title: title,
      content: clone.innerHTML,
      status: status,
      type: type,
      category: category,
      excerpt: excerpt,
      featured_image: featuredImage,
      show_sidebar: showSidebar,
      tags: tags
    })
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        statusEl.textContent = data.status === 'published' ? 'เผยแพร่แล้ว' : 'บันทึกร่างแล้ว';
        document.getElementById('article-id').value = data.id;
        document.getElementById('slug').value = data.slug;
        window.history.replaceState({}, '', 'editor.php?slug=' + data.slug);
        const viewBase = data.type === 'page' ? 'page.php' : 'article.php';
        document.getElementById('view-link').href = viewBase + '?slug=' + data.slug;
        document.getElementById('view-link').style.display = 'inline';

        const badge = document.getElementById('status-badge');
        badge.textContent = data.status === 'published' ? 'เผยแพร่แล้ว' : 'ร่าง';
        badge.className = 'status-badge status-' + data.status;
        document.getElementById('slug-warning').style.display = data.status === 'published' ? 'block' : 'none';
      } else {
        statusEl.textContent = '';
        alert('บันทึกไม่สำเร็จ: ' + (data.error || 'unknown error'));
      }
    })
    .catch(() => {
      statusEl.textContent = '';
      alert('บันทึกไม่สำเร็จ');
    });
}

// Same shape as saveArticle() above (reuses the same Quill content cleanup —
// linkify + expand captions — since sidebar-item-editor.php uses the full
// editor too) but posts to a different endpoint with a much smaller field
// set: no slug/status/type/category/tags, just what a sidebar card needs.
function saveSidebarItem(quill, itemId) {
  const title = document.getElementById('title').value.trim();
  const image = document.getElementById('featured-image').value;
  const linkUrl = document.getElementById('link-url').value.trim();
  const isActive = document.getElementById('is-active').checked;
  const statusEl = document.getElementById('save-status');
  if (!title) {
    alert('กรุณาใส่ชื่อรายการ');
    return;
  }

  const clone = quill.root.cloneNode(true);
  clone.querySelectorAll('img.img-selected').forEach((img) => img.classList.remove('img-selected'));
  clone.querySelectorAll('select.ql-ui').forEach((el) => el.remove());
  linkifyPlainTextInDom(clone);
  embedYoutubeLinks(clone);
  expandImageCaptions(clone);

  statusEl.textContent = 'กำลังบันทึก...';
  fetch('api/save-sidebar-item.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      id: itemId || '',
      title: title,
      content: clone.innerHTML,
      image: image,
      link_url: linkUrl,
      is_active: isActive
    })
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        statusEl.textContent = 'บันทึกแล้ว';
        document.getElementById('item-id').value = data.id;
        window.history.replaceState({}, '', 'sidebar-item-editor.php?id=' + data.id);
      } else {
        statusEl.textContent = '';
        alert('บันทึกไม่สำเร็จ: ' + (data.error || 'unknown error'));
      }
    })
    .catch(() => {
      statusEl.textContent = '';
      alert('บันทึกไม่สำเร็จ');
    });
}
