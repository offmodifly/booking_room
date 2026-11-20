  </div>
  <footer class="app-footer">
    <span>© <?= date('Y') + 543 ?> <?= e(APP_NAME) ?> · <?= e(ORG_NAME) ?></span>
    <span>v<?= e(APP_VERSION) ?></span>
  </footer>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script>window.__FLASH = <?= json_encode(take_flashes(), JSON_UNESCAPED_UNICODE) ?>;</script>
<?= $extraScripts ?? '' ?>
</body>
</html>
