<?php
declare(strict_types=1);
?>
<footer class="border-top bg-white mt-4">
    <div class="container py-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-md-6">
                <div class="fw-semibold"><?= htmlspecialchars(APP_NAME) ?></div>
                <div class="small text-muted">
                    Minimal news experience. Built with PHP + MySQL.
                </div>
            </div>
            <div class="col-12 col-md-6">
                <ul class="list-inline mb-0 text-md-end">
                    <li class="list-inline-item"><a class="link-secondary text-decoration-none"
                            href="<?= htmlspecialchars(BASE_URL) ?>/index.php">Home</a></li>
                    <li class="list-inline-item"><a class="link-secondary text-decoration-none"
                            href="<?= htmlspecialchars(BASE_URL) ?>/search.php?sort=latest">Latest</a></li>
                    <li class="list-inline-item"><a class="link-secondary text-decoration-none"
                            href="<?= htmlspecialchars(BASE_URL) ?>/search.php?sort=popular">Popular</a></li>
                    <li class="list-inline-item"><span class="text-muted">© <?= date('Y') ?></span></li>
                </ul>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>