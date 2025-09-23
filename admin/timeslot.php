<?php
//admin/timeslot.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Handle form submission for adding time slot
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_time_slot'])) {
    $departure_time = trim($_POST['departure_time'] ?? '');
    $arrival_time = trim($_POST['arrival_time'] ?? '');

    // Validate inputs
    if (empty($departure_time)) {
        $errors[] = 'Departure time is required.';
    }
    if (empty($arrival_time)) {
        $errors[] = 'Arrival time is required.';
    }
    if (!empty($departure_time) && !empty($arrival_time)) {
        try {
            $dep_time = DateTime::createFromFormat('H:i', $departure_time);
            $arr_time = DateTime::createFromFormat('H:i', $arrival_time);
            if (!$dep_time || !$arr_time) {
                $errors[] = 'Invalid time format. Use HH:MM (24-hour).';
            } elseif ($arr_time <= $dep_time) {
                $errors[] = 'Arrival time must be after departure time.';
            }
        } catch (Exception $e) {
            $errors[] = 'Invalid time format. Use HH:MM (24-hour).';
        }
    }

    // Insert time slot if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO time_slots (departure_time, arrival_time) VALUES (?, ?)");
        $stmt->bind_param("ss", $departure_time, $arrival_time);
        if ($stmt->execute()) {
            $success = 'Time slot added successfully.';
        } else {
            $errors[] = 'Failed to add time slot. Please try again.';
        }
        $stmt->close();
    }
}

// Handle deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM time_slots WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $success = 'Time slot deleted successfully.';
    } else {
        $errors[] = 'Failed to delete time slot.';
    }
    $stmt->close();
}

// Fetch time slots
$time_slots_query = "SELECT id, departure_time, arrival_time FROM time_slots ORDER BY departure_time";
$time_slots_result = $conn->query($time_slots_query);
$time_slots = [];
while ($row = $time_slots_result->fetch_assoc()) {
    $time_slots[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Time Slots - DGC Transports</title>
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
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        
        .card-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .form-input {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 0.875rem;
            width: 100%;
            transition: all 0.2s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #e30613;
            box-shadow: 0 0 0 2px rgba(227, 6, 19, 0.2);
        }
        
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .btn-primary {
            background: #e30613;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        
        .btn-primary:hover {
            background: #c70410;
            transform: translateY(-1px);
        }
        
        .error-message {
            color: #dc2626;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
        }
        
        .success-message {
            color: #059669;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
        }
        
        .table-hover:hover {
            background-color: #f8fafc;
        }
        
        .content-container {
            overflow-y: auto;
            max-height: calc(100vh - 4rem);
        }
        
        @media (min-width: 768px) {
            .content-container {
                max-height: 100vh;
            }
        }
        
        @media (max-width: 640px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    <?php include '../templates/sidebar.php'; ?>

    <div class="md:ml-64 min-h-screen">
        <div class="content-container p-4 md:p-6 lg:p-8">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                            <i class="fas fa-clock text-primary-red mr-3"></i>
                            Manage Time Slots
                        </h1>
                        <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </div>

                <!-- Add Time Slot Form -->
                <div class="bg-white rounded-xl card-shadow mb-8">
                    <div class="p-4 md:p-6 border-b border-gray-200">
                        <h2 class="text-lg md:text-xl font-bold text-gray-900">
                            <i class="fas fa-plus text-primary-red mr-2"></i>
                            Add New Time Slot
                        </h2>
                    </div>
                    <div class="p-4 md:p-6">
                        <?php if (!empty($errors)): ?>
                            <?php foreach ($errors as $error): ?>
                                <div class="mb-4 bg-red-50 border border-red-200 rounded-lg p-3">
                                    <div class="flex items-center">
                                        <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                                        <span class="text-red-800 text-sm"><?= htmlspecialchars($error) ?></span>
                                    </div>
                        </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="mb-4 bg-green-50 border border-green-200 rounded-lg p-3">
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                    <span class="text-green-800 text-sm"><?= htmlspecialchars($success) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <form method="POST" class="form-grid grid gap-6">
                            <div>
                                <label for="departure_time" class="form-label block">Departure Time</label>
                                <input type="time" id="departure_time" name="departure_time" class="form-input" required>
                            </div>
                            <div>
                                <label for="arrival_time" class="form-label block">Arrival Time</label>
                                <input type="time" id="arrival_time" name="arrival_time" class="form-input" required>
                            </div>
                            <div class="col-span-1 md:col-span-2">
                                <button type="submit" name="add_time_slot" class="btn-primary w-full md:w-auto">
                                    <i class="fas fa-save mr-2"></i>Add Time Slot
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Time Slots List -->
                <div class="bg-white rounded-xl card-shadow">
                    <div class="p-4 md:p-6 border-b border-gray-200">
                        <h2 class="text-lg md:text-xl font-bold text-gray-900">
                            <i class="fas fa-list text-primary-red mr-2"></i>
                            Time Slots
                        </h2>
                    </div>
                    <div class="p-4 md:p-6">
                        <?php if (empty($time_slots)): ?>
                            <div class="text-center py-12">
                                <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-clock text-3xl text-gray-400"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">No Time Slots Found</h3>
                                <p class="text-gray-600">Add a new time slot above.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Departure Time</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Arrival Time</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($time_slots as $slot): ?>
                                            <tr class="table-hover">
                                                <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($slot['departure_time']) ?></td>
                                                <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($slot['arrival_time']) ?></td>
                                                <td class="px-6 py-4">
                                                    <a href="?delete_id=<?= $slot['id'] ?>" onclick="return confirm('Are you sure you want to delete this time slot?');" class="text-red-600 hover:text-red-800">
                                                        <i class="fas fa-trash"></i>
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
    </div>
</body>
</html>