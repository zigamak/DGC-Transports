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

// Fetch bookings with search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "
    SELECT 
        b.id, b.pnr, b.passenger_name, b.email, b.phone, b.emergency_contact, b.special_requests,
        b.trip_date, b.seat_number, b.total_amount, b.payment_status, b.status, b.created_at,
        c1.name AS pickup_city, c2.name AS dropoff_city, vt.type AS vehicle_type,
        v.vehicle_number, v.driver_name, ts.departure_time, ts.arrival_time,
        p.transaction_reference, p.payment_method, p.status AS payment_status_detail
    FROM bookings b
    LEFT JOIN trip_templates tt ON b.template_id = tt.id
    LEFT JOIN cities c1 ON tt.pickup_city_id = c1.id
    LEFT JOIN cities c2 ON tt.dropoff_city_id = c2.id
    LEFT JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    LEFT JOIN vehicles v ON tt.vehicle_id = v.id
    LEFT JOIN time_slots ts ON tt.time_slot_id = ts.id
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE (b.pnr LIKE ? OR b.passenger_name LIKE ? OR b.email LIKE ? OR b.phone LIKE ?)
    ORDER BY b.created_at DESC
";
$search_param = "%$search%";
$stmt = $conn->prepare($query);
$stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            display: flex;
            min-height: 100vh;
        }
        /* Header Styles */
        header {
            background: #000;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 40;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
        }
        .header-logo img {
            height: 40px;
        }
        .header-nav {
            display: flex;
            gap: 20px;
        }
        .header-nav a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }
        .header-nav a:hover {
            color: #dc2626;
        }
        /* Mobile Menu */
        #mobile-menu {
            display: none;
            background: #000;
            padding: 20px;
        }
        #mobile-menu.active {
            display: block;
        }
        .mobile-nav a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 10px;
            font-weight: 600;
            transition: color 0.2s;
        }
        .mobile-nav a:hover {
            color: #dc2626;
        }
        /* Toggle Buttons */
        .toggle-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            display: none;
        }
        .toggle-btn:hover {
            color: #dc2626;
        }
        /* Main Content */
        main {
            flex: 1;
            padding-top: 60px;
            background: linear-gradient(135deg, var(--white), var(--gray));
            transition: margin-left 0.3s ease-in-out;
        }
        /* Responsive Design */
        @media (min-width: 768px) {
            main {
                margin-left: 256px; /* Matches w-64 (64 * 4px = 256px) */
            }
            .header-nav {
                display: flex;
            }
            .toggle-btn {
                display: none;
            }
            header.md-hidden {
                display: none;
            }
        }
        @media (max-width: 767px) {
            .toggle-btn {
                display: block;
            }
            .header-nav {
                display: none;
            }
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
        /* Mobile-first card layout for table rows */
        .mobile-card-row {
            display: flex;
            flex-direction: column;
            padding: 1rem;
            border-radius: 0.5rem;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 0.75rem;
        }
        .mobile-card-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .mobile-card-item:last-child {
            margin-bottom: 0;
        }
        .mobile-card-label {
            font-weight: 600;
            color: #4b5563;
            width: 120px;
            min-width: 120px;
        }
        .mobile-card-content {
            flex-grow: 1;
        }
        /* Desktop table layout */
        @media (min-width: 768px) {
            .mobile-card-row {
                display: none;
            }
            .table-container {
                display: block;
            }
            .table-header {
                background: var(--gray);
                color: var(--black);
                font-weight: 600;
            }
            .table-row:hover {
                background: #f9fafb;
            }
        }
        @media (max-width: 767px) {
            .table-container {
                display: none;
            }
        }
        .text-red-600 { color: #e30613; }
        .bg-red-600 { background-color: #e30613; }
        .hover\:bg-red-600:hover { background-color: #e30613; }
        .hover\:text-red-600:hover { color: #e30613; }
        .bg-red-700 { background-color: #c70410; }
        .hover\:bg-red-700:hover { background-color: #c70410; }
        /* Details modal */
        .details-content {
            max-height: 80vh;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php if (isLoggedIn()): ?>
        <?php include '../templates/sidebar.php'; ?>
    <?php endif; ?>

    <div class="flex-1">
        <header class="fixed top-0 left-0 w-full bg-black text-white p-4 shadow-md flex items-center justify-between z-40 md-hidden">
            <button id="sidebar-toggle-mobile" class="toggle-btn">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <div class="flex-1 text-center">
                <a href="<?= SITE_URL ?>">
                    <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="Logo" class="h-8 mx-auto">
                </a>
            </div>
            <div class="w-10"></div>
        </header>
        <div id="mobile-menu" class="mobile-menu">
            <nav class="mobile-nav">
                <a href="<?= SITE_URL ?>/index.php">Book a Trip</a>
                <a href="<?= SITE_URL ?>/bookings/manage_booking.php">Manage Booking</a>
                <a href="<?= SITE_URL ?>/contact.php">Contact Us</a>
                <a href="<?= SITE_URL ?>/login.php">Login</a>
            </nav>
        </div>

        <main class="ml-0 md:ml-64 p-4 sm:p-6 lg:p-10">
            <div class="container mx-auto">
                <div class="card p-6 sm:p-8 lg:p-10">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8">
                        <h1 class="text-3xl sm:text-4xl font-bold text-gray-800 mb-4 sm:mb-0">
                            <i class="fas fa-ticket-alt text-primary-red mr-3"></i>Manage Bookings
                        </h1>
                        <form method="GET" class="flex items-center">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by PNR, name, email, or phone" class="border border-gray-300 rounded-lg p-2 w-full sm:w-64">
                            <button type="submit" class="btn-primary ml-2"><i class="fas fa-search mr-2"></i>Search</button>
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
                            <i class="fas fa-ticket-alt text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-2xl font-bold text-gray-700 mb-2">No Bookings Found</h3>
                            <p class="text-gray-500 mb-6">No bookings match your search criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4 md:hidden">
                            <?php foreach ($bookings as $booking): ?>
                                <div class="mobile-card-row bg-white shadow-md rounded-xl relative">
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
                                        <span class="mobile-card-content">₦<?= number_format($booking['total_amount'], 2) ?></span>
                                    </div>
                                    <div class="mobile-card-item">
                                        <span class="mobile-card-label"><i class="fas fa-info-circle text-primary-red mr-2"></i>Status:</span>
                                        <span class="mobile-card-content"><?= htmlspecialchars($booking['status']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="overflow-x-auto hidden md:block">
                            <table class="w-full table-auto border-collapse">
                                <thead>
                                    <tr class="table-header rounded-lg">
                                        <th class="px-4 py-3 text-left">PNR</th>
                                        <th class="px-4 py-3 text-left">Passenger</th>
                                        <th class="px-4 py-3 text-left">Route</th>
                                        <th class="px-4 py-3 text-left">Trip Date</th>
                                        <th class="px-4 py-3 text-left">Amount</th>
                                        <th class="px-4 py-3 text-left">Status</th>
                                        <th class="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                        <tr class="table-row border-b border-gray-200">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-ticket-alt text-primary-red mr-2"></i>
                                                    <span><?= htmlspecialchars($booking['pnr']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-user text-primary-red mr-2"></i>
                                                    <span><?= htmlspecialchars($booking['passenger_name']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                                                    <span><?= htmlspecialchars($booking['pickup_city'] ?? 'N/A') ?> → <?= htmlspecialchars($booking['dropoff_city'] ?? 'N/A') ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-calendar-alt text-primary-red mr-2"></i>
                                                    <span><?= date('M j, Y', strtotime($booking['trip_date'])) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-money-bill-wave text-primary-red mr-2"></i>
                                                    <span>₦<?= number_format($booking['total_amount'], 2) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-info-circle text-primary-red mr-2"></i>
                                                    <span><?= htmlspecialchars($booking['status']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-right relative">
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
                    <?php endif; ?>
                </div>
            </div>
        </main>
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
                        <p><strong>Time:</strong> ${booking.departure_time || 'N/A'} - ${booking.arrival_time || 'N/A'}</p>
                        <p><strong>Vehicle:</strong> ${booking.vehicle_type || 'N/A'} (${booking.vehicle_number || 'N/A'}, Driver: ${booking.driver_name || 'N/A'})</p>
                        <p><strong>Seat Number:</strong> ${booking.seat_number}</p>
                        <p><strong>Total Amount:</strong> ₦${Number(booking.total_amount).toFixed(2)}</p>
                        <p><strong>Booking Status:</strong> ${booking.status}</p>
                        <p><strong>Payment Status:</strong> ${booking.payment_status}</p>
                        <p><strong>Transaction Reference:</strong> ${booking.transaction_reference || 'N/A'}</p>
                        <p><strong>Payment Method:</strong> ${booking.payment_method || 'N/A'}</p>
                        <p><strong>Payment Status Detail:</strong> ${booking.payment_status_detail || 'N/A'}</p>
                        <p><strong>Created At:</strong> ${new Date(booking.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true })}</p>
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

            // Sidebar and Mobile Menu
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const mobileMenuCancel = document.getElementById('mobile-menu-cancel');
            const mobileMenu = document.getElementById('mobile-menu');
            const mobileMenuIcon = document.getElementById('mobile-menu-icon');
            if (mobileMenuToggle && mobileMenuCancel && mobileMenu && mobileMenuIcon) {
                mobileMenuToggle.addEventListener('click', function() {
                    mobileMenu.classList.toggle('active');
                    mobileMenuToggle.style.display = mobileMenu.classList.contains('active') ? 'none' : 'block';
                    mobileMenuCancel.style.display = mobileMenu.classList.contains('active') ? 'block' : 'none';
                    mobileMenuIcon.classList.toggle('fa-bars');
                    mobileMenuIcon.classList.toggle('fa-times');
                });
                mobileMenuCancel.addEventListener('click', function() {
                    mobileMenu.classList.remove('active');
                    mobileMenuToggle.style.display = 'block';
                    mobileMenuCancel.style.display = 'none';
                    mobileMenuIcon.classList.remove('fa-times');
                    mobileMenuIcon.classList.add('fa-bars');
                });
            }
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