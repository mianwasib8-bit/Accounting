<?php
/**
 * Items screen — after categories
 * Category dropdown · Item code auto (cat 2 + item 2) · Name · Packing · Rates · Stock
 * Table with edit/delete icons
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Models\Item;
use App\Models\ItemCategory;

$pageTitle = 'Items';
$activePage = 'item_adder';
$pageScripts = ['/assets/js/item_adder.js'];

$categories = ItemCategory::all();
$items = Item::all();

require dirname(__DIR__, 2) . '/includes/layout_start.php';
?>

<div class="section-head">
  <div>
    <h2>Items</h2>
    <p>Step 2 · Category (2) + Item (2) = code e.g. 0101 · then packing &amp; rates</p>
  </div>
  <a href="<?= e(url('/modules/inventory/category.php')) ?>" class="btn btn-secondary btn-sm">← Categories</a>
</div>

<div class="split-2">
  <div class="card accent-left card-pad">
    <p class="card-kicker">Inventory Master</p>
    <h2 class="card-title">Add / Edit Item</h2>

    <form id="item-form" class="form-grid" novalidate>
      <input type="hidden" id="item_id" value="" />

      <div class="field">
        <label for="category_id">Category</label>
        <select class="select" id="category_id" name="category_id" required>
          <option value="">— Select category —</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" data-code="<?= e($c['code']) ?>">
              <?= e($c['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (!$categories): ?>
          <p class="hint" style="margin:6px 0 0;font-size:12px;color:var(--danger)">
            Pehle <a href="<?= e(url('/modules/inventory/category.php')) ?>" style="color:var(--brand);font-weight:600">Category</a> banao.
          </p>
        <?php endif; ?>
      </div>

      <div class="form-grid cols-2">
        <div class="field">
          <label>Item Code <span class="hint">(auto · 2+2)</span></label>
          <input class="input input-mono" type="text" id="item_code" value="—" readonly disabled />
        </div>
        <div class="field">
          <label for="item_name">Item Name</label>
          <input class="input" type="text" id="item_name" name="item_name" required maxlength="200" placeholder="Item name" />
        </div>
      </div>

      <div class="field">
        <label for="packing">Packing</label>
        <input class="input" type="text" id="packing" name="packing" maxlength="80" placeholder="e.g. Bag, Pcs, Box" />
      </div>

      <div class="form-grid cols-3">
        <div class="field">
          <label for="sale_rate">Sale Rate</label>
          <input class="input" type="number" id="sale_rate" min="0" step="0.01" value="0.00" />
        </div>
        <div class="field">
          <label for="purchase_rate">Purchase Rate</label>
          <input class="input" type="number" id="purchase_rate" min="0" step="0.01" value="0.00" />
        </div>
        <div class="field">
          <label for="stock">Stock</label>
          <input class="input" type="number" id="stock" min="0" step="0.001" value="0" />
        </div>
      </div>

      <div class="flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary" id="btn-save-item">Save</button>
        <button type="button" class="btn btn-secondary" id="btn-update-item" disabled>Update</button>
      </div>
    </form>
  </div>

  <div class="card accent-left">
    <div class="card-pad" style="border-bottom:1px solid var(--line)">
      <h2 class="card-title" style="margin:0;font-size:18px">Saved Items</h2>
    </div>
    <div class="table-wrap">
      <table class="data" id="items-table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Item Name</th>
            <th>Packing</th>
            <th class="text-right">Sale</th>
            <th class="text-right">Pur.</th>
            <th class="text-right">Stock</th>
            <th class="text-center" style="width:100px">Action</th>
          </tr>
        </thead>
        <tbody id="items-tbody">
        <?php if (!$items): ?>
          <tr class="empty-row"><td colspan="7" class="empty-state">No items yet.</td></tr>
        <?php else: foreach ($items as $i): ?>
          <tr class="item-row" data-id="<?= (int)$i['id'] ?>"
              data-category-id="<?= (int)$i['category_id'] ?>"
              data-code="<?= e($i['item_code']) ?>"
              data-name="<?= e($i['item_name']) ?>"
              data-packing="<?= e((string)$i['packing']) ?>"
              data-sale="<?= e((string)$i['sale_rate']) ?>"
              data-pur="<?= e((string)$i['purchase_rate']) ?>"
              data-stock="<?= e((string)$i['stock']) ?>">
            <td class="num" style="color:var(--brand);font-weight:700"><?= e($i['item_code']) ?></td>
            <td><strong><?= e($i['item_name']) ?></strong></td>
            <td><?= e((string)$i['packing']) ?></td>
            <td class="text-right num"><?= number_format((float)$i['sale_rate'], 2) ?></td>
            <td class="text-right num"><?= number_format((float)$i['purchase_rate'], 2) ?></td>
            <td class="text-right num"><?= number_format((float)$i['stock'], 3) ?></td>
            <td class="text-center">
              <button type="button" class="icon-action btn-edit" title="Edit">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              </button>
              <button type="button" class="icon-action btn-del" title="Delete">
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
