<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin', '/login.php');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$response = ['success' => false, 'message' => ''];

// Handle AJAX Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');

    // CSRF Check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please try again.']);
        exit;
    }

    $action = $_POST['action'];

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'City name is required.']);
            exit;
        }

        $check = $conn->prepare("SELECT id FROM cities WHERE LOWER(name) = LOWER(?)");
        $check->bind_param("s", $name);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'This city already exists.']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO cities (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $success = $stmt->execute();

        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'City added successfully!',
                'city' => ['id' => $conn->insert_id, 'name' => htmlspecialchars($name)]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add city.']);
        }
        exit;
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($id <= 0 || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'City name is required.']);
            exit;
        }

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

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid city.']);
            exit;
        }

        // Prevent deleting cities used in routes
        $check = $conn->prepare("SELECT COUNT(*) FROM trip_templates WHERE pickup_city_id = ? OR dropoff_city_id = ?");
        $check->bind_param("ii", $id, $id);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();

        if ($count > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete: This city is used in routes.']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM cities WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();

        echo json_encode([
            'success' => $success,
            'message' => $success ? 'City deleted!' : 'Failed to delete.',
            'id' => $success ? $id : null
        ]);
        exit;
    }
}

// Fetch cities
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
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        .card-shadow { box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        .form-input { @apply w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent; }
        .btn-primary { @apply bg-primary-red hover:bg-red-700 text-white font-medium py-3 px-6 rounded-lg transition; }
        .modal-error { @apply hidden mt-3 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm; }
    </style>
</head>
<body class="min-h-screen">
    <?php include '../templates/sidebar.php'; ?>

    <div class="md:ml-64 p-6 lg:p-10">
        <div class="max-w-5xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Cities Management</h1>
            <p class="text-gray-600 mb-8">Add, edit, or remove cities from your transport network</p>

            <div id="status-message" class="hidden mb-6 p-4 rounded-lg border"></div>

            <div class="bg-white rounded-2xl card-shadow overflow-hidden">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold">All Cities</h2>
                        <p class="text-sm text-gray-500"><span id="city-count"><?= count($cities) ?></span> cities</p>
                    </div>
                    <button onclick="openAddModal()" class="btn-primary flex items-center gap-2">
                        Add New City
                    </button>
                </div>

                <div class="p-6">
                    <?php if (empty($cities)): ?>
                        <div class="text-center py-16">
                            <i class="fas fa-city text-6xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No cities added yet. Click "Add New City" to get started.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b">
                                    <tr>
                                        <th class="text-left p-4 font-medium text-gray-700">#</th>
                                        <th class="text-left p-4 font-medium text-gray-700">City Name</th>
                                        <th class="text-right p-4 font-medium text-gray-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="cities-table" class="divide-y divide-gray-200">
                                    <?php foreach ($cities as $i => $city): ?>
                                    <tr data-id="<?= $city['id'] ?>">
                                        <td class="p-4 text-gray-600"><?= $i + 1 ?></td>
                                        <td class="p-4 font-medium"><?= htmlspecialchars($city['name']) ?></td>
                                        <td class="p-4 text-right space-x-4">
                                            <button onclick="openEditModal(<?= $city['id'] ?>, '<?= htmlspecialchars(addslashes($city['name'])) ?>')" class="text-primary-red hover:underline">Edit</button>
                                            <button onclick="deleteCity(<?= $city['id'] ?>, '<?= htmlspecialchars(addslashes($city['name'])) ?>')" class="text-red-600 hover:underline">Delete</button>
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
        <div class="bg-white rounded-2xl card-shadow p-8 w-full max-w-md">
            <h3 class="text-2xl font-bold mb-6">Add New City</h3>
            <form id="addForm">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">City Name</label>
                    <input type="text" name="name" required class="form-input" placeholder="Enter city name">
                    <div id="add-error" class="modal-error"></div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal('addModal')" class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="btn-primary">Add City</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl card-shadow p-8 w-full max-w-md">
            <h3 class="text-2xl font-bold mb-6">Edit City</h3>
            <form id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="id" id="edit-id">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">City Name</label>
                    <input type="text" name="name" id="edit-name" required class="form-input">
                    <div id="edit-error" class="modal-error"></div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal('editModal')" class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const csrf = "<?= $_SESSION['csrf_token'] ?>";

        function showMessage(success, msg) {
            const el = document.getElementById('status-message');
            el.className = `mb-6 p-4 rounded-lg border ${success ? 'bg-green-50 border-green-300 text-green-800' : 'bg-red-50 border-red-300 text-red-800'}`;
            el.innerHTML = msg;
            el.classList.remove('hidden');
            setTimeout(() => el.classList.add('hidden'), 5000);
        }

        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
            document.getElementById('addForm').reset();
            document.getElementById('add-error').classList.add('hidden');
        }

        function openEditModal(id, name) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-error').classList.add('hidden');
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        function deleteCity(id, name) {
            if (!confirm(`Delete "${name}" permanently?`)) return;

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}&csrf_token=${csrf}`
            })
            .then(r => r.json())
            .then(d => {
                showMessage(d.success, d.message);
                if (d.success) {
                    document.querySelector(`tr[data-id="${id}"]`).remove();
                    document.getElementById('city-count').textContent = parseInt(document.getElementById('city-count').textContent) - 1;
                }
            });
        }

        // Add Form
        document.getElementById('addForm').onsubmit = function(e) {
            e.preventDefault();
            const errorEl = document.getElementById('add-error');
            errorEl.classList.add('hidden');

            fetch('', { method: 'POST', body: new FormData(this) })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        showMessage(true, d.message);
                        closeModal('addModal');
                        const tbody = document.getElementById('cities-table');
                        const row = document.createElement('tr');
                        row.dataset.id = d.city.id;
                        row.innerHTML = `<td class="p-4">${tbody.children.length + 1}</td>
                                         <td class="p-4 font-medium">${d.city.name}</td>
                                         <td class="p-4 text-right space-x-4">
                                            <button onclick="openEditModal(${d.city.id}, '${d.city.name.replace(/'/g, "\\'")}')" class="text-primary-red hover:underline">Edit</button>
                                            <button onclick="deleteCity(${d.city.id}, '${d.city.name.replace(/'/g, "\\'")}')" class="text-red-600 hover:underline">Delete</button>
                                         </td>`;
                        tbody.insertBefore(row, tbody.firstChild);
                        document.getElementById('city-count').textContent = parseInt(document.getElementById('city-count').textContent) + 1;
                    } else {
                        errorEl.textContent = d.message;
                        errorEl.classList.remove('hidden');
                    }
                });
        };

        // Edit Form
        document.getElementById('editForm').onsubmit = function(e) {
            e.preventDefault();
            const errorEl = document.getElementById('edit-error');
            errorEl.classList.add('hidden');

            fetch('', { method: 'POST', body: new FormData(this) })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        showMessage(true, d.message);
                        closeModal('editModal');
                        document.querySelector(`tr[data-id="${d.city.id}"] td:nth-child(2)`).textContent = d.city.name;
                    } else {
                        errorEl.textContent = d.message;
                        errorEl.classList.remove('hidden');
                    }
                });
        };
    </script>
</body>
</html>