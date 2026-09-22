<?php

require_once __DIR__ . '/includes/auth_check.php';
requireContentAccess();

require_once __DIR__ . '/includes/supabase_api.php';


$success = '';
$error = '';

$editMode = false;
$editExam = null;


/*
|--------------------------------------------------------------------------
| CATEGORY FILE
|--------------------------------------------------------------------------
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


usort($categories, function ($a, $b) {

    return strcasecmp(
        $a['name'] ?? '',
        $b['name'] ?? ''
    );

});


/*
|--------------------------------------------------------------------------
| SLUG
|--------------------------------------------------------------------------
*/

function makeSlug(string $text): string
{
    $slug = strtolower(trim($text));

    $slug = preg_replace(
        '/[^a-z0-9]+/',
        '-',
        $slug
    );

    return trim($slug, '-');
}


/*
|--------------------------------------------------------------------------
| CREATE UPLOAD DIRECTORY
|--------------------------------------------------------------------------
*/

$uploadDir = __DIR__ . '/assets/uploads/exams/';

if (!is_dir($uploadDir)) {

    mkdir(
        $uploadDir,
        0777,
        true
    );

}


/*
|--------------------------------------------------------------------------
| DELETE EXAM
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['delete_id']) &&
    is_numeric($_GET['delete_id'])
) {

    $deleteId = (int) $_GET['delete_id'];

    $deleteResult = deleteExam($deleteId);

    if ($deleteResult['success']) {

        header(
            'Location: exams.php?msg=deleted'
        );

        exit;

    } else {

        $error = 'Failed to delete exam.';

    }

}


/*
|--------------------------------------------------------------------------
| EDIT MODE LOAD
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['edit_id']) &&
    is_numeric($_GET['edit_id'])
) {

    $editId = (int) $_GET['edit_id'];

    $examResult = getExamById($editId);

    if (
        ($examResult['success'] ?? false) &&
        !empty($examResult['data'])
    ) {

        $editMode = true;

        $editExam = $examResult['data'][0];

    } else {

        $error = 'Exam not found.';

    }

}


/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/

if (isset($_GET['msg'])) {

    if ($_GET['msg'] === 'added') {

        $success = 'Exam added successfully.';

    } elseif ($_GET['msg'] === 'updated') {

        $success = 'Exam updated successfully.';

    } elseif ($_GET['msg'] === 'deleted') {

        $success = 'Exam deleted successfully.';

    }

}


/*
|--------------------------------------------------------------------------
| EXISTING SELECTED CATEGORIES
|--------------------------------------------------------------------------
*/

$selectedCategories = [];

if ($editMode && !empty($editExam['choose_categories'])) {

    $selectedCategories = array_filter(
        array_map(
            'trim',
            explode(',', $editExam['choose_categories'])
        )
    );

}


