<?php
// includes/bo_footer.php
?>
    </main>
  </div><!-- /bo-content -->
</div><!-- /bo-shell -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const sidebar = document.querySelector('.bo-sidebar');
const overlay = document.querySelector('.bo-overlay');
document.querySelectorAll('.bo-menu a').forEach(a=>{
  a.addEventListener('click', ()=> {
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
  });
});
</script>

</body>
</html>
