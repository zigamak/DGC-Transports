<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin', '/login.php');

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';

// Handle Add (AJAX)
if ($_POST['action'] ?? '' === 'add') {
    header('Content-Type: application/json');

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'City name is required.']);
        exit;
    }

    // Check for duplicate (case-insensitive)
    $check = $conn->prepare("SELECT id FROM cities WHERE LOWER(name) = LOWER(?)");
    $check->bind_param("s", $name);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'City already exists.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO cities (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $success = $stmt->execute();

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'City added successfully!' : 'Failed to add city.',
        'city' => $success ? ['id' => $conn->insert_id, 'name' => htmlspecialchars($name)] : null
    ]);
    exit;
}

// Handle Edit (AJAX)
if ($_POST['action'] ?? '' === 'edit') {
    header('Content-Type: application/json');

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    if ($id <= 0 || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        exit;
    }

    // Check duplicate excluding current ID
    $check = $conn->prepare("SELECT id FROM cities WHERE LOWER(name) = LOWER(?) AND id != ?");
    $check->bind_param("si", $name, $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Another city with this name already exists.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE cities SET name = ? WHERE id = ?");
    $stmt->bind_param("si", $name, $id);
    $success = $stmt->execute();

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'City updated successfully!' : 'Update failed.',
        'city' => $success ? ['id' => $id, 'name' => htmlspecialchars($name)] : null
    ]);
    exit;
}

// Handle Delete (AJAX)
if ($_POST['action'] ?? '' === 'delete') {
    header('Content-Type: application/json');

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid city ID.']);
        exit;
    }

    // Optional: Check if city is used in routes
    $check = $conn->prepare("SELECT COUNT(*) FROM trip_templates WHERE pickup_city_id = ? OR dropoff_city_id = ?");
    $check->bind_param("ii", $id, $id);
    $check->execute();
    $check->bind_result($used);
    $check->fetch();
    $check->close();

    if ($used > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete: This city is used in existing routes.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM cities WHERE id = ?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'City deleted successfully!' : 'Failed to delete city.',
        'id' => $success ? $id : null
    ]);
    exit;
}

