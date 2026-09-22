<?php
require_once __DIR__ . '/includes/auth_check.php';
requireSuperAdmin();
require_once __DIR__ . '/includes/supabase_api.php';

$success = '';
$error = '';

$settingsRes = getSettings();
$settings = ($settingsRes['success'] ?? false && !empty($settingsRes['data']))
    ? $settingsRes['data'][0]
    : [];

$uploadDir = __DIR__ . '/assets/uploads/settings/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $siteName = $_POST['site_name'] ?? '';
    $contactEmail = $_POST['contact_email'] ?? '';
    $contactPhone = $_POST['contact_phone'] ?? '';
    $whatsapp = $_POST['whatsapp_number'] ?? '';
    $supportEmail = $_POST['support_email'] ?? '';
    $currency = $_POST['currency'] ?? '₹';
    $primaryColor = $_POST['primary_color'] ?? '#6d28d9';
    $maintenance = isset($_POST['maintenance_mode']);

    $logoUrl = $settings['logo_url'] ?? '';
    $faviconUrl = $settings['favicon_url'] ?? '';

    // LOGO UPLOAD
    if (!empty($_FILES['logo']['name'])) {
        $name = 'logo_' . time() . '.png';
        move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $name);
        $logoUrl = 'assets/uploads/settings/' . $name;
    }

    // FAVICON UPLOAD
    if (!empty($_FILES['favicon']['name'])) {
        $name = 'favicon_' . time() . '.png';
        move_uploaded_file($_FILES['favicon']['tmp_name'], $uploadDir . $name);
        $faviconUrl = 'assets/uploads/settings/' . $name;
    }

    $result = updateSettings([
        'site_name' => $siteName,
        'logo_url' => $logoUrl,
        'favicon_url' => $faviconUrl,
        'contact_email' => $contactEmail,
        'contact_phone' => $contactPhone,
        'whatsapp_number' => $whatsapp,
        'support_email' => $supportEmail,
        'currency' => $currency,
        'primary_color' => $primaryColor,
        'maintenance_mode' => $maintenance,
        'updated_at' => date('c')
    ]);

    if ($result['success']) {
        header("Location: settings.php?msg=updated");
        exit;
    } else {
        $error = "Failed to update settings";
    }
}

if (isset($_GET['msg'])) {
    $success = "Settings updated successfully";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Settings</title>
    <link rel="stylesheet" href="assets/css/settings.css">
</head>
<body>

<div class="container">
    <h1>Settings</h1>

    <?php if($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>
    <?php if($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <div class="card">
            <h3>Platform</h3>

            <input type="text" name="site_name" placeholder="Site Name" value="<?php echo $settings['site_name'] ?? ''; ?>">

            <label>Logo</label>
            <input type="file" name="logo">
            <?php if(!empty($settings['logo_url'])): ?>
                <img src="<?php echo $settings['logo_url']; ?>" class="preview">
            <?php endif; ?>

            <label>Favicon</label>
            <input type="file" name="favicon">
            <?php if(!empty($settings['favicon_url'])): ?>
                <img src="<?php echo $settings['favicon_url']; ?>" class="preview small">
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Contact</h3>

            <input type="email" name="contact_email" placeholder="Contact Email" value="<?php echo $settings['contact_email'] ?? ''; ?>">
            <input type="text" name="contact_phone" placeholder="Phone" value="<?php echo $settings['contact_phone'] ?? ''; ?>">
            <input type="text" name="whatsapp_number" placeholder="WhatsApp" value="<?php echo $settings['whatsapp_number'] ?? ''; ?>">
            <input type="email" name="support_email" placeholder="Support Email" value="<?php echo $settings['support_email'] ?? ''; ?>">
        </div>

        <div class="card">
            <h3>System</h3>

            <input type="text" name="currency" value="<?php echo $settings['currency'] ?? '₹'; ?>">

            <label>Primary Color</label>
            <input type="color" name="primary_color" value="<?php echo $settings['primary_color'] ?? '#6d28d9'; ?>">

            <label class="toggle">
                <input type="checkbox" name="maintenance_mode" <?php echo !empty($settings['maintenance_mode']) ? 'checked' : ''; ?>>
                Maintenance Mode
            </label>
        </div>

        <button class="save-btn">Save Settings</button>

    </form>
</div>

</body>
</html>