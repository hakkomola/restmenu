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
  <title>Restoran Menü Tree</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <!-- jsTree CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css">
  <style>
    #menuTree {
      max-height: 600px;
      overflow-y: auto;
      padding: 15px;
      border-radius: 8px;
      border: 1px solid #ddd;
      background-color: #fdfdfd;
    }
    .jstree-anchor.category { 
      font-weight: bold; color: #0d6efd; 
    }
    .jstree-anchor.subcategory { 
      font-weight: 500; color: #198754; 
    }
    .jstree-anchor.item { 
      font-style: italic; color: #6c757d; 
    }
    .jstree-node:hover > .jstree-anchor {
      background-color: #e9ecef;
      border-radius: 4px;
    }
  </style>
</head>
<body class="bg-light">
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-body">
      <h2 class="card-title text-center mb-4">Restoran Menü Tree</h2>
       <div class="d-flex justify-content-ob_start mt-3">
        <button id="saveBtn" class="btn btn-primary">Kaydet</button>
      </div>
      <br>
      <div id="menuTree"></div>
     
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>
<script>
$(function() {
  $.getJSON('get_menu_tree.php', function(data) {

    // Root olarak Ana Menü ekle
    let treeData = [{
      id: "root",
      text: "Ana Menü",
      state: { opened: true },
      data: { type: "root" },
      icon: "bi bi-list-task",
      children: data.map(cat => ({
        id: "cat_" + cat.id,
        text: cat.name,
        data: { type: "category", originalId: cat.id },
        li_attr: { class: "category" },
        icon: "bi bi-folder-fill",
        state: { opened: true }, // Tüm kategoriler açık
        children: cat.subcategories.map(sub => ({
          id: "sub_" + sub.id,
          text: sub.name,
          data: { type: "subcategory", originalId: sub.id, parentCategoryId: cat.id },
          li_attr: { class: "subcategory" },
          icon: "bi bi-folder",
          state: { opened: true }, // Alt kategoriler açık
          children: sub.items.map(item => ({
            id: "item_" + item.id,
            text: item.name,
            icon: "bi bi-file-earmark-text",
            data: { type: "item", originalId: item.id, parentCategoryId: cat.id, parentSubId: sub.id },
            li_attr: { class: "item" }
          }))
        }))
      }))
    }];

    $('#menuTree').jstree({
      plugins: ["checkbox", "wholerow"],
      core: { 
        data: treeData,
        themes: { stripes: true, dots: true, icons: true },
        animation: 200
      }
    }).on('ready.jstree', function() {
      $('#menuTree').jstree('open_all'); // Sayfa yüklendiğinde tüm tree açık
    });
  });

  $('#saveBtn').click(function() {
    let selectedNodes = $('#menuTree').jstree('get_checked', true);
    let selectedData = selectedNodes.map(node => ({
      id: node.data.originalId,
      text: node.text,
      type: node.data.type,
      parentCategoryId: node.data.parentCategoryId || null,
      parentSubId: node.data.parentSubId || null
    }));

    $.ajax({
      url: 'save_menu_selection.php',
      method: 'POST',
      data: { selections: JSON.stringify(selectedData) },
      success: function(res) { alert("Seçimler kaydedildi: " + res); },
      error: function() { alert("Kaydetme sırasında hata oluştu."); }
    });
  });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
