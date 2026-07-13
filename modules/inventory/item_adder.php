<?php
/**
 * New Item Adder
 * Category (user-add) · Auto item code · Name · Packing · Sale rate · Purchase rate · Stock
 * Right side: saved items table
 * No picture field
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Models\Item;
use App\Models\ItemCategory;

$pageTitle = 'New Item Adder';
$activePage = 'item_adder';
$pageScripts = ['/assets/js/item_adder.js'];

$categories = ItemCategory::all();
$items = Item::all();
$nextCatCode = ItemCategory::nextCode();

require dirname(__DIR__, 2) . '/includes/layout_start.php';
?>

<div class="section-head">
  <div>
    <h2>New Item Adder</h2>
    <p>Categories + items master · ready for Purchase form later</p>
  </div>
</div>

<div class="split-2">
  <!-- Left: form -->
  <div class="card accent-left card-pad">
    <p class="card-kicker">Inventory Master</p>
    <h2 class="card-title" style="margin-bottom:14px">Add / Edit Item</h2>

    <form id="item-form" class="form-grid" novalidate>
      <input type="hidden" id="item_id" value="" />

      <div class="field">
        <label for="category_id">Category</label>
        <div class="flex gap-2 items-center">
          <select class="select" id="category_id" name="category_id" required style="flex:1">
            <option value="">— Select category —</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" data-code="<?= e($c['code']) ?>">
                <?= e($c['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-secondary btn-sm" id="btn-add-category" title="Add category">+ Cat</button>
        </div>
        <p class="hint mt-1" style="margin:4px 0 0;font-size:11px;color:var(--muted)">
          Category code (auto): <strong id="cat_code_view" class="num"><?= e($nextCatCode) ?></strong>
        </p>
      </div>

      <div class="form-grid cols-2">
        <div class="field">
          <label>Item Code <span class="hint">(auto)</span></label>
          <input class="input input-mono" type="text" id="item_code" value="—" readonly disabled />
        </div>
        <div class="field">
          <label for="packing">Packing</label>
          <input class="input" type="text" id="packing" name="packing" maxlength="80" placeholder="e.g. Bag, Pcs, Box" />
        </div>
      </div>

      <div class="field">
        <label for="item_name">Item Name</label>
        <input class="input" type="text" id="item_name" name="item_name" required maxlength="200" placeholder="Item name" />
      </div>

      <div class="form-grid cols-3">
        <div class="field">
          <label for="sale_rate">Sale Rate</label>
          <input class="input" type="number" id="sale_rate" name="sale_rate" min="0" step="0.01" value="0.00" />
        </div>
        <div class="field">
          <label for="purchase_rate">Purchase Rate</label>
          <input class="input" type="number" id="purchase_rate" name="purchase_rate" min="0" step="0.01" value="0.00" />
        </div>
        <div class="field">
          <label for="stock">Stock</label>
          <input class="input" type="number" id="stock" name="stock" min="0" step="0.001" value="0" />
        </div>
      </div>

      <div class="flex gap-2 flex-wrap mt-2">
        <button type="submit" class="btn btn-primary" id="btn-save-item">Save</button>
        <button type="button" class="btn btn-secondary" id="btn-update-item" disabled>Update</button>
        <button type="button" class="btn btn-secondary" id="btn-clear-item">Clear</button>
        <button type="button" class="btn btn-secondary" id="btn-delete-item" disabled style="color:var(--danger)">Delete</button>
      </div>
    </form>
  </div>

  <!-- Right: table -->
  <div class="card accent-left">
    <div class="card-pad" style="border-bottom:1px solid var(--line)">
      <h2 class="card-title" style="margin:0;font-size:18px">Items List</h2>
      <p style="margin:4px 0 0;font-size:12px;color:var(--muted)">Click a row to edit</p>
    </div>
    <div class="table-wrap">
      <table class="data" id="items-table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Item Name</th>
            <th>Packing</th>
            <th class="text-right">Sale Rate</th>
            <th class="text-right">Pur. Rate</th>
            <th class="text-right">Stock</th>
          </tr>
        </thead>
        <tbody id="items-tbody">
        <?php if (!$items): ?>
          <tr class="empty-row"><td colspan="6" class="empty-state">No items yet. Add from the left form.</td></tr>
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
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Category modal -->
<div id="cat-modal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
  <div class="modal-card">
    <h3>Add Category</h3>
    <p>Code auto-generates (2 digits). You only enter the name.</p>
    <form id="cat-form" class="form-grid">
      <div class="field">
        <label>Category Code</label>
        <input class="input input-mono" type="text" id="new_cat_code" value="<?= e($nextCatCode) ?>" readonly disabled />
      </div>
      <div class="field">
        <label for="new_cat_title">Category Name</label>
        <input class="input" type="text" id="new_cat_title" required maxlength="150" placeholder="e.g. Cement, Hardware" autofocus />
      </div>
      <div class="flex gap-2" style="justify-content:flex-end">
        <button type="button" class="btn btn-secondary" id="cat-cancel">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Category</button>
      </div>
    </form>
  </div>
</div>

<?php require dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
