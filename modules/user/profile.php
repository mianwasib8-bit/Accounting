<?php
/**
 * User Profile
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;

$pageTitle = 'Profile';
$activePage = 'profile';

$message = '';
$error = '';
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($full === '') {
        $error = 'Full name is required.';
    } else {
        $db = Database::getInstance();
        $db->query(
            'UPDATE users SET full_name = :n, email = :e, phone = :p WHERE id = :id',
            ['n' => $full, 'e' => $email ?: null, 'p' => $phone ?: null, 'id' => Auth::id()]
        );
        Auth::updateSession(['full_name' => $full, 'email' => $email, 'phone' => $phone]);
        Audit::log(Auth::id(), 'update', 'profile', (string)Auth::id(), 'Updated profile');
        $message = 'Profile updated successfully.';
        $user = Auth::user();
    }
}

require dirname(__DIR__, 2) . '/includes/layout_start.php';
?>

<div class="section-head">
  <div>
    <h2>User Profile</h2>
    <p>Manage your account details</p>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success mb-4"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error mb-4"><?= e($error) ?></div><?php endif; ?>

<div class="card card-pad" style="max-width:560px">
  <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px">
    <div class="avatar" style="width:56px;height:56px;font-size:20px">
      <?= e(strtoupper(substr($user['full_name'] ?? 'U', 0, 1))) ?>
    </div>
    <div>
      <div style="font-weight:800;font-size:18px"><?= e($user['full_name'] ?? '') ?></div>
      <div style="font-size:13px;color:#94a3b8;text-transform:capitalize"><?= e($user['role'] ?? '') ?> · @<?= e($user['username'] ?? '') ?></div>
    </div>
  </div>
  <form method="POST" class="form-grid">
    <div class="field">
      <label>Username</label>
      <input class="input" type="text" value="<?= e($user['username'] ?? '') ?>" readonly disabled />
    </div>
    <div class="field">
      <label for="full_name">Full Name</label>
      <input class="input" type="text" id="full_name" name="full_name" required value="<?= e($user['full_name'] ?? '') ?>" />
    </div>
    <div class="field">
      <label for="email">Email</label>
      <input class="input" type="email" id="email" name="email" value="<?= e($user['email'] ?? '') ?>" />
    </div>
    <div class="field">
      <label for="phone">Phone</label>
      <input class="input" type="text" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>" />
    </div>
    <div>
      <button type="submit" class="btn btn-primary">Save Profile</button>
    </div>
  </form>
</div>

<?php require dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
