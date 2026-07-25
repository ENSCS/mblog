// adds a GitHub-style "copy" button to the top-right corner of every
// rendered code block on the article page
(function () {
  const copyIcon = '<svg viewBox="0 0 16 16" fill="currentColor"><path d="M0 6.75C0 5.784.784 5 1.75 5h1.5a.75.75 0 010 1.5h-1.5a.25.25 0 00-.25.25v7.5c0 .138.112.25.25.25h7.5a.25.25 0 00.25-.25v-1.5a.75.75 0 011.5 0v1.5A1.75 1.75 0 019.25 16h-7.5A1.75 1.75 0 010 14.25v-7.5z"></path><path d="M5 1.75C5 .784 5.784 0 6.75 0h7.5C15.216 0 16 .784 16 1.75v7.5A1.75 1.75 0 0114.25 11h-7.5A1.75 1.75 0 015 9.25v-7.5zM6.75 1.5a.25.25 0 00-.25.25v7.5c0 .138.112.25.25.25h7.5a.25.25 0 00.25-.25v-7.5a.25.25 0 00-.25-.25h-7.5z"></path></svg>';

  document.querySelectorAll('.ql-code-block-container').forEach(function (container) {
    const lines = container.querySelectorAll('.ql-code-block');
    const code = Array.from(lines).map(function (l) { return l.textContent; }).join('\n');

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'code-copy-btn';
    btn.innerHTML = copyIcon + '<span>คัดลอก</span>';

    function showCopied() {
      btn.classList.add('copied');
      btn.querySelector('span').textContent = 'คัดลอกแล้ว';
      setTimeout(function () {
        btn.classList.remove('copied');
        btn.querySelector('span').textContent = 'คัดลอก';
      }, 1800);
    }

    function showSelectManually() {
      // last resort: select the code text itself so the user can hit Ctrl/Cmd+C
      const range = document.createRange();
      range.selectNodeContents(container);
      const selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(range);
      btn.querySelector('span').textContent = 'กด Ctrl+C';
      setTimeout(function () {
        btn.querySelector('span').textContent = 'คัดลอก';
      }, 2200);
    }

    function fallbackCopy() {
      const textarea = document.createElement('textarea');
      textarea.value = code;
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.select();
      let ok = false;
      try {
        ok = document.execCommand('copy');
      } catch (e) {
        ok = false;
      }
      document.body.removeChild(textarea);
      if (ok) {
        showCopied();
      } else {
        showSelectManually();
      }
    }

    btn.addEventListener('click', function () {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(showCopied, fallbackCopy);
      } else {
        fallbackCopy();
      }
    });

    container.appendChild(btn);
  });
})();
