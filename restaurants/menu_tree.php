<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header("Location: restaurants/login.php");
    exit;
}

include __DIR__ . '/../includes/mainnavbar.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Restoran Menü Ağacı</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: #f8f9fa; }

/* Tree yapısı */
.tree ul { list-style:none; margin:0; padding-left:1.2rem; border-left:1px solid #dee2e6; }
.tree li { margin:6px 0; position:relative; }
.tree li::before { content:""; position:absolute; top:0; left:-1.2rem; height:100%; border-left:1px solid #dee2e6; }

.node { display:flex; align-items:center; gap:8px; padding:5px 8px; border-radius:6px; cursor:pointer; transition: background 0.2s; }
.node:hover { background-color:#eef1f4; }

input[type="checkbox"] { width:16px; height:16px; accent-color:#0d6efd; }

.category > .node i.bi-folder2-open { color:#0d6efd; }
.subcategory > .node i.bi-folder { color:#198754; }
.item > .node i.bi-file-earmark-text { color:#6c757d; }

.collapsed > ul { display:none; }
.collapsed > .node i.toggle { transform:rotate(-90deg); }

/* Seviye iç boşlukları */
.level1 > .node { padding-left:8px; font-weight:600; }      /* Ana Menü */
.level2 > .node { padding-left:24px; }                     /* Kategori */
.level3 > .node { padding-left:40px; }                     /* Alt Kategori */
.level4 > .node { padding-left:56px; }                     /* Ürün */
</style>
</head>
<body>
<div class="container mt-4">
<div class="card shadow-sm">
<div class="card-body">
<h3 class="text-center mb-3">Restoran Menü Ağacı</h3>

<div class="tree" id="menuTree">
  <div id="treeContent"></div>
</div>

<div class="text-center mt-3">
  <button id="saveBtn" class="btn btn-primary">Kaydet</button>
</div>
</div>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script>
function renderTree(data) {
    let html = '<ul>';

    // 1. kırılım → Ana Menü
    html += `<li class="level1">
        <div class="node"><input type="checkbox" data-id="root" data-type="root"><i class="bi bi-house-door-fill"></i> Ana Menü</div>
        <ul>`;

    // 2-3-4 kırılım → DB’den gelen data
    data.forEach(cat=>{
        const hasSubs = cat.subcategories?.length > 0;
        const toggleCat = hasSubs ? '<i class="bi bi-chevron-down toggle"></i>' : '<i class="bi bi-dot text-secondary"></i>';
        html += `<li class="level2 category">
            <div class="node">${toggleCat}<input type="checkbox" data-id="${cat.id}" data-type="category"><i class="bi bi-folder2-open"></i> ${cat.name}</div>`;

        if(hasSubs){
            html += '<ul>';
            cat.subcategories.forEach(sub=>{
                const hasItems = sub.items?.length>0;
                const toggleSub = hasItems ? '<i class="bi bi-chevron-down toggle"></i>' : '<i class="bi bi-dot text-secondary"></i>';
                html += `<li class="level3 subcategory">
                    <div class="node">${toggleSub}<input type="checkbox" data-id="${sub.id}" data-type="subcategory"><i class="bi bi-folder"></i> ${sub.name}</div>`;

                if(hasItems){
                    html += '<ul>';
                    sub.items.forEach(item=>{
                        html += `<li class="level4 item">
                            <div class="node"><input type="checkbox" data-id="${item.id}" data-type="item"><i class="bi bi-file-earmark-text"></i> ${item.name}</div>
                        </li>`;
                    });
                    html += '</ul>';
                }

                html += '</li>';
            });
            html += '</ul>';
        }

        html += '</li>';
    });

    html += '</ul></li></ul>'; // Ana Menü li ve ul kapatma
    return html;
}

$(function(){
    $.getJSON('get_menu_tree.php', function(data){
        $('#treeContent').html(renderTree(data));
    });

    // Toggle collapse/expand
    $('#menuTree').on('click', '.toggle', function(e){
        e.stopPropagation();
        const $li = $(this).closest('li');
        $li.toggleClass('collapsed');
    });

    // Ana Menü checkbox → tüm alt elemanlar seç/kaldır
    $('#menuTree').on('change', 'input[data-type="root"]', function(){
        const checked = $(this).prop('checked');
        $('#menuTree input[type="checkbox"]').not(this).prop('checked', checked);
    });

    // Alt veya üst checkbox değişim mantığı
    $('#menuTree').on('change', 'input[type="checkbox"]:not([data-type="root"])', function(){
        const $this = $(this);
        const checked = $this.prop('checked');

        // 1️⃣ Eğer üst seçildiyse → tüm alt checkbox’lar seç/kaldır
        const $li = $this.closest('li');
        $li.find('ul input[type="checkbox"]').prop('checked', checked);

        // 2️⃣ Direkt üst checkbox → DOM üzerinden parent li
        const $parentLi = $this.closest('ul').closest('li');
        const $parentCheckbox = $parentLi.children('.node').children('input[type="checkbox"]');
        if(checked && $parentCheckbox.length){
            $parentCheckbox.prop('checked', true);
        }

        // 3️⃣ Ana Menü checkbox durumu: tüm alt elemanlar seçiliyse seç
        const allChecked = $('#menuTree input[type="checkbox"]:not([data-type="root"])').length ===
                           $('#menuTree input[type="checkbox"]:not([data-type="root"])').filter(':checked').length;
        $('input[data-type="root"]').prop('checked', allChecked);
    });

    // Kaydet butonu
    $('#saveBtn').click(()=>{
        const selected=[];
        $('#menuTree input:checked').each(function(){
            selected.push({
                id: $(this).data('id'),
                type: $(this).data('type')
            });
        });

        $.ajax({
            url:'save_menu_selection.php',
            method:'POST',
            data:{ selections: JSON.stringify(selected) },
            success: ()=>alert("Seçimler kaydedildi."),
            error: ()=>alert("Kaydetme sırasında hata oluştu.")
        });
    });
});
</script>
</body>
</html>
