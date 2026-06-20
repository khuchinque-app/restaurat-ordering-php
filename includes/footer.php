</main>
<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> Restaurant Ordering System</p>
    </div>
</footer>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<?php if (!empty($extra_js)): ?>
    <script src="<?= APP_URL ?>/assets/js/<?= htmlspecialchars($extra_js) ?>"></script>
<?php endif; ?>
</body>
</html>
