        </div>
    </main>

    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light border-top">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-4 text-center text-md-start mb-2 mb-md-0">
                    <div class="d-flex align-items-center justify-content-center justify-content-md-start">
                        <a href="<?= SITE_URL ?>" class="d-flex align-items-center text-decoration-none">
                            <img src="<?= SITE_URL ?>/assets/img/<?= LOGO_MEPC ?>" alt="Logo MEPC" height="35" class="footer-logo me-2">
                            <span class="fw-bold small text-dark"><?= SITE_NAME ?></span>
                        </a>
                    </div>
                </div>
                <div class="col-md-4 text-center mb-2 mb-md-0">
                    <small class="text-muted d-block"><?= MINISTRY_NAME ?></small>
                    <small class="text-muted">© <?= date('Y') ?> <a href="https://www.linkedin.com/in/dr-aboubekrine-sakho-4851981b0/" target="_blank" class="text-decoration-none">Abou Sakho</a> - Tous droits réservés</small>
                </div>
                <div class="col-md-4 text-center text-md-end">
                    <small class="text-muted">&copy; <?= date('Y') ?> <?= ORG_NAME ?></small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- QRCode.js (browser version) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <!-- Signature Pad -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

    <!-- Custom JS -->
    <script src="<?= SITE_URL ?>/assets/js/app.js"></script>

    <?php if (isset($extraJs)): ?>
        <?= $extraJs ?>
    <?php endif; ?>
</body>
</html>
