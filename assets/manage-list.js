document.addEventListener('DOMContentLoaded', () => {
  const selectAll = document.getElementById('select-all');
  const rowChecks = () => Array.from(document.querySelectorAll('.row-select'));

  if (selectAll) {
    selectAll.addEventListener('change', () => {
      rowChecks().forEach((cb) => { cb.checked = selectAll.checked; });
    });
  }

  const bulkForm = document.getElementById('bulk-form');
  const bulkSelect = document.getElementById('bulk-action-select');
  if (!bulkForm || !bulkSelect) return;

  bulkForm.addEventListener('submit', (e) => {
    const action = bulkSelect.value;
    const checked = rowChecks().filter((cb) => cb.checked);

    if (!action) {
      e.preventDefault();
      alert('กรุณาเลือกการดำเนินการ');
      return;
    }
    if (checked.length === 0) {
      e.preventDefault();
      alert('กรุณาเลือกอย่างน้อย 1 รายการ');
      return;
    }

    // Only the destructive actions need a confirm — matches the per-row
    // buttons (WP itself doesn't prompt for bulk publish/draft either).
    // Wording stays generic ("รายการ", not "บทความ"/"หน้า") since this
    // script is shared by manage-articles.php and manage-pages.php.
    if (action === 'trash' && !confirm(`ย้าย ${checked.length} รายการไปถังขยะ?`)) {
      e.preventDefault();
    } else if (action === 'permanently_delete' && !confirm(`ลบถาวร ${checked.length} รายการ — กู้คืนไม่ได้อีก ยืนยันลบถาวร?`)) {
      e.preventDefault();
    }
  });
});
