<?php if (isLoggedIn()): ?>
            </div><!-- /.page-content -->
        </main><!-- /.app-main -->
    </div><!-- /.app-shell -->
<?php else: ?>
            </div><!-- /.page-content-bare -->
<?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?= BASE_URL ?>/assets/js/script.js?v=<?= @filemtime(__DIR__ . '/../assets/js/script.js') ?: time() ?>"></script>
    <?= renderFlash() ?>
</body>
</html>