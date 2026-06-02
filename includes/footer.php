<?php

declare(strict_types=1);
?>
        </div>
    </main>
</div>

<footer class="py-3 border-top bg-white">
    <div class="container-fluid d-flex justify-content-between flex-wrap small text-muted">
        <span>&copy; <?= date('Y') ?> SmartBlood Connect</span>
        <span>Secure blood request, matching, and inventory management platform.</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/main.js')) ?>"></script>
<?php if (!empty($withMaps)): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <?php if (sb_map_provider() === 'google' && MAP_GOOGLE_API_KEY !== ''): ?>
        <script src="https://maps.googleapis.com/maps/api/js?key=<?= e(MAP_GOOGLE_API_KEY) ?>&libraries=places"></script>
    <?php endif; ?>
    <script src="<?= e(asset('js/maps.js')) ?>"></script>
    <script src="<?= e(asset('js/location-picker.js')) ?>"></script>
<?php endif; ?>
<?php if (!empty($withChartJs)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="<?= e(asset('js/charts.js')) ?>"></script>
<?php endif; ?>
<?php if (!empty($extraScripts) && is_array($extraScripts)): ?>
    <?php foreach ($extraScripts as $script): ?>
        <script src="<?= e($script) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
