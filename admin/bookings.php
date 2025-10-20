<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Handle cancel request
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $booking_id = (int)$_GET['cancel'];
    $conn->begin_transaction();
    try {
        // Update booking status
        $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled', payment_status = 'cancelled' WHERE id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $stmt->close();

        // Update payment status
        $stmt = $conn->prepare("UPDATE payments SET status = 'cancelled' WHERE booking_id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $success = 'Booking cancelled successfully.';
    } catch (Exception $e) {
        $conn->rollback();
        $error = 'Failed to cancel booking: ' . $e->getMessage();
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build the WHERE clause for filtering bookings
$where_conditions = ["b.status = 'confirmed'", "b.payment_status = 'paid'"];
$params = [];
$param_types = "";
if (!empty($search)) {
    $where_conditions[] = "(b.pnr LIKE ? OR b.passenger_name LIKE ? OR b.email LIKE ? OR b.phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= "ssss";
}
if (!empty($date_from)) {
    $where_conditions[] = "b.trip_date >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}
if (!empty($date_to)) {
    $where_conditions[] = "b.trip_date <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}
$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM bookings b
    JOIN trip_instances ti ON b.trip_id = ti.id
    JOIN trip_templates tt ON ti.template_id = tt.id
    JOIN cities c1 ON tt.pickup_city_id = c1.id
    JOIN cities c2 ON tt.dropoff_city_id = c2.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE $where_clause
";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_bookings = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_bookings / $per_page);
$stmt->close();

// Fetch bookings with search and date filters
$query = "
    SELECT 
        b.id, b.pnr, b.passenger_name, b.email, b.phone, b.emergency_contact, b.special_requests,
        b.trip_date, b.seat_number, b.total_amount, b.payment_status, b.status, b.created_at,
        c1.name AS pickup_city, c2.name AS dropoff_city, vt.type AS vehicle_type,
        v.vehicle_number, v.driver_name, ts.departure_time, ts.arrival_time,
        p.transaction_reference, p.payment_method, p.status AS payment_status_detail
    FROM bookings b
    JOIN trip_instances ti ON b.trip_id = ti.id
    JOIN trip_templates tt ON ti.template_id = tt.id
    JOIN cities c1 ON tt.pickup_city_id = c1.id
    JOIN cities c2 ON tt.dropoff_city_id = c2.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE $where_clause
    ORDER BY b.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - DGC Transports</title>
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
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-red: #e30613;
            --dark-red: #c70410;
            --black: #1a1a1a;
            --white: #ffffff;
            --gray: #f5f5f5;
            --light-gray: #e5e7eb;
            --accent-blue: #3b82f6;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--white), var(--gray));
        }
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        .btn-primary {
            background: linear-gradient(to right, var(--primary-red), var(--dark-red));
            color: var(--white);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(to right, var(--dark-red), var(--primary-red));
            transform: translateY(-2px);
        }
        .error-message {
            color: #ef4444;
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }
        .success-message {
            color: #10b981;
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        .table-row:hover {
            background-color: #f8fafc;
        }
        .details-content {
            max-height: 80vh;
            overflow-y: auto;
        }
        .mobile-card-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .mobile-card-label {
            font-weight: 500;
            color: #6b7280;
        }
        .mobile-card-content {
            font-weight: 600;
            color: #1a1a1a;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-confirmed {
            background-color: #d1fae5;
            color: #10b981;
        }
        .status-cancelled {
            background-color: #fee2e2;
            color: #ef4444;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            margin: 0 4px;
            border-radius: 8px;
            color: #374151;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .pagination a:hover {
            background-color: #e5e7eb;
        }
        .pagination .current {
            background-color: var(--primary-red);
            color: white;
        }
        @media (min-width: 768px) {
            .mobile-view {
                display: none;
            }
        }
        @media (max-width: 767px) {
            .desktop-view {
                display: none;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../templates/sidebar.php'; ?>

    <div class="md:ml-64 min-h-screen">
        <div class="pt-20 md:pt-0 p-4 md:p-6 lg:p-8">
            <div class="card p-6 sm:p-8 lg:p-10">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                        <i class="fas fa-ticket-alt text-primary-red mr-3"></i>Manage Bookings
                    </h1>
                    <form method="GET" class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by PNR, name, email, or phone" class="border border-gray-300 rounded-lg p-2 w-full sm:w-64">
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="border border-gray-300 rounded-lg p-2 w-full sm:w-40">
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="border border-gray-300 rounded-lg p-2 w-full sm:w-40">
                        <button type="submit" class="btn-primary"><i class="fas fa-search mr-2"></i>Search</button>
                    </form>
                </div>
                <?php if (isset($success)): ?>
                    <p class="success-message"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($success) ?></p>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <p class="error-message"><i class="fas fa-exclamation-circle mr-1"></i><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <?php if (empty($bookings)): ?>
                    <div class="text-center py-12">
                        <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-ticket-alt text-3xl text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No Bookings Found</h3>
                        <p class="text-gray-600 mb-6 max-w-sm mx-auto">No confirmed and paid bookings match your search criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4 mobile-view">
                        <?php foreach ($bookings as $booking): ?>
                            <div class="mobile-card-row bg-white shadow-md rounded-xl relative p-4">
                                <div class="absolute top-4 right-4">
                                    <div class="relative inline-block text-left">
                                        <button type="button" class="inline-flex justify-center items-center w-8 h-8 text-gray-400 hover:text-gray-600 focus:outline-none menu-toggle" data-id="<?= $booking['id'] ?>">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="origin-top-right absolute right-0 mt-2 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 hidden menu-content" data-id="<?= $booking['id'] ?>">
                                            <div class="py-1">
                                                <button onclick="showDetailsModal(<?= $booking['id'] ?>)" class="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100">
                                                    <i class="fas fa-info-circle mr-2"></i>Details
                                                </button>
                                                <?php if ($booking['status'] !== 'cancelled'): ?>
                                                    <button onclick="showCancelModal(<?= $booking['id'] ?>, '<?= htmlspecialchars($booking['pnr']) ?>')" class="text-gray-700 w-full text-left block px-4 py-2 text-sm hover:bg-gray-100">
                                                        <i class="fas fa-times mr-2"></i>Cancel
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mobile-card-item">
                                    <span class="mobile-card-label"><i class="fas fa-ticket-alt text-primary-red mr-2"></i>PNR:</span>
                                    <span class="mobile-card-content"><?= htmlspecialchars($booking['pnr']) ?></span>
                                </div>
                                <div class="mobile-card-item">
                                    <span class="mobile-card-label"><i class="fas fa-user text-primary-red mr-2"></i>Passenger:</span>
                                    <span class="mobile-card-content"><?= htmlspecialchars($booking['passenger_name']) ?></span>
                                </div>
                                <div class="mobile-card-item">
                                    <span class="mobile-card-label"><i class="fas fa-map-marker-alt text-primary-red mr-2"></i>Route:</span>
                                    <span class="mobile-card-content"><?= htmlspecialchars($booking['pickup_city'] ?? 'N/A') ?> → <?= htmlspecialchars($booking['dropoff_city'] ?? 'N/A') ?></span>
                                </div>
                                <div class="mobile-card-item">
                                    <span class="mobile-card-label"><i class="fas fa-calendar-alt text-primary-red mr-2"></i>Date:</span>
                                    <span class="mobile-card-content"><?= date('M j, Y', strtotime($booking['trip_date'])) ?></span>
                                </div>
                                <div class="mobile-card-item">
                                    <span class="mobile-card-label"><i class="fas fa-money-bill-wave text-primary-red mr-2"></i>Amount:</span>
                                    <span class="mobile-card-content">₦<?= number_format($booking['total_amount'], 0) ?></span>
                                </div>
                                <div class="mobile-card-item">
                                    <span class="mobile-card-label"><i class="fas fa-info-circle text-primary-red mr-2"></i>Status:</span>
                                    <span class="mobile-card-content status-badge status-<?= $booking['status'] ?>"><?= ucfirst($booking['status']) ?></span>
                                </div>
                                <div class="mobile-card-item">
                                    <span class="mobile-card-label"><i class="fas fa-calendar-check text-primary-red mr-2"></i>Reservation Date:</span>
                                    <span class="mobile-card-content"><?= date('M j, Y H:i', strtotime($booking['created_at'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PNR</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Passenger</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trip Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reservation Date</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($bookings as $booking): ?>
                                    <tr class="table-row">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <div class="flex items-center">
                                                <i class="fas fa-ticket-alt text-primary-red mr-2"></i>
                                                <span><?= htmlspecialchars($booking['pnr']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="flex items-center">
                                                <i class="fas fa-user text-primary-red mr-2"></i>
                                                <span><?= htmlspecialchars($booking['passenger_name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="flex items-center">
                                                <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                                                <span><?= htmlspecialchars($booking['pickup_city'] ?? 'N/A') ?> → <?= htmlspecialchars($booking['dropoff_city'] ?? 'N/A') ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="flex items-center">
                                                <i class="fas fa-calendar-alt text-primary-red mr-2"></i>
                                                <span><?= date('M j, Y', strtotime($booking['trip_date'])) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="flex items-center">
                                                <i class="fas fa-money-bill-wave text-primary-red mr-2"></i>
                                                <span>₦<?= number_format($booking['total_amount'], 0) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="flex items-center">
                                                <i class="fas fa-info-circle text-primary-red mr-2"></i>
                                                <span class="status-badge status-<?= $booking['status'] ?>"><?= ucfirst($booking['status']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="flex items-center">
                                                <i class="fas fa-calendar-check text-primary-red mr-2"></i>
                                                <span><?= date('M j, Y H:i', strtotime($booking['created_at'])) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="relative inline-block text-left">
                                                <button type="button" class="inline-flex justify-center items-center w-8 h-8 text-gray-400 hover:text-gray-600 focus:outline-none menu-toggle" data-id="<?= $booking['id'] ?>">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="origin-top-right absolute right-0 mt-2 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 hidden menu-content" data-id="<?= $booking['id'] ?>">
                                                    <div class="py-1">
                                                        <button onclick="showDetailsModal(<?= $booking['id'] ?>)" class="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100">
                                                            <i class="fas fa-info-circle mr-2"></i>Details
                                                        </button>
                                                        <?php if ($booking['status'] !== 'cancelled'): ?>
                                                            <button onclick="showCancelModal(<?= $booking['id'] ?>, '<?= htmlspecialchars($booking['pnr']) ?>')" class="text-gray-700 w-full text-left block px-4 py-2 text-sm hover:bg-gray-100">
                                                                <i class="fas fa-times mr-2"></i>Cancel
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <div class="p-4 border-t border-gray-200">
                            <div class="pagination flex justify-center">
                                <?php 
                                $query_params = $_GET;
                                unset($query_params['page']);
                                $base_url = '?' . http_build_query($query_params) . (empty($query_params) ? '' : '&') . 'page=';
                                ?>
                                <?php if ($page > 1): ?>
                                    <a href="<?= $base_url ?>1"><i class="fas fa-angle-double-left"></i></a>
                                    <a href="<?= $base_url . ($page - 1) ?>"><i class="fas fa-angle-left"></i></a>
                                <?php endif; ?>
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="current"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="<?= $base_url . $i ?>"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= $base_url . ($page + 1) ?>"><i class="fas fa-angle-right"></i></a>
                                    <a href="<?= $base_url . $total_pages ?>"><i class="fas fa-angle-double-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cancel Modal -->
    <div id="cancelModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-50 hidden flex items-center justify-center modal-overlay">
        <div class="modal-content bg-white rounded-lg p-6 max-w-sm mx-auto shadow-xl">
            <h3 class="text-xl font-bold mb-4 text-center">Confirm Cancellation</h3>
            <p id="cancelMessage" class="text-gray-700 mb-6 text-center"></p>
            <div class="flex justify-center space-x-4">
                <button id="cancelCancelButton" class="btn-primary bg-gray-300 text-gray-800 hover:bg-gray-400 transform-none">Cancel</button>
                <a id="confirmCancelLink" href="#" class="btn-primary bg-red-600 hover:bg-red-700">Confirm</a>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-50 hidden flex items-center justify-center modal-overlay">
        <div class="modal-content bg-white rounded-lg p-6 max-w-lg mx-auto shadow-xl details-content">
            <h3 class="text-xl font-bold mb-4 text-center">Booking Details</h3>
            <div id="detailsContent" class="space-y-4"></div>
            <div class="flex justify-center mt-6">
                <button id="closeDetailsButton" class="btn-primary bg-gray-300 text-gray-800 hover:bg-gray-400 transform-none">Close</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Cancel Modal
            const cancelModal = document.getElementById('cancelModal');
            const cancelMessage = document.getElementById('cancelMessage');
            const confirmCancelLink = document.getElementById('confirmCancelLink');
            const cancelCancelButton = document.getElementById('cancelCancelButton');
            window.showCancelModal = (id, pnr) => {
                cancelMessage.textContent = `Are you sure you want to cancel the booking with PNR ${pnr}?`;
                confirmCancelLink.href = `bookings.php?cancel=${id}`;
                cancelModal.classList.remove('hidden');
            };
            cancelCancelButton.addEventListener('click', () => {
                cancelModal.classList.add('hidden');
            });
            window.addEventListener('click', (event) => {
                if (event.target === cancelModal) {
                    cancelModal.classList.add('hidden');
                }
            });

            // Details Modal
            const detailsModal = document.getElementById('detailsModal');
            const detailsContent = document.getElementById('detailsContent');
            const closeDetailsButton = document.getElementById('closeDetailsButton');
            window.showDetailsModal = (id) => {
                const booking = <?php echo json_encode($bookings); ?>.find(b => b.id === id);
                if (booking) {
                    detailsContent.innerHTML = `
                        <p><strong>PNR:</strong> ${booking.pnr}</p>
                        <p><strong>Passenger Name:</strong> ${booking.passenger_name}</p>
                        <p><strong>Email:</strong> ${booking.email}</p>
                        <p><strong>Phone:</strong> ${booking.phone}</p>
                        <p><strong>Emergency Contact:</strong> ${booking.emergency_contact || 'N/A'}</p>
                        <p><strong>Special Requests:</strong> ${booking.special_requests || 'None'}</p>
                        <p><strong>Route:</strong> ${booking.pickup_city || 'N/A'} → ${booking.dropoff_city || 'N/A'}</p>
                        <p><strong>Trip Date:</strong> ${new Date(booking.trip_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                        <p><strong>Time:</strong> ${booking.departure_time ? new Date('1970-01-01T' + booking.departure_time + 'Z').toLocaleTimeString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true }) : 'N/A'} - ${booking.arrival_time ? new Date('1970-01-01T' + booking.arrival_time + 'Z').toLocaleTimeString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true }) : 'N/A'}</p>
                        <p><strong>Vehicle:</strong> ${booking.vehicle_type || 'N/A'} (${booking.vehicle_number || 'N/A'}, Driver: ${booking.driver_name || 'N/A'})</p>
                        <p><strong>Seat Number:</strong> ${booking.seat_number}</p>
                        <p><strong>Total Amount:</strong> ₦${Number(booking.total_amount).toLocaleString('en-NG', { minimumFractionDigits: 0 })}</p>
                        <p><strong>Booking Status:</strong> ${booking.status}</p>
                        <p><strong>Payment Status:</strong> ${booking.payment_status}</p>
                        <p><strong>Transaction Reference:</strong> ${booking.transaction_reference || 'N/A'}</p>
                        <p><strong>Payment Method:</strong> ${booking.payment_method || 'N/A'}</p>
                        <p><strong>Payment Status Detail:</strong> ${booking.payment_status_detail || 'N/A'}</p>
                        <p><strong>Reservation Date:</strong> ${new Date(booking.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true })}</p>
                    `;
                    detailsModal.classList.remove('hidden');
                }
            };
            closeDetailsButton.addEventListener('click', () => {
                detailsModal.classList.add('hidden');
            });
            window.addEventListener('click', (event) => {
                if (event.target === detailsModal) {
                    detailsModal.classList.add('hidden');
                }
            });

            // Dropdown Menu
            const menuToggles = document.querySelectorAll('.menu-toggle');
            menuToggles.forEach(toggle => {
                toggle.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const id = toggle.dataset.id;
                    const menu = document.querySelector(`.menu-content[data-id="${id}"]`);
                    document.querySelectorAll('.menu-content').forEach(otherMenu => {
                        if (otherMenu !== menu) {
                            otherMenu.classList.add('hidden');
                        }
                    });
                    menu.classList.toggle('hidden');
                });
            });
            window.addEventListener('click', (event) => {
                document.querySelectorAll('.menu-content').forEach(menu => {
                    if (!menu.classList.contains('hidden')) {
                        menu.classList.add('hidden');
                    }
                });
            });

            // Sidebar Toggle
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            const sidebarToggleMobile = document.getElementById('sidebar-toggle-mobile');
            const sidebarClose = document.getElementById('sidebar-close');
            if (sidebarToggleMobile && sidebarClose && sidebar && sidebarOverlay) {
                sidebarToggleMobile.addEventListener('click', () => {
                    sidebar.classList.remove('-translate-x-full');
                    sidebarOverlay.classList.remove('hidden');
                });
                const closeSidebar = () => {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                };
                sidebarClose.addEventListener('click', closeSidebar);
                sidebarOverlay.addEventListener('click', closeSidebar);
            }
        });
    </script>
</body>
</html>