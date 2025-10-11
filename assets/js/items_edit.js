// items_edit.js
// create sayfasındaki mantığın aynısı + edit özel davranışlar

(function(){
  // --- Kategori -> Alt Kategori ---
  const $cat = $('#categorySelect');
  const $sub = $('#subCategorySelect');

  function renderSubs(catId, selectedSubId) {
    let html = '<option value="">Seçiniz</option>';
    if (catId && subMap[catId]) {
      subMap[catId].forEach(sc => {
        const sel = (selectedSubId && Number(selectedSubId) === Number(sc.SubCategoryID)) ? 'selected' : '';
        html += `<option value="${sc.SubCategoryID}" ${sel}>${sc.SubCategoryName}</option>`;
      });
    }
    $sub.html(html);
  }

  // İlk yüklemede mevcut seçimi koru
  if ($cat.length) {
    renderSubs($cat.val() || currentCatId, currentSubId);
  }

  $cat.on('change', function(){
    const catId = $(this).val();
    renderSubs(catId, null);
  });

  // --- Seçenekler (dinamik satırlar) ---
  const defContainer = $(`#options-${defaultLang}-container`);

  // Mevcut seçenek silme (default tabdan tetiklenir)
  $(document).on('click', '.removeExistingBtn', function(){
    const oid = $(this).data('oid');
    if (!oid) return;

    // Varsayılan dil satırı
    $(`#options-${defaultLang}-container .option-row[data-oid="${oid}"]`).remove();
    // Diğer dillerin çeviri satırları
    Object.keys(languages).forEach(lc => {
      if (lc === defaultLang) return;
      $(`#options-${lc}-container .option-row[data-oid="${oid}"]`).remove();
    });
    // Sunucuya bildir
    $('#deletedOptions').append(`<input type="hidden" name="options_delete[]" value="${oid}">`);
  });

  // Yeni seçenek ekle
  $('#addNewOptionBtn').on('click', function(){
    const newIndex = defContainer.find('.option-row[data-new="1"]').length;

    // Varsayılan dil için (ad + fiyat + sil butonu)
    defContainer.append(`
      <div class="option-row" data-new="1" data-new-index="${newIndex}">
        <input type="text" name="options_new[name][]" class="form-control" placeholder="Seçenek adı (${defaultLang.toUpperCase()})">
        <input type="number" step="0.01" name="options_new[price][]" class="form-control" placeholder="Fiyat (₺)">
        <!-- TEK GRUP: tüm radio'lar options_def[IsDefault] altında. yeni satırlar new-{index} değerini taşır -->
        <input type="radio" class="opt-new-default" name="options_def[IsDefault]" value="new-${newIndex}">
        <label>Varsayılan</label>
        <button type="button" class="btn btn-outline-danger removeNewBtn">&times;</button>
      </div>
    `);

    // Diğer dillerde sadece çeviri alanı
    Object.keys(languages).forEach(lc => {
      if (lc === defaultLang) return;
      $(`#options-${lc}-container`).append(`
        <div class="option-row" data-new="1" data-new-index="${newIndex}">
          <input type="text" name="options_new_tr[${lc}][name][]" class="form-control" placeholder="Seçenek adı (çeviri: ${lc.toUpperCase()})">
        </div>
      `);
    });
  });

  // Yeni eklenen seçenek satırını kaldır
  $(document).on('click', '.removeNewBtn', function(){
    const $row = $(this).closest('.option-row');
    const idx = $row.data('new-index');
    $row.remove();
    Object.keys(languages).forEach(lc => {
      if (lc === defaultLang) return;
      $(`#options-${lc}-container .option-row[data-new-index="${idx}"]`).remove();
    });
  });

  // --- Yeni resim önizleme + X ile kaldırma ---
  const input   = document.getElementById('newImagesInput');
  const preview = document.getElementById('newImagesPreview');
  let selectedFiles = [];

  function syncInput() {
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    input.files = dt.files;
  }

  function renderPreviews(){
    preview.innerHTML = '';
    selectedFiles.forEach((file, idx) => {
      if (!file.type || !file.type.startsWith('image/')) return;
      const url  = URL.createObjectURL(file);
      const card = document.createElement('div');
      card.className = 'image-card';

      const img  = document.createElement('img');
      img.src = url;
      img.onload = () => URL.revokeObjectURL(url);

      const btn  = document.createElement('button');
      btn.type = 'button';
      btn.className = 'img-remove';
      btn.innerHTML = '&times;';
      btn.title = 'Bu resmi kaldır';
      btn.addEventListener('click', () => {
        selectedFiles.splice(idx, 1);
        syncInput();
        renderPreviews();
      });

      card.appendChild(img);
      card.appendChild(btn);
      preview.appendChild(card);
    });
  }

  if (input) {
    input.addEventListener('change', e => {
      const newFiles = Array.from(e.target.files || []);
      newFiles.forEach(f => { if (f && f.type && f.type.startsWith('image/')) selectedFiles.push(f); });
      syncInput();
      renderPreviews();
    });
  }

  // (opsiyonel) submit anında new-X değerini güncel DOM index'iyle yeniden senkronlamak istersen:
  $('form').on('submit', function(){
    const $checked = $('input[type="radio"][name="options_def[IsDefault]"]:checked');
    if ($checked.length && $checked.val().startsWith('new-')) {
      const rows = $(`#options-${defaultLang}-container .option-row[data-new="1"]`);
      const idx = rows.index($checked.closest('.option-row'));
      if (idx >= 0) $checked.val(`new-${idx}`);
    }
  });
})();
