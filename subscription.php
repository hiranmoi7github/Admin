<?php
require_once __DIR__ . '/includes/auth_check.php';
requireSuperAdmin();

require_once __DIR__ . '/includes/supabase_api.php';

$success = '';
$error = '';
$editMode = false;
$editPlan = null;

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $deleteResult = deleteSubscription($deleteId);

    if ($deleteResult['success']) {
        header('Location: subscription.php?msg=deleted');
        exit;
    } else {
        $error = 'Failed to delete plan.';
    }
}

/*
|--------------------------------------------------------------------------
| EDIT LOAD
|--------------------------------------------------------------------------
*/
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $planRes = getSubscriptionById($editId);

    if (($planRes['success'] ?? false) && !empty($planRes['data'])) {
        $editMode = true;
        $editPlan = $planRes['data'][0];
    } else {
        $error = 'Plan not found.';
    }
}

/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGES
|--------------------------------------------------------------------------
*/
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') $success = 'Plan added successfully.';
    if ($_GET['msg'] === 'updated') $success = 'Plan updated successfully.';
    if ($_GET['msg'] === 'deleted') $success = 'Plan deleted successfully.';
}

/*
|--------------------------------------------------------------------------
| FORM SUBMIT
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'add');

    $planName = trim($_POST['plan_name'] ?? '');
    $durationLabel = trim($_POST['duration_label'] ?? '');
    $originalPrice = (float)($_POST['original_price'] ?? 0);
    $sellingPrice = (float)($_POST['selling_price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    $offerings = $_POST['offerings'] ?? [];
    $offerings = array_map(static function ($item) {
        return trim((string)$item);
    }, (array)$offerings);
    $offerings = array_values(array_filter($offerings, static function ($item) {
        return $item !== '';
    }));

    if ($planName === '') {
        $error = 'Plan name is required.';
    } elseif ($sellingPrice < 0 || $originalPrice < 0) {
        $error = 'Price cannot be negative.';
    } else {
        if ($action === 'add') {
            $result = addSubscription(
                $planName,
                $durationLabel,
                $originalPrice,
                $sellingPrice,
                $description,
                $offerings,
                $status
            );

            if ($result['success']) {
                header('Location: subscription.php?msg=added');
                exit;
            } else {
                $error = 'Failed to add subscription plan.';
            }
        }

        if ($action === 'update') {
            $id = (int)($_POST['subscription_id'] ?? 0);

            if ($id <= 0) {
                $error = 'Invalid plan ID.';
            } else {
                $result = updateSubscription(
                    $id,
                    $planName,
                    $durationLabel,
                    $originalPrice,
                    $sellingPrice,
                    $description,
                    $offerings,
                    $status
                );

                if ($result['success']) {
                    header('Location: subscription.php?msg=updated');
                    exit;
                } else {
                    $error = 'Failed to update subscription plan.';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH ALL PLANS
|--------------------------------------------------------------------------
*/
$plansRes = getAllSubscriptions();
$plans = ($plansRes['success'] ?? false) ? ($plansRes['data'] ?? []) : [];