// Fetch all cities
$cities = $conn->query("SELECT * FROM cities ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cities Management - DGC Transports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
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
    </style>
</head>
<body class="min-h-screen">
    <?php include '../templates/sidebar.php'; ?>

    <div class="md:ml-64 min-h-screen p-4 md:p-6 lg:p-8">
        <div class="max-w-w-5xl mx-auto">
            <div class="mb-8">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                    Cities Management
                </h1>
                <p class="text-gray-600 mt-2">Manage all cities in the transport network</p>
            </div>

            <div id="status-message" class="mb-6 hidden px-4 py-3 rounded-lg flex items-center transition-all duration-300"></div>

            <div class="bg-white rounded-xl card-shadow">
                <div class="p-4 md:p-6 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg md:text-xl font-bold text-gray-900">All Cities</h2>
                            <p class="text-sm text-gray-600 mt-1"><span id="city-count"><?= count($cities) ?></span> cities registered</p>
                        </div>
                        <button onclick="openModal('addModal')" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition shadow-md">
                            Add New City
                        </button>
                    </div>
                </div>

                <div class="p-4 md:p-6">
                    <?php if (empty($cities)): ?>
                        <div class="text-center py-12">
                            <div class="w-24 h-24 bg-gray-100 rounded-ful mx-auto flex items-center justify-center mb-4">
                                <i class="fas fa-city text-4xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">No cities found</h3>
                            <p class="text-gray-600 mt-2">Start by adding your first city.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">#</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">City Name</th>
                                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="cities-table-body" class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($cities as $index => $city): ?>
                                    <tr data-city-id="<?= $city['id'] ?>" class="table-hover">
                                        <td class="px-6 py-4 text-sm text-gray-600"><?= $index + 1 ?></td>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 city-name">
                                            <?= htmlspecialchars($city['name']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-right text-sm">
                                            <button onclick="openEditModal(<?= $city['id'] ?>, '<?= htmlspecialchars(addslashes($city['name']), ENT_QUOTES) ?>')"
                                                class="text-primary-red hover:text-dark-red mr-4" title="Edit">
                                                Edit
                                            </button>
                                            <button onclick="deleteCity(<?= $city['id'] ?>, '<?= htmlspecialchars(addslashes($city['name']), ENT_QUOTES) ?>')"
                                                class="text-red-600 hover:text-red-800" title="Delete">
                                                Delete
                                            </button>
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

    <!-- Add Modal -->
    <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl card-shadow p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4 border-b pb-3">
                <h3 class="text-xl font-bold">Add New City</h3>
                <button onclick="closeModal('addModal')" class="text-gray-500 hover:text-gray-700">×</button>
            </div>
            <form id="addForm">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">City Name</label>
                    <input type="text" name="name" required class="form-input w-full" placeholder="e.g. Lagos">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal('addModal')" class="px-5 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-primary-red text-white rounded-lg hover:bg-dark-red">Add City</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl card-shadow p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4 border-b pb-3">
                <h3 class="text-xl font-bold">Edit City</h3>
                <button onclick="closeModal('editModal')" class="text-gray-500 hover:text-gray-700">×</button>
            </div>
            <form id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="id" id="editId">
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">City Name</label>
                    <input type="text" name="name" id="editName" required class="form-input w-full">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal('editModal')" class="px-5 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-primary-red text-white rounded-lg hover:bg-dark-red">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const csrfToken = "<?= $_SESSION['csrf_token'] ?>";

        function showMessage(success, message) {
            const el = document.getElementById('status-message');
            el.className = `mb-6 px-4 py-3 rounded-lg flex items-center ${success ? 'bg-green-100 text-green-700 border border-green-400' : 'bg-red-100 text-red-700 border border-red-400'}`;
            el.innerHTML = `<i class="fas ${success ? 'fa-check-circle' : 'fa-times-circle'} mr-2"></i> ${message}`;
            el.classList.remove('hidden');
            setTimeout(() => el.classList.add('hidden'), 5000);
        }

        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function openEditModal(id, name) {
            document.getElementById('editId').value = id;
            document.getElementById('editName').value = name;
            openModal('editModal');
        }

        function deleteCity(id, name) {
            if (!confirm(`Delete city "${name}" permanently? This cannot be undone.`)) return;

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}&csrf_token=${csrfToken}`
            })
            .then(r => r.json())
            .then(data => {
                showMessage(data.success, data.message);
                if (data.success) {
                    document.querySelector(`tr[data-city-id="${id}"]`).remove();
                    document.getElementById('city-count').textContent = parseInt(document.getElementById('city-count').textContent) - 1;
                }
            });
        }

        // Add Form
        document.getElementById('addForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    showMessage(data.success, data.message);
                    if (data.success) {
                        closeModal('addModal');
                        this.reset();

                        // Add to table
                        const tbody = document.getElementById('cities-table-body');
                        const count = tbody.children.length + 1;
                        const row = document.createElement('tr');
                        row.setAttribute('data-city-id', data.city.id);
                        row.className = 'table-hover';
                        row.innerHTML = `
                            <td class="px-6 py-4 text-sm text-gray-600">${count}</td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 city-name">${data.city.name}</td>
                            <td class="px-6 py-4 text-right text-sm">
                                <button onclick="openEditModal(${data.city.id}, '${data.city.name.replace(/'/g, "\\'")}')" class="text-primary-red hover:text-dark-red mr-4">Edit</button>
                                <button onclick="deleteCity(${data.city.id}, '${data.city.name.replace(/'/g, "\\'")}')" class="text-red-600 hover:text-red-800">Delete</button>
                            </td>
                        `;
                        tbody.insertBefore(row, tbody.firstChild);
                        document.getElementById('city-count').textContent = count;
                    }
                });
        });

        // Edit Form
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    showMessage(data.success, data.message);
                    if (data.success) {
                        closeModal('editModal');
                        const row = document.querySelector(`tr[data-city-id="${data.city.id}"]`);
                        row.querySelector('.city-name').textContent = data.city.name;
                    }
                });
        });
    </script>
</body>
</html>