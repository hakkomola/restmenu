<?php
session_start();
require_once __DIR__ . '/../db.php';

// Login kontrolü
if (!isset($_SESSION['restaurant_id'])) {
    header("Location: restaurants/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title>Restoran Menü Tree</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- jsTree CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css">
  <style>
    #menuTree {
      max-height: 600px;
      overflow-y: auto;
      border: 1px solid #ddd;
      padding: 15px;
      border-radius: 8px;
      background-color: #f9f9f9;
    }
    h2 {
      margin-bottom: 20px;
    }
  </style>
</head>
<body class="bg-light">
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-body">
      <h2 class="card-title text-center">Restoran Menü Tree</h2>
      <div id="menuTree"></div>
      <div class="d-flex justify-content-end mt-3">
        <button id="saveBtn" class="btn btn-primary">Kaydet</button>
      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>
<script>
$(function() {
  $.getJSON('get_menu_tree.php', function(data) {
    let treeData = data.map(cat => ({
      id: "cat_" + cat.id,
      text: cat.name,
      data: { type: "category", originalId: cat.id },
      children: cat.subcategories.map(sub => ({
        id: "sub_" + sub.id,
        text: sub.name,
        data: { type: "subcategory", originalId: sub.id, parentCategoryId: cat.id },
        children: sub.items.map(item => ({
          id: "item_" + item.id,
          text: item.name,
          icon: "jstree-file",
          data: { type: "item", originalId: item.id, parentCategoryId: cat.id, parentSubId: sub.id }
        }))
      }))
    }));

    $('#menuTree').jstree({ plugins: ["checkbox"], core: { data: treeData } });
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
      success: function(res) { 
        alert("Seçimler kaydedildi: " + res); 
      },
      error: function() { 
        alert("Kaydetme sırasında hata oluştu."); 
      }
    });
  });
});
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
