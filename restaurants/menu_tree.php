<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header("Location: restaurants/login.php");
    exit;
}

include __DIR__ . '/../includes/navbar.php';
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

.level1 > .node { padding-left:8px; font-weight:600; }
.level2 > .node { padding-left:24px; }
.level3 > .node { padding-left:40px; }
.level4 > .node { padding-left:56px; }

/* Kaydet buton pozisyonu */
#saveBtn { margin-bottom: 15px; }
</style>
</head>
<body>
<div class="container mt-4">
<div class="card shadow-sm">
<div class="card-body">

<div class="d-flex justify-content-start mb-3">
  <button id="saveBtn" class="btn btn-primary">Kaydet</button>
</div>

<h3 class="text-center mb-3">Restoran Menü Ağacı</h3>

<div class="tree" id="menuTree">
  <div id="treeContent"></div>
</div>

</div>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script>
function renderTree(data) {
    let html = '<ul>';

    html += `<li class="level1">
        <div class="node"><input type="checkbox" data-id="root" data-type="root"><i class="bi bi-house-door-fill"></i> Ana Menü</div>
        <ul>`;

    data.forEach(cat=>{
        const hasSubs = cat.subcategories?.length > 0;
        const toggleCat = hasSubs ? '<i class="bi bi-chevron-down toggle"></i>' : '<i class="bi bi-dot text-secondary"></i>';
        html += `<li class="level2 category">
            <div class="node">${toggleCat}<input type="checkbox" data-id="${cat.id}" data-type="category" data-text="${cat.name}"><i class="bi bi-folder2-open"></i> ${cat.name}</div>`;

        if(hasSubs){
            html += '<ul>';
            cat.subcategories.forEach(sub=>{
                const hasItems = sub.items?.length>0;
                const toggleSub = hasItems ? '<i class="bi bi-chevron-down toggle"></i>' : '<i class="bi bi-dot text-secondary"></i>';
                html += `<li class="level3 subcategory">
                    <div class="node">${toggleSub}<input type="checkbox" data-id="${sub.id}" data-type="subcategory" data-parent-category="${cat.id}" data-text="${sub.name}"><i class="bi bi-folder"></i> ${sub.name}</div>`;

                if(hasItems){
                    html += '<ul>';
                    sub.items.forEach(item=>{
                        html += `<li class="level4 item">
                            <div class="node"><input type="checkbox" data-id="${item.id}" data-type="item" data-parent-category="${cat.id}" data-parent-sub="${sub.id}" data-text="${item.name}"><i class="bi bi-file-earmark-text"></i> ${item.name}</div>
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

    html += '</ul></li></ul>';
    return html;
}

$(function(){
    $.getJSON('get_menu_tree.php', function(data){
        $('#treeContent').html(renderTree(data));
    });

    $('#menuTree').on('click', '.toggle', function(e){
        e.stopPropagation();
        $(this).closest('li').toggleClass('collapsed');
    });

    $('#menuTree').on('change', 'input[type="checkbox"]', function(){
        const $this = $(this);
        const checked = $this.prop('checked');
        const $li = $this.closest('li');

        $li.find('ul input[type="checkbox"]').prop('checked', checked);

        let $parentLi = $this.closest('ul').closest('li');
        while($parentLi.length){
            const $parentCheckbox = $parentLi.children('.node').children('input[type="checkbox"]');
            if($parentCheckbox.length){
                if(checked){
                    $parentCheckbox.prop('checked', true);
                } else {
                    const anyChecked = $parentLi.find('ul input[type="checkbox"]:checked').length>0;
                    $parentCheckbox.prop('checked', anyChecked);
                }
            }
            $parentLi = $parentLi.closest('ul').closest('li');
        }

        const allChecked = $('#menuTree input[type="checkbox"]:not([data-id="root"])').length ===
                           $('#menuTree input[type="checkbox"]:not([data-id="root"])').filter(':checked').length;
        $('input[data-id="root"]').prop('checked', allChecked);
    });

    $('#saveBtn').click(function(){
        let selected = [];
        $('#menuTree input:checked').each(function(){
            const id = $(this).data('id');
            const type = $(this).data('type');
            if(id !== 'root'){
                selected.push({
                    id: id,
                    type: type,
                    parentCategoryId: $(this).data('parent-category') || null,
                    parentSubId: $(this).data('parent-sub') || null,
                    text: $(this).data('text') || $(this).closest('.node').text().trim()
                });
            }
        });

        $.ajax({
            url:'save_menu_selection.php',
            method:'POST',
            data:{ selections: JSON.stringify(selected) },
            success:function(res){
                alert("Seçimler kaydedildi: " + res);
                window.location.href = 'dashboard.php'; // Kaydetme sonrası yönlendirme
            },
            error:function(xhr){ alert("Kaydetme sırasında hata: " + xhr.responseText); }
        });
    });
});
</script>
</body>
</html>
