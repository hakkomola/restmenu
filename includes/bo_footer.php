<?php
// includes/bo_footer.php
?>
    </main>
  </div><!-- /bo-content -->
</div><!-- /bo-shell -->

<!-- Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Sidebar kontrolü -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.querySelector('.bo-sidebar');
  const toggleBtn = document.getElementById('sidebarToggle');

  // Mobilde dış alan için overlay oluştur
  let overlay = document.createElement('div');
  overlay.className = 'bo-overlay';
  document.body.appendChild(overlay);

  // Aç/kapa
  toggleBtn?.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
  });

  // Overlay'e tıklayınca kapat
  overlay.addEventListener('click', () => {
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
  });

  // Menü linkine tıklanınca da kapat
  document.querySelectorAll('.bo-menu a').forEach(a => {
    a.addEventListener('click', () => {
      sidebar.classList.remove('active');
      overlay.classList.remove('active');
    });
  });
});
</script>

</body>
</html>
