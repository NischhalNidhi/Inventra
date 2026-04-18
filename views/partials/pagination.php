<?php
/**
 * Expected variables:
 * $totalItems - Total number of items
 * $perPage - Items per page
 * $currentPageNum - Current page
 */
$totalPages = (int) ceil($totalItems / $perPage);

if ($totalPages > 1):
    $queryParams = $_GET;
    unset($queryParams['p']);
?>
<div class="pagination" style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
    <div>
        <?php if ($currentPageNum > 1): ?>
            <a href="<?= e(basePath('index.php?' . http_build_query(array_merge($queryParams, ['p' => $currentPageNum - 1])))); ?>" class="button ghost">Previous</a>
        <?php else: ?>
            <button class="button ghost" disabled>Previous</button>
        <?php endif; ?>
    </div>
    <div class="page-numbers">
        Page <?= e((string) $currentPageNum); ?> of <?= e((string) $totalPages); ?>
    </div>
    <div>
        <?php if ($currentPageNum < $totalPages): ?>
            <a href="<?= e(basePath('index.php?' . http_build_query(array_merge($queryParams, ['p' => $currentPageNum + 1])))); ?>" class="button ghost">Next</a>
        <?php else: ?>
            <button class="button ghost" disabled>Next</button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
