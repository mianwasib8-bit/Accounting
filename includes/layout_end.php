<?php
/**
 * Shared authenticated layout — end
 */
declare(strict_types=1);
?>
    </main>
  </div>
</div>
<script src="<?= e(url('/assets/js/app.js')) ?>?v=12"></script>
<?php if (!empty($pageScripts)): ?>
  <?php foreach ((array)$pageScripts as $src): ?>
    <script src="<?= e(url($src)) ?>?v=12"></script>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
