<?php
/**
 * Item Categories — Screen 1
 * Code auto 2 digits · Title · table with edit/delete icons
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Models\ItemCategory;

$pageTitle = 'Item Categories';
$activePage = 'item_category';
$pageScripts = ['/assets/js/item_category.js'];

$categories = ItemCategory::all();
$nextCode = ItemCategory::nextCode();

require dirname(__DIR__, 2) . '/includes/layout_start.php';
?>

<div class="section-head">
  <div>
    <h2>Item Categories</h2>
    <p>Step 1 · Create category first · then add items</p>
  </div>
  <a href="<?= e(url('/modules/inventory/item_adder.php')) ?>" class="btn btn-secondary btn-sm">Go to Items →</a>
</div>

<div class="split-2">
  <div class="card accent-left card-pad">
    <p class="card-kicker">Inventory</p>
    <h2 class="card-title">Add Category</h2>
    <form id="cat-form" class="form-grid" novalidate>
      <input type="hidden" id="cat_id" value="" />
      <div class="field">
        <label>Category Code <span class="hint">(auto · 2 digits)</span></label>
        <input class="input input-mono" type="text" id="cat_code" value="<?= e($nextCode) ?>" readonly disabled />
      </div>
      <div class="field">
        <label for="cat_title">Category Title / Name</label>
        <input class="input" type="text" id="cat_title" required maxlength="150" placeholder="e.g. Cement, Hardware" autofocus />
      </div>
      <div class="flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary" id="btn-cat-save">Save</button>
        <button type="button" class="btn btn-secondary" id="btn-cat-update" disabled>Update</button>
      </div>
    </form>
  </div>

  <div class="card accent-left">
    <div class="card-pad" style="border-bottom:1px solid var(--line)">
      <h2 class="card-title" style="margin:0;font-size:18px">Saved Categories</h2>
    </div>
    <div class="table-wrap">
      <table class="data" id="cat-table">
        <thead>
          <tr>
            <th style="width:90px">Code</th>
            <th>Title</th>
            <th class="text-center" style="width:100px">Action</th>
          </tr>
        </thead>
        <tbody id="cat-tbody">
        <?php if (!$categories): ?>
          <tr class="empty-row"><td colspan="3" class="empty-state">No categories yet.</td></tr>
        <?php else: foreach ($categories as $c): ?>
          <tr data-id="<?= (int)$c['id'] ?>" data-code="<?= e($c['code']) ?>" data-title="<?= e($c['title']) ?>">
            <td class="num" style="color:var(--brand);font-weight:700"><?= e($c['code']) ?></td>
            <td><strong><?= e($c['title']) ?></strong></td>
            <td class="text-center">
              <button type="button" class="icon-action btn-edit" title="Edit" aria-label="Edit">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              </button>
              <button type="button" class="icon-action btn-del" title="Delete" aria-label="Delete">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
