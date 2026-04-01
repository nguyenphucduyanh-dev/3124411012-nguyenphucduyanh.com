</main><!-- /#page-content -->
</div><!-- /#main-wrap -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // Sidebar toggle (mobile)
  document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
  });

  // Auto-dismiss flash messages after 4s
  document.querySelectorAll('.flash-container .alert').forEach(el => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      bsAlert.close();
    }, 4000);
  });
</script>
</body>
</html>
