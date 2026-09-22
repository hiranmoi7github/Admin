<?php

require_once __DIR__ . '/includes/auth_check.php';
requireContentAccess();

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| CATEGORY STORAGE
|--------------------------------------------------------------------------
|
| Categories are stored in:
| admin/assets/data/categories.json
|
*/

$dataDir = __DIR__ . '/assets/data/';
$dataFile = $dataDir . 'categories.json';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

if (!file_exists($dataFile)) {
    file_put_contents(
        $dataFile,
        json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}


/*
|--------------------------------------------------------------------------
| LOAD CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

$json = @file_get_contents($dataFile);

if ($json !== false) {

    $decoded = json_decode($json, true);

    if (is_array($decoded)) {
        $categories = $decoded;
    }

}


/*
|--------------------------------------------------------------------------
| ADD CATEGORY
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $categoryName = trim($_POST['category_name'] ?? '');

    if ($categoryName === '') {

        $error = 'Category name is required.';

    } else {

        $categoryExists = false;

        foreach ($categories as $category) {

            if (
                strtolower(trim($category['name'] ?? '')) ===
                strtolower($categoryName)
            ) {

                $categoryExists = true;
                break;

            }

        }


        if ($categoryExists) {

            $error = 'This category already exists.';

        } else {

            $newCategory = [
                'id' => uniqid('cat_', true),
                'name' => $categoryName,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $categories[] = $newCategory;


            $saved = file_put_contents(
                $dataFile,
                json_encode(
                    $categories,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                ),
                LOCK_EX
            );


            if ($saved !== false) {

                header('Location: exam_categories.php?msg=added');
                exit;

            } else {

                $error = 'Failed to save category. Please check folder permissions.';

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| DELETE CATEGORY
|--------------------------------------------------------------------------
*/

if (isset($_GET['delete_id'])) {

    $deleteId = trim($_GET['delete_id']);

    $newCategories = [];

    $found = false;

    foreach ($categories as $category) {

        if (($category['id'] ?? '') === $deleteId) {

            $found = true;
            continue;

        }

        $newCategories[] = $category;

    }


    if ($found) {

        $saved = file_put_contents(
            $dataFile,
            json_encode(
                $newCategories,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            ),
            LOCK_EX
        );


        if ($saved !== false) {

            header('Location: exam_categories.php?msg=deleted');
            exit;

        } else {

            $error = 'Failed to delete category.';

        }

    }

}


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

if (isset($_GET['msg'])) {

    if ($_GET['msg'] === 'added') {

        $success = 'Category added successfully.';

    } elseif ($_GET['msg'] === 'deleted') {

        $success = 'Category deleted successfully.';

    }

}


/*
|--------------------------------------------------------------------------
| SORT
|--------------------------------------------------------------------------
*/

usort($categories, function ($a, $b) {

    return strcasecmp(
        $a['name'] ?? '',
        $b['name'] ?? ''
    );

});

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Exam Categories</title>

    <link
        rel="stylesheet"
        href="assets/css/exam_categories.css"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

</head>


<body>

<div class="page-wrap">


    <div class="page-top">

        <div>

            <h1>Exam Categories</h1>

            <p>
                Create and manage categories for your exams.
            </p>

        </div>


        <a
            href="dashboard.php"
            class="back-link"
        >
            ← Back to Dashboard
        </a>

    </div>


    <?php if ($success !== ''): ?>

        <div class="msg success">

            <?php echo htmlspecialchars($success); ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="msg error">

            <?php echo htmlspecialchars($error); ?>

        </div>

    <?php endif; ?>


    <div class="category-options">


        <!-- ADD NEW CATEGORY -->

        <div class="option-card add-category-card">

            <div class="option-icon">
                +
            </div>

            <h2>Add New Category</h2>

            <p>
                Create a new category that can be assigned
                to one or more exams.
            </p>


            <form
                method="POST"
                class="category-form"
            >

                <div class="group">

                    <label for="category_name">
                        Category Name
                    </label>

                    <input
                        type="text"
                        id="category_name"
                        name="category_name"
                        placeholder="e.g. Government Exams"
                        required
                        autocomplete="off"
                    >

                </div>


                <button
                    type="submit"
                    class="btn primary-btn"
                >
                    Save Category
                </button>

            </form>

        </div>


        <!-- EXISTING CATEGORIES -->

        <a
            href="exams.php"
            class="option-card existing-category-card"
        >

            <div class="option-icon">
                ≡
            </div>

            <h2>Existing Categories</h2>

            <p>
                Open the existing exam management page
                and assign multiple categories to exams.
            </p>


            <span class="option-button">
                Manage Exams →
            </span>

        </a>

    </div>


    <!-- SAVED CATEGORIES -->

    <div class="card">

        <div class="card-heading">

            <div>

                <h2>Saved Categories</h2>

                <p>
                    Categories currently available for exams.
                </p>

            </div>


            <span class="count-badge">

                <?php echo count($categories); ?>

                <?php echo count($categories) === 1 ? 'Category' : 'Categories'; ?>

            </span>

        </div>


        <?php if (!empty($categories)): ?>

            <div class="category-list">

                <?php foreach ($categories as $category): ?>

                    <div class="category-row">

                        <div class="category-info">

                            <div class="category-number">
                                <?php echo htmlspecialchars(
                                    strtoupper(substr($category['name'] ?? 'C', 0, 1))
                                ); ?>
                            </div>

                            <div>

                                <strong>
                                    <?php echo htmlspecialchars($category['name'] ?? ''); ?>
                                </strong>

                                <span>
                                    Created
                                    <?php echo htmlspecialchars($category['created_at'] ?? ''); ?>
                                </span>

                            </div>

                        </div>


                        <a
                            href="exam_categories.php?delete_id=<?php echo urlencode($category['id'] ?? ''); ?>"
                            class="delete-category"
                            onclick="return confirm('Delete this category?');"
                        >
                            Delete
                        </a>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <h3>No categories yet</h3>

                <p>
                    Add your first category using the form above.
                </p>

            </div>

        <?php endif; ?>

    </div>


</div>

</body>

</html>