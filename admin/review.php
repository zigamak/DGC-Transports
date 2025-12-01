<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin', '/login.php');

$message = '';

// Handle AJAX Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    // ADD REVIEW
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rating = (int)($_POST['rating'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $status = $_POST['status'] ?? 'approved';

        if (empty($name) || $rating < 1 || $rating > 5 || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required and rating must be 1–5.']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO reviews (name, email, rating, message, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiss", $name, $email, $rating, $message, $status);
        $success = $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Review added successfully!' : 'Failed to add review.'
        ]);
        exit;
    }

    // EDIT REVIEW
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rating = (int)($_POST['rating'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $status = $_POST['status'] ?? 'pending';

        if (empty($name) || $rating < 1 || $rating > 5 || empty($message) || $id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE reviews SET name = ?, email = ?, rating = ?, message = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssissi", $name, $email, $rating, $message, $status, $id);
        $success = $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Review updated successfully!' : 'Failed to update review.'
        ]);
        exit;
    }

    // APPROVE REVIEW
    if ($action === 'approve') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success, 'message' => $success ? 'Review approved!' : 'Failed to approve.']);
        exit;
    }
}

// HANDLE DELETE
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();
    $stmt->close();
    header("Location: reviews.php?" . ($success ? "deleted=1" : "error=1"));
    exit;
}

