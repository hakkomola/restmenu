$(function() {
  // === 1️⃣ Kategori -> Alt kategori ===
  if (typeof subCategoriesMap !== "undefined") {
    $('#categorySelect').on('change', function() {
      const catId = $(this).val();
      let html = '<option value="">Seçiniz</option>';
      if (catId && subCategoriesMap[catId]) {
        subCategoriesMap[catId].forEach(sub => {
          html += `<option value="${sub.SubCategoryID}">${sub.SubCategoryName}</option>`;
        });
      }
      $('#subCategorySelect').html(html);
    });
  }

  // === 2️⃣ Seçenek ekleme / silme ===
  if (typeof languages === "undefined" || typeof defaultLang === "undefined") return;

  const defContainer = $(`#options-${defaultLang}-container`);

  function addOptionRow() {
    // Varsayılan dil satırı
    defContainer.append(`
      <div class="option-row" data-new="1">
        <input type="text" name="options_new[name][]" class="form-control" placeholder="Seçenek adı (${defaultLang.toUpperCase()})">
        <input type="number" step="0.01" name="options_new[price][]" class="form-control" placeholder="Fiyat (₺)">
        <button type="button" class="btn btn-outline-danger removeOptionBtn">&times;</button>
      </div>
    `);

    // Diğer dillerdeki çeviri satırları
    Object.keys(languages).forEach(lc => {
      if (lc === defaultLang) return;
      $(`#options-${lc}-container`).append(`
        <div class="option-row" data-new="1">
          <input type="text" name="options_new_tr[${lc}][name][]" class="form-control" placeholder="Seçenek adı (çeviri: ${lc.toUpperCase()})">
        </div>
      `);
    });
  }

  // 🟢 Butona tıklama
  $(document).on('click', '#addOptionBtn', addOptionRow);

  // 🔴 Seçenek silme
  $(document).on('click', '.removeOptionBtn', function() {
    const idx = $(this).closest('.option-row').index();
    defContainer.find('.option-row[data-new="1"]').eq(idx).remove();
    Object.keys(languages).forEach(lc => {
      if (lc === defaultLang) return;
      $(`#options-${lc}-container .option-row[data-new="1"]`).eq(idx).remove();
    });
  });

  // === 3️⃣ Görsel önizleme ===
  const input = document.getElementById('imagesInput');
  const preview = document.getElementById('imagesPreview');
  if (!input || !preview) return;

  let selectedFiles = [];

  function syncInput() {
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    input.files = dt.files;
  }

  function renderPreviews() {
    preview.innerHTML = '';
    selectedFiles.forEach((file, idx) => {
      if (!file.type.startsWith('image/')) return;
      const url = URL.createObjectURL(file);
      const card = document.createElement('div');
      card.className = 'image-card';

      const img = document.createElement('img');
      img.src = url;

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'img-remove';
      btn.innerHTML = '&times;';
      btn.onclick = () => {
        selectedFiles.splice(idx, 1);
        syncInput();
        renderPreviews();
      };

      card.appendChild(img);
      card.appendChild(btn);
      preview.appendChild(card);
    });
  }

  input.addEventListener('change', e => {
    const newFiles = Array.from(e.target.files);
    newFiles.forEach(f => {
      if (f && f.type.startsWith('image/')) selectedFiles.push(f);
    });
    syncInput();
    renderPreviews();
  });
});