$currentOfferings = [''];
if ($editMode && !empty($editPlan)) {
    $storedOfferings = $editPlan['offerings'] ?? [];
    if (is_string($storedOfferings)) {
        $decoded = json_decode($storedOfferings, true);
        if (is_array($decoded)) {
            $storedOfferings = $decoded;
        }
    }

    if (is_array($storedOfferings) && !empty($storedOfferings)) {
        $currentOfferings = array_values($storedOfferings);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscription Plans</title>
    <link rel="stylesheet" href="assets/css/subscription.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="page-wrap">
    <div class="page-top">
        <div>
            <h1>Manage Subscription Plans</h1>
            <p>Control plan prices, offers, duration, and active status.</p>
        </div>
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>

    <?php if ($success !== ''): ?><div class="msg success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="msg error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="sub-grid">
        <div class="card">
            <h3><?php echo $editMode ? 'Edit Plan' : 'Add New Plan'; ?></h3>

            <form method="POST" class="sub-form">
                <input type="hidden" name="action" value="<?php echo $editMode ? 'update' : 'add'; ?>">
                <input type="hidden" name="subscription_id" value="<?php echo $editMode ? (int)$editPlan['id'] : ''; ?>">

                <div class="group">
                    <label for="plan_name">Plan Name</label>
                    <input type="text" id="plan_name" name="plan_name" value="<?php echo $editMode ? htmlspecialchars($editPlan['plan_name'] ?? '') : ''; ?>" required>
                </div>

                <div class="group">
                    <label for="duration_label">Duration Label</label>
                    <input type="text" id="duration_label" name="duration_label" value="<?php echo $editMode ? htmlspecialchars($editPlan['duration_label'] ?? '') : ''; ?>" placeholder="e.g. 1 Day / 6 Months / 12 Months">
                </div>

                <div class="group two-col">
                    <div>
                        <label for="original_price">Original Price</label>
                        <input type="number" step="0.01" id="original_price" name="original_price" value="<?php echo $editMode ? htmlspecialchars((string)($editPlan['original_price'] ?? 0)) : '0'; ?>">
                    </div>
                    <div>
                        <label for="selling_price">Selling Price</label>
                        <input type="number" step="0.01" id="selling_price" name="selling_price" value="<?php echo $editMode ? htmlspecialchars((string)($editPlan['selling_price'] ?? 0)) : '0'; ?>">
                    </div>
                </div>

                <div class="group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Write short plan details..."><?php echo $editMode ? htmlspecialchars($editPlan['description'] ?? '') : ''; ?></textarea>
                </div>

                <div class="group">
                    <label>Offerings</label>
                    <div id="offerings-wrapper">
                        <?php foreach ($currentOfferings as $offering): ?>
                            <div class="offering-row">
                                <input type="text" name="offerings[]" value="<?php echo htmlspecialchars((string)$offering); ?>" placeholder="e.g. Full materials">
                                <button type="button" class="remove-offering-btn" onclick="removeOfferingRow(this)">−</button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="add-offering-btn" onclick="addOfferingRow()">+ Add More</button>
                </div>

                <div class="group">
                    <label for="status">Status</label>
                    <?php $currentStatus = $editMode ? ($editPlan['status'] ?? 'active') : 'active'; ?>
                    <select id="status" name="status">
                        <option value="active" <?php echo $currentStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $currentStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="draft" <?php echo $currentStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>

                <button type="submit" class="btn primary-btn">
                    <?php echo $editMode ? 'Update Plan' : 'Add Plan'; ?>
                </button>

                <?php if ($editMode): ?>
                    <a href="subscription.php" class="btn secondary-btn">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <h3>All Subscription Plans</h3>

            <div class="plans-wrap">
                <?php if (!empty($plans)): ?>
                    <?php foreach ($plans as $plan): ?>
                        <?php
                        $statusValue = $plan['status'] ?? 'draft';
                        $originalPrice = (float)($plan['original_price'] ?? 0);
                        $sellingPrice = (float)($plan['selling_price'] ?? 0);
                        $discountAmount = max(0, $originalPrice - $sellingPrice);

                        $planOfferings = $plan['offerings'] ?? [];
                        if (is_string($planOfferings)) {
                            $decoded = json_decode($planOfferings, true);
                            if (is_array($decoded)) {
                                $planOfferings = $decoded;
                            }
                        }
                        if (!is_array($planOfferings)) {
                            $planOfferings = [];
                        }
                        ?>
                        <div class="plan-card">
                            <div class="plan-head">
                                <div>
                                    <h4><?php echo htmlspecialchars($plan['plan_name'] ?? '-'); ?></h4>
                                    <p><?php echo htmlspecialchars($plan['duration_label'] ?? '-'); ?></p>
                                </div>
                                <span class="tag <?php echo htmlspecialchars(strtolower($statusValue)); ?>">
                                    <?php echo htmlspecialchars(ucfirst($statusValue)); ?>
                                </span>
                            </div>

                            <div class="price-block">
                                <div class="selling-price">₹<?php echo number_format($sellingPrice, 2); ?></div>
                                <div class="original-price">₹<?php echo number_format($originalPrice, 2); ?></div>
                            </div>

                            <?php if ($discountAmount > 0): ?>
                                <div class="discount-badge">
                                    Offer Save ₹<?php echo number_format($discountAmount, 2); ?>
                                </div>
                            <?php endif; ?>

                            <p class="plan-desc"><?php echo htmlspecialchars($plan['description'] ?? '-'); ?></p>

                            <?php if (!empty($planOfferings)): ?>
                                <div class="offerings-view">
                                    <h5>Offerings</h5>
                                    <ul>
                                        <?php foreach ($planOfferings as $item): ?>
                                            <li><?php echo htmlspecialchars((string)$item); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <div class="row-actions">
                                <a class="mini-btn edit-btn" href="subscription.php?edit_id=<?php echo (int)$plan['id']; ?>">Edit</a>
                                <a class="mini-btn delete-btn" href="subscription.php?delete_id=<?php echo (int)$plan['id']; ?>" onclick="return confirm('Delete this plan?');">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">No plans found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function addOfferingRow(value = '') {
    const wrapper = document.getElementById('offerings-wrapper');
    const row = document.createElement('div');
    row.className = 'offering-row';
    row.innerHTML = `
        <input type="text" name="offerings[]" value="${value.replace(/"/g, '&quot;')}" placeholder="e.g. Full materials">
        <button type="button" class="remove-offering-btn" onclick="removeOfferingRow(this)">−</button>
    `;
    wrapper.appendChild(row);
}

function removeOfferingRow(button) {
    const wrapper = document.getElementById('offerings-wrapper');
    const rows = wrapper.querySelectorAll('.offering-row');

    if (rows.length <= 1) {
        const input = rows[0].querySelector('input');
        if (input) input.value = '';
        return;
    }

    button.parentElement.remove();
}
</script>
</body>
</html>