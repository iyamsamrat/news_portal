<?php
declare(strict_types=1);
?>
<footer class="mt-auto border-top bg-white">
    <div class="container-fluid py-3 d-flex justify-content-between align-items-center">
        <div class="small text-muted">
            <?= htmlspecialchars(APP_NAME) ?> Admin • © <?= date('Y') ?>
        </div>
        <div class="small text-muted">
            <span class="d-none d-md-inline">Minimal CMS</span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</div> <!-- /flex-grow-1 from admin_nav.php -->
</div> <!-- /admin-shell from admin_header.php -->
</body>

</html>