// Fetch all reviews
$reviews = $conn->query("
    SELECT id, name, email, rating, message, status, created_at 
    FROM reviews 
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews Management - DGC Transports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-red': '#e30613',
                        'dark-red': '#c70410',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); }
        .card-shadow { box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); }
        .table-hover:hover { background-color: #f8fafc; }
        .modal { transition: all 0.3s ease; }
        .form-input {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        .form-input:focus {
            border-color: #e30613;
            box-shadow: 0 0 0 3px rgba(227, 6, 19, 0.1);
            outline: none;
        }
        .form-label {
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        .status-pending { @apply bg-yellow-100 text-yellow-800; }
        .status-approved { @apply bg-green-100 text-green-800; }
    </style>
</head>
<body class="min-h-screen">
    <?php include '../templates/sidebar.php'; ?>

    <div class="md:ml-64 min-h-screen p-4 md:p-6 lg:p-8">
        <div class="max-w-6xl mx-auto">
            <div class="mb-8">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                    Reviews Management
                </h1>
                <p class="text-gray-600 mt-2">Approve, edit, or delete customer reviews</p>
            </div>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center">
                    Review deleted successfully.
                </div>
            <?php endif; ?>

            <div id="form-status-message" class="mb-6 hidden px-4 py-3 rounded-lg flex items-center transition-all duration-300"></div>

            <div class="bg-white rounded-xl card-shadow">
                <div class="p-4 md:p-6 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg md:text-xl font-bold text-gray-900">
                                All Reviews
                            </h2>
                            <p class="text-sm text-gray-600 mt-1"><?= count($reviews) ?> reviews total</p>
                        </div>
                        <button onclick="openModal('addModal')" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition shadow-md hover:shadow-lg">
                            Add Testimonial
                        </button>
                    </div>
                </div>

                <div class="p-4 md:p-6">
                    <?php if (empty($reviews)): ?>
                        <div class="text-center py-12">
                            <div class="w-24 h-24 bg-gray-100 rounded-full mx-auto flex items-center justify-center mb-4">
                                <i class="fas fa-comment-dots text-4xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">No reviews yet</h3>
                            <p class="text-gray-600 mt-2">Customer reviews will appear here once submitted.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-12">#</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Customer</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Review</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Rating</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider w-40">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($reviews as $index => $rev): ?>
                                    <tr class="table-hover transition-colors duration-150">
                                        <td class="px-6 py-4 text-sm text-gray-600"><?= $index + 1 ?></td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($rev['name']) ?></div>
                                            <?php if ($rev['email']): ?>
                                                <div class="text-xs text-gray-500"><?= htmlspecialchars($rev['email']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700 max-w-xs truncate">
                                            "<?= htmlspecialchars(substr($rev['message'], 0, 80)) ?><?= strlen($rev['message']) > 80 ? '...' : '' ?>"
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-yellow-500 text-lg">
                                                <?= str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium status-<?= $rev['status'] ?>">
                                                <?= ucfirst($rev['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <?= date('M j, Y', strtotime($rev['created_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 text-right text-sm space-x-2">
                                            <?php if ($rev['status'] === 'pending'): ?>
                                                <button onclick="approveReview(<?= $rev['id'] ?>)" class="text-green-600 hover:text-green-800 p-1 rounded hover:bg-green-50" title="Approve">
                                                    Approve
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="openEditModal(<?= $rev['id'] ?>, <?= htmlspecialchars(json_encode($rev), ENT_QUOTES) ?>)"
                                                    class="text-primary-red hover:text-dark-red p-1 rounded-full hover:bg-red-50" title="Edit">
                                                Edit
                                            </button>
                                            <a href="reviews.php?delete=<?= $rev['id'] ?>"
                                               onclick="return confirm('Delete this review permanently?')"
                                               class="text-red-600 hover:text-red-800 p-1 rounded-full hover:bg-red-50" title="Delete">
                                                Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Review Modal -->
    <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl card-shadow p-6 w-full max-w-lg">
            <div class="flex justify-between items-center mb-4 border-b pb-2">
                <h3 class="text-xl font-bold text-gray-800">Add New Testimonial</h3>
                <button onclick="closeModal('addModal')" class="text-gray-400 hover:text-gray-600">Close</button>
            </div>
            <form id="addForm">
                <input type="hidden" name="action" value="add">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="form-label block">Name *</label>
                        <input type="text" name="name" class="form-input w-full" required>
                    </div>
                    <div>
                        <label class="form-label block">Email (optional)</label>
                        <input type="email" name="email" class="form-input w-full">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label block">Rating *</label>
                    <div class="flex space-x-2">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <label class="cursor-pointer">
                                <input type="radio" name="rating" value="<?= $i ?>" class="hidden" required>
                                <i class="fas fa-star text-3xl text-gray-300 hover:text-yellow-500 transition"></i>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label block">Message *</label>
                    <textarea name="message" rows="4" class="form-input w-full resize-none" required></textarea>
                </div>
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="status" value="approved" class="mr-2">
                        <span class="text-sm">Publish immediately (Approved)</span>
                    </label>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary-red text-white rounded-lg hover:bg-dark-red">
                        Add Review
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl card-shadow p-6 w-full max-w-lg">
            <div class="flex justify-between items-center mb-4 border-b pb-2">
                <h3 class="text-xl font-bold text-gray-800">Edit Review</h3>
                <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-600">Close</button>
            </div>
            <form id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <!-- Same fields as add modal -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="form-label block">Name *</label>
                        <input type="text" name="name" id="editName" class="form-input w-full" required>
                    </div>
                    <div>
                        <label class="form-label block">Email</label>
                        <input type="email" name="email" id="editEmail" class="form-input w-full">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label block">Rating *</label>
                    <div class="flex space-x-2" id="editRatingStars">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <label class="cursor-pointer">
                                <input type="radio" name="rating" value="<?= $i ?>" class="hidden">
                                <i class="fas fa-star text-3xl text-gray-300 hover:text-yellow-500 transition"></i>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label block">Message *</label>
                    <textarea name="message" id="editMessage" rows="4" class="form-input w-full resize-none" required></textarea>
                </div>
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="status" value="approved" id="editStatus">
                        <span class="text-sm ml-2">Approved (visible on homepage)</span>
                    </label>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary-red text-white rounded-lg hover:bg-dark-red">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function displayStatusMessage(success, message) {
            const msgArea = document.getElementById('form-status-message');
            const icon = success ? 'fa-check-circle' : 'fa-times-circle';
            const bg = success ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';

            msgArea.className = `mb-6 px-4 py-3 rounded-lg flex items-center transition-all duration-300 ${bg}`;
            msgArea.innerHTML = `<i class="fas ${icon} mr-2"></i> ${message}`;
            msgArea.classList.remove('hidden');

            setTimeout(() => {
                msgArea.classList.add('hidden');
                msgArea.innerHTML = '';
            }, 5000);
        }

        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function approveReview(id) {
            if (!confirm('Approve this review? It will appear on the homepage.')) return;
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=approve&id=' + id
            })
            .then(r => r.json())
            .then(data => {
                displayStatusMessage(data.success, data.message);
                if (data.success) setTimeout(() => location.reload(), 1500);
            });
        }

        function openEditModal(id, review) {
            document.getElementById('editId').value = id;
            document.getElementById('editName').value = review.name;
            document.getElementById('editEmail').value = review.email || '';
            document.getElementById('editMessage').value = review.message;
            document.getElementById('editStatus').checked = review.status === 'approved';

            // Set rating stars
            document.querySelectorAll('#editRatingStars input[name="rating"]').forEach((input, i) => {
                input.checked = (5 - i) === review.rating;
                input.nextElementSibling.classList.toggle('text-yellow-500', input.checked);
            });

            openModal('editModal');
        }

        // Add & Edit Forms
        document.getElementById('addForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    closeModal('addModal');
                    displayStatusMessage(data.success, data.message);
                    if (data.success) setTimeout(() => location.reload(), 1500);
                });
        });

        document.getElementById('editForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    closeModal('editModal');
                    displayStatusMessage(data.success, data.message);
                    if (data.success) setTimeout(() => location.reload(), 1500);
                });
        });

        // Star hover effect
        document.querySelectorAll('input[name="rating"]').forEach(input => {
            input.addEventListener('change', function() {
                this.parentElement.parentElement.querySelectorAll('i').forEach((star, i) => {
                    star.classList.toggle('text-yellow-500', i >= (5 - this.value));
                });
            });
        });
    </script>
</body>
</html>