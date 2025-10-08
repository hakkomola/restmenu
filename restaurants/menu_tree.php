<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header("Location: restaurants/login.php");
    exit;
}

// 🔹 HEADER ve NAVBAR dahil
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';

?>


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


<?php include __DIR__ . '/../includes/footer.php'; ?>
