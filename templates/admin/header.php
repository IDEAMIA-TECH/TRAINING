  <div class="navbar-nav">
      <?php if ($settings->get('maintenance_mode', false)): ?>
          <div class="nav-item me-3">
              <span class="badge bg-warning">Modo Mantenimiento Activo</span>
          </div>
      <?php endif; ?>
      <div class="nav-item dropdown"> 