/*
|--------------------------------------------------------------------------
| HANDLE FORM
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = trim(
        $_POST['action'] ?? 'add'
    );


    /*
    |--------------------------------------------------------------------------
    | MULTIPLE CATEGORIES
    |--------------------------------------------------------------------------
    */

    $postedCategories = $_POST['categories'] ?? [];

    if (!is_array($postedCategories)) {
        $postedCategories = [];
    }


    $postedCategories = array_map(
        'trim',
        $postedCategories
    );


    $postedCategories = array_filter(
        $postedCategories,
        function ($value) {
            return $value !== '';
        }
    );


    $postedCategories = array_values(
        array_unique(
            $postedCategories
        )
    );


    /*
    |--------------------------------------------------------------------------
    | SAVE INTO EXISTING Choose_categories COLUMN
    |--------------------------------------------------------------------------
    */

    $chooseCategories = implode(
        ', ',
        $postedCategories
    );


    $examName = trim(
        $_POST['exam_name'] ?? ''
    );


    $description = trim(
        $_POST['description'] ?? ''
    );


    $status = trim(
        $_POST['status'] ?? 'active'
    );


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (empty($postedCategories)) {

        $error = 'Please choose at least one category.';

    } elseif ($examName === '') {

        $error = 'Exam name is required.';

    } else {

        $slug = makeSlug($examName);

        $logoUrl = '';


        /*
        |--------------------------------------------------------------------------
        | KEEP OLD LOGO WHEN EDITING
        |--------------------------------------------------------------------------
        */

        if (
            $action === 'update' &&
            !empty($editExam['logo_url'])
        ) {

            $logoUrl = $editExam['logo_url'];

        }


        /*
        |--------------------------------------------------------------------------
        | LOGO UPLOAD
        |--------------------------------------------------------------------------
        */

        if (
            isset($_FILES['logo']) &&
            $_FILES['logo']['error'] === UPLOAD_ERR_OK
        ) {

            $tmpName = $_FILES['logo']['tmp_name'];

            $originalName = $_FILES['logo']['name'];

            $extension = strtolower(
                pathinfo(
                    $originalName,
                    PATHINFO_EXTENSION
                )
            );


            if (
                !in_array(
                    $extension,
                    [
                        'jpg',
                        'jpeg',
                        'png',
                        'webp',
                        'gif'
                    ],
                    true
                )
            ) {

                $error =
                    'Only jpg, jpeg, png, webp, gif files are allowed for logo.';

            } else {

                $newFileName =
                    'exam_' .
                    time() .
                    '_' .
                    rand(1000, 9999) .
                    '.' .
                    $extension;


                $targetPath =
                    $uploadDir .
                    $newFileName;


                if (
                    move_uploaded_file(
                        $tmpName,
                        $targetPath
                    )
                ) {

                    $logoUrl =
                        'assets/uploads/exams/' .
                        $newFileName;

                } else {

                    $error =
                        'Failed to upload logo.';

                }

            }

        }


        /*
        |--------------------------------------------------------------------------
        | ADD EXAM
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            if ($action === 'add') {

                $result = addExam(
                    $chooseCategories,
                    $examName,
                    $slug,
                    $description,
                    $logoUrl,
                    $status
                );


                if ($result['success']) {

                    header(
                        'Location: exams.php?msg=added'
                    );

                    exit;

                } else {

                    $error =
                        'Failed to add exam.';

                }

            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE EXAM
            |--------------------------------------------------------------------------
            */

            if ($action === 'update') {

                $examId = (int) (
                    $_POST['exam_id'] ?? 0
                );


                if ($examId <= 0) {

                    $error =
                        'Invalid exam ID.';

                } else {

                    $result = updateExam(
                        $examId,
                        $chooseCategories,
                        $examName,
                        $slug,
                        $description,
                        $logoUrl,
                        $status
                    );


                    if ($result['success']) {

                        header(
                            'Location: exams.php?msg=updated'
                        );

                        exit;

                    } else {

                        $error =
                            'Failed to update exam.';

                    }

                }

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| FETCH ALL EXAMS
|--------------------------------------------------------------------------
*/

$examResponse = getAllExams();

$exams =
    ($examResponse['success'] ?? false)
        ? ($examResponse['data'] ?? [])
        : [];

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Manage Exams</title>

    <link
        rel="stylesheet"
        href="assets/css/exams.css"
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

            <h1>Manage Exams</h1>

            <p>
                Add, edit, and manage exams with categories,
                description, logo, and status.
            </p>

        </div>


        <a
            href="exam_categories.php"
            class="back-link"
        >
            ← Back to Categories
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


    <div class="exam-grid">


        <!-- ADD / EDIT EXAM -->

        <div class="card">

            <h3>
                <?php echo $editMode ? 'Edit Exam' : 'Add New Exam'; ?>
            </h3>


            <form
                method="POST"
                enctype="multipart/form-data"
                class="exam-form"
            >

                <input
                    type="hidden"
                    name="action"
                    value="<?php echo $editMode ? 'update' : 'add'; ?>"
                >


                <input
                    type="hidden"
                    name="exam_id"
                    value="<?php echo $editMode ? (int)$editExam['id'] : ''; ?>"
                >


                <!-- CATEGORY -->

                <div class="group">

                    <label for="categories">
                        Choose Category
                    </label>


                    <select
                        id="categories"
                        name="categories[]"
                        multiple
                        required
                    >

                        <?php if (!empty($categories)): ?>

                            <?php foreach ($categories as $category): ?>

                                <?php
                                $categoryName =
                                    trim(
                                        $category['name'] ?? ''
                                    );

                                $isSelected =
                                    in_array(
                                        $categoryName,
                                        $selectedCategories,
                                        true
                                    );
                                ?>

                                <option
                                    value="<?php echo htmlspecialchars($categoryName); ?>"
                                    <?php echo $isSelected ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($categoryName); ?>
                                </option>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </select>


                    <small class="field-help">
                        Hold Ctrl on Windows or Command on Mac to choose multiple categories.
                    </small>


                    <a
                        href="exam_categories.php"
                        class="category-link"
                    >
                        + Add New Category
                    </a>

                </div>


                <!-- EXAM NAME -->

                <div class="group">

                    <label for="exam_name">
                        Exam Name
                    </label>


                    <input
                        type="text"
                        id="exam_name"
                        name="exam_name"
                        value="<?php
                            echo $editMode
                                ? htmlspecialchars(
                                    $editExam['exam_name'] ?? ''
                                )
                                : '';
                        ?>"
                        placeholder="e.g. ADRE Grade 3"
                        required
                    >

                </div>


                <!-- DESCRIPTION -->

                <div class="group">

                    <label for="description">
                        Description
                    </label>


                    <textarea
                        id="description"
                        name="description"
                        placeholder="Write exam description here..."
                    ><?php
                        echo $editMode
                            ? htmlspecialchars(
                                $editExam['description'] ?? ''
                            )
                            : '';
                    ?></textarea>

                </div>


                <!-- LOGO -->

                <div class="group">

                    <label for="logo">
                        Logo Upload
                    </label>


                    <input
                        type="file"
                        id="logo"
                        name="logo"
                        accept=".jpg,.jpeg,.png,.webp,.gif"
                    >


                    <?php if (
                        $editMode &&
                        !empty($editExam['logo_url'])
                    ): ?>

                        <div class="logo-preview-wrap">

                            <img
                                src="<?php echo htmlspecialchars($editExam['logo_url']); ?>"
                                alt="Exam Logo"
                                class="logo-preview"
                            >

                        </div>

                    <?php endif; ?>

                </div>


                <!-- STATUS -->

                <div class="group">

                    <label for="status">
                        Status
                    </label>


                    <?php

                    $currentStatus =
                        $editMode
                            ? ($editExam['status'] ?? 'active')
                            : 'active';

                    ?>


                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value="active"
                            <?php
                            echo $currentStatus === 'active'
                                ? 'selected'
                                : '';
                            ?>
                        >
                            Active
                        </option>


                        <option
                            value="inactive"
                            <?php
                            echo $currentStatus === 'inactive'
                                ? 'selected'
                                : '';
                            ?>
                        >
                            Inactive
                        </option>


                        <option
                            value="draft"
                            <?php
                            echo $currentStatus === 'draft'
                                ? 'selected'
                                : '';
                            ?>
                        >
                            Draft
                        </option>


                        <option
                            value="coming soon"
                            <?php
                            echo $currentStatus === 'coming soon'
                                ? 'selected'
                                : '';
                            ?>
                        >
                            Coming Soon
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    class="btn primary-btn"
                >
                    <?php echo $editMode ? 'Update Exam' : 'Add Exam'; ?>
                </button>


                <?php if ($editMode): ?>

                    <a
                        href="exams.php"
                        class="btn secondary-btn"
                    >
                        Cancel Edit
                    </a>

                <?php endif; ?>


            </form>

        </div>


        <!-- ALL EXAMS -->

        <div class="card">

            <h3>All Exams</h3>


            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Logo</th>

                            <th>Category</th>

                            <th>Exam Name</th>

                            <th>Description</th>

                            <th>Status</th>

                            <th>Actions</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (!empty($exams)): ?>


                        <?php foreach ($exams as $index => $exam): ?>


                            <?php

                            $statusValue =
                                $exam['status'] ?? 'draft';

                            ?>


                            <tr>

                                <td>
                                    <?php echo $index + 1; ?>
                                </td>


                                <td>

                                    <?php if (!empty($exam['logo_url'])): ?>

                                        <img
                                            src="<?php echo htmlspecialchars($exam['logo_url']); ?>"
                                            alt="Logo"
                                            class="table-logo"
                                        >

                                    <?php else: ?>

                                        <span class="no-logo">
                                            No Logo
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <div class="category-tags">

                                        <?php

                                        $examCategories =
                                            array_filter(
                                                array_map(
                                                    'trim',
                                                    explode(
                                                        ',',
                                                        $exam['choose_categories'] ?? ''
                                                    )
                                                )
                                            );

                                        ?>


                                        <?php if (!empty($examCategories)): ?>

                                            <?php foreach ($examCategories as $examCategory): ?>

                                                <span class="category-tag">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $examCategory
                                                    );
                                                    ?>

                                                </span>

                                            <?php endforeach; ?>

                                        <?php else: ?>

                                            <span class="no-logo">
                                                No Category
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $exam['exam_name'] ?? '-'
                                    );
                                    ?>

                                </td>


                                <td class="desc-cell">

                                    <?php
                                    echo htmlspecialchars(
                                        $exam['description'] ?? '-'
                                    );
                                    ?>

                                </td>


                                <td>

                                    <span
                                        class="tag <?php
                                            echo htmlspecialchars(
                                                str_replace(
                                                    ' ',
                                                    '-',
                                                    strtolower(
                                                        $statusValue
                                                    )
                                                )
                                            );
                                        ?>"
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            ucwords($statusValue)
                                        );
                                        ?>

                                    </span>

                                </td>


                                <td>

                                    <div class="row-actions">

                                        <a
                                            class="mini-btn edit-btn"
                                            href="exams.php?edit_id=<?php echo (int)$exam['id']; ?>"
                                        >
                                            Edit
                                        </a>


                                        <a
                                            class="mini-btn delete-btn"
                                            href="exams.php?delete_id=<?php echo (int)$exam['id']; ?>"
                                            onclick="return confirm('Delete this exam?');"
                                        >
                                            Delete
                                        </a>

                                    </div>

                                </td>

                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="7"
                                class="empty-state"
                            >
                                No exams found.
                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>


    </div>

</div>

</body>

</